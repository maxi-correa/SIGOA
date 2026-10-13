<?php

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Validación en servidor de las resoluciones de obra.
 *
 * Comprueba que el backend impone las reglas de cada tipo aunque el
 * cliente envíe campos inhabilitados (monto/días) y que las resoluciones
 * informativas (ADJUDICACIÓN/OTRO) exigen observaciones para confirmarse.
 *
 * Usa la conexión `tests` (SQLite en memoria) con un esquema mínimo.
 *
 * @internal
 */
final class ResolucionesValidacionTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private BaseConnection $conn;

    private int $obraId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conn = db_connect();

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('estados_obra') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            estado VARCHAR(60) NOT NULL,
            descripcion TEXT NULL,
            activo INTEGER NOT NULL DEFAULT 1
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

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('barrios') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre VARCHAR(200) NULL,
            activo INTEGER NOT NULL DEFAULT 1
        )');

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('empresas') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            razon_social VARCHAR(200) NULL,
            activo INTEGER NOT NULL DEFAULT 1
        )');

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('tipos_licitacion') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tipo_licitacion VARCHAR(120) NULL,
            activo INTEGER NOT NULL DEFAULT 1
        )');

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('tipos_resolucion') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tipo_resolucion VARCHAR(120) NOT NULL,
            activo INTEGER NOT NULL DEFAULT 1
        )');

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('resoluciones') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            obra_id INTEGER NOT NULL,
            tipo_resolucion_id INTEGER NOT NULL,
            numero INTEGER NULL,
            anio INTEGER NULL,
            fecha_resolucion DATE NULL,
            monto DECIMAL(15,3) NULL,
            dias INTEGER NULL,
            observaciones TEXT NULL,
            confirmada INTEGER NOT NULL DEFAULT 0,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');

        foreach (['resoluciones', 'tipos_resolucion', 'obras', 'estados_obra'] as $tabla) {
            $this->conn->query('DELETE FROM ' . $this->tabla($tabla));
        }

        $this->conn->table('tipos_resolucion')->insertBatch([
            ['id' => 1, 'tipo_resolucion' => 'ADJUDICACIÓN', 'activo' => 1],
            ['id' => 2, 'tipo_resolucion' => 'APROBACIÓN DE CUADRO COMPARATIVO', 'activo' => 1],
            ['id' => 3, 'tipo_resolucion' => 'APROBACIÓN DE AMPLIACIÓN DE PLAZO', 'activo' => 1],
            ['id' => 4, 'tipo_resolucion' => 'OTRO', 'activo' => 1],
        ]);

        $this->conn->table('estados_obra')->insert([
            'estado' => 'EN EJECUCIÓN',
            'activo' => 1,
        ]);

        $estadoId = (int) $this->conn->insertID();

        $this->conn->table('obras')->insert([
            'codigo'         => 'OBRA-001',
            'nombre'         => 'Obra de prueba',
            'estado_obra_id' => $estadoId,
        ]);

        $this->obraId = (int) $this->conn->insertID();
    }

    protected function tearDown(): void
    {
        foreach (['resoluciones', 'tipos_resolucion', 'obras', 'estados_obra'] as $tabla) {
            $this->conn->query('DELETE FROM ' . $this->tabla($tabla));
        }

        parent::tearDown();
    }

    public function testAdjudicacionSeRegistraSinMontoNiDiasConObservaciones(): void
    {
        $resultado = $this->postComoAdmin('/obras/resoluciones/crear', [
            'obra_id'            => $this->obraId,
            'tipo_resolucion_id' => 1,
            'numero'             => '12',
            'anio'               => '2026',
            'fecha_resolucion'   => '2026-01-15',
            'observaciones'      => 'Acta de adjudicación',
        ]);

        $resultado->assertRedirectTo('/obras/resoluciones/' . $this->obraId);

        $fila = $this->conn->table('resoluciones')->get()->getRow();

        $this->assertNotNull($fila);
        $this->assertNull($fila->monto);
        $this->assertNull($fila->dias);
        $this->assertSame('Acta de adjudicación', $fila->observaciones);
    }

    /**
     * @dataProvider tiposInformativos
     */
    public function testLosTiposInformativosRechazanMontoYDias(int $tipoId): void
    {
        $resultado = $this->postComoAdmin('/obras/resoluciones/crear', [
            'obra_id'            => $this->obraId,
            'tipo_resolucion_id' => $tipoId,
            'monto'              => '1000',
            'dias'               => '30',
        ]);

        $resultado->assertRedirectTo('/obras/resoluciones/' . $this->obraId);
        $this->assertSame(0, $this->conn->table('resoluciones')->countAllResults());
    }

    public function testAdjudicacionNoSeConfirmaSinObservaciones(): void
    {
        $id = $this->crearDirecto(1, [
            'numero'           => 12,
            'anio'             => 2026,
            'fecha_resolucion' => '2026-01-15',
            'observaciones'    => null,
        ]);

        $resultado = $this->postComoAdmin('/obras/resoluciones/confirmar', [
            'obra_id'       => $this->obraId,
            'resolucion_id' => $id,
        ]);

        $resultado->assertRedirectTo('/obras/resoluciones/' . $this->obraId);
        $this->assertSame(0, (int) $this->conn->table('resoluciones')->where('id', $id)->get()->getRow()->confirmada);
    }

    public function testAdjudicacionCompletaSeConfirma(): void
    {
        $id = $this->crearDirecto(1, [
            'numero'           => 12,
            'anio'             => 2026,
            'fecha_resolucion' => '2026-01-15',
            'observaciones'    => 'Acta de adjudicación',
        ]);

        $resultado = $this->postComoAdmin('/obras/resoluciones/confirmar', [
            'obra_id'       => $this->obraId,
            'resolucion_id' => $id,
        ]);

        $resultado->assertRedirectTo('/obras/resoluciones/' . $this->obraId);
        $this->assertSame(1, (int) $this->conn->table('resoluciones')->where('id', $id)->get()->getRow()->confirmada);
    }

    public function testElCuadroComparativoSigueRechazandoDias(): void
    {
        $resultado = $this->postComoAdmin('/obras/resoluciones/crear', [
            'obra_id'            => $this->obraId,
            'tipo_resolucion_id' => 2,
            'monto'              => '1500',
            'dias'               => '10',
        ]);

        $resultado->assertRedirectTo('/obras/resoluciones/' . $this->obraId);
        $this->assertSame(0, $this->conn->table('resoluciones')->countAllResults());
    }

    public function testLaAmpliacionDePlazoSigueRechazandoMonto(): void
    {
        $resultado = $this->postComoAdmin('/obras/resoluciones/crear', [
            'obra_id'            => $this->obraId,
            'tipo_resolucion_id' => 3,
            'monto'              => '500',
            'dias'               => '30',
        ]);

        $resultado->assertRedirectTo('/obras/resoluciones/' . $this->obraId);
        $this->assertSame(0, $this->conn->table('resoluciones')->countAllResults());
    }

    /**
     * @return array<string, array{int}>
     */
    public static function tiposInformativos(): array
    {
        return [
            'ADJUDICACIÓN' => [1],
            'OTRO'         => [4],
        ];
    }

    /**
     * @param array<string, mixed> $datos
     */
    private function postComoAdmin(string $url, array $datos)
    {
        $token = csrf_hash();

        return $this->withSession([
            'logged_in' => true,
            'activo'    => true,
            'user_id'   => 1,
            'username'  => 'ADMIN_PRUEBA',
            'user_name' => 'ADMIN PRUEBA',
            'roles'     => ['ADMINISTRADOR'],
        ])->post($url, $datos + ['csrf_test_name' => $token]);
    }

    /**
     * @param array<string, mixed> $datos
     */
    private function crearDirecto(int $tipoId, array $datos): int
    {
        $ahora = date('Y-m-d H:i:s');

        $this->conn->table('resoluciones')->insert(array_merge([
            'obra_id'            => $this->obraId,
            'tipo_resolucion_id' => $tipoId,
            'monto'              => null,
            'dias'               => null,
            'confirmada'         => 0,
            'created_at'         => $ahora,
            'updated_at'         => $ahora,
        ], $datos));

        return (int) $this->conn->insertID();
    }

    private function tabla(string $nombre): string
    {
        return $this->conn->prefixTable($nombre);
    }
}
