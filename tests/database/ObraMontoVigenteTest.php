<?php

use App\Libraries\ResolucionObra;
use App\Models\ObraModel;
use App\Models\ResolucionModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Persistencia y recálculo del monto contractual vigente a partir de las
 * resoluciones almacenadas.
 *
 * Construye su propio esquema SQLite, por lo que no depende de que las
 * migraciones de MySQL estén aplicadas.
 *
 * @internal
 */
final class ObraMontoVigenteTest extends CIUnitTestCase
{
    private BaseConnection $conn;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conn = db_connect();

        $this->conn->query('DROP TABLE IF EXISTS ' . $this->tabla('resoluciones'));
        $this->conn->query('DROP TABLE IF EXISTS ' . $this->tabla('tipos_resolucion'));
        $this->conn->query('DROP TABLE IF EXISTS ' . $this->tabla('obras'));

        $this->conn->query('CREATE TABLE ' . $this->tabla('obras') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            codigo VARCHAR(20) NOT NULL,
            expediente_municipal VARCHAR(30) NOT NULL,
            nombre VARCHAR(255) NOT NULL,
            estado_obra_id INTEGER NOT NULL,
            presupuesto_oficial DECIMAL(15,3) NULL,
            monto_contrato DECIMAL(15,3) NULL,
            monto_contractual_vigente DECIMAL(15,3) NULL,
            tiene_anticipo_financiero INTEGER NOT NULL DEFAULT 0,
            porcentaje_anticipo_financiero DECIMAL(6,3) NULL,
            tiene_fondo_reparo INTEGER NOT NULL DEFAULT 0,
            porcentaje_fondo_reparo DECIMAL(6,3) NULL,
            fondo_reparo_con_poliza INTEGER NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )');

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
            ['id' => 1, 'tipo_resolucion' => ResolucionObra::TIPO_CUADRO_COMPARATIVO, 'activo' => 1],
            ['id' => 2, 'tipo_resolucion' => ResolucionObra::TIPO_CUADRO_Y_AMPLIACION, 'activo' => 1],
            ['id' => 3, 'tipo_resolucion' => ResolucionObra::TIPO_AMPLIACION_PLAZO, 'activo' => 1],
        ]);
    }

    protected function tearDown(): void
    {
        $this->conn->query('DROP TABLE IF EXISTS ' . $this->tabla('resoluciones'));
        $this->conn->query('DROP TABLE IF EXISTS ' . $this->tabla('tipos_resolucion'));
        $this->conn->query('DROP TABLE IF EXISTS ' . $this->tabla('obras'));

        parent::tearDown();
    }

    public function testElRecalculoSumanLosAjustesConfirmadosYPersiste(): void
    {
        $this->insertarObra(1, '95000000.000');

        $modelo = new ResolucionModel();
        $this->crearConfirmada($modelo, 1, 1, '5000000.000');
        $this->crearConfirmada($modelo, 1, 2, '7500000.000');

        $this->assertTrue((new ObraModel())->recalcularMontoContractualVigente(1));

        $obra = (new ObraModel())->find(1);

        $this->assertSame(107500000.0, (float) $obra->monto_contractual_vigente);
        $this->assertSame(95000000.0, (float) $obra->monto_contrato);
    }

    public function testRecalcularVariasVecesNoDuplicaLosAjustes(): void
    {
        $this->insertarObra(1, '100000.000');

        $modelo = new ResolucionModel();
        $this->crearConfirmada($modelo, 1, 1, '5000.000');

        $obraModel = new ObraModel();
        $obraModel->recalcularMontoContractualVigente(1);
        $obraModel->recalcularMontoContractualVigente(1);
        $obraModel->recalcularMontoContractualVigente(1);

        $obra = $obraModel->find(1);

        $this->assertSame(105000.0, (float) $obra->monto_contractual_vigente);
    }

    public function testLasResolucionesPendientesNoSePersistenEnElVigente(): void
    {
        $this->insertarObra(1, '100000.000');

        (new ResolucionModel())->crear(1, [
            'tipo_resolucion_id' => 1,
            'numero'             => 10,
            'anio'               => 2026,
            'fecha_resolucion'   => '2026-03-01',
            'monto'              => '5000.000',
            'dias'               => null,
            'observaciones'      => null,
        ]);

        (new ObraModel())->recalcularMontoContractualVigente(1);

        $obra = (new ObraModel())->find(1);

        $this->assertSame(100000.0, (float) $obra->monto_contractual_vigente);
    }

    public function testEditarLaConfiguracionEconomicaPreservaLosAjustesConfirmados(): void
    {
        $this->insertarObra(1, '100000.000');

        $modelo = new ResolucionModel();
        $this->crearConfirmada($modelo, 1, 1, '5000.000');

        $obraModel = new ObraModel();
        $obraModel->recalcularMontoContractualVigente(1);

        $this->assertSame(105000.0, (float) $obraModel->find(1)->monto_contractual_vigente);

        $obraModel->actualizarConfiguracionEconomica(1, [
            'presupuesto_oficial'            => '120000.000',
            'monto_contrato'                 => '100000.000',
            'tiene_anticipo_financiero'      => 0,
            'porcentaje_anticipo_financiero' => null,
            'tiene_fondo_reparo'             => 0,
            'porcentaje_fondo_reparo'        => null,
            'fondo_reparo_con_poliza'        => null,
        ]);
        $obraModel->recalcularMontoContractualVigente(1);

        $obra = $obraModel->find(1);

        $this->assertSame(100000.0, (float) $obra->monto_contrato);
        $this->assertSame(105000.0, (float) $obra->monto_contractual_vigente);
        $this->assertNotSame((float) $obra->monto_contrato, (float) $obra->monto_contractual_vigente);
    }

    public function testCambiarElContratoOriginalYRecalcularAjustaElVigente(): void
    {
        $this->insertarObra(1, '100000.000');

        $modelo = new ResolucionModel();
        $this->crearConfirmada($modelo, 1, 1, '5000.000');

        $obraModel = new ObraModel();
        $obraModel->actualizarConfiguracionEconomica(1, [
            'presupuesto_oficial'            => '120000.000',
            'monto_contrato'                 => '200000.000',
            'tiene_anticipo_financiero'      => 0,
            'porcentaje_anticipo_financiero' => null,
            'tiene_fondo_reparo'             => 0,
            'porcentaje_fondo_reparo'        => null,
            'fondo_reparo_con_poliza'        => null,
        ]);
        $obraModel->recalcularMontoContractualVigente(1);

        $obra = $obraModel->find(1);

        $this->assertSame(200000.0, (float) $obra->monto_contrato);
        $this->assertSame(205000.0, (float) $obra->monto_contractual_vigente);
    }

    private function insertarObra(int $id, string $montoContrato): void
    {
        $ahora = '2026-10-13 12:00:00';

        $this->conn->table('obras')->insert([
            'id'                        => $id,
            'codigo'                    => 'OBR-000001',
            'expediente_municipal'      => '4356-M-2026',
            'nombre'                    => 'OBRA DE PRUEBA',
            'estado_obra_id'            => 1,
            'presupuesto_oficial'       => '120000.000',
            'monto_contrato'            => $montoContrato,
            'monto_contractual_vigente' => $montoContrato,
            'created_at'                => $ahora,
            'updated_at'                => $ahora,
        ]);
    }

    private function crearConfirmada(ResolucionModel $modelo, int $obraId, int $tipoId, string $monto): void
    {
        $modelo->crear($obraId, [
            'tipo_resolucion_id' => $tipoId,
            'numero'             => 10 + $tipoId,
            'anio'               => 2026,
            'fecha_resolucion'   => '2026-03-01',
            'monto'              => $monto,
            'dias'               => null,
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
