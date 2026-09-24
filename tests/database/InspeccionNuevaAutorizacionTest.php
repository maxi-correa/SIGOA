<?php

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Autorización del inicio local de inspecciones (Fase D.2).
 *
 * La ruta `GET /inspector/inspecciones/nueva/{obra}` es la precondición de
 * creación LOCAL: el servidor verifica usuario autenticado (filtros auth +
 * role:INSPECTOR), asignación vigente del inspector y estado de obra que
 * permita nuevas inspecciones (F.7). La inspección en sí NO se crea en el
 * servidor en esta fase: el controlador solo autoriza e inicializa.
 *
 * Usa la conexión `tests` (SQLite en memoria compartida con el grupo) y
 * crea únicamente las tablas mínimas que el flujo necesita.
 *
 * @internal
 */
final class InspeccionNuevaAutorizacionTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private BaseConnection $conn;

    private int $obraId;

    private int $inspectorId;

    private int $estadoEjecucion;

    private int $estadoPrevio;

    private int $estadoFinalizada;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conn = db_connect();

        if ($this->conn->tableExists('obras')) {
            $this->conn->query('DELETE FROM ' . $this->tabla('obras'));
        }
        if ($this->conn->tableExists('usuarios')) {
            $this->conn->query('DELETE FROM ' . $this->tabla('usuarios'));
        }
        if ($this->conn->tableExists('inspectores_obras')) {
            $this->conn->query('DELETE FROM ' . $this->tabla('inspectores_obras'));
        }
        if ($this->conn->tableExists('estados_obra')) {
            $this->conn->query('DELETE FROM ' . $this->tabla('estados_obra'));
        }

        $this->crearTablas();

        $this->estadoEjecucion  = $this->insertarEstado('EN EJECUCIÓN');
        $this->estadoPrevio     = $this->insertarEstado('PREVIO INICIO');
        $this->estadoFinalizada = $this->insertarEstado('FINALIZADA');

        $this->conn->table('obras')->insert([
            'codigo'         => 'OBR-' . strtoupper(dechex((int) (microtime(true) * 1000))),
            'nombre'         => 'OBRA INSPECCIÓN D2',
            'estado_obra_id' => $this->estadoEjecucion,
        ]);
        $this->obraId = (int) $this->conn->insertID();

        $this->conn->table('usuarios')->insert([
            'usuario'       => 'PRUEBA_INSPECTOR_D2',
            'password_hash' => password_hash('Inspector123', PASSWORD_DEFAULT),
            'activo'        => 1,
        ]);
        $this->inspectorId = (int) $this->conn->insertID();
    }

    protected function tearDown(): void
    {
        foreach (['inspectores_obras', 'estados_obra', 'obras', 'usuarios'] as $tabla) {
            if ($this->conn->tableExists($tabla)) {
                $this->conn->query('DELETE FROM ' . $this->tabla($tabla));
            }
        }

        parent::tearDown();
    }

    private function tabla(string $nombre): string
    {
        return $this->conn->prefixTable($nombre);
    }

    private function crearTablas(): void
    {
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

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('usuarios') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre VARCHAR(100) NULL,
            apellido VARCHAR(100) NULL,
            usuario VARCHAR(100) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            email VARCHAR(150) NULL,
            activo TINYINT NOT NULL DEFAULT 1,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('estados_obra') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            estado VARCHAR(60) NOT NULL,
            descripcion TEXT NULL,
            activo TINYINT NOT NULL DEFAULT 1
        )');

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('inspectores_obras') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            obra_id INTEGER NOT NULL,
            usuario_id INTEGER NOT NULL,
            fecha_inicio DATE NULL,
            fecha_fin DATE NULL,
            documento_id INTEGER NULL,
            observaciones TEXT NULL,
            created_at DATETIME NULL
        )');

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('barrios') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre VARCHAR(150) NOT NULL,
            activo TINYINT NOT NULL DEFAULT 1
        )');

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('empresas') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            razon_social VARCHAR(200) NOT NULL,
            activo TINYINT NOT NULL DEFAULT 1
        )');

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('tipos_licitacion') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tipo_licitacion VARCHAR(120) NOT NULL,
            activo TINYINT NOT NULL DEFAULT 1
        )');
    }

    private function insertarEstado(string $nombre): int
    {
        $this->conn->table('estados_obra')->insert([
            'estado'      => $nombre,
            'descripcion' => 'Estado de prueba D.2',
            'activo'      => 1,
        ]);

        return (int) $this->conn->insertID();
    }

    private function sesionInspector(): array
    {
        return [
            'logged_in' => true,
            'activo'    => true,
            'user_id'   => $this->inspectorId,
            'username'  => 'PRUEBA_INSPECTOR_D2',
            'user_name' => 'PRUEBA_INSPECTOR_D2',
            'roles'     => ['INSPECTOR'],
        ];
    }

    private function asignarVigente(int $obraId, int $usuarioId): void
    {
        $this->conn->table('inspectores_obras')->insert([
            'obra_id'     => $obraId,
            'usuario_id'  => $usuarioId,
            'fecha_inicio' => date('Y-m-d'),
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    private function ponerObraEnEstado(int $estadoId): void
    {
        $this->conn->table('obras')->where('id', $this->obraId)->update(['estado_obra_id' => $estadoId]);
    }

    public function testSinSesionRedirigeAlLogin(): void
    {
        $resultado = $this->get('/inspector/inspecciones/nueva/' . $this->obraId);
        $resultado->assertRedirectTo('/login');
    }

    public function testInspectorVigenteConEstadoPermitidoPuedeIniciar(): void
    {
        $this->asignarVigente($this->obraId, $this->inspectorId);

        $resultado = $this->withSession($this->sesionInspector())
            ->get('/inspector/inspecciones/nueva/' . $this->obraId);

        $resultado->assertStatus(200);
        $resultado->assertSee('Guardar inspección');
        $resultado->assertSee('data-obra-id="' . $this->obraId . '"');
        $resultado->assertSee('data-inspector-id="' . $this->inspectorId . '"');
    }

    public function testEstadoPrevioInicioNoPermiteIniciar(): void
    {
        $this->asignarVigente($this->obraId, $this->inspectorId);
        $this->ponerObraEnEstado($this->estadoPrevio);

        $resultado = $this->withSession($this->sesionInspector())
            ->get('/inspector/inspecciones/nueva/' . $this->obraId);

        $resultado->assertRedirectTo('/inspector/obras/ver/' . $this->obraId);
    }

    public function testEstadoFinalizadaNoPermiteIniciar(): void
    {
        $this->asignarVigente($this->obraId, $this->inspectorId);
        $this->ponerObraEnEstado($this->estadoFinalizada);

        $resultado = $this->withSession($this->sesionInspector())
            ->get('/inspector/inspecciones/nueva/' . $this->obraId);

        $resultado->assertRedirectTo('/inspector/obras/ver/' . $this->obraId);
    }

    public function testInspectorNoVigenteNoPuedeIniciar(): void
    {
        $this->conn->table('usuarios')->insert([
            'usuario'       => 'PRUEBA_INSPECTOR_OTRO',
            'password_hash' => password_hash('Inspector123', PASSWORD_DEFAULT),
            'activo'        => 1,
        ]);

        $otroId = (int) $this->conn->insertID();

        $this->asignarVigente($this->obraId, $otroId);

        $resultado = $this->withSession($this->sesionInspector())
            ->get('/inspector/inspecciones/nueva/' . $this->obraId);

        $resultado->assertRedirectTo('/inspector/obras/ver/' . $this->obraId);
    }

    public function testObraInexistenteRedirigeAlDashboard(): void
    {
        $resultado = $this->withSession($this->sesionInspector())
            ->get('/inspector/inspecciones/nueva/999999999');

        $resultado->assertRedirectTo('/inspector/dashboard');
    }
}