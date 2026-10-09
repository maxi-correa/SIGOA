<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Acceso a las inspecciones por rol (Fase E.6).
 *
 * E.3–E.5 dieron cuerpo a la pantalla histórica dentro del área del inspector.
 * E.6 la convierte en la navegación **compartida** por los cuatro roles, sin
 * duplicar implementación, y le añade el listado de obras por el que se entra.
 *
 * Esta prueba fija las fronteras estructurales que sostienen ese diseño:
 *
 * * existe una sola implementación (`App\Controllers\Inspecciones`);
 * * el grupo compartido expone consulta y galería, **nunca** alta ni
 *   sincronización, que son exclusivas del inspector;
 * * el flujo previo del inspector conserva sus URLs;
 * * el listado de obras vive en el grupo de cada rol, no en el compartido;
 * * la autorización distingue inspeccionar de consultar en un único servicio;
 * * el listado es de solo lectura y no ofrece el alta de inspecciones.
 *
 * @internal
 */
final class InspeccionesAccesoPorRolTest extends CIUnitTestCase
{
    private function leerApp(string $relativa): string
    {
        $ruta      = APPPATH . ltrim($relativa, '/\\');
        $contenido = file_get_contents($ruta);

        $this->assertNotFalse($contenido, "No se pudo leer: {$relativa}");

        return $contenido ?: '';
    }

    private function leerPublic(string $relativa): string
    {
        $ruta      = rtrim((string) FCPATH, '/\\') . DIRECTORY_SEPARATOR . ltrim($relativa, '/\\');
        $contenido = file_get_contents($ruta);

        $this->assertNotFalse($contenido, "No se pudo leer el archivo público: {$relativa}");

        return $contenido ?: '';
    }

    /**
     * Bloque delimitado por el inicio del grupo de rutas y el cierre de la
     * llamada a `group()`, para poder afirmar sobre ese grupo y no sobre todo
     * el archivo de rutas.
     */
    private function grupoDeRutas(string $marca): string
    {
        $rutas = $this->leerApp('Config/Routes.php');

        $desde = strpos($rutas, $marca);

        $this->assertIsInt($desde, "No se encontró el grupo «{$marca}»");

        $hasta = strpos($rutas, '});', (int) $desde);

        $this->assertIsInt($hasta, "El grupo «{$marca}» no cierra");

        return substr($rutas, (int) $desde, (int) $hasta - (int) $desde);
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
     * Una sola implementación
     * ---------------------------------------------------------------- */

    public function testExisteUnSoloControladorDeConsultaHistorica(): void
    {
        $this->assertFileExists(APPPATH . 'Controllers/Inspecciones.php');

        $compartido = $this->leerApp('Controllers/Inspecciones.php');

        foreach (['index(', 'ver(', 'detalle('] as $metodo) {
            $this->assertStringContainsString('public function ' . $metodo, $compartido);
        }

        /* Ninguna otra clase vuelve a implementar la navegación histórica. */
        $inspector = $this->leerApp('Controllers/Inspector/Inspecciones.php');

        $this->assertStringNotContainsString('public function index(', $inspector);
        $this->assertStringNotContainsString('public function ver(', $inspector);
        $this->assertStringNotContainsString('public function detalle(', $inspector);
    }

    public function testElControladorCompartidoNoDejaAltaNiSincronizacion(): void
    {
        $compartido = $this->leerApp('Controllers/Inspecciones.php');

        foreach (['function nueva(', 'Sincronizar::', 'InspeccionCarpeta', 'ObraAlmacenamiento'] as $prohibido) {
            $this->assertStringNotContainsString(
                $prohibido,
                $compartido,
                'La consulta compartida es de solo lectura: el alta y la sincronización no se duplican aquí.'
            );
        }
    }

    /* ----------------------------------------------------------------
     * Rutas
     * ---------------------------------------------------------------- */

    public function testElGrupoCompartidoExponeConsultaYGaleriaParaLosCuatroRoles(): void
    {
        $grupo = $this->grupoDeRutas("\$routes->group('inspecciones'");

        $this->assertStringContainsString("'role:SUPERADMINISTRADOR,ADMINISTRADOR,CONSULTA,INSPECTOR'", $grupo);
        $this->assertStringContainsString("'ver/(:num)', 'Inspecciones::ver/\$1'", $grupo);
        $this->assertStringContainsString("'detalle/(:num)', 'Inspecciones::detalle/\$1'", $grupo);
        $this->assertStringContainsString("'fotografias/ver/(:segment)', 'Inspector\Fotografias::ver/\$1'", $grupo);
        $this->assertStringContainsString("'fotografias/mini/(:segment)', 'Inspector\Fotografias::miniatura/\$1'", $grupo);
    }

    public function testElGrupoCompartidoNoExponeAltaNiSincronizacion(): void
    {
        $grupo = $this->grupoDeRutas("\$routes->group('inspecciones'");

        foreach (['nueva/', 'sincronizar'] as $prohibido) {
            $this->assertStringNotContainsString(
                $prohibido,
                $grupo,
                'El alta y la sincronización son exclusivas del inspector y no se exponen en la ruta compartida.'
            );
        }
    }

    public function testElGrupoCompartidoNoTieneListadoDeObras(): void
    {
        /* El listado vive en el grupo de cada rol: los roles administrativos
           entran desde una obra ya seleccionada y no consultan un listado
           global. */
        $grupo = $this->grupoDeRutas("\$routes->group('inspecciones'");

        $this->assertStringNotContainsString("Inspecciones::index", $grupo);

        $rutas = $this->leerApp('Config/Routes.php');

        $this->assertStringContainsString("'inspecciones', 'Inspector\Inspecciones::index'", $rutas);
        $this->assertStringContainsString("'inspecciones', 'Inspecciones::index'", $rutas);
    }

    public function testElFlujoPrevioDelInspectorConservaSusURLs(): void
    {
        $grupo = $this->grupoDeRutas("\$routes->group('inspector'");

        $this->assertStringContainsString("'role:INSPECTOR'", $grupo);
        $this->assertStringContainsString("'inspecciones/ver/(:num)', 'Inspector\Inspecciones::ver/\$1'", $grupo);
        $this->assertStringContainsString("'inspecciones/detalle/(:num)', 'Inspector\Inspecciones::detalle/\$1'", $grupo);
        $this->assertStringContainsString("'inspecciones/nueva/(:num)', 'Inspector\Inspecciones::nueva/\$1'", $grupo);
        $this->assertStringContainsString("'fotografias/ver/(:segment)', 'Inspector\Fotografias::ver/\$1'", $grupo);
    }

    public function testElGrupoDeConsultaExponeSuListado(): void
    {
        $grupo = $this->grupoDeRutas("\$routes->group('consulta'");

        $this->assertStringContainsString("'role:CONSULTA'", $grupo);
        $this->assertStringContainsString("'inspecciones', 'Inspecciones::index'", $grupo);

        $this->assertStringNotContainsString(
            'nueva/',
            $grupo,
            'El rol de consulta no crea inspecciones.'
        );
    }

    /* ----------------------------------------------------------------
     * Autorización
     * ---------------------------------------------------------------- */

    public function testElServicioDistingueInspeccionarDeConsultar(): void
    {
        $servicio = $this->leerApp('Services/AccesoInspecciones.php');

        $inspeccionar = $this->metodo($servicio, 'public function puedeInspeccionar(');
        $consultar    = $this->metodo($servicio, 'public function puedeConsultarObra(');

        $this->assertStringContainsString(
            "in_array('INSPECTOR', \$roles, true)",
            $inspeccionar,
            'Crear una inspección sigue exigiendo el rol INSPECTOR.'
        );
        $this->assertStringContainsString('esVigente(', $inspeccionar, 'Y asignación vigente.');

        $this->assertStringContainsString('ROLES_CONSULTA_OBRA', $consultar);
        $this->assertStringContainsString(
            'haTenidoAsignacion(',
            $consultar,
            'El inspector consulta también las obras con asignación ya cerrada.'
        );
    }

    public function testLosRolesQueVenLaFichaConsultanElHistorialDeLaObra(): void
    {
        $servicio = $this->leerApp('Services/AccesoInspecciones.php');

        $this->assertStringContainsString(
            "ROLES_CONSULTA_OBRA = ['SUPERADMINISTRADOR', 'ADMINISTRADOR', 'CONSULTA']",
            $servicio,
            'La consulta sigue al acceso a la obra: los mismos roles que ven su ficha.'
        );
    }

    public function testElModeloExponeLaAsignacionHistorica(): void
    {
        $modelo = $this->leerApp('Models/InspectoresObrasModel.php');

        $metodo = $this->metodo($modelo, 'public function haTenidoAsignacion(');

        $this->assertStringContainsString("->where('obra_id', \$obraId)", $metodo);
        $this->assertStringContainsString("->where('usuario_id', \$usuarioId)", $metodo);
        $this->assertStringNotContainsString(
            "'fecha_fin'",
            $metodo,
            'La consulta histórica no exige que la asignación siga abierta.'
        );
    }

    public function testElListadoDeObrasFiltraPorInspectorSinDuplicar(): void
    {
        $modelo = $this->leerApp('Models/ObraModel.php');

        $metodo = $this->metodo($modelo, 'public function listarParaConsulta(');

        $this->assertStringContainsString("whereIn('obras.id'", $metodo, 'El filtro por inspector es una subconsulta.');
        $this->assertStringContainsString("->from('inspectores_obras')", $metodo);
        $this->assertStringNotContainsString(
            "->join('inspectores_obras', 'inspectores_obras.obra_id = obras.id')\n",
            $metodo,
            'Un JOIN directo multiplicaría filas con varios períodos de asignación.'
        );
    }

    /* ----------------------------------------------------------------
     * Vista del listado
     * ---------------------------------------------------------------- */

    public function testElListadoEsDeSoloConsultaYNoUsaJavaScript(): void
    {
        $vista = $this->leerApp('Views/inspecciones/obras.php');

        $this->assertStringContainsString("extend('layouts/auth')", $vista);
        $this->assertStringContainsString('assets/css/pages/inspecciones-obras.css', $vista);

        foreach (['assets/js/', 'inspecciones/nueva', 'sincronizar'] as $prohibido) {
            $this->assertStringNotContainsString(
                $prohibido,
                $vista,
                'El listado se entrega desde el servidor y no ofrece alta ni sincronización.'
            );
        }

        $this->assertStringContainsString("\$base . '/ver/'", $vista, 'Cada obra abre su navegación histórica.');
    }

    public function testLasVistasHistoricasNoFijanRutas(): void
    {
        foreach (['inspector/inspecciones.php', 'inspector/inspeccion_detalle.php'] as $archivo) {
            $vista = $this->leerApp('Views/' . $archivo);

            $this->assertMatchesRegularExpression(
                '/\$base\s*=\s*\$base\s*\?\?\s*\'\/inspector\/inspecciones\'/',
                $vista,
                "{$archivo}: el prefijo de la navegación lo declara el punto de entrada."
            );
        }

        $detalle = $this->leerApp('Views/inspector/inspeccion_detalle.php');

        $this->assertMatchesRegularExpression(
            '/\$baseFotos\s*=\s*\$base_fotos\s*\?\?\s*\'\/inspector\/fotografias\'/',
            $detalle
        );
    }

    /* ----------------------------------------------------------------
     * Entradas de navegación
     * ---------------------------------------------------------------- */

    public function testElSidebarOfreceInspeccionesATodosLosRolesQueLoNavegan(): void
    {
        $sidebar = $this->leerApp('Views/layouts/partials/sidebar.php');

        $this->assertStringContainsString('$esInspector || $esConsulta', $sidebar, 'Los listados son por rol.');
        $this->assertStringContainsString("'/inspector/inspecciones' : '/consulta/inspecciones'", $sidebar);
        $this->assertStringContainsString('<span>Inspecciones</span>', $sidebar);
    }

    public function testLaFichaDeObraEnlazaAlHistorialDeInspecciones(): void
    {
        $ficha = $this->leerApp('Views/obras/ficha.php');

        $this->assertStringContainsString('$puede_consultar_inspecciones', $ficha);
        $this->assertStringContainsString(
            "site_url('/inspecciones/ver/' . (int) \$obra->id)",
            $ficha,
            'Los roles administrativos entran al historial desde la obra ya seleccionada.'
        );

        /* El botón se decide en backend, no con roles dentro de la vista. */
        $this->assertStringNotContainsString('session()->get(\'roles\')', $ficha);
    }

    /* ----------------------------------------------------------------
     * Infraestructura offline
     * ---------------------------------------------------------------- */

    public function testElEstiloDelListadoEstaEnElPrecache(): void
    {
        $this->assertStringContainsString(
            "'/assets/css/pages/inspecciones-obras.css'",
            $this->leerPublic('sw.js'),
            'Sin estar en el app shell la página perdería sus estilos sin conexión.'
        );
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
            'E.6 reorganiza accesos y navegación: no introduce cambios de esquema.'
        );
    }
}