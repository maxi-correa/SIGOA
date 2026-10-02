<?php

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Render real de la consulta de inspecciones de una obra (Fase E.3).
 *
 * Verifica el comportamiento observable de la vista nueva:
 *
 * * una obra **sin** inspecciones muestra el estado vacío acordado;
 * * una obra **con** inspecciones no muestra ese estado vacío;
 * * la consulta no depende del estado de la obra (una obra FINALIZADA también
 *   se consulta), a diferencia del alta de inspecciones;
 * * sin asignación vigente no se entra.
 *
 * A diferencia del alta (D.2), la ruta es de solo lectura: no prepara
 * almacenamiento físico ni escribe datos.
 *
 * Usa la conexión `tests` (SQLite en memoria compartida con el grupo) y crea
 * únicamente las tablas mínimas que la vista necesita.
 *
 * @internal
 */
final class InspectorInspeccionesConsultaRenderTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private BaseConnection $conn;

    private int $obraId;

    private int $inspectorId;

    private int $estadoEjecucion;

    private int $estadoFinalizada;

    /** @var list<int> Usuarios creados por esta prueba (para su limpieza). */
    private array $usuariosIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->conn = db_connect();

        $this->crearTablas();

        $this->estadoEjecucion  = $this->insertarEstado('EN EJECUCIÓN');
        $this->estadoFinalizada = $this->insertarEstado('FINALIZADA');

        $this->conn->table('obras')->insert([
            'codigo'         => 'OBR-' . strtoupper(dechex((int) (microtime(true) * 1000))),
            'nombre'         => 'OBRA CONSULTA INSPECCIONES E3',
            'estado_obra_id' => $this->estadoEjecucion,
        ]);
        $this->obraId = (int) $this->conn->insertID();

        $this->inspectorId = $this->crearUsuario('PRUEBA_INSPECTOR_E3');
    }

    protected function tearDown(): void
    {
        /* La conexión `tests` es compartida: solo se borran las filas creadas
           por esta prueba, en orden de dependencia (hijas antes que padres),
           para no romper las claves foráneas de otros archivos de prueba. */
        $this->conn->table('inspecciones')->where('obra_id', $this->obraId)->delete();
        $this->conn->table('inspectores_obras')->where('obra_id', $this->obraId)->delete();
        $this->conn->table('obras')->where('id', $this->obraId)->delete();
        $this->conn->table('usuarios')->whereIn('id', $this->usuariosIds)->delete();
        $this->conn->table('estados_obra')
            ->whereIn('id', [$this->estadoEjecucion, $this->estadoFinalizada])
            ->delete();

        parent::tearDown();
    }

    private function crearUsuario(string $nombreUsuario): int
    {
        $this->conn->table('usuarios')->insert([
            'usuario'       => $nombreUsuario,
            'password_hash' => password_hash('Inspector123', PASSWORD_DEFAULT),
            'activo'        => 1,
        ]);

        $id = (int) $this->conn->insertID();

        $this->usuariosIds[] = $id;

        return $id;
    }

    private function tabla(string $nombre): string
    {
        return $this->conn->prefixTable($nombre);
    }

    private function crearTablas(): void
    {
        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('obras') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            codigo VARCHAR(20) NOT NULL,
            expediente_municipal VARCHAR(60) NULL,
            numero_licitacion VARCHAR(30) NULL,
            tipo_licitacion_id INTEGER NULL,
            nombre VARCHAR(200) NOT NULL,
            barrio_id INTEGER NULL,
            empresa_id INTEGER NULL,
            estado_obra_id INTEGER NOT NULL,
            created_at DATETIME NULL
        )');

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('usuarios') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre VARCHAR(100) NULL,
            apellido VARCHAR(100) NULL,
            usuario VARCHAR(100) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            email VARCHAR(150) NULL,
            activo TINYINT NOT NULL DEFAULT 1,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('estados_obra') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            estado VARCHAR(60) NOT NULL,
            descripcion TEXT NULL,
            activo TINYINT NOT NULL DEFAULT 1
        )');

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('inspectores_obras') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            obra_id INTEGER NOT NULL,
            usuario_id INTEGER NOT NULL,
            fecha_inicio DATE NULL,
            fecha_fin DATE NULL,
            documento_id INTEGER NULL,
            observaciones TEXT NULL,
            created_at DATETIME NULL
        )');

        /* `created_at` y `updated_at` son NOT NULL en el esquema real. */
        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('inspecciones') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            uuid VARCHAR(36) NOT NULL,
            obra_id INTEGER NOT NULL,
            inspector_id INTEGER NULL,
            fecha_inspeccion DATE NOT NULL,
            hora_inspeccion TIME NULL,
            observacion TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )');

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('barrios') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre VARCHAR(150) NOT NULL,
            activo TINYINT NOT NULL DEFAULT 1
        )');

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('empresas') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            razon_social VARCHAR(200) NOT NULL,
            activo TINYINT NOT NULL DEFAULT 1
        )');

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('tipos_licitacion') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tipo_licitacion VARCHAR(120) NOT NULL,
            activo TINYINT NOT NULL DEFAULT 1
        )');
    }

    private function insertarEstado(string $nombre): int
    {
        $this->conn->table('estados_obra')->insert([
            'estado'      => $nombre,
            'descripcion' => 'Estado de prueba E.3',
            'activo'      => 1,
        ]);

        return (int) $this->conn->insertID();
    }

    private function sesionInspector(): array
    {
        return [
            'logged_in' => true,
            'activo'    => true,
            'user_id'   => $this->inspectorId,
            'username'  => 'PRUEBA_INSPECTOR_E3',
            'user_name' => 'PRUEBA_INSPECTOR_E3',
            'roles'     => ['INSPECTOR'],
        ];
    }

    private function asignarVigente(int $obraId, int $usuarioId): void
    {
        $this->conn->table('inspectores_obras')->insert([
            'obra_id'      => $obraId,
            'usuario_id'   => $usuarioId,
            'fecha_inicio' => date('Y-m-d'),
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Registra una inspección de la obra de prueba.
     *
     * `hora` y `observacion` se pueden dejar sin valor porque ambos casos son
     * reales: una inspección puede haberse registrado sin hora y sin
     * observaciones.
     *
     * @return int `inspecciones.id` registrado.
     */
    private function registrarInspeccion(
        string $uuid,
        string $fecha,
        ?string $hora = '10:30:00',
        ?string $observacion = null
    ): int {
        $this->conn->table('inspecciones')->insert([
            'uuid'             => $uuid,
            'obra_id'          => $this->obraId,
            'inspector_id'     => $this->inspectorId,
            'fecha_inspeccion' => $fecha,
            'hora_inspeccion'  => $hora,
            'observacion'      => $observacion,
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->conn->insertID();
    }

    private function ponerObraEnEstado(int $estadoId): void
    {
        $this->conn->table('obras')->where('id', $this->obraId)->update(['estado_obra_id' => $estadoId]);
    }

    public function testObraSinInspeccionesMuestraElEstadoVacioAcordado(): void
    {
        $this->asignarVigente($this->obraId, $this->inspectorId);

        $resultado = $this->withSession($this->sesionInspector())
            ->get('/inspector/inspecciones/ver/' . $this->obraId);

        $resultado->assertStatus(200);
        $resultado->assertSee('Inspecciones');
        $resultado->assertSee('No existen inspecciones aún');
        $resultado->assertSee('OBRA CONSULTA INSPECCIONES E3');

        /* El texto de la View se redacta en varias líneas y la respuesta
           escapa las entidades HTML: se compara la frase completa sobre el
           cuerpo normalizado y decodificado. */
        $cuerpo = html_entity_decode(
            (string) preg_replace('/\s+/', ' ', $resultado->getBody()),
            ENT_QUOTES,
            'UTF-8'
        );

        $this->assertStringContainsString(
            'Se recomienda generar una nueva inspección para comenzar a registrar el seguimiento de la obra.',
            $cuerpo
        );
    }

    public function testObraConInspeccionesNoMuestraElEstadoVacio(): void
    {
        $this->asignarVigente($this->obraId, $this->inspectorId);
        $this->registrarInspeccion('11111111-1111-4111-8111-111111111111', '2026-09-22');
        $this->registrarInspeccion('22222222-2222-4222-8222-222222222222', '2026-09-23');

        $resultado = $this->withSession($this->sesionInspector())
            ->get('/inspector/inspecciones/ver/' . $this->obraId);

        $resultado->assertStatus(200);
        $resultado->assertSee('2 inspecciones registradas');
        $resultado->assertDontSee('No existen inspecciones aún');
    }

    public function testUnaInspeccionSeInformaEnSingular(): void
    {
        $this->asignarVigente($this->obraId, $this->inspectorId);
        $this->registrarInspeccion('33333333-3333-4333-8333-333333333333', '2026-09-22');

        $resultado = $this->withSession($this->sesionInspector())
            ->get('/inspector/inspecciones/ver/' . $this->obraId);

        $resultado->assertSee('1 inspección registrada');
    }

    public function testLaConsultaNoSeBloqueaPorElEstadoDeLaObra(): void
    {
        $this->asignarVigente($this->obraId, $this->inspectorId);
        $this->ponerObraEnEstado($this->estadoFinalizada);
        $this->registrarInspeccion('44444444-4444-4444-8444-444444444444', '2026-09-22');

        $resultado = $this->withSession($this->sesionInspector())
            ->get('/inspector/inspecciones/ver/' . $this->obraId);

        $resultado->assertStatus(200);
        $resultado->assertSee('FINALIZADA');
        $resultado->assertSee('1 inspección registrada');
    }

    public function testInspectorNoVigenteNoPuedeConsultar(): void
    {
        $otroId = $this->crearUsuario('PRUEBA_INSPECTOR_E3_OTRO');

        $this->asignarVigente($this->obraId, $otroId);

        $resultado = $this->withSession($this->sesionInspector())
            ->get('/inspector/inspecciones/ver/' . $this->obraId);

        $resultado->assertRedirectTo('/inspector/dashboard');
    }

    public function testObraInexistenteRedirigeAlDashboard(): void
    {
        $resultado = $this->withSession($this->sesionInspector())
            ->get('/inspector/inspecciones/ver/999999999');

        $resultado->assertRedirectTo('/inspector/dashboard');
    }

    public function testLaConsultaEsDeSoloLectura(): void
    {
        $this->asignarVigente($this->obraId, $this->inspectorId);
        $this->registrarInspeccion('55555555-5555-4555-8555-555555555555', '2026-09-22');

        $antes = (int) $this->conn->table('inspecciones')->countAllResults();

        $this->withSession($this->sesionInspector())
            ->get('/inspector/inspecciones/ver/' . $this->obraId);

        $despues = (int) $this->conn->table('inspecciones')->countAllResults();

        $this->assertSame($antes, $despues, 'La consulta no crea inspecciones en el servidor.');
    }
    /* ----------------------------------------------------------------
     * Fase E.4 — navegación histórica
     * ---------------------------------------------------------------- */

    public function testLasFechasSeMuestranDeMasRecienteAMasAntigua(): void
    {
        $this->asignarVigente($this->obraId, $this->inspectorId);

        $this->registrarInspeccion('a1111111-1111-4111-8111-111111111111', '2026-09-10', '09:00:00');
        $this->registrarInspeccion('a2222222-2222-4222-8222-222222222222', '2026-09-22', '09:00:00');
        $this->registrarInspeccion('a3333333-3333-4333-8333-333333333333', '2026-09-17', '09:00:00');

        $cuerpo = $this->cuerpoDe($this->verHistorico());

        $posiciones = [
            '22/09/2026' => (int) strpos($cuerpo, '22/09/2026'),
            '17/09/2026' => (int) strpos($cuerpo, '17/09/2026'),
            '10/09/2026' => (int) strpos($cuerpo, '10/09/2026'),
        ];

        foreach ($posiciones as $fecha => $posicion) {
            $this->assertNotSame(0, $posicion, "La fecha {$fecha} debe mostrarse en el histórico.");
        }

        $this->assertTrue(
            $posiciones['22/09/2026'] < $posiciones['17/09/2026'],
            'La fecha más reciente debe aparecer antes que las anteriores.'
        );
        $this->assertTrue(
            $posiciones['17/09/2026'] < $posiciones['10/09/2026'],
            'Las fechas deben ir de más reciente a más antigua.'
        );
    }

    public function testDentroDeUnaFechaLasInspeccionesVanDeMasRecienteAMasAntigua(): void
    {
        $this->asignarVigente($this->obraId, $this->inspectorId);

        $this->registrarInspeccion('b1111111-1111-4111-8111-111111111111', '2026-09-22', '09:00:00');
        $this->registrarInspeccion('b2222222-2222-4222-8222-222222222222', '2026-09-22', '14:00:00');
        $this->registrarInspeccion('b3333333-3333-4333-8333-333333333333', '2026-09-22', '11:30:00');

        $cuerpo = $this->cuerpoDe($this->verHistorico());

        $posiciones = [
            '14:00' => (int) strpos($cuerpo, '14:00'),
            '11:30' => (int) strpos($cuerpo, '11:30'),
            '09:00' => (int) strpos($cuerpo, '09:00'),
        ];

        foreach ($posiciones as $hora => $posicion) {
            $this->assertNotSame(0, $posicion, "La hora {$hora} debe mostrarse.");
        }

        $this->assertTrue(
            $posiciones['14:00'] < $posiciones['11:30'] && $posiciones['11:30'] < $posiciones['09:00'],
            'Dentro de la fecha las inspecciones van de la hora más reciente a la más antigua.'
        );
        $this->assertSame(
            1,
            substr_count($cuerpo, '22/09/2026'),
            'Las tres inspecciones deben pertenecer a un único grupo de fecha.'
        );
    }

    public function testLaMedianocheSeMuestraComoHoraValida(): void
    {
        $this->asignarVigente($this->obraId, $this->inspectorId);

        $this->registrarInspeccion('c1111111-1111-4111-8111-111111111111', '2026-09-22', '00:00:00');

        $resultado = $this->verHistorico();

        $resultado->assertSee('00:00');
        $resultado->assertDontSee('Sin hora');
    }

    public function testUnaInspeccionSinHoraSeIdentificaComoTal(): void
    {
        $this->asignarVigente($this->obraId, $this->inspectorId);

        $this->registrarInspeccion('d1111111-1111-4111-8111-111111111111', '2026-09-22', '10:00:00');
        $this->registrarInspeccion('d2222222-2222-4222-8222-222222222222', '2026-09-22', null);

        $resultado = $this->verHistorico();

        $resultado->assertSee('Sin hora');

        $cuerpo = $this->cuerpoDe($resultado);

        $this->assertTrue(
            (int) strpos($cuerpo, '10:00') < (int) strpos($cuerpo, 'Sin hora'),
            'Una hora registrada es más reciente que la ausencia de hora: se muestra después.'
        );
    }

    public function testCadaInspeccionEnlazaASuDetalle(): void
    {
        $this->asignarVigente($this->obraId, $this->inspectorId);

        $id = $this->registrarInspeccion('e1111111-1111-4111-8111-111111111111', '2026-09-22', '10:30:00');

        $resultado = $this->verHistorico();

        $resultado->assertStatus(200);
        $resultado->assertSee('inspector/inspecciones/detalle/' . $id);
    }

    public function testElDetalleMuestraLosDatosRegistradosDeLaInspeccion(): void
    {
        $this->asignarVigente($this->obraId, $this->inspectorId);

        $id = $this->registrarInspeccion(
            'f1111111-1111-4111-8111-111111111111',
            '2026-09-22',
            '14:45:00',
            'Fisuras visibles en el sector norte.'
        );

        $resultado = $this->withSession($this->sesionInspector())
            ->get('/inspector/inspecciones/detalle/' . $id);

        $resultado->assertStatus(200);
        $resultado->assertSee('Inspección del 22/09/2026');
        $resultado->assertSee('14:45');
        $resultado->assertSee('Observaciones');
        $resultado->assertSee('Fisuras visibles en el sector norte.');
        $resultado->assertDontSee('Sin observaciones');
        $resultado->assertSee('inspector/inspecciones/ver/' . $this->obraId);
    }

    public function testElDetalleDeUnaInspeccionSinHoraLoDice(): void
    {
        $this->asignarVigente($this->obraId, $this->inspectorId);

        $id = $this->registrarInspeccion('f2222222-2222-4222-8222-222222222222', '2026-09-22', null);

        $resultado = $this->withSession($this->sesionInspector())
            ->get('/inspector/inspecciones/detalle/' . $id);

        $resultado->assertStatus(200);
        $resultado->assertSee('Sin hora');
        $resultado->assertSee('Sin observaciones');
    }

    public function testElDetalleNoSeBloqueaPorElEstadoDeLaObra(): void
    {
        $this->asignarVigente($this->obraId, $this->inspectorId);
        $this->ponerObraEnEstado($this->estadoFinalizada);

        $id = $this->registrarInspeccion('f3333333-3333-4333-8333-333333333333', '2026-09-22', '10:30:00');

        $resultado = $this->withSession($this->sesionInspector())
            ->get('/inspector/inspecciones/detalle/' . $id);

        $resultado->assertStatus(200);
        $resultado->assertSee('FINALIZADA');
        $resultado->assertSee('10:30');
    }

    public function testElDetalleDeUnaInspeccionDeObraAjenaSeRechaza(): void
    {
        $otroId = $this->crearUsuario('PRUEBA_INSPECTOR_E4_AJENO');

        $this->conn->table('obras')->insert([
            'codigo'         => 'OBR-' . strtoupper(dechex((int) (microtime(true) * 1000))),
            'nombre'         => 'OBRA AJENA E4',
            'estado_obra_id' => $this->estadoEjecucion,
        ]);
        $obraAjenaId = (int) $this->conn->insertID();

        $this->asignarVigente($obraAjenaId, $otroId);

        $this->conn->table('inspecciones')->insert([
            'uuid'             => 'f4444444-4444-4444-8444-444444444444',
            'obra_id'          => $obraAjenaId,
            'inspector_id'     => $otroId,
            'fecha_inspeccion' => '2026-09-22',
            'hora_inspeccion'  => '10:30:00',
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);
        $inspeccionAjenaId = (int) $this->conn->insertID();

        $resultado = $this->withSession($this->sesionInspector())
            ->get('/inspector/inspecciones/detalle/' . $inspeccionAjenaId);

        $resultado->assertRedirectTo('/inspector/dashboard');

        /* La inspección ajena no pertenece a la obra de la prueba: se limpia
           explícitamente para no dejar filas huérfanas en el banco compartido. */
        $this->conn->table('inspecciones')->where('id', $inspeccionAjenaId)->delete();
        $this->conn->table('inspectores_obras')->where('obra_id', $obraAjenaId)->delete();
        $this->conn->table('obras')->where('id', $obraAjenaId)->delete();
    }

    public function testUnaInspeccionInexistenteRedirigeAlDashboard(): void
    {
        $resultado = $this->withSession($this->sesionInspector())
            ->get('/inspector/inspecciones/detalle/999999999');

        $resultado->assertRedirectTo('/inspector/dashboard');
    }

    public function testLaNavegacionHistoricaNoModificaLosDatos(): void
    {
        $this->asignarVigente($this->obraId, $this->inspectorId);

        $id = $this->registrarInspeccion(
            'a1111111-1111-4111-8111-111111111111',
            '2026-09-22',
            '14:45:00',
            'Observación que no debe cambiar.'
        );

        $antes = $this->conn->table('inspecciones')->where('id', $id)->get()->getRowArray();

        $this->withSession($this->sesionInspector())
            ->get('/inspector/inspecciones/ver/' . $this->obraId);
        $this->withSession($this->sesionInspector())
            ->get('/inspector/inspecciones/detalle/' . $id);

        $despues = $this->conn->table('inspecciones')->where('id', $id)->get()->getRowArray();

        $this->assertSame($antes, $despues, 'Consultar el histórico no modifica ni recalcula datos.');
    }

    /**
     * Cuerpo de una respuesta con los espacios colapsados y las entidades HTML
     * decodificadas: permite afirmar sobre frases y comparar posiciones.
     */
    private function cuerpoDe($respuesta): string
    {
        return html_entity_decode(
            (string) preg_replace('/\s+/', ' ', $respuesta->getBody()),
            ENT_QUOTES,
            'UTF-8'
        );
    }

    /** Histórico de la obra de prueba, con inspector asignado. */
    private function verHistorico()
    {
        return $this->withSession($this->sesionInspector())
            ->get('/inspector/inspecciones/ver/' . $this->obraId);
    }
}
