<?php

use App\Libraries\PlazoObra;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Reglas de servidor de la gestión de recepciones (BLOQUE 3 RP/RD).
 *
 * Cubre el alta y la corrección de la recepción provisoria, el alta de la
 * definitiva, la habilitación por fecha prevista y las transiciones de
 * estado, incluyendo obras históricas que completan datos sin retroceder.
 *
 * @internal
 */
final class RecepcionesGestionTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private BaseConnection $conn;

    private int $obraId;

    /** @var array<string, int> */
    private array $estados = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->conn = db_connect();

        foreach ([
            'recepciones_definitivas',
            'recepciones_provisorias',
            'obras',
            'estados_obra',
            'barrios',
            'empresas',
            'tipos_licitacion',
        ] as $tabla) {
            $this->conn->query('DROP TABLE IF EXISTS ' . $this->tabla($tabla));
        }

        $this->conn->query('CREATE TABLE ' . $this->tabla('estados_obra') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            estado VARCHAR(60) NOT NULL,
            descripcion TEXT NULL,
            activo INTEGER NOT NULL DEFAULT 1
        )');

        $this->conn->query('CREATE TABLE ' . $this->tabla('obras') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            codigo VARCHAR(20) NOT NULL,
            expediente_municipal VARCHAR(60) NULL,
            numero_licitacion VARCHAR(30) NULL,
            tipo_licitacion_id INTEGER NULL,
            nombre VARCHAR(200) NOT NULL,
            barrio_id INTEGER NULL,
            empresa_id INTEGER NULL,
            estado_obra_id INTEGER NOT NULL,
            fecha_inicio DATE NULL,
            plazo_original_valor INTEGER NULL,
            plazo_original_unidad VARCHAR(20) NULL,
            plazo_original_dias INTEGER NULL,
            plazo_inicial_confirmado INTEGER NOT NULL DEFAULT 0,
            plazo_conservacion_dias INTEGER NOT NULL DEFAULT 180,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');

        $this->conn->query('CREATE TABLE ' . $this->tabla('barrios') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre VARCHAR(200) NULL,
            activo INTEGER NOT NULL DEFAULT 1
        )');

        $this->conn->query('CREATE TABLE ' . $this->tabla('empresas') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            razon_social VARCHAR(200) NULL,
            activo INTEGER NOT NULL DEFAULT 1
        )');

        $this->conn->query('CREATE TABLE ' . $this->tabla('tipos_licitacion') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tipo_licitacion VARCHAR(120) NULL,
            activo INTEGER NOT NULL DEFAULT 1
        )');

        $this->conn->query('CREATE TABLE ' . $this->tabla('recepciones_provisorias') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            obra_id INTEGER NOT NULL UNIQUE,
            fecha_recepcion DATE NOT NULL,
            documento_id INTEGER NULL,
            observaciones TEXT NULL,
            plazo_conservacion_dias INTEGER NOT NULL,
            fecha_prevista_definitiva DATE NOT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');

        $this->conn->query('CREATE TABLE ' . $this->tabla('recepciones_definitivas') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            obra_id INTEGER NOT NULL UNIQUE,
            fecha_recepcion DATE NOT NULL,
            documento_id INTEGER NULL,
            observaciones TEXT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');

        foreach (['PREVIO INICIO', 'EN EJECUCIÓN', 'NEUTRALIZADA', 'EN PLAZO DE CONSERVACIÓN', 'FINALIZADA'] as $estado) {
            $this->conn->table('estados_obra')->insert(['estado' => $estado, 'activo' => 1]);
            $this->estados[$estado] = (int) $this->conn->insertID();
        }

        $this->obraId = $this->crearObra($this->estados['EN EJECUCIÓN']);
    }

    protected function tearDown(): void
    {
        foreach ([
            'recepciones_definitivas',
            'recepciones_provisorias',
            'obras',
            'estados_obra',
            'barrios',
            'empresas',
            'tipos_licitacion',
        ] as $tabla) {
            $this->conn->query('DROP TABLE IF EXISTS ' . $this->tabla($tabla));
        }

        parent::tearDown();
    }

    public function testRegistraLaProvisoriaYIniciaElPlazoDeConservacion(): void
    {
        $resultado = $this->postComoAdmin('/obras/recepciones/provisoria', [
            'obra_id'                 => $this->obraId,
            'tipo_recepcion'          => 'provisoria',
            'fecha_recepcion'         => '2026-10-23',
            'plazo_conservacion_dias' => '180',
            'observaciones'           => 'Acta provisoria',
        ]);

        $resultado->assertRedirectTo('/obras/recepciones/' . $this->obraId);

        $fila = $this->conn->table('recepciones_provisorias')->get()->getRow();

        $this->assertNotNull($fila);
        $this->assertSame('2026-10-23', $fila->fecha_recepcion);
        $this->assertSame(180, (int) $fila->plazo_conservacion_dias);
        $this->assertSame('2027-04-21', $fila->fecha_prevista_definitiva);
        $this->assertSame($this->estados['EN PLAZO DE CONSERVACIÓN'], $this->estadoActual());

        $obra = $this->conn->table('obras')->where('id', $this->obraId)->get()->getRow();
        $this->assertSame(180, (int) $obra->plazo_conservacion_dias);
    }

    public function testRechazaUnaFechaDeRecepcionFutura(): void
    {
        $resultado = $this->postComoAdmin('/obras/recepciones/provisoria', [
            'obra_id'                 => $this->obraId,
            'fecha_recepcion'         => date('Y-m-d', strtotime('+1 day')),
            'plazo_conservacion_dias' => '180',
        ]);

        $resultado->assertRedirectTo('/obras/recepciones/' . $this->obraId);

        $this->assertSame(0, $this->conn->table('recepciones_provisorias')->countAllResults());
        $this->assertSame($this->estados['EN EJECUCIÓN'], $this->estadoActual());
    }

    public function testRechazaUnaFechaDeRecepcionInexistente(): void
    {
        $resultado = $this->postComoAdmin('/obras/recepciones/provisoria', [
            'obra_id'                 => $this->obraId,
            'fecha_recepcion'         => '2026-02-31',
            'plazo_conservacion_dias' => '180',
        ]);

        $resultado->assertRedirectTo('/obras/recepciones/' . $this->obraId);

        $this->assertSame(0, $this->conn->table('recepciones_provisorias')->countAllResults());
    }

    public function testRechazaUnPlazoDeConservacionNoPositivo(): void
    {
        $resultado = $this->postComoAdmin('/obras/recepciones/provisoria', [
            'obra_id'                 => $this->obraId,
            'fecha_recepcion'         => '2026-10-23',
            'plazo_conservacion_dias' => '0',
        ]);

        $resultado->assertRedirectTo('/obras/recepciones/' . $this->obraId);

        $this->assertSame(0, $this->conn->table('recepciones_provisorias')->countAllResults());
    }

    public function testRechazaUnaSegundaRecepcionProvisoria(): void
    {
        $this->crearProvisoria('2025-10-23', 180);
        $this->cambiarEstado($this->estados['EN PLAZO DE CONSERVACIÓN']);

        $resultado = $this->postComoAdmin('/obras/recepciones/provisoria', [
            'obra_id'                 => $this->obraId,
            'fecha_recepcion'         => '2025-10-24',
            'plazo_conservacion_dias' => '180',
        ]);

        $resultado->assertRedirectTo('/obras/recepciones/' . $this->obraId);

        $this->assertSame(1, $this->conn->table('recepciones_provisorias')->countAllResults());
    }

    public function testRechazaLaProvisoriaEnEstadoNeutralizada(): void
    {
        $this->cambiarEstado($this->estados['NEUTRALIZADA']);

        $resultado = $this->postComoAdmin('/obras/recepciones/provisoria', [
            'obra_id'                 => $this->obraId,
            'fecha_recepcion'         => '2025-10-23',
            'plazo_conservacion_dias' => '180',
        ]);

        $resultado->assertRedirectTo('/obras/recepciones/' . $this->obraId);

        $this->assertSame(0, $this->conn->table('recepciones_provisorias')->countAllResults());
        $this->assertSame($this->estados['NEUTRALIZADA'], $this->estadoActual());
    }

    public function testUnaObraHistoricaEnPlazoCompletaLaProvisoriaSinCambiarDeEstado(): void
    {
        $this->cambiarEstado($this->estados['EN PLAZO DE CONSERVACIÓN']);

        $this->postComoAdmin('/obras/recepciones/provisoria', [
            'obra_id'                 => $this->obraId,
            'fecha_recepcion'         => '2026-06-01',
            'plazo_conservacion_dias' => '180',
        ]);

        $this->assertSame(1, $this->conn->table('recepciones_provisorias')->countAllResults());
        $this->assertSame($this->estados['EN PLAZO DE CONSERVACIÓN'], $this->estadoActual());
    }

    public function testUnaObraHistoricaFinalizadaCompletaLaProvisoriaSinRetroceder(): void
    {
        $this->cambiarEstado($this->estados['FINALIZADA']);

        $this->postComoAdmin('/obras/recepciones/provisoria', [
            'obra_id'                 => $this->obraId,
            'fecha_recepcion'         => '2026-06-01',
            'plazo_conservacion_dias' => '180',
        ]);

        $this->assertSame(1, $this->conn->table('recepciones_provisorias')->countAllResults());
        $this->assertSame($this->estados['FINALIZADA'], $this->estadoActual());
    }

    public function testCorrigeLaProvisoriaYRecalculaLaFechaPrevista(): void
    {
        $this->crearProvisoria('2026-01-10', 180);
        $this->cambiarEstado($this->estados['EN PLAZO DE CONSERVACIÓN']);

        $resultado = $this->postComoAdmin('/obras/recepciones/provisoria/actualizar', [
            'obra_id'                 => $this->obraId,
            'fecha_recepcion'         => '2026-01-15',
            'plazo_conservacion_dias' => '90',
            'observaciones'           => 'Corrección de fecha',
        ]);

        $resultado->assertRedirectTo('/obras/recepciones/' . $this->obraId);

        $fila = $this->conn->table('recepciones_provisorias')->get()->getRow();

        $this->assertSame('2026-01-15', $fila->fecha_recepcion);
        $this->assertSame(90, (int) $fila->plazo_conservacion_dias);
        $this->assertSame('2026-04-15', $fila->fecha_prevista_definitiva);
        $this->assertSame('Corrección de fecha', $fila->observaciones);
        $this->assertSame($this->estados['EN PLAZO DE CONSERVACIÓN'], $this->estadoActual());
    }

    public function testNoCorrigeLaProvisoriaDespuesDeLaDefinitiva(): void
    {
        $this->crearProvisoria('2025-01-01', 180);
        $this->crearDefinitiva('2025-06-30');
        $this->cambiarEstado($this->estados['FINALIZADA']);

        $resultado = $this->postComoAdmin('/obras/recepciones/provisoria/actualizar', [
            'obra_id'                 => $this->obraId,
            'fecha_recepcion'         => '2025-01-02',
            'plazo_conservacion_dias' => '180',
        ]);

        $resultado->assertRedirectTo('/obras/recepciones/' . $this->obraId);

        $fila = $this->conn->table('recepciones_provisorias')->get()->getRow();

        $this->assertSame('2025-01-01', $fila->fecha_recepcion);
    }

    public function testRechazaLaDefinitivaAntesDeLaFechaPrevista(): void
    {
        $this->crearProvisoria('2025-01-01', 180);
        $this->cambiarEstado($this->estados['EN PLAZO DE CONSERVACIÓN']);

        $resultado = $this->postComoAdmin('/obras/recepciones/definitiva', [
            'obra_id'         => $this->obraId,
            'fecha_recepcion' => '2025-06-29',
        ]);

        $resultado->assertRedirectTo('/obras/recepciones/' . $this->obraId);

        $this->assertSame(0, $this->conn->table('recepciones_definitivas')->countAllResults());
        $this->assertSame($this->estados['EN PLAZO DE CONSERVACIÓN'], $this->estadoActual());
    }

    public function testRegistraLaDefinitivaEnLaFechaPrevistaYFinalizaLaObra(): void
    {
        $this->crearProvisoria('2025-01-01', 180);
        $this->cambiarEstado($this->estados['EN PLAZO DE CONSERVACIÓN']);

        $resultado = $this->postComoAdmin('/obras/recepciones/definitiva', [
            'obra_id'         => $this->obraId,
            'fecha_recepcion' => '2025-06-30',
            'observaciones'   => 'Acta definitiva',
        ]);

        $resultado->assertRedirectTo('/obras/recepciones/' . $this->obraId);

        $fila = $this->conn->table('recepciones_definitivas')->get()->getRow();

        $this->assertNotNull($fila);
        $this->assertSame('2025-06-30', $fila->fecha_recepcion);
        $this->assertSame('Acta definitiva', $fila->observaciones);
        $this->assertSame($this->estados['FINALIZADA'], $this->estadoActual());
    }

    public function testRechazaLaDefinitivaSinProvisoria(): void
    {
        $this->cambiarEstado($this->estados['EN PLAZO DE CONSERVACIÓN']);

        $resultado = $this->postComoAdmin('/obras/recepciones/definitiva', [
            'obra_id'         => $this->obraId,
            'fecha_recepcion' => '2025-06-30',
        ]);

        $resultado->assertRedirectTo('/obras/recepciones/' . $this->obraId);

        $this->assertSame(0, $this->conn->table('recepciones_definitivas')->countAllResults());
    }

    public function testRechazaLaDefinitivaConObraEnEjecucion(): void
    {
        $this->crearProvisoria('2025-01-01', 180);

        $resultado = $this->postComoAdmin('/obras/recepciones/definitiva', [
            'obra_id'         => $this->obraId,
            'fecha_recepcion' => '2025-06-30',
        ]);

        $resultado->assertRedirectTo('/obras/recepciones/' . $this->obraId);

        $this->assertSame(0, $this->conn->table('recepciones_definitivas')->countAllResults());
        $this->assertSame($this->estados['EN EJECUCIÓN'], $this->estadoActual());
    }

    public function testUnaObraHistoricaFinalizadaCompletaLaDefinitivaSinCambiarDeEstado(): void
    {
        $this->crearProvisoria('2025-01-01', 180);
        $this->cambiarEstado($this->estados['FINALIZADA']);

        $this->postComoAdmin('/obras/recepciones/definitiva', [
            'obra_id'         => $this->obraId,
            'fecha_recepcion' => '2025-06-30',
        ]);

        $this->assertSame(1, $this->conn->table('recepciones_definitivas')->countAllResults());
        $this->assertSame($this->estados['FINALIZADA'], $this->estadoActual());
    }

    public function testRechazaUnaDefinitivaFutura(): void
    {
        $this->crearProvisoria(date('Y-m-d', strtotime('-200 days')), 180);
        $this->cambiarEstado($this->estados['EN PLAZO DE CONSERVACIÓN']);

        $resultado = $this->postComoAdmin('/obras/recepciones/definitiva', [
            'obra_id'         => $this->obraId,
            'fecha_recepcion' => date('Y-m-d', strtotime('+1 day')),
        ]);

        $resultado->assertRedirectTo('/obras/recepciones/' . $this->obraId);

        $this->assertSame(0, $this->conn->table('recepciones_definitivas')->countAllResults());
    }

    public function testLaPantallaDeRecepcionesSeRenderizaParaConsulta(): void
    {
        $resultado = $this->withSession([
            'logged_in' => true,
            'activo'    => true,
            'user_id'   => 2,
            'username'  => 'CONSULTA_PRUEBA',
            'user_name' => 'CONSULTA PRUEBA',
            'roles'     => ['CONSULTA'],
        ])->get('/obras/recepciones/' . $this->obraId);

        $resultado->assertStatus(200);
        $resultado->assertSee('Recepción provisoria');
        $resultado->assertSee('Recepción definitiva');
    }

    private function postComoAdmin(string $ruta, array $datos)
    {
        $token = csrf_hash();

        return $this->withSession([
            'logged_in' => true,
            'activo'    => true,
            'user_id'   => 1,
            'username'  => 'ADMIN_PRUEBA',
            'user_name' => 'ADMIN PRUEBA',
            'roles'     => ['ADMINISTRADOR'],
        ])->post($ruta, array_merge([
            'csrf_test_name' => $token,
        ], $datos));
    }

    private function crearObra(int $estadoId): int
    {
        $ahora = date('Y-m-d H:i:s');

        $this->conn->table('obras')->insert([
            'codigo'                   => 'OBR-000001',
            'expediente_municipal'     => 'EXP-001',
            'nombre'                   => 'OBRA DE PRUEBA',
            'estado_obra_id'           => $estadoId,
            'fecha_inicio'             => '2026-01-05',
            'plazo_original_valor'     => 120,
            'plazo_original_unidad'    => 'DIAS',
            'plazo_original_dias'      => 120,
            'plazo_inicial_confirmado' => 1,
            'plazo_conservacion_dias'  => 180,
            'created_at'               => $ahora,
            'updated_at'               => $ahora,
        ]);

        return (int) $this->conn->insertID();
    }

    private function crearProvisoria(string $fecha, int $plazo): void
    {
        $ahora = date('Y-m-d H:i:s');

        $this->conn->table('recepciones_provisorias')->insert([
            'obra_id'                    => $this->obraId,
            'fecha_recepcion'            => $fecha,
            'plazo_conservacion_dias'    => $plazo,
            'fecha_prevista_definitiva'  => PlazoObra::fechaPrevistaDefinitiva($fecha, $plazo),
            'created_at'                 => $ahora,
            'updated_at'                 => $ahora,
        ]);
    }

    private function crearDefinitiva(string $fecha): void
    {
        $ahora = date('Y-m-d H:i:s');

        $this->conn->table('recepciones_definitivas')->insert([
            'obra_id'         => $this->obraId,
            'fecha_recepcion' => $fecha,
            'created_at'      => $ahora,
            'updated_at'      => $ahora,
        ]);
    }

    private function cambiarEstado(int $estadoId): void
    {
        $this->conn->table('obras')->where('id', $this->obraId)->update([
            'estado_obra_id' => $estadoId,
        ]);
    }

    private function estadoActual(): int
    {
        return (int) $this->conn->table('obras')
            ->where('id', $this->obraId)
            ->get()
            ->getRow()
            ->estado_obra_id;
    }

    private function tabla(string $nombre): string
    {
        return $this->conn->prefixTable($nombre);
    }
}
