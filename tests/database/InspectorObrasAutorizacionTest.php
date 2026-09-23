<?php

use CodeIgniter\Config\Factories;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Autorización de la ruta de vista operativa de la obra del inspector.
 *
 * Verifica que un usuario sin sesión no pueda acceder a la ruta y que,
 * en consecuencia, no se prepare ni acceda a la estructura física de
 * almacenamiento (que se mantiene bajo una raíz temporal de prueba).
 *
 * @internal
 */
final class InspectorObrasAutorizacionTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private string $raiz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->raiz = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sigoa_auth_' . bin2hex(random_bytes(4));

        $configo = new \Config\SigoaStorage();
        $configo->storagePath = $this->raiz;

        Factories::injectMock('config', 'SigoaStorage', $configo);
    }

    protected function tearDown(): void
    {
        $this->eliminarTree($this->raiz);

        parent::tearDown();
    }

    public function testUsuarioSinAutenticacionEsRedirigidoSinCrearEstructura(): void
    {
        $resultado = $this->get('/inspector/obras/ver/1');

        $resultado->assertRedirectTo('/login');
        $this->assertFalse(file_exists($this->raiz));
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