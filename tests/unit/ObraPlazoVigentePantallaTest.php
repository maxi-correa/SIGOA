<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Contratos estructurales del desglose del plazo contractual vigente en
 * la pantalla Ver Obra.
 *
 * @internal
 */
final class ObraPlazoVigentePantallaTest extends CIUnitTestCase
{
    public function testElControladorCalculaElPlazoVigenteDesdeLaObraYLasResoluciones(): void
    {
        $controlador = $this->leerApp('Controllers/Obras.php');

        $this->assertStringContainsString('PlazoObra::calcularVigente', $controlador);
        $this->assertStringContainsString('PlazoObra::fechaFinalizacion', $controlador);
        $this->assertStringContainsString('listarPorObra', $controlador);
        $this->assertStringContainsString('NeutralizacionModel', $controlador);
        $this->assertStringContainsString('PlazoObra::totalNeutralizado', $controlador);
    }

    public function testLaReglaDeAmpliacionViveEnLaBibliotecaDeResoluciones(): void
    {
        $biblioteca = $this->leerApp('Libraries/ResolucionObra.php');

        $this->assertStringContainsString('TIPOS_AMPLIACION_PLAZO', $biblioteca);
        $this->assertStringContainsString('function ampliaPlazo', $biblioteca);
    }

    public function testElModeloDeNeutralizacionPersistePeriodosInmutables(): void
    {
        $modelo = $this->leerApp('Models/NeutralizacionModel.php');

        $this->assertStringContainsString('function tieneAbierta', $modelo);
        $this->assertStringContainsString('function abierta', $modelo);
        $this->assertStringContainsString('function proximoNumero', $modelo);
        $this->assertStringContainsString('function crear', $modelo);
        $this->assertStringContainsString('function reiniciar', $modelo);
        $this->assertStringContainsString('function update', $modelo);
        $this->assertStringContainsString('function delete', $modelo);
        $this->assertStringContainsString("'fecha_reinicio'", $modelo);
    }

    public function testLaVistaMuestraElDesgloseDeAmpliacionesYElTotal(): void
    {
        $vista = $this->leerApp('Views/obras/ficha.php');

        $this->assertStringContainsString('plazo-desglose', $vista);
        $this->assertStringContainsString('Plazo contractual vigente', $vista);
        $this->assertStringContainsString('Plazo inicial confirmado', $vista);
        $this->assertStringContainsString('Ampliaciones de plazo confirmadas', $vista);
        $this->assertStringContainsString('Total de días de ampliación aprobados', $vista);
        $this->assertStringContainsString('Ampliaciones pendientes de confirmación', $vista);
        $this->assertStringContainsString('no se suman al plazo vigente', $vista);
        $this->assertStringContainsString('No hay ampliaciones de plazo confirmadas', $vista);
        $this->assertStringContainsString('Fecha de finalización', $vista);
    }

    public function testLaVistaMarcaElPlazoProvisorioYLaNeutralizacionAbierta(): void
    {
        $vista = $this->leerApp('Views/obras/ficha.php');

        $this->assertStringContainsString('provisorio', $vista);
        $this->assertStringContainsString('neutralizacionAbierta', $vista);
        $this->assertStringContainsString('neutralización abierta', $vista);
    }

    public function testLaVistaNoUsaEstilosNiJavascriptEmbebidos(): void
    {
        $vista = $this->leerApp('Views/obras/ficha.php');

        $this->assertStringContainsString('assets/css/pages/ficha-obra.css', $vista);
        $this->assertStringContainsString('assets/js/pages/ficha-obra.js', $vista);
        $this->assertStringNotContainsString('<style', $vista);
        $this->assertStringNotContainsString('<script>', $vista);
    }

    public function testConsultaAccedeAlaMismaFichaSinPermisosDeEscritura(): void
    {
        $rutas = $this->leerApp('Config/Routes.php');

        $this->assertStringContainsString(
            "get('/obras/ver/(:num)', 'Obras::ver/\$1'",
            $rutas
        );
        $this->assertStringContainsString(
            'role:SUPERADMINISTRADOR,ADMINISTRADOR,CONSULTA',
            $rutas
        );
    }

    private function leerApp(string $relativa): string
    {
        $ruta      = ROOTPATH . 'app/' . ltrim($relativa, '/\\');
        $contenido = file_get_contents($ruta);

        $this->assertNotFalse($contenido, "No se pudo leer: {$relativa}");

        return $contenido ?: '';
    }
}
