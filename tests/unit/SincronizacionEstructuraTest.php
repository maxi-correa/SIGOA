<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Estructura de Fase D.3 — sincronización servidor de inspecciones.
 *
 * Pruebas estructurales (sin navegador): controlador y ruta del endpoint,
 * método de autorización histórica, botón de sincronización en la vista de
 * obra, componente JS y precache del Service Worker. Verifica coherencia con
 * la arquitectura de SIGOA definida en docs.
 *
 * @internal
 */
final class SincronizacionEstructuraTest extends CIUnitTestCase
{
    private function leerApp(string $relativa): string
    {
        $ruta     = APPPATH . ltrim($relativa, '/\\');
        $contenido = file_get_contents($ruta);

        $this->assertNotFalse($contenido, "No se pudo leer: {$relativa}");

        return $contenido ?: '';
    }

    private function leerPublic(string $relativa): string
    {
        $ruta = rtrim((string) FCPATH, '/\\') . DIRECTORY_SEPARATOR . ltrim($relativa, '/\\');
        $contenido = file_get_contents($ruta);

        $this->assertNotFalse($contenido, "No se pudo leer el archivo público: {$relativa}");

        return $contenido ?: '';
    }

    public function testControladorSincronizarExisteConEndpointInspector(): void
    {
        $this->assertFileExists(APPPATH . 'Controllers/Inspector/Sincronizar.php');

        $contenido = file_get_contents(APPPATH . 'Controllers/Inspector/Sincronizar.php');
        $this->assertNotFalse($contenido);
        $this->assertStringContainsString('class Sincronizar extends BaseController', $contenido);
        $this->assertMatchesRegularExpression('/function\s+inspecciones\s*\(\)/', $contenido);
    }

    public function testRutaSincronizacionRegistradaEnGrupoInspector(): void
    {
        $rutas = $this->leerApp('Config/Routes.php');

        $this->assertStringContainsString("sincronizar/inspecciones", $rutas);
        $this->assertStringContainsString('Inspector\Sincronizar::inspecciones', $rutas);
        $this->assertMatchesRegularExpression("/->post\(.*sincronizar\/inspecciones/", $rutas);
    }

    public function testModeloInspectoresObrasOfreceAutorizacionHistorica(): void
    {
        $this->assertFileExists(APPPATH . 'Models/InspectoresObrasModel.php');

        $modelo = file_get_contents(APPPATH . 'Models/InspectoresObrasModel.php');
        $this->assertNotFalse($modelo);

        $this->assertMatchesRegularExpression('/function\s+fueVigente\s*\(/', $modelo);
    }

    public function testModeloOperacionSincronizacionExisteConRegistrar(): void
    {
        $this->assertFileExists(APPPATH . 'Models/OperacionSincronizacionModel.php');

        $modelo = file_get_contents(APPPATH . 'Models/OperacionSincronizacionModel.php');
        $this->assertNotFalse($modelo);
        $this->assertStringContainsString('class OperacionSincronizacionModel extends Model', $modelo);
        $this->assertMatchesRegularExpression('/function\s+registrar\s*\(/', $modelo);
    }

    public function testVistaObraIncluyeBotonYAlimentacionDeSincronizacion(): void
    {
        $vista = $this->leerApp('Views/inspector/obra.php');

        $this->assertStringContainsString('btnSincronizar', $vista);
        $this->assertStringContainsString('bi-arrow-repeat', $vista);
        $this->assertStringContainsString('sincronizacionEstado', $vista);
        $this->assertStringContainsString('assets/js/components/sincronizacion.js', $vista);
    }

    public function testComponenteSincronizacionUnicoOrigenDelFetch(): void
    {
        $componente = $this->leerPublic('assets/js/components/sincronizacion.js');

        $this->assertStringContainsString('sincronizar/inspecciones', $componente);
        $this->assertStringContainsString('sincronizarInspecciones', $componente);
        $this->assertStringContainsString('ESTADO_PENDIENTE_SYNC', $componente);
        $this->assertStringContainsString("'PENDIENTE_SYNC'", $componente);
        $this->assertStringContainsString('ESTADO_SINCRONIZADA', $componente);
        $this->assertStringContainsString("'SINCRONIZADA'", $componente);
        $this->assertStringContainsString("'X-CSRF-TOKEN':", $componente);

        $pagina = $this->leerPublic('assets/js/pages/obra-inspecciones.js');
        $this->assertStringContainsString('window.SIGOA.sincronizacion', $pagina);
        $this->assertStringContainsString('sincronizarInspecciones(obraId)', $pagina);

        // La estrategia "offline primero" se conserva: la página no dispara fetch().
        $this->assertStringNotContainsString('fetch(', $pagina);
    }

    public function testServiceWorkerPrecacheaComponenteD3(): void
    {
        $sw = $this->leerPublic('sw.js');

        $this->assertStringContainsString("'sigoa-shell-v3'", $sw);
        $this->assertStringContainsString("'/assets/js/components/sincronizacion.js'", $sw);
    }

    public function testVistaObraPrecargaComponenteAntesQueLaPagina(): void
    {
        $vista = $this->leerApp('Views/inspector/obra.php');

        $posComponente = strpos($vista, "base_url('assets/js/components/sincronizacion.js')");
        $posPagina     = strpos($vista, "base_url('assets/js/pages/obra-inspecciones.js')");

        $this->assertNotFalse($posComponente);
        $this->assertNotFalse($posPagina);
        $this->assertLessThan($posPagina, $posComponente);
    }
}