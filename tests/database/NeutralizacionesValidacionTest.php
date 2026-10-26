<?php

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Validación en servidor de neutralizaciones y transiciones de estado
 * (BLOQUE 5): D1 (estado solo por el ciclo de neutralización), D2 (sin
 * períodos solapados) y D3 (atomicidad ante fallo lógico post-escritura).
 *
 * Usa la conexión `tests` (SQLite en memoria) con un esquema mínimo que
 * reproduce —cuando aplica— la columna generada `abierta` y su índice
 * único, igual que MySQL.
 *
 * @internal
 */
final class NeutralizacionesValidacionTest extends CIUnitTestCase
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
            'neutralizaciones',
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
            plazo_inicial_confirmado INTEGER NOT NULL DEFAULT 0,
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

        $this->conn->query('CREATE TABLE ' . $this->tabla('neutralizaciones') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            obra_id INTEGER NOT NULL,
            numero INTEGER NOT NULL,
            fecha_inicio DATE NOT NULL,
            fecha_reinicio DATE NULL,
            documento_id INTEGER NULL,
            observaciones TEXT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            abierta INTEGER GENERATED ALWAYS AS (CASE WHEN fecha_reinicio IS NULL THEN 1 ELSE NULL END) STORED
        )');

        $this->conn->query(
            'CREATE UNIQUE INDEX uq_neutralizaciones_abierta ON '
            . $this->tabla('neutralizaciones')
            . ' (obra_id, abierta)'
        );

        foreach (['PREVIO INICIO', 'EN EJECUCIÓN', 'NEUTRALIZADA', 'EN PLAZO DE CONSERVACIÓN', 'FINALIZADA'] as $estado) {
            $this->conn->table('estados_obra')->insert(['estado' => $estado, 'activo' => 1]);
            $this->estados[$estado] = (int) $this->conn->insertID();
        }

        $this->obraId = $this->crearObra($this->estados['EN EJECUCIÓN']);
    }

    protected function tearDown(): void
    {
        foreach ([
            'neutralizaciones',
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

    // ------------------------------------------------------------------
    // D1 — la edición general no elude el ciclo de neutralización
    // ------------------------------------------------------------------

    public function testLaEdicionNoPuedeForzarNeutralizadaSinPeriodoAbierto(): void
    {
        $resultado = $this->postComoAdmin('/obras/actualizar', [
            'obra_id'             => $this->obraId,
            'expediente_municipal' => 'EXP-001',
            'nombre'              => 'Obra de prueba',
            'estado_obra_id'      => $this->estados['NEUTRALIZADA'],
        ]);

        $resultado->assertRedirectTo('/admin/dashboard');

        $this->assertSame(
            $this->estados['EN EJECUCIÓN'],
            $this->estadoActual(),
            'El estado no debe cambiar a NEUTRALIZADA sin un período abierto.'
        );
    }

    public function testLaEdicionNoPuedeSacarDeNeutralizadaConPeriodoAbierto(): void
    {
        $this->cambiarEstadoObra($this->estados['NEUTRALIZADA']);
        $this->crearPeriodoAbierto('2026-03-01');

        $resultado = $this->postComoAdmin('/obras/actualizar', [
            'obra_id'             => $this->obraId,
            'expediente_municipal' => 'EXP-001',
            'nombre'              => 'Obra de prueba',
            'estado_obra_id'      => $this->estados['EN EJECUCIÓN'],
        ]);

        $resultado->assertRedirectTo('/admin/dashboard');

        $this->assertSame(
            $this->estados['NEUTRALIZADA'],
            $this->estadoActual(),
            'No debe poder abandonarse NEUTRALIZADA con un período abierto.'
        );
    }

    public function testLaEdicionConservaNeutralizadaSiHayPeriodoAbierto(): void
    {
        $this->cambiarEstadoObra($this->estados['NEUTRALIZADA']);
        $this->crearPeriodoAbierto('2026-03-01');

        $resultado = $this->postComoAdmin('/obras/actualizar', [
            'obra_id'             => $this->obraId,
            'expediente_municipal' => 'EXP-001',
            'nombre'              => 'Obra renombrada',
            'estado_obra_id'      => $this->estados['NEUTRALIZADA'],
        ]);

        $resultado->assertRedirectTo('/admin/dashboard');

        $obra = $this->conn->table('obras')->where('id', $this->obraId)->get()->getRow();

        $this->assertSame('OBRA RENOMBRADA', $obra->nombre);
        $this->assertSame($this->estados['NEUTRALIZADA'], (int) $obra->estado_obra_id);
    }

    public function testLaEdicionPermiteOtrosEstadosSinPeriodoAbierto(): void
    {
        $resultado = $this->postComoAdmin('/obras/actualizar', [
            'obra_id'             => $this->obraId,
            'expediente_municipal' => 'EXP-001',
            'nombre'              => 'Obra previa al inicio',
            'estado_obra_id'      => $this->estados['PREVIO INICIO'],
        ]);

        $resultado->assertRedirectTo('/admin/dashboard');

        $this->assertSame($this->estados['PREVIO INICIO'], $this->estadoActual());
    }

    // ------------------------------------------------------------------
    // D2 — no se admiten períodos de neutralización solapados
    // ------------------------------------------------------------------

    public function testLaNeutralizacionNoPuedeSolaparseConElUltimoPeriodoCerrado(): void
    {
        $this->confirmarPlazo('2026-02-02');
        $this->crearPeriodoCerrado('2026-03-01', '2026-06-30');

        $resultado = $this->postComoAdmin('/obras/neutralizaciones/crear', [
            'obra_id'      => $this->obraId,
            'fecha_inicio' => '2026-05-01',
        ]);

        $resultado->assertRedirectTo('/obras/neutralizaciones/' . $this->obraId);

        $this->assertSame(1, $this->conn->table('neutralizaciones')->countAllResults());
        $this->assertSame($this->estados['EN EJECUCIÓN'], $this->estadoActual());
    }

    public function testLaNeutralizacionConsecutivaSeRegistra(): void
    {
        $this->confirmarPlazo('2026-02-02');
        $this->crearPeriodoCerrado('2026-03-01', '2026-06-30');

        $resultado = $this->postComoAdmin('/obras/neutralizaciones/crear', [
            'obra_id'      => $this->obraId,
            'fecha_inicio' => '2026-07-01',
        ]);

        $resultado->assertRedirectTo('/obras/neutralizaciones/' . $this->obraId);

        $this->assertSame(2, $this->conn->table('neutralizaciones')->countAllResults());

        $abierta = $this->conn->table('neutralizaciones')->where('fecha_reinicio', null)->get()->getRow();

        $this->assertNotNull($abierta);
        $this->assertSame('2026-07-01', $abierta->fecha_inicio);
        $this->assertSame($this->estados['NEUTRALIZADA'], $this->estadoActual());
    }

    public function testSeConsideraElUltimoReinicioEntreVariosPeriodos(): void
    {
        $this->confirmarPlazo('2026-02-02');
        $this->crearPeriodoCerrado('2026-03-01', '2026-04-01');
        $this->crearPeriodoCerrado('2026-05-01', '2026-06-30');

        $resultado = $this->postComoAdmin('/obras/neutralizaciones/crear', [
            'obra_id'      => $this->obraId,
            'fecha_inicio' => '2026-06-01',
        ]);

        $resultado->assertRedirectTo('/obras/neutralizaciones/' . $this->obraId);

        $this->assertSame(2, $this->conn->table('neutralizaciones')->countAllResults());
        $this->assertSame($this->estados['EN EJECUCIÓN'], $this->estadoActual());
    }

    public function testUnaFechaBastantePosteriorAlUltimoReinicioSeRegistra(): void
    {
        $this->confirmarPlazo('2026-02-02');
        $this->crearPeriodoCerrado('2026-03-01', '2026-06-30');

        $resultado = $this->postComoAdmin('/obras/neutralizaciones/crear', [
            'obra_id'      => $this->obraId,
            'fecha_inicio' => '2026-06-30',
        ]);

        $resultado->assertRedirectTo('/obras/neutralizaciones/' . $this->obraId);

        $this->assertSame(2, $this->conn->table('neutralizaciones')->countAllResults());
        $this->assertSame($this->estados['NEUTRALIZADA'], $this->estadoActual());
    }

    // ------------------------------------------------------------------
    // D3 — atomicidad: un fallo lógico posterior revierte toda la escritura
    // ------------------------------------------------------------------

    public function testUnFalloAlCambiarDeEstadoNoDejaNeutralizacionParcial(): void
    {
        $this->borrarEstado('NEUTRALIZADA');
        $this->confirmarPlazo('2026-02-02');

        $resultado = $this->postComoAdmin('/obras/neutralizaciones/crear', [
            'obra_id'      => $this->obraId,
            'fecha_inicio' => '2026-03-01',
        ]);

        $resultado->assertRedirectTo('/obras/neutralizaciones/' . $this->obraId);

        $this->assertSame(
            0,
            $this->conn->table('neutralizaciones')->countAllResults(),
            'La neutralización insertada no debe persistir si falla el cambio de estado.'
        );
        $this->assertSame($this->estados['EN EJECUCIÓN'], $this->estadoActual());
    }

    public function testUnFalloAlCambiarDeEstadoNoDejaReinicioParcial(): void
    {
        $this->borrarEstado('EN EJECUCIÓN');
        $this->cambiarEstadoObra($this->estados['NEUTRALIZADA']);
        $this->crearPeriodoAbierto('2026-03-01');

        $resultado = $this->postComoAdmin('/obras/neutralizaciones/reiniciar', [
            'obra_id'        => $this->obraId,
            'fecha_reinicio' => '2026-03-11',
        ]);

        $resultado->assertRedirectTo('/obras/neutralizaciones/' . $this->obraId);

        $fila = $this->conn->table('neutralizaciones')->get()->getRow();

        $this->assertNotNull($fila);
        $this->assertNull(
            $fila->fecha_reinicio,
            'La fecha de reinicio no debe persistir si falla el cambio de estado.'
        );
        $this->assertSame($this->estados['NEUTRALIZADA'], $this->estadoActual());
    }

    // ------------------------------------------------------------------
    // Utilidades
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $datos
     */
    private function postComoAdmin(string $url, array $datos)
    {
        $token = csrf_hash();

        return $this->withSession([
            'logged_in' => true,
            'activo'    => true,
            'user_id'   => 1,
            'username'  => 'ADMIN_PRUEBA',
            'user_name' => 'ADMIN PRUEBA',
            'roles'     => ['ADMINISTRADOR'],
        ])->post($url, $datos + ['csrf_test_name' => $token]);
    }

    private function crearObra(int $estadoId): int
    {
        $ahora = date('Y-m-d H:i:s');

        $this->conn->table('obras')->insert([
            'codigo'                  => 'OBR-000001',
            'expediente_municipal'    => 'EXP-001',
            'nombre'                  => 'OBRA DE PRUEBA',
            'estado_obra_id'          => $estadoId,
            'plazo_inicial_confirmado' => 0,
            'created_at'              => $ahora,
            'updated_at'              => $ahora,
        ]);

        return (int) $this->conn->insertID();
    }

    private function confirmarPlazo(string $fechaInicio): void
    {
        $this->conn->table('obras')->where('id', $this->obraId)->update([
            'fecha_inicio'             => $fechaInicio,
            'plazo_inicial_confirmado' => 1,
        ]);
    }

    private function cambiarEstadoObra(int $estadoId): void
    {
        $this->conn->table('obras')->where('id', $this->obraId)->update([
            'estado_obra_id' => $estadoId,
        ]);
    }

    private function crearPeriodoAbierto(string $fechaInicio): void
    {
        $this->insertarPeriodo(1, $fechaInicio, null);
    }

    private function crearPeriodoCerrado(string $fechaInicio, string $fechaReinicio): void
    {
        $numero = $this->conn->table('neutralizaciones')->countAllResults() + 1;

        $this->insertarPeriodo($numero, $fechaInicio, $fechaReinicio);
    }

    private function insertarPeriodo(int $numero, string $fechaInicio, ?string $fechaReinicio): void
    {
        $ahora = date('Y-m-d H:i:s');

        $this->conn->table('neutralizaciones')->insert([
            'obra_id'        => $this->obraId,
            'numero'         => $numero,
            'fecha_inicio'   => $fechaInicio,
            'fecha_reinicio' => $fechaReinicio,
            'created_at'     => $ahora,
            'updated_at'     => $ahora,
        ]);
    }

    private function borrarEstado(string $nombre): void
    {
        $this->conn->table('estados_obra')->where('id', $this->estados[$nombre])->delete();
    }

    private function estadoActual(): int
    {
        return (int) $this->conn->table('obras')->where('id', $this->obraId)->get()->getRow()->estado_obra_id;
    }

    private function tabla(string $nombre): string
    {
        return $this->conn->prefixTable($nombre);
    }
}
