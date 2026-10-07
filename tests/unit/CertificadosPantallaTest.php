<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Contratos estructurales de la pantalla de certificados.
 *
 * @internal
 */
final class CertificadosPantallaTest extends CIUnitTestCase
{
    public function testLaFichaHabilitaVerCertificadosHaciaLaPantallaDeLaObra(): void
    {
        $ficha = $this->leerApp('Views/obras/ficha.php');

        $this->assertStringContainsString('/obras/certificados/', $ficha);
        $this->assertStringContainsString('Ver Certificados', $ficha);
        $this->assertStringNotContainsString('title="Disponible próximamente"', $ficha);
        $this->assertDoesNotMatchRegularExpression(
            '/ficha-btn-certificados[^>]+\sdisabled/',
            $ficha
        );
    }

    public function testLasRutasDeCertificadosRespetanRolesDeLaFicha(): void
    {
        $rutas = $this->leerApp('Config/Routes.php');

        $this->assertStringContainsString("get('/obras/certificados/(:num)', 'Certificados::index/\$1'", $rutas);
        $this->assertStringContainsString("role:SUPERADMINISTRADOR,ADMINISTRADOR,CONSULTA", $rutas);
        $this->assertStringContainsString("post('/obras/certificados/configurar', 'Certificados::confirmarConfiguracion'", $rutas);
        $this->assertStringContainsString("post('/obras/certificados/crear', 'Certificados::crear'", $rutas);
    }

    public function testLaVistaNoUsaEstilosNiJavascriptEmbebidos(): void
    {
        $vista = $this->leerApp('Views/obras/certificados.php');

        $this->assertStringContainsString("assets/css/pages/obra-certificados.css", $vista);
        $this->assertStringContainsString("assets/js/pages/obra-certificados.js", $vista);
        $this->assertStringNotContainsString('<style', $vista);
        $this->assertStringNotContainsString('<script>', $vista);
        $this->assertStringContainsString('Cargar Certificado', $vista);
        $this->assertStringContainsString('¿Confirmar configuración económica?', $vista);
        $this->assertStringContainsString('btn-success', $vista);
    }

    public function testLaVistaMuestraEncabezadoDeObraConDatosIdentificatorios(): void
    {
        $vista = $this->leerApp('Views/obras/certificados.php');

        $this->assertStringContainsString('ficha-nombre', $vista);
        $this->assertStringContainsString('$obra->nombre', $vista);
        $this->assertStringContainsString('Fecha de inicio', $vista);
        $this->assertStringContainsString('Plazo de obra', $vista);
        $this->assertStringContainsString('N° de expediente', $vista);
        $this->assertStringContainsString('expediente_municipal', $vista);
        $this->assertStringContainsString('ficha-btn-volver', $vista);
        $this->assertStringContainsString('Volver', $vista);
    }

    public function testLosCamposMonetariosYPorcentualesUsanGrupoVisual(): void
    {
        $vista = $this->leerApp('Views/obras/certificados.php');

        $this->assertStringContainsString('cert-input-grupo', $vista);
        $this->assertStringContainsString('$ 100.000.000', $vista);
        $this->assertStringContainsString('$ 10.000.000', $vista);
        $this->assertStringContainsString('20,000 %', $vista);
        $this->assertStringContainsString('5,000 %', $vista);
        $this->assertStringContainsString('cert-importe-contable', $vista);
        $this->assertStringContainsString('cert-importe-simbolo', $vista);
        $this->assertStringContainsString('cert-importe-numero', $vista);
        $this->assertStringContainsString("estadoAnticipoUi", $vista);
        $this->assertStringNotContainsString('YA_LIQUIDADO', $vista);
    }

    public function testElControladorRecalculaValoresAlCrearYNoTomaDescuentosDelCliente(): void
    {
        $controlador = $this->leerApp('Controllers/Certificados.php');

        $this->assertStringContainsString('CertificacionObra::calcularValoresCertificado', $controlador);
        $this->assertStringContainsString('transStart', $controlador);
        $this->assertStringNotContainsString("getPost('descuento_anticipo')", $controlador);
        $this->assertStringNotContainsString("getPost('estado_anticipo')", $controlador);
        $this->assertStringNotContainsString("getPost('retencion_fondo_reparo')", $controlador);
        $this->assertStringNotContainsString("getPost('monto_neto')", $controlador);
        $this->assertStringContainsString('estaBloqueada', $controlador);
        $this->assertStringContainsString('estaConfirmada', $controlador);
        $this->assertStringContainsString('ultimoPorObra', $controlador);
        $this->assertStringContainsString('validarPeriodoPosterior', $controlador);
    }

    public function testNoSeCrearonCamposRedundantesDeAnticipoNiAvance(): void
    {
        $modeloObra = $this->leerApp('Models/ObraModel.php');
        $modeloCert = $this->leerApp('Models/CertificadoModel.php');

        $this->assertStringNotContainsString("'anticipo_total'", $modeloObra);
        $this->assertStringNotContainsString("'saldo_anticipo'", $modeloObra);
        $this->assertStringNotContainsString("'avance'", $modeloObra);
        $this->assertStringNotContainsString("'avance_acumulado'", $modeloCert);
    }

    private function leerApp(string $relativa): string
    {
        $ruta      = APPPATH . ltrim($relativa, '/\\');
        $contenido = file_get_contents($ruta);

        $this->assertNotFalse($contenido, "No se pudo leer: {$relativa}");

        return $contenido ?: '';
    }
}
