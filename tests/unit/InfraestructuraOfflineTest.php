<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Infraestructura PWA / offline de Fase D.1 (docs/SIGOA.md §53).
 *
 * Pruebas estructurales sobre los archivos de la capa offline: manifest,
 * Service Worker, app shell y componentes JavaScript. No ejecutan
 * navegador: verifican existencia, validez y coherencia de la
 * infraestructura entregada.
 *
 * @internal
 */
final class InfraestructuraOfflineTest extends CIUnitTestCase
{
    private function publicPath(string $relativa): string
    {
        return rtrim((string) FCPATH, '/\\') . DIRECTORY_SEPARATOR . ltrim($relativa, '/\\');
    }

    private function leerPublic(string $relativa): string
    {
        $contenido = file_get_contents($this->publicPath($relativa));

        $this->assertNotFalse($contenido, "No se pudo leer el archivo público: {$relativa}");

        return $contenido ?: '';
    }

    public function testManifestExisteYEsJsonValido(): void
    {
        $manifest = json_decode($this->leerPublic('manifest.json'), true);

        $this->assertIsArray($manifest);
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
    }

    public function testManifestDefineCamposPwa(): void
    {
        $manifest = json_decode($this->leerPublic('manifest.json'), true);

        $this->assertIsArray($manifest);

        $this->assertSame('SIGOA', $manifest['short_name'] ?? null);
        $this->assertNotEmpty($manifest['name'] ?? null);
        $this->assertSame('/login', $manifest['start_url'] ?? null);
        $this->assertSame('/', $manifest['scope'] ?? null);
        $this->assertSame('standalone', $manifest['display'] ?? null);
        $this->assertSame('portrait', $manifest['orientation'] ?? null);
        $this->assertSame('es', $manifest['lang'] ?? null);
        $this->assertSame('#24344C', $manifest['theme_color'] ?? null);
        $this->assertSame('#F5F3F0', $manifest['background_color'] ?? null);
        $this->assertIsArray($manifest['icons'] ?? null);
        $this->assertSame([], $manifest['icons'] ?? null, 'No deben declararse iconos inexistentes (pendiente documentado en §53).');
    }

    public function testServiceWorkerExisteYDeclaraCicloDeVida(): void
    {
        $sw = $this->leerPublic('sw.js');

        $this->assertStringContainsString("'use strict'", $sw);
        $this->assertMatchesRegularExpression('/addEventListener\(\s*[\'"]install[\'"]/', $sw);
        $this->assertMatchesRegularExpression('/addEventListener\(\s*[\'"]activate[\'"]/', $sw);
        $this->assertMatchesRegularExpression('/addEventListener\(\s*[\'"]fetch[\'"]/', $sw);
        $this->assertStringContainsString('sigoa-shell-v3', $sw);
    }

    public function testServiceWorkerNoUsaBackgroundSync(): void
    {
        $sw = $this->leerPublic('sw.js');

        $this->assertStringNotContainsString('registerSync', $sw);
        $this->assertStringNotContainsString('backgroundSync', $sw);
        $this->assertStringNotContainsString('background-sync', $sw);
    }

    public function testServiceWorkerNoCacheaPaginasNiSesion(): void
    {
        $sw = $this->leerPublic('sw.js');

        $this->assertStringNotContainsString('/login', $sw, 'El app shell no debe cachear páginas autenticadas ni la página de login.');
        $this->assertStringContainsString("has('set-cookie')", $sw, 'El Service Worker debe evitar cachear respuestas con cookie de sesión.');
        $this->assertStringContainsString('caches.match(request)', $sw, 'La estrategia debe apoyarse en caches.match (cache-first), no en cachear documentos.');
    }

    public function testRecursosDelAppShellExistenEnDisco(): void
    {
        $sw = $this->leerPublic('sw.js');

        preg_match_all("/'(\/[^']+)'/", $sw, $coincidencias);

        $this->assertGreaterThan(0, count($coincidencias[1]));

        foreach ($coincidencias[1] as $ruta) {
            $this->assertFileExists(
                $this->publicPath($ruta),
                "El app shell referencia un recurso que no existe: {$ruta}"
            );
        }
    }

    public function testComponentesJsOfflineExisten(): void
    {
        $componentes = [
            'assets/js/app.js',
            'assets/js/components/uuid.js',
            'assets/js/components/indexeddb.js',
            'assets/js/components/connectivity.js',
            'assets/js/components/camera-resize.js',
            'assets/js/components/sincronizacion.js',
            'assets/js/pages/inspeccion-nueva.js',
            'assets/js/pages/obra-inspecciones.js',
        ];

        foreach ($componentes as $ruta) {
            $this->assertFileExists($this->publicPath($ruta), "Falta el componente JS: {$ruta}");
        }
    }

    public function testComponenteSincronizacionD3ExponeApiYEstados(): void
    {
        $js = $this->leerPublic('assets/js/components/sincronizacion.js');

        $this->assertStringContainsString('SIGOA.sincronizacion', $js);
        $this->assertStringContainsString('sincronizarInspecciones', $js);
        $this->assertStringContainsString('ESTADO_PENDIENTE_SYNC', $js);
        $this->assertStringContainsString("'PENDIENTE_SYNC'", $js);
        $this->assertStringContainsString('ESTADO_SINCRONIZADA', $js);
        $this->assertStringContainsString("'SINCRONIZADA'", $js);
        $this->assertStringContainsString('ESTADO_ERROR', $js);
        $this->assertStringContainsString("'ERROR'", $js);
        $this->assertStringContainsString('X-CSRF-TOKEN', $js);
    }

    public function testCapaIndexeddbD2AgregaIndicePorObraYBuscadorPorIndice(): void
    {
        $capa = $this->leerPublic('assets/js/components/indexeddb.js');

        $this->assertStringContainsString("VERSION_BASE = 2", $capa, 'La base local debe migrar a v2 en D.2.');
        $this->assertStringContainsString("nombre: 'por_obra'", $capa);
        $this->assertStringContainsString("keyPath: 'obra_id'", $capa);
        $this->assertStringContainsString('buscarPorIndice', $capa);
    }

    public function testComponenteCamaraRespetaLimitesDeProcesamiento(): void
    {
        $js = $this->leerPublic('assets/js/components/camera-resize.js');

        $this->assertStringContainsString('SIGOA.imagenes', $js);
        $this->assertStringContainsString('LIMITE_LADO_FOTO = 2560', $js);
        $this->assertStringContainsString('LIMITE_LADO_THUMBNAIL = 400', $js);
        $this->assertStringContainsString("TIPO_JPEG = 'image/jpeg'", $js);
        $this->assertStringContainsString('optimizar', $js);
    }

    public function testLayoutAutenticadoIntegraManifestYAppJs(): void
    {
        $layout = file_get_contents(APPPATH . 'Views/layouts/auth.php');

        $this->assertNotFalse($layout);
        $this->assertStringContainsString('manifest.json', $layout);
        $this->assertStringContainsString('theme-color', $layout);
        $this->assertStringContainsString('assets/js/app.js', $layout);
        $this->assertStringContainsString('sigaConnectividad', $layout);
    }
}