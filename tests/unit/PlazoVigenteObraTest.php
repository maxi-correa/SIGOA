<?php

use App\Libraries\PlazoObra;
use App\Libraries\ResolucionObra;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Cálculo del plazo contractual vigente y de la fecha de finalización a
 * partir del plazo inicial y las ampliaciones de plazo confirmadas.
 *
 * @internal
 */
final class PlazoVigenteObraTest extends CIUnitTestCase
{
    public function testAmpliaPlazoClasificaLosTiposPorNombre(): void
    {
        $this->assertTrue(ResolucionObra::ampliaPlazo(ResolucionObra::TIPO_AMPLIACION_PLAZO));
        $this->assertTrue(ResolucionObra::ampliaPlazo(ResolucionObra::TIPO_CUADRO_Y_AMPLIACION));

        foreach ([
            ResolucionObra::TIPO_ADJUDICACION,
            ResolucionObra::TIPO_CUADRO_COMPARATIVO,
            ResolucionObra::TIPO_OTRO,
            'TIPO FUTURO',
            null,
        ] as $tipo) {
            $this->assertFalse(ResolucionObra::ampliaPlazo($tipo));
        }
    }

    public function testSinAmpliacionesElVigenteCoincideConElInicial(): void
    {
        $plazo = PlazoObra::calcularVigente(180, []);

        $this->assertSame(180, $plazo['vigente_dias']);
        $this->assertSame(0, $plazo['total_ampliacion_dias']);
        $this->assertSame([], $plazo['ampliaciones']);
        $this->assertFalse($plazo['tiene_ampliaciones']);
    }

    public function testUnaAmpliacionConfirmadaIncrementaElVigente(): void
    {
        $plazo = PlazoObra::calcularVigente(180, [
            $this->resolucion(ResolucionObra::TIPO_AMPLIACION_PLAZO, 30, true),
        ]);

        $this->assertSame(210, $plazo['vigente_dias']);
        $this->assertSame(30, $plazo['total_ampliacion_dias']);
        $this->assertCount(1, $plazo['ampliaciones']);
        $this->assertTrue($plazo['tiene_ampliaciones']);
    }

    public function testVariasAmpliacionesConfirmadasSeSuman(): void
    {
        $plazo = PlazoObra::calcularVigente(100, [
            $this->resolucion(ResolucionObra::TIPO_AMPLIACION_PLAZO, 15, true),
            $this->resolucion(ResolucionObra::TIPO_AMPLIACION_PLAZO, 25, true),
            $this->resolucion(ResolucionObra::TIPO_CUADRO_Y_AMPLIACION, 60, true),
        ]);

        $this->assertSame(100 + 15 + 25 + 60, $plazo['vigente_dias']);
        $this->assertSame(100, $plazo['total_ampliacion_dias']);
        $this->assertCount(3, $plazo['ampliaciones']);
    }

    public function testUnaResolucionPendienteNoModificaElCalculo(): void
    {
        $plazo = PlazoObra::calcularVigente(180, [
            $this->resolucion(ResolucionObra::TIPO_AMPLIACION_PLAZO, 30, false),
        ]);

        $this->assertSame(180, $plazo['vigente_dias']);
        $this->assertSame(0, $plazo['total_ampliacion_dias']);
        $this->assertSame([], $plazo['ampliaciones']);
        $this->assertCount(1, $plazo['pendientes']);
    }

    public function testLosTiposQueNoAmplianNoModificanElPlazo(): void
    {
        $plazo = PlazoObra::calcularVigente(180, [
            $this->resolucion(ResolucionObra::TIPO_ADJUDICACION, 90, true),
            $this->resolucion(ResolucionObra::TIPO_OTRO, 90, true),
            $this->resolucion(ResolucionObra::TIPO_CUADRO_COMPARATIVO, 90, true),
        ]);

        $this->assertSame(180, $plazo['vigente_dias']);
        $this->assertSame([], $plazo['ampliaciones']);
        $this->assertSame([], $plazo['pendientes']);
    }

    public function testLaResolucionConjuntaSumaSusDiasUnaSolaVez(): void
    {
        $plazo = PlazoObra::calcularVigente(180, [
            $this->resolucion(ResolucionObra::TIPO_CUADRO_Y_AMPLIACION, 45, true),
        ]);

        $this->assertSame(225, $plazo['vigente_dias']);
        $this->assertSame(45, $plazo['total_ampliacion_dias']);
        $this->assertCount(1, $plazo['ampliaciones']);
    }

    public function testLosDiasInvalidosNoSeSuman(): void
    {
        $plazo = PlazoObra::calcularVigente(180, [
            $this->resolucion(ResolucionObra::TIPO_AMPLIACION_PLAZO, null, true),
            $this->resolucion(ResolucionObra::TIPO_AMPLIACION_PLAZO, 0, true),
        ]);

        $this->assertSame(180, $plazo['vigente_dias']);
        $this->assertSame([], $plazo['ampliaciones']);
    }

    public function testSinPlazoInicialElVigenteEsNulo(): void
    {
        $plazo = PlazoObra::calcularVigente(null, [
            $this->resolucion(ResolucionObra::TIPO_AMPLIACION_PLAZO, 30, true),
        ]);

        $this->assertNull($plazo['vigente_dias']);
        $this->assertSame(30, $plazo['total_ampliacion_dias']);
    }

    public function testLaFechaDeFinalizacionSumaDiasCorridosIncluyendoCambioDeAnio(): void
    {
        $this->assertSame(
            '2027-01-14',
            PlazoObra::fechaFinalizacion('2026-12-15', 30)
        );

        $this->assertSame(
            '2026-07-11',
            PlazoObra::fechaFinalizacion('2026-03-15', 118)
        );
    }

    public function testLaFechaDeFinalizacionEsNulaSinDatosInicialesValidos(): void
    {
        $this->assertNull(PlazoObra::fechaFinalizacion(null, 180));
        $this->assertNull(PlazoObra::fechaFinalizacion('2026-03-15', null));
        $this->assertNull(PlazoObra::fechaFinalizacion('fecha inválida', 180));
    }

    public function testLaNeutralizacionAbiertaImpidePresentarLaFecha(): void
    {
        $this->assertNull(
            PlazoObra::fechaFinalizacion('2026-03-15', 180, true),
            'Con una neutralización abierta no debe presentarse una fecha de finalización vigente.'
        );
    }

    private function resolucion(string $tipoNombre, ?int $dias, bool $confirmada): object
    {
        return (object) [
            'tipo_nombre' => $tipoNombre,
            'dias'        => $dias,
            'confirmada'  => $confirmada ? 1 : 0,
        ];
    }
}
