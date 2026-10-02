<?php

use App\Libraries\HoraInspeccion;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Navegación histórica de inspecciones (Fase E.4): Obra → Inspecciones →
 * Fecha → Inspección.
 *
 * Comprueba tres cosas que en esta fase son requisitos, no preferencias:
 *
 * 1. el orden histórico se resuelve con **datos de la base**, nunca con el
 *    nombre físico de la carpeta `HH-mm-ss[-N]` (§52.8);
 * 2. `00:00:00` es una hora válida y `NULL` es "sin hora": son casos distintos
 *    y confundirlos daría un histórico incorrecto;
 * 3. la galería de fotografías **no** se implementa: sin miniaturas, sin
 *    descarga, sin caché y sin IndexedDB histórico (eso es E.5).
 *
 * El punto 3 quedó superado por E.5: la galería en línea sí existe, en
 * `Inspector\Fotografias` y en `inspector/inspeccion_detalle.php`. Aquí se
 * mantienen las fronteras que E.5 no cruza —sin descarga, sin caché, sin
 * guardado en el dispositivo— y la consulta de fotografías se verifica en
 * `InspectorInspeccionesGaleriaTest`.
 *
 * @internal
 */
final class InspectorInspeccionesHistoricoTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private function leerApp(string $relativa): string
    {
        $ruta = APPPATH . ltrim($relativa, '/\\');

        $contenido = file_get_contents($ruta);

        $this->assertNotFalse($contenido, "No se pudo leer: {$relativa}");

        return $contenido ?: '';
    }

    private function leerPublic(string $relativa): string
    {
        $ruta = rtrim((string) FCPATH, '/\\') . DIRECTORY_SEPARATOR . ltrim($relativa, '/\\');

        $contenido = file_get_contents($ruta);

        $this->assertNotFalse($contenido, "No se pudo leer el archivo público: {$relativa}");

        return $contenido ?: '';
    }

    private function textoPlano(string $contenido): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $contenido));
    }

    /**
     * Código de una vista sin los comentarios HTML.
     *
     * Las vistas documentan en comentarios qué **no** se implementa, y esa
     * documentación no es implementación: comprobarla como si lo fuera daría un
     * falso positivo en cada fase.
     */
    private function sinComentarios(string $contenido): string
    {
        return (string) preg_replace('/<!--.*?-->/s', '', $contenido);
    }

    /**
     * Cuerpo de un método PHP, desde su firma hasta la llave que lo cierra.
     */
    private function metodo(string $contenido, string $firma): string
    {
        $desde = strpos($contenido, $firma);

        $this->assertIsInt($desde, "No se encontró el método «{$firma}»");

        $nivel  = 0;
        $inicio = strpos($contenido, '{', (int) $desde);

        $this->assertIsInt($inicio);

        for ($i = (int) $inicio; $i < strlen($contenido); $i++) {
            if ($contenido[$i] === '{') {
                $nivel++;
            }

            if ($contenido[$i] === '}') {
                $nivel--;

                if ($nivel === 0) {
                    return substr($contenido, (int) $desde, $i - (int) $desde + 1);
                }
            }
        }

        $this->fail("El método «{$firma}» no cierra sus llaves.");

        return '';
    }

    /* ----------------------------------------------------------------
     * Rutas y controlador
     * ---------------------------------------------------------------- */

    public function testLasRutasDelHistoricoYDelDetalleEstanRegistradas(): void
    {
        $rutas = $this->leerApp('Config/Routes.php');

        $this->assertStringContainsString('inspecciones/ver/(:num)', $rutas);
        $this->assertStringContainsString('Inspector\Inspecciones::ver/$1', $rutas);
        $this->assertStringContainsString('inspecciones/detalle/(:num)', $rutas);
        $this->assertStringContainsString('Inspector\Inspecciones::detalle/$1', $rutas);
    }

    public function testElDetalleSeExponeComoMetodoSeparado(): void
    {
        $contenido = $this->leerApp('Controllers/Inspector/Inspecciones.php');

        $this->assertMatchesRegularExpression('/function\s+detalle\s*\(\s*int\s+\$inspeccionId/', $contenido);
        $this->assertStringContainsString("view('inspector/inspeccion_detalle'", $contenido);
        $this->assertStringContainsString('listarPorObraAgrupado', $contenido, 'El histórico se resuelve en el modelo.');
    }

    public function testElDetalleAutorizaSobreLaObraDeLaInspeccion(): void
    {
        $metodo = $this->metodo(
            $this->leerApp('Controllers/Inspector/Inspecciones.php'),
            'public function detalle('
        );

        $this->assertStringContainsString(
            '$inspeccion->obra_id',
            $metodo,
            'La autorización debe resolverse sobre la obra de la inspección, no sobre un id recibido.'
        );
        $this->assertStringContainsString('esVigente', $metodo, 'Se mantiene la asignación vigente de E.3.');
        $this->assertStringNotContainsString(
            'permiteInspeccionar',
            $metodo,
            'Consultar el detalle no depende del estado de la obra: una obra finalizada también se consulta.'
        );
        $this->assertMatchesRegularExpression(
            '/esVigente\([^)]*\$usuarioId/',
            $metodo,
            'La asignación vigente se verifica contra el usuario de la sesión.'
        );
    }

    public function testElHistoricoYElDetalleNoEscribenDatos(): void
    {
        $contenido = $this->leerApp('Controllers/Inspector/Inspecciones.php');

        foreach (['ver(', 'detalle('] as $firma) {
            $metodo = $this->metodo($contenido, 'public function ' . $firma);

            foreach (['->insert(', '->update(', '->delete(', '->replace(', 'asegurarEstructura'] as $escritura) {
                $this->assertStringNotContainsString(
                    $escritura,
                    $metodo,
                    "El método {$firma} es de solo lectura y no escribe datos ni prepara almacenamiento."
                );
            }
        }
    }

    /* ----------------------------------------------------------------
     * Orden histórico
     * ---------------------------------------------------------------- */

    public function testElOrdenHistoricoLoResuelveLaBaseDeDatos(): void
    {
        $modelo = $this->leerApp('Models/InspeccionModel.php');
        $metodo = $this->metodo($modelo, 'public function listarPorObraAgrupado(');

        $this->assertStringContainsString(
            "orderBy('fecha_inspeccion', 'DESC')",
            $metodo,
            'Las fechas se muestran de más reciente a más antigua.'
        );
        $this->assertStringContainsString(
            "orderBy('hora_inspeccion IS NULL', 'ASC', false)",
            $metodo,
            'Dentro del día, las inspecciones con hora van antes que las que no la tienen.'
        );
        $this->assertStringContainsString(
            "orderBy('hora_inspeccion', 'DESC')",
            $metodo,
            'Dentro del día, de la hora más reciente a la más antigua.'
        );
        $this->assertStringContainsString(
            "orderBy('id', 'DESC')",
            $metodo,
            'El desempate debe ser determinista.'
        );
    }

    public function testElHistoricoNoSeConstruyeConElNombreDeLaCarpetaFisica(): void
    {
        $modelo = $this->leerApp('Models/InspeccionModel.php');
        $metodo = $this->metodo($modelo, 'public function listarPorObraAgrupado(');

        foreach (['nombreCarpetaInspeccion', 'sufijoCarpeta', 'ruta_relativa', 'ruta_thumbnail', 'nombreCarpeta'] as $prohibido) {
            $this->assertStringNotContainsString(
                $prohibido,
                $metodo,
                "El orden histórico no puede derivarse de la carpeta física ({$prohibido})."
            );
        }
    }

    public function testElDetalleBuscaLaInspeccionPorIdentidadTecnica(): void
    {
        $modelo = $this->leerApp('Models/InspeccionModel.php');
        $metodo = $this->metodo($modelo, 'public function findDetalle(int $id)');

        $this->assertStringContainsString("where('inspecciones.id', \$id)", $metodo);
        $this->assertStringContainsString('uuid', $modelo, 'El uuid sigue siendo la identidad técnica de la inspección.');
    }

    /* ----------------------------------------------------------------
     * Hora: 00:00:00 es válida, NULL es "sin hora"
     * ---------------------------------------------------------------- */

    public function testLaMedianocheEsUnaHoraValida(): void
    {
        $this->assertSame('00:00', HoraInspeccion::texto('00:00:00'));
        $this->assertFalse(HoraInspeccion::esSinHora('00:00:00'));
    }

    public function testUnaHoraNulaSeInformaComoSinHora(): void
    {
        $this->assertSame('Sin hora', HoraInspeccion::texto(null));
        $this->assertTrue(HoraInspeccion::esSinHora(null));
    }

    public function testLaHoraSeMuestraEnFormatoCorto(): void
    {
        $this->assertSame('09:30', HoraInspeccion::texto('09:30:00'));
        $this->assertSame('09:30', HoraInspeccion::texto('09:30'));
        $this->assertSame('23:59', HoraInspeccion::texto('23:59:59.123'));
    }

    public function testUnaHoraIlegibleNoSeMuestraComoDato(): void
    {
        foreach (['24:00:00', 'no-es-una-hora', '9:30', ''] as $ilegible) {
            $this->assertTrue(
                HoraInspeccion::esSinHora($ilegible),
                "«{$ilegible}» no es una hora informable y no debe mostrarse como dato."
            );
            $this->assertSame('Sin hora', HoraInspeccion::texto($ilegible));
        }
    }

    public function testLasVistasDelHistoricoUsanLaReglaCentralizadaDeLaHora(): void
    {
        foreach (['Views/inspector/inspecciones.php', 'Views/inspector/inspeccion_detalle.php'] as $vista) {
            $contenido = $this->leerApp($vista);

            $this->assertStringContainsString('HoraInspeccion::texto(', $contenido, "{$vista} debe usar la regla centralizada.");
            $this->assertStringNotContainsString(
                'substr(',
                $contenido,
                "{$vista} no debe recortar la hora por su cuenta: duplicaría la regla."
            );
        }
    }

    /* ----------------------------------------------------------------
     * Vistas
     * ---------------------------------------------------------------- */

    public function testElHistoricoAgrupaPorFechaYEnlazaAlDetalle(): void
    {
        $vista = $this->leerApp('Views/inspector/inspecciones.php');
        $texto = $this->textoPlano($vista);

        $this->assertStringContainsString('foreach ($grupos as $grupo)', $vista, 'La vista recorre los grupos que entrega el modelo.');
        $this->assertStringContainsString('iis-grupo-fecha', $vista);
        $this->assertStringContainsString('inspector/inspecciones/detalle/', $vista, 'Cada inspección enlaza a su detalle.');
        $this->assertStringContainsString('PlazoObra::formatearFecha', $vista, 'Las fechas se muestran en el formato institucional.');
        $this->assertStringContainsString('No existen inspecciones aún', $texto, 'El estado vacío de E.3 se conserva.');
    }

    public function testElDetalleMuestraLosDatosRegistradosDeLaInspeccion(): void
    {
        $vista = $this->leerApp('Views/inspector/inspeccion_detalle.php');
        $texto = $this->textoPlano($vista);

        $this->assertStringContainsString('Fecha', $texto);
        $this->assertStringContainsString('Hora', $texto);
        $this->assertStringContainsString('Observaciones', $texto);
        $this->assertStringContainsString('inspector/inspecciones/ver/', $vista, 'Se vuelve al histórico de la obra.');
        $this->assertStringContainsString('Sin observaciones', $texto, 'Una inspección sin observaciones lo dice, no lo omite.');
        $this->assertStringNotContainsString(
            'usarApp',
            $vista,
            'No se agrega un framework JS a la vista.'
        );

        /* Desde E.5 la vista carga un único script de página, y solo para el
           estado sin conexión de la galería; el resto del detalle es HTML. */
        $this->assertSame(
            1,
            substr_count($vista, '<script'),
            'El detalle carga un solo script: el estado sin conexión de la galería.'
        );
        $this->assertStringContainsString('assets/js/pages/inspeccion-detalle.js', $vista);
    }

    /* ----------------------------------------------------------------
     * E.5: fronteras de la galería en línea
     * ---------------------------------------------------------------- */

    /**
     * El listado de inspecciones sigue sin resolver fotografías: la galería es
     * del detalle, no del histórico por fecha.
     */
    public function testElListadoNoResuelveFotografias(): void
    {
        $contenido = $this->sinComentarios($this->leerApp('Views/inspector/inspecciones.php'));

        foreach ([
            '<img',
            'fotografias/mini',
            'fotografias/ver',
            'ruta_thumbnail',
            'ruta_relativa',
        ] as $prohibido) {
            $this->assertStringNotContainsStringIgnoringCase(
                $prohibido,
                $contenido,
                'El listado por fecha no muestra fotografías: eso es del detalle.'
            );
        }
    }

    /**
     * La galería no expone rutas del almacenamiento, no ofrece descarga y no
     * guarda nada en el dispositivo.
     */
    public function testLaGaleriaNoExponeRutasNiGuardaLasFotosEnElDispositivo(): void
    {
        $contenido = $this->sinComentarios($this->leerApp('Views/inspector/inspeccion_detalle.php'));

        foreach ([
            'ruta_relativa',
            'ruta_thumbnail',
            'miniatura',
            'thumbnail',
            'download',
            'descargar',
            'indexeddb',
            'SIGOA.indexeddb',
            'crearObjectURL',
            'localStorage',
            'sessionStorage',
            'caches',
        ] as $prohibido) {
            $this->assertStringNotContainsStringIgnoringCase(
                $prohibido,
                $contenido,
                'La galería pide imágenes por uuid y no administra archivos ni caché en el dispositivo.'
            );
        }
    }

    /**
     * El detalle pide las fotografías al modelo y se las pasa a la vista; los
     * archivos los sirve otro controlador.
     */
    public function testElDetalleEntregaLasFotografiasYNoLosArchivos(): void
    {
        $contenido = $this->leerApp('Controllers/Inspector/Inspecciones.php');
        $metodo    = $this->metodo($contenido, 'public function detalle(');

        $this->assertStringContainsString(
            'listarPorInspeccion',
            $metodo,
            'El detalle consulta las fotografías no anuladas de la inspección.'
        );
        $this->assertStringContainsString("'fotografias'", $metodo, 'La vista las recibe para mostrarlas.');

        foreach (['file_get_contents', 'ruta_relativa', 'ruta_thumbnail', 'finfo'] as $prohibido) {
            $this->assertStringNotContainsString(
                $prohibido,
                $metodo,
                'El detalle no lee archivos: solo entrega los datos que la galería necesita.'
            );
        }
    }

    public function testNoSeAgregoNingunaMigracion(): void
    {
        $migraciones = glob(APPPATH . 'Database/Migrations/*.php') ?: [];

        $this->assertNotEmpty($migraciones, 'Deben existir migraciones del proyecto.');

        $nombres = array_map(static fn (string $ruta): string => basename($ruta), $migraciones);
        sort($nombres);

        $ultima = end($nombres);

        $this->assertSame(
            '2026-09-23-122000_DropUniqueObraFechaInspeccion.php',
            $ultima,
            'E.4 no introduce cambios de esquema: la última migración del proyecto no debe cambiar.'
        );
    }

    public function testElEstiloDelDetalleEstaEnElPrecache(): void
    {
        $sw = $this->leerPublic('sw.js');

        $this->assertStringContainsString(
            "'/assets/css/pages/inspector-inspeccion-detalle.css'",
            $sw,
            'Sin estar en el app shell la página perdería sus estilos sin conexión.'
        );
        $this->assertMatchesRegularExpression(
            "/CACHE_VERSION = 'sigoa-shell-v\d+'/",
            $sw,
            'El app shell debe versionarse para invalidar la caché anterior.'
        );
    }

    /* ----------------------------------------------------------------
     * Autorización de las rutas
     * ---------------------------------------------------------------- */

    public function testElDetalleSinSesionRedirigeAlLogin(): void
    {
        $resultado = $this->get('/inspector/inspecciones/detalle/1');

        $resultado->assertRedirectTo('/login');
    }

    public function testElDetalleSinRolDeInspectorRedirigeAlDashboard(): void
    {
        $resultado = $this->withSession([
            'logged_in' => true,
            'activo'    => true,
            'roles'     => ['CONSULTA'],
        ])->get('/inspector/inspecciones/detalle/1');

        $resultado->assertRedirectTo('/dashboard');
    }
}
