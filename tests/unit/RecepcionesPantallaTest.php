<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Contratos estructurales de la pantalla de recepciones (BLOQUE 3 RP/RD).
 *
 * @internal
 */
final class RecepcionesPantallaTest extends CIUnitTestCase
{
    public function testLaFichaHabilitaVerRecepcionesHaciaLaPantallaDeLaObra(): void
    {
        $ficha = $this->leerApp('Views/obras/ficha.php');

        $this->assertStringContainsString('/obras/recepciones/', $ficha);
        $this->assertStringContainsString('Ver Recepciones', $ficha);
        $this->assertStringContainsString('ficha-btn-recepciones', $ficha);
    }

    public function testLasRutasDeRecepcionesRespetanLosRoles(): void
    {
        $rutas = $this->leerApp('Config/Routes.php');

        $this->assertStringContainsString("get('/obras/recepciones/(:num)', 'Recepciones::index/\$1'", $rutas);
        $this->assertStringContainsString('role:SUPERADMINISTRADOR,ADMINISTRADOR,CONSULTA', $rutas);
        $this->assertStringContainsString("post('/obras/recepciones/provisoria', 'Recepciones::registrarProvisoria'", $rutas);
        $this->assertStringContainsString("post('/obras/recepciones/provisoria/actualizar', 'Recepciones::actualizarProvisoria'", $rutas);
        $this->assertStringContainsString("post('/obras/recepciones/definitiva', 'Recepciones::registrarDefinitiva'", $rutas);
        $this->assertStringNotContainsString('Recepciones::eliminar', $rutas);
    }

    public function testLaVistaNoUsaEstilosNiJavascriptEmbebidos(): void
    {
        $vista = $this->leerApp('Views/obras/recepciones.php');

        $this->assertStringContainsString('assets/css/pages/obra-recepciones.css', $vista);
        $this->assertStringNotContainsString('<style', $vista);
        $this->assertStringNotContainsString('<script>', $vista);
        $this->assertStringContainsString('Registrar recepción provisoria', $vista);
        $this->assertStringContainsString('Registrar recepción definitiva', $vista);
        $this->assertStringContainsString('Corregir datos de la recepción provisoria', $vista);
    }

    public function testLaVistaExplicaLasReglasYLosEstados(): void
    {
        $vista = $this->leerApp('Views/obras/recepciones.php');

        $this->assertStringContainsString('Recepción provisoria', $vista);
        $this->assertStringContainsString('Recepción definitiva', $vista);
        $this->assertStringContainsString('EN PLAZO DE CONSERVACIÓN', $vista);
        $this->assertStringContainsString('FINALIZADA', $vista);
        $this->assertStringContainsString('fecha_prevista_definitiva', $vista);
        $this->assertStringContainsString('recepcion-estado-registrada', $vista);
        $this->assertStringContainsString('recepcion-estado-pendiente', $vista);
        $this->assertStringContainsString('ficha-btn-volver', $vista);
    }

    public function testElControladorAplicaRolesYDelegaLaDefinitivaEnElServicio(): void
    {
        $controlador = $this->leerApp('Controllers/Recepciones.php');

        $this->assertStringContainsString('ROLES_EDICION', $controlador);
        $this->assertStringContainsString('permiteRegistrarRecepcionProvisoria', $controlador);
        $this->assertStringContainsString('permiteCorregirRecepcionProvisoria', $controlador);
        $this->assertStringContainsString('PlazoObra::fechaPrevistaDefinitiva', $controlador);
        $this->assertStringContainsString('transStart', $controlador);
        $this->assertStringContainsString('bloquearObra', $controlador);
        $this->assertStringContainsString('RecepcionDefinitivaService', $controlador);
    }

    public function testElServicioDeLaDefinitivaAplicaTransaccionYAuditoria(): void
    {
        $servicio = $this->leerApp('Services/RecepcionDefinitivaService.php');

        $this->assertStringContainsString('transStart', $servicio);
        $this->assertStringContainsString('transComplete', $servicio);
        $this->assertStringContainsString('transRollback', $servicio);
        $this->assertStringContainsString('bloquearObra', $servicio);
        $this->assertStringContainsString('permiteRegistrarRecepcionDefinitiva', $servicio);
        $this->assertStringContainsString('ESTADO_FINALIZADA', $servicio);
        $this->assertStringContainsString('AuditoriaModel', $servicio);
        $this->assertStringContainsString('ACTA DE RECEPCIÓN DEFINITIVA', $servicio);
    }

    public function testLosModelosBloqueanLaEdicionYLaEliminacionDirectas(): void
    {
        $provisoria = $this->leerApp('Models/RecepcionProvisoriaModel.php');
        $definitiva = $this->leerApp('Models/RecepcionDefinitivaModel.php');

        $this->assertStringContainsString('function porObra(', $provisoria);
        $this->assertStringContainsString('function crear(', $provisoria);
        $this->assertStringContainsString('function actualizar(', $provisoria);
        $this->assertStringContainsString('function update(', $provisoria);
        $this->assertStringContainsString('function delete(', $provisoria);

        $this->assertStringContainsString('function porObra(', $definitiva);
        $this->assertStringContainsString('function crear(', $definitiva);
        $this->assertStringContainsString('function update(', $definitiva);
        $this->assertStringContainsString('function delete(', $definitiva);
        $this->assertStringNotContainsString('function actualizar(', $definitiva);
    }

    public function testElEstadoCentralizaLasReglasDeRecepcion(): void
    {
        $modelo = $this->leerApp('Models/EstadoObraModel.php');

        $this->assertStringContainsString('PERMITEN_RECEPCION_PROVISORIA', $modelo);
        $this->assertStringContainsString('PERMITEN_RECEPCION_DEFINITIVA', $modelo);
        $this->assertStringContainsString('PERMITEN_CORREGIR_RECEPCION_PROVISORIA', $modelo);
        $this->assertStringContainsString('permiteRegistrarRecepcionProvisoria', $modelo);
        $this->assertStringContainsString('permiteRegistrarRecepcionDefinitiva', $modelo);
        $this->assertStringContainsString('permiteCorregirRecepcionProvisoria', $modelo);
    }

    private function leerApp(string $relativa): string
    {
        $ruta      = APPPATH . ltrim($relativa, '/\\');
        $contenido = file_get_contents($ruta);

        $this->assertNotFalse($contenido, "No se pudo leer: {$relativa}");

        return $contenido ?: '';
    }
}
