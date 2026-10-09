<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Contratos estructurales de la confirmación de los datos iniciales de
 * plazo en la ficha de obra.
 *
 * @internal
 */
final class ObraDatosInicialesPantallaTest extends CIUnitTestCase
{
    public function testLaRutaDeConfirmacionRespetaRoleDeFicha(): void
    {
        $rutas = $this->leerApp('Config/Routes.php');

        $this->assertStringContainsString(
            "post('/obras/ficha/confirmar-plazo', 'Obras::confirmarPlazoInicial'",
            $rutas
        );
        $this->assertStringContainsString("post('/obras/ficha/actualizar', 'Obras::actualizarFicha'", $rutas);
        $this->assertStringContainsString('role:SUPERADMINISTRADOR,ADMINISTRADOR', $rutas);
    }

    public function testLaVistaOfreceConfirmarYBloqueaLaEdicionTrasConfirmar(): void
    {
        $vista = $this->leerApp('Views/obras/ficha.php');

        $this->assertStringContainsString('id="modalConfirmarPlazo"', $vista);
        $this->assertStringContainsString('id="btnConfirmarPlazoInicial"', $vista);
        $this->assertStringContainsString('/obras/ficha/confirmar-plazo', $vista);
        $this->assertStringContainsString('form="formFichaObra"', $vista);
        $this->assertStringContainsString('Confirmar datos iniciales', $vista);

        $this->assertStringContainsString('$plazoInicialConfirmado', $vista);
        $this->assertStringContainsString('no pueden modificarse', $vista);
        $this->assertStringContainsString('ficha-aviso-ok', $vista);
    }

    public function testLaVistaNoUsaEstilosNiJavascriptEmbebidos(): void
    {
        $vista = $this->leerApp('Views/obras/ficha.php');

        $this->assertStringContainsString('assets/css/pages/ficha-obra.css', $vista);
        $this->assertStringContainsString('assets/js/pages/ficha-obra.js', $vista);
        $this->assertStringNotContainsString('<style', $vista);
        $this->assertStringNotContainsString('<script>', $vista);
    }

    public function testElControladorExigeYNoRecibeElPlazoDelClienteAlConfirmar(): void
    {
        $controlador = $this->leerApp('Controllers/Obras.php');

        $this->assertStringContainsString('public function confirmarPlazoInicial()', $controlador);
        $this->assertStringContainsString('confirmarDatosIniciales', $controlador);
        $this->assertStringContainsString('validarDatosFicha($datos, true)', $controlador);
        $this->assertStringContainsString('es obligatoria para confirmar los datos iniciales', $controlador);
        $this->assertStringContainsString('actualizarExpedienteContableFicha', $controlador);
    }

    public function testElModeloGarantizaLaInmutabilidadDelPlazoConfirmado(): void
    {
        $modelo = $this->leerApp('Models/ObraModel.php');

        $this->assertStringContainsString("'plazo_inicial_confirmado'", $modelo);
        $this->assertStringContainsString('CAMPOS_PLAZO_INICIAL', $modelo);
        $this->assertStringContainsString('public function update($id = null, $row = null): bool', $modelo);
        $this->assertStringContainsString('modificaPlazoInicialConfirmado', $modelo);
        $this->assertStringContainsString('actualizarExpedienteContable', $modelo);
    }

    public function testElModalTieneResumenConLosDatosIniciales(): void
    {
        $vista = $this->leerApp('Views/obras/ficha.php');
        $js    = $this->leer('public/assets/js/pages/ficha-obra.js');

        $this->assertStringContainsString('id="resumenFechaInicio"', $vista);
        $this->assertStringContainsString('id="resumenPlazoObra"', $vista);
        $this->assertStringContainsString('antesDeAbrir', $js);
        $this->assertStringContainsString('resumenFechaInicio', $js);
        $this->assertStringContainsString('is-invalid', $js);
    }

    private function leerApp(string $relativa): string
    {
        return $this->leer('app/' . ltrim($relativa, '/\\'));
    }

    private function leer(string $relativa): string
    {
        $ruta      = ROOTPATH . ltrim($relativa, '/\\');
        $contenido = file_get_contents($ruta);

        $this->assertNotFalse($contenido, "No se pudo leer: {$relativa}");

        return $contenido ?: '';
    }
}