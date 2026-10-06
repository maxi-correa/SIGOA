<?php

use App\Models\ObraModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Validación estructural de anticipo financiero y fondo de reparo.
 *
 * @internal
 */
final class ObraConfiguracionEconomicaTest extends CIUnitTestCase
{
    public function testSinAnticipoElPorcentajeDebeQuedarVacio(): void
    {
        $errores = (new ObraModel())->validarConfiguracionEconomica([
            'tiene_anticipo_financiero'      => 0,
            'porcentaje_anticipo_financiero' => 19.97,
            'tiene_fondo_reparo'             => 0,
            'porcentaje_fondo_reparo'        => null,
            'fondo_reparo_con_poliza'        => null,
        ]);

        $this->assertNotSame([], $errores);
    }

    public function testConAnticipoElPorcentajeEsObligatorioYAcotado(): void
    {
        $modelo = new ObraModel();

        $this->assertNotSame([], $modelo->validarConfiguracionEconomica([
            'tiene_anticipo_financiero'      => 1,
            'porcentaje_anticipo_financiero' => null,
            'tiene_fondo_reparo'             => 0,
            'porcentaje_fondo_reparo'        => null,
            'fondo_reparo_con_poliza'        => null,
        ]));

        $this->assertNotSame([], $modelo->validarConfiguracionEconomica([
            'tiene_anticipo_financiero'      => 1,
            'porcentaje_anticipo_financiero' => 0,
            'tiene_fondo_reparo'             => 0,
            'porcentaje_fondo_reparo'        => null,
            'fondo_reparo_con_poliza'        => null,
        ]));

        $this->assertNotSame([], $modelo->validarConfiguracionEconomica([
            'tiene_anticipo_financiero'      => 1,
            'porcentaje_anticipo_financiero' => 100.001,
            'tiene_fondo_reparo'             => 0,
            'porcentaje_fondo_reparo'        => null,
            'fondo_reparo_con_poliza'        => null,
        ]));

        $this->assertSame([], $modelo->validarConfiguracionEconomica([
            'tiene_anticipo_financiero'      => 1,
            'porcentaje_anticipo_financiero' => 19.97,
            'tiene_fondo_reparo'             => 0,
            'porcentaje_fondo_reparo'        => null,
            'fondo_reparo_con_poliza'        => null,
        ]));
    }

    public function testSinFondoDeReparoPorcentajeYPolizaDebenQuedarVacios(): void
    {
        $modelo = new ObraModel();

        $this->assertNotSame([], $modelo->validarConfiguracionEconomica([
            'tiene_anticipo_financiero'      => 0,
            'porcentaje_anticipo_financiero' => null,
            'tiene_fondo_reparo'             => 0,
            'porcentaje_fondo_reparo'        => 5,
            'fondo_reparo_con_poliza'        => null,
        ]));

        $this->assertNotSame([], $modelo->validarConfiguracionEconomica([
            'tiene_anticipo_financiero'      => 0,
            'porcentaje_anticipo_financiero' => null,
            'tiene_fondo_reparo'             => 0,
            'porcentaje_fondo_reparo'        => null,
            'fondo_reparo_con_poliza'        => 0,
        ]));
    }

    public function testConFondoDeReparoPorcentajeYPolizaSonObligatorios(): void
    {
        $modelo = new ObraModel();

        $this->assertNotSame([], $modelo->validarConfiguracionEconomica([
            'tiene_anticipo_financiero'      => 0,
            'porcentaje_anticipo_financiero' => null,
            'tiene_fondo_reparo'             => 1,
            'porcentaje_fondo_reparo'        => 5,
            'fondo_reparo_con_poliza'        => null,
        ]));

        $this->assertSame([], $modelo->validarConfiguracionEconomica([
            'tiene_anticipo_financiero'      => 0,
            'porcentaje_anticipo_financiero' => null,
            'tiene_fondo_reparo'             => 1,
            'porcentaje_fondo_reparo'        => 5,
            'fondo_reparo_con_poliza'        => 0,
        ]));

        $this->assertSame([], $modelo->validarConfiguracionEconomica([
            'tiene_anticipo_financiero'      => 0,
            'porcentaje_anticipo_financiero' => null,
            'tiene_fondo_reparo'             => 1,
            'porcentaje_fondo_reparo'        => 5,
            'fondo_reparo_con_poliza'        => 1,
        ]));
    }
}
