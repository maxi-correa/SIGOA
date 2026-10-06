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

    public function testLaConsultaViveEnElControladorCompartido(): void
    {
        $compartido = $this->leerApp('Controllers/Inspecciones.php');
        $inspector  = $this->leerApp('Controllers/Inspector/Inspecciones.php');

        $this->assertMatchesRegularExpression('/function\s+ver\s*\(\s*int\s+\$obraId/', $compartido);
        $this->assertStringContainsString("view('inspector/inspecciones'", $compartido);

        /* El inspector hereda la consulta: no puede existir una segunda
           implementación que se desincronice de la compartida. */
        $this->assertStringContainsString('extends InspeccionesConsulta', $inspector);
        $this->assertStringNotContainsString('public function ver(', $inspector);
        $this->assertStringNotContainsString('public function detalle(', $inspector);

        /* El alta sí es suya y se conserva. */
        $this->assertMatchesRegularExpression('/function\s+nueva\s*\(\s*int\s+\$obraId/', $inspector);
    }

    public function testLaConsultaNoExigeEstadoDeObraQuePermitaInspeccionar(): void
    {
        $metodo = $this->metodo(
            $this->leerApp('Controllers/Inspecciones.php'),
            'public function ver('
        );

        $this->assertStringContainsString(
            'puedeConsultarObra',
            $metodo,
            'La autorización de la consulta se resuelve en el servicio de acceso.'
        );

        $this->assertStringNotContainsString(
            'puedeInspeccionar',
            $metodo,
            'Consultar el historial no debe depender del estado de la obra: una obra finalizada también se consulta.'
        );

        /* Y el servicio que decide tampoco introduce el estado de la obra, que
           es un criterio de *inspección*. Para el inspector, la asignación
           vigente es solo una vía: también habilita la histórica. */
        $servicio = $this->metodo(
            $this->leerApp('Services/AccesoInspecciones.php'),
            'public function puedeConsultarObra('
        );

        $this->assertStringNotContainsString('puedeInspeccionar', $servicio);
        $this->assertStringNotContainsString('permiteInspeccionar', $servicio);
        $this->assertStringContainsString(
            'haTenidoAsignacion',
            $servicio,
            'El inspector consulta también las obras cuya asignación ya se cerró.'
        );
    }

    public function testVistaDeObraExponeLaAccionInspecciones(): void
    {
        $obra = $this->leerApp('Views/inspector/obra.php');
        $bloque = $this->bloque($obra, 'class="io-consulta"', '</section>');

        $this->assertStringContainsString('inspector/inspecciones/ver/', $bloque, 'La acción apunta a la consulta.');
        $this->assertStringContainsString('Historial de inspecciones', $bloque);
        $this->assertStringContainsString('io-btn-consulta', $bloque);
        $this->assertStringNotContainsString(
            'inspecciones/nueva/',
            $bloque,
            'La consulta no debe ofrecer el alta de inspecciones.'
        );
    }

    /**
     * El historial se nombra por lo que es y no "Inspecciones", que es el
     * nombre del ítem global del sidebar y de la propia pantalla de listado.
     */
    public function testLaAccionSeLlamaHistorialDeInspecciones(): void
    {
        $obra = $this->leerApp('Views/inspector/obra.php');
        $texto = $this->textoPlano($obra);

        $this->assertMatchesRegularExpression(
            '/>\s*Historial de inspecciones\s*</',
            $texto,
            'La acción de la obra se distingue del ítem global del menú.'
        );
    }

    /**
     * El historial es la segunda acción de navegación de la pantalla: se
     * presenta inmediatamente debajo de "Mis obras", no al final del cuerpo.
     */
    public function testElHistorialQuedaDebajoDeMisObras(): void
    {
        $obra = $this->leerApp('Views/inspector/obra.php');

        $volver = strpos($obra, 'io-btn-volver');
        $historial = strpos($obra, 'class="io-consulta"');
        $identidad = strpos($obra, 'class="io-identidad"');

        $this->assertNotFalse($volver, 'La pantalla conserva la acción de volver a "Mis obras".');
        $this->assertNotFalse($historial, 'La pantalla ofrece el historial de la obra.');
        $this->assertLessThan(
            $historial,
            $volver,
            'El historial se declara después de "Mis obras".'
        );
        $this->assertLessThan(
            $identidad,
            $historial,
            'El historial se declara antes de los datos de la obra: queda debajo, no al final.'
        );
    }

    public function testLaAccionInspeccionesEsIndependienteDeLaBajaPorEstadoDeObra(): void
    {
        $obra = $this->leerApp('Views/inspector/obra.php');

        /* Declarada antes del condicional de «puede_inspeccionar»: existe
           también en obras que no permiten iniciar inspecciones, porque
           consultar el historial no depende del estado de la obra. */
        $this->assertLessThan(
            strpos($obra, '<?php if ($puedeInspeccionar): ?>'),
            strpos($obra, 'class="io-consulta"'),
            'La consulta se declara fuera del condicional de «puede_inspeccionar».'
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
