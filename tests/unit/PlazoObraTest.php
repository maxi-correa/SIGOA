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
        $this->assertNull(PlazoObra::formatearFecha(''));
        $this->assertNull(PlazoObra::formatearFecha(null));
    }

    public function testSumarDias(): void
    {
        $this->assertSame('2026-07-11', PlazoObra::sumarDias('2026-03-15', 118));
        $this->assertSame('2026-03-15', PlazoObra::sumarDias('2026-03-15', 0));
    }
}
