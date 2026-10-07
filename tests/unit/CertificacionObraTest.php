<?php

use App\Libraries\CertificacionObra;
use App\Models\CertificadoModel;
use App\Models\ObraModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Cálculos de certificación: anticipo, fondo, neto, avance y bloqueo.
 *
 * @internal
 */
final class CertificacionObraTest extends CIUnitTestCase
{
    private function obra(array $overrides = []): object
    {
        return (object) array_merge([
            'presupuesto_oficial'            => 100000000,
            'monto_contrato'                 => 95000000,
            'monto_contractual_vigente'      => 95000000,
            'tiene_anticipo_financiero'      => 1,
            'porcentaje_anticipo_financiero' => 20,
            'tiene_fondo_reparo'             => 1,
            'porcentaje_fondo_reparo'        => 5,
            'fondo_reparo_con_poliza'        => 0,
        ], $overrides);
    }

    /**
     * @param list<object> $anteriores
     *
     * @return array<string, mixed>
     */
    private function calcular(object $obra, float $bruto, array $anteriores = []): array
    {
        return CertificacionObra::calcularValoresCertificado($obra, $anteriores, $bruto);
    }

    public function testAnticipoInexistenteNoDescuentaNiInventaEstado(): void
    {
        $obra = $this->obra([
            'tiene_anticipo_financiero'      => 0,
            'porcentaje_anticipo_financiero' => null,
        ]);

        $this->assertNull(CertificacionObra::anticipoTotal($obra));

        $valores = $this->calcular($obra, 10000000);

        $this->assertSame(0.0, $valores['descuento_anticipo']);
        $this->assertNull($valores['estado_anticipo']);
        $this->assertSame('---', CertificacionObra::estadoAnticipoUi($valores['estado_anticipo']));
    }

    public function testAnticipoTotalSeCalculaSobrePresupuestoOficial(): void
    {
        $obra = $this->obra([
            'presupuesto_oficial'            => 100000000,
            'porcentaje_anticipo_financiero' => 20,
        ]);

        $this->assertSame(20000000.0, CertificacionObra::anticipoTotal($obra));
    }

    public function testPorcentajeDeAnticipoDistintoDelEjemplo(): void
    {
        $obra = $this->obra([
            'presupuesto_oficial'            => 100000000,
            'porcentaje_anticipo_financiero' => 19.97,
        ]);

        $this->assertSame(19970000.0, CertificacionObra::anticipoTotal($obra));
    }

    public function testPrimerCertificadoConAnticipoEsNormal(): void
    {
        $valores = $this->calcular($this->obra(), 10000000);

        $this->assertSame(2000000.0, $valores['descuento_anticipo']);
        $this->assertSame(CertificadoModel::ESTADO_ANTICIPO_NORMAL, $valores['estado_anticipo']);
    }

    public function testVariosCertificadosSinSuperarElAnticipoSiguenNormal(): void
    {
        $obra       = $this->obra();
        $anteriores = [];

        for ($i = 0; $i < 3; $i++) {
            $valores      = $this->calcular($obra, 10000000, $anteriores);
            $anteriores[] = (object) ['descuento_anticipo' => $valores['descuento_anticipo']];

            $this->assertSame(CertificadoModel::ESTADO_ANTICIPO_NORMAL, $valores['estado_anticipo']);
            $this->assertSame(2000000.0, $valores['descuento_anticipo']);
        }

        $this->assertSame(6000000.0, CertificacionObra::descuentoAnticipoAcumulado($anteriores));
    }

    public function testCertificadoRemanenteNoSuperaElAnticipoTotal(): void
    {
        $anteriores = [
            (object) ['descuento_anticipo' => 15000000],
        ];

        $valores = $this->calcular($this->obra(), 100000000, $anteriores);

        $this->assertSame(5000000.0, $valores['descuento_anticipo']);
        $this->assertSame(CertificadoModel::ESTADO_ANTICIPO_REMANENTE, $valores['estado_anticipo']);
    }

    public function testCertificadoQueAgotaExactamenteElAnticipoEsNormal(): void
    {
        $anteriores = [
            (object) ['descuento_anticipo' => 18000000],
        ];

        $valores = $this->calcular($this->obra(), 10000000, $anteriores);

        $this->assertSame(2000000.0, $valores['descuento_anticipo']);
        $this->assertSame(CertificadoModel::ESTADO_ANTICIPO_NORMAL, $valores['estado_anticipo']);
    }

    public function testCertificadoPosteriorALiquidacionEsYaLiquidado(): void
    {
        $anteriores = [
            (object) ['descuento_anticipo' => 20000000],
        ];

        $valores = $this->calcular($this->obra(), 10000000, $anteriores);

        $this->assertSame(0.0, $valores['descuento_anticipo']);
        $this->assertSame(CertificadoModel::ESTADO_ANTICIPO_YA_LIQUIDADO, $valores['estado_anticipo']);
        $this->assertSame('YA LIQUIDADO', CertificacionObra::estadoAnticipoUi($valores['estado_anticipo']));
        $this->assertStringNotContainsString('_', CertificacionObra::estadoAnticipoUi($valores['estado_anticipo']));
    }

    public function testEstadosDeAnticipoSePresentanSinModificarElValorInterno(): void
    {
        $this->assertSame('NORMAL', CertificacionObra::estadoAnticipoUi(CertificadoModel::ESTADO_ANTICIPO_NORMAL));
        $this->assertSame('REMANENTE', CertificacionObra::estadoAnticipoUi(CertificadoModel::ESTADO_ANTICIPO_REMANENTE));
        $this->assertSame('YA LIQUIDADO', CertificacionObra::estadoAnticipoUi(CertificadoModel::ESTADO_ANTICIPO_YA_LIQUIDADO));
        $this->assertSame(CertificadoModel::ESTADO_ANTICIPO_YA_LIQUIDADO, 'YA_LIQUIDADO');
    }

    public function testFondoSinPolizaRetieneSobreElBruto(): void
    {
        $obra = $this->obra([
            'tiene_anticipo_financiero'      => 0,
            'porcentaje_anticipo_financiero' => null,
            'tiene_fondo_reparo'             => 1,
            'porcentaje_fondo_reparo'        => 5,
            'fondo_reparo_con_poliza'        => 0,
        ]);

        $valores = $this->calcular($obra, 10000000);

        $this->assertSame(500000.0, $valores['retencion_fondo_reparo']);
        $this->assertGreaterThan(0, $valores['retencion_fondo_reparo']);
        $this->assertSame('-$ 500.000,00', CertificacionObra::formatearDeduccion($valores['retencion_fondo_reparo']));
    }

    public function testFondoConPolizaNoRetiene(): void
    {
        $obra = $this->obra([
            'tiene_anticipo_financiero'      => 0,
            'porcentaje_anticipo_financiero' => null,
            'fondo_reparo_con_poliza'        => 1,
        ]);

        $valores = $this->calcular($obra, 10000000);

        $this->assertSame(0.0, $valores['retencion_fondo_reparo']);
        $this->assertSame('-$ 0,00', CertificacionObra::formatearDeduccion($valores['retencion_fondo_reparo']));
    }

    public function testFondoInexistenteNoRetiene(): void
    {
        $obra = $this->obra([
            'tiene_anticipo_financiero'      => 0,
            'porcentaje_anticipo_financiero' => null,
            'tiene_fondo_reparo'             => 0,
            'porcentaje_fondo_reparo'        => null,
            'fondo_reparo_con_poliza'        => null,
        ]);

        $valores = $this->calcular($obra, 10000000);

        $this->assertSame(0.0, $valores['retencion_fondo_reparo']);
        $this->assertSame('-$ 0,00', CertificacionObra::formatearDeduccion(0));
    }

    public function testNetoEsBrutoMenosAnticipoMenosFondo(): void
    {
        $valores = $this->calcular($this->obra(), 10000000);

        $this->assertSame(2000000.0, $valores['descuento_anticipo']);
        $this->assertSame(500000.0, $valores['retencion_fondo_reparo']);
        $this->assertSame(7500000.0, $valores['monto_neto']);
    }

    public function testAvanceMensualUsaMontoContractualVigente(): void
    {
        $obra = $this->obra([
            'presupuesto_oficial'       => 100000000,
            'monto_contractual_vigente' => 80000000,
        ]);

        $this->assertSame(12.5, CertificacionObra::avanceMensual($obra, 10000000));
        $this->assertSame(12.5, $this->calcular($obra, 10000000)['avance_mensual']);
    }

    public function testAvanceAcumuladoSumaLosAvancesMensuales(): void
    {
        $obra = $this->obra(['monto_contractual_vigente' => 100000000]);

        $filas = CertificacionObra::conAvances($obra, [
            (object) ['monto_bruto' => 10000000],
            (object) ['monto_bruto' => 8000000],
            (object) ['monto_bruto' => 12000000],
        ]);

        $this->assertSame(10.0, $filas[0]->avance_mensual);
        $this->assertSame(10.0, $filas[0]->avance_acumulado);
        $this->assertSame(8.0, $filas[1]->avance_mensual);
        $this->assertSame(18.0, $filas[1]->avance_acumulado);
        $this->assertSame(12.0, $filas[2]->avance_mensual);
        $this->assertSame(30.0, $filas[2]->avance_acumulado);
        $this->assertSame('10,000 %', CertificacionObra::formatearPorcentaje($filas[0]->avance_mensual));
    }

    public function testAvanceDelPrimerCertificadoUsaElMontoContractualVigente(): void
    {
        $obra = $this->obra(['monto_contractual_vigente' => 100000000]);

        $this->assertSame(10.0, CertificacionObra::avanceMensual($obra, 10000000));

        $filas = CertificacionObra::conAvances($obra, [
            (object) ['monto_bruto' => 10000000],
        ]);

        $this->assertSame(10.0, $filas[0]->avance_mensual);
        $this->assertSame(10.0, $filas[0]->avance_acumulado);
    }

    public function testAvanceDelSegundoCertificadoAcumulaElPrimero(): void
    {
        $obra = $this->obra(['monto_contractual_vigente' => 100000000]);

        $filas = CertificacionObra::conAvances($obra, [
            (object) ['monto_bruto' => 10000000],
            (object) ['monto_bruto' => 8000000],
        ]);

        $this->assertSame(8.0, $filas[1]->avance_mensual);
        $this->assertSame(18.0, $filas[1]->avance_acumulado);
    }

    public function testAvanceSinMontoContractualVigenteQuedaVacio(): void
    {
        $obra = $this->obra(['monto_contractual_vigente' => null]);

        $this->assertNull(CertificacionObra::avanceMensual($obra, 10000000));

        $filas = CertificacionObra::conAvances($obra, [
            (object) ['monto_bruto' => 10000000],
            (object) ['monto_bruto' => 8000000],
        ]);

        $this->assertNull($filas[0]->avance_mensual);
        $this->assertNull($filas[0]->avance_acumulado);
        $this->assertNull($filas[1]->avance_mensual);
        $this->assertNull($filas[1]->avance_acumulado);
        $this->assertSame('—', CertificacionObra::formatearPorcentaje($filas[0]->avance_mensual));
    }

    public function testAvanceConMontoContractualCeroNoDivide(): void
    {
        $obra = $this->obra(['monto_contractual_vigente' => 0]);

        $this->assertNull(CertificacionObra::avanceMensual($obra, 10000000));
        $this->assertNull($this->calcular($obra, 10000000)['avance_mensual']);
    }

    public function testConfiguracionSinPresupuestoNoEstaConfirmada(): void
    {
        $obra = $this->obra([
            'presupuesto_oficial' => null,
            'monto_contrato'      => null,
        ]);

        $this->assertFalse(CertificacionObra::estaConfirmada($obra));
    }

    public function testConfiguracionCompletaEstaConfirmadaYEditableSinCertificados(): void
    {
        $this->assertTrue(CertificacionObra::estaConfirmada($this->obra()));
        $this->assertFalse(CertificacionObra::estaBloqueada(0));
        $this->assertTrue(CertificacionObra::estaBloqueada(1));
    }

    public function testConfiguracionInvalidaFondoSinPoliza(): void
    {
        $errores = CertificacionObra::validarConfiguracionParaCertificar([
            'presupuesto_oficial'            => 100000000,
            'monto_contrato'                 => 95000000,
            'tiene_anticipo_financiero'      => 0,
            'porcentaje_anticipo_financiero' => null,
            'tiene_fondo_reparo'             => 1,
            'porcentaje_fondo_reparo'        => 5,
            'fondo_reparo_con_poliza'        => null,
        ]);

        $this->assertNotSame([], $errores);
    }

    public function testDatosDeCertificadoInvalidos(): void
    {
        $this->assertNotSame([], CertificacionObra::validarDatosCertificado(0, 2026, 1000));
        $this->assertNotSame([], CertificacionObra::validarDatosCertificado(3, 1999, 1000));
        $this->assertNotSame([], CertificacionObra::validarDatosCertificado(3, 2026, 0));
        $this->assertSame([], CertificacionObra::validarDatosCertificado(3, 2026, 1000));
    }

    public function testPrimerCertificadoNoTieneRestriccionCronologica(): void
    {
        $this->assertSame([], CertificacionObra::validarPeriodoPosterior(9, 2026, null));
        $this->assertSame([], CertificacionObra::validarPeriodoPosterior(1, 2020, null));
    }

    public function testSegundoCertificadoPosteriorEnElMismoAnioEsValido(): void
    {
        $ultimo = (object) ['mes' => 9, 'anio' => 2026];

        $this->assertSame([], CertificacionObra::validarPeriodoPosterior(10, 2026, $ultimo));
        $this->assertSame([], CertificacionObra::validarPeriodoPosterior(11, 2026, $ultimo));
        $this->assertSame([], CertificacionObra::validarPeriodoPosterior(12, 2026, $ultimo));
    }

    public function testSegundoCertificadoPosteriorCambiandoDeAnioEsValido(): void
    {
        $ultimo = (object) ['mes' => 12, 'anio' => 2026];

        $this->assertSame([], CertificacionObra::validarPeriodoPosterior(1, 2027, $ultimo));
    }

    public function testMismoMesYAnioEsRechazado(): void
    {
        $ultimo  = (object) ['mes' => 9, 'anio' => 2026];
        $errores = CertificacionObra::validarPeriodoPosterior(9, 2026, $ultimo);

        $this->assertNotSame([], $errores);
        $this->assertStringContainsString('Septiembre 2026', $errores[0]);
    }

    public function testMesAnteriorDelMismoAnioEsRechazado(): void
    {
        $ultimo  = (object) ['mes' => 9, 'anio' => 2026];
        $errores = CertificacionObra::validarPeriodoPosterior(8, 2026, $ultimo);

        $this->assertNotSame([], $errores);
        $this->assertStringContainsString('Septiembre 2026', $errores[0]);
    }

    public function testAnioAnteriorEsRechazado(): void
    {
        $ultimo  = (object) ['mes' => 1, 'anio' => 2027];
        $errores = CertificacionObra::validarPeriodoPosterior(12, 2026, $ultimo);

        $this->assertNotSame([], $errores);
        $this->assertStringContainsString('Enero 2027', $errores[0]);
    }

    public function testImportesYPorcentajesParseadosSiguenSiendoNumericos(): void
    {
        $this->assertSame('100000000.000', CertificacionObra::parseImporte('$ 100.000.000'));
        $this->assertSame('100000000.000', CertificacionObra::parseImporte('$ 100.000.000,00'));
        $this->assertSame('95000000.000', CertificacionObra::parseImporte('95000000'));
        $this->assertSame('20.000', CertificacionObra::parsePorcentaje('20,000 %'));
        $this->assertSame('5.000', CertificacionObra::parsePorcentaje('5,000'));
        $this->assertSame('10.000.000,00', CertificacionObra::formatearNumeroImporte(10000000));
        $this->assertSame('$ 10.000.000,00', CertificacionObra::formatearImporte(10000000));
        $this->assertSame('-$ 0,00', CertificacionObra::formatearDeduccion(0));
    }

    public function testValidacionEstructuralDeAnticipoSeConserva(): void
    {
        $errores = (new ObraModel())->validarConfiguracionEconomica([
            'tiene_anticipo_financiero'      => 0,
            'porcentaje_anticipo_financiero' => 20,
            'tiene_fondo_reparo'             => 0,
            'porcentaje_fondo_reparo'        => null,
            'fondo_reparo_con_poliza'        => null,
        ]);

        $this->assertNotSame([], $errores);
    }
}
