<?php

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Separación de los guardados de la ficha de obra: el Expte. contable y
 * los datos iniciales de plazo tienen envíos y operaciones de guardado
 * independientes.
 *
 * Comprueba que cada endpoint escribe exclusivamente sus campos, que la
 * inmutabilidad del plazo confirmado se impone en el servidor aunque el
 * cliente envíe los campos, y que se mantienen los roles.
 *
 * Usa la conexión `tests` (SQLite en memoria) con un esquema mínimo.
 *
 * @internal
 */
final class FichaObraGuardadoIndependienteTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private BaseConnection $conn;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conn = db_connect();

        $this->conn->query('DROP TABLE IF EXISTS ' . $this->tabla('obras'));
        $this->conn->query('CREATE TABLE ' . $this->tabla('obras') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            codigo VARCHAR(20) NOT NULL,
            expediente_municipal VARCHAR(60) NULL,
            nombre VARCHAR(200) NOT NULL,
            estado_obra_id INTEGER NOT NULL,
            expediente_contable VARCHAR(50) NULL,
            fecha_inicio DATE NULL,
            plazo_original_valor INTEGER NULL,
            plazo_original_unidad VARCHAR(10) NULL,
            plazo_original_dias INTEGER NULL,
            plazo_inicial_confirmado INTEGER NOT NULL DEFAULT 0,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');
    }

    protected function tearDown(): void
    {
        $this->conn->query('DROP TABLE IF EXISTS ' . $this->tabla('obras'));

        parent::tearDown();
    }

    public function testElExpedienteContableSeGuardaTrasConfirmarSinTocarElPlazo(): void
    {
        $this->insertarObra(2, [
            'expediente_contable'      => null,
            'fecha_inicio'             => '2026-02-02',
            'plazo_original_valor'     => 180,
            'plazo_original_unidad'    => 'DIAS',
            'plazo_original_dias'      => 180,
            'plazo_inicial_confirmado' => 1,
        ]);

        $resultado = $this->postComoAdmin('/obras/ficha/actualizar-expediente', [
            'obra_id'             => 2,
            'expediente_contable' => 'exp-nuevo',
        ]);

        $resultado->assertRedirectTo('/obras/ver/2');

        $obra = $this->obra(2);

        $this->assertSame('EXP-NUEVO', $obra->expediente_contable);
        $this->assertSame(1, (int) $obra->plazo_inicial_confirmado);
        $this->assertSame('2026-02-02', $obra->fecha_inicio);
        $this->assertSame(180, (int) $obra->plazo_original_dias);
    }

    public function testUnaPeticionManipuladaAlGuardarElExpedienteNoModificaElPlazo(): void
    {
        $this->insertarObra(2, [
            'fecha_inicio'             => '2026-02-02',
            'plazo_original_valor'     => 180,
            'plazo_original_unidad'    => 'DIAS',
            'plazo_original_dias'      => 180,
            'plazo_inicial_confirmado' => 1,
        ]);

        $resultado = $this->postComoAdmin('/obras/ficha/actualizar-expediente', [
            'obra_id'             => 2,
            'expediente_contable' => 'EXP-X',
            'fecha_inicio'        => '01/01/2027',
            'plazo_valor'         => '30',
            'plazo_unidad'        => 'DIAS',
        ]);

        $resultado->assertRedirectTo('/obras/ver/2');

        $obra = $this->obra(2);

        $this->assertSame('EXP-X', $obra->expediente_contable);
        $this->assertSame('2026-02-02', $obra->fecha_inicio, 'El guardado del Expte. no debe tocar la fecha de inicio.');
        $this->assertSame(180, (int) $obra->plazo_original_dias, 'El guardado del Expte. no debe tocar el plazo.');
        $this->assertSame(1, (int) $obra->plazo_inicial_confirmado);
    }

    public function testLosDatosInicialesSeGuardanAntesDeConfirmarSinTocarElExpediente(): void
    {
        $this->insertarObra(2, [
            'expediente_contable' => 'EXP-ANTIGUO',
            'fecha_inicio'        => null,
            'plazo_original_dias' => null,
        ]);

        $resultado = $this->postComoAdmin('/obras/ficha/actualizar', [
            'obra_id'     => 2,
            'fecha_inicio' => '02/02/2026',
            'plazo_valor'  => '6',
            'plazo_unidad' => 'MES',
        ]);

        $resultado->assertRedirectTo('/obras/ver/2');

        $obra = $this->obra(2);

        $this->assertSame('2026-02-02', $obra->fecha_inicio);
        $this->assertSame(6, (int) $obra->plazo_original_valor);
        $this->assertSame('MES', $obra->plazo_original_unidad);
        $this->assertSame(180, (int) $obra->plazo_original_dias);
        $this->assertSame('EXP-ANTIGUO', $obra->expediente_contable, 'El guardado de datos de obra no debe tocar el Expte. contable.');
        $this->assertSame(0, (int) $obra->plazo_inicial_confirmado);
    }

    public function testUnaPeticionManipuladaNoModificaElPlazoConfirmado(): void
    {
        $this->insertarObra(2, [
            'fecha_inicio'             => '2026-02-02',
            'plazo_original_valor'     => 180,
            'plazo_original_unidad'    => 'DIAS',
            'plazo_original_dias'      => 180,
            'plazo_inicial_confirmado' => 1,
        ]);

        $this->postComoAdmin('/obras/ficha/actualizar', [
            'obra_id'      => 2,
            'fecha_inicio' => '01/01/2027',
            'plazo_valor'  => '30',
            'plazo_unidad' => 'DIAS',
        ]);

        $obra = $this->obra(2);

        $this->assertSame('2026-02-02', $obra->fecha_inicio, 'El servidor no debe aceptar el cambio de fecha confirmada.');
        $this->assertSame(180, (int) $obra->plazo_original_dias, 'El servidor no debe aceptar el cambio de plazo confirmado.');
        $this->assertSame(1, (int) $obra->plazo_inicial_confirmado);
    }

    public function testElExpteContableDemasiadoLargoNoSeGuarda(): void
    {
        $this->insertarObra(2, ['expediente_contable' => 'EXP-ANTIGUO']);

        $resultado = $this->postComoAdmin('/obras/ficha/actualizar-expediente', [
            'obra_id'             => 2,
            'expediente_contable' => str_repeat('X', 51),
        ]);

        $resultado->assertRedirectTo('/obras/ver/2');
        $this->assertSame('EXP-ANTIGUO', $this->obra(2)->expediente_contable);
    }

    public function testLosGuardadosDeLaFichaRespetanLosRoles(): void
    {
        $this->insertarObra(2, ['expediente_contable' => 'EXP-ANTIGUO']);

        $this->postComoConsulta('/obras/ficha/actualizar-expediente', [
            'obra_id'             => 2,
            'expediente_contable' => 'EXP-NUEVO',
        ])->assertRedirectTo('/dashboard');

        $this->postComoConsulta('/obras/ficha/actualizar', [
            'obra_id'      => 2,
            'fecha_inicio' => '02/02/2026',
            'plazo_valor'  => '180',
            'plazo_unidad' => 'DIAS',
        ])->assertRedirectTo('/dashboard');

        $obra = $this->obra(2);

        $this->assertSame('EXP-ANTIGUO', $obra->expediente_contable);
        $this->assertNull($obra->fecha_inicio);
    }

    /**
     * @param array<string, mixed> $datos
     */
    private function postComoAdmin(string $url, array $datos)
    {
        return $this->withSession($this->sesion(['ADMINISTRADOR']))
            ->post($url, $datos + ['csrf_test_name' => csrf_hash()]);
    }

    /**
     * @param array<string, mixed> $datos
     */
    private function postComoConsulta(string $url, array $datos)
    {
        return $this->withSession($this->sesion(['CONSULTA']))
            ->post($url, $datos + ['csrf_test_name' => csrf_hash()]);
    }

    /**
     * @param list<string> $roles
     *
     * @return array<string, mixed>
     */
    private function sesion(array $roles): array
    {
        return [
            'logged_in' => true,
            'activo'    => true,
            'user_id'   => 1,
            'username'  => 'USUARIO_PRUEBA',
            'user_name' => 'USUARIO PRUEBA',
            'roles'     => $roles,
        ];
    }

    /**
     * @param array<string, mixed> $extras
     */
    private function insertarObra(int $id, array $extras = []): void
    {
        $ahora = date('Y-m-d H:i:s');

        $this->conn->table('obras')->insert(array_merge([
            'id'                       => $id,
            'codigo'                   => 'OBR-00000' . $id,
            'expediente_municipal'     => 'EXP-' . $id,
            'nombre'                   => 'OBRA ' . $id,
            'estado_obra_id'           => 1,
            'expediente_contable'      => null,
            'fecha_inicio'             => null,
            'plazo_original_valor'     => null,
            'plazo_original_unidad'    => null,
            'plazo_original_dias'      => null,
            'plazo_inicial_confirmado' => 0,
            'created_at'               => $ahora,
            'updated_at'               => $ahora,
        ], $extras));
    }

    private function obra(int $id): object
    {
        return $this->conn->table('obras')->where('id', $id)->get()->getRow();
    }

    private function tabla(string $nombre): string
    {
        return $this->conn->prefixTable($nombre);
    }
}
