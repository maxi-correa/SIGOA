<?php

use App\Controllers\Inspector\Obras;
use App\Services\ObraAlmacenamiento;
use CodeIgniter\Config\Factories;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\ControllerTestTrait;

/**
 * Integración: acceso del inspector a la vista de obra y preparación de
 * la estructura física de almacenamiento.
 *
 * No se toca la ruta real C:\Compartida\SIGOA: se inyecta una
 * configuración SigoaStorage con raíz temporal propia de cada prueba.
 *
 * La suite usa la conexión `tests` (SQLite en memoria) con el esquema
 * mínimo necesario, igual que InspectoresObrasModelTest.
 *
 * @internal
 */
final class InspectorObrasStorageTest extends CIUnitTestCase
{
    use ControllerTestTrait;

    private BaseConnection $conn;

    private string $raiz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->raiz = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sigoa_integr_' . bin2hex(random_bytes(4));

        $configo = new \Config\SigoaStorage();
        $configo->storagePath = $this->raiz;

        Factories::injectMock('config', 'SigoaStorage', $configo);

        $this->conn = db_connect();

        $this->crearEsquema();
        $this->limpiarTablas();
        $this->insertarCatalogos();

        service('session')->set([
            'logged_in' => true,
            'activo'    => true,
            'user_id'   => 10,
            'user_name' => 'Inspector',
            'username'  => 'inspector',
            'roles'     => ['INSPECTOR'],
        ]);
    }

    protected function tearDown(): void
    {
        $this->eliminarTree($this->raiz);

        parent::tearDown();
    }

    public function testObraVigenteCreaEstructuraDeAlmacenamiento(): void
    {
        $this->crearObra(1, 'CENTRO DE SALUD NORTE', 1);
        $this->crearAsignacionVigente(1, 10);

        $resultado = $this->controller(Obras::class)->execute('ver', 1);

        $this->assertTrue($resultado->isOK());
        $this->assertTrue(is_dir($this->raiz . DIRECTORY_SEPARATOR . 'OBR-000001'));
        $this->assertTrue(is_dir($this->raiz . DIRECTORY_SEPARATOR . 'OBR-000001' . DIRECTORY_SEPARATOR . 'IMAGENES'));
        $this->assertTrue(is_dir($this->raiz . DIRECTORY_SEPARATOR . 'OBR-000001' . DIRECTORY_SEPARATOR . 'THUMBNAILS'));
    }

    public function testEstructuraYaExistenteNoFalla(): void
    {
        $this->crearObra(1, 'EDIFICIO MUNICIPAL', 1);
        $this->crearAsignacionVigente(1, 10);

        (new ObraAlmacenamiento($this->raiz))->asegurarEstructuraObra('OBR-000001');

        $resultado = $this->controller(Obras::class)->execute('ver', 1);

        $this->assertTrue($resultado->isOK());
        $this->assertTrue(is_dir($this->raiz . DIRECTORY_SEPARATOR . 'OBR-000001' . DIRECTORY_SEPARATOR . 'IMAGENES'));
        $this->assertTrue(is_dir($this->raiz . DIRECTORY_SEPARATOR . 'OBR-000001' . DIRECTORY_SEPARATOR . 'THUMBNAILS'));
    }

    public function testInspectorNoVigenteRedirigeYNoCreaEstructura(): void
    {
        $this->crearObra(1, 'PLAYÓN DEPORTIVO', 1);

        $resultado = $this->controller(Obras::class)->execute('ver', 1);

        $resultado->assertRedirectTo('/inspector/dashboard');
        $this->assertFalse(file_exists($this->raiz . DIRECTORY_SEPARATOR . 'OBR-000001'));
    }

    public function testObraInexistenteRedirigeYNoCreaEstructura(): void
    {
        $resultado = $this->controller(Obras::class)->execute('ver', 999);

        $resultado->assertRedirectTo('/inspector/dashboard');
        $this->assertFalse(file_exists($this->raiz . DIRECTORY_SEPARATOR . 'OBR-000999'));
    }

    private function crearEsquema(): void
    {
        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('estados_obra') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            estado VARCHAR(60) NOT NULL
        )');

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('tipos_licitacion') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tipo_licitacion VARCHAR(80) NOT NULL
        )');

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('barrios') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre VARCHAR(200) NULL
        )');

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('empresas') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            razon_social VARCHAR(255) NULL
        )');

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('obras') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            codigo VARCHAR(20) NOT NULL,
            expediente_municipal VARCHAR(60) NULL,
            numero_licitacion VARCHAR(30) NULL,
            tipo_licitacion_id INTEGER NULL,
            nombre VARCHAR(200) NOT NULL,
            barrio_id INTEGER NULL,
            empresa_id INTEGER NULL,
            estado_obra_id INTEGER NOT NULL,
            created_at DATETIME NULL
        )');

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('inspectores_obras') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            obra_id INTEGER NOT NULL,
            usuario_id INTEGER NOT NULL,
            fecha_inicio DATE NOT NULL,
            fecha_fin DATE NULL,
            created_at DATETIME NULL
        )');
    }

    private function insertarCatalogos(): void
    {
        $this->conn->table('estados_obra')->insert([
            'id'     => 1,
            'estado' => 'EN EJECUCIÓN',
        ]);

        $this->conn->table('tipos_licitacion')->insert([
            'id'              => 1,
            'tipo_licitacion' => 'LICITACIÓN PÚBLICA',
        ]);
    }

    private function tabla(string $nombre): string
    {
        return $this->conn->prefixTable($nombre);
    }

    private function limpiarTablas(): void
    {
        foreach (['inspectores_obras', 'obras', 'empresas', 'barrios', 'tipos_licitacion', 'estados_obra'] as $tabla) {
            $this->conn->query('DELETE FROM ' . $this->tabla($tabla));
        }
    }

    private function crearObra(int $id, string $nombre, int $estadoId): void
    {
        $this->conn->table('obras')->insert([
            'id'                  => $id,
            'codigo'              => 'OBR-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT),
            'nombre'              => $nombre,
            'tipo_licitacion_id'  => 1,
            'estado_obra_id'      => $estadoId,
        ]);
    }

    private function crearAsignacionVigente(int $obraId, int $usuarioId): void
    {
        $this->conn->table('inspectores_obras')->insert([
            'obra_id'      => $obraId,
            'usuario_id'   => $usuarioId,
            'fecha_inicio' => '2026-03-01',
            'fecha_fin'    => null,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
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