<?php

use App\Libraries\Uuid;
use App\Services\ObraAlmacenamiento;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Galería de fotografías de una inspección (Fase E.5).
 *
 * Render real y archivos reales: se escriben JPEG en una raíz de almacenamiento
 * temporal, en la estructura real `OBR-XXXXXX/fecha/HH-mm-ss/{IMAGENES,THUMBNAILS}`
 * que compone `ObraAlmacenamiento`, y se sirve la galería y los archivos por
 * HTTP como lo haría el navegador.
 *
 * Lo que se verifica es el comportamiento observable y sus rechazos:
 *
 * * una inspección sin fotografías dice que no tiene;
 * * con fotografías muestra las miniaturas, el pie y el enlace al original, en
 *   orden de registro;
 * * una fotografía anulada no aparece ni se sirve;
 * * la miniatura y el original responden con el contenido del archivo correcto,
 *   con su `Content-Type`;
 * * una fotografía no registrada, de una obra ajena o anulada no se sirve;
 * * el histórico de una obra FINALIZADA sigue consultable;
 * * una ruta relativa que sale de la raíz, o un archivo que no es imagen, no se
 *   sirven: es la protección contra `path traversal`;
 * * nada de esto modifica datos.
 *
 * @internal
 */
final class InspectorInspeccionesGaleriaRenderTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private BaseConnection $conn;

    private string $tmp;

    private string $raizOriginal;

    private string $codigoObra = '';

    private int $obraId;

    private int $inspeccionId;

    private int $inspectorId;

    private int $estadoEjecucion;

    private int $estadoFinalizada;

    /** @var list<int> */
    private array $usuariosIds = [];

    /** Carpeta física de la inspección de prueba: `OBR-XXXXXX/fecha/HH-mm-ss`. */
    private string $carpeta = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->conn = db_connect();

        $this->crearTablas();

        $this->estadoEjecucion  = $this->insertarEstado('EN EJECUCIÓN');
        $this->estadoFinalizada = $this->insertarEstado('FINALIZADA');

        $this->tmp = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'sigoa_e5_' . bin2hex(random_bytes(4));

        mkdir($this->tmp, 0755, true);

        $this->codigoObra = $this->codigoValido();

        $this->conn->table('obras')->insert([
            'codigo'         => $this->codigoObra,
            'nombre'         => 'OBRA GALERIA E5',
            'estado_obra_id' => $this->estadoEjecucion,
        ]);
        $this->obraId = (int) $this->conn->insertID();

        $this->inspectorId = $this->crearUsuario('PRUEBA_INSPECTOR_E5');

        $this->conn->table('inspecciones')->insert([
            'uuid'             => Uuid::v4(),
            'obra_id'          => $this->obraId,
            'inspector_id'     => $this->inspectorId,
            'fecha_inspeccion' => '2026-09-22',
            'hora_inspeccion'  => '10:30:00',
            'observacion'      => 'Fisuras visibles en el sector norte.',
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);
        $this->inspeccionId = (int) $this->conn->insertID();

        $this->carpeta = $this->codigoObra . '/2026-09-22/10-30-00';

        $config             = config('SigoaStorage');
        $this->raizOriginal = $config->storagePath;
        $config->storagePath = $this->tmp;
    }

    protected function tearDown(): void
    {
        config('SigoaStorage')->storagePath = $this->raizOriginal;

        /* La conexión `tests` es compartida: solo se borran las filas de esta
           prueba y en orden de dependencia (hijas antes que padres). */
        $this->conn->table('fotografias')->where('inspeccion_id', $this->inspeccionId)->delete();
        $this->conn->table('inspecciones')->where('id', $this->inspeccionId)->delete();
        $this->conn->table('inspectores_obras')->where('obra_id', $this->obraId)->delete();
        $this->conn->table('obras')->where('id', $this->obraId)->delete();
        $this->conn->table('usuarios')->whereIn('id', $this->usuariosIds)->delete();
        $this->conn->table('estados_obra')
            ->whereIn('id', [$this->estadoEjecucion, $this->estadoFinalizada])
            ->delete();

        $this->eliminarArbol($this->tmp);

        parent::tearDown();
    }

    /* ================================================================
       Esquema
       ================================================================ */

    private function tabla(string $nombre): string
    {
        return $this->conn->prefixTable($nombre);
    }

    private function crearTablas(): void
    {
        /* Mismo esquema que el resto de pruebas del grupo: la base en memoria es
           compartida y la crea el primer test que pase. */
        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('obras') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            codigo VARCHAR(20) NOT NULL,
            expediente_municipal VARCHAR(60) NULL,
            numero_licitacion VARCHAR(30) NULL,
            tipo_licitacion_id INTEGER NULL,
            nombre VARCHAR(200) NOT NULL,
            barrio_id INTEGER NULL,
            empresa_id INTEGER NULL,
            estado_obra_id INTEGER NOT NULL,
            created_at DATETIME NULL
        )');

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('usuarios') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre VARCHAR(100) NULL,
            apellido VARCHAR(100) NULL,
            usuario VARCHAR(100) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            email VARCHAR(150) NULL,
            activo TINYINT NOT NULL DEFAULT 1,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('estados_obra') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            estado VARCHAR(60) NOT NULL,
            descripcion TEXT NULL,
            activo TINYINT NOT NULL DEFAULT 1
        )');

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('inspectores_obras') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            obra_id INTEGER NOT NULL,
            usuario_id INTEGER NOT NULL,
            fecha_inicio DATE NULL,
            fecha_fin DATE NULL,
            documento_id INTEGER NULL,
            observaciones TEXT NULL,
            created_at DATETIME NULL,
            FOREIGN KEY (obra_id) REFERENCES ' . $this->tabla('obras') . ' (id),
            FOREIGN KEY (usuario_id) REFERENCES ' . $this->tabla('usuarios') . ' (id)
        )');

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('inspecciones') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            uuid VARCHAR(36) NOT NULL,
            obra_id INTEGER NOT NULL,
            inspector_id INTEGER NULL,
            fecha_inspeccion DATE NOT NULL,
            hora_inspeccion TIME NULL,
            observacion TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )');

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('fotografias') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            uuid VARCHAR(36) NOT NULL UNIQUE,
            inspeccion_id INTEGER NOT NULL,
            nombre_archivo VARCHAR(255) NULL,
            ruta_relativa VARCHAR(500) NULL,
            ruta_thumbnail VARCHAR(500) NULL,
            extension VARCHAR(10) NULL,
            mime_type VARCHAR(100) NULL,
            tamano_bytes BIGINT NULL,
            ancho INTEGER NULL,
            alto INTEGER NULL,
            fecha_hora_captura DATETIME NULL,
            fecha_hora_carga DATETIME NULL,
            latitud DECIMAL(10,7) NULL,
            longitud DECIMAL(10,7) NULL,
            dispositivo VARCHAR(255) NULL,
            anulada TINYINT NOT NULL DEFAULT 0,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            FOREIGN KEY (inspeccion_id) REFERENCES ' . $this->tabla('inspecciones') . ' (id)
        )');

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('barrios') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre VARCHAR(150) NOT NULL,
            activo TINYINT NOT NULL DEFAULT 1
        )');

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('empresas') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            razon_social VARCHAR(200) NOT NULL,
            activo TINYINT NOT NULL DEFAULT 1
        )');

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('tipos_licitacion') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tipo_licitacion VARCHAR(120) NOT NULL,
            activo TINYINT NOT NULL DEFAULT 1
        )');
    }

    /* ================================================================
       Utilidades
       ================================================================ */

    /**
     * Código de obra con la forma real `OBR-XXXXXX`, que es la que valida
     * `ObraAlmacenamiento::normalizarCodigo()`.
     */
    private function codigoValido(): string
    {
        return 'OBR-' . (string) random_int(100000, 999999);
    }

    private function crearUsuario(string $nombreUsuario): int
    {
        $this->conn->table('usuarios')->insert([
            'usuario'       => $nombreUsuario,
            'password_hash' => password_hash('Inspector123', PASSWORD_DEFAULT),
            'activo'        => 1,
        ]);

        $id = (int) $this->conn->insertID();

        $this->usuariosIds[] = $id;

        return $id;
    }

    private function insertarEstado(string $nombre): int
    {
        $this->conn->table('estados_obra')->insert([
            'estado'      => $nombre,
            'descripcion' => 'Estado de prueba E.5',
            'activo'      => 1,
        ]);

        return (int) $this->conn->insertID();
    }

    private function sesionInspector(): array
    {
        return [
            'logged_in' => true,
            'activo'    => true,
            'user_id'   => $this->inspectorId,
            'username'  => 'PRUEBA_INSPECTOR_E5',
            'user_name' => 'PRUEBA_INSPECTOR_E5',
            'roles'     => ['INSPECTOR'],
        ];
    }

    private function asignarVigente(int $obraId, int $usuarioId): void
    {
        $this->conn->table('inspectores_obras')->insert([
            'obra_id'      => $obraId,
            'usuario_id'   => $usuarioId,
            'fecha_inicio' => date('Y-m-d'),
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    private function verDetalle(): \CodeIgniter\Test\TestResponse
    {
        return $this->withSession($this->sesionInspector())
            ->get('/inspector/inspecciones/detalle/' . $this->inspeccionId);
    }

    private function cuerpoDe($respuesta): string
    {
        return html_entity_decode(
            (string) preg_replace('/\s+/', ' ', $respuesta->getBody()),
            ENT_QUOTES,
            'UTF-8'
        );
    }

    /**
     * Registra una fotografía de la inspección de prueba con sus archivos
     * reales, usando las rutas que compone `ObraAlmacenamiento` (§52.8).
     *
     * @return array{uuid: string, ruta_relativa: string, ruta_thumbnail: string}
     */
    private function registrarFotografia(
        bool $anulada = false,
        ?int $inspeccionId = null,
        ?string $codigoObra = null
    ): array {
        $inspeccionId = $inspeccionId ?? $this->inspeccionId;
        $codigoObra   = $codigoObra ?? $this->codigoObra;

        $almacenamiento = new ObraAlmacenamiento($this->tmp);
        $marca          = date('Ymd-His');
        $nombreOriginal = sprintf('INS-%05d-%s-%s.jpg', $inspeccionId, $marca, bin2hex(random_bytes(3)));
        $nombreThumb    = sprintf('THB-%05d-%s-%s.jpg', $inspeccionId, $marca, bin2hex(random_bytes(3)));

        $rutaRelativa = $almacenamiento->rutaRelativaImagen($codigoObra, '2026-09-22', '10-30-00', $nombreOriginal);
        $rutaThumb    = $almacenamiento->rutaRelativaThumbnail($codigoObra, '2026-09-22', '10-30-00', $nombreThumb);

        $this->assertNotNull($rutaRelativa, 'La ruta del original debe componerse.');
        $this->assertNotNull($rutaThumb, 'La ruta de la miniatura debe componerse.');

        $absolutaOriginal = $this->absoluta($rutaRelativa);
        $absolutaThumb    = $this->absoluta($rutaThumb);

        $this->escribirJpeg($absolutaOriginal, 800, 600, [40, 90, 160]);
        $this->escribirJpeg($absolutaThumb, 400, 300, [200, 60, 60]);

        $uuid = Uuid::v4();
        $ahora = date('Y-m-d H:i:s');

        $this->conn->table('fotografias')->insert([
            'uuid'              => $uuid,
            'inspeccion_id'     => $inspeccionId,
            'nombre_archivo'    => $nombreOriginal,
            'ruta_relativa'     => $rutaRelativa,
            'ruta_thumbnail'    => $rutaThumb,
            'extension'         => 'jpg',
            'mime_type'         => 'image/jpeg',
            'tamano_bytes'      => (int) filesize($absolutaOriginal),
            'ancho'             => 800,
            'alto'              => 600,
            'fecha_hora_captura' => '2026-09-22 10:31:00',
            'fecha_hora_carga'  => $ahora,
            'dispositivo'       => 'PRUEBA E5',
            'anulada'           => $anulada ? 1 : 0,
            'created_at'        => $ahora,
            'updated_at'        => $ahora,
        ]);

        return ['uuid' => $uuid, 'ruta_relativa' => $rutaRelativa, 'ruta_thumbnail' => $rutaThumb];
    }

    private function absoluta(string $relativa): string
    {
        return $this->tmp . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativa);
    }

    /**
     * Escribe un JPEG real, creando la estructura de carpetas que necesite.
     */
    private function escribirJpeg(string $ruta, int $ancho, int $alto, array $color): void
    {
        $directorio = dirname($ruta);

        if (! is_dir($directorio)) {
            mkdir($directorio, 0755, true);
        }

        $imagen = imagecreatetruecolor($ancho, $alto);
        imagefilledrectangle($imagen, 0, 0, $ancho, $alto, imagecolorallocate($imagen, $color[0], $color[1], $color[2]));
        imagejpeg($imagen, $ruta, 90);
        imagedestroy($imagen);
    }

    private function eliminarArbol(string $ruta): void
    {
        if ($ruta === '' || ! is_dir($ruta)) {
            return;
        }

        foreach (scandir($ruta) as $entrada) {
            if ($entrada === '.' || $entrada === '..') {
                continue;
            }

            $completa = $ruta . DIRECTORY_SEPARATOR . $entrada;

            is_dir($completa) ? $this->eliminarArbol($completa) : @unlink($completa);
        }

        @rmdir($ruta);
    }

    /* ================================================================
       La galería
     * ================================================================ */

    public function testUnaInspeccionSinFotografiasLoDice(): void
    {
        $this->asignarVigente($this->obraId, $this->inspectorId);

        $resultado = $this->verDetalle();

        $resultado->assertStatus(200);
        $resultado->assertSee('Esta inspección no tiene fotografías registradas.');
        $resultado->assertDontSee('iid-galeria');
        $resultado->assertDontSee('/inspector/fotografias/mini/');
    }

    public function testUnaInspeccionConUnaFotografiaMuestraLaMinaturaYElEnlaceAlOriginal(): void
    {
        $this->asignarVigente($this->obraId, $this->inspectorId);
        $foto = $this->registrarFotografia();

        $resultado = $this->verDetalle();

        $resultado->assertStatus(200);
        $resultado->assertSee('1 fotografía');
        $resultado->assertSee('/inspector/fotografias/mini/' . $foto['uuid']);
        $resultado->assertSee('/inspector/fotografias/ver/' . $foto['uuid']);
        $resultado->assertSee('Ver original');
        $resultado->assertSee('800 × 600 px');
        $resultado->assertSee('22/09/2026 10:31');
        $resultado->assertDontSee('Esta inspección no tiene fotografías registradas.');
    }

    public function testVariasFotografiasAparecenEnOrdenDeRegistro(): void
    {
        $this->asignarVigente($this->obraId, $this->inspectorId);

        $primera = $this->registrarFotografia();
        $segunda = $this->registrarFotografia();
        $tercera = $this->registrarFotografia();

        $cuerpo = $this->cuerpoDe($this->verDetalle());

        $this->assertStringContainsString('3 fotografías', $cuerpo);

        $posiciones = [
            'primera' => (int) strpos($cuerpo, '/inspector/fotografias/mini/' . $primera['uuid']),
            'segunda' => (int) strpos($cuerpo, '/inspector/fotografias/mini/' . $segunda['uuid']),
            'tercera' => (int) strpos($cuerpo, '/inspector/fotografias/mini/' . $tercera['uuid']),
        ];

        foreach ($posiciones as $nombre => $posicion) {
            $this->assertNotSame(0, $posicion, "La fotografía {$nombre} debe aparecer en la galería.");
        }

        $this->assertTrue(
            $posiciones['primera'] < $posiciones['segunda'] && $posiciones['segunda'] < $posiciones['tercera'],
            'La galería se ordena por id ASC: el orden en que el servidor registró cada fotografía.'
        );
    }

    public function testLasFotografiasAnuladasNoSeMuestran(): void
    {
        $this->asignarVigente($this->obraId, $this->inspectorId);

        $visible = $this->registrarFotografia();
        $anulada = $this->registrarFotografia(anulada: true);

        $resultado = $this->verDetalle();

        $resultado->assertStatus(200);
        $resultado->assertSee('/inspector/fotografias/mini/' . $visible['uuid']);
        $resultado->assertDontSee('/inspector/fotografias/mini/' . $anulada['uuid']);
        $resultado->assertSee('1 fotografía');
    }

    /* ================================================================
       Servir los archivos
     * ================================================================ */

    public function testLaMiniaturaSeSirveConElContenidoDelArchivoDelThumbnail(): void
    {
        $this->asignarVigente($this->obraId, $this->inspectorId);
        $foto = $this->registrarFotografia();

        $resultado = $this->withSession($this->sesionInspector())
            ->get('/inspector/fotografias/mini/' . $foto['uuid']);

        $resultado->assertStatus(200);
        $resultado->assertHeader('Content-Type', 'image/jpeg');

        $esperado = file_get_contents($this->absoluta($foto['ruta_thumbnail']));

        $this->assertSame($esperado, $resultado->response()->getBody(), 'Se sirve el archivo de `ruta_thumbnail`.');
        $this->assertSame((string) strlen((string) $esperado), $resultado->response()->getHeaderLine('Content-Length'));
    }

    public function testElOriginalSeSirveAlAbrirLaFotografia(): void
    {
        $this->asignarVigente($this->obraId, $this->inspectorId);
        $foto = $this->registrarFotografia();

        $resultado = $this->withSession($this->sesionInspector())
            ->get('/inspector/fotografias/ver/' . $foto['uuid']);

        $resultado->assertStatus(200);
        $resultado->assertHeader('Content-Type', 'image/jpeg');

        $original = (string) file_get_contents($this->absoluta($foto['ruta_relativa']));
        $thumb    = (string) file_get_contents($this->absoluta($foto['ruta_thumbnail']));

        $this->assertSame($original, $resultado->response()->getBody(), 'Se sirve el archivo de `ruta_relativa`.');
        $this->assertNotSame($thumb, $original, 'El original y la miniatura son archivos distintos.');
    }

    public function testLaRespuestaNoInvitaAlNavegadorAGuardarLaFotografia(): void
    {
        $this->asignarVigente($this->obraId, $this->inspectorId);
        $foto = $this->registrarFotografia();

        $resultado = $this->withSession($this->sesionInspector())
            ->get('/inspector/fotografias/mini/' . $foto['uuid']);

        $resultado->assertStatus(200);
        $resultado->assertHeader('Cache-Control', 'private, no-store, max-age=0, must-revalidate');
        $resultado->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    /* ================================================================
       Rechazos
     * ================================================================ */

    public function testUnaFotografiaNoRegistradaNoSeSirve(): void
    {
        $this->asignarVigente($this->obraId, $this->inspectorId);

        foreach (['/inspector/fotografias/mini/', '/inspector/fotografias/ver/'] as $ruta) {
            $resultado = $this->withSession($this->sesionInspector())->get($ruta . Uuid::v4());

            $resultado->assertStatus(404);
            $this->assertSame('', $resultado->response()->getBody());
        }
    }

    public function testUnIdentificadorQueNoEsUuidNoSeSirve(): void
    {
        $this->asignarVigente($this->obraId, $this->inspectorId);

        $resultado = $this->withSession($this->sesionInspector())
            ->get('/inspector/fotografias/mini/' . Uuid::v4() . 'extra');

        $resultado->assertStatus(404);
    }

    public function testUnaFotografiaAnuladaNoSeSirveAunQueElArchivoExista(): void
    {
        $this->asignarVigente($this->obraId, $this->inspectorId);
        $foto = $this->registrarFotografia(anulada: true);

        $this->assertFileExists($this->absoluta($foto['ruta_relativa']), 'La anulación es lógica: el archivo sigue en disco.');

        foreach (['/inspector/fotografias/mini/', '/inspector/fotografias/ver/'] as $ruta) {
            $resultado = $this->withSession($this->sesionInspector())->get($ruta . $foto['uuid']);

            $resultado->assertStatus(404);
        }
    }

    public function testLaFotografiaDeUnaInspeccionAjenaNoSeSirve(): void
    {
        $otro = $this->crearUsuario('PRUEBA_INSPECTOR_E5_AJENO');

        $codigoAjeno = $this->codigoValido();

        $this->conn->table('obras')->insert([
            'codigo'         => $codigoAjeno,
            'nombre'         => 'OBRA AJENA E5',
            'estado_obra_id' => $this->estadoEjecucion,
        ]);
        $obraAjenaId = (int) $this->conn->insertID();

        $this->asignarVigente($obraAjenaId, $otro);

        $this->conn->table('inspecciones')->insert([
            'uuid'             => Uuid::v4(),
            'obra_id'          => $obraAjenaId,
            'inspector_id'     => $otro,
            'fecha_inspeccion' => '2026-09-22',
            'hora_inspeccion'  => '10:30:00',
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);
        $inspeccionAjenaId = (int) $this->conn->insertID();

        $foto = $this->registrarFotografia(inspeccionId: $inspeccionAjenaId, codigoObra: $codigoAjeno);

        try {
            foreach (['/inspector/fotografias/mini/', '/inspector/fotografias/ver/'] as $ruta) {
                $resultado = $this->withSession($this->sesionInspector())->get($ruta . $foto['uuid']);

                $resultado->assertStatus(404, 'Una fotografía de otra obra no se sirve ni se revela.');
            }

            $this->withSession($this->sesionInspector())
                ->get('/inspector/inspecciones/detalle/' . $inspeccionAjenaId)
                ->assertRedirectTo('/inspector/dashboard');
        } finally {
            $this->conn->table('fotografias')->where('inspeccion_id', $inspeccionAjenaId)->delete();
            $this->conn->table('inspecciones')->where('id', $inspeccionAjenaId)->delete();
            $this->conn->table('inspectores_obras')->where('obra_id', $obraAjenaId)->delete();
            $this->conn->table('obras')->where('id', $obraAjenaId)->delete();
        }
    }

    public function testSinSesionNoSeSirveNingunaFotografia(): void
    {
        $this->asignarVigente($this->obraId, $this->inspectorId);
        $foto = $this->registrarFotografia();

        $resultado = $this->get('/inspector/fotografias/mini/' . $foto['uuid']);

        $resultado->assertRedirectTo('/login');
    }

    public function testLaGaleriaDeUnaObraFinalizadaSigueConsultable(): void
    {
        $this->asignarVigente($this->obraId, $this->inspectorId);
        $this->conn->table('obras')->where('id', $this->obraId)->update(['estado_obra_id' => $this->estadoFinalizada]);
        $foto = $this->registrarFotografia();

        $resultado = $this->verDetalle();

        /* El estado de la obra no se muestra en el detalle: lo que
           demuestra que la galería sigue consultable es que la página se
           sirve y la imagen real se entrega. */
        $resultado->assertStatus(200);
        $resultado->assertDontSee('FINALIZADA');
        $resultado->assertSee('Fotografías');
        $resultado->assertSee('/inspector/fotografias/mini/' . $foto['uuid']);

        $this->withSession($this->sesionInspector())
            ->get('/inspector/fotografias/mini/' . $foto['uuid'])
            ->assertStatus(200);
    }

    /* ================================================================
       Protección contra path traversal
     * ================================================================ */

    public function testUnaRutaRelativaQueSaleDeLaRaizNoSeSirve(): void
    {
        $this->asignarVigente($this->obraId, $this->inspectorId);

        /* Un archivo real FUERA de la raíz de almacenamiento: si el servicio
           confiara en la ruta de la base, lo entregaría. */
        $fuera = $this->tmp . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR
            . 'sigoa_e5_secreto_' . bin2hex(random_bytes(3)) . '.txt';

        file_put_contents($fuera, 'CONTENIDO FUERA DE LA RAIZ');

        $foto = $this->registrarFotografia();

        $this->conn->table('fotografias')->where('uuid', $foto['uuid'])->update([
            'ruta_thumbnail' => '../' . basename($fuera),
        ]);

        try {
            $resultado = $this->withSession($this->sesionInspector())
                ->get('/inspector/fotografias/mini/' . $foto['uuid']);

            $resultado->assertStatus(404);
            $this->assertStringNotContainsString('CONTENIDO FUERA DE LA RAIZ', $resultado->response()->getBody());
        } finally {
            @unlink($fuera);
        }
    }

    public function testUnaRutaRelativaAbsolutaNoSeSirve(): void
    {
        $this->asignarVigente($this->obraId, $this->inspectorId);

        $foto = $this->registrarFotografia();

        $this->conn->table('fotografias')->where('uuid', $foto['uuid'])->update([
            'ruta_relativa' => $this->absoluta($foto['ruta_relativa']),
        ]);

        $this->withSession($this->sesionInspector())
            ->get('/inspector/fotografias/ver/' . $foto['uuid'])
            ->assertStatus(404);
    }

    public function testUnArchivoQueNoEsImagenNoSeSirve(): void
    {
        $this->asignarVigente($this->obraId, $this->inspectorId);

        $foto = $this->registrarFotografia();

        $rutaTexto = $this->carpeta . '/IMAGENES/NO-ES-UNA-IMAGEN.jpg';
        $this->escribirTexto($this->absoluta($rutaTexto), 'no soy una imagen');

        $this->conn->table('fotografias')->where('uuid', $foto['uuid'])->update([
            'ruta_relativa' => $rutaTexto,
        ]);

        $resultado = $this->withSession($this->sesionInspector())
            ->get('/inspector/fotografias/ver/' . $foto['uuid']);

        $resultado->assertStatus(404);
        $this->assertStringNotContainsString('no soy una imagen', $resultado->response()->getBody());
    }

    public function testUnaFotoRegistradaSinArchivoEnDiscoNoSeSirve(): void
    {
        $this->asignarVigente($this->obraId, $this->inspectorId);
        $foto = $this->registrarFotografia();

        unlink($this->absoluta($foto['ruta_thumbnail']));

        $this->withSession($this->sesionInspector())
            ->get('/inspector/fotografias/mini/' . $foto['uuid'])
            ->assertStatus(404);
    }

    private function escribirTexto(string $ruta, string $contenido): void
    {
        $directorio = dirname($ruta);

        if (! is_dir($directorio)) {
            mkdir($directorio, 0755, true);
        }

        file_put_contents($ruta, $contenido);
    }

    /* ================================================================
       Solo lectura
     * ================================================================ */

    public function testConsultarLaGaleriaNoModificaLosDatos(): void
    {
        $this->asignarVigente($this->obraId, $this->inspectorId);
        $foto = $this->registrarFotografia();
        $this->registrarFotografia(anulada: true);

        $antesFotos = $this->conn->table('fotografias')->orderBy('id', 'ASC')->get()->getResultArray();
        $antesObra  = $this->conn->table('inspecciones')->where('id', $this->inspeccionId)->get()->getRowArray();
        $tamanio    = filesize($this->absoluta($foto['ruta_relativa']));

        $this->verDetalle();
        $this->withSession($this->sesionInspector())->get('/inspector/fotografias/mini/' . $foto['uuid']);
        $this->withSession($this->sesionInspector())->get('/inspector/fotografias/ver/' . $foto['uuid']);

        $despuesFotos = $this->conn->table('fotografias')->orderBy('id', 'ASC')->get()->getResultArray();

        $this->assertSame($antesFotos, $despuesFotos, 'Ver la galería no modifica las fotografías.');
        $this->assertSame(
            $antesObra,
            $this->conn->table('inspecciones')->where('id', $this->inspeccionId)->get()->getRowArray(),
            'Ver la galería no modifica la inspección.'
        );
        $this->assertSame(
            $tamanio,
            filesize($this->absoluta($foto['ruta_relativa'])),
            'Servir una fotografía no toca el archivo.'
        );
    }
}
