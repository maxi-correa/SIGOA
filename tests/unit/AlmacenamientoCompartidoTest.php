<?php

use CodeIgniter\Config\DotEnv;
use CodeIgniter\Test\CIUnitTestCase;
use Config\SigoaStorage;

/**
 * Contrato de la raíz física de almacenamiento.
 *
 * El fallo observado en la prueba manual no era del código de carpetas sino
 * de la configuración: la raíz apuntaba a `C:\Compartida\SIGOA`, un volumen
 * local del servidor, cuando el sistema escribe sobre un recurso compartido
 * de red.
 *
 * Estas pruebas fijan el contrato para que el error no vuelva a colarse sin
 * que nadie lo note:
 *
 *   - una letra de unidad no es una ruta válida para producción, porque es un
 *     alias de la sesión del usuario y el proceso de Apache no la ve;
 *   - `Z:\…` tampoco lo es, aunque apunte a un recurso de red: el mismo fallo
 *     de visibilidad, y además dependiente de quién la mapea;
 *   - en producción la raíz debe ser UNC (`//equipo/recurso/SIGOA`).
 *
 * También fijan cómo se declara: `SIGOA_STORAGE_COMPARTIDO` admite una ruta o
 * un booleano, y una ruta deja obsoleta a `SIGOA_STORAGE_PATH` en lugar de
 * dejar que un valor antiguo sobreviva en silencio.
 *
 * La comprobación es declarativa: no toca disco, red ni base de datos, y
 * ningún test depende de que el recurso de red esté montado. La prueba de
 * escritura real sobre el recurso queda para el despliegue, porque depende de
 * los permisos en el equipo donde se ejecuta.
 *
 * @internal
 */
final class AlmacenamientoCompartidoTest extends CIUnitTestCase
{
    /** @var array<string, string> */
    private array $original = [];

    /**
     * Variables que se vacían antes de cada prueba.
     */
    private const CLAVES = ['SIGOA_STORAGE_PATH', 'SIGOA_STORAGE_COMPARTIDO'];

    protected function setUp(): void
    {
        parent::setUp();

        /* El `.env` real del proyecto declara la raíz de producción, y `env()`
           la leería igual: sin vaciarla, cada prueba heredaría el valor del
           despliegue en vez del que declara. Hay que limpiar las tres
           fuentes —`$_ENV`, `$_SERVER` y `putenv()`— porque CodeIgniter
           escribe en las tres al cargar el `.env`. */
        foreach (self::CLAVES as $clave) {
            $entorno = getenv($clave);
            $this->original[$clave] = $entorno === false ? '' : $entorno;

            unset($_ENV[$clave], $_SERVER[$clave]);
            putenv($clave);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->original as $clave => $valor) {
            unset($_ENV[$clave], $_SERVER[$clave]);
            putenv($clave);

            if ($valor !== '') {
                $_ENV[$clave] = $valor;
            }
        }

        parent::tearDown();
    }

    /* ---------------------------------------------------------------- */
    /* Resolución desde el entorno                                      */
    /* ---------------------------------------------------------------- */

    public function testLeeLaRaizDesdeElEntorno(): void
    {
        $_ENV['SIGOA_STORAGE_PATH'] = '\\\\SERVIDOR\\Compartido KM5\\SIGOA';

        $config = new SigoaStorage();

        $this->assertSame('\\\\SERVIDOR\\Compartido KM5\\SIGOA', $config->storagePath);
        $this->assertTrue($config->esRutaDeRed());
    }

    public function testLaRaizSeDeclaraEnLaPropiaVariableCompartido(): void
    {
        /* Forma de producción: una ruta en SIGOA_STORAGE_COMPARTIDO. */
        $_ENV['SIGOA_STORAGE_COMPARTIDO'] = '//DESKTOP-RJ9VDRF/Compartido KM5/SIGOA';

        $config = new SigoaStorage();

        $this->assertSame('//DESKTOP-RJ9VDRF/Compartido KM5/SIGOA', $config->storagePath);
        $this->assertTrue($config->esRutaDeRed());
        $this->assertTrue($config->esAbsoluta());
        $this->assertFalse($config->esUnidadDeDisco());
    }

    public function testDeclararLaRutaImplicaQueElDespliegueEsCompartido(): void
    {
        $_ENV['SIGOA_STORAGE_COMPARTIDO'] = '//DESKTOP-RJ9VDRF/Compartido KM5/SIGOA';

        $config = new SigoaStorage();

        /* Declarar una ruta ya dice cuál es el almacenamiento: no hace falta
           un segundo indicador, y si lo hubiera con "0" sería contradictorio. */
        $this->assertTrue($config->compartido);
        $this->assertSame([], $config->problemasDeContrato());
    }

    public function testLaRutaDeclaradaGanaSobreLaVariableAuxiliar(): void
    {
        /* Un valor antiguo no debe sobrevivir en silencio junto a la raíz
           nueva, o la escritura seguiría yendo al sitio anterior. */
        $_ENV['SIGOA_STORAGE_COMPARTIDO'] = '//DESKTOP-RJ9VDRF/Compartido KM5/SIGOA';
        $_ENV['SIGOA_STORAGE_PATH']       = 'C:\\Compartida\\SIGOA';

        $config = new SigoaStorage();

        $this->assertSame('//DESKTOP-RJ9VDRF/Compartido KM5/SIGOA', $config->storagePath);
    }

    public function testLaRutaIgnoraLaBarraFinal(): void
    {
        $_ENV['SIGOA_STORAGE_COMPARTIDO'] = '\\\\SERVIDOR\\Compartido KM5\\SIGOA\\';

        $config = new SigoaStorage();

        $this->assertSame('\\\\SERVIDOR\\Compartido KM5\\SIGOA', $config->storagePath);
    }

    public function testUnidadMapeadaNoSeConsideraRutaDeRed(): void
    {
        $_ENV['SIGOA_STORAGE_COMPARTIDO'] = 'Z:\\SIGOA';

        $config = new SigoaStorage();

        /* El caso que motivó la fase: una letra de unidad apunta a la red
           desde la sesión del usuario, pero no lo es para el servidor. */
        $this->assertTrue($config->esUnidadDeDisco());
        $this->assertFalse($config->esRutaDeRed());
    }

    /**
     * @dataProvider proveedorValoresBooleanos
     */
    public function testElValorCompartidoSeNormaliza(mixed $valor, bool $esperado): void
    {
        $_ENV['SIGOA_STORAGE_COMPARTIDO'] = $valor;

        $config = new SigoaStorage();

        $this->assertSame($esperado, $config->compartido);
    }

    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function proveedorValoresBooleanos(): iterable
    {
        yield 'entorno true'    => [true, true];
        yield 'uno'            => ['1', true];
        yield 'texto true'     => ['true', true];
        yield 'entorno false'  => [false, false];
        yield 'cero'           => ['0', false];
        yield 'texto false'    => ['false', false];
        yield 'desconocido'    => ['', false];
    }

    /* ---------------------------------------------------------------- */
    /* Contrato de producción                                            */
    /* ---------------------------------------------------------------- */

    /**
     * @dataProvider proveedorRaicesDeProduccion
     */
    public function testProduccionExigeRutaDeRed(string $raiz, bool $valida): void
    {
        $config = $this->configuracion($raiz, true);

        $this->assertSame($valida, $config->problemasDeContrato() === [], $raiz);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function proveedorRaicesDeProduccion(): iterable
    {
        yield 'recurso UNC' => ['\\\\SERVIDOR\\Compartido KM5\\SIGOA', true];

        /* El valor que había en `.env`: volumen local del servidor. */
        yield 'volumen local' => ['C:\\Compartida\\SIGOA', false];

        /* Unidades mapeadas por un usuario concreto. */
        yield 'unidad mapeada Z' => ['Z:\\SIGOA', false];
        yield 'unidad mapeada Y' => ['Y:\\Compartido KM5\\SIGOA', false];

        /* Rutas relativas: nunca válidas para un servicio. */
        yield 'relativa' => ['SIGOA', false];
        yield 'relativa con padre' => ['../SIGOA', false];
    }

    public function testDesarrolloAdmiteUnaRutaLocal(): void
    {
        $config = $this->configuracion('C:\\writable\\sigoa', false);

        $this->assertSame([], $config->problemasDeContrato());
    }

    public function testSinRaizConfiguradaElContratoFallaSiempre(): void
    {
        $config = $this->configuracion('', false);

        $problemas = $config->problemasDeContrato();

        $this->assertCount(1, $problemas);
        $this->assertStringContainsString('SIGOA_STORAGE_COMPARTIDO', $problemas[0]);
    }

    public function testElMensajeExplicaPorQueFallaUnaUnidad(): void
    {
        $problemas = $this->configuracion('Z:\\SIGOA', true)->problemasDeContrato();

        $this->assertCount(1, $problemas);
        $this->assertStringContainsString('Apache', $problemas[0]);
    }

    public function testUnaRutaRelativaSeReportaComoTal(): void
    {
        $problemas = $this->configuracion('SIGOA\\compartido', false)->problemasDeContrato();

        $this->assertCount(1, $problemas);
        $this->assertStringContainsString('absoluta', $problemas[0]);
    }

    public function testLaVersionVersionadaNoDeclaraRecursoDeRed(): void
    {
        /* La plantilla `env` se versiona: no puede fijar el nombre del equipo
           ni una letra de unidad, porque el despliegue es distinto en cada
           instalación. Solo puede declarar la intención. */
        $plantilla = (string) file_get_contents(ROOTPATH . 'env');

        $this->assertStringContainsString('SIGOA_STORAGE_PATH', $plantilla);
        $this->assertStringContainsString('SIGOA_STORAGE_COMPARTIDO', $plantilla);

        /* Solo importan las asignaciones activas: un ejemplo comentado de
           desarrollo es legítimo, una raíz fijada en la plantilla no lo es,
           porque se copiaría a todos los despliegues. */
        $this->assertDoesNotMatchRegularExpression(
            "/^[^#\n]*SIGOA_STORAGE_(?:PATH|COMPARTIDO)\s*=\s*'?[A-Za-z]:/mi",
            $plantilla,
            'La plantilla no puede fijar una letra de unidad: el despliegue cambia en cada instalación'
        );
    }

    public function testLaPlantillaNoPideCredenciales(): void
    {
        /* El acceso al recurso lo resuelve la cuenta de servicio de Windows.
           Pedir usuario o contraseña en `.env` los expondría en un archivo
           sin versionar que se copia entre equipos. */
        $plantilla = (string) file_get_contents(ROOTPATH . 'env');

        $this->assertDoesNotMatchRegularExpression(
            "/SIGOA_STORAGE_\\w*\\s*=\\s*'?.?[^=\\n]*(?:PASSWORD|PASS|USER|USUARIO|CREDENTIAL)/i",
            $plantilla
        );
    }

    public function testLaRaizDelDespliegueRealEsValida(): void
    {
        /* La ruta del recurso UNC de producción. Se fija aquí a propósito:
         * si alguien cambia la declaración, esta prueba avisa. */
        $_ENV['SIGOA_STORAGE_COMPARTIDO'] = '//DESKTOP-RJ9VDRF/Compartido KM5/SIGOA';

        $config = new SigoaStorage();

        $this->assertSame('//DESKTOP-RJ9VDRF/Compartido KM5/SIGOA', $config->storagePath);
        $this->assertTrue($config->esRutaDeRed());
        $this->assertTrue($config->compartido);
        $this->assertSame([], $config->problemasDeContrato());
    }

    /* ---------------------------------------------------------------- */
    /* Cómo se escribe la ruta en el `.env`                             */
    /* ---------------------------------------------------------------- */

    /**
     * El nombre del recurso contiene espacios, así que la ruta debe ir entre
     * comillas. Y dentro de las comillas, CodeIgniter colapsa las barras
     * inversas dobles: escribirlas sin duplicar deja `\\SERVIDOR` en vez de
     * `\\SERVIDOR`, que es una ruta que no existe.
     *
     * Estas pruebas fijan la forma correcta de cada grafía contra el lector
     * real, para que un despliegue mal escrito falle en las pruebas y no en
     * producción.
     *
     * @dataProvider proveedorFormasDeRutaEnEntorno
     */
    public function testLasFormasDocumentadasSeLeenComoRutaDeRed(string $linea, string $esperado): void
    {
        $leido = $this->leerDesdeEnv($linea);

        $this->assertSame($esperado, $leido, 'La forma documentada debe leerse tal cual');

        $config = $this->configuracion($leido, true);

        $this->assertTrue($config->esRutaDeRed());
        $this->assertSame([], $config->problemasDeContrato());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function proveedorFormasDeRutaEnEntorno(): iterable
    {
        yield 'barras inversas duplicadas' => [
            "SIGOA_STORAGE_PATH = '\\\\\\\\SERVIDOR\\\\Compartido KM5\\\\SIGOA'",
            '\\\\SERVIDOR\\Compartido KM5\\SIGOA',
        ];

        yield 'barras normales' => [
            "SIGOA_STORAGE_PATH = '//SERVIDOR/Compartido KM5/SIGOA'",
            '//SERVIDOR/Compartido KM5/SIGOA',
        ];
    }

    public function testUnaUnidadSinComillasYConEspaciosFallaAlArrancar(): void
    {
        /* No es una prueba de SIGOA sino del lector de CodeIgniter: sirve
           para dejar constancia de que el fallo por comillas es un error de
           arranque, no un aviso recuperable. */
        $this->expectException(\CodeIgniter\Exceptions\InvalidArgumentException::class);

        $this->leerDesdeEnv('SIGOA_STORAGE_PATH = \\\\SERVIDOR\\Compartido KM5\\SIGOA');
    }

    private function leerDesdeEnv(string $contenido): string
    {
        $directorio = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sigoa_env_' . bin2hex(random_bytes(4));

        if (! is_dir($directorio) && ! mkdir($directorio, 0700, true) && ! is_dir($directorio)) {
            $this->fail('No se pudo crear el directorio temporal para leer el .env.');
        }

        try {
            file_put_contents($directorio . DIRECTORY_SEPARATOR . '.env', $contenido . PHP_EOL);

            $variables = (new DotEnv($directorio))->parse();
        } finally {
            @unlink($directorio . DIRECTORY_SEPARATOR . '.env');
            @rmdir($directorio);
        }

        $this->assertIsArray($variables, 'El .env de prueba debe poder leerse.');
        $this->assertArrayHasKey('SIGOA_STORAGE_PATH', $variables);

        return (string) $variables['SIGOA_STORAGE_PATH'];
    }

    private function configuracion(string $raiz, bool $compartido): SigoaStorage
    {
        $config = new SigoaStorage();

        $config->storagePath = rtrim($raiz, '/\\');
        $config->compartido  = $compartido;

        return $config;
    }
}
