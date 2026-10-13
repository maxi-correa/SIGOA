<?php

use App\Libraries\PlazoObra;
use App\Libraries\ResolucionObra;
use App\Models\NeutralizacionModel;
use App\Models\ResolucionModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Cálculo del plazo vigente a partir de las resoluciones almacenadas y
 * detección de la neutralización abierta.
 *
 * Construye su propio esquema SQLite, por lo que no depende de que las
 * migraciones de MySQL estén aplicadas.
 *
 * @internal
 */
final class ObraPlazoVigenteTest extends CIUnitTestCase
{
    private BaseConnection $conn;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conn = db_connect();

        $this->conn->query('DROP TABLE IF EXISTS ' . $this->tabla('resoluciones'));
        $this->conn->query('DROP TABLE IF EXISTS ' . $this->tabla('tipos_resolucion'));
        $this->conn->query('DROP TABLE IF EXISTS ' . $this->tabla('neutralizaciones'));

        $this->conn->query('CREATE TABLE ' . $this->tabla('tipos_resolucion') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tipo_resolucion VARCHAR(120) NOT NULL,
            activo INTEGER NOT NULL DEFAULT 1
        )');

        $this->conn->query('CREATE TABLE ' . $this->tabla('resoluciones') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            obra_id INTEGER NOT NULL,
            tipo_resolucion_id INTEGER NOT NULL,
            numero INTEGER NULL,
            anio INTEGER NULL,
            fecha_resolucion DATE NULL,
            monto DECIMAL(15,3) NULL,
            dias INTEGER NULL,
            observaciones TEXT NULL,
            confirmada INTEGER NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )');

        $this->conn->query('CREATE TABLE ' . $this->tabla('neutralizaciones') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            obra_id INTEGER NOT NULL,
            numero INTEGER NULL,
            fecha_inicio DATE NOT NULL,
            fecha_reinicio DATE NULL,
            observaciones TEXT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');

        $this->conn->table('tipos_resolucion')->insertBatch([
            ['id' => 1, 'tipo_resolucion' => ResolucionObra::TIPO_AMPLIACION_PLAZO, 'activo' => 1],
            ['id' => 2, 'tipo_resolucion' => ResolucionObra::TIPO_CUADRO_Y_AMPLIACION, 'activo' => 1],
            ['id' => 3, 'tipo_resolucion' => ResolucionObra::TIPO_ADJUDICACION, 'activo' => 1],
            ['id' => 4, 'tipo_resolucion' => ResolucionObra::TIPO_CUADRO_COMPARATIVO, 'activo' => 1],
            ['id' => 5, 'tipo_resolucion' => ResolucionObra::TIPO_OTRO, 'activo' => 1],
        ]);
    }

    protected function tearDown(): void
    {
        $this->conn->query('DROP TABLE IF EXISTS ' . $this->tabla('resoluciones'));
        $this->conn->query('DROP TABLE IF EXISTS ' . $this->tabla('tipos_resolucion'));
        $this->conn->query('DROP TABLE IF EXISTS ' . $this->tabla('neutralizaciones'));

        parent::tearDown();
    }

    public function testLasResolucionesAlmacenadasAlimentanElCalculo(): void
    {
        $modelo = new ResolucionModel();

        $this->crearConfirmada($modelo, 1, 1, 10, 2026, '2026-03-01', 30);
        $this->crearConfirmada($modelo, 1, 2, 11, 2026, '2026-04-01', 45);
        $this->crearConfirmada($modelo, 1, 3, 12, 2026, '2026-02-01', null);

        $modelo->crear(1, [
            'tipo_resolucion_id' => 1,
            'numero'             => 13,
            'anio'               => 2026,
            'fecha_resolucion'   => '2026-05-01',
            'monto'              => null,
            'dias'               => 99,
            'observaciones'      => null,
        ]);

        $resoluciones = $modelo->listarPorObra(1);
        $plazo        = PlazoObra::calcularVigente(180, $resoluciones);

        $this->assertSame(180 + 30 + 45, $plazo['vigente_dias']);
        $this->assertSame(75, $plazo['total_ampliacion_dias']);
        $this->assertCount(2, $plazo['ampliaciones']);
        $this->assertCount(1, $plazo['pendientes']);
    }

    public function testOtraObraNoAportaAlCalculo(): void
    {
        $modelo = new ResolucionModel();

        $this->crearConfirmada($modelo, 1, 1, 10, 2026, '2026-03-01', 30);
        $this->crearConfirmada($modelo, 2, 1, 20, 2026, '2026-03-01', 500);

        $plazo = PlazoObra::calcularVigente(180, $modelo->listarPorObra(1));

        $this->assertSame(210, $plazo['vigente_dias']);
    }

    public function testLaNeutralizacionAbiertaSeDetectaYBloqueaLaFecha(): void
    {
        $this->conn->table('neutralizaciones')->insert([
            'obra_id'         => 1,
            'numero'          => 1,
            'fecha_inicio'    => '2026-04-01',
            'fecha_reinicio'  => null,
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        $modelo = new NeutralizacionModel();

        $this->assertTrue($modelo->tieneAbierta(1));
        $this->assertFalse($modelo->tieneAbierta(2));

        $this->assertNull(
            PlazoObra::fechaFinalizacion('2026-02-02', 210, $modelo->tieneAbierta(1))
        );
    }

    public function testLaNeutralizacionReiniciadaNoBloqueaLaFecha(): void
    {
        $this->conn->table('neutralizaciones')->insert([
            'obra_id'         => 1,
            'numero'          => 1,
            'fecha_inicio'    => '2026-04-01',
            'fecha_reinicio'  => '2026-05-01',
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        $modelo = new NeutralizacionModel();

        $this->assertFalse($modelo->tieneAbierta(1));
        $this->assertSame(
            '2026-08-31',
            PlazoObra::fechaFinalizacion('2026-02-02', 210, $modelo->tieneAbierta(1))
        );
    }

    private function crearConfirmada(
        ResolucionModel $modelo,
        int $obraId,
        int $tipoId,
        int $numero,
        int $anio,
        string $fecha,
        ?int $dias
    ): void {
        $modelo->crear($obraId, [
            'tipo_resolucion_id' => $tipoId,
            'numero'             => $numero,
            'anio'               => $anio,
            'fecha_resolucion'   => $fecha,
            'monto'              => null,
            'dias'               => $dias,
            'observaciones'      => null,
        ]);

        $id = (int) $this->conn->insertID();

        $modelo->confirmar($id);
    }

    private function tabla(string $nombre): string
    {
        return $this->conn->prefixTable($nombre);
    }
}
