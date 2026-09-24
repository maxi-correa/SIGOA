<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Estructura de Fase D.2 — flujo local de nueva inspección.
 *
 * Pruebas estructurales (sin navegador): controlador, ruta, vista del
 * formulario, acción en la vista de obra y componentes JS del flujo local.
 * Verifica coherencia con la arquitectura de SIGOA definida en docs.
 *
 * @internal
 */
final class InspeccionNuevaEstructuraTest extends CIUnitTestCase
{
    private function leerApp(string $relativa): string
    {
        $ruta   = APPPATH . ltrim($relativa, '/\\');
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

    public function testControladorInspectorInspeccionesExisteConNueva(): void
    {
        $this->assertFileExists(APPPATH . 'Controllers/Inspector/Inspecciones.php');

        $contenido = file_get_contents(APPPATH . 'Controllers/Inspector/Inspecciones.php');
        $this->assertNotFalse($contenido);
        $this->assertStringContainsString('class Inspecciones extends BaseController', $contenido);
        $this->assertMatchesRegularExpression('/function\s+nueva\s*\(\s*int\s+\$obraId/', $contenido);
    }

    public function testRutaNuevaInspeccionRegistradaEnGrupoInspector(): void
    {
        $rutas = file_get_contents(APPPATH . 'Config/Routes.php');
        $this->assertNotFalse($rutas);

        $this->assertStringContainsString('inspecciones/nueva/(:num)', $rutas);
        $this->assertStringContainsString('Inspector\Inspecciones::nueva/$1', $rutas);
    }

    public function testVistaNuevaInspeccionAutorizaYExponeDatosLocales(): void
    {
        $vista = $this->leerApp('Views/inspector/inspeccion_nueva.php');

        $this->assertStringContainsString('data-obra-id', $vista);
        $this->assertStringContainsString('data-inspector-id', $vista);
        $this->assertStringContainsString('Guardar inspección', $vista);
        $this->assertStringContainsString('Inspección guardada en este dispositivo.', $vista);
        $this->assertStringContainsString('capture="environment"', $vista);
        $this->assertStringContainsString('assets/js/components/camera-resize.js', $vista);
        $this->assertStringContainsString('assets/js/pages/inspeccion-nueva.js', $vista);
    }

    public function testVistaObraAgregaAccionYSeccionDeInspeccionesLocales(): void
    {
        $vista = $this->leerApp('Views/inspector/obra.php');

        $this->assertStringContainsString('Nueva inspección', $vista);
        $this->assertStringContainsString('inspector/inspecciones/nueva/', $vista);
        $this->assertStringContainsString('inspeccionesLocales', $vista);
        $this->assertStringContainsString('data-obra-id', $vista);
        $this->assertStringContainsString('assets/js/pages/obra-inspecciones.js', $vista);
    }

    public function testPaginaJavascriptDelFlujoUsaSoloElAlmacenamientoLocal(): void
    {
        $js = $this->leerPublic('assets/js/pages/inspeccion-nueva.js');

        $this->assertStringContainsString('PENDIENTE_SYNC', $js);
        $this->assertStringContainsString('estado_local', $js);
        $this->assertStringContainsString('ALMACEN.guardar(', $js);
        $this->assertStringContainsString('ALMACEN.ALMACENES.inspecciones', $js);
        $this->assertStringContainsString('ALMACEN.ALMACENES.fotografias', $js);
        $this->assertStringContainsString('IMAGENES.optimizar(', $js);
        $this->assertStringNotContainsString('fetch(', $js);
    }

    public function testObraInspeccionesJsRecuperaFotografiasPorIndice(): void
    {
        $js = $this->leerPublic('assets/js/pages/obra-inspecciones.js');

        $this->assertStringContainsString('por_inspeccion', $js);
        $this->assertStringContainsString('por_obra', $js);
        $this->assertStringContainsString('buscarPorIndice', $js);
        $this->assertStringNotContainsString('fetch(', $js);
    }
}