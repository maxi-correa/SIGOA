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
        $this->assertStringContainsString("post('/obras/ficha/actualizar-expediente', 'Obras::actualizarExpedienteContable'", $rutas);
        $this->assertStringContainsString('role:SUPERADMINISTRADOR,ADMINISTRADOR', $rutas);
    }

    public function testLaVistaOfreceConfirmarYBloqueaLaEdicionTrasConfirmar(): void
    {
        $vista = $this->leerApp('Views/obras/ficha.php');

        $this->assertStringContainsString('id="modalConfirmarPlazo"', $vista);
        $this->assertStringContainsString('id="btnConfirmarPlazoInicial"', $vista);
        $this->assertStringContainsString('/obras/ficha/confirmar-plazo', $vista);
        $this->assertStringContainsString('form="formDatosObra"', $vista);
        $this->assertStringContainsString('Confirmar datos iniciales', $vista);

        $this->assertStringContainsString('$plazoInicialConfirmado', $vista);
        $this->assertStringContainsString('no pueden modificarse', $vista);
        $this->assertStringContainsString('ficha-aviso-ok', $vista);
    }

    public function testLaVistaSeparaElGuardadoEnDosBloquesIndependientes(): void
    {
        $vista = $this->leerApp('Views/obras/ficha.php');

        $this->assertStringContainsString('id="formExpedienteContable"', $vista);
        $this->assertStringContainsString('action="' . "<?= site_url('/obras/ficha/actualizar-expediente') ?>" . '"', $vista);
        $this->assertStringContainsString('id="formDatosObra"', $vista);
        $this->assertStringContainsString("<?= site_url('/obras/ficha/actualizar') ?>", $vista);

        $this->assertStringContainsString(
            "\$puedeEditar && ! \$plazoInicialConfirmado",
            $vista,
            'El bloque de datos de obra no debe ofrecer guardado cuando el plazo ya está confirmado.'
        );
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
        $this->assertStringContainsString('public function actualizarExpedienteContable()', $controlador);
        $this->assertStringContainsString("\$datos['expediente_contable'] = \$obra->expediente_contable;", $controlador);
    }

    public function testElGuardadoDeLosDatosDeObraNoEscribeElExpedienteContable(): void
    {
        $controlador = $this->leerApp('Controllers/Obras.php');
        $modelo      = $this->leerApp('Models/ObraModel.php');

        $this->assertStringContainsString('public function actualizarFicha()', $controlador);
        $this->assertStringContainsString('Los datos iniciales de la obra ya fueron confirmados', $controlador);

        $inicioActualizarFicha = strpos($modelo, 'public function actualizarFicha(');
        $finActualizarFicha    = strpos($modelo, 'public function actualizarExpedienteContable(');

        $this->assertNotFalse($inicioActualizarFicha);
        $this->assertNotFalse($finActualizarFicha);

        $cuerpo = substr($modelo, $inicioActualizarFicha, $finActualizarFicha - $inicioActualizarFicha);

        $this->assertStringNotContainsString(
            'expediente_contable',
            $cuerpo,
            'actualizarFicha no debe escribir el Expte. contable: tiene su propio guardado.'
        );
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