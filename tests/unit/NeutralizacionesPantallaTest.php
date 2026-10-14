<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Contratos estructurales de la pantalla de neutralizaciones (BLOQUE 5).
 *
 * @internal
 */
final class NeutralizacionesPantallaTest extends CIUnitTestCase
{
    public function testLaFichaHabilitaVerNeutralizacionesHaciaLaPantallaDeLaObra(): void
    {
        $ficha = $this->leerApp('Views/obras/ficha.php');

        $this->assertStringContainsString('/obras/neutralizaciones/', $ficha);
        $this->assertStringContainsString('Ver Neutralizaciones', $ficha);
        $this->assertStringContainsString('Días neutralizados', $ficha);
        $this->assertStringContainsString('neutralizacion-estado-abierta', $ficha);
    }

    public function testLasRutasDeNeutralizacionesRespetanLosRoles(): void
    {
        $rutas = $this->leerApp('Config/Routes.php');

        $this->assertStringContainsString("get('/obras/neutralizaciones/(:num)', 'Neutralizaciones::index/\$1'", $rutas);
        $this->assertStringContainsString("role:SUPERADMINISTRADOR,ADMINISTRADOR,CONSULTA", $rutas);
        $this->assertStringContainsString("post('/obras/neutralizaciones/crear', 'Neutralizaciones::crear'", $rutas);
        $this->assertStringContainsString("post('/obras/neutralizaciones/reiniciar', 'Neutralizaciones::reiniciar'", $rutas);
    }

    public function testLaVistaNoUsaEstilosNiJavascriptEmbebidos(): void
    {
        $vista = $this->leerApp('Views/obras/neutralizaciones.php');

        $this->assertStringContainsString('assets/css/pages/obra-neutralizaciones.css', $vista);
        $this->assertStringContainsString('assets/js/pages/obra-neutralizaciones.js', $vista);
        $this->assertStringNotContainsString('<style', $vista);
        $this->assertStringNotContainsString('<script>', $vista);
        $this->assertStringContainsString('Registrar neutralización', $vista);
        $this->assertStringContainsString('Registrar reinicio', $vista);
    }

    public function testElControladorAplicaTransaccionYReglasDeEstado(): void
    {
        $controlador = $this->leerApp('Controllers/Neutralizaciones.php');

        $this->assertStringContainsString('ROLES_EDICION', $controlador);
        $this->assertStringContainsString('permiteIniciarNeutralizacion', $controlador);
        $this->assertStringContainsString('permiteReiniciarNeutralizacion', $controlador);
        $this->assertStringContainsString('tieneAbierta', $controlador);
        $this->assertStringContainsString('transStart', $controlador);
        $this->assertStringContainsString('transComplete', $controlador);
        $this->assertStringContainsString('bloquearObra', $controlador);
        $this->assertStringContainsString('ESTADO_NEUTRALIZADA', $controlador);
        $this->assertStringContainsString('ESTADO_EN_EJECUCION', $controlador);
        $this->assertStringContainsString("'total_neutralizado_dias'", $controlador);
    }

    public function testElModeloBloqueaLaEdicionYLaEliminacion(): void
    {
        $modelo = $this->leerApp('Models/NeutralizacionModel.php');

        $this->assertStringContainsString('public function update(', $modelo);
        $this->assertStringContainsString('public function delete(', $modelo);
        $this->assertStringContainsString('return false;', $modelo);
    }

    public function testLosEstadosQuePermitenNeutralizarYReiniciar(): void
    {
        $modelo = $this->leerApp('Models/EstadoObraModel.php');

        $this->assertStringContainsString('ESTADO_EN_EJECUCION', $modelo);
        $this->assertStringContainsString('ESTADO_NEUTRALIZADA', $modelo);
        $this->assertStringContainsString('permiteIniciarNeutralizacion', $modelo);
        $this->assertStringContainsString('permiteReiniciarNeutralizacion', $modelo);
    }

    public function testLaRestriccionDeUnaSolaNeutralizacionAbiertaEstaEnLaBase(): void
    {
        $this->assertNotSame([], glob(APPPATH . 'Database/Migrations/*AddNeutralizacionAbiertaUnica.php'));
    }

    private function leerApp(string $relativa): string
    {
        $ruta      = APPPATH . ltrim($relativa, '/\\');
        $contenido = file_get_contents($ruta);

        $this->assertNotFalse($contenido, "No se pudo leer: {$relativa}");

        return $contenido ?: '';
    }
}
