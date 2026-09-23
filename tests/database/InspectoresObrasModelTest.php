<?php

use App\Models\InspectoresObrasModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Asignaciones vigentes del inspector y su uso en el dashboard.
 *
 * La suite usa la conexión `tests` (SQLite en memoria). Las migraciones de la
 * aplicación no son compatibles con SQLite, por lo que aquí se crea el esquema
 * mínimo necesario en cada test. No se usa DatabaseTestTrait para no depender
 * de migraciones específicas del grupo de pruebas.
 *
 * @internal
 */
final class InspectoresObrasModelTest extends CIUnitTestCase
{
    private BaseConnection $conn;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conn = db_connect();

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('estados_obra') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            estado VARCHAR(60) NOT NULL
        )');

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('tipos_licitacion') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tipo_licitacion VARCHAR(80) NOT NULL
        )');

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('obras') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            codigo VARCHAR(20) NOT NULL,
            nombre VARCHAR(200) NOT NULL,
            expediente_municipal VARCHAR(60) NULL,
            numero_licitacion VARCHAR(30) NULL,
            tipo_licitacion_id INTEGER NULL,
            estado_obra_id INTEGER NOT NULL
        )');

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('inspectores_obras') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            obra_id INTEGER NOT NULL,
            usuario_id INTEGER NOT NULL,
            fecha_inicio DATE NOT NULL,
            fecha_fin DATE NULL,
            created_at DATETIME NULL
        )');

        $this->limpiarTablas();

        $this->conn->table('estados_obra')->insert([
            'id'     => 1,
            'estado' => 'EN EJECUCIÓN',
        ]);

        $this->conn->table('estados_obra')->insert([
            'id'     => 2,
            'estado' => 'PREVIO INICIO',
        ]);

        $this->conn->table('tipos_licitacion')->insert([
            'id'             => 1,
            'tipo_licitacion' => 'LICITACIÓN PÚBLICA',
        ]);
    }

    protected function tearDown(): void
    {
        $this->limpiarTablas();

        parent::tearDown();
    }

    private function tabla(string $nombre): string
    {
        return $this->conn->prefixTable($nombre);
    }

    private function limpiarTablas(): void
    {
        foreach (['inspectores_obras', 'obras', 'tipos_licitacion', 'estados_obra'] as $tabla) {
            $this->conn->query('DELETE FROM ' . $this->tabla($tabla));
        }
    }

    private function crearObra(
        int $id,
        string $nombre,
        int $estadoId,
        ?int $tipoLicitacionId = 1,
        ?string $expediente = 'EX-' . null,
        ?string $numeroLicitacion = null
    ): void {
        $this->conn->table('obras')->insert([
            'id'                   => $id,
            'codigo'               => 'OBR-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT),
            'nombre'               => $nombre,
            'expediente_municipal' => $expediente,
            'numero_licitacion'    => $numeroLicitacion,
            'tipo_licitacion_id'   => $tipoLicitacionId,
            'estado_obra_id'       => $estadoId,
        ]);
    }

    private function crearAsignacion(
        int $obraId,
        int $usuarioId,
        string $fechaInicio,
        ?string $fechaFin
    ): void {
        $this->conn->table('inspectores_obras')->insert([
            'obra_id'     => $obraId,
            'usuario_id'  => $usuarioId,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin'   => $fechaFin,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    public function testListarVigentesSoloDevuelveObrasAsignadasDeFormaVigente(): void
    {
        $this->crearObra(1, 'CENTRO DE SALUD NORTE', 1);
        $this->crearObra(2, 'PLAZA CENTRAL', 2);

        $this->crearAsignacion(1, 10, '2026-03-01', null);
        $this->crearAsignacion(2, 20, '2026-03-01', null);

        $obras = (new InspectoresObrasModel())->listarVigentesConObra(10);

        $this->assertCount(1, $obras);
        $this->assertSame('CENTRO DE SALUD NORTE', $obras[0]->nombre);
        $this->assertSame('EN EJECUCIÓN', $obras[0]->estado_nombre);
        $this->assertSame('LICITACIÓN PÚBLICA', $obras[0]->tipo_licitacion_nombre);
    }

    public function testListarVigentesExcluyeAsignacionesCerradas(): void
    {
        $this->crearObra(1, 'EDIFICIO MUNICIPAL', 1);
        $this->crearObra(2, 'POLIDEPORTIVO', 2);

        $this->crearAsignacion(1, 10, '2025-01-01', '2025-12-31');
        $this->crearAsignacion(2, 10, '2026-02-01', null);

        $obras = (new InspectoresObrasModel())->listarVigentesConObra(10);

        $this->assertCount(1, $obras);
        $this->assertSame('POLIDEPORTIVO', $obras[0]->nombre);
    }

    public function testListarVigentesResuelveSinDatosOpcionales(): void
    {
        $this->crearObra(1, 'PLAYÓN DEPORTIVO', 1, null, 'EX-88', null);

        $this->crearAsignacion(1, 10, '2026-03-01', null);

        $obras = (new InspectoresObrasModel())->listarVigentesConObra(10);

        $this->assertCount(1, $obras);
        $this->assertNull($obras[0]->tipo_licitacion_nombre);
        $this->assertNull($obras[0]->numero_licitacion);
    }

    public function testEsVigenteReconoceAsignacionAbierta(): void
    {
        $this->crearObra(1, 'HOSPITAL MUNICIPAL', 1);
        $this->crearAsignacion(1, 10, '2026-03-01', null);

        $this->assertTrue((new InspectoresObrasModel())->esVigente(1, 10));
    }

    public function testEsVigenteNiegaAsignacionCerrada(): void
    {
        $this->crearObra(1, 'HOSPITAL MUNICIPAL', 1);
        $this->crearAsignacion(1, 10, '2025-01-01', '2025-12-31');

        $this->assertFalse((new InspectoresObrasModel())->esVigente(1, 10));
    }

    public function testEsVigenteNiegaOtroInspector(): void
    {
        $this->crearObra(1, 'HOSPITAL MUNICIPAL', 1);
        $this->crearAsignacion(1, 10, '2026-03-01', null);

        $this->assertFalse((new InspectoresObrasModel())->esVigente(1, 20));
    }
}