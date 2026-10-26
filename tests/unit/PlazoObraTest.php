<?php

namespace App\Libraries;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Pruebas de la conversión de plazos y del manejo de fechas de obra.
 *
 * @internal
 */
final class PlazoObraTest extends CIUnitTestCase
{
    public function testUnidadesContieneCodigosValidos(): void
    {
        $this->assertArrayHasKey(PlazoObra::UNIDAD_DIAS, PlazoObra::unidades());
        $this->assertArrayHasKey(PlazoObra::UNIDAD_MES, PlazoObra::unidades());
    }

    public function testEsUnidadValida(): void
    {
        $this->assertTrue(PlazoObra::esUnidadValida(PlazoObra::UNIDAD_DIAS));
        $this->assertTrue(PlazoObra::esUnidadValida(PlazoObra::UNIDAD_MES));
        $this->assertFalse(PlazoObra::esUnidadValida('SEMANAS'));
        $this->assertFalse(PlazoObra::esUnidadValida(''));
    }

    public function testDiasDesdeUnidadEnDiasCorridos(): void
    {
        $this->assertSame(120, PlazoObra::diasDesdeUnidad(120, PlazoObra::UNIDAD_DIAS));
    }

    public function testDiasDesdeUnidadEnMeses(): void
    {
        $this->assertSame(180, PlazoObra::diasDesdeUnidad(6, PlazoObra::UNIDAD_MES));
    }

    public function testParseFechaValida(): void
    {
        $this->assertSame('2026-03-15', PlazoObra::parseFecha('15/03/2026'));
    }

    public function testParseFechaVaciaDevuelveNull(): void
    {
        $this->assertNull(PlazoObra::parseFecha(''));
        $this->assertNull(PlazoObra::parseFecha(null));
    }

    public function testParseFechaInvalidaDevuelveNull(): void
    {
        $this->assertNull(PlazoObra::parseFecha('2026-03-15'));
        $this->assertNull(PlazoObra::parseFecha('31/02/2026'));
        $this->assertNull(PlazoObra::parseFecha('texto'));
    }

    public function testFormatearFecha(): void
    {
        $this->assertSame('15/03/2026', PlazoObra::formatearFecha('2026-03-15'));
        $this->assertNull(PlazoObra::formatearFecha('2026-02-31'));
        $this->assertNull(PlazoObra::formatearFecha(''));
        $this->assertNull(PlazoObra::formatearFecha(null));
    }

    public function testSumarDias(): void
    {
        $this->assertSame('2026-07-11', PlazoObra::sumarDias('2026-03-15', 118));
        $this->assertSame('2026-03-15', PlazoObra::sumarDias('2026-03-15', 0));
    }

    public function testFechaPrevistaDefinitivaSumaElPlazoALaRecepcionProvisoria(): void
    {
        $this->assertSame(
            '2027-04-21',
            PlazoObra::fechaPrevistaDefinitiva('2026-10-23', 180)
        );
    }

    public function testFechaPrevistaDefinitivaConDatosInvalidosDevuelveNull(): void
    {
        $this->assertNull(PlazoObra::fechaPrevistaDefinitiva('2026-10-23', -1));
        $this->assertNull(PlazoObra::fechaPrevistaDefinitiva('2026-02-31', 180));
        $this->assertNull(PlazoObra::fechaPrevistaDefinitiva('', 180));
    }

    public function testLaRecepcionDefinitivaSeHabilitaDesdeLaFechaPrevistaInclusive(): void
    {
        $this->assertFalse(
            PlazoObra::permiteRecepcionDefinitiva('2026-10-23', 180, '2027-04-20')
        );
        $this->assertTrue(
            PlazoObra::permiteRecepcionDefinitiva('2026-10-23', 180, '2027-04-21')
        );
        $this->assertTrue(
            PlazoObra::permiteRecepcionDefinitiva('2026-10-23', 180, '2027-04-22')
        );
    }

    public function testLaRecepcionDefinitivaConDatosInvalidosNoSeHabilita(): void
    {
        $this->assertFalse(
            PlazoObra::permiteRecepcionDefinitiva('2026-10-23', 0, '2026-10-23')
        );
        $this->assertFalse(
            PlazoObra::permiteRecepcionDefinitiva('2026-02-31', 180, '2027-04-21')
        );
        $this->assertFalse(
            PlazoObra::permiteRecepcionDefinitiva('2026-10-23', 180, '2027-02-31')
        );
    }

    public function testDiasEntreCuentaDiasCorridosSinIncluirExtremos(): void
    {
        $this->assertSame(10, PlazoObra::diasEntre('2026-03-01', '2026-03-11'));
        $this->assertSame(0, PlazoObra::diasEntre('2026-03-01', '2026-03-01'));
        $this->assertSame(-10, PlazoObra::diasEntre('2026-03-11', '2026-03-01'));
    }

    public function testDiasEntreConFechasInvalidasDevuelveNull(): void
    {
        $this->assertNull(PlazoObra::diasEntre(null, '2026-03-11'));
        $this->assertNull(PlazoObra::diasEntre('2026-03-01', ''));
        $this->assertNull(PlazoObra::diasEntre('2026-02-31', '2026-03-11'));
        $this->assertNull(PlazoObra::diasEntre('texto', '2026-03-11'));
    }

    public function testDiasNeutralizados(): void
    {
        $this->assertSame(10, PlazoObra::diasNeutralizados('2026-03-01', '2026-03-11'));
    }

    public function testDiasNeutralizadosAbiertaODuracionNegativaDevuelveNull(): void
    {
        $this->assertNull(PlazoObra::diasNeutralizados('2026-03-01', null));
        $this->assertNull(PlazoObra::diasNeutralizados('2026-03-11', '2026-03-01'));
        $this->assertNull(PlazoObra::diasNeutralizados('texto', '2026-03-11'));
    }

    public function testTotalNeutralizadoSumaSoloPeriodosCerrados(): void
    {
        $periodos = [
            (object) ['fecha_inicio' => '2026-03-01', 'fecha_reinicio' => '2026-03-11'],
            (object) ['fecha_inicio' => '2026-05-01', 'fecha_reinicio' => null],
            (object) ['fecha_inicio' => '2026-06-01', 'fecha_reinicio' => '2026-06-06'],
        ];

        $this->assertSame(15, PlazoObra::totalNeutralizado($periodos));
        $this->assertSame(0, PlazoObra::totalNeutralizado([]));
    }

    public function testFechaFinalizacionSumaLosDiasNeutralizados(): void
    {
        $this->assertSame(
            '2026-09-10',
            PlazoObra::fechaFinalizacion('2026-02-02', 210, false, 10)
        );
    }

    public function testFechaFinalizacionConNeutralizacionAbiertaEsNull(): void
    {
        $this->assertNull(PlazoObra::fechaFinalizacion('2026-02-02', 210, true, 10));
    }
}
