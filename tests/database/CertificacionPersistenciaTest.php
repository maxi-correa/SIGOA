<?php

use App\Libraries\CertificacionObra;
use App\Models\CertificadoModel;
use App\Models\ObraModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Persistencia de configuración confirmada y de certificados calculados.
 *
 * @internal
 */
final class CertificacionPersistenciaTest extends CIUnitTestCase
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
            updated_at DATETIME NOT NULL,
            UNIQUE (obra_id, numero)
        )');
    }

    protected function tearDown(): void
    {
        $this->conn->query('DROP TABLE IF EXISTS ' . $this->tabla('certificados'));
        $this->conn->query('DROP TABLE IF EXISTS ' . $this->tabla('obras'));

        parent::tearDown();
    }

    public function testConfiguracionConfirmadaSeDerivaDeLosImportesPersistidos(): void
    {
        $this->insertarObra(2);

        $this->assertFalse(CertificacionObra::estaConfirmada((new ObraModel())->find(2)));

        (new ObraModel())->actualizarConfiguracionEconomica(2, [
            'presupuesto_oficial'            => '100000000.000',
            'monto_contrato'                 => '95000000.000',
            'tiene_anticipo_financiero'      => 1,
            'porcentaje_anticipo_financiero' => '20.000',
            'tiene_fondo_reparo'             => 1,
            'porcentaje_fondo_reparo'        => '5.000',
            'fondo_reparo_con_poliza'        => 0,
        ]);

        $obra = (new ObraModel())->find(2);

        $this->assertTrue(CertificacionObra::estaConfirmada($obra));
        $this->assertFalse(CertificacionObra::estaBloqueada((new CertificadoModel())->contarPorObra(2)));
        $this->assertSame(95000000.0, (float) $obra->monto_contrato);
        $this->assertSame(95000000.0, (float) $obra->monto_contractual_vigente);
    }

    public function testCrearCertificadoPersisteCalculosDelServidorYBloqueaLaConfiguracion(): void
    {
        $this->insertarObra(2, [
            'presupuesto_oficial'            => '100000000.000',
            'monto_contrato'                 => '95000000.000',
            'monto_contractual_vigente'      => '95000000.000',
            'tiene_anticipo_financiero'      => 1,
            'porcentaje_anticipo_financiero' => '20.000',
            'tiene_fondo_reparo'             => 1,
            'porcentaje_fondo_reparo'        => '5.000',
            'fondo_reparo_con_poliza'        => 0,
        ]);

        $obra      = (new ObraModel())->find(2);
        $modelo    = new CertificadoModel();
        $calculado = CertificacionObra::calcularValoresCertificado($obra, [], 10000000);

        $guardado = $modelo->crear([
            'obra_id'                => 2,
            'numero'                 => $modelo->proximoNumero(2),
            'mes'                    => 4,
            'anio'                   => 2026,
            'monto_bruto'            => '10000000.000',
            'descuento_anticipo'     => number_format($calculado['descuento_anticipo'], 3, '.', ''),
            'estado_anticipo'        => $calculado['estado_anticipo'],
            'retencion_fondo_reparo' => number_format($calculado['retencion_fondo_reparo'], 3, '.', ''),
            'monto_neto'             => number_format($calculado['monto_neto'], 3, '.', ''),
        ]);

        $certificado = $modelo->find($guardado);

        $this->assertSame(1, (int) $certificado->numero);
        $this->assertSame(CertificadoModel::ESTADO_ANTICIPO_NORMAL, $certificado->estado_anticipo);
        $this->assertSame(2000000.0, (float) $certificado->descuento_anticipo);
        $this->assertSame(500000.0, (float) $certificado->retencion_fondo_reparo);
        $this->assertGreaterThan(0, (float) $certificado->retencion_fondo_reparo);
        $this->assertSame(7500000.0, (float) $certificado->monto_neto);
        $this->assertTrue(CertificacionObra::estaBloqueada($modelo->contarPorObra(2)));
    }

    public function testRetencionSeGuardaPositivaAunqueLaUiLaMuestreNegativa(): void
    {
        $this->insertarObra(2, [
            'presupuesto_oficial'            => '100000000.000',
            'monto_contrato'                 => '95000000.000',
            'tiene_anticipo_financiero'      => 0,
            'tiene_fondo_reparo'             => 1,
            'porcentaje_fondo_reparo'        => '5.000',
            'fondo_reparo_con_poliza'        => 0,
        ]);

        $obra      = (new ObraModel())->find(2);
        $calculado = CertificacionObra::calcularValoresCertificado($obra, [], 10000000);
        $modelo    = new CertificadoModel();

        $id = $modelo->crear([
            'obra_id'                => 2,
            'numero'                 => 1,
            'mes'                    => 1,
            'anio'                   => 2026,
            'monto_bruto'            => '10000000.000',
            'descuento_anticipo'     => number_format($calculado['descuento_anticipo'], 3, '.', ''),
            'estado_anticipo'        => $calculado['estado_anticipo'],
            'retencion_fondo_reparo' => number_format($calculado['retencion_fondo_reparo'], 3, '.', ''),
            'monto_neto'             => number_format($calculado['monto_neto'], 3, '.', ''),
        ]);

        $certificado = $modelo->find($id);

        $this->assertSame(500000.0, (float) $certificado->retencion_fondo_reparo);
        $this->assertSame('-$ 500.000,00', CertificacionObra::formatearDeduccion($certificado->retencion_fondo_reparo));
    }

    public function testAvancesSeRecalculanAlListarCertificadosExistentes(): void
    {
        $this->insertarObra(2, [
            'presupuesto_oficial'            => '100000000.000',
            'monto_contrato'                 => '95000000.000',
            'monto_contractual_vigente'      => '100000000.000',
            'tiene_anticipo_financiero'      => 0,
            'tiene_fondo_reparo'             => 0,
        ]);

        $modelo = new CertificadoModel();

        $modelo->crear([
            'obra_id'                => 2,
            'numero'                 => 1,
            'mes'                    => 9,
            'anio'                   => 2026,
            'monto_bruto'            => '10000000.000',
            'descuento_anticipo'     => '0.000',
            'estado_anticipo'        => null,
            'retencion_fondo_reparo' => '0.000',
            'monto_neto'             => '10000000.000',
        ]);

        $modelo->crear([
            'obra_id'                => 2,
            'numero'                 => 2,
            'mes'                    => 10,
            'anio'                   => 2026,
            'monto_bruto'            => '8000000.000',
            'descuento_anticipo'     => '0.000',
            'estado_anticipo'        => null,
            'retencion_fondo_reparo' => '0.000',
            'monto_neto'             => '8000000.000',
        ]);

        $obra  = (new ObraModel())->find(2);
        $filas = CertificacionObra::conAvances($obra, $modelo->listarPorObra(2));

        $this->assertSame(10.0, $filas[0]->avance_mensual);
        $this->assertSame(10.0, $filas[0]->avance_acumulado);
        $this->assertSame(8.0, $filas[1]->avance_mensual);
        $this->assertSame(18.0, $filas[1]->avance_acumulado);
    }

    public function testAvancesQuedanVaciosSiElMontoContractualVigenteEsNull(): void
    {
        $this->insertarObra(2, [
            'presupuesto_oficial' => '100000000.000',
            'monto_contrato'      => '95000000.000',
        ]);

        $modelo = new CertificadoModel();

        $modelo->crear([
            'obra_id'                => 2,
            'numero'                 => 1,
            'mes'                    => 9,
            'anio'                   => 2026,
            'monto_bruto'            => '10000000.000',
            'descuento_anticipo'     => '0.000',
            'estado_anticipo'        => null,
            'retencion_fondo_reparo' => '0.000',
            'monto_neto'             => '10000000.000',
        ]);

        $obra  = (new ObraModel())->find(2);
        $filas = CertificacionObra::conAvances($obra, $modelo->listarPorObra(2));

        $this->assertNull($obra->monto_contractual_vigente);
        $this->assertNull($filas[0]->avance_mensual);
        $this->assertNull($filas[0]->avance_acumulado);
        $this->assertSame('—', CertificacionObra::formatearPorcentaje($filas[0]->avance_mensual));
    }

    public function testUltimoCertificadoSeObtienePorNumero(): void
    {
        $this->insertarObra(2, [
            'presupuesto_oficial' => '100000000.000',
            'monto_contrato'      => '95000000.000',
        ]);

        $modelo = new CertificadoModel();

        $this->assertNull($modelo->ultimoPorObra(2));

        $modelo->crear([
            'obra_id'                => 2,
            'numero'                 => 1,
            'mes'                    => 9,
            'anio'                   => 2026,
            'monto_bruto'            => '1000.000',
            'descuento_anticipo'     => '0.000',
            'estado_anticipo'        => null,
            'retencion_fondo_reparo' => '0.000',
            'monto_neto'             => '1000.000',
        ]);

        $modelo->crear([
            'obra_id'                => 2,
            'numero'                 => 2,
            'mes'                    => 10,
            'anio'                   => 2026,
            'monto_bruto'            => '2000.000',
            'descuento_anticipo'     => '0.000',
            'estado_anticipo'        => null,
            'retencion_fondo_reparo' => '0.000',
            'monto_neto'             => '2000.000',
        ]);

        $ultimo = (new CertificadoModel())->ultimoPorObra(2);

        $this->assertNotNull($ultimo);
        $this->assertSame(2, (int) $ultimo->numero);
        $this->assertSame(10, (int) $ultimo->mes);
        $this->assertSame(2026, (int) $ultimo->anio);
        $this->assertNotSame([], CertificacionObra::validarPeriodoPosterior(9, 2026, $ultimo));
        $this->assertSame([], CertificacionObra::validarPeriodoPosterior(11, 2026, $ultimo));
    }

    public function testNoPermiteNumeroDuplicadoEnLaMismaObra(): void
    {
        $this->insertarObra(2, [
            'presupuesto_oficial' => '100000000.000',
            'monto_contrato'      => '95000000.000',
        ]);

        $modelo = new CertificadoModel();

        $modelo->crear([
            'obra_id'                => 2,
            'numero'                 => 1,
            'mes'                    => 1,
            'anio'                   => 2026,
            'monto_bruto'            => '1000.000',
            'descuento_anticipo'     => '0.000',
            'estado_anticipo'        => null,
            'retencion_fondo_reparo' => '0.000',
            'monto_neto'             => '1000.000',
        ]);

        try {
            $modelo->crear([
                'obra_id'                => 2,
                'numero'                 => 1,
                'mes'                    => 2,
                'anio'                   => 2026,
                'monto_bruto'            => '2000.000',
                'descuento_anticipo'     => '0.000',
                'estado_anticipo'        => null,
                'retencion_fondo_reparo' => '0.000',
                'monto_neto'             => '2000.000',
            ]);
        } catch (\Throwable $excepcion) {
            $this->assertInstanceOf(\Throwable::class, $excepcion);
        }

        $this->assertSame(1, (new CertificadoModel())->contarPorObra(2));
    }

    /**
     * @param array<string, mixed> $extras
     */
    private function insertarObra(int $id, array $extras = []): void
    {
        $ahora = '2026-10-06 12:00:00';

        $this->conn->table('obras')->insert(array_merge([
            'id'                         => $id,
            'codigo'                     => 'OBR-000001',
            'expediente_municipal'       => '4356-M-2026',
            'nombre'                     => 'OBRA DE PRUEBA',
            'estado_obra_id'             => 1,
            'presupuesto_oficial'        => null,
            'monto_contrato'             => null,
            'monto_contractual_vigente'  => null,
            'tiene_anticipo_financiero'  => 0,
            'tiene_fondo_reparo'         => 0,
            'fondo_reparo_con_poliza'    => null,
            'created_at'                 => $ahora,
            'updated_at'                 => $ahora,
        ], $extras));
    }

    private function tabla(string $nombre): string
    {
        return $this->conn->prefixTable($nombre);
    }
}
