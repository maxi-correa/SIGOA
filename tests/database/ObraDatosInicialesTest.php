<?php

use App\Database\Migrations\AddPlazoInicialConfirmadoToObras;
use App\Models\ObraModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Config as DatabaseConfig;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Confirmación e inmutabilidad de los datos iniciales de plazo de una
 * obra, y backfill de la marca sobre el parque de obras existente.
 *
 * @internal
 */
final class ObraDatosInicialesTest extends CIUnitTestCase
{
    private BaseConnection $conn;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conn = db_connect();

        $this->conn->query('DROP TABLE IF EXISTS ' . $this->tabla('obras'));

        $this->conn->query('CREATE TABLE ' . $this->tabla('obras') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            codigo VARCHAR(20) NOT NULL,
            expediente_municipal VARCHAR(30) NOT NULL,
            nombre VARCHAR(255) NOT NULL,
            estado_obra_id INTEGER NOT NULL,
            expediente_contable VARCHAR(50) NULL,
            fecha_inicio DATE NULL,
            plazo_original_valor INTEGER NULL,
            plazo_original_unidad VARCHAR(10) NULL,
            plazo_original_dias INTEGER NULL,
            plazo_inicial_confirmado INTEGER NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )');
    }

    protected function tearDown(): void
    {
        $this->conn->query('DROP TABLE IF EXISTS ' . $this->tabla('obras'));

        parent::tearDown();
    }

    public function testActualizarFichaGuardaDatosInicialesSinConfirmar(): void
    {
        $this->insertarObra(2);

        $guardado = (new ObraModel())->actualizarFicha(2, $this->datosIniciales());

        $this->assertTrue($guardado);

        $obra = (new ObraModel())->find(2);

        $this->assertSame(0, (int) $obra->plazo_inicial_confirmado);
        $this->assertSame('2026-02-02', $obra->fecha_inicio);
        $this->assertSame(180, (int) $obra->plazo_original_dias);
    }

    public function testConfirmarDatosInicialesMarcaLaConfirmacionYConservaElEstado(): void
    {
        $this->insertarObra(2, ['estado_obra_id' => 3]);

        $confirmado = (new ObraModel())->confirmarDatosIniciales(2, $this->datosIniciales());

        $this->assertTrue($confirmado);

        $obra = (new ObraModel())->find(2);

        $this->assertSame(1, (int) $obra->plazo_inicial_confirmado);
        $this->assertSame('2026-02-02', $obra->fecha_inicio);
        $this->assertSame(180, (int) $obra->plazo_original_dias);
        $this->assertSame(3, (int) $obra->estado_obra_id, 'La confirmación no debe alterar el estado de la obra.');
    }

    public function testNoSePuedeConfirmarSinFechaYPlazo(): void
    {
        $this->insertarObra(2);

        $confirmado = (new ObraModel())->confirmarDatosIniciales(2, [
            'expediente_contable'   => null,
            'fecha_inicio'          => null,
            'plazo_original_valor'  => null,
            'plazo_original_unidad' => null,
            'plazo_original_dias'   => null,
        ]);

        $this->assertFalse($confirmado);
        $this->assertSame(0, (int) (new ObraModel())->find(2)->plazo_inicial_confirmado);
    }

    public function testNoSePuedeConfirmarDosVeces(): void
    {
        $this->insertarObra(2);

        $modelo = new ObraModel();
        $modelo->confirmarDatosIniciales(2, $this->datosIniciales());

        $segunda = $modelo->confirmarDatosIniciales(2, [
            'expediente_contable'   => null,
            'fecha_inicio'          => '2027-01-01',
            'plazo_original_valor'  => 30,
            'plazo_original_unidad' => 'DIAS',
            'plazo_original_dias'   => 30,
        ]);

        $this->assertFalse($segunda);

        $obra = $modelo->find(2);

        $this->assertSame('2026-02-02', $obra->fecha_inicio);
        $this->assertSame(180, (int) $obra->plazo_original_dias);
    }

    public function testActualizarFichaTrasConfirmarSoloEditaElExpedienteContable(): void
    {
        $this->insertarObra(2);

        $modelo = new ObraModel();
        $modelo->confirmarDatosIniciales(2, $this->datosIniciales());

        $guardado = $modelo->actualizarFicha(2, [
            'expediente_contable'   => 'EXP-NUEVO',
            'fecha_inicio'          => '2027-01-01',
            'plazo_original_valor'  => 30,
            'plazo_original_unidad' => 'DIAS',
            'plazo_original_dias'   => 30,
        ]);

        $this->assertTrue($guardado);

        $obra = $modelo->find(2);

        $this->assertSame('EXP-NUEVO', $obra->expediente_contable);
        $this->assertSame('2026-02-02', $obra->fecha_inicio, 'El plazo confirmado no debe modificarse.');
        $this->assertSame(180, (int) $obra->plazo_original_dias);
    }

    public function testUpdateDirectoNoModificaElPlazoConfirmado(): void
    {
        $this->insertarObra(2);

        $modelo = new ObraModel();
        $modelo->confirmarDatosIniciales(2, $this->datosIniciales());

        $this->assertFalse(
            $modelo->update(2, ['fecha_inicio' => '2020-01-01']),
            'El modelo debe rechazar la modificación de la fecha de inicio confirmada.'
        );

        $this->assertSame('2026-02-02', $modelo->find(2)->fecha_inicio);

        $this->assertTrue(
            $modelo->update(2, ['fecha_inicio' => '2026-02-02']),
            'Reescribir el mismo valor no constituye una modificación.'
        );
    }

    public function testBackfillConfirmaSoloObrasConDatosConsistentes(): void
    {
        $this->conn->query('DROP TABLE IF EXISTS ' . $this->tabla('obras'));
        $this->conn->query('CREATE TABLE ' . $this->tabla('obras') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            codigo VARCHAR(20) NOT NULL,
            expediente_municipal VARCHAR(30) NOT NULL,
            nombre VARCHAR(255) NOT NULL,
            estado_obra_id INTEGER NOT NULL,
            fecha_inicio DATE NULL,
            plazo_original_valor INTEGER NULL,
            plazo_original_unidad VARCHAR(10) NULL,
            plazo_original_dias INTEGER NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )');

        $ahora = '2026-10-08 00:00:00';

        $this->conn->table('obras')->insertBatch([
            ['id' => 1, 'codigo' => 'OBR-000001', 'expediente_municipal' => 'E1', 'nombre' => 'A', 'estado_obra_id' => 1, 'fecha_inicio' => '2026-02-02', 'plazo_original_valor' => 180, 'plazo_original_unidad' => 'DIAS', 'plazo_original_dias' => 180, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['id' => 2, 'codigo' => 'OBR-000002', 'expediente_municipal' => 'E2', 'nombre' => 'B', 'estado_obra_id' => 1, 'fecha_inicio' => '2026-02-02', 'plazo_original_valor' => 6, 'plazo_original_unidad' => 'MES', 'plazo_original_dias' => 180, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['id' => 3, 'codigo' => 'OBR-000003', 'expediente_municipal' => 'E3', 'nombre' => 'C', 'estado_obra_id' => 1, 'fecha_inicio' => '2026-02-02', 'plazo_original_valor' => 180, 'plazo_original_unidad' => 'DIAS', 'plazo_original_dias' => 999, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['id' => 4, 'codigo' => 'OBR-000004', 'expediente_municipal' => 'E4', 'nombre' => 'D', 'estado_obra_id' => 1, 'fecha_inicio' => null, 'plazo_original_valor' => 180, 'plazo_original_unidad' => 'DIAS', 'plazo_original_dias' => 180, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['id' => 5, 'codigo' => 'OBR-000005', 'expediente_municipal' => 'E5', 'nombre' => 'E', 'estado_obra_id' => 1, 'fecha_inicio' => '2026-02-02', 'plazo_original_valor' => 0, 'plazo_original_unidad' => 'DIAS', 'plazo_original_dias' => 0, 'created_at' => $ahora, 'updated_at' => $ahora],
        ]);

        require_once APPPATH . 'Database/Migrations/2026-10-08-120000_AddPlazoInicialConfirmadoToObras.php';

        (new AddPlazoInicialConfirmadoToObras(DatabaseConfig::forge()))->up();

        $this->assertSame(1, $this->flag(1), 'Obra con fecha y plazo consistentes debe quedar confirmada.');
        $this->assertSame(1, $this->flag(2), 'Obra con plazo en meses consistente debe quedar confirmada.');
        $this->assertSame(0, $this->flag(3), 'Días inconsistentes con valor × unidad no deben confirmarse.');
        $this->assertSame(0, $this->flag(4), 'Obra sin fecha de inicio no debe confirmarse.');
        $this->assertSame(0, $this->flag(5), 'Plazo con valor cero no debe confirmarse.');
    }

    private function flag(int $id): int
    {
        $fila = $this->conn->table('obras')
            ->select('plazo_inicial_confirmado')
            ->where('id', $id)
            ->get()
            ->getRow();

        return (int) $fila->plazo_inicial_confirmado;
    }

    /**
     * @return array<string, mixed>
     */
    private function datosIniciales(): array
    {
        return [
            'expediente_contable'   => null,
            'fecha_inicio'          => '2026-02-02',
            'plazo_original_valor'  => 180,
            'plazo_original_unidad' => 'DIAS',
            'plazo_original_dias'   => 180,
        ];
    }

    /**
     * @param array<string, mixed> $extras
     */
    private function insertarObra(int $id, array $extras = []): void
    {
        $ahora = '2026-10-08 00:00:00';

        $this->conn->table('obras')->insert(array_merge([
            'id'                    => $id,
            'codigo'                => 'OBR-00000' . $id,
            'expediente_municipal'  => 'EXP-' . $id,
            'nombre'                => 'OBRA ' . $id,
            'estado_obra_id'        => 1,
            'expediente_contable'   => null,
            'fecha_inicio'          => null,
            'plazo_original_valor'  => null,
            'plazo_original_unidad' => null,
            'plazo_original_dias'   => null,
            'created_at'            => $ahora,
            'updated_at'            => $ahora,
        ], $extras));
    }

    private function tabla(string $nombre): string
    {
        return $this->conn->prefixTable($nombre);
    }
}