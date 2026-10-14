<?php

use App\Libraries\MontoObra;
use App\Libraries\ResolucionObra;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Cálculo del monto contractual vigente a partir del monto de contrato
 * original y de los ajustes económicos de los cuadros comparativos
 * confirmados.
 *
 * @internal
 */
final class MontoObraTest extends CIUnitTestCase
{
    public function testAjustaMontoClasificaLosTiposPorNombre(): void
    {
        $this->assertTrue(ResolucionObra::ajustaMonto(ResolucionObra::TIPO_CUADRO_COMPARATIVO));
        $this->assertTrue(ResolucionObra::ajustaMonto(ResolucionObra::TIPO_CUADRO_Y_AMPLIACION));

        foreach ([
            ResolucionObra::TIPO_ADJUDICACION,
            ResolucionObra::TIPO_AMPLIACION_PLAZO,
            ResolucionObra::TIPO_OTRO,
            'TIPO FUTURO',
            null,
        ] as $tipo) {
            $this->assertFalse(ResolucionObra::ajustaMonto($tipo));
        }
    }

    public function testSinAjustesElVigenteCoincideConElContrato(): void
    {
        $monto = MontoObra::calcularVigente('95000000.000', []);

        $this->assertSame('95000000.000', $monto['contrato']);
        $this->assertSame('95000000.000', $monto['vigente']);
        $this->assertSame('0.000', $monto['total_ajuste']);
        $this->assertSame([], $monto['ajustes']);
        $this->assertFalse($monto['tiene_ajustes']);
    }

    public function testUnCuadroComparativoConfirmadoSumaAlVigente(): void
    {
        $monto = MontoObra::calcularVigente('95000000.000', [
            $this->resolucion(ResolucionObra::TIPO_CUADRO_COMPARATIVO, '5000000.000', true),
        ]);

        $this->assertSame('100000000.000', $monto['vigente']);
        $this->assertSame('5000000.000', $monto['total_ajuste']);
        $this->assertCount(1, $monto['ajustes']);
        $this->assertTrue($monto['tiene_ajustes']);
    }

    public function testLaResolucionConjuntaSumaSuMontoUnaSolaVez(): void
    {
        $monto = MontoObra::calcularVigente('95000000.000', [
            $this->resolucion(ResolucionObra::TIPO_CUADRO_Y_AMPLIACION, '7500000.000', true),
        ]);

        $this->assertSame('102500000.000', $monto['vigente']);
        $this->assertSame('7500000.000', $monto['total_ajuste']);
        $this->assertCount(1, $monto['ajustes']);
    }

    public function testVariosAjustesConfirmadosSeSuman(): void
    {
        $monto = MontoObra::calcularVigente('100000.000', [
            $this->resolucion(ResolucionObra::TIPO_CUADRO_COMPARATIVO, '5000.000', true),
            $this->resolucion(ResolucionObra::TIPO_CUADRO_Y_AMPLIACION, '-2000.000', true),
        ]);

        $this->assertSame('103000.000', $monto['vigente']);
        $this->assertSame('3000.000', $monto['total_ajuste']);
        $this->assertCount(2, $monto['ajustes']);
    }

    public function testUnAjusteNegativoReduceElVigente(): void
    {
        $monto = MontoObra::calcularVigente('100000.000', [
            $this->resolucion(ResolucionObra::TIPO_CUADRO_COMPARATIVO, '-25000.500', true),
        ]);

        $this->assertSame('74999.500', $monto['vigente']);
        $this->assertSame('-25000.500', $monto['total_ajuste']);
    }

    public function testLasResolucionesPendientesNoAjustanElVigente(): void
    {
        $monto = MontoObra::calcularVigente('100000.000', [
            $this->resolucion(ResolucionObra::TIPO_CUADRO_COMPARATIVO, '5000.000', false),
        ]);

        $this->assertSame('100000.000', $monto['vigente']);
        $this->assertSame('0.000', $monto['total_ajuste']);
        $this->assertSame([], $monto['ajustes']);
        $this->assertCount(1, $monto['pendientes']);
    }

    public function testLosTiposQueNoAjustanMontoNoModificanElVigente(): void
    {
        $monto = MontoObra::calcularVigente('100000.000', [
            $this->resolucion(ResolucionObra::TIPO_ADJUDICACION, '9000.000', true),
            $this->resolucion(ResolucionObra::TIPO_AMPLIACION_PLAZO, '9000.000', true),
            $this->resolucion(ResolucionObra::TIPO_OTRO, '9000.000', true),
        ]);

        $this->assertSame('100000.000', $monto['vigente']);
        $this->assertSame([], $monto['ajustes']);
        $this->assertSame([], $monto['pendientes']);
    }

    public function testLosMontosInvalidosOCeroNoSeSuman(): void
    {
        $monto = MontoObra::calcularVigente('100000.000', [
            $this->resolucion(ResolucionObra::TIPO_CUADRO_COMPARATIVO, null, true),
            $this->resolucion(ResolucionObra::TIPO_CUADRO_COMPARATIVO, '0.000', true),
        ]);

        $this->assertSame('100000.000', $monto['vigente']);
        $this->assertSame([], $monto['ajustes']);
    }

    public function testSinMontoDeContratoElVigenteEsNulo(): void
    {
        $monto = MontoObra::calcularVigente(null, [
            $this->resolucion(ResolucionObra::TIPO_CUADRO_COMPARATIVO, '5000.000', true),
        ]);

        $this->assertNull($monto['contrato']);
        $this->assertNull($monto['vigente']);
        $this->assertSame('5000.000', $monto['total_ajuste']);
    }

    public function testLaSumaEsExactaEnLosDecimalesDelImporte(): void
    {
        $monto = MontoObra::calcularVigente('999999999999.999', [
            $this->resolucion(ResolucionObra::TIPO_CUADRO_COMPARATIVO, '-0.999', true),
        ]);

        $this->assertSame('999999999999.000', $monto['vigente']);
    }

    public function testElPorcentajeDeCadaAjusteSeCalculaSobreElContratoOriginal(): void
    {
        $monto = MontoObra::calcularVigente('100000.000', [
            $this->resolucion(ResolucionObra::TIPO_CUADRO_COMPARATIVO, '5000.000', true),
            $this->resolucion(ResolucionObra::TIPO_CUADRO_Y_AMPLIACION, '-2000.000', true),
        ]);

        /* Acumulado sobre el original: +5% y luego +3%, nunca sobre el
           vigente parcial previo. */
        $this->assertSame(['5,00 %', '3,00 %'], $monto['porcentajes']);
    }

    public function testPorcentajeVariacionEsCeroCuandoElVigenteIgualAlContrato(): void
    {
        $this->assertSame('0,00 %', MontoObra::porcentajeVariacion('100000.000', '100000.000'));
    }

    public function testPorcentajeVariacionReflejaAumentoYDisminucion(): void
    {
        $this->assertSame('3,00 %', MontoObra::porcentajeVariacion('100000.000', '103000.000'));
        $this->assertSame('-5,00 %', MontoObra::porcentajeVariacion('100000.000', '95000.000'));
    }

    public function testSinBaseDeCalculoNoHayPorcentaje(): void
    {
        $this->assertNull(MontoObra::porcentajeVariacion(null, '100000.000'));
        $this->assertNull(MontoObra::porcentajeVariacion('0.000', '100000.000'));

        $monto = MontoObra::calcularVigente(null, [
            $this->resolucion(ResolucionObra::TIPO_CUADRO_COMPARATIVO, '5000.000', true),
        ]);

        $this->assertSame([null], $monto['porcentajes']);
    }

    private function resolucion(string $tipoNombre, ?string $monto, bool $confirmada): object
    {
        return (object) [
            'tipo_nombre' => $tipoNombre,
            'monto'       => $monto,
            'confirmada'  => $confirmada ? 1 : 0,
        ];
    }
}
