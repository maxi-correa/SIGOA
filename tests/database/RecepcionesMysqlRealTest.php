<?php

use App\Models\AuditoriaModel;
use App\Models\RecepcionDefinitivaModel;
use App\Models\RecepcionProvisoriaModel;
use App\Services\RecepcionCorreccionService;
use App\Services\RecepcionProvisoriaService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database as DatabaseConfig;

/**
 * Verificación de recepciones contra MySQL real (RP/RD).
 *
 * Crea una base descartable en el mismo servidor, ejecuta las migraciones
 * reales del proyecto y verifica en ese motor:
 *  - las restricciones reales (UNIQUE por obra, CHECK de plazo, FK de acta);
 *  - el flujo de alta de RP y RD con auditoría y cambios de estado;
 *  - el comportamiento frente a una transacción concurrente sobre la obra (el
 *    lock de fila impide el alta; no aísla por sí solo el `FOR UPDATE`);
 *  - la atomicidad de la corrección conjunta con un fallo real de auditoría;
 *  - el historial y la pantalla.
 *
 * La base de la aplicación no se toca. Antes de cualquier borrado se verifica
 * que la conexión apunte a la base descartable.
 *
 * Se omite si el servidor no es MySQL/MariaDB o no permite crear bases.
 * Excluir con `phpunit --exclude-group mysql-real`.
 *
 * @internal
 */
#[\PHPUnit\Framework\Attributes\Group('mysql-real')]
final class RecepcionesMysqlRealTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private const BD_DESCARTABLE = 'sigoa_b6_descartable';

    private static ?array $configTestsOriginal = null;

    private static array $instanciasOriginal = [];

    private static bool $basePreparada = false;

    /** Configuración MySQL de la base descartable (sobrevive al reinicio de servicios entre pruebas). */
    private static ?array $configMysql = null;

    private BaseConnection $conn;

    private int $usuarioId = 0;

    private int $obraId = 0;

    private int $tipoProvisoriaId = 0;

    private int $tipoDefinitivaId = 0;

    /** @var array<string, int> */
    private array $estados = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $dbConfig = config(DatabaseConfig::class);

        self::$configTestsOriginal = $dbConfig->tests;
        self::$instanciasOriginal  = self::instanciasCompartidas();

        if (! self::prepararBaseDescartable($dbConfig)) {
            self::restituirConfig();

            self::markTestSkipped(
                'El servidor configurado no es MySQL/MariaDB o no permite crear una base descartable: '
                . 'la verificación contra MySQL real no aplica en este entorno.'
            );
        }

        self::$basePreparada = true;
        self::$configMysql   = $dbConfig->tests;

        (new \CodeIgniter\Database\MigrationRunner(config('Migrations'), 'tests'))->latest('tests');
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$basePreparada) {
            self::eliminarBaseDescartable();
        }

        self::restituirConfig();
        self::$basePreparada = false;

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->conn = db_connect();

        $base = (string) $this->conn->query('SELECT DATABASE() AS base')->getRow()->base;
        $this->assertSame(
            self::BD_DESCARTABLE,
            $base,
            'Guarda: la conexión de prueba debe apuntar a la base descartable, nunca a la de la aplicación.'
        );

        $this->limpiarYSembrar();
    }

    protected function tearDown(): void
    {
        $this->conn->query('DROP TRIGGER IF EXISTS trg_b6_fallo_auditoria_rd');

        parent::tearDown();
    }

    /* =================================================================
       Restricciones reales del esquema
       ================================================================= */

    public function testEsquemaRealImponeUnaRecepcionProvisoriaPorObra(): void
    {
        $this->crearProvisoria('2025-01-01', 120);

        $this->assertTrue(
            $this->fallaAlInsertar(fn () => $this->insertarProvisoriaCruda($this->obraId, '2025-02-01', 120)),
            'La base real debe rechazar una segunda recepción provisoria para la misma obra.'
        );
    }

    public function testEsquemaRealImponeUnaRecepcionDefinitivaPorObra(): void
    {
        $this->crearProvisoria('2025-01-01', 120);
        $this->crearDefinitiva('2025-05-01');

        $this->assertTrue(
            $this->fallaAlInsertar(fn () => $this->conn->table('recepciones_definitivas')->insert([
                'obra_id'         => $this->obraId,
                'fecha_recepcion' => '2025-05-02',
                'created_at'      => date('Y-m-d H:i:s'),
                'updated_at'      => date('Y-m-d H:i:s'),
            ])),
            'La base real debe rechazar una segunda recepción definitiva para la misma obra.'
        );
    }

    public function testEsquemaRealRechazaUnPlazoNoPositivo(): void
    {
        $this->assertTrue(
            $this->fallaAlInsertar(fn () => $this->insertarProvisoriaCruda($this->obraId, '2025-01-01', 0)),
            'El CHECK de plazo_conservacion_dias > 0 debe existir en la base real.'
        );
    }

    public function testEsquemaRealRechazaUnActaInexistente(): void
    {
        $this->assertTrue(
            $this->fallaAlInsertar(fn () => $this->conn->table('recepciones_provisorias')->insert([
                'obra_id'                   => $this->obraId,
                'fecha_recepcion'           => '2025-01-01',
                'documento_id'              => 999999,
                'plazo_conservacion_dias'   => 120,
                'fecha_prevista_definitiva' => '2025-05-01',
                'created_at'                => date('Y-m-d H:i:s'),
                'updated_at'                => date('Y-m-d H:i:s'),
            ])),
            'La FK documento_id debe impedir vincular un acta inexistente.'
        );
    }

    /* =================================================================
       Flujo completo por HTTP contra MySQL real
       ================================================================= */

    public function testAltaDeProvisoriaYDefinitivaConAuditoriaYCambiosDeEstado(): void
    {
        $this->postComoAdmin('/obras/recepciones/provisoria', [
            'obra_id'                 => $this->obraId,
            'fecha_recepcion'         => '2025-01-01',
            'plazo_conservacion_dias' => '120',
        ])->assertRedirectTo('/obras/recepciones/' . $this->obraId);

        $this->assertSame($this->estados['EN PLAZO DE CONSERVACIÓN'], $this->estadoActual());

        $provisoria = $this->conn->table('recepciones_provisorias')->get()->getRow();
        $this->assertSame('2025-05-01', $provisoria->fecha_prevista_definitiva);

        $this->postComoAdmin('/obras/recepciones/definitiva', [
            'obra_id'         => $this->obraId,
            'fecha_recepcion' => '2025-05-01',
        ])->assertRedirectTo('/obras/recepciones/' . $this->obraId);

        $this->assertSame($this->estados['FINALIZADA'], $this->estadoActual());
        $this->assertSame(1, $this->conn->table('recepciones_definitivas')->countAllResults());

        $this->assertSame(1, $this->contarAuditoria('ALTA', 'recepciones_provisorias'));
        $this->assertSame(1, $this->contarAuditoria('ALTA', 'recepciones_definitivas'));
        $this->assertSame(2, $this->contarAuditoria('CAMBIO_ESTADO', 'obras'));
        $this->assertSame(1, $this->contarAuditoria('CAMBIO', 'obras'), 'El plazo 120 difiere del 180 por defecto.');
    }

    public function testRegularizacionDeLaProvisoriaDesdeElActaNoCierraLaObra(): void
    {
        $actaId = $this->crearDocumento($this->tipoProvisoriaId);

        $this->postComoAdmin('/obras/recepciones/provisoria', [
            'obra_id'                 => $this->obraId,
            'fecha_recepcion'         => '2025-01-01',
            'plazo_conservacion_dias' => '120',
            'documento_id'            => (string) $actaId,
        ])->assertRedirectTo('/obras/recepciones/' . $this->obraId);

        $provisoria = $this->conn->table('recepciones_provisorias')->get()->getRow();

        $this->assertSame($actaId, (int) $provisoria->documento_id);
        $this->assertSame($this->estados['EN PLAZO DE CONSERVACIÓN'], $this->estadoActual());
        $this->assertSame(0, $this->conn->table('recepciones_definitivas')->countAllResults());
    }

    /* =================================================================
       Concurrencia real: transacción concurrente sobre la obra
       ================================================================= */

    public function testUnaTransaccionConcurrenteSobreLaObraImpideElAltaSinDatosParciales(): void
    {
        $otra = DatabaseConfig::connect(self::$configMysql, false);

        try {
            $otra->query('START TRANSACTION');
            $otra->query('SELECT id FROM obras WHERE id = ? FOR UPDATE', [$this->obraId]);

            $this->conn->query('SET SESSION innodb_lock_wait_timeout = 1');

            $resultado = (new RecepcionProvisoriaService())->registrar(
                $this->obraId,
                ['fecha_recepcion' => '2025-01-01', 'plazo_conservacion_dias' => 120, 'observaciones' => null, 'documento_id' => null],
                $this->usuarioId
            );

            $this->assertFalse($resultado['registrada'], 'Con la obra bloqueada por otra transacción, el alta no debe completarse.');
            $this->assertSame(0, $this->conn->table('recepciones_provisorias')->countAllResults());
            $this->assertSame($this->estados['EN EJECUCIÓN'], $this->estadoActual());
        } finally {
            $otra->query('ROLLBACK');
            $otra->close();
            $this->conn->query('SET SESSION innodb_lock_wait_timeout = DEFAULT');
        }

        $resultado = (new RecepcionProvisoriaService())->registrar(
            $this->obraId,
            ['fecha_recepcion' => '2025-01-01', 'plazo_conservacion_dias' => 120, 'observaciones' => null, 'documento_id' => null],
            $this->usuarioId
        );

        $this->assertTrue($resultado['registrada'], 'Liberada la obra, el mismo alta debe completarse.');
    }

    /* =================================================================
       Atomicidad real: fallo de auditoría dentro de la transacción
       ================================================================= */

    public function testLaCorreccionConjuntaRevierteAmbasSiFallaLaAuditoriaEnMysql(): void
    {
        $this->crearProvisoria('2025-01-01', 120);
        $this->crearDefinitiva('2025-05-01');
        $this->cambiarEstado($this->estados['FINALIZADA']);

        $this->conn->query(
            "CREATE TRIGGER trg_b6_fallo_auditoria_rd BEFORE INSERT ON auditoria FOR EACH ROW
             BEGIN
                 IF NEW.entidad = 'recepciones_definitivas' THEN
                     SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fallo forzado';
                 END IF;
             END"
        );

        $resultado = (new RecepcionCorreccionService())->corregir(
            $this->obraId,
            ['fecha_recepcion' => '2025-01-02', 'plazo_conservacion_dias' => 200, 'observaciones' => null, 'documento_id' => null],
            ['fecha_recepcion' => '2025-07-25', 'observaciones' => null, 'documento_id' => null],
            $this->usuarioId
        );

        $this->conn->query('DROP TRIGGER IF EXISTS trg_b6_fallo_auditoria_rd');

        $this->assertFalse($resultado['corregida']);

        $provisoria = $this->conn->table('recepciones_provisorias')->get()->getRow();
        $definitiva = $this->conn->table('recepciones_definitivas')->get()->getRow();

        $this->assertSame('2025-01-01', $provisoria->fecha_recepcion);
        $this->assertSame(120, (int) $provisoria->plazo_conservacion_dias);
        $this->assertSame('2025-05-01', $definitiva->fecha_recepcion);
        $this->assertSame(0, $this->contarAuditoria('CORRECCION', 'recepciones_provisorias'));
    }

    public function testLaCorreccionConjuntaValidaAmbasEnMysql(): void
    {
        $this->crearProvisoria('2025-01-01', 120);
        $this->crearDefinitiva('2025-05-01');
        $this->cambiarEstado($this->estados['FINALIZADA']);

        $resultado = (new RecepcionCorreccionService())->corregir(
            $this->obraId,
            ['fecha_recepcion' => '2025-01-02', 'plazo_conservacion_dias' => 200, 'observaciones' => null, 'documento_id' => null],
            ['fecha_recepcion' => '2025-07-25', 'observaciones' => 'Conjunta', 'documento_id' => null],
            $this->usuarioId
        );

        $this->assertTrue($resultado['corregida']);

        $provisoria = $this->conn->table('recepciones_provisorias')->get()->getRow();
        $this->assertSame('2025-07-21', $provisoria->fecha_prevista_definitiva);
        $this->assertSame('2025-07-25', $this->conn->table('recepciones_definitivas')->get()->getRow()->fecha_recepcion);
        $this->assertSame(
            5,
            $this->conn->table('auditoria')->where('accion', 'CORRECCION')->countAllResults(),
            'RP: fecha, plazo y fecha prevista recalculada (3). RD: fecha y observaciones (2).'
        );
    }

    /* =================================================================
       Historial y pantalla
       ================================================================= */

    public function testElHistorialDeLaObraSeResuelveConElUsuarioEnMysql(): void
    {
        $this->crearProvisoria('2025-01-01', 120);
        $this->cambiarEstado($this->estados['EN PLAZO DE CONSERVACIÓN']);

        (new RecepcionCorreccionService())->corregir(
            $this->obraId,
            ['fecha_recepcion' => '2025-01-02', 'plazo_conservacion_dias' => 120, 'observaciones' => null, 'documento_id' => null],
            null,
            $this->usuarioId
        );

        $rpId = (int) $this->conn->table('recepciones_provisorias')->get()->getRow()->id;

        $historial = (new AuditoriaModel())->historialRecepciones($this->obraId, $rpId, null);

        $this->assertNotEmpty($historial);
        $this->assertSame('ADMIN', $historial[0]->usuario_nombre);
        $this->assertSame('PRUEBA', $historial[0]->usuario_apellido);
    }

    public function testLaPantallaDeRecepcionesRenderizaEnMysql(): void
    {
        $this->crearProvisoria('2025-01-01', 120);
        $this->cambiarEstado($this->estados['EN PLAZO DE CONSERVACIÓN']);

        $resultado = $this->conDocumentoSesion()->get('/obras/recepciones/' . $this->obraId);

        $resultado->assertStatus(200);
        $resultado->assertSee('Historial de recepciones');
        $resultado->assertSee('Recepción definitiva');
    }

    /* =================================================================
       Ayudas
       ================================================================= */

    private function limpiarYSembrar(): void
    {
        $this->conn->query('SET FOREIGN_KEY_CHECKS = 0');

        foreach ([
            'auditoria', 'recepciones_definitivas', 'recepciones_provisorias',
            'documentos', 'obras', 'usuarios', 'estados_obra', 'tipos_documento',
        ] as $tabla) {
            $this->conn->query('TRUNCATE TABLE ' . $tabla);
        }

        $this->conn->query('SET FOREIGN_KEY_CHECKS = 1');

        $ahora = date('Y-m-d H:i:s');

        $this->conn->table('usuarios')->insert([
            'nombre'        => 'ADMIN',
            'apellido'      => 'PRUEBA',
            'usuario'       => 'admin_b6',
            'password_hash' => 'no-utilizable',
            'activo'        => 1,
            'created_at'    => $ahora,
            'updated_at'    => $ahora,
        ]);
        $this->usuarioId = (int) $this->conn->insertID();

        foreach (['PREVIO INICIO', 'EN EJECUCIÓN', 'NEUTRALIZADA', 'EN PLAZO DE CONSERVACIÓN', 'FINALIZADA'] as $estado) {
            $this->conn->table('estados_obra')->insert([
                'estado'     => $estado,
                'activo'     => 1,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
            $this->estados[$estado] = (int) $this->conn->insertID();
        }

        $this->conn->table('tipos_documento')->insert(['tipo_documento' => 'ACTA DE RECEPCIÓN PROVISORIA', 'activo' => 1]);
        $this->tipoProvisoriaId = (int) $this->conn->insertID();

        $this->conn->table('tipos_documento')->insert(['tipo_documento' => 'ACTA DE RECEPCIÓN DEFINITIVA', 'activo' => 1]);
        $this->tipoDefinitivaId = (int) $this->conn->insertID();

        $this->conn->table('obras')->insert([
            'codigo'                   => 'OBR-B6-0001',
            'expediente_municipal'     => 'EXP-B6-001',
            'nombre'                   => 'OBRA DE VERIFICACIÓN MYSQL',
            'estado_obra_id'           => $this->estados['EN EJECUCIÓN'],
            'fecha_inicio'             => '2024-01-01',
            'plazo_inicial_confirmado' => 1,
            'plazo_conservacion_dias'  => 180,
            'created_at'               => $ahora,
            'updated_at'               => $ahora,
        ]);
        $this->obraId = (int) $this->conn->insertID();
    }

    private function contarAuditoria(string $accion, string $entidad): int
    {
        return $this->conn->table('auditoria')
            ->where('accion', $accion)
            ->where('entidad', $entidad)
            ->countAllResults();
    }

    private function crearProvisoria(string $fecha, int $plazo): void
    {
        $prevista = (new \DateTimeImmutable($fecha))->modify('+' . $plazo . ' days')->format('Y-m-d');

        $this->conn->table('recepciones_provisorias')->insert([
            'obra_id'                   => $this->obraId,
            'fecha_recepcion'           => $fecha,
            'plazo_conservacion_dias'   => $plazo,
            'fecha_prevista_definitiva' => $prevista,
            'created_at'                => date('Y-m-d H:i:s'),
            'updated_at'                => date('Y-m-d H:i:s'),
        ]);
    }

    private function insertarProvisoriaCruda(int $obraId, string $fecha, int $plazo): void
    {
        $this->conn->table('recepciones_provisorias')->insert([
            'obra_id'                   => $obraId,
            'fecha_recepcion'           => $fecha,
            'plazo_conservacion_dias'   => $plazo,
            'fecha_prevista_definitiva' => $fecha,
            'created_at'                => date('Y-m-d H:i:s'),
            'updated_at'                => date('Y-m-d H:i:s'),
        ]);
    }

    private function crearDefinitiva(string $fecha): void
    {
        $this->conn->table('recepciones_definitivas')->insert([
            'obra_id'         => $this->obraId,
            'fecha_recepcion' => $fecha,
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);
    }

    private function crearDocumento(int $tipoId): int
    {
        $this->conn->table('documentos')->insert([
            'obra_id'           => $this->obraId,
            'tipo_documento_id' => $tipoId,
            'nombre_archivo'    => 'acta-b6.pdf',
            'ruta_relativa'     => 'OBR-B6-0001/acta-b6.pdf',
            'extension'         => 'pdf',
            'mime_type'         => 'application/pdf',
            'version'           => 1,
            'es_version_actual' => 1,
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->conn->insertID();
    }

    private function cambiarEstado(int $estadoId): void
    {
        $this->conn->table('obras')->where('id', $this->obraId)->update(['estado_obra_id' => $estadoId]);
    }

    private function estadoActual(): int
    {
        return (int) $this->conn->table('obras')->where('id', $this->obraId)->get()->getRow()->estado_obra_id;
    }

    private function fallaAlInsertar(callable $insercion): bool
    {
        try {
            $insercion();
        } catch (\Throwable) {
            return true;
        }

        return false;
    }

    private function conDocumentoSesion()
    {
        return $this->withSession([
            'logged_in' => true,
            'activo'    => true,
            'user_id'   => $this->usuarioId,
            'username'  => 'admin_b6',
            'user_name' => 'ADMIN PRUEBA',
            'roles'     => ['ADMINISTRADOR'],
        ]);
    }

    private function postComoAdmin(string $ruta, array $datos)
    {
        return $this->conDocumentoSesion()->post($ruta, array_merge([
            'csrf_test_name' => csrf_hash(),
        ], $datos));
    }

    private static function prepararBaseDescartable(DatabaseConfig $dbConfig): bool
    {
        $base = $dbConfig->default;

        if (strtolower((string) ($base['DBDriver'] ?? '')) !== 'mysqli') {
            return false;
        }

        self::fijarInstancias([]);

        $administracion             = $base;
        $administracion['database'] = '';

        try {
            $conexion = DatabaseConfig::connect($administracion, false);
            $conexion->query('DROP DATABASE IF EXISTS ' . self::BD_DESCARTABLE);
            $conexion->query('CREATE DATABASE ' . self::BD_DESCARTABLE . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
            $conexion->close();
        } catch (\Throwable) {
            return false;
        }

        $dbConfig->tests = [
            'DSN'          => '',
            'hostname'     => $base['hostname'],
            'username'     => $base['username'],
            'password'     => $base['password'],
            'database'     => self::BD_DESCARTABLE,
            'DBDriver'     => 'MySQLi',
            'DBPrefix'     => '',
            'pConnect'     => false,
            'DBDebug'      => true,
            'charset'      => 'utf8mb4',
            'DBCollat'     => 'utf8mb4_general_ci',
            'swapPre'      => '',
            'encrypt'      => false,
            'compress'     => false,
            'strictOn'     => false,
            'failover'     => [],
            'port'         => $base['port'] ?? 3306,
            'numberNative' => false,
            'foundRows'    => false,
            'dateFormat'   => $base['dateFormat'],
        ];

        return true;
    }

    private static function restituirConfig(): void
    {
        $dbConfig = config(DatabaseConfig::class);

        if (self::$configTestsOriginal !== null) {
            $dbConfig->tests = self::$configTestsOriginal;
        }

        self::fijarInstancias(self::$instanciasOriginal);
    }

    private static function eliminarBaseDescartable(): void
    {
        try {
            $base                       = config(DatabaseConfig::class)->default;
            $administracion             = $base;
            $administracion['database'] = '';

            $conexion = DatabaseConfig::connect($administracion, false);
            $conexion->query('DROP DATABASE IF EXISTS ' . self::BD_DESCARTABLE);
            $conexion->close();
        } catch (\Throwable) {
            // El nombre es exclusivo de esta prueba; no afecta a la aplicación.
        }
    }

    private static function instanciasCompartidas(): array
    {
        $propiedad = new ReflectionProperty(DatabaseConfig::class, 'instances');
        $propiedad->setAccessible(true);

        return $propiedad->getValue() ?? [];
    }

    private static function fijarInstancias(array $instancias): void
    {
        $propiedad = new ReflectionProperty(DatabaseConfig::class, 'instances');
        $propiedad->setAccessible(true);
        $propiedad->setValue(null, $instancias);
    }
}
