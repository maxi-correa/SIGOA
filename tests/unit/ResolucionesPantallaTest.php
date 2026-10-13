<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Contratos estructurales de la pantalla de resoluciones de obra.
 *
 * @internal
 */
final class ResolucionesPantallaTest extends CIUnitTestCase
{
    public function testLaFichaHabilitaVerResolucionesHaciaLaPantallaDeLaObra(): void
    {
        $ficha = $this->leerApp('Views/obras/ficha.php');

        $this->assertStringContainsString('/obras/resoluciones/', $ficha);
        $this->assertStringContainsString('Ver Resoluciones', $ficha);
        $this->assertDoesNotMatchRegularExpression(
            '/ficha-btn-resoluciones[^>]+\sdisabled/',
            $ficha
        );
    }

    public function testLasRutasDeResolucionesRespetanLosRoles(): void
    {
        $rutas = $this->leerApp('Config/Routes.php');

        $this->assertStringContainsString("get('/obras/resoluciones/(:num)', 'Resoluciones::index/\$1'", $rutas);
        $this->assertStringContainsString("role:SUPERADMINISTRADOR,ADMINISTRADOR,CONSULTA", $rutas);
        $this->assertStringContainsString("post('/obras/resoluciones/crear', 'Resoluciones::crear'", $rutas);
        $this->assertStringContainsString("post('/obras/resoluciones/actualizar', 'Resoluciones::actualizar'", $rutas);
        $this->assertStringContainsString("post('/obras/resoluciones/confirmar', 'Resoluciones::confirmar'", $rutas);
        $this->assertStringContainsString("post('/obras/resoluciones/eliminar', 'Resoluciones::eliminar'", $rutas);
    }

    public function testLaVistaNoUsaEstilosNiJavascriptEmbebidos(): void
    {
        $vista = $this->leerApp('Views/obras/resoluciones.php');

        $this->assertStringContainsString("assets/css/pages/obra-resoluciones.css", $vista);
        $this->assertStringContainsString("assets/js/pages/obra-resoluciones.js", $vista);
        $this->assertStringNotContainsString('<style', $vista);
        $this->assertStringNotContainsString('<script>', $vista);
        $this->assertStringContainsString('Registrar resolución', $vista);
        $this->assertStringContainsString('¿Confirmar resolución?', $vista);
        $this->assertStringContainsString('¿Eliminar resolución?', $vista);
    }

    public function testLaVistaExhibeEstadoEIdentificacionYReglasPorTipo(): void
    {
        $vista = $this->leerApp('Views/obras/resoluciones.php');

        $this->assertStringContainsString('resolucion-estado', $vista);
        $this->assertStringContainsString('Pendiente', $vista);
        $this->assertStringContainsString('Confirmada', $vista);
        $this->assertStringContainsString('data-monto', $vista);
        $this->assertStringContainsString('data-dias', $vista);
        $this->assertStringContainsString('ficha-btn-volver', $vista);
        $this->assertStringContainsString('expediente_municipal', $vista);
    }

    public function testElControladorNormalizaYvalidaConLasReglasDeLaBiblioteca(): void
    {
        $controlador = $this->leerApp('Controllers/Resoluciones.php');

        $this->assertStringContainsString('ResolucionObra::validar', $controlador);
        $this->assertStringContainsString('ResolucionObra::parseMonto', $controlador);
        $this->assertStringContainsString('ResolucionObra::parseDias', $controlador);
        $this->assertStringContainsString('permiteRegistrarResoluciones', $controlador);
        $this->assertStringContainsString('existeNumeroAnio', $controlador);
        $this->assertStringContainsString('ROLES_EDICION', $controlador);
    }

    public function testElModeloBloqueaLaEdicionDeUnaResolucionConfirmada(): void
    {
        $modelo = $this->leerApp('Models/ResolucionModel.php');

        $this->assertStringContainsString('estaConfirmada', $modelo);
        $this->assertStringContainsString('public function confirmar(', $modelo);
        $this->assertStringContainsString('public function update(', $modelo);
        $this->assertStringContainsString('public function delete(', $modelo);
    }

    public function testLasResolucionesInformativasExigenObservacionesAlConfirmar(): void
    {
        $controlador = $this->leerApp('Controllers/Resoluciones.php');
        $vista       = $this->leerApp('Views/obras/resoluciones.php');

        $this->assertStringContainsString('puedeConfirmarse', $controlador);
        $this->assertStringContainsString('requiereObservaciones', $controlador);
        $this->assertStringContainsString("reglas['observaciones']", $controlador);

        $this->assertStringContainsString('data-observaciones', $vista);
        $this->assertStringContainsString('observaciones-requerido', $vista);
    }

    public function testElCatalogoDeTiposFijaUnOrdenIndependienteDelIdConOtroAlFinal(): void
    {
        $modelo = $this->leerApp('Models/TipoResolucionModel.php');

        $this->assertStringContainsString('usort', $modelo);
        $this->assertStringContainsString('ResolucionObra::TIPO_OTRO', $modelo);
        $this->assertStringContainsString('findAllActivos', $modelo);
    }

    public function testLosEstadosQuePermitenRegistrarResoluciones(): void
    {
        $modelo = $this->leerApp('Models/EstadoObraModel.php');

        $this->assertStringContainsString('PERMITEN_REGISTRAR_RESOLUCIONES', $modelo);
        $this->assertStringContainsString('EN EJECUCIÓN', $modelo);
        $this->assertStringContainsString('NEUTRALIZADA', $modelo);
    }

    private function leerApp(string $relativa): string
    {
        $ruta      = APPPATH . ltrim($relativa, '/\\');
        $contenido = file_get_contents($ruta);

        $this->assertNotFalse($contenido, "No se pudo leer: {$relativa}");

        return $contenido ?: '';
    }
}
