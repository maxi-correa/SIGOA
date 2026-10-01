<?php

namespace App\Services;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Pruebas del almacenamiento físico de fotografías por obra.
 *
 * @internal
 */
final class ObraAlmacenamientoTest extends CIUnitTestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sigoa_test_' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        $this->eliminarTree($this->tmp);

        parent::tearDown();
    }

    public function testNormalizarCodigoValido(): void
    {
        $servicio = new ObraAlmacenamiento($this->tmp);

        $this->assertSame('OBR-000001', $servicio->normalizarCodigo('obr-000001'));
        $this->assertSame('OBR-000123', $servicio->normalizarCodigo('  OBR-000123  '));
    }

    public function testNormalizarCodigoInvalido(): void
    {
        $servicio = new ObraAlmacenamiento($this->tmp);

        $this->assertNull($servicio->normalizarCodigo('OBR-1'));
        $this->assertNull($servicio->normalizarCodigo('OBR-000001/../EVIL'));
        $this->assertNull($servicio->normalizarCodigo('../../fuera'));
        $this->assertNull($servicio->normalizarCodigo(''));
    }

    public function testAsegurarRaizSinConfiguracion(): void
    {
        $servicio = new ObraAlmacenamiento('');

        $this->assertFalse($servicio->asegurarRaiz());
        $this->assertFalse($servicio->asegurarEstructuraObra('OBR-000001'));
    }

    public function testAsegurarEstructuraObraCreaSoloLaCarpetaDeLaObra(): void
    {
        $servicio = new ObraAlmacenamiento($this->tmp);

        $this->assertTrue($servicio->asegurarEstructuraObra('OBR-000001'));
        $this->assertTrue(is_dir($this->tmp . DIRECTORY_SEPARATOR . 'OBR-000001'));
    }

    /**
     * El esquema legacy `OBR-XXXXXX/{IMAGENES,THUMBNAILS}` fue retirado en la
     * Fase E.1.2: dejar de recrearlo es el motivo del cambio, así que se
     * verifica explícitamente que no vuelva a aparecer.
     */
    public function testAsegurarEstructuraObraNoCreaCarpetasLegacy(): void
    {
        $servicio = new ObraAlmacenamiento($this->tmp);

        $this->assertTrue($servicio->asegurarEstructuraObra('OBR-000001'));

        $obra = $this->tmp . DIRECTORY_SEPARATOR . 'OBR-000001';

        $this->assertFalse(is_dir($obra . DIRECTORY_SEPARATOR . 'IMAGENES'));
        $this->assertFalse(is_dir($obra . DIRECTORY_SEPARATOR . 'THUMBNAILS'));
    }

    public function testAsegurarEstructuraInspeccionCreaImagenesYThumbnails(): void
    {
        $servicio = new ObraAlmacenamiento($this->tmp);

        $relativa = $servicio->asegurarEstructuraInspeccion(
            'OBR-000001',
            '2026-09-25',
            '11-14-00'
        );

        $this->assertSame('OBR-000001/2026-09-25/11-14-00', $relativa);

        $carpeta = $this->tmp . DIRECTORY_SEPARATOR . 'OBR-000001'
            . DIRECTORY_SEPARATOR . '2026-09-25' . DIRECTORY_SEPARATOR . '11-14-00';

        $this->assertTrue(is_dir($carpeta . DIRECTORY_SEPARATOR . 'IMAGENES'));
        $this->assertTrue(is_dir($carpeta . DIRECTORY_SEPARATOR . 'THUMBNAILS'));
    }

    /* =================================================================
       Fase E.2 — nombre físico derivado de fecha + hora
       ================================================================= */

    /**
     * Con hora, la carpeta es `HH-mm-ss`; la hora es la de la inspección, no
     * la del momento de la carga ni la del archivo fotográfico.
     */
    public function testNombreCarpetaConHoraNormal(): void
    {
        $servicio = new ObraAlmacenamiento($this->tmp);

        $this->assertSame('11-14-00', $servicio->nombreCarpetaInspeccion('11:14:00', 1));
        $this->assertSame('09-05-00', $servicio->nombreCarpetaInspeccion('09:05:00', 1));
        $this->assertSame('23-59-59', $servicio->nombreCarpetaInspeccion('23:59:59', 1));
    }

    /**
     * `00:00:00` es una hora válida y su carpeta es `00-00-00`: la medianoche
     * no equivale a la ausencia de hora.
     */
    public function testMedianocheEsHoraValidaYNoSinHora(): void
    {
        $servicio = new ObraAlmacenamiento($this->tmp);

        $this->assertSame('00-00-00', $servicio->nombreCarpetaInspeccion('00:00:00', 1));
        $this->assertNotSame('SIN-HORA', $servicio->nombreCarpetaInspeccion('00:00:00', 1));
        $this->assertSame('00-00-00-2', $servicio->nombreCarpetaInspeccion('00:00:00', 2));
    }

    /**
     * Sin hora (`hora_inspeccion IS NULL`) la carpeta es `SIN-HORA`, con el
     * mismo esquema de sufijos ordinales.
     */
    public function testNombreCarpetaSinHora(): void
    {
        $servicio = new ObraAlmacenamiento($this->tmp);

        $this->assertSame('SIN-HORA', $servicio->nombreCarpetaInspeccion('', 1));
        $this->assertSame('SIN-HORA', $servicio->nombreCarpetaInspeccion(null, 1));
        $this->assertSame('SIN-HORA-2', $servicio->nombreCarpetaInspeccion(null, 2));
        $this->assertSame('SIN-HORA-3', $servicio->nombreCarpetaInspeccion(null, 3));
    }

    /**
     * Sufijos ordinales para inspecciones que comparten obra + fecha + hora.
     * El primero (menor `id`) conserva el nombre base.
     */
    public function testSufijosOrdenalesParaMismaFechaYHora(): void
    {
        $servicio = new ObraAlmacenamiento($this->tmp);

        $this->assertSame('11-14-00', $servicio->nombreCarpetaInspeccion('11:14:00', 1));
        $this->assertSame('11-14-00-2', $servicio->nombreCarpetaInspeccion('11:14:00', 2));
        $this->assertSame('11-14-00-3', $servicio->nombreCarpetaInspeccion('11:14:00', 3));
        $this->assertSame('11-14-00-12', $servicio->nombreCarpetaInspeccion('11:14:00', 12));
    }

    /**
     * Una hora ilegible no produce carpeta. No se degrada a `SIN-HORA`:
     * `SIN-HORA` significa "la inspección no tiene hora", y una hora
     * inválida es un dato corrupto que debe rechazarse, no renombrarse.
     */
    public function testHoraIlegibleNoProduceNombreDeCarpeta(): void
    {
        $servicio = new ObraAlmacenamiento($this->tmp);

        $this->assertNull($servicio->nombreCarpetaInspeccion('no-es-una-hora', 1));
        $this->assertNull($servicio->nombreCarpetaInspeccion('24:00:00', 1));
        $this->assertNull($servicio->nombreCarpetaInspeccion('11:60:00', 1));
        $this->assertNull($servicio->nombreCarpetaInspeccion('11:14', 1));
        $this->assertNull($servicio->nombreCarpetaInspeccion('../11-14-00', 1));

        $this->assertNull($servicio->normalizarHoraCarpeta('no-es-una-hora'));
        $this->assertNull($servicio->normalizarHoraCarpeta('24:00:00'));
        $this->assertSame('SIN-HORA', $servicio->normalizarHoraCarpeta(null));
        $this->assertSame('00-00-00', $servicio->normalizarHoraCarpeta('00:00:00'));
    }

    /**
     * Un sufijo menor que 1 no corresponde a ninguna inspección válida.
     */
    public function testSufijoInvalidoNoProduceNombre(): void
    {
        $servicio = new ObraAlmacenamiento($this->tmp);

        $this->assertNull($servicio->nombreCarpetaInspeccion('11:14:00', 0));
        $this->assertNull($servicio->nombreCarpetaInspeccion('11:14:00', -1));
    }

    /**
     * La misma hora en fechas distintas vive en carpetas de fecha distintas,
     * de modo que nunca colisionan.
     */
    public function testMismaHoraEnFechasDistintasNoColisiona(): void
    {
        $servicio = new ObraAlmacenamiento($this->tmp);

        $primera = $servicio->rutaRelativaInspeccion('OBR-000001', '2026-09-30', '11-14-00');
        $segunda = $servicio->rutaRelativaInspeccion('OBR-000001', '2026-10-01', '11-14-00');

        $this->assertSame('OBR-000001/2026-09-30/11-14-00', $primera);
        $this->assertSame('OBR-000001/2026-10-01/11-14-00', $segunda);
        $this->assertNotSame($primera, $segunda);
    }

    /**
     * `IMAGENES/` y `THUMBNAILS/` se crean dentro de la carpeta correcta de
     * cada inspección, incluidas las que comparten fecha y hora.
     */
    public function testInspeccionesConSufijosCreanCarpetasDistintas(): void
    {
        $servicio = new ObraAlmacenamiento($this->tmp);

        $primera  = $servicio->asegurarEstructuraInspeccion('OBR-000001', '2026-09-30', '11-14-00');
        $segunda  = $servicio->asegurarEstructuraInspeccion('OBR-000001', '2026-09-30', '11-14-00-2');
        $tercera  = $servicio->asegurarEstructuraInspeccion('OBR-000001', '2026-09-30', '11-14-00-3');

        $this->assertSame('OBR-000001/2026-09-30/11-14-00', $primera);
        $this->assertSame('OBR-000001/2026-09-30/11-14-00-2', $segunda);
        $this->assertSame('OBR-000001/2026-09-30/11-14-00-3', $tercera);

        $base = $this->tmp . DIRECTORY_SEPARATOR . 'OBR-000001' . DIRECTORY_SEPARATOR . '2026-09-30';

        foreach (['11-14-00', '11-14-00-2', '11-14-00-3'] as $carpeta) {
            $this->assertTrue(is_dir($base . DIRECTORY_SEPARATOR . $carpeta . DIRECTORY_SEPARATOR . 'IMAGENES'));
            $this->assertTrue(is_dir($base . DIRECTORY_SEPARATOR . $carpeta . DIRECTORY_SEPARATOR . 'THUMBNAILS'));
        }
    }

    public function testInspeccionesSinHoraCreanCarpetasDistintas(): void
    {
        $servicio = new ObraAlmacenamiento($this->tmp);

        $primera = $servicio->asegurarEstructuraInspeccion('OBR-000001', '2026-09-30', 'SIN-HORA');
        $segunda = $servicio->asegurarEstructuraInspeccion('OBR-000001', '2026-09-30', 'SIN-HORA-2');

        $this->assertSame('OBR-000001/2026-09-30/SIN-HORA', $primera);
        $this->assertSame('OBR-000001/2026-09-30/SIN-HORA-2', $segunda);

        $base = $this->tmp . DIRECTORY_SEPARATOR . 'OBR-000001' . DIRECTORY_SEPARATOR . '2026-09-30';

        $this->assertTrue(is_dir($base . DIRECTORY_SEPARATOR . 'SIN-HORA' . DIRECTORY_SEPARATOR . 'IMAGENES'));
        $this->assertTrue(is_dir($base . DIRECTORY_SEPARATOR . 'SIN-HORA-2' . DIRECTORY_SEPARATOR . 'IMAGENES'));
    }

    /**
     * El UUID no forma parte de ninguna ruta física: es identidad técnica, no
     * nombre de carpeta. Ninguna ruta compuesta puede contenerlo.
     */
    public function testUuidNuncaFormaParteDelNombreDeCarpeta(): void
    {
        $servicio = new ObraAlmacenamiento($this->tmp);
        $uuid     = 'e4cba23b-199a-467f-942c-a09f4a4a4edb';

        $relativa = $servicio->asegurarEstructuraInspeccion('OBR-000001', '2026-09-30', '11-14-00');

        $this->assertStringNotContainsString($uuid, (string) $relativa);
        $this->assertStringNotContainsString($uuid, (string) $servicio->rutaRelativaImagen(
            'OBR-000001',
            '2026-09-30',
            '11-14-00',
            'INS-00001-20260930-111400-abc123.jpg'
        ));
        $this->assertStringNotContainsString($uuid, (string) $servicio->rutaRelativaThumbnail(
            'OBR-000001',
            '2026-09-30',
            '11-14-00',
            'THB-00001-20260930-111400-def456.jpg'
        ));

        /* Tampoco existe ninguna carpeta con el nombre del UUID. */
        $base = $this->tmp . DIRECTORY_SEPARATOR . 'OBR-000001' . DIRECTORY_SEPARATOR . '2026-09-30';

        $this->assertFalse(is_dir($base . DIRECTORY_SEPARATOR . $uuid));
        $this->assertSame(['.', '..', '11-14-00'], scandir($base));
    }

    /**
     * Las rutas relativas de las fotografías apuntan al esquema vigente.
     */
    public function testRutasRelativasDeFotografiasApuntanAlEsquemaNuevo(): void
    {
        $servicio = new ObraAlmacenamiento($this->tmp);

        $this->assertSame(
            'OBR-000001/2026-09-30/11-14-00/IMAGENES/INS-00042-20260930-111400-a1b2c3.jpg',
            $servicio->rutaRelativaImagen(
                'OBR-000001',
                '2026-09-30',
                '11-14-00',
                'INS-00042-20260930-111400-a1b2c3.jpg'
            )
        );

        $this->assertSame(
            'OBR-000001/2026-09-30/11-14-00/THUMBNAILS/THB-00042-20260930-111400-d4e5f6.jpg',
            $servicio->rutaRelativaThumbnail(
                'OBR-000001',
                '2026-09-30',
                '11-14-00',
                'THB-00042-20260930-111400-d4e5f6.jpg'
            )
        );

        $this->assertSame(
            'OBR-000001/2026-09-30/SIN-HORA-2/IMAGENES/INS-00043-20260930-000000-aa11bb.jpg',
            $servicio->rutaRelativaImagen(
                'OBR-000001',
                '2026-09-30',
                'SIN-HORA-2',
                'INS-00043-20260930-000000-aa11bb.jpg'
            )
        );
    }

    /**
     * El nombre de carpeta se revalida antes de usarlo: aunque lo compone el
     * propio servicio, un nombre con separadores o traversal se rechaza.
     */
    public function testNombreDeCarpetaInvalidoSeRechaza(): void
    {
        $servicio = new ObraAlmacenamiento($this->tmp);

        $this->assertNull($servicio->rutaRelativaInspeccion('OBR-000001', '2026-09-30', '../../fuera'));
        $this->assertNull($servicio->rutaRelativaInspeccion('OBR-000001', '2026-09-30', 'SUB/CARPETA'));
        $this->assertNull($servicio->rutaRelativaInspeccion('OBR-000001', '2026-09-30', ''));
        $this->assertNull($servicio->rutaRelativaInspeccion('OBR-000001', '2026-09-30', '11:14:00'));
        $this->assertNull($servicio->rutaRelativaInspeccion('OBR-000001', '2026-09-30', '11-14-00-0'));
    }

    /**
     * Las rutas absolutas de las carpetas se resuelven dentro de la raíz
     * configurada y solo existen si la estructura fue creada.
     */
    public function testDirectoriosDeInspeccionSeResuelvenDentroDeLaCarpetaCorrecta(): void
    {
        $servicio = new ObraAlmacenamiento($this->tmp);

        $this->assertNull($servicio->directorioImagenesInspeccion('OBR-000001', '2026-09-30', '11-14-00'));

        $servicio->asegurarEstructuraInspeccion('OBR-000001', '2026-09-30', '11-14-00-2');

        $esperado = $this->tmp . DIRECTORY_SEPARATOR . 'OBR-000001'
            . DIRECTORY_SEPARATOR . '2026-09-30' . DIRECTORY_SEPARATOR . '11-14-00-2';

        $this->assertSame(
            $esperado . DIRECTORY_SEPARATOR . 'IMAGENES',
            $servicio->directorioImagenesInspeccion('OBR-000001', '2026-09-30', '11-14-00-2')
        );
        $this->assertSame(
            $esperado . DIRECTORY_SEPARATOR . 'THUMBNAILS',
            $servicio->directorioThumbnailsInspeccion('OBR-000001', '2026-09-30', '11-14-00-2')
        );

        /* La inspección base no existe todavía: no se resuelve a nada. */
        $this->assertNull($servicio->directorioImagenesInspeccion('OBR-000001', '2026-09-30', '11-14-00'));
    }

    public function testAsegurarEstructuraObraEsIdempotente(): void
    {
        $servicio = new ObraAlmacenamiento($this->tmp);

        $this->assertTrue($servicio->asegurarEstructuraObra('OBR-000002'));
        $this->assertTrue($servicio->asegurarEstructuraObra('OBR-000002'));
    }

    public function testAsegurarEstructuraObraConCodigoInvalido(): void
    {
        $servicio = new ObraAlmacenamiento($this->tmp);

        $this->assertFalse($servicio->asegurarEstructuraObra('INVALIDO'));
        $this->assertFalse($servicio->asegurarEstructuraObra('OBR-000001/../EVIL'));
    }

    public function testRutaRelativaObra(): void
    {
        $servicio = new ObraAlmacenamiento($this->tmp);

        $this->assertSame('OBR-000001', $servicio->rutaRelativaObra('OBR-000001'));
        $this->assertNull($servicio->rutaRelativaObra('OBR-1'));
    }

    private function eliminarTree(string $directorio): void
    {
        if (! is_dir($directorio)) {
            return;
        }

        $items = scandir($directorio);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $ruta = $directorio . DIRECTORY_SEPARATOR . $item;

            if (is_dir($ruta)) {
                $this->eliminarTree($ruta);
            } else {
                @unlink($ruta);
            }
        }

        @rmdir($directorio);
    }
}