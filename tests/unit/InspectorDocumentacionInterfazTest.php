<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Contratos estructurales de la pantalla «Documentación disponible» del
 * inspector.
 *
 * Verifica que la pantalla:
 *
 * * usa el layout autenticado y carga su CSS y su JavaScript desde archivos
 *   externos (RNF §37: nada de estilos ni scripts embebidos);
 * * entrega los datos iniciales como JSON dentro de `#didDatos`, escapados
 *   para que no puedan inyectar marcado;
 * * comparte estructura con el shell sin conexión, de modo que ambos modos
 *   pintan el mismo render;
 * * no expone en el cliente nada que el servidor no haya autorizado
 *   (la ruta física del archivo nunca viaja al navegador);
 * * monta el listado con el DOM en lugar de `innerHTML`, para que un
 *   nombre de documento no pueda inyectar marcado.
 *
 * @internal
 */
final class InspectorDocumentacionInterfazTest extends CIUnitTestCase
{
    public function testLaVistaUsaElLayoutAutenticadoYNo_embebeEstilosNiScripts(): void
    {
        $vista = $this->leerApp('Views/inspector/documentacion.php');

        $this->assertStringContainsString("extend('layouts/auth')", $vista);
        $this->assertStringContainsString("assets/css/pages/documentacion-inspector.css", $vista);
        $this->assertStringContainsString("assets/js/pages/documentacion-inspector.js", $vista);

        $this->assertStringNotContainsString('<style', $vista, 'La vista no debe incluir estilos embebidos.');

        /* El único script sin `src` es el que transporta los datos en
           JSON; no hay JavaScript embebido. */
        preg_match_all('/<script([^>]*)>/i', $vista, $scripts);

        $this->assertGreaterThan(0, count($scripts[1]));

        foreach ($scripts[1] as $atributos) {
            $this->assertTrue(
                stripos($atributos, 'src=') !== false || stripos($atributos, 'type="application/json"') !== false,
                'Todo script de la vista debe ser externo o transportar únicamente datos JSON: ' . $atributos
            );
        }
    }

    public function testLaVistaEntregaLosDatosInicialesComoJsonEscapado(): void
    {
        $vista = $this->leerApp('Views/inspector/documentacion.php');

        $this->assertStringContainsString('id="didDatos"', $vista);
        $this->assertStringContainsString('type="application/json"', $vista);
        $this->assertStringContainsString('JSON_HEX_TAG', $vista, 'El JSON debe ir escapado para que no inyecte marcado.');
        $this->assertStringContainsString('JSON_HEX_AMP', $vista);
        $this->assertStringContainsString('JSON_HEX_APOS', $vista);
        $this->assertStringContainsString('JSON_HEX_QUOT', $vista);
    }

    public function testLaVistaExponeLosElementosQuePintaElCliente(): void
    {
        $vista = $this->leerApp('Views/inspector/documentacion.php');

        foreach ([
            'documentacionDisponible',
            'didResumen',
            'didConexion',
            'didConexionTexto',
            'didRefrescar',
            'didFiltros',
            'didAviso',
            'didAvisoTexto',
            'didLista',
            'didVacio',
            'didVacioTitulo',
            'didVacioTexto',
        ] as $id) {
            $this->assertStringContainsString('id="' . $id . '"', $vista, "Falta el elemento #{$id}.");
        }

        /* Los tres filtros del RNF. */
        $this->assertStringContainsString('data-filtro="todos"', $vista);
        $this->assertStringContainsString('data-filtro="offline"', $vista);
        $this->assertStringContainsString('data-filtro="pendientes"', $vista);
    }

    public function testLaVistaNoExponeLaRutaFisicaDelArchivo(): void
    {
        $vista = $this->leerApp('Views/inspector/documentacion.php');

        $this->assertStringNotContainsString('ruta_relativa', $vista);
        $this->assertStringNotContainsString('ruta_completa', $vista);
        $this->assertStringNotContainsString('nombre_archivo"', $vista, 'Solo se expone el nombre original del documento.');
    }

    public function testElShellSinConexionComparteLaEstructuraYNoLlevaDatosDeSesion(): void
    {
        $shell = $this->leerPublic('assets/offline/documentacion.html');

        foreach ([
            'documentacionDisponible',
            'didResumen',
            'didConexion',
            'didConexionTexto',
            'didRefrescar',
            'didFiltros',
            'didAviso',
            'didAvisoTexto',
            'didLista',
            'didVacio',
            'didVacioTitulo',
            'didVacioTexto',
        ] as $id) {
            $this->assertStringContainsString('id="' . $id . '"', $shell, "El shell no comparte el elemento #{$id}.");
        }

        $this->assertStringContainsString('assets/js/pages/documentacion-inspector.js', $shell);
        $this->assertStringContainsString('assets/js/components/indexeddb.js', $shell);

        /* El shell es estático: no puede contener datos de sesión ni de la
           documentación (no hay PHP ni referencias a la sesión). */
        $this->assertStringNotContainsString('<?php', $shell);
        $this->assertStringNotContainsString('session', $shell);
        $this->assertStringNotContainsString('csrf', strtolower($shell));
    }

    public function testElClienteNoConstruyeMarcadoConInnerHtml(): void
    {
        $js = $this->leerPublic('assets/js/pages/documentacion-inspector.js');

        /* El listado se monta con el DOM: ningún dato (nombre de archivo,
           tipo de documento) llega al HTML del cliente. */
        $this->assertStringNotContainsString('innerHTML =', $js, 'El listado debe montarse con el DOM, no con innerHTML.');
        $this->assertStringNotContainsString('outerHTML =', $js);
        $this->assertStringNotContainsString('insertAdjacentHTML', $js);
        $this->assertStringNotContainsString('document.write', $js);

        /* Los datos se leen como texto plano del bloque JSON. */
        $this->assertStringContainsString('nodo.textContent', $js);
    }

    public function testElClienteVerificaElPdfYGuardaLaCopiaDeFormaSegura(): void
    {
        $js = $this->leerPublic('assets/js/pages/documentacion-inspector.js');

        $this->assertStringContainsString('%PDF-', $js, 'La descarga debe comprobar la firma del PDF.');
        $this->assertStringContainsString('reemplazar', $js, 'La copia anterior se sustituye de forma atómica.');
        $this->assertStringContainsString('::tmp', $js, 'La versión nueva se escribe primero en una clave temporal.');
        $this->assertStringContainsString('temporal', $js);
    }

    public function testLasRutasDelInspectorExigenAutenticacionYRol(): void
    {
        $rutas = $this->leerApp('Config/Routes.php');

        $this->assertStringContainsString("documentacion/api/(:num)", $rutas);
        $this->assertStringContainsString("documentacion/ver/(:num)", $rutas);
        $this->assertStringContainsString("documentacion/(:num)", $rutas);

        $this->assertMatchesRegularExpression(
            '/group\(\s*\'inspector\'[\s\S]*?role:INSPECTOR/',
            $rutas,
            'Las rutas de documentación deben quedar dentro del grupo del inspector con su filtro de rol.'
        );
    }

    public function testElControladorExponeLasTresOperaciones(): void
    {
        $controlador = $this->leerApp('Controllers/Inspector/Documentacion.php');

        $this->assertStringContainsString('public function index(', $controlador);
        $this->assertStringContainsString('public function api(', $controlador);
        $this->assertStringContainsString('public function ver(', $controlador);

        /* La autorización se resuelve con la asignación vigente. */
        $this->assertStringContainsString('esVigente', $controlador);

        /* El listado del inspector solo incluye la versión vigente. */
        $this->assertStringContainsString('listarVigentesPorObra', $controlador);
    }

    public function testElServicioDeArchivosConcentraLaEntregaDePdf(): void
    {
        $servicio = $this->leerApp('Services/DocumentoArchivo.php');

        $this->assertStringContainsString('public function servirPdf(', $servicio);
        $this->assertStringContainsString('public function rutaNormalizada(', $servicio);
        $this->assertStringContainsString('public function rutaAutorizada(', $servicio);

        /* La entrega no puede filtrar la ruta física al cliente. */
        $this->assertStringNotContainsString('ruta_completa', $servicio);
    }

    public function testElEstiloDeLaPaginaUsaLosTokensDelSistema(): void
    {
        $css = $this->leerPublic('assets/css/pages/documentacion-inspector.css');

        $this->assertStringContainsString('var(--color-surface)', $css);
        $this->assertStringContainsString('var(--color-border)', $css);
        $this->assertStringContainsString('var(--color-text)', $css);

        /* Sin colores literales: los colores son decisiones del sistema. */
        $this->assertDoesNotMatchRegularExpression('/#[0-9A-Fa-f]{3,8}\b/', $css, 'La página no debe introducir colores literales.');
    }

    public function testElPerfilLocalSeRegistraYSeBorraAlCerrarSesion(): void
    {
        $app     = $this->leerPublic('assets/js/app.js');
        $sidebar = $this->leerPublic('assets/js/components/sidebar.js');
        $layout  = $this->leerApp('Views/layouts/auth.php');

        $this->assertStringContainsString('registrarPerfilLocal', $app);
        $this->assertStringContainsString('ALMACENES.perfiles', $app);

        $this->assertStringContainsString('sidebar-link-logout', $sidebar);
        $this->assertStringContainsString('ALMACENES.perfiles', $sidebar);

        /* El layout publica la identidad de la sesión como metadatos. */
        $this->assertStringContainsString('meta name="sigOA-usuario-id"', $layout);
        $this->assertStringContainsString('meta name="sigOA-usuario-nombre"', $layout);
    }

    public function testLaFichaDeObraOfreceElAccesoALaDocumentacion(): void
    {
        $obra = $this->leerApp('Views/inspector/obra.php');

        $this->assertStringContainsString('/inspector/documentacion/', $obra);
        $this->assertStringContainsString('Documentación disponible', $obra);
    }

    private function leerApp(string $relativa): string
    {
        return $this->leer('app/' . ltrim($relativa, '/\\'));
    }

    private function leerPublic(string $relativa): string
    {
        return $this->leer('public/' . ltrim($relativa, '/\\'));
    }

    private function leer(string $relativa): string
    {
        $ruta      = ROOTPATH . ltrim($relativa, '/\\');
        $contenido = file_get_contents($ruta);

        $this->assertNotFalse($contenido, "No se pudo leer: {$relativa}");

        return $contenido ?: '';
    }
}
