<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Separación entre sincronización y consulta de inspecciones (Fase E.3).
 *
 * La vista de obra del inspector tenía dos responsabilidades mezcladas en la
 * misma sección: la cola de sincronización de este dispositivo y el acceso a
 * las inspecciones de la obra. E.3 las separa:
 *
 * * la cola conserva su lógica intacta (endpoint, reintentos, CSRF) y solo
 *   informa de lo pendiente de enviar;
 * * "Inspecciones" es una acción independiente, punto de entrada de la futura
 *   consulta histórica, que en esta fase solo informa el estado vacío;
 * * "Nueva inspección" sigue siendo exclusivamente un alta.
 *
 * @internal
 */
final class InspectorInspeccionesConsultaTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private function leerApp(string $relativa): string
    {
        $ruta = APPPATH . ltrim($relativa, '/\\');

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

    /**
     * Porción de la vista de obra delimitada por dos marcadores, para poder
     * afirmar sobre un bloque concreto y no sobre la página entera.
     */
    private function bloque(string $contenido, string $inicio, string $fin): string
    {
        $desde = strpos($contenido, $inicio);

        $this->assertIsInt($desde, "No se encontró el bloque que comienza por «{$inicio}»");

        $hasta = strpos($contenido, $fin, (int) $desde);

        $this->assertIsInt($hasta, "No se encontró el fin del bloque «{$inicio}»");

        return substr($contenido, (int) $desde, (int) $hasta - (int) $desde);
    }

    /**
     * Cuerpo de un método PHP, desde su firma hasta la llave que lo cierra.
     *
     * Contar llaves evita depender de que el método sea el último de la
     * clase o de buscar una cadena que pueda aparecer dentro del cuerpo.
     */
    private function metodo(string $contenido, string $firma): string
    {
        $desde = strpos($contenido, $firma);

        $this->assertIsInt($desde, "No se encontró el método «{$firma}»");

        $nivel = 0;
        $inicio = strpos($contenido, '{', (int) $desde);

        $this->assertIsInt($inicio);

        for ($i = (int) $inicio; $i < strlen($contenido); $i++) {
            if ($contenido[$i] === '{') {
                $nivel++;
            }

            if ($contenido[$i] === '}') {
                $nivel--;

                if ($nivel === 0) {
                    return substr($contenido, (int) $desde, $i - (int) $desde + 1);
                }
            }
        }

        $this->fail("El método «{$firma}» no cierra sus llaves.");

        return '';
    }

    /**
     * Texto de una View con los saltos de línea colapsados: permite
     * afirmar sobre una frase redactada en varias líneas.
     */
    private function textoPlano(string $contenido): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $contenido));
    }

    /* ----------------------------------------------------------------
     * Acción "Inspecciones"
     * ---------------------------------------------------------------- */

    public function testRutaDeConsultaRegistradaEnGrupoInspector(): void
    {
        $rutas = $this->leerApp('Config/Routes.php');

        $this->assertStringContainsString('inspecciones/ver/(:num)', $rutas);
        $this->assertStringContainsString('Inspector\Inspecciones::ver/$1', $rutas);
    }

    public function testControladorExponeLaConsultaComoMetodoSeparado(): void
    {
        $contenido = $this->leerApp('Controllers/Inspector/Inspecciones.php');

        $this->assertMatchesRegularExpression('/function\s+ver\s*\(\s*int\s+\$obraId/', $contenido);
        $this->assertMatchesRegularExpression('/function\s+nueva\s*\(\s*int\s+\$obraId/', $contenido, 'El alta de inspección se conserva.');
        $this->assertStringContainsString("view('inspector/inspecciones'", $contenido);
    }

    public function testLaConsultaNoExigeEstadoDeObraQuePermitaInspeccionar(): void
    {
        $metodo = $this->metodo(
            $this->leerApp('Controllers/Inspector/Inspecciones.php'),
            'public function ver('
        );

        $this->assertStringContainsString('esVigente', $metodo, 'La autorización vigente se mantiene.');
        $this->assertStringNotContainsString(
            'permiteInspeccionar',
            $metodo,
            'Consultar el historial no debe depender del estado de la obra: una obra finalizada también se consulta.'
        );
    }

    public function testVistaDeObraExponeLaAccionInspecciones(): void
    {
        $obra = $this->leerApp('Views/inspector/obra.php');
        $bloque = $this->bloque($obra, 'class="io-consulta"', '</section>');

        $this->assertStringContainsString('inspector/inspecciones/ver/', $bloque, 'La acción apunta a la consulta.');
        $this->assertStringContainsString('Inspecciones', $bloque);
        $this->assertStringContainsString('io-btn-consulta', $bloque);
        $this->assertStringNotContainsString(
            'inspecciones/nueva/',
            $bloque,
            'La consulta no debe ofrecer el alta de inspecciones.'
        );
    }

    public function testLaAccionInspeccionesEsIndependienteDeLaBajaPorEstadoDeObra(): void
    {
        $obra = $this->leerApp('Views/inspector/obra.php');

        $this->assertLessThan(
            strpos($obra, 'class="io-consulta"'),
            strrpos($obra, '<?php endif; ?>'),
            'La consulta se declara después del endif de «puede_inspeccionar», para existir también en obras que no permiten inspeccionar.'
        );
    }

    /* ----------------------------------------------------------------
     * Bloque de sincronización
     * ---------------------------------------------------------------- */

    public function testElBloqueDeSincronizacionSeTitulaComoTal(): void
    {
        $obra   = $this->leerApp('Views/inspector/obra.php');
        $bloque = $this->bloque($obra, 'class="io-locales"', '</section>');

        $this->assertStringContainsString('Sincronización', $bloque);
        $this->assertStringContainsString('id="btnSincronizar"', $bloque, 'El ciclo de sincronización se conserva.');
        $this->assertStringContainsString('id="sincronizacionEstado"', $bloque, 'El aviso del ciclo se conserva.');
        $this->assertStringContainsString('assets/js/pages/obra-inspecciones.js', $obra);
        $this->assertStringNotContainsString(
            'inspecciones/ver/',
            $bloque,
            'La cola no debe enlazar a la consulta histórica: son vistas separadas.'
        );
    }

    public function testLaPaginaDeSincronizacionFiltraLoYaConfirmado(): void
    {
        $js = $this->leerPublic('assets/js/pages/obra-inspecciones.js');

        $this->assertStringContainsString('inspeccionesPendientes', $js, 'La vista decide qué inspecciones mostrar.');
        $this->assertStringContainsString(
            'fotosPendientesDe',
            $js,
            'Una inspección sincronizada se conserva solo si conserva fotografías en cola.'
        );
        $this->assertStringContainsString('dependencia_uuid', $js, 'La cola resuelve las fotografías pendientes por su inspección.');
        $this->assertStringNotContainsString(
            'inspecciones/ver/',
            $js,
            'La cola no navega al historial.'
        );
    }

    public function testLaColaNoCambiaLaLogicaDeSincronizacion(): void
    {
        $js = $this->leerPublic('assets/js/pages/obra-inspecciones.js');

        $this->assertStringContainsString('sincronizarTodo({ obraId: obraId, motivo: \'manual\', revivirAgotadas: true })', $js);
        $this->assertStringContainsString('SINCRONIZACION.reintentar(', $js, 'El reintento manual se conserva.');
        $this->assertStringContainsString('requiereReintentoManual', $js);
    }

    /* ----------------------------------------------------------------
     * Vista de consulta
     * ---------------------------------------------------------------- */

    public function testLaVistaDeConsultaInformaElEstadoVacio(): void
    {
        $vista = $this->leerApp('Views/inspector/inspecciones.php');
        $texto = $this->textoPlano($vista);

        $this->assertStringContainsString("extend('layouts/auth')", $vista);
        $this->assertStringContainsString('No existen inspecciones aún', $texto);
        $this->assertStringContainsString(
            'Se recomienda generar una nueva inspección para comenzar a registrar el seguimiento de la obra.',
            $texto
        );
        $this->assertStringContainsString('assets/css/pages/inspector-inspecciones.css', $vista);
    }

    public function testLaVistaDeConsultaNoOfreceElAltaNiUsaJavaScript(): void
    {
        $vista = $this->leerApp('Views/inspector/inspecciones.php');

        $this->assertStringNotContainsString(
            'inspecciones/nueva/',
            $vista,
            'El alta sigue siendo una acción de la obra, no de la consulta.'
        );
        $this->assertStringNotContainsString(
            'assets/js/',
            $vista,
            'El histórico se entrega desde el servidor: la vista no necesita JavaScript.'
        );
        $this->assertStringContainsString(
            'grupos',
            $vista,
            'El histórico se presenta con los datos que entrega el servidor.'
        );
    }

    public function testLaNuevaInspeccionNoOfreceHistorial(): void
    {
        $vista = $this->leerApp('Views/inspector/inspeccion_nueva.php');

        $this->assertStringNotContainsString(
            'inspecciones/ver/',
            $vista,
            'El alta no debe enlazar a la consulta histórica.'
        );
        $this->assertStringNotContainsString(
            'anteriores',
            $vista,
            'El alta no ofrece historial, galería ni inspecciones previas.'
        );
    }

    /* ----------------------------------------------------------------
     * Infraestructura offline
     * ---------------------------------------------------------------- */

    public function testElEstiloDeLaConsultaEstaEnElPrecache(): void
    {
        $sw = $this->leerPublic('sw.js');

        $this->assertStringContainsString(
            "'/assets/css/pages/inspector-inspecciones.css'",
            $sw,
            'Sin estar en el app shell la página perdería sus estilos sin conexión.'
        );
    }

    public function testElModeloAgrupaLasInspeccionesDeLaObra(): void
    {
        $modelo = $this->leerApp('Models/InspeccionModel.php');

        $this->assertMatchesRegularExpression(
            '/function\s+listarPorObraAgrupado\s*\(\s*int\s+\$obraId/',
            $modelo
        );
        $this->assertMatchesRegularExpression(
            '/listarPorObraAgrupado[\s\S]{0,600}where\(\'obra_id\',\s*\$obraId\)/',
            $modelo,
            'El listado debe filtrar por obra.'
        );
    }

    /* ----------------------------------------------------------------
     * Autorización de la ruta
     * ---------------------------------------------------------------- */

    public function testLaConsultaSinSesionRedirigeAlLogin(): void
    {
        $resultado = $this->get('/inspector/inspecciones/ver/1');

        $resultado->assertRedirectTo('/login');
    }

    public function testLaConsultaSinRolDeInspectorRedirigeAlDashboard(): void
    {
        $resultado = $this->withSession([
            'logged_in' => true,
            'activo'    => true,
            'roles'     => ['CONSULTA'],
        ])->get('/inspector/inspecciones/ver/1');

        $resultado->assertRedirectTo('/dashboard');
    }
}
