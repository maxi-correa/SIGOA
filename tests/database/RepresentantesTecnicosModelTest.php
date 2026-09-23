<?php

use App\Models\ObrasRepresentantesTecnicosModel;
use App\Models\RepresentanteTecnicoModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Estado Activo/Inactivo de los representantes técnicos.
 *
 * La suite usa la conexión `tests` (SQLite en memoria). Las migraciones de la
 * aplicación no son compatibles con SQLite, por lo que aquí se crea el esquema
 * mínimo necesario en cada test. No se usa DatabaseTestTrait para no depender
 * de migraciones específicas del grupo de pruebas.
 *
 * @internal
 */
final class RepresentantesTecnicosModelTest extends CIUnitTestCase
{
    private BaseConnection $conn;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conn = db_connect();

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('tipos_titulo_profesional') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            titulo VARCHAR(150) NOT NULL,
            activo INTEGER NOT NULL DEFAULT 1
        )');

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('representantes_tecnicos') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            titulo_profesional_id INTEGER NOT NULL,
            nombre VARCHAR(100) NOT NULL,
            apellido VARCHAR(100) NOT NULL,
            matricula VARCHAR(30) NULL UNIQUE,
            activo INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('obras_representantes_tecnicos') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            obra_id INTEGER NOT NULL,
            representante_tecnico_id INTEGER NOT NULL,
            fecha_inicio DATE NOT NULL,
            fecha_fin DATE NULL,
            created_at DATETIME NULL
        )');

        $this->limpiarTablas();

        $this->conn->table('tipos_titulo_profesional')->insert([
            'id'     => 1,
            'titulo' => 'ARQUITECTO',
            'activo' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        $this->limpiarTablas();

        parent::tearDown();
    }

    private function tabla(string $nombre): string
    {
        return $this->conn->prefixTable($nombre);
    }

    private function limpiarTablas(): void
    {
        foreach (['obras_representantes_tecnicos', 'representantes_tecnicos', 'tipos_titulo_profesional'] as $tabla) {
            $this->conn->query('DELETE FROM ' . $this->tabla($tabla));
        }
    }

    public function testAltaQuedaActivaPorDefecto(): void
    {
        $modelo = new RepresentanteTecnicoModel();

        $id = $modelo->crear([
            'titulo_profesional_id' => 1,
            'nombre'                => 'ANA',
            'apellido'              => 'GÓMEZ',
            'matricula'             => null,
        ]);

        $representante = $modelo->find($id);

        $this->assertNotNull($representante);
        $this->assertSame(1, (int) $representante->activo);
    }

    public function testDesactivarSinAsignacionVigente(): void
    {
        $modelo        = new RepresentanteTecnicoModel();
        $asignaciones  = new ObrasRepresentantesTecnicosModel();
        $id            = $this->crearRepresentante($modelo);

        $this->crearAsignacion($asignaciones, $id, '2026-01-01', '2026-06-30');

        $this->assertFalse($asignaciones->tieneAsignacionVigente($id));
        $this->assertTrue($modelo->cambiarActivo($id, 0));
        $this->assertSame(0, (int) $modelo->find($id)->activo);
    }

    public function testNoSePuedeDesactivarConAsignacionVigente(): void
    {
        $modelo       = new RepresentanteTecnicoModel();
        $asignaciones = new ObrasRepresentantesTecnicosModel();
        $id           = $this->crearRepresentante($modelo);

        $this->crearAsignacion($asignaciones, $id, '2026-01-01', null);

        $this->assertTrue($asignaciones->tieneAsignacionVigente($id));
    }

    public function testReactivarRepresentanteInactivo(): void
    {
        $modelo = new RepresentanteTecnicoModel();
        $id     = $this->crearRepresentante($modelo);

        $modelo->cambiarActivo($id, 0);
        $this->assertTrue($modelo->cambiarActivo($id, 1));
        $this->assertSame(1, (int) $modelo->find($id)->activo);
    }

    public function testListarActivasExcluyeInactivos(): void
    {
        $modelo = new RepresentanteTecnicoModel();

        $activoId   = $this->crearRepresentante($modelo, 'ACTIVO');
        $inactivoId = $this->crearRepresentante($modelo, 'INACTIVO');
        $modelo->cambiarActivo($inactivoId, 0);

        $idsActivas = array_map(
            static fn ($representante) => (int) $representante->id,
            $modelo->listarActivas()
        );

        $this->assertContains($activoId, $idsActivas);
        $this->assertNotContains($inactivoId, $idsActivas);

        $idsConTitulo = array_map(
            static fn ($representante) => (int) $representante->id,
            $modelo->listarActivasConTitulo()
        );

        $this->assertContains($activoId, $idsConTitulo);
        $this->assertNotContains($inactivoId, $idsConTitulo);
    }

    public function testHistorialSeConservaAlDesactivar(): void
    {
        $modelo       = new RepresentanteTecnicoModel();
        $asignaciones = new ObrasRepresentantesTecnicosModel();
        $id           = $this->crearRepresentante($modelo);

        $this->crearAsignacion($asignaciones, $id, '2026-01-01', '2026-06-30');
        $modelo->cambiarActivo($id, 0);

        $historial = $asignaciones->listarHistorial(1);

        $this->assertCount(1, $historial);
        $this->assertSame($id, (int) $historial[0]->representante_tecnico_id);
    }

    public function testAsignacionVigenteSigueSiendoConsultableConRepresentanteInactivo(): void
    {
        $modelo       = new RepresentanteTecnicoModel();
        $asignaciones = new ObrasRepresentantesTecnicosModel();
        $id           = $this->crearRepresentante($modelo);

        $this->crearAsignacion($asignaciones, $id, '2026-01-01', null);
        $modelo->cambiarActivo($id, 0);

        $vigente = $asignaciones->findVigenteConRepresentante(1);

        $this->assertNotNull($vigente);
        $this->assertSame($id, (int) $vigente->representante_tecnico_id);
        $this->assertSame('GÓMEZ', $vigente->representante_apellido);
    }

    public function testNoExisteEliminacionFisicaEnElControlador(): void
    {
        $this->assertFalse(method_exists(\App\Controllers\RepresentantesTecnicos::class, 'eliminar'));
        $this->assertTrue(method_exists(\App\Controllers\RepresentantesTecnicos::class, 'cambiarEstado'));
    }

    private function crearRepresentante(RepresentanteTecnicoModel $modelo, string $apellido = 'GÓMEZ'): int
    {
        return $modelo->crear([
            'titulo_profesional_id' => 1,
            'nombre'                => 'ANA',
            'apellido'              => $apellido,
            'matricula'             => null,
        ]);
    }

    private function crearAsignacion(
        ObrasRepresentantesTecnicosModel $asignaciones,
        int $representanteId,
        string $fechaInicio,
        ?string $fechaFin
    ): void {
        $asignaciones->insert([
            'obra_id'                  => 1,
            'representante_tecnico_id' => $representanteId,
            'fecha_inicio'             => $fechaInicio,
            'fecha_fin'                => $fechaFin,
            'created_at'               => date('Y-m-d H:i:s'),
        ]);
    }
}
