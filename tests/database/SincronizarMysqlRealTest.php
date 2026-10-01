<?php

use App\Libraries\Uuid;
use App\Models\InspeccionModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\MigrationRunner;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use Config\Database as DatabaseConfig;

/**
 * Sincronización real contra el motor de producción (Fase D.6.1).
 *
 * El resto del grupo `database` construye su propio esquema SQLite en
 * memoria, lo que permite que la suite pase aunque las migraciones de
 * D.3/D.4 no estén aplicadas en la base real (falso verde documentado en
 * D.6). Este test cubre ese hueco: crea una base **descartable** en el
 * mismo servidor MySQL configurado para la aplicación, ejecuta las
 * **migraciones reales** del proyecto y recibe los endpoints D.3/D.4
 * contra ella.
 *
 * Verifica de punta a punta lo que la prueba manual no pudo confirmar:
 * `uuid` persistido en `inspecciones` y `fotografias`, trazabilidad en
 * `operaciones_sincronizacion`, escritura física de la imagen y su
 * thumbnail en la estructura `OBR-XXXXXX/AAAA-MM-DD/HH-mm-ss/{IMAGENES,
 * THUMBNAILS}` (Fase E.2) e idempotencia del reenvío.
 *
 * La base real de la aplicación **no se toca**: se crea y se elimina una
 * base con nombre propio. Si el servidor no es MySQL/MariaDB o no permite
 * crear bases, el test se omite.
 *
 * Es más lento que el resto de la suite (ejecuta todas las migraciones
 * reales). Puede excluirse por grupo cuando se quiera una vuelta rápida:
 * `phpunit --exclude-group mysql-real`.
 *
 * @internal
 */
#[\PHPUnit\Framework\Attributes\Group('mysql-real')]
final class SincronizarMysqlRealTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    /**
     * Base descartable creada y eliminada por este test.
     */
    private const BD_DESCARTABLE = 'sigoa_d61_descartable';

    private static ?array $configTestsOriginal = null;

    private static array $instanciasOriginal = [];

    private static bool $basePreparada = false;

    private BaseConnection $conn;

    private string $tmp;

    private string $raizOriginal;

    private int $obraId;

    private int $inspectorId;

    private string $uuidInspeccion = '';

    private int $inspeccionId = 0;

    private string $uuidFoto = '';

    /**
     * La base descartable se crea y se migra una sola vez para toda la
     * clase: ejecutar las 29 migraciones reales en cada test multiplicaría
     * el tiempo de la suite sin aportar cobertura adicional.
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $dbConfig = config(DatabaseConfig::class);

        self::$configTestsOriginal = $dbConfig->tests;
        self::$instanciasOriginal   = self::instanciasCompartidas();

        if (! self::prepararBaseDescartable($dbConfig)) {
            self::restituirConfig();

            self::markTestSkipped(
                'La base configurada para la aplicación no es MySQL/MariaDB o no permite crear una base descartable: '
                . 'la prueba real contra el motor de producción no aplica en este entorno.'
            );
        }

        self::$basePreparada = true;

        self::correrMigracionesReales();
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

        $this->limpiar();

        $this->migracionesDeD3YD4Aplicadas();

        $this->semilla();

        $this->tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sigoa_d61_' . bin2hex(random_bytes(4));
        mkdir($this->tmp, 0755, true);

        $config              = config('SigoaStorage');
        $this->raizOriginal  = $config->storagePath;
        $config->storagePath = $this->tmp;
    }

    protected function tearDown(): void
    {
        config('SigoaStorage')->storagePath = $this->raizOriginal;

        $this->eliminarTree($this->tmp);

        service('superglobals')->setFilesArray([]);
        service('superglobals')->setPostArray([]);

        parent::tearDown();
    }

    /* =================================================================
       Esquema producido por las migraciones reales
       ================================================================= */

    public function testEsquemaRealTieneUuidNotNullUnicoEnAmbasTablas(): void
    {
        foreach (['inspecciones', 'fotografias'] as $tabla) {
            $columna = $this->columna($tabla, 'uuid');

            $this->assertNotNull($columna, $tabla . '.uuid no existe en el esquema real.');
            $this->assertSame('char(36)', strtolower((string) $columna->COLUMN_TYPE), $tabla . '.uuid debe ser CHAR(36).');
            $this->assertSame('NO', $columna->IS_NULLABLE, $tabla . '.uuid debe ser NOT NULL.');
            $this->assertTrue(
                $this->indiceUnico($tabla, 'uuid'),
                $tabla . '.uuid debe tener un índice UNIQUE.'
            );
        }
    }

    public function testEsquemaRealPermiteVariasInspeccionesPorObraYFecha(): void
    {
        $this->assertFalse(
            $this->existeIndice('inspecciones', 'obra_id_fecha_inspeccion'),
            'La unicidad (obra_id, fecha_inspeccion) no debe volver a existir.'
        );

        // Se conservan el índice simple y las foreign keys de la tabla.
        $this->assertNotEmpty($this->indicesDe('inspecciones', 'obra_id'), 'Debe conservarse el índice de obra_id.');

        $fkObra = $this->conn->query(
            "SELECT COUNT(*) AS total FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inspecciones'
             AND COLUMN_NAME = 'obra_id' AND REFERENCED_TABLE_NAME = 'obras'"
        )->getRow();

        $this->assertSame(1, (int) $fkObra->total, 'Debe conservarse la FK de inspecciones.obra_id.');
    }

    public function testModelosOperanSobreElEsquemaRealMigrado(): void
    {
        $uuid = Uuid::v4();

        $id = (new InspeccionModel())->insert([
            'uuid'             => $uuid,
            'obra_id'          => $this->obraId,
            'inspector_id'     => $this->inspectorId,
            'fecha_inspeccion' => '2026-03-15',
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ], true);

        $this->assertIsInt($id);

        $encontrada = (new InspeccionModel())->findByUuid($uuid);

        $this->assertNotNull($encontrada, 'findByUuid() debe funcionar contra el esquema real.');
        $this->assertSame($uuid, $encontrada->uuid);
    }

    /* =================================================================
       FASE D.3 — sincronización de inspecciones
       ================================================================= */

    public function testInspeccionSeSincronizaYQuedaRegistradaEnElServidor(): void
    {
        $respuesta = $this->enviarInspecciones();

        $respuesta->assertStatus(200);

        $cuerpo = $this->cuerpo($respuesta);

        $this->assertTrue($cuerpo['ok']);
        $this->assertSame('SYNCED', $cuerpo['results'][0]['estado']);
        $this->assertSame(1, $cuerpo['resumen']['sincronizadas']);
        $this->assertSame(0, $cuerpo['resumen']['errores']);

        $fila = $this->conn->table('inspecciones')->where('uuid', $this->uuidInspeccion)->get()->getRow();

        $this->assertNotNull($fila, 'La inspección debe quedar insertada en el esquema real.');
        $this->assertSame($cuerpo['results'][0]['id'], (int) $fila->id);
        $this->assertSame($this->obraId, (int) $fila->obra_id);
        $this->assertSame($this->inspectorId, (int) $fila->inspector_id);
        $this->assertSame('2026-03-15', $fila->fecha_inspeccion);

        $operacion = $this->conn->table('operaciones_sincronizacion')
            ->where('registro_id', $this->uuidInspeccion)
            ->get()
            ->getRow();

        $this->assertNotNull($operacion, 'Debe registrarse la trazabilidad de la operación.');
        $this->assertSame('alta', $operacion->tipo_operacion);
        $this->assertSame('inspecciones', $operacion->entidad);
        $this->assertSame('PROCESADA', $operacion->estado);
    }

    public function testSegundaSincronizacionDeInspeccionNoDuplica(): void
    {
        $this->enviarInspecciones();

        $repeticion = $this->enviarInspecciones();

        $repeticion->assertStatus(200);

        $cuerpo = $this->cuerpo($repeticion);

        $this->assertSame('ALREADY_SYNCED', $cuerpo['results'][0]['estado']);
        $this->assertSame(1, $cuerpo['resumen']['ya_sincronizadas']);

        $this->assertSame(
            1,
            (int) $this->conn->table('inspecciones')->where('uuid', $this->uuidInspeccion)->countAllResults(),
            'La segunda sincronización no debe crear una segunda inspección.'
        );
    }

    public function testInspeccionesDistintasMismaObraYMismaFecha(): void
    {
        $this->enviarInspecciones();

        $segundoUuid = Uuid::v4();

        $respuesta = $this->enviarInspecciones(['uuid' => $segundoUuid, 'observacion' => 'Segunda del mismo día']);

        $respuesta->assertStatus(200);
        $this->assertSame('SYNCED', $this->cuerpo($respuesta)['results'][0]['estado']);

        $this->assertSame(2, (int) $this->conn->table('inspecciones')->countAllResults());
    }

    /* =================================================================
       FASE D.4 — sincronización de fotografías
       ================================================================= */

    public function testFotografiaSeSincronizaYSeEscribenImagenYThumbnail(): void
    {
        $this->sincronizarInspeccion();

        $respuesta = $this->enviarFotografias();

        $respuesta->assertStatus(200);

        $cuerpo = $this->cuerpo($respuesta);

        $this->assertTrue($cuerpo['ok']);
        $this->assertSame('SYNCED', $cuerpo['results'][0]['estado']);
        $this->assertSame(1, $cuerpo['resumen']['sincronizadas']);

        $fila = $this->conn->table('fotografias')->where('uuid', $this->uuidFoto)->get()->getRow();

        $this->assertNotNull($fila, 'La fotografía debe quedar insertada en el esquema real.');
        $this->assertSame($this->inspeccionId, (int) $fila->inspeccion_id);
        $this->assertSame('image/jpeg', $fila->mime_type);
        $this->assertSame('jpg', strtolower((string) $fila->extension));

        /* Archivos físicos en la estructura definitiva. Desde la Fase E.2 la
           carpeta de la inspección se deriva de fecha + hora, no del UUID. */
        $this->assertStringStartsWith('OBR-000099/2026-03-15/10-30-00/IMAGENES/', (string) $fila->ruta_relativa);
        $this->assertStringStartsWith('OBR-000099/2026-03-15/10-30-00/THUMBNAILS/', (string) $fila->ruta_thumbnail);
        $this->assertStringNotContainsString($this->uuidInspeccion, (string) $fila->ruta_relativa);
        $this->assertStringNotContainsString($this->uuidInspeccion, (string) $fila->ruta_thumbnail);

        $this->assertFileExists($this->rutaAbsoluta((string) $fila->ruta_relativa), 'Debe existir la imagen original optimizada.');
        $this->assertFileExists($this->rutaAbsoluta((string) $fila->ruta_thumbnail), 'Debe existir el thumbnail regenerado en el servidor.');
        $this->assertGreaterThan(0, (int) $fila->tamano_bytes);
        $this->assertGreaterThan(0, (int) $fila->ancho);
        $this->assertGreaterThan(0, (int) $fila->alto);

        $operacion = $this->conn->table('operaciones_sincronizacion')
            ->where('registro_id', $this->uuidFoto)
            ->get()
            ->getRow();

        $this->assertNotNull($operacion, 'Debe registrarse la trazabilidad de la fotografía.');
        $this->assertSame('fotografias', $operacion->entidad);
        $this->assertSame('PROCESADA', $operacion->estado);
    }

    public function testSegundaSincronizacionDeFotografiaNoDuplicaNiReescribe(): void
    {
        $this->sincronizarInspeccion();
        $this->enviarFotografias();

        $original = $this->conn->table('fotografias')->where('uuid', $this->uuidFoto)->get()->getRow();

        $repeticion = $this->enviarFotografias();

        $repeticion->assertStatus(200);

        $cuerpo = $this->cuerpo($repeticion);

        $this->assertSame('ALREADY_SYNCED', $cuerpo['results'][0]['estado']);
        $this->assertSame(1, $cuerpo['resumen']['ya_sincronizadas']);
        $this->assertFalse($cuerpo['results'][0]['reparado']);

        $this->assertSame(
            1,
            (int) $this->conn->table('fotografias')->where('uuid', $this->uuidFoto)->countAllResults(),
            'La segunda sincronización no debe crear una segunda fotografía.'
        );

        $this->assertSame(
            (string) $original->ruta_relativa,
            (string) $this->conn->table('fotografias')->where('uuid', $this->uuidFoto)->get()->getRow()->ruta_relativa
        );
    }

    /* =================================================================
       Utilidades de conexión y esquema
       ================================================================= */

    /**
     * Crea la base descartable y apunta el grupo `tests` a ella. Devuelve
     * `false` si el entorno no lo permite, para que la clase se omita.
     */
    private static function prepararBaseDescartable(DatabaseConfig $dbConfig): bool
    {
        $base = $dbConfig->default;

        if (strtolower((string) ($base['DBDriver'] ?? '')) !== 'mysqli') {
            return false;
        }

        /* Se libera la caché de conexiones compartidas para que el grupo
         * `tests` (usado por modelos y helpers) apunte a la base
         * descartable. La conexión original se restituye al finalizar. */
        self::fijarInstancias([]);

        $administracion          = $base;
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

    /**
     * Restaura la configuración de base de datos original del entorno.
     */
    private static function restituirConfig(): void
    {
        $dbConfig = config(DatabaseConfig::class);

        if (self::$configTestsOriginal !== null) {
            $dbConfig->tests = self::$configTestsOriginal;
        }

        self::fijarInstancias(self::$instanciasOriginal);
    }

    private static function correrMigracionesReales(): void
    {
        $migraciones = new MigrationRunner(config('Migrations'), 'tests');
        $migraciones->latest('tests');
    }

    /**
     * Las tres migraciones de D.3/D.4 deben haber quedado aplicadas en la
     * base descartable; se verifica al inicio de cada test para que un
     * fallo de migración no pase inadvertido.
     */
    private function migracionesDeD3YD4Aplicadas(): void
    {
        foreach ([
            '2026-09-23-120000' => 'la migración de uuid en inspecciones',
            '2026-09-23-121000' => 'la migración de uuid en fotografías',
            '2026-09-23-122000' => 'la migración que elimina la unicidad por obra y fecha',
        ] as $version => $descripcion) {
            $aplicada = (int) $this->conn->table('migrations')
                ->where('version', $version)
                ->countAllResults();

            $this->assertSame(1, $aplicada, $descripcion . ' debe quedar aplicada.');
        }
    }

    private static function eliminarBaseDescartable(): void
    {
        try {
            $dbConfig = config(DatabaseConfig::class);
            $base     = $dbConfig->default;

            $administracion             = $base;
            $administracion['database'] = '';

            $conexion = DatabaseConfig::connect($administracion, false);
            $conexion->query('DROP DATABASE IF EXISTS ' . self::BD_DESCARTABLE);
            $conexion->close();
        } catch (\Throwable) {
            // Si no puede eliminarse, la base no afecta a la aplicación:
            // su nombre es exclusivo de la prueba.
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

    private function columna(string $tabla, string $columna): ?object
    {
        return $this->conn->query(
            'SELECT COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$tabla, $columna]
        )->getRow();
    }

    private function indicesDe(string $tabla, string $columna): array
    {
        return $this->conn->query(
            'SELECT INDEX_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$tabla, $columna]
        )->getResultArray();
    }

    private function existeIndice(string $tabla, string $indice): bool
    {
        return (int) $this->conn->query(
            'SELECT COUNT(*) AS total FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$tabla, $indice]
        )->getRow()->total > 0;
    }

    private function indiceUnico(string $tabla, string $columna): bool
    {
        $filas = $this->conn->query(
            'SELECT INDEX_NAME, MIN(NON_UNIQUE) AS no_unico, COUNT(*) AS columnas
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
             GROUP BY INDEX_NAME',
            [$tabla, $columna]
        )->getResultArray();

        foreach ($filas as $fila) {
            if ((int) $fila['no_unico'] === 0 && (int) $fila['columnas'] === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vacía las tablas operativas de la base descartable sin tocar el
     * registro de migraciones, para que cada test parta del mismo estado.
     */
    private function limpiar(): void
    {
        $tablas = $this->conn->query(
            "SELECT TABLE_NAME AS tabla FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' AND TABLE_NAME <> 'migrations'"
        )->getResultArray();

        $this->conn->query('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($tablas as $fila) {
            $this->conn->query('TRUNCATE TABLE `' . $fila['tabla'] . '`');
        }

        $this->conn->query('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * Datos mínimos para que los endpoints tengan una obra, un inspector
     * con rol y una asignación vigente en la fecha de la inspección.
     */
    private function semilla(): void
    {
        $ahora = date('Y-m-d H:i:s');

        $this->conn->table('estados_obra')->insert([
            'estado'      => 'EN EJECUCION',
            'descripcion' => 'Estado de la prueba D.6.1',
            'activo'      => 1,
            'created_at'  => $ahora,
            'updated_at'  => $ahora,
        ]);

        $estadoId = (int) $this->conn->insertID();

        $this->conn->table('roles')->insert([
            'nombre'      => 'INSPECTOR',
            'descripcion' => 'Rol de la prueba D.6.1',
            'activo'      => 1,
        ]);

        $rolId = (int) $this->conn->insertID();

        $this->conn->table('usuarios')->insert([
            'nombre'         => 'INSPECTOR',
            'apellido'       => 'PRUEBA D61',
            'usuario'        => 'PRUEBA_INSPECTOR_D61',
            'password_hash'  => password_hash('Inspector123', PASSWORD_DEFAULT),
            'email'          => null,
            'activo'         => 1,
            'created_at'     => $ahora,
            'updated_at'     => $ahora,
        ]);

        $this->inspectorId = (int) $this->conn->insertID();

        $this->conn->table('usuarios_roles')->insert([
            'usuario_id' => $this->inspectorId,
            'rol_id'     => $rolId,
            'created_at' => $ahora,
        ]);

        $this->conn->table('obras')->insert([
            'codigo'              => 'OBR-000099',
            'expediente_municipal' => 'EXP-D61-PRUEBA',
            'nombre'              => 'OBRA PRUEBA REAL D.6.1',
            'estado_obra_id'      => $estadoId,
            'created_at'          => $ahora,
            'updated_at'          => $ahora,
        ]);

        $this->obraId = (int) $this->conn->insertID();

        $this->conn->table('inspectores_obras')->insert([
            'obra_id'      => $this->obraId,
            'usuario_id'   => $this->inspectorId,
            'fecha_inicio' => '2026-03-01',
            'created_at'   => $ahora,
        ]);
    }

    /* =================================================================
       Peticiones a los endpoints
       ================================================================= */

    private function sesionInspector(): array
    {
        return [
            'logged_in' => true,
            'activo'    => true,
            'user_id'   => $this->inspectorId,
            'username'  => 'PRUEBA_INSPECTOR_D61',
            'user_name' => 'PRUEBA_INSPECTOR_D61',
            'roles'     => ['INSPECTOR'],
        ];
    }

    private function itemInspeccion(array $extra = []): array
    {
        if ($this->uuidInspeccion === '') {
            $this->uuidInspeccion = Uuid::v4();
        }

        return array_merge([
            'uuid'             => $this->uuidInspeccion,
            'obra_id'          => $this->obraId,
            'fecha_inspeccion' => '2026-03-15',
            'hora_inspeccion'  => '10:30:00',
            'observacion'      => 'Inspección de prueba real D.6.1',
        ], $extra);
    }

    /**
     * Sincroniza la inspección de la prueba y memoriza su `id` de servidor.
     */
    private function sincronizarInspeccion(array $extra = []): TestResponse
    {
        $respuesta = $this->enviarInspecciones($extra);

        $fila = $this->conn->table('inspecciones')
            ->where('uuid', $this->uuidInspeccion)
            ->orderBy('id', 'DESC')
            ->get()
            ->getRow();

        $this->inspeccionId = $fila === null ? 0 : (int) $fila->id;

        return $respuesta;
    }

    private function enviarInspecciones(array $extra = []): TestResponse
    {
        return $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept'           => 'application/json',
            'X-CSRF-TOKEN'     => csrf_hash(),
        ])
            ->withBodyFormat('json')
            ->withSession($this->sesionInspector())
            ->post('/inspector/sincronizar/inspecciones', [
                'inspecciones' => [$this->itemInspeccion($extra)],
            ]);
    }

    private function enviarFotografias(): TestResponse
    {
        if ($this->uuidFoto === '') {
            $this->uuidFoto = Uuid::v4();
        }

        $ruta = $this->crearJpeg();

        service('superglobals')->setFilesArray([
            'archivo' => [
                'name'     => 'captura.jpg',
                'type'     => 'image/jpeg',
                'tmp_name' => $ruta,
                'error'    => UPLOAD_ERR_OK,
                'size'     => (int) filesize($ruta),
            ],
        ]);

        return $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept'           => 'application/json',
            'X-CSRF-TOKEN'     => csrf_hash(),
        ])
            ->withSession($this->sesionInspector())
            ->post('/inspector/sincronizar/fotografias', [
                'uuid'               => $this->uuidFoto,
                'inspeccion_uuid'    => $this->uuidInspeccion,
                'dispositivo'        => 'PRUEBA D.6.1',
                'fecha_hora_captura' => '2026-03-15 10:30:00',
            ]);
    }

    private function cuerpo(TestResponse $respuesta): array
    {
        return json_decode($respuesta->getJSON(), true) ?? [];
    }

    /* =================================================================
       Archivos
       ================================================================= */

    private function rutaAbsoluta(string $relativa): string
    {
        return $this->tmp . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativa);
    }

    private function crearJpeg(int $ancho = 1200, int $alto = 900): string
    {
        $ruta = $this->tmp . DIRECTORY_SEPARATOR . 'origen.jpg';

        $imagen = imagecreatetruecolor($ancho, $alto);
        imagefilledrectangle($imagen, 0, 0, $ancho, $alto, imagecolorallocate($imagen, 40, 90, 160));
        imagejpeg($imagen, $ruta, 90);
        imagedestroy($imagen);

        return $ruta;
    }

    private function eliminarTree(string $ruta): void
    {
        if ($ruta === '' || ! is_dir($ruta)) {
            return;
        }

        foreach (scandir($ruta) as $entrada) {
            if ($entrada === '.' || $entrada === '..') {
                continue;
            }

            $completa = $ruta . DIRECTORY_SEPARATOR . $entrada;

            if (is_dir($completa)) {
                $this->eliminarTree($completa);
            } else {
                @unlink($completa);
            }
        }

        @rmdir($ruta);
    }
}
