<?php

use App\Models\NeutralizacionModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Persistencia de neutralizaciones (BLOQUE 5): creación abierta,
 * reinicio, numeración por obra e inmutabilidad.
 *
 * Construye su propio esquema SQLite, por lo que no depende de que las
 * migraciones de MySQL estén aplicadas.
 *
 * @internal
 */
final class ObraNeutralizacionTest extends CIUnitTestCase
{
    private BaseConnection $conn;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conn = db_connect();

        $this->conn->query('DROP TABLE IF EXISTS ' . $this->tabla('neutralizaciones'));

        /*
         * Se reproduce la estructura de MySQL (migración
         * 2026-10-14-120000_AddNeutralizacionAbiertaUnica): una columna
         * generada `abierta` con índice único por obra. SQLite (3.40)
         * soporta columnas generadas STORED e índices únicos sobre ellas
         * con la misma semántica de NULLs que MySQL, de modo que la prueba
         * cubre la garantía "una sola neutralización abierta por obra".
         * Esto no reemplaza la verificación sobre el motor real.
         */
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
    }

    protected function tearDown(): void
    {
        $this->conn->query('DROP TABLE IF EXISTS ' . $this->tabla('neutralizaciones'));

        parent::tearDown();
    }

    public function testCrearDejaLaNeutralizacionAbiertaYAvanzaLaNumeracion(): void
    {
        $modelo = new NeutralizacionModel();

        $this->assertSame(1, $modelo->proximoNumero(1));

        $this->assertTrue($modelo->crear(1, [
            'numero'        => 1,
            'fecha_inicio'  => '2026-03-01',
            'observaciones' => 'Suspensión por lluvias',
        ]));

        $this->assertSame(2, $modelo->proximoNumero(1));

        $abierta = $modelo->abierta(1);

        $this->assertNotNull($abierta);
        $this->assertNull($abierta->fecha_reinicio);
        $this->assertSame('2026-03-01', $abierta->fecha_inicio);
        $this->assertTrue($modelo->tieneAbierta(1));
        $this->assertCount(1, $modelo->listarPorObra(1));
    }

    public function testReiniciarCierraLaNeutralizacionAbierta(): void
    {
        $modelo = new NeutralizacionModel();

        $modelo->crear(1, ['numero' => 1, 'fecha_inicio' => '2026-03-01']);
        $id = (int) $this->conn->insertID();

        $this->assertTrue($modelo->reiniciar($id, '2026-03-11'));

        $this->assertFalse($modelo->tieneAbierta(1));
        $this->assertSame('2026-03-11', $modelo->listarPorObra(1)[0]->fecha_reinicio);
    }

    public function testReiniciarDosVecesFalla(): void
    {
        $modelo = new NeutralizacionModel();

        $modelo->crear(1, ['numero' => 1, 'fecha_inicio' => '2026-03-01']);
        $id = (int) $this->conn->insertID();

        $this->assertTrue($modelo->reiniciar($id, '2026-03-11'));
        $this->assertFalse($modelo->reiniciar($id, '2026-03-20'));
    }

    public function testLaNumeracionReiniciaPorObra(): void
    {
        $modelo = new NeutralizacionModel();

        $modelo->crear(1, ['numero' => 1, 'fecha_inicio' => '2026-03-01']);
        $modelo->crear(2, ['numero' => 1, 'fecha_inicio' => '2026-04-01']);

        $this->assertSame(2, $modelo->proximoNumero(1));
        $this->assertSame(2, $modelo->proximoNumero(2));
        $this->assertSame(1, $modelo->proximoNumero(99));
    }

    public function testListarPorObraOrdenaPorNumeroYSoloDevuelveLaObra(): void
    {
        $modelo = new NeutralizacionModel();

        $modelo->crear(1, ['numero' => 2, 'fecha_inicio' => '2026-05-01']);
        $modelo->reiniciar((int) $this->conn->insertID(), '2026-05-10');

        $modelo->crear(1, ['numero' => 1, 'fecha_inicio' => '2026-03-01']);
        $modelo->reiniciar((int) $this->conn->insertID(), '2026-03-10');

        $modelo->crear(2, ['numero' => 1, 'fecha_inicio' => '2026-06-01']);

        $periodos = $modelo->listarPorObra(1);

        $this->assertCount(2, $periodos);
        $this->assertSame(1, (int) $periodos[0]->numero);
        $this->assertSame(2, (int) $periodos[1]->numero);
    }

    public function testUltimaFechaReinicioIgnoraLosPeriodosAbiertos(): void
    {
        $modelo = new NeutralizacionModel();

        $this->assertNull($modelo->ultimaFechaReinicio(1));

        $modelo->crear(1, ['numero' => 1, 'fecha_inicio' => '2026-03-01']);
        $this->assertNull($modelo->ultimaFechaReinicio(1));

        $modelo->reiniciar((int) $this->conn->insertID(), '2026-03-10');
        $this->assertSame('2026-03-10', $modelo->ultimaFechaReinicio(1));

        $modelo->crear(1, ['numero' => 2, 'fecha_inicio' => '2026-04-01']);
        $this->assertSame('2026-03-10', $modelo->ultimaFechaReinicio(1));

        $modelo->reiniciar((int) $this->conn->insertID(), '2026-05-20');
        $this->assertSame('2026-05-20', $modelo->ultimaFechaReinicio(1));
    }

    public function testUnSegundoPeriodoAbiertoEsRechazadoPorLaRestriccionUnica(): void
    {
        $modelo = new NeutralizacionModel();

        $modelo->crear(1, ['numero' => 1, 'fecha_inicio' => '2026-03-01']);

        $this->expectException(CodeIgniter\Database\Exceptions\DatabaseException::class);

        $modelo->crear(1, ['numero' => 2, 'fecha_inicio' => '2026-04-01']);
    }

    public function testElModeloBloqueaEdicionYEliminacion(): void
    {
        $modelo = new NeutralizacionModel();

        $modelo->crear(1, ['numero' => 1, 'fecha_inicio' => '2026-03-01']);
        $id = (int) $this->conn->insertID();

        $this->assertFalse($modelo->update($id, ['fecha_inicio' => '2026-01-01']));
        $this->assertFalse($modelo->delete($id));

        $this->assertCount(1, $modelo->listarPorObra(1));
        $this->assertSame('2026-03-01', $modelo->listarPorObra(1)[0]->fecha_inicio);
    }

    public function testElModeloBloqueaLaInsercionYEdicionMasivas(): void
    {
        $modelo = new NeutralizacionModel();

        $this->assertFalse($modelo->insertBatch([
            ['obra_id' => 1, 'numero' => 1, 'fecha_inicio' => '2026-03-01'],
        ]));

        $this->assertFalse($modelo->updateBatch([
            ['id' => 1, 'fecha_reinicio' => '2026-03-11'],
        ]));

        $this->assertCount(0, $modelo->listarPorObra(1));
    }

    private function tabla(string $nombre): string
    {
        return $this->conn->prefixTable($nombre);
    }
}
