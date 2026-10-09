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
     * Declaraciones CSS de un selector, en todas sus variantes.
     *
     * Un mismo selector puede aparecer en la regla base y dentro de un
     * `@media`: se recogen todas para que la afirmación valgue para el
     * tratamiento completo del elemento y no para una de sus apariciones.
     */
    private function reglasCss(string $css, string $selector): string
    {
        $patron = '/' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/';

        $this->assertGreaterThan(
            0,
            preg_match_all($patron, $css, $coincidencias),
            "No se encontró la regla «{$selector}»."
        );

        return implode("\n", $coincidencias[1]);
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
        /* Desde E.6 el histórico y el detalle son una implementación
           compartida (`App\Controllers\Inspecciones`), no dos por rol. */
        $contenido = $this->leerApp('Controllers/Inspecciones.php');

        $this->assertMatchesRegularExpression('/function\s+detalle\s*\(\s*int\s+\$inspeccionId/', $contenido);
        $this->assertStringContainsString("view('inspector/inspeccion_detalle'", $contenido);
        $this->assertStringContainsString('listarPorObraAgrupado', $contenido, 'El histórico se resuelve en el modelo.');
    }

    public function testElDetalleAutorizaSobreLaObraDeLaInspeccion(): void
    {
        $metodo = $this->metodo(
            $this->leerApp('Controllers/Inspecciones.php'),
            'public function detalle('
        );

        $this->assertStringContainsString(
            '$inspeccion->obra_id',
            $metodo,
            'La autorización debe resolverse sobre la obra de la inspección, no sobre un id recibido.'
        );
        $this->assertStringContainsString(
            'puedeConsultarObra',
            $metodo,
            'La consulta se autoriza en el servicio de acceso, sin exigir asignación vigente.'
        );
        $this->assertMatchesRegularExpression(
            '/puedeConsultarObra\(\$obraId, \$usuarioId, \$roles\)/',
            $metodo,
            'La autorización se verifica contra la obra de la inspección y el usuario de la sesión.'
        );
        $this->assertStringNotContainsString(
            'permiteInspeccionar',
            $metodo,
            'Consultar el detalle no depende del estado de la obra: una obra finalizada también se consulta.'
        );
    }

    public function testElHistoricoYElDetalleNoEscribenDatos(): void
    {
        $contenido = $this->leerApp('Controllers/Inspecciones.php');

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
        /* Desde E.6 el enlace se compone con el prefijo que declara el punto de
           entrada; por defecto es el del inspector. */
        $this->assertStringContainsString("\$base ?? '/inspector/inspecciones'", $vista, 'Cada inspección enlaza a su detalle.');
        $this->assertStringContainsString("\$base . '/detalle/'", $vista);
        $this->assertStringContainsString('PlazoObra::formatearFecha', $vista, 'Las fechas se muestran en el formato institucional.');
        $this->assertStringContainsString('No existen inspecciones aún', $texto, 'El estado vacío de E.3 se conserva.');
    }

    public function testElDetalleMuestraLosDatosRegistradosDeLaInspeccion(): void
    {
        $vista = $this->leerApp('Views/inspector/inspeccion_detalle.php');
        $texto = $this->textoPlano($vista);

        /* Los datos viven solo en el encabezado: la fecha es el
           título y hora, inspector y observación quedan como datos de apoyo. */
        $this->assertStringContainsString('<h1 class="iid-titulo"><?= esc($fechaTexto) ?></h1>', $vista);
        $this->assertStringContainsString('Hora:', $texto);
        $this->assertStringContainsString('Inspector:', $texto);
        $this->assertStringContainsString('Observación:', $texto);
        $this->assertStringNotContainsString('Inspección del', $vista, 'La etiqueta del título era redundante.');
        $this->assertStringContainsString("\$base . '/ver/'", $vista, 'Se vuelve al histórico de la obra.');
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

    /**
     * El encabezado ya no repite lo que el bloque de datos mostraba, ni el
     * estado de la obra, que es un dato de la obra y no de la inspección.
     */
    public function testElDetalleNoRepiteLosDatosNiMuestraElEstadoDeLaObra(): void
    {
        $vista = $this->leerApp('Views/inspector/inspeccion_detalle.php');

        $this->assertStringNotContainsString(
            'iid-datos',
            $vista,
            'El bloque que repetía fecha, hora, inspector y observación se eliminó.'
        );

        $this->assertStringNotContainsString(
            'estado-badge',
            $vista,
            'El detalle no muestra el estado de la obra.'
        );

        $this->assertStringNotContainsString(
            'estado_nombre',
            $vista,
            'La vista ya no necesita el nombre del estado de la obra.'
        );

        /* Y el estilo de la página no conserva reglas huérfanas de ese bloque. */
        $css = $this->leerPublic('assets/css/pages/inspector-inspeccion-detalle.css');

        foreach (['.iid-datos', '.iid-grid', '.iid-dato ', '.iid-hora'] as $regla) {
            $this->assertStringNotContainsString(
                $regla,
                $css,
                "El estilo de la página no conserva la regla eliminada «{$regla}»."
            );
        }
    }

    /**
     * La galería se conserva tal cual: sigue siendo la única sección que
     * resuelve fotografías y la que el script de página vigila sin conexión.
     */
    public function testLaGaleriaDelDetalleSeConserva(): void
    {
        $vista = $this->leerApp('Views/inspector/inspeccion_detalle.php');

        $this->assertStringContainsString('iid-fotografias', $vista);
        $this->assertStringContainsString('iidGaleria', $vista, 'El contenedor que vigila el script se conserva.');
        $this->assertStringContainsString('iidGaleriaSinConexion', $vista);
        $this->assertStringContainsString("\$baseFotos . '/mini/'", $vista, 'Las miniaturas se siguen pidiendo por uuid.');
        $this->assertStringContainsString("\$baseFotos . '/ver/'", $vista);
        $this->assertStringContainsString(
            'iid-seccion-titulo">Fotografías</h2>',
            $vista,
            'La galería conserva su encabezado de sección.'
        );
    }

    /**
     * La hora de cada tarjeta se presenta como texto a la izquierda y no como
     * distintivo de color: la tarjeta se lee como un registro.
     */
    public function testLaHoraDeLaTarjetaNoEsUnDistintivo(): void
    {
        $vista = $this->leerApp('Views/inspector/inspecciones.php');
        $css   = $this->leerPublic('assets/css/pages/inspector-inspecciones.css');

        $this->assertStringContainsString('iis-item-hora', $vista);
        $this->assertStringContainsString('iis-item-hora-sin', $vista, '"Sin hora" conserva su propio tratamiento.');

        /* En el CSS la hora solo declara ancho, tipografía y color de texto:
           ni fondo propio ni padding de distintivo. */
        $regla = $this->reglasCss($css, '.iis-item-hora');

        foreach (['background', 'padding', 'border-radius'] as $propiedad) {
            $this->assertStringNotContainsString(
                $propiedad,
                $regla,
                "La hora no se presenta como distintivo: «{$propiedad}» sobra."
            );
        }

        $this->assertStringContainsString('flex: 0 0 auto', $regla, 'La hora es una columna propia a la izquierda.');

        $this->assertStringContainsString(
            'align-items: flex-end',
            $this->reglasCss($css, '.iis-item-cuerpo'),
            'El cuerpo de la tarjeta se alinea a la derecha.'
        );

        /* "Sin hora" con el mismo tratamiento sobrio: tono secundario y
           cursiva, como ya se usaba en el detalle. */
        $this->assertStringContainsString(
            'font-style: italic',
            $this->reglasCss($css, '.iis-item-hora-sin')
        );
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
        $contenido = $this->leerApp('Controllers/Inspecciones.php');
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
            '2026-10-08-120000_AddPlazoInicialConfirmadoToObras.php',
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
