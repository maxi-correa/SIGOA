<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Contratos estructurales del buscador y filtro por estado del listado de
 * obras, compartido por los dashboards de SUPERADMINISTRADOR, ADMINISTRADOR
 * y CONSULTA.
 *
 * El listado se filtra en el servidor (la paginación es server-side), por lo
 * que la vista usa un formulario GET cuyos parámetros llegan a
 * ObraModel::listarPaginado() a través de BaseController::datosDashboardObras().
 *
 * @internal
 */
final class ObrasBuscadorFiltroPantallaTest extends CIUnitTestCase
{
    public function testLaVistaExponeElFormularioDeBusquedaYSinDeshabilitar(): void
    {
        $vista = $this->leerApp('Views/obras/index.php');

        $this->assertStringContainsString('id="obrasFiltrosForm"', $vista);
        $this->assertStringContainsString('method="get"', $vista);
        $this->assertStringContainsString('name="buscar"', $vista);
        $this->assertStringContainsString('name="estado"', $vista);
        $this->assertStringContainsString('Todos los estados', $vista);

        /* Los campos ya no son reservados: no deben quedar deshabilitados. */
        $this->assertDoesNotMatchRegularExpression('/id="buscarObra"[^>]*\sdisabled/', $vista);
        $this->assertDoesNotMatchRegularExpression('/id="filtroEstado"[^>]*\sdisabled/', $vista);
        $this->assertStringNotContainsString('Disponible próximamente', $vista);
        $this->assertDoesNotMatchRegularExpression('/obras-filtros[^>]*aria-hidden="true"/', $vista);
    }

    public function testLaVistaConservaLosFiltrosEnLaPaginacion(): void
    {
        $vista = $this->leerApp('Views/obras/index.php');

        /* La paginación debe reconstruir la query conservando los filtros. */
        $this->assertStringContainsString('http_build_query', $vista);
        $this->assertStringContainsString("\$urlPagina", $vista);
        $this->assertStringContainsString('$paramsPagina', $vista);
        $this->assertStringNotContainsString('href="?page=', $vista);
    }

    public function testLaVistaDiferenciaElEstadoVacioConFiltrosActivos(): void
    {
        $vista = $this->leerApp('Views/obras/index.php');

        $this->assertStringContainsString('hay_filtros', $vista);
        $this->assertStringContainsString('No se encontraron obras con los criterios seleccionados.', $vista);
        $this->assertStringContainsString('Aún no hay obras cargadas.', $vista);
    }

    public function testLaVistaIncluyeElBotonDeBusquedaYLaAccionDeLimpiar(): void
    {
        $vista = $this->leerApp('Views/obras/index.php');

        /* Botón visible de lupa que envía el formulario, con nombre accesible
           aunque solo muestre el icono. */
        $this->assertStringContainsString('type="submit"', $vista);
        $this->assertStringContainsString('bi bi-search', $vista);
        $this->assertStringContainsString('aria-label="Buscar obras"', $vista);

        /* Acción para restablecer los filtros: enlace a la misma pantalla sin
           query string (current_url() no incluye la cadena de consulta). El
           botón de la fila muestra solo el icono, con nombre accesible y
           tooltip. */
        $this->assertStringContainsString('Limpiar filtros', $vista);
        $this->assertStringContainsString('aria-label="Limpiar filtros"', $vista);
        $this->assertStringContainsString('bi bi-eraser', $vista);
        $this->assertStringContainsString('current_url()', $vista);
    }

    public function testElBaseControllerLeeYValidaLosFiltrosDelQueryString(): void
    {
        $controlador = $this->leerApp('Controllers/BaseController.php');

        $this->assertStringContainsString("getGet('buscar')", $controlador);
        $this->assertStringContainsString("getGet('estado')", $controlador);
        $this->assertStringContainsString('normalizarTerminoBusqueda', $controlador);
        $this->assertStringContainsString('validarEstadoFiltro', $controlador);
        $this->assertStringContainsString("'hay_filtros'", $controlador);
        $this->assertStringContainsString("'estado_filtro'", $controlador);
    }

    public function testElControladorValidaElEstadoContraElCatalogoReal(): void
    {
        $controlador = $this->leerApp('Controllers/BaseController.php');

        $this->assertStringContainsString('findAllActivos', $controlador);
        $this->assertStringContainsString('is_numeric', $controlador);
    }

    public function testElModeloAplicaAmbosFiltrosAntesDePaginar(): void
    {
        $modelo = $this->leerApp('Models/ObraModel.php');

        $this->assertStringContainsString("like('obras.nombre'", $modelo);
        $this->assertStringContainsString("orLike('obras.expediente_municipal'", $modelo);
        $this->assertStringContainsString("orLike('obras.numero_licitacion'", $modelo);
        $this->assertStringContainsString("orLike('barrios.nombre'", $modelo);
        $this->assertStringContainsString("orLike('empresas.razon_social'", $modelo);
        $this->assertStringContainsString("where('obras.estado_obra_id'", $modelo);

        /* La búsqueda se agrupa, se recorta el término y se escapan los
           comodines LIKE antes de construir el patrón. */
        $this->assertStringContainsString('groupStart()', $modelo);
        $this->assertStringContainsString('groupEnd()', $modelo);
        $this->assertStringContainsString('trim($buscar)', $modelo);
        $this->assertStringContainsString('escapeLikeString', $modelo);
    }

    public function testLosTresDashboardsSiguenCompartiendoLaMismaVistaYControlador(): void
    {
        foreach (['Superadmin', 'Admin', 'Consulta'] as $rol) {
            $controlador = $this->leerApp("Controllers/{$rol}/Dashboard.php");

            $this->assertStringContainsString('datosDashboardObras', $controlador, "El dashboard {$rol} debe usar los datos compartidos");
            $this->assertStringContainsString('obras/index', $controlador, "El dashboard {$rol} debe usar la vista compartida");
        }
    }

    public function testLasRutasDeLosDashboardsConservanLaAutorizacionPorRol(): void
    {
        $rutas = $this->leerApp('Config/Routes.php');

        $this->assertStringContainsString("role:SUPERADMINISTRADOR", $rutas);
        $this->assertStringContainsString("role:ADMINISTRADOR", $rutas);
        $this->assertStringContainsString("role:CONSULTA", $rutas);
    }

    public function testElScriptDeObrasAplicaBusquedaExplicitaYFiltroInmediato(): void
    {
        $js = file_get_contents(FCPATH . 'assets/js/pages/obras.js');

        $this->assertNotFalse($js, 'No se pudo leer obras.js');

        $this->assertStringContainsString('obrasFiltrosForm', $js ?: '');
        $this->assertStringContainsString('buscarObra', $js ?: '');
        $this->assertStringContainsString('filtroEstado', $js ?: '');
        $this->assertStringContainsString("addEventListener('change'", $js ?: '');
        $this->assertStringContainsString('.submit()', $js ?: '');
        $this->assertStringContainsString('setSelectionRange', $js ?: '');

        /* La búsqueda por texto es explícita: no hay envío automático por tecla. */
        $this->assertStringNotContainsString("addEventListener('input'", $js ?: '');
        $this->assertStringNotContainsString('setTimeout', $js ?: '');
    }

    public function testElCssYaNoContieneElEstiloDeCampoDeshabilitadoReservado(): void
    {
        $css = file_get_contents(FCPATH . 'assets/css/pages/obras.css');

        $this->assertNotFalse($css, 'No se pudo leer obras.css');
        $this->assertStringNotContainsString('.obras-filtros .form-control:disabled', $css ?: '');
    }

    private function leerApp(string $relativa): string
    {
        $ruta      = APPPATH . ltrim($relativa, '/\\');
        $contenido = file_get_contents($ruta);

        $this->assertNotFalse($contenido, "No se pudo leer: {$relativa}");

        return $contenido ?: '';
    }
}
