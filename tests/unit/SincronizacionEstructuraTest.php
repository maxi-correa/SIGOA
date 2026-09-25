<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Estructura de Fases D.3 y D.4 — sincronización con el servidor.
 *
 * Pruebas estructurales (sin navegador): controlador, rutas de ambos
 * endpoints, método de autorización histórica, servicios de almacenamiento,
 * botón de sincronización en la vista de obra, carga global del componente
 * JS y precache del Service Worker. Verifica coherencia con la arquitectura
 * de SIGOA definida en docs.
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

    public function testVistaObraIncluyeBotonYEstadoDeSincronizacion(): void
    {
        $vista = $this->leerApp('Views/inspector/obra.php');

        $this->assertStringContainsString('btnSincronizar', $vista);
        $this->assertStringContainsString('bi-arrow-repeat', $vista);
        $this->assertStringContainsString('sincronizacionEstado', $vista);
        $this->assertStringContainsString('assets/js/pages/obra-inspecciones.js', $vista);
    }

    /**
     * Desde D.4 el componente es global: la cola debe poder reanudarse al
     * abrir cualquier vista autenticada, no solo la de una obra.
     */
    public function testComponenteSincronizacionSeCargaEnElLayoutAutenticado(): void
    {
        $layout = $this->leerApp('Views/layouts/auth.php');

        $this->assertStringContainsString('assets/js/components/sincronizacion.js', $layout);

        $posComponente = strpos($layout, "base_url('assets/js/components/sincronizacion.js')");
        $posApp        = strpos($layout, "base_url('assets/js/app.js')");

        $this->assertNotFalse($posComponente);
        $this->assertNotFalse($posApp);
        $this->assertLessThan($posApp, $posComponente, 'El componente debe cargarse antes de app.js para que iniciar() lo encuentre.');

        $posConectividad = strpos($layout, "base_url('assets/js/components/connectivity.js')");
        $this->assertLessThan($posComponente, $posConectividad, 'La conectividad debe inicializarse antes que la sincronización.');

        $obra = $this->leerApp('Views/inspector/obra.php');
        $this->assertStringNotContainsString('assets/js/components/sincronizacion.js', $obra);
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
        $this->assertStringContainsString('sincronizarTodo({ obraId: obraId })', $pagina);

        // La estrategia "offline primero" se conserva: la página no dispara fetch().
        $this->assertStringNotContainsString('fetch(', $pagina);

        $nueva = $this->leerPublic('assets/js/pages/inspeccion-nueva.js');
        $this->assertStringNotContainsString('fetch(', $nueva);
    }

    /* ==================================================================
       Fase D.4 — fotografías
       ================================================================== */

    public function testEndpointFotografiasRegistradoEnGrupoInspector(): void
    {
        $rutas = $this->leerApp('Config/Routes.php');

        $this->assertStringContainsString("sincronizar/fotografias", $rutas);
        $this->assertStringContainsString('Inspector\Sincronizar::fotografias', $rutas);
        $this->assertMatchesRegularExpression("/->post\(.*sincronizar\/fotografias/", $rutas);
    }

    public function testControladorExponeAltaDeFotografias(): void
    {
        $contenido = $this->leerApp('Controllers/Inspector/Sincronizar.php');

        $this->assertMatchesRegularExpression('/function\s+fotografias\s*\(\)/', $contenido);

        /* La identidad del inspector sigue viniendo de la sesión. */
        $this->assertStringContainsString("session()->get('user_id')", $contenido);

        /* Autorización heredada de la inspección, no del payload. */
        $this->assertStringContainsString('fueVigente(', $contenido);
        $this->assertStringContainsString('findByUuid(', $contenido);

        /* Idempotencia por uuid de fotografía. */
        $this->assertStringContainsString('ALREADY_SYNCED', $contenido);
    }

    public function testServicioDeArchivosDeFotografiaExiste(): void
    {
        $ruta = APPPATH . 'Services/FotografiaArchivo.php';

        $this->assertFileExists($ruta);

        $servicio = file_get_contents($ruta);
        $this->assertNotFalse($servicio);

        $this->assertStringContainsString('final class FotografiaArchivo', $servicio);
        $this->assertMatchesRegularExpression('/function\s+inspeccionar\s*\(/', $servicio);
        $this->assertMatchesRegularExpression('/function\s+escribirImagen\s*\(/', $servicio);
        $this->assertMatchesRegularExpression('/function\s+escribirThumbnail\s*\(/', $servicio);
        $this->assertMatchesRegularExpression('/function\s+eliminarRelativo\s*\(/', $servicio);
        $this->assertMatchesRegularExpression('/function\s+repararArchivo\s*\(/', $servicio);

        /* El MIME se detecta por contenido, no por lo declarado. */
        $this->assertStringContainsString('finfo', $servicio);
    }

    public function testAlmacenamientoDefineEstructuraAnidadaDeInspeccion(): void
    {
        $contenido = $this->leerApp('Services/ObraAlmacenamiento.php');

        $this->assertMatchesRegularExpression('/function\s+asegurarEstructuraInspeccion\s*\(/', $contenido);
        $this->assertMatchesRegularExpression('/function\s+rutaRelativaInspeccion\s*\(/', $contenido);
        $this->assertMatchesRegularExpression('/function\s+nombreFotografia\s*\(/', $contenido);
        $this->assertMatchesRegularExpression('/function\s+nombreThumbnailFotografia\s*\(/', $contenido);
        $this->assertMatchesRegularExpression('/function\s+absolutoDesdeRelativa\s*\(/', $contenido);

        /* Se conserva la estructura base usada por Inspector\Obras::ver(). */
        $this->assertMatchesRegularExpression('/function\s+asegurarEstructuraObra\s*\(/', $contenido);
    }

    public function testComponenteSincronizacionExponeColaYBackoff(): void
    {
        $componente = $this->leerPublic('assets/js/components/sincronizacion.js');

        /* Endpoint de fotografías y envío multipart. */
        $this->assertStringContainsString('sincronizar/fotografias', $componente);
        $this->assertStringContainsString('FormData', $componente);

        /* Estados de operación de la cola. */
        $this->assertStringContainsString("'PENDIENTE'", $componente);
        $this->assertStringContainsString("'SINCRONIZANDO'", $componente);
        $this->assertStringContainsString("'ERROR'", $componente);

        /* Backoff progresivo. */
        $this->assertStringContainsString('RETRASOS_MS', $componente);
        $this->assertStringContainsString('5000', $componente);
        $this->assertStringContainsString('15000', $componente);
        $this->assertStringContainsString('30000', $componente);
        $this->assertStringContainsString('60000', $componente);
        $this->assertStringContainsString('300000', $componente);

        /* Reintento manual. */
        $this->assertStringContainsString('reintentar', $componente);

        /* Orden inspecciones → fotografías. */
        $this->assertStringContainsString('TIPO_INSPECCION', $componente);
        $this->assertStringContainsString('TIPO_FOTOGRAFIA', $componente);
    }

    public function testColaSeEncolaAlGuardarInspeccionYFotografia(): void
    {
        $js = $this->leerPublic('assets/js/pages/inspeccion-nueva.js');

        $this->assertStringContainsString('encolarOperacion(', $js);
        $this->assertStringContainsString('SINCRONIZACION.encolar(', $js);
        $this->assertStringContainsString('SINCRONIZACION.TIPO_INSPECCION', $js);
        $this->assertStringContainsString('SINCRONIZACION.TIPO_FOTOGRAFIA', $js);

        /* La cola es responsabilidad exclusiva del componente de
           sincronización; la página no escribe en el store directamente. */
        $this->assertStringNotContainsString('ALMACEN.ALMACENES.operaciones', $js);

        $componente = $this->leerPublic('assets/js/components/sincronizacion.js');
        $this->assertStringContainsString('ALMACEN.ALMACENES.operaciones', $componente);
    }

    public function testAppIniciaLaSincronizacionAlAbrir(): void
    {
        $app = $this->leerPublic('assets/js/app.js');

        $this->assertStringContainsString('window.SIGOA.sincronizacion', $app);
        $this->assertStringContainsString('sincronizacion.iniciar()', $app);
    }

    public function testServiceWorkerPrecacheaComponenteD3(): void
    {
        $sw = $this->leerPublic('sw.js');

        $this->assertStringContainsString("'sigoa-shell-v3'", $sw);
        $this->assertStringContainsString("'/assets/js/components/sincronizacion.js'", $sw);
    }
}
