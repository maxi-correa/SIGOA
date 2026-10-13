<?php

use App\Models\ResolucionModel;
use App\Models\TipoResolucionModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Persistencia y reglas de estado de las resoluciones de obra.
 *
 * Construye su propio esquema SQLite, por lo que no depende de que las
 * migraciones de MySQL estén aplicadas.
 *
 * @internal
 */
final class ResolucionesModelTest extends CIUnitTestCase
{
    private BaseConnection $conn;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conn = db_connect();

        $this->conn->query('DROP TABLE IF EXISTS ' . $this->tabla('resoluciones'));
        $this->conn->query('DROP TABLE IF EXISTS ' . $this->tabla('tipos_resolucion'));

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

        $this->conn->table('tipos_resolucion')->insertBatch([
            ['id' => 1, 'tipo_resolucion' => 'APROBACIÓN DE CUADRO COMPARATIVO', 'activo' => 1],
            ['id' => 2, 'tipo_resolucion' => 'APROBACIÓN DE AMPLIACIÓN DE PLAZO', 'activo' => 1],
            ['id' => 3, 'tipo_resolucion' => 'OTRO', 'activo' => 1],
        ]);
    }

    protected function tearDown(): void
    {
        $this->conn->query('DROP TABLE IF EXISTS ' . $this->tabla('resoluciones'));
        $this->conn->query('DROP TABLE IF EXISTS ' . $this->tabla('tipos_resolucion'));

        parent::tearDown();
    }

    public function testUnaResolucionSinIdentificacionQuedaPendiente(): void
    {
        $modelo = new ResolucionModel();

        $ok = $modelo->crear(1, [
            'tipo_resolucion_id' => 2,
            'numero'             => null,
            'anio'               => null,
            'fecha_resolucion'   => null,
            'monto'              => null,
            'dias'               => 30,
            'observaciones'      => null,
        ]);

        $this->assertTrue($ok);

        $fila = $this->conn->table('resoluciones')->get()->getRow();

        $this->assertSame(0, (int) $fila->confirmada);
        $this->assertNull($fila->numero);
        $this->assertNull($fila->fecha_resolucion);
        $this->assertSame(30, (int) $fila->dias);
    }

    public function testConfirmarVuelveLaResolucionInmutable(): void
    {
        $modelo = new ResolucionModel();
        $modelo->crear(1, [
            'tipo_resolucion_id' => 2,
            'numero'             => 10,
            'anio'               => 2026,
            'fecha_resolucion'   => '2026-03-01',
            'monto'              => null,
            'dias'               => 30,
            'observaciones'      => null,
        ]);

        $id = (int) $this->conn->table('resoluciones')->get()->getRow()->id;

        $this->assertFalse($modelo->estaConfirmada($id));
        $this->assertTrue($modelo->confirmar($id));
        $this->assertTrue($modelo->estaConfirmada($id));

        $this->assertFalse($modelo->actualizar($id, [
            'tipo_resolucion_id' => 2,
            'numero'             => 11,
            'anio'               => 2026,
            'fecha_resolucion'   => '2026-04-01',
            'monto'              => null,
            'dias'               => 60,
            'observaciones'      => null,
        ]));

        $this->assertFalse($modelo->delete($id));

        $fila = $this->conn->table('resoluciones')->where('id', $id)->get()->getRow();

        $this->assertSame(10, (int) $fila->numero);
        $this->assertSame(30, (int) $fila->dias);
    }

    public function testNoSeConfirmaDosVeces(): void
    {
        $modelo = new ResolucionModel();
        $modelo->crear(1, $this->datosBase());
        $id = (int) $this->conn->table('resoluciones')->get()->getRow()->id;

        $this->assertTrue($modelo->confirmar($id));
        $this->assertFalse($modelo->confirmar($id));
    }

    public function testActualizarYeliminarUnaResolucionPendiente(): void
    {
        $modelo = new ResolucionModel();
        $modelo->crear(1, $this->datosBase());
        $id = (int) $this->conn->table('resoluciones')->get()->getRow()->id;

        $this->assertTrue($modelo->actualizar($id, [
            'tipo_resolucion_id' => 1,
            'numero'             => 5,
            'anio'               => 2026,
            'fecha_resolucion'   => '2026-02-01',
            'monto'              => '1500.000',
            'dias'               => null,
            'observaciones'      => 'Actualizada',
        ]));

        $this->assertSame(1500.0, (float) $this->conn->table('resoluciones')->where('id', $id)->get()->getRow()->monto);

        $this->assertTrue($modelo->delete($id));
        $this->assertSame(0, $this->conn->table('resoluciones')->countAllResults());
    }

    public function testExisteNumeroAnioRespetaLaExclusion(): void
    {
        $modelo = new ResolucionModel();
        $modelo->crear(1, $this->datosBase(['numero' => 10, 'anio' => 2026]));
        $id = (int) $this->conn->table('resoluciones')->get()->getRow()->id;

        $this->assertTrue($modelo->existeNumeroAnio(1, 10, 2026));
        $this->assertFalse($modelo->existeNumeroAnio(1, 10, 2026, $id));
        $this->assertFalse($modelo->existeNumeroAnio(1, 11, 2026));
    }

    public function testListarPorObraResuelveElTipoYOrdena(): void
    {
        $modelo = new ResolucionModel();
        $modelo->crear(1, $this->datosBase(['numero' => 2, 'anio' => 2026]));
        $modelo->crear(1, $this->datosBase(['numero' => 1, 'anio' => 2026]));
        $modelo->crear(2, $this->datosBase(['numero' => 9, 'anio' => 2026]));

        $filas = $modelo->listarPorObra(1);

        $this->assertCount(2, $filas);
        $this->assertSame(1, (int) $filas[0]->numero);
        $this->assertSame(2, (int) $filas[1]->numero);
        $this->assertSame('APROBACIÓN DE AMPLIACIÓN DE PLAZO', $filas[0]->tipo_nombre);
    }

    public function testLasObservacionesSePersistenYRecuperan(): void
    {
        $modelo = new ResolucionModel();

        $this->assertTrue($modelo->crear(1, $this->datosBase([
            'numero'        => 7,
            'anio'          => 2026,
            'monto'         => null,
            'dias'          => null,
            'observaciones' => 'Acta de adjudicación',
        ])));

        $filas = $modelo->listarPorObra(1);

        $this->assertSame('Acta de adjudicación', $filas[0]->observaciones);
    }

    public function testLasObservacionesPuedenActualizarseMientrasNoEsteConfirmada(): void
    {
        $modelo = new ResolucionModel();
        $modelo->crear(1, $this->datosBase(['observaciones' => 'Inicial']));
        $id = (int) $this->conn->table('resoluciones')->get()->getRow()->id;

        $this->assertTrue($modelo->actualizar($id, [
            'tipo_resolucion_id' => 2,
            'numero'             => 1,
            'anio'               => 2026,
            'fecha_resolucion'   => '2026-01-01',
            'monto'              => null,
            'dias'               => 15,
            'observaciones'      => 'Corregida',
        ]));

        $this->assertSame('Corregida', (new ResolucionModel())->listarPorObra(1)[0]->observaciones);
    }

    public function testElSelectorOrdenaOtroAlFinalYCudroYAmpliacionJustoAntes(): void
    {
        $this->conn->table('tipos_resolucion')->insertBatch([
            ['id' => 4, 'tipo_resolucion' => 'ADJUDICACIÓN', 'activo' => 1],
            ['id' => 5, 'tipo_resolucion' => 'APROBACIÓN DE CUADRO COMPARATIVO Y AMPLIACIÓN DE PLAZO', 'activo' => 1],
        ]);

        $orden = array_map(
            static fn ($tipo): string => $tipo->tipo_resolucion,
            (new TipoResolucionModel())->findAllActivos()
        );

        $this->assertSame([
            'ADJUDICACIÓN',
            'APROBACIÓN DE CUADRO COMPARATIVO',
            'APROBACIÓN DE AMPLIACIÓN DE PLAZO',
            'APROBACIÓN DE CUADRO COMPARATIVO Y AMPLIACIÓN DE PLAZO',
            'OTRO',
        ], $orden);
        $this->assertSame('OTRO', $orden[count($orden) - 1]);
        $this->assertSame('APROBACIÓN DE CUADRO COMPARATIVO Y AMPLIACIÓN DE PLAZO', $orden[count($orden) - 2]);
    }

    /**
     * @param array<string, mixed> $extras
     *
     * @return array<string, mixed>
     */
    private function datosBase(array $extras = []): array
    {
        return array_merge([
            'tipo_resolucion_id' => 2,
            'numero'             => 1,
            'anio'               => 2026,
            'fecha_resolucion'   => '2026-01-01',
            'monto'              => null,
            'dias'               => 15,
            'observaciones'      => null,
        ], $extras);
    }

    private function tabla(string $nombre): string
    {
        return $this->conn->prefixTable($nombre);
    }
}
