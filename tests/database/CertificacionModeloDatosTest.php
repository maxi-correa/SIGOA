<?php

use App\Models\CertificadoModel;
use App\Models\ObraModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Persistencia de la configuración económica de obra y de los campos
 * de certificado preparados para la certificación.
 *
 * La suite usa SQLite en memoria. Las migraciones de la aplicación no
 * son compatibles con SQLite, por lo que se crea el esquema mínimo.
 *
 * @internal
 */
final class CertificacionModeloDatosTest extends CIUnitTestCase
{
    private BaseConnection $conn;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conn = db_connect();

        $this->conn->query('DROP TABLE IF EXISTS ' . $this->tabla('certificados'));
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

        $this->conn->query('CREATE TABLE ' . $this->tabla('certificados') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            obra_id INTEGER NOT NULL,
            numero INTEGER NOT NULL,
            mes INTEGER NOT NULL,
            anio INTEGER NOT NULL,
            fecha_emision DATE NULL,
            monto_bruto DECIMAL(15,3) NOT NULL,
            descuento_anticipo DECIMAL(15,3) NULL,
            estado_anticipo VARCHAR(30) NULL,
            retencion_fondo_reparo DECIMAL(15,3) NULL,
            monto_neto DECIMAL(15,3) NULL,
            documento_id INTEGER NULL,
            observaciones TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )');

        $this->limpiarTablas();
    }

    protected function tearDown(): void
    {
        $this->conn->query('DROP TABLE IF EXISTS ' . $this->tabla('certificados'));
        $this->conn->query('DROP TABLE IF EXISTS ' . $this->tabla('obras'));

        parent::tearDown();
    }

    public function testActualizarConfiguracionEconomicaPersisteLosCampos(): void
    {
        $ahora = '2026-10-06 12:00:00';

        $this->conn->table('obras')->insert([
            'id'                    => 2,
            'codigo'                => 'OBR-000001',
            'expediente_municipal'  => '4356-M-2026',
            'nombre'                => 'OBRA DE PRUEBA',
            'estado_obra_id'        => 1,
            'presupuesto_oficial'   => null,
            'monto_contrato'        => null,
            'monto_contractual_vigente' => null,
            'tiene_anticipo_financiero' => 0,
            'tiene_fondo_reparo'    => 0,
            'fondo_reparo_con_poliza' => null,
            'created_at'            => $ahora,
            'updated_at'            => $ahora,
        ]);

        $ok = (new ObraModel())->actualizarConfiguracionEconomica(2, [
            'presupuesto_oficial'            => '100000000.000',
            'monto_contrato'                 => '95000000.000',
            'tiene_anticipo_financiero'      => 1,
            'porcentaje_anticipo_financiero' => '19.970',
            'tiene_fondo_reparo'             => 1,
            'porcentaje_fondo_reparo'        => '5.000',
            'fondo_reparo_con_poliza'        => 0,
        ]);

        $this->assertTrue($ok);

        $obra = (new ObraModel())->find(2);

        $this->assertNotNull($obra);
        $this->assertSame(100000000.0, (float) $obra->presupuesto_oficial);
        $this->assertSame(95000000.0, (float) $obra->monto_contrato);
        $this->assertSame(1, (int) $obra->tiene_anticipo_financiero);
        $this->assertSame(19.97, (float) $obra->porcentaje_anticipo_financiero);
        $this->assertSame(1, (int) $obra->tiene_fondo_reparo);
        $this->assertSame(5.0, (float) $obra->porcentaje_fondo_reparo);
        $this->assertSame(0, (int) $obra->fondo_reparo_con_poliza);
        $this->assertSame(95000000.0, (float) $obra->monto_contractual_vigente);
        $this->assertSame((float) $obra->monto_contrato, (float) $obra->monto_contractual_vigente);
        $this->assertSame($ahora, $obra->created_at);
        $this->assertNotSame('', (string) $obra->updated_at);
    }

    public function testCertificadoModelPersisteEstadoAnticipoYRetencion(): void
    {
        $ahora = '2026-10-06 12:00:00';

        $this->conn->table('obras')->insert([
            'id'                   => 2,
            'codigo'               => 'OBR-000001',
            'expediente_municipal' => '4356-M-2026',
            'nombre'               => 'OBRA DE PRUEBA',
            'estado_obra_id'       => 1,
            'created_at'           => $ahora,
            'updated_at'           => $ahora,
        ]);

        $id = (new CertificadoModel())->insert([
            'obra_id'                => 2,
            'numero'                 => 1,
            'mes'                    => 3,
            'anio'                   => 2026,
            'monto_bruto'            => '1000000.000',
            'descuento_anticipo'     => '199700.000',
            'estado_anticipo'        => CertificadoModel::ESTADO_ANTICIPO_NORMAL,
            'retencion_fondo_reparo' => '50000.000',
            'monto_neto'             => '750300.000',
            'created_at'             => $ahora,
            'updated_at'             => $ahora,
        ], true);

        $this->assertNotFalse($id);

        $certificado = (new CertificadoModel())->find((int) $id);

        $this->assertNotNull($certificado);
        $this->assertSame(CertificadoModel::ESTADO_ANTICIPO_NORMAL, $certificado->estado_anticipo);
        $this->assertSame(199700.0, (float) $certificado->descuento_anticipo);
        $this->assertSame(50000.0, (float) $certificado->retencion_fondo_reparo);
        $this->assertSame(750300.0, (float) $certificado->monto_neto);
    }

    public function testEstadosAnticipoAdmitenNullYRechazanValoresAjenos(): void
    {
        $this->assertTrue(CertificadoModel::esEstadoAnticipoValido(null));
        $this->assertTrue(CertificadoModel::esEstadoAnticipoValido(CertificadoModel::ESTADO_ANTICIPO_REMANENTE));
        $this->assertTrue(CertificadoModel::esEstadoAnticipoValido(CertificadoModel::ESTADO_ANTICIPO_YA_LIQUIDADO));
        $this->assertFalse(CertificadoModel::esEstadoAnticipoValido('LIQUIDADO'));
    }

    private function tabla(string $nombre): string
    {
        return $this->conn->prefixTable($nombre);
    }

    private function limpiarTablas(): void
    {
        foreach (['certificados', 'obras'] as $tabla) {
            $this->conn->query('DELETE FROM ' . $this->tabla($tabla));
        }
    }
}
