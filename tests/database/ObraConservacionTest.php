<?php

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Reglas de servidor del ciclo de recepciones (RP/RD): no se puede ingresar
 * a EN PLAZO DE CONSERVACIÓN sin recepción provisoria ni a FINALIZADA sin
 * recepción definitiva, y no se retrocede desde esos estados.
 *
 * Las obras históricas que ya están en un estado posterior sin recepciones
 * conservan su estado: la guardia solo actúa ante una transición real.
 *
 * @internal
 */
final class ObraConservacionTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private BaseConnection $conn;

    private int $obraId;

    /** @var array<string, int> */
    private array $estados = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->conn = db_connect();

        foreach ([
            'recepciones_definitivas',
            'recepciones_provisorias',
            'neutralizaciones',
            'obras',
            'estados_obra',
        ] as $tabla) {
            $this->conn->query('DROP TABLE IF EXISTS ' . $this->tabla($tabla));
        }

        $this->conn->query('CREATE TABLE ' . $this->tabla('estados_obra') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            estado VARCHAR(60) NOT NULL,
            descripcion TEXT NULL,
            activo INTEGER NOT NULL DEFAULT 1
        )');

        $this->conn->query('CREATE TABLE ' . $this->tabla('obras') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            codigo VARCHAR(20) NOT NULL,
            expediente_municipal VARCHAR(60) NULL,
            numero_licitacion VARCHAR(30) NULL,
            tipo_licitacion_id INTEGER NULL,
            nombre VARCHAR(200) NOT NULL,
            barrio_id INTEGER NULL,
            empresa_id INTEGER NULL,
            estado_obra_id INTEGER NOT NULL,
            fecha_inicio DATE NULL,
            plazo_inicial_confirmado INTEGER NOT NULL DEFAULT 0,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');

        $this->conn->query('CREATE TABLE ' . $this->tabla('neutralizaciones') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            obra_id INTEGER NOT NULL,
            numero INTEGER NOT NULL,
            fecha_inicio DATE NOT NULL,
            fecha_reinicio DATE NULL,
            documento_id INTEGER NULL,
            observaciones TEXT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');

        $this->conn->query('CREATE TABLE ' . $this->tabla('recepciones_provisorias') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            obra_id INTEGER NOT NULL UNIQUE,
            fecha_recepcion DATE NOT NULL,
            documento_id INTEGER NULL,
            observaciones TEXT NULL,
            plazo_conservacion_dias INTEGER NOT NULL,
            fecha_prevista_definitiva DATE NOT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');

        $this->conn->query('CREATE TABLE ' . $this->tabla('recepciones_definitivas') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            obra_id INTEGER NOT NULL UNIQUE,
            fecha_recepcion DATE NOT NULL,
            documento_id INTEGER NULL,
            observaciones TEXT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');

        foreach (['PREVIO INICIO', 'EN EJECUCIÓN', 'NEUTRALIZADA', 'EN PLAZO DE CONSERVACIÓN', 'FINALIZADA'] as $estado) {
            $this->conn->table('estados_obra')->insert(['estado' => $estado, 'activo' => 1]);
            $this->estados[$estado] = (int) $this->conn->insertID();
        }

        $this->obraId = $this->crearObra($this->estados['EN EJECUCIÓN']);
    }

    protected function tearDown(): void
    {
        foreach ([
            'recepciones_definitivas',
            'recepciones_provisorias',
            'neutralizaciones',
            'obras',
            'estados_obra',
        ] as $tabla) {
            $this->conn->query('DROP TABLE IF EXISTS ' . $this->tabla($tabla));
        }

        parent::tearDown();
    }

    public function testNoPuedeEntrarEnPlazoDeConservacionSinRecepcionProvisoria(): void
    {
        $this->postEstado($this->estados['EN PLAZO DE CONSERVACIÓN']);

        $this->assertSame($this->estados['EN EJECUCIÓN'], $this->estadoActual());
    }

    public function testPuedeEntrarEnPlazoDeConservacionConRecepcionProvisoria(): void
    {
        $this->registrarRecepcionProvisoria();

        $this->postEstado($this->estados['EN PLAZO DE CONSERVACIÓN']);

        $this->assertSame($this->estados['EN PLAZO DE CONSERVACIÓN'], $this->estadoActual());
    }

    public function testNoPuedeFinalizarSinRecepcionDefinitiva(): void
    {
        $this->registrarRecepcionProvisoria();
        $this->cambiarEstado($this->estados['EN PLAZO DE CONSERVACIÓN']);

        $this->postEstado($this->estados['FINALIZADA']);

        $this->assertSame($this->estados['EN PLAZO DE CONSERVACIÓN'], $this->estadoActual());
    }

    public function testPuedeFinalizarConRecepcionDefinitiva(): void
    {
        $this->registrarRecepcionProvisoria();
        $this->registrarRecepcionDefinitiva();
        $this->cambiarEstado($this->estados['EN PLAZO DE CONSERVACIÓN']);

        $this->postEstado($this->estados['FINALIZADA']);

        $this->assertSame($this->estados['FINALIZADA'], $this->estadoActual());
    }

    public function testNoPuedeRetrocederDesdePlazoDeConservacion(): void
    {
        $this->registrarRecepcionProvisoria();
        $this->cambiarEstado($this->estados['EN PLAZO DE CONSERVACIÓN']);

        $this->postEstado($this->estados['EN EJECUCIÓN']);

        $this->assertSame($this->estados['EN PLAZO DE CONSERVACIÓN'], $this->estadoActual());
    }

    public function testNoPuedeRetrocederDesdeFinalizada(): void
    {
        $this->registrarRecepcionProvisoria();
        $this->registrarRecepcionDefinitiva();
        $this->cambiarEstado($this->estados['FINALIZADA']);

        $this->postEstado($this->estados['EN PLAZO DE CONSERVACIÓN']);

        $this->assertSame($this->estados['FINALIZADA'], $this->estadoActual());
    }

    public function testObraHistoricaEnPlazoConservaSuEstadoSinRecepcion(): void
    {
        $this->cambiarEstado($this->estados['EN PLAZO DE CONSERVACIÓN']);

        $this->postEstado($this->estados['EN PLAZO DE CONSERVACIÓN'], 'OBRA HISTÓRICA RENOMBRADA');

        $obra = $this->conn->table('obras')->where('id', $this->obraId)->get()->getRow();

        $this->assertSame($this->estados['EN PLAZO DE CONSERVACIÓN'], (int) $obra->estado_obra_id);
        $this->assertSame('OBRA HISTÓRICA RENOMBRADA', $obra->nombre);
    }

    public function testObraHistoricaFinalizadaConservaSuEstadoSinRecepcion(): void
    {
        $this->cambiarEstado($this->estados['FINALIZADA']);

        $this->postEstado($this->estados['FINALIZADA'], 'OBRA FINALIZADA RENOMBRADA');

        $obra = $this->conn->table('obras')->where('id', $this->obraId)->get()->getRow();

        $this->assertSame($this->estados['FINALIZADA'], (int) $obra->estado_obra_id);
        $this->assertSame('OBRA FINALIZADA RENOMBRADA', $obra->nombre);
    }

    private function postEstado(int $estadoId, string $nombre = 'OBRA DE PRUEBA')
    {
        $token = csrf_hash();

        return $this->withSession([
            'logged_in' => true,
            'activo'    => true,
            'user_id'   => 1,
            'username'  => 'ADMIN_PRUEBA',
            'user_name' => 'ADMIN PRUEBA',
            'roles'     => ['ADMINISTRADOR'],
        ])->post('/obras/actualizar', [
            'obra_id'              => $this->obraId,
            'expediente_municipal' => 'EXP-001',
            'nombre'               => $nombre,
            'estado_obra_id'       => $estadoId,
            'csrf_test_name'       => $token,
        ])->assertRedirectTo('/admin/dashboard');
    }

    private function crearObra(int $estadoId): int
    {
        $ahora = date('Y-m-d H:i:s');

        $this->conn->table('obras')->insert([
            'codigo'                   => 'OBR-000001',
            'expediente_municipal'     => 'EXP-001',
            'nombre'                   => 'OBRA DE PRUEBA',
            'estado_obra_id'           => $estadoId,
            'plazo_inicial_confirmado' => 0,
            'created_at'               => $ahora,
            'updated_at'               => $ahora,
        ]);

        return (int) $this->conn->insertID();
    }

    private function registrarRecepcionProvisoria(): void
    {
        $ahora = date('Y-m-d H:i:s');

        $this->conn->table('recepciones_provisorias')->insert([
            'obra_id'                    => $this->obraId,
            'fecha_recepcion'            => '2026-10-23',
            'plazo_conservacion_dias'    => 180,
            'fecha_prevista_definitiva'  => '2027-04-21',
            'created_at'                 => $ahora,
            'updated_at'                 => $ahora,
        ]);
    }

    private function registrarRecepcionDefinitiva(): void
    {
        $ahora = date('Y-m-d H:i:s');

        $this->conn->table('recepciones_definitivas')->insert([
            'obra_id'         => $this->obraId,
            'fecha_recepcion' => '2027-04-21',
            'created_at'      => $ahora,
            'updated_at'      => $ahora,
        ]);
    }

    private function cambiarEstado(int $estadoId): void
    {
        $this->conn->table('obras')->where('id', $this->obraId)->update([
            'estado_obra_id' => $estadoId,
        ]);
    }

    private function estadoActual(): int
    {
        return (int) $this->conn->table('obras')
            ->where('id', $this->obraId)
            ->get()
            ->getRow()
            ->estado_obra_id;
    }

    private function tabla(string $nombre): string
    {
        return $this->conn->prefixTable($nombre);
    }
}
