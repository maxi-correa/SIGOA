<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Contratos estructurales del desglose del monto contractual vigente en
 * la pantalla Ver Obra y de su recálculo al confirmar resoluciones.
 *
 * @internal
 */
final class ObraMontoVigentePantallaTest extends CIUnitTestCase
{
    public function testElControladorCalculaElMontoVigenteDesdeLaObraYLasResoluciones(): void
    {
        $controlador = $this->leerApp('Controllers/Obras.php');

        $this->assertStringContainsString('MontoObra::calcularVigente', $controlador);
        $this->assertStringContainsString('listarPorObra', $controlador);
        $this->assertStringContainsString("'monto_vigente'", $controlador);
        $this->assertStringContainsString("'monto_ajustes_porcentajes'", $controlador);
    }

    public function testLaReglaDeAjusteViveEnLaBibliotecaDeResoluciones(): void
    {
        $biblioteca = $this->leerApp('Libraries/ResolucionObra.php');

        $this->assertStringContainsString('TIPOS_AJUSTE_MONTO', $biblioteca);
        $this->assertStringContainsString('function ajustaMonto', $biblioteca);
    }

    public function testElModeloRecalculaYPersisteElMontoVigente(): void
    {
        $modelo = $this->leerApp('Models/ObraModel.php');

        $this->assertStringContainsString('function recalcularMontoContractualVigente', $modelo);
        $this->assertStringContainsString('function fijarMontoContractualVigente', $modelo);
        $this->assertStringContainsString('MontoObra::calcularVigente', $modelo);
    }

    public function testLaConfirmacionDeResolucionesRecalculaEnTransaccion(): void
    {
        $controlador = $this->leerApp('Controllers/Resoluciones.php');

        $this->assertStringContainsString('transStart', $controlador);
        $this->assertStringContainsString('ajustaMonto', $controlador);
        $this->assertStringContainsString('recalcularMontoContractualVigente', $controlador);
    }

    public function testLaConfiguracionEconomicaRecalculaElMontoVigente(): void
    {
        $controlador = $this->leerApp('Controllers/Certificados.php');

        $this->assertStringContainsString('recalcularMontoContractualVigente', $controlador);
    }

    public function testLaVistaMuestraElDesgloseDelMontoVigente(): void
    {
        $vista = $this->leerApp('Views/obras/ficha.php');

        $this->assertStringContainsString('Cálculo del monto contractual vigente', $vista);
        $this->assertStringContainsString('Monto de contrato original', $vista);
        $this->assertStringContainsString('Ajustes económicos confirmados', $vista);
        $this->assertStringContainsString('Total de ajustes económicos aprobados', $vista);
        $this->assertStringContainsString('Ajustes económicos pendientes de confirmación', $vista);
        $this->assertStringContainsString('no se suman al monto vigente', $vista);
        $this->assertStringContainsString('No hay ajustes económicos confirmados', $vista);
        $this->assertStringContainsString('monto-ajustes-tabla', $vista);
        $this->assertStringContainsString('Monto contractual vigente', $vista);
        $this->assertStringContainsString('montoAjustesPorcentajes', $vista);
        $this->assertStringContainsString('monto-col-porcentaje', $vista);
        $this->assertStringContainsString('Porcentaje', $vista);
    }

    public function testLaVistaNoUsaEstilosNiJavascriptEmbebidos(): void
    {
        $vista = $this->leerApp('Views/obras/ficha.php');

        $this->assertStringContainsString('assets/css/pages/ficha-obra.css', $vista);
        $this->assertStringNotContainsString('<style', $vista);
        $this->assertStringNotContainsString('<script>', $vista);
    }

    private function leerApp(string $relativa): string
    {
        $ruta      = ROOTPATH . 'app/' . ltrim($relativa, '/\\');
        $contenido = file_get_contents($ruta);

        $this->assertNotFalse($contenido, "No se pudo leer: {$relativa}");

        return $contenido ?: '';
    }
}
