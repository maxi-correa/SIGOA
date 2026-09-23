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

    public function testAsegurarEstructuraObraCreaCarpetas(): void
    {
        $servicio = new ObraAlmacenamiento($this->tmp);

        $this->assertTrue($servicio->asegurarEstructuraObra('OBR-000001'));
        $this->assertTrue(is_dir($this->tmp . DIRECTORY_SEPARATOR . 'OBR-000001'));
        $this->assertTrue(is_dir($this->tmp . DIRECTORY_SEPARATOR . 'OBR-000001' . DIRECTORY_SEPARATOR . 'IMAGENES'));
        $this->assertTrue(is_dir($this->tmp . DIRECTORY_SEPARATOR . 'OBR-000001' . DIRECTORY_SEPARATOR . 'THUMBNAILS'));
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

    public function testDirectorioImagenes(): void
    {
        $servicio = new ObraAlmacenamiento($this->tmp);
        $servicio->asegurarEstructuraObra('OBR-000003');

        $esperado = $this->tmp . DIRECTORY_SEPARATOR . 'OBR-000003' . DIRECTORY_SEPARATOR . 'IMAGENES';

        $this->assertSame($esperado, $servicio->directorioImagenes('OBR-000003'));
        $this->assertNull($servicio->directorioImagenes('OBR-999999'));
        $this->assertNull($servicio->directorioImagenes('INVALIDO'));
    }

    public function testDirectorioThumbnails(): void
    {
        $servicio = new ObraAlmacenamiento($this->tmp);
        $servicio->asegurarEstructuraObra('OBR-000003');

        $esperado = $this->tmp . DIRECTORY_SEPARATOR . 'OBR-000003' . DIRECTORY_SEPARATOR . 'THUMBNAILS';

        $this->assertSame($esperado, $servicio->directorioThumbnails('OBR-000003'));
        $this->assertNull($servicio->directorioThumbnails('OBR-999999'));
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