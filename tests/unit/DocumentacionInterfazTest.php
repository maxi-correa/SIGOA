<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Contratos estructurales de la pantalla de Documentación de obra.
 *
 * Verifica que los modales usan el componente propio de SIGOA
 * (`.modal-overlay[hidden]`) y no Bootstrap JS —que el proyecto no carga—,
 * que las URLs se pasan a los botones por atributos `data-*`, que el
 * selector de tipo no depende del texto de la opción, y que no hay estilos
 * ni JavaScript embebidos en la vista.
 *
 * @internal
 */
final class DocumentacionInterfazTest extends CIUnitTestCase
{
    public function testLaVistaNoUsaBootstrapJsNiMarkupDeBootstrap(): void
    {
        $vista = $this->leerApp('Views/obras/documentacion.php');

        $this->assertStringNotContainsString('data-bs-', $vista);
        $this->assertStringNotContainsString('btn-close', $vista);
        $this->assertStringNotContainsString('modal fade', $vista);
        $this->assertStringNotContainsString('modal-dialog', $vista);
    }

    public function testElJavascriptNoDependeDeBootstrap(): void
    {
        $js = $this->leer('public/assets/js/documentacion.js');

        $this->assertStringNotContainsString('bootstrap', $js);
        $this->assertStringNotContainsString('new bootstrap.Modal', $js);
    }

    public function testLosModalesUsanElComponentePropioConHidden(): void
    {
        $vista = $this->leerApp('Views/obras/documentacion.php');

        $this->assertStringContainsString('class="modal-overlay" id="modalUbicacion" hidden', $vista);
        $this->assertStringContainsString('class="modal-overlay" id="modalReemplazar" hidden', $vista);
        $this->assertStringContainsString('role="dialog"', $vista);
        $this->assertStringContainsString('aria-modal="true"', $vista);
        $this->assertStringContainsString('aria-labelledby="modalUbicacionTitulo"', $vista);
        $this->assertStringContainsString('aria-labelledby="modalReemplazarTitulo"', $vista);
    }

    public function testElModalDeUbicacionUsaDataUrlYBotonesDeCopiadoIndependientes(): void
    {
        $vista = $this->leerApp('Views/obras/documentacion.php');

        $this->assertStringContainsString('data-accion="ubicacion"', $vista);
        $this->assertStringContainsString('data-url="', $vista);
        $this->assertStringContainsString('/obras/documentacion/ubicacion/', $vista);
        $this->assertStringContainsString('id="btnCopiarRuta"', $vista);
        $this->assertStringContainsString('id="btnCopiarDir"', $vista);
        $this->assertStringContainsString('id="avisoCopiado"', $vista);
        $this->assertStringContainsString('role="status"', $vista);
        $this->assertStringContainsString('aria-live="polite"', $vista);
    }

    public function testElSelectorDeTipoNoDependeDelTextoDeLaOpcion(): void
    {
        $vista = $this->leerApp('Views/obras/documentacion.php');
        $js    = $this->leer('public/assets/js/documentacion.js');

        $this->assertStringContainsString('data-tipo="', $vista);
        $this->assertStringNotContainsString('textContent.includes', $js);
        $this->assertStringNotContainsString("texto.includes('ACTA DE NEUTRALIZACIÓN')", $js);
        $this->assertStringContainsString("getAttribute('data-tipo')", $js);
    }

    public function testLaVistaOfreceReemplazarYSuFormulario(): void
    {
        $vista = $this->leerApp('Views/obras/documentacion.php');

        $this->assertStringContainsString('id="modalReemplazar"', $vista);
        $this->assertStringContainsString('data-accion="reemplazar"', $vista);
        $this->assertStringContainsString('/obras/documentacion/reemplazar', $vista);
        $this->assertStringContainsString('name="documento_id"', $vista);
        $this->assertStringContainsString('Reemplazar documento', $vista);
    }

    public function testLaRutaDeReemplazoEsPostYSoloAdministradores(): void
    {
        $rutas = $this->leerApp('Config/Routes.php');

        $this->assertStringContainsString(
            "post('/obras/documentacion/reemplazar', 'Documentacion::reemplazar'",
            $rutas
        );
        $this->assertStringContainsString('Documentacion::reemplazar', $rutas);
    }

    public function testLaVistaNoUsaEstilosNiVariablesInexistentes(): void
    {
        $vista = $this->leerApp('Views/obras/documentacion.php');

        $this->assertStringNotContainsString('style="', $vista);
        $this->assertStringNotContainsString('<style', $vista);
    }

    public function testElFormularioDeCargaSeOfreceEnUnModalConBotonVerde(): void
    {
        $vista = $this->leerApp('Views/obras/documentacion.php');

        /* El formulario permanente ya no existe como sección propia. */
        $this->assertStringNotContainsString('id="seccionAgregarDocumento"', $vista);

        /* El botón verde de alta reutiliza el patrón de «Agregar obra». */
        $this->assertStringContainsString('id="btnAgregarDocumento"', $vista);
        $this->assertStringContainsString('class="btn btn-success documentacion-btn-agregar"', $vista);
        $this->assertStringContainsString('aria-controls="modalAgregarDocumento"', $vista);

        /* El formulario de carga vive dentro del modal propio. */
        $this->assertStringContainsString('id="modalAgregarDocumento"', $vista);
        $this->assertStringContainsString('aria-labelledby="modalAgregarTitulo"', $vista);
        $this->assertStringContainsString('id="formAgregarDocumento"', $vista);
        $this->assertStringContainsString('/obras/documentacion/subir', $vista);
    }

    public function testElModalDeCargaConservaCamposYElMismoEndpointDeSubida(): void
    {
        $vista = $this->leerApp('Views/obras/documentacion.php');

        $this->assertStringContainsString('name="tipo_documento_id"', $vista);
        $this->assertStringContainsString('name="archivo"', $vista);
        $this->assertStringContainsString('name="observaciones"', $vista);
        $this->assertStringContainsString('id="btnCancelarAgregar"', $vista);
        $this->assertStringContainsString('Cargar documento', $vista);
        $this->assertStringContainsString('data-reabrir="<?= esc($reabrirAgregar) ?>"', $vista);
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
