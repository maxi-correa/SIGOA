<?php

use App\Libraries\ResolucionObra;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Reglas de negocio de las resoluciones de obra: qué campos admite cada
 * tipo, cómo se normalizan monto/días y cuándo una resolución está lista
 * para confirmarse.
 *
 * @internal
 */
final class ResolucionObraTest extends CIUnitTestCase
{
    public function testLasReglasDeCadaTipoSonLasEsperadas(): void
    {
        $cuadro = ResolucionObra::reglas(ResolucionObra::TIPO_CUADRO_COMPARATIVO);
        $this->assertSame(ResolucionObra::CAMPO_REQUERIDO, $cuadro['monto']);
        $this->assertSame(ResolucionObra::CAMPO_OCULTO, $cuadro['dias']);

        $ampliacion = ResolucionObra::reglas(ResolucionObra::TIPO_AMPLIACION_PLAZO);
        $this->assertSame(ResolucionObra::CAMPO_OCULTO, $ampliacion['monto']);
        $this->assertSame(ResolucionObra::CAMPO_REQUERIDO, $ampliacion['dias']);

        $combinada = ResolucionObra::reglas(ResolucionObra::TIPO_CUADRO_Y_AMPLIACION);
        $this->assertSame(ResolucionObra::CAMPO_REQUERIDO, $combinada['monto']);
        $this->assertSame(ResolucionObra::CAMPO_REQUERIDO, $combinada['dias']);

        foreach ([ResolucionObra::TIPO_ADJUDICACION, ResolucionObra::TIPO_OTRO] as $tipo) {
            $reglas = ResolucionObra::reglas($tipo);
            $this->assertSame(ResolucionObra::CAMPO_OCULTO, $reglas['monto']);
            $this->assertSame(ResolucionObra::CAMPO_OCULTO, $reglas['dias']);
        }

        $desconocido = ResolucionObra::reglas('TIPO FUTURO');
        $this->assertSame(ResolucionObra::CAMPO_OPCIONAL, $desconocido['monto']);
        $this->assertSame(ResolucionObra::CAMPO_OPCIONAL, $desconocido['dias']);
    }

    public function testElCuadroComparativoExigeMontoYRechazaDias(): void
    {
        $this->assertSame([], ResolucionObra::validar('1000.000', null, ResolucionObra::TIPO_CUADRO_COMPARATIVO));
        $this->assertNotSame([], ResolucionObra::validar(null, null, ResolucionObra::TIPO_CUADRO_COMPARATIVO));
        $this->assertNotSame([], ResolucionObra::validar('1000.000', 10, ResolucionObra::TIPO_CUADRO_COMPARATIVO));
    }

    public function testLaAmpliacionDePlazoExigeDiasYRechazaMonto(): void
    {
        $this->assertSame([], ResolucionObra::validar(null, 30, ResolucionObra::TIPO_AMPLIACION_PLAZO));
        $this->assertNotSame([], ResolucionObra::validar(null, null, ResolucionObra::TIPO_AMPLIACION_PLAZO));
        $this->assertNotSame([], ResolucionObra::validar(null, 0, ResolucionObra::TIPO_AMPLIACION_PLAZO));
        $this->assertNotSame([], ResolucionObra::validar('500.000', 30, ResolucionObra::TIPO_AMPLIACION_PLAZO));
    }

    public function testLaResolucionCombinadaExigeAmbosValores(): void
    {
        $this->assertSame([], ResolucionObra::validar('500.000', 15, ResolucionObra::TIPO_CUADRO_Y_AMPLIACION));
        $this->assertNotSame([], ResolucionObra::validar('1000.000', null, ResolucionObra::TIPO_CUADRO_Y_AMPLIACION));
        $this->assertNotSame([], ResolucionObra::validar(null, 15, ResolucionObra::TIPO_CUADRO_Y_AMPLIACION));
    }

    public function testLasResolucionesInformativasNoAdmitenMontoNiDias(): void
    {
        foreach ([ResolucionObra::TIPO_ADJUDICACION, ResolucionObra::TIPO_OTRO] as $tipo) {
            $this->assertSame([], ResolucionObra::validar(null, null, $tipo));
            $this->assertNotSame([], ResolucionObra::validar('1000.000', null, $tipo));
            $this->assertNotSame([], ResolucionObra::validar(null, 30, $tipo));
            $this->assertNotSame([], ResolucionObra::validar('1000.000', 30, $tipo));
        }
    }

    public function testLosTiposDesconocidosAdmitenAlgunoDeLosDosCampos(): void
    {
        $this->assertSame([], ResolucionObra::validar('1000.000', null, 'TIPO FUTURO'));
        $this->assertSame([], ResolucionObra::validar(null, 30, 'TIPO FUTURO'));
        $this->assertSame([], ResolucionObra::validar('1000.000', 30, 'TIPO FUTURO'));
        $this->assertNotSame([], ResolucionObra::validar(null, null, 'TIPO FUTURO'));
        $this->assertNotSame([], ResolucionObra::validar('0.000', null, 'TIPO FUTURO'));
    }

    public function testSoloLasResolucionesInformativasExigenObservaciones(): void
    {
        $this->assertTrue(ResolucionObra::requiereObservaciones(ResolucionObra::TIPO_ADJUDICACION));
        $this->assertTrue(ResolucionObra::requiereObservaciones(ResolucionObra::TIPO_OTRO));

        foreach ([
            ResolucionObra::TIPO_CUADRO_COMPARATIVO,
            ResolucionObra::TIPO_AMPLIACION_PLAZO,
            ResolucionObra::TIPO_CUADRO_Y_AMPLIACION,
            'TIPO FUTURO',
            null,
        ] as $tipo) {
            $this->assertFalse(ResolucionObra::requiereObservaciones($tipo));
        }
    }

    public function testPuedeConfirmarseExigeIdentificacionYObservacionesInformativas(): void
    {
        $this->assertTrue(ResolucionObra::puedeConfirmarse(ResolucionObra::TIPO_CUADRO_COMPARATIVO, 1, 2026, '2026-01-01', null));

        $this->assertFalse(ResolucionObra::puedeConfirmarse(ResolucionObra::TIPO_ADJUDICACION, 1, 2026, '2026-01-01', null));
        $this->assertFalse(ResolucionObra::puedeConfirmarse(ResolucionObra::TIPO_ADJUDICACION, 1, 2026, '2026-01-01', ''));
        $this->assertFalse(ResolucionObra::puedeConfirmarse(ResolucionObra::TIPO_ADJUDICACION, null, 2026, '2026-01-01', 'Nota'));
        $this->assertTrue(ResolucionObra::puedeConfirmarse(ResolucionObra::TIPO_ADJUDICACION, 1, 2026, '2026-01-01', 'Nota'));

        $this->assertTrue(ResolucionObra::puedeConfirmarse(ResolucionObra::TIPO_OTRO, 5, 2025, '2025-06-30', 'Detalle'));
    }

    public function testRequiereObservacionesIgnoraEspaciosEnBlanco(): void
    {
        $this->assertFalse(ResolucionObra::puedeConfirmarse(ResolucionObra::TIPO_OTRO, 1, 2026, '2026-01-01', '   '));
    }

    public function testParseMontoNormalizaFormatosYAdmiteNegativos(): void
    {
        $this->assertSame('1234.560', ResolucionObra::parseMonto('1.234,56'));
        $this->assertSame('100000.000', ResolucionObra::parseMonto('$ 100.000'));
        $this->assertSame('10.500', ResolucionObra::parseMonto('10.5'));
        $this->assertSame('-1234.560', ResolucionObra::parseMonto('-1.234,56'));
        $this->assertNull(ResolucionObra::parseMonto(''));
        $this->assertNull(ResolucionObra::parseMonto('no es un número'));
    }

    public function testParseDiasSoloAceptaEnterosNoNegativos(): void
    {
        $this->assertSame(30, ResolucionObra::parseDias('30'));
        $this->assertSame(0, ResolucionObra::parseDias('0'));
        $this->assertNull(ResolucionObra::parseDias(''));
        $this->assertNull(ResolucionObra::parseDias('3.5'));
        $this->assertNull(ResolucionObra::parseDias('-3'));
    }

    public function testParseNumeroYAnioValidanRango(): void
    {
        $this->assertSame(123, ResolucionObra::parseNumero('123'));
        $this->assertNull(ResolucionObra::parseNumero('0'));
        $this->assertNull(ResolucionObra::parseNumero(''));

        $this->assertSame(2026, ResolucionObra::parseAnio('2026'));
        $this->assertNull(ResolucionObra::parseAnio('26'));
        $this->assertNull(ResolucionObra::parseAnio('99999'));
    }

    public function testIdentificacionCompletaExigeLosTresDatos(): void
    {
        $this->assertTrue(ResolucionObra::identificacionCompleta(1, 2026, '2026-01-01'));
        $this->assertFalse(ResolucionObra::identificacionCompleta(null, 2026, '2026-01-01'));
        $this->assertFalse(ResolucionObra::identificacionCompleta(1, null, '2026-01-01'));
        $this->assertFalse(ResolucionObra::identificacionCompleta(1, 2026, null));
    }
}
