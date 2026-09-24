<?php

use App\Models\EstadoObraModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regla F.7 / §52.5 — Estados de obra que permiten nuevas inspecciones.
 *
 * Verifica la función pura `EstadoObraModel::permiteInspeccionar()`: la
 * precondición de creación local/alta en línea (EN EJECUCIÓN, NEUTRALIZADA
 * y EN PLAZO DE CONSERVACIÓN PERMITIDO; PREVIO INICIO y FINALIZADA NO).
 *
 * @internal
 */
final class PermisoInspeccionarEstadoTest extends CIUnitTestCase
{
    public function testEstadosPermitidos(): void
    {
        $modelo = new EstadoObraModel();

        foreach (EstadoObraModel::PERMITEN_INSPECCIONAR as $estado) {
            $this->assertTrue(
                $modelo->permiteInspeccionar($estado),
                "El estado {$estado} debería permitir nuevas inspecciones (F.7)."
            );
        }
    }

    public function testEstadosNoPermitidos(): void
    {
        $modelo = new EstadoObraModel();

        $this->assertFalse($modelo->permiteInspeccionar('PREVIO INICIO'));
        $this->assertFalse($modelo->permiteInspeccionar('FINALIZADA'));
    }

    public function testNormalizaMayusculasYEspacios(): void
    {
        $modelo = new EstadoObraModel();

        $this->assertTrue($modelo->permiteInspeccionar('en ejecución'));
        $this->assertTrue($modelo->permiteInspeccionar('  NEUTRALIZADA  '));
        $this->assertTrue($modelo->permiteInspeccionar('En Plazo De Conservación'));
    }

    public function testValoresInvalidosNoPermiten(): void
    {
        $modelo = new EstadoObraModel();

        $this->assertFalse($modelo->permiteInspeccionar(''));
        $this->assertFalse($modelo->permiteInspeccionar('CUALQUIERA'));
        $this->assertFalse($modelo->permiteInspeccionar('EJECUCIÓN'));
    }

    public function testListadoPublicadoContieneSoloLosTresEstados(): void
    {
        $this->assertSame([
            'EN EJECUCIÓN',
            'NEUTRALIZADA',
            'EN PLAZO DE CONSERVACIÓN',
        ], EstadoObraModel::PERMITEN_INSPECCIONAR);
    }
}