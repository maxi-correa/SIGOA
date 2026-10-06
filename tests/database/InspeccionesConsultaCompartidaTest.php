<?php

use App\Models\ObraModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Consulta del historial de inspecciones por rol (Fase E.6).
 *
 * Es la única navegación `Obra → Fechas → Inspecciones → Fotografías`, y esta
 * prueba verifica lo que cambia respecto de E.3–E.5:
 *
 * * el listado de obras consultables del inspector incluye las obras cuya
 *   asignación **ya se cerró**, y funciona aunque no tenga ninguna vigente;
 * * el listado de CONSULTA incluye todas las obras;
 * * la misma pantalla histórica se sirve a todos los roles desde la ruta
 *   compartida `/inspecciones/...`, y cada uno vuelve a su punto de entrada;
 * * el inspector conserva la asignación vigente como condición de **alta**, y
 *   el listado no ofrece ninguna acción de alta;
 * * la galería se sirve por la ruta compartida con la misma autorización.
 *
 * Usa la conexión `tests` (SQLite en memoria compartida con el grupo) y crea
 * únicamente las tablas mínimas que estas pantallas necesitan.
 *
 * @internal
 */
final class InspeccionesConsultaCompartidaTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private BaseConnection $conn;

    private int $estadoEjecucion;

    private int $estadoFinalizada;

    private int $inspectorId;

    private int $otroInspectorId;

    private int $usuarioConsultaId;

    /** Obra con asignación vigente del inspector de la prueba. */
    private int $obraVigente;

    /** Obra con asignación ya cerrada del inspector de la prueba. */
    private int $obraHistorica;

    /** Obra de otro inspector: el de la prueba nunca estuvo a cargo. */
    private int $obraAjena;

    /** @var list<int> Obras creadas por esta prueba (para su limpieza). */
    private array $obrasIds = [];

    /** @var list<int> Usuarios creados por esta prueba (para su limpieza). */
    private array $usuariosIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->conn = db_connect();

        $this->crearTablas();

        $this->estadoEjecucion  = $this->insertarEstado('EN EJECUCIÓN');
        $this->estadoFinalizada = $this->insertarEstado('FINALIZADA');

        $this->inspectorId       = $this->crearUsuario('PRUEBA_INSPECTOR_E6');
        $this->otroInspectorId   = $this->crearUsuario('PRUEBA_INSPECTOR_E6_OTRO');
        $this->usuarioConsultaId = $this->crearUsuario('PRUEBA_CONSULTA_E6');

        $this->obraVigente   = $this->crearObra('OBRA E6 VIGENTE', $this->estadoEjecucion);
        $this->obraHistorica = $this->crearObra('OBRA E6 HISTORICA', $this->estadoFinalizada);
        $this->obraAjena     = $this->crearObra('OBRA E6 AJENA', $this->estadoEjecucion);

        $this->asignar($this->obraVigente, $this->inspectorId, null);
        $this->asignar($this->obraHistorica, $this->inspectorId, '2026-05-31');
        $this->asignar($this->obraAjena, $this->otroInspectorId, null);

        $this->registrarInspeccion('e6000001-0000-4000-8000-000000000001', $this->obraVigente);
        $this->registrarInspeccion('e6000002-0000-4000-8000-000000000002', $this->obraHistorica);
        $this->registrarInspeccion('e6000003-0000-4000-8000-000000000003', $this->obraAjena);
    }

    protected function tearDown(): void
    {
        /* La conexión `tests` es compartida: solo se borran las filas creadas
           por esta prueba, en orden de dependencia (hijas antes que padres). */
        $this->conn->table('inspecciones')->whereIn('obra_id', $this->obrasIds)->delete();
        $this->conn->table('inspectores_obras')->whereIn('obra_id', $this->obrasIds)->delete();
        $this->conn->table('obras')->whereIn('id', $this->obrasIds)->delete();
        $this->conn->table('usuarios')->whereIn('id', $this->usuariosIds)->delete();
        $this->conn->table('estados_obra')
            ->whereIn('id', [$this->estadoEjecucion, $this->estadoFinalizada])
            ->delete();

        parent::tearDown();
    }

    /* ----------------------------------------------------------------
     * Listado de obras consultables
     * ---------------------------------------------------------------- */

    public function testElListadoDelInspectorIncluyeLasObrasCuyaAsignacionYaCerro(): void
    {
        $resultado = $this->withSession($this->sesion(['INSPECTOR'], $this->inspectorId))
            ->get('/inspector/inspecciones');

        $resultado->assertStatus(200);
        $resultado->assertSee('OBRA E6 VIGENTE');
        $resultado->assertSee('OBRA E6 HISTORICA');
        $resultado->assertDontSee('OBRA E6 AJENA');
    }

    public function testElListadoDelInspectorFuncionaSinNingunaAsignacionVigente(): void
    {
        /* Se cierran todas las asignaciones del inspector: queda sin ninguna obra
           a cargo y el listado debe seguir existiendo, con su historial. */
        $this->conn->table('inspectores_obras')
            ->where('usuario_id', $this->inspectorId)
            ->groupStart()
            ->where('fecha_fin', null)
            ->groupEnd()
            ->update(['fecha_fin' => '2026-06-30']);

        $resultado = $this->withSession($this->sesion(['INSPECTOR'], $this->inspectorId))
            ->get('/inspector/inspecciones');

        $resultado->assertStatus(200);
        $resultado->assertSee('OBRA E6 VIGENTE');
        $resultado->assertSee('OBRA E6 HISTORICA');
        $resultado->assertDontSee('No hay obras para consultar');
    }

    public function testElListadoDeConsultaMuestraTodasLasObras(): void
    {
        $resultado = $this->withSession($this->sesion(['CONSULTA'], $this->usuarioConsultaId))
            ->get('/consulta/inspecciones');

        $resultado->assertStatus(200);
        $resultado->assertSee('OBRA E6 VIGENTE');
        $resultado->assertSee('OBRA E6 HISTORICA');
        $resultado->assertSee('OBRA E6 AJENA');
    }

    public function testElListadoNoOfreceElAltaDeInspecciones(): void
    {
        $puntos = [
            ['/inspector/inspecciones', 'INSPECTOR', $this->inspectorId],
            ['/consulta/inspecciones', 'CONSULTA', $this->usuarioConsultaId],
        ];

        foreach ($puntos as [$ruta, $rol, $usuarioId]) {
            $resultado = $this->withSession($this->sesion([$rol], $usuarioId))->get($ruta);

            $resultado->assertStatus(200);
            $this->assertStringNotContainsString(
                'inspecciones/nueva',
                (string) $resultado->getBody(),
                "El listado {$ruta} no debe ofrecer el alta de inspecciones."
            );
        }
    }

    public function testElListadoNoRepiteUnaObraConVariosPeriodosDeAsignacion(): void
    {
        /* Un segundo período del mismo inspector sobre la misma obra: la
           tarjeta debe seguir apareciendo una sola vez. */
        $this->asignar($this->obraVigente, $this->inspectorId, '2026-01-15');

        $obras = (new ObraModel())->listarParaConsulta($this->inspectorId);

        $ids = array_map(static fn (object $obra): int => (int) $obra->id, $obras);

        $this->assertSame(
            1,
            count(array_keys($ids, $this->obraVigente, true)),
            'Una obra con varios períodos no debe duplicarse en el listado.'
        );
    }

    public function testElListadoDeConsultaResuelveElInspectorYElRepresentanteVigentes(): void
    {
        $this->conn->table('usuarios')->where('id', $this->inspectorId)
            ->update(['nombre' => 'NOMBRE', 'apellido' => 'APELLIDO INSPECTOR']);

        $obras = (new ObraModel())->listarParaConsulta();

        $porId = [];

        foreach ($obras as $obra) {
            $porId[(int) $obra->id] = $obra;
        }

        $this->assertArrayHasKey($this->obraVigente, $porId);
        $this->assertSame('APELLIDO INSPECTOR', $porId[$this->obraVigente]->inspector_apellido);
        $this->assertSame('EN EJECUCIÓN', $porId[$this->obraVigente]->estado_nombre);
        $this->assertNull(
            $porId[$this->obraVigente]->representante_nombre,
            'Una obra sin representante vigente se muestra igual, sin representante.'
        );
    }

    /* ----------------------------------------------------------------
     * Navegación compartida
     * ---------------------------------------------------------------- */

    public function testElInspectorConsultaUnaObraCuyaAsignacionCerro(): void
    {
        $resultado = $this->withSession($this->sesion(['INSPECTOR'], $this->inspectorId))
            ->get('/inspecciones/ver/' . $this->obraHistorica);

        $resultado->assertStatus(200);
        $resultado->assertSee('1 inspección registrada');
        $resultado->assertSee('FINALIZADA');
    }

    public function testElInspectorNoConsultaLaObraDeOtroInspector(): void
    {
        $resultado = $this->withSession($this->sesion(['INSPECTOR'], $this->inspectorId))
            ->get('/inspecciones/ver/' . $this->obraAjena);

        $resultado->assertRedirectTo('/inspector/dashboard');
    }

    public function testConsultaAccedeAlHistoricoDeLaObraDeSuDashboard(): void
    {
        $resultado = $this->withSession($this->sesion(['CONSULTA'], $this->usuarioConsultaId))
            ->get('/inspecciones/ver/' . $this->obraAjena);

        $resultado->assertStatus(200);
        $resultado->assertSee('OBRA E6 AJENA');
        $resultado->assertSee('Volver a inspecciones');
    }

    public function testElRolAdministrativoVuelveALaFichaDeLaObra(): void
    {
        $resultado = $this->withSession($this->sesion(['ADMINISTRADOR'], $this->usuarioConsultaId))
            ->get('/inspecciones/ver/' . $this->obraAjena);

        $resultado->assertStatus(200);
        $resultado->assertSee('Volver a la obra');
        $this->assertStringContainsString(
            '/obras/ver/' . $this->obraAjena,
            (string) $resultado->getBody()
        );
    }

    public function testElDetalleEsLaMismaPantallaParaTodosLosRoles(): void
    {
        $resultado = $this->withSession($this->sesion(['CONSULTA'], $this->usuarioConsultaId))
            ->get('/inspecciones/detalle/' . $this->inspeccionIdDe($this->obraAjena));

        $resultado->assertStatus(200);
        $resultado->assertSee('OBRA E6 AJENA');
        $this->assertStringContainsString(
            '/inspecciones/ver/' . $this->obraAjena,
            (string) $resultado->getBody()
        );
    }

    public function testElDetalleDeUnaInspeccionDeObraAjenaSeRechaza(): void
    {
        $resultado = $this->withSession($this->sesion(['INSPECTOR'], $this->inspectorId))
            ->get('/inspecciones/detalle/' . $this->inspeccionIdDe($this->obraAjena));

        $resultado->assertRedirectTo('/inspector/dashboard');
    }

    public function testLaGaleriaSeSirvePorLaRutaCompartidaConAutorizacionDeConsulta(): void
    {
        /* Un uuid inexistente responde 404 a quien sí puede consultar: la ruta
           existe y está autorizada, y a la vez no sirve de oráculo. */
        $resultado = $this->withSession($this->sesion(['CONSULTA'], $this->usuarioConsultaId))
            ->get('/inspecciones/fotografias/ver/e6999999-9999-4999-8999-999999999999');

        $resultado->assertStatus(404);
    }

    public function testLaGaleriaCompartidaNoSeAbreSinSesion(): void
    {
        $resultado = $this->get('/inspecciones/fotografias/mini/e6999999-9999-4999-8999-999999999999');

        $resultado->assertRedirectTo('/login');
    }

    public function testElSidebarOfreceInspeccionesAlInspectorYAConsulta(): void
    {
        $inspector = $this->withSession($this->sesion(['INSPECTOR'], $this->inspectorId))
            ->get('/inspector/inspecciones');

        $this->assertStringContainsString(
            '/inspector/inspecciones',
            (string) $inspector->getBody()
        );

        $consulta = $this->withSession($this->sesion(['CONSULTA'], $this->usuarioConsultaId))
            ->get('/consulta/inspecciones');

        $this->assertStringContainsString(
            '/consulta/inspecciones',
            (string) $consulta->getBody()
        );
    }

    public function testElSuperadministradorNoTieneListadoPropioDeInspecciones(): void
    {
        /* Los roles administrativos entran desde la obra ya seleccionada: no
           existe listado global para ellos, así que la ruta no existe. */
        $this->expectException(PageNotFoundException::class);

        $this->withSession($this->sesion(['SUPERADMINISTRADOR'], $this->usuarioConsultaId))
            ->get('/inspecciones');
    }

    /* ----------------------------------------------------------------
     * Utilidades
     * ---------------------------------------------------------------- */

    private function crearObra(string $nombre, int $estadoId): int
    {
        $this->conn->table('obras')->insert([
            'codigo'               => 'OBR-' . strtoupper(dechex((int) (microtime(true) * 1000000))),
            'nombre'               => $nombre,
            'expediente_municipal' => 'EXP ' . strtoupper(dechex((int) (microtime(true) * 1000000))),
            'estado_obra_id'       => $estadoId,
            'created_at'           => date('Y-m-d H:i:s'),
        ]);

        $id = (int) $this->conn->insertID();

        $this->obrasIds[] = $id;

        return $id;
    }

    private function crearUsuario(string $nombreUsuario): int
    {
        $this->conn->table('usuarios')->insert([
            'nombre'        => 'PRUEBA',
            'apellido'      => $nombreUsuario,
            'usuario'       => $nombreUsuario,
            'password_hash' => password_hash('Inspector123', PASSWORD_DEFAULT),
            'activo'        => 1,
        ]);

        $id = (int) $this->conn->insertID();

        $this->usuariosIds[] = $id;

        return $id;
    }

    private function insertarEstado(string $nombre): int
    {
        $this->conn->table('estados_obra')->insert([
            'estado'      => $nombre,
            'descripcion' => 'Estado de prueba E.6',
            'activo'      => 1,
        ]);

        return (int) $this->conn->insertID();
    }

    /**
     * Asigna un inspector a una obra.
     *
     * `fechaFin` en `null` deja la asignación vigente; una fecha la cierra, que
     * es el caso que E.6 habilita a consultar.
     */
    private function asignar(int $obraId, int $usuarioId, ?string $fechaFin): void
    {
        $this->conn->table('inspectores_obras')->insert([
            'obra_id'      => $obraId,
            'usuario_id'   => $usuarioId,
            'fecha_inicio' => '2026-01-01',
            'fecha_fin'    => $fechaFin,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    private function registrarInspeccion(string $uuid, int $obraId): int
    {
        $this->conn->table('inspecciones')->insert([
            'uuid'             => $uuid,
            'obra_id'          => $obraId,
            'inspector_id'     => $this->inspectorId,
            'fecha_inspeccion' => '2026-07-15',
            'hora_inspeccion'  => '10:30:00',
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->conn->insertID();
    }

    private function inspeccionIdDe(int $obraId): int
    {
        return (int) $this->conn->table('inspecciones')
            ->select('id')
            ->where('obra_id', $obraId)
            ->get()
            ->getRow()
                ->id;
    }

    private function sesion(array $roles, int $usuarioId): array
    {
        return [
            'logged_in' => true,
            'activo'    => true,
            'user_id'   => $usuarioId,
            'username'  => 'PRUEBA_E6',
            'user_name' => 'PRUEBA E6',
            'roles'     => $roles,
        ];
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

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('inspecciones') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            uuid VARCHAR(36) NOT NULL,
            obra_id INTEGER NOT NULL,
            inspector_id INTEGER NULL,
            fecha_inspeccion DATE NOT NULL,
            hora_inspeccion TIME NULL,
            observacion TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )');

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('fotografias') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            uuid VARCHAR(36) NOT NULL UNIQUE,
            inspeccion_id INTEGER NOT NULL,
            nombre_archivo VARCHAR(255) NULL,
            ruta_relativa VARCHAR(500) NULL,
            ruta_thumbnail VARCHAR(500) NULL,
            extension VARCHAR(10) NULL,
            mime_type VARCHAR(100) NULL,
            tamano_bytes BIGINT NULL,
            ancho INTEGER NULL,
            alto INTEGER NULL,
            fecha_hora_captura DATETIME NULL,
            fecha_hora_carga DATETIME NULL,
            latitud DECIMAL(10,7) NULL,
            longitud DECIMAL(10,7) NULL,
            dispositivo VARCHAR(255) NULL,
            anulada TINYINT NOT NULL DEFAULT 0,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');

        /* La conexión es compartida: las columnas se declaran como en el
           esquema real, no en una versión recortada. */
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

        /* `ObraModel::findDetalle()` resuelve los catálogos de la ficha. */
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
}