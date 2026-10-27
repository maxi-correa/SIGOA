<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Contratos estructurales de la pantalla y servicios de recepciones
 * (BLOQUES 3 a 5).
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
        $this->assertStringContainsString("post('/obras/recepciones/definitiva/actualizar', 'Recepciones::actualizarDefinitiva'", $rutas);
        $this->assertStringContainsString("post('/obras/recepciones/correccion', 'Recepciones::corregirConjunta'", $rutas);
        $this->assertStringNotContainsString('Recepciones::eliminar', $rutas);
    }

    public function testLaVistaNoUsaEstilosNiJavascriptEmbebidos(): void
    {
        $vista = $this->leerApp('Views/obras/recepciones.php');

        $this->assertStringContainsString('assets/css/pages/obra-recepciones.css', $vista);
        $this->assertStringNotContainsString('<style', $vista);
        $this->assertStringNotContainsString('<script', $vista);
    }

    public function testLaVistaOfreceAltasCorreccionesYHistorial(): void
    {
        $vista = $this->leerApp('Views/obras/recepciones.php');

        $this->assertStringContainsString('Registrar recepción provisoria', $vista);
        $this->assertStringContainsString('Registrar recepción definitiva', $vista);
        $this->assertStringContainsString('Corregir recepción provisoria', $vista);
        $this->assertStringContainsString('Corregir recepción definitiva', $vista);
        $this->assertStringContainsString('Corregir recepción provisoria y definitiva juntas', $vista);
        $this->assertStringContainsString('Historial de recepciones', $vista);
        $this->assertStringContainsString("'documento_id'", $vista);
        $this->assertStringContainsString('name="rp[fecha_recepcion]"', $vista);
        $this->assertStringContainsString('name="rd[fecha_recepcion]"', $vista);
    }

    public function testLaVistaUsaComponentesYEstadosExistentes(): void
    {
        $vista = $this->leerApp('Views/obras/recepciones.php');

        $this->assertStringContainsString('EN PLAZO DE CONSERVACIÓN', $vista);
        $this->assertStringContainsString('FINALIZADA', $vista);
        $this->assertStringContainsString('recepcion-estado-registrada', $vista);
        $this->assertStringContainsString('recepcion-estado-pendiente', $vista);
        $this->assertStringContainsString('ficha-btn-volver', $vista);
        $this->assertStringContainsString('sigoa-table', $vista);
    }

    public function testElControladorDelegaEnLosServicios(): void
    {
        $controlador = $this->leerApp('Controllers/Recepciones.php');

        $this->assertStringContainsString('ROLES_EDICION', $controlador);
        $this->assertStringContainsString('RecepcionProvisoriaService', $controlador);
        $this->assertStringContainsString('RecepcionCorreccionService', $controlador);
        $this->assertStringContainsString('RecepcionDefinitivaService', $controlador);
        $this->assertStringContainsString('historialRecepciones', $controlador);
        $this->assertStringNotContainsString('transStart', $controlador);
        $this->assertStringNotContainsString('transRollback', $controlador);
    }

    public function testLosServiciosAplicanTransaccionAuditoriaYBloqueo(): void
    {
        foreach ([
            'Services/RecepcionProvisoriaService.php',
            'Services/RecepcionCorreccionService.php',
            'Services/RecepcionDefinitivaService.php',
        ] as $servicio) {
            $contenido = $this->leerApp($servicio);

            $this->assertStringContainsString('transStart', $contenido, $servicio);
            $this->assertStringContainsString('transComplete', $contenido, $servicio);
            $this->assertStringContainsString('transRollback', $contenido, $servicio);
            $this->assertStringContainsString('resetTransStatus', $contenido, $servicio);
            $this->assertStringContainsString('bloquearObra', $contenido, $servicio);
            $this->assertStringContainsString('AuditoriaModel', $contenido, $servicio);
        }

        $definitiva = $this->leerApp('Services/RecepcionDefinitivaService.php');
        $this->assertStringContainsString('ESTADO_FINALIZADA', $definitiva);
        $this->assertStringContainsString('TIPO_ACTA', $definitiva);

        $provisoria = $this->leerApp('Services/RecepcionProvisoriaService.php');
        $this->assertStringNotContainsString('ESTADO_FINALIZADA', $provisoria);
        $this->assertStringNotContainsString('recepciones_definitivas\', ', $provisoria);
    }

    public function testLasReglasCompartidasDeValidacionEstanCentralizadas(): void
    {
        $validacion = $this->leerApp('Services/RecepcionValidacion.php');

        $this->assertStringContainsString('function fechaProvisoria', $validacion);
        $this->assertStringContainsString('function fechaDefinitiva', $validacion);
        $this->assertStringContainsString('function plazo', $validacion);
        $this->assertStringContainsString('function acta', $validacion);
        $this->assertStringContainsString('PLAZO_MAXIMO_DIAS = 36500', $validacion);
    }

    public function testLosModelosSoloEscribenPorMetodosDeServicio(): void
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
        $this->assertStringContainsString('function actualizar(', $definitiva);
        $this->assertStringContainsString('function update(', $definitiva);
        $this->assertStringContainsString('function delete(', $definitiva);
    }

    public function testElModeloDeAuditoriaSoloInserta(): void
    {
        $auditoria = $this->leerApp('Models/AuditoriaModel.php');

        $this->assertStringContainsString('function registrar(', $auditoria);
        $this->assertStringContainsString('function registrarCambios(', $auditoria);
        $this->assertStringContainsString('function historialRecepciones(', $auditoria);
        $this->assertStringNotContainsString('function delete(', $auditoria);
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
