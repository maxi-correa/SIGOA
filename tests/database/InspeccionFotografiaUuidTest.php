<?php

use App\Libraries\Uuid;
use App\Models\FotografiaModel;
use App\Models\InspeccionModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Fase C — UUID e identidad de inspecciones y fotografías.
 *
 * La suite usa la conexión `tests` (SQLite en memoria). Las migraciones de la
 * aplicación no son compatibles con SQLite, por lo que aquí se crea el esquema
 * equivalente al resultado final de Fase C: `uuid` NOT NULL UNIQUE en ambas
 * tablas, sin la restricción `UNIQUE (obra_id, fecha_inspeccion)`, y con las
 * foreign keys conservadas.
 *
 * Cubre (PASO 10) la estructura de migración y (PASO 11) el comportamiento de
 * los modelos InspeccionModel / FotografiaModel.
 *
 * @internal
 */
final class InspeccionFotografiaUuidTest extends CIUnitTestCase
{
    private BaseConnection $conn;

    private int $obraId;

    private int $inspectorId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conn = db_connect();

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

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('inspecciones') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            uuid VARCHAR(36) NOT NULL UNIQUE,
            obra_id INTEGER NOT NULL,
            inspector_id INTEGER NOT NULL,
            fecha_inspeccion DATE NOT NULL,
            hora_inspeccion TIME NULL,
            observacion TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            FOREIGN KEY (obra_id) REFERENCES ' . $this->tabla('obras') . ' (id),
            FOREIGN KEY (inspector_id) REFERENCES ' . $this->tabla('usuarios') . ' (id)
        )');

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('fotografias') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            uuid VARCHAR(36) NOT NULL UNIQUE,
            inspeccion_id INTEGER NOT NULL,
            nombre_archivo VARCHAR(255) NOT NULL,
            ruta_relativa VARCHAR(500) NOT NULL,
            ruta_thumbnail VARCHAR(500) NOT NULL,
            extension VARCHAR(10) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            tamano_bytes BIGINT NULL,
            ancho INT NULL,
            alto INT NULL,
            fecha_hora_captura DATETIME NULL,
            fecha_hora_carga DATETIME NOT NULL,
            latitud DECIMAL(10,7) NULL,
            longitud DECIMAL(10,7) NULL,
            dispositivo VARCHAR(255) NULL,
            anulada INTEGER NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            FOREIGN KEY (inspeccion_id) REFERENCES ' . $this->tabla('inspecciones') . ' (id)
        )');

        $this->conn->query('PRAGMA foreign_keys = ON');

        $this->limpiar();

        $this->conn->table('obras')->insert([
            'codigo'         => 'OBR-' . strtoupper(dechex((int) (microtime(true) * 1000))),
            'nombre'         => 'OBRA PRUEBA',
            'estado_obra_id' => 1,
        ]);
        $this->obraId = (int) $this->conn->insertID();

        $this->conn->table('usuarios')->insert([
            'usuario'       => 'PRUEBA_INSPECTOR',
            'password_hash' => password_hash('Inspector123', PASSWORD_DEFAULT),
            'activo'        => 1,
        ]);
        $this->inspectorId = (int) $this->conn->insertID();
    }

    protected function tearDown(): void
    {
        $this->limpiar();

        parent::tearDown();
    }

    private function tabla(string $nombre): string
    {
        return $this->conn->prefixTable($nombre);
    }

    private function limpiar(): void
    {
        foreach (['fotografias', 'inspecciones', 'obras', 'usuarios'] as $tabla) {
            $this->conn->query('DELETE FROM ' . $this->tabla($tabla));
        }
    }

    /** Registro base de inspección válido. */
    private function inspeccionValida(string $uuid, string $fecha = '2026-09-23'): array
    {
        return [
            'uuid'             => $uuid,
            'obra_id'          => $this->obraId,
            'inspector_id'     => $this->inspectorId,
            'fecha_inspeccion' => $fecha,
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ];
    }

    /** Registro base de fotografía válido (requiere una inspección creada). */
    private function fotografiaValida(string $uuid, int $inspeccionId): array
    {
        return [
            'uuid'              => $uuid,
            'inspeccion_id'     => $inspeccionId,
            'nombre_archivo'    => $uuid . '.jpg',
            'ruta_relativa'     => 'fotos/' . $uuid . '.jpg',
            'ruta_thumbnail'    => 'fotos/thumbs/' . $uuid . '.jpg',
            'extension'         => 'jpg',
            'mime_type'         => 'image/jpeg',
            'fecha_hora_carga'  => date('Y-m-d H:i:s'),
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ];
    }

    private function insertarInspeccion(string $uuid, string $fecha = '2026-09-23'): int
    {
        $this->conn->table('inspecciones')->insert($this->inspeccionValida($uuid, $fecha));

        return (int) $this->conn->insertID();
    }

    /* =====================================================================
       PASO 10 — Estructura de migración
       ===================================================================== */

    public function testInspeccionesTieneUuidNotNullUnico(): void
    {
        $columnas = $this->conn->query('PRAGMA table_info(' . $this->tabla('inspecciones') . ')')->getResultArray();
        $uuid     = null;

        foreach ($columnas as $columna) {
            if ($columna['name'] === 'uuid') {
                $uuid = $columna;
                break;
            }
        }

        $this->assertNotNull($uuid, 'La columna uuid debería existir en inspecciones.');
        $this->assertSame(1, (int) $uuid['notnull'], 'La columna uuid debe ser NOT NULL.');

        $this->assertTrue($this->indiceUnicoContiene('inspecciones', 'uuid'));
    }

    public function testFotografiasTieneUuidNotNullUnico(): void
    {
        $columnas = $this->conn->query('PRAGMA table_info(' . $this->tabla('fotografias') . ')')->getResultArray();
        $uuid     = null;

        foreach ($columnas as $columna) {
            if ($columna['name'] === 'uuid') {
                $uuid = $columna;
                break;
            }
        }

        $this->assertNotNull($uuid, 'La columna uuid debería existir en fotografias.');
        $this->assertSame(1, (int) $uuid['notnull'], 'La columna uuid debe ser NOT NULL.');

        $this->assertTrue($this->indiceUnicoContiene('fotografias', 'uuid'));
    }

    public function testInspeccionesMismaObraMismaFechaDistintosUuids(): void
    {
        $this->insertarInspeccion(Uuid::v4(), '2026-09-23');
        $this->insertarInspeccion(Uuid::v4(), '2026-09-23');
        $this->insertarInspeccion(Uuid::v4(), '2026-09-23');

        $total = $this->conn->table('inspecciones')
            ->where('obra_id', $this->obraId)
            ->where('fecha_inspeccion', '2026-09-23')
            ->countAllResults();

        $this->assertSame(3, $total, 'Una obra puede tener varias inspecciones en la misma fecha (F.5).');
    }

    public function testInspeccionesUuidDuplicadoRechazado(): void
    {
        $uuid = Uuid::v4();
        $this->insertarInspeccion($uuid);

        $this->expectException(DatabaseException::class);
        $this->conn->table('inspecciones')->insert($this->inspeccionValida($uuid));
    }

    public function testFotografiasDistintosUuidsPermitidos(): void
    {
        $inspeccionId = $this->insertarInspeccion(Uuid::v4());

        $this->conn->table('fotografias')->insert($this->fotografiaValida(Uuid::v4(), $inspeccionId));
        $this->conn->table('fotografias')->insert($this->fotografiaValida(Uuid::v4(), $inspeccionId));

        $total = $this->conn->table('fotografias')->where('inspeccion_id', $inspeccionId)->countAllResults();

        $this->assertSame(2, $total);
    }

    public function testFotografiasUuidDuplicadoRechazado(): void
    {
        $inspeccionId = $this->insertarInspeccion(Uuid::v4());
        $uuid         = Uuid::v4();

        $this->conn->table('fotografias')->insert($this->fotografiaValida($uuid, $inspeccionId));

        $this->expectException(DatabaseException::class);
        $this->conn->table('fotografias')->insert($this->fotografiaValida($uuid, $inspeccionId));
    }

    public function testForeignKeysDeInspeccionesContinuan(): void
    {
        // obra inexistente
        try {
            $this->conn->table('inspecciones')->insert(array_replace($this->inspeccionValida(Uuid::v4()), ['obra_id' => 999999999]));
            $this->fail('Debería rechazar obra_id inexistente (fk_inspecciones_obra).');
        } catch (DatabaseException) {
        }

        // inspector inexistente
        try {
            $this->conn->table('inspecciones')->insert(array_replace($this->inspeccionValida(Uuid::v4()), ['inspector_id' => 999999999]));
            $this->fail('Debería rechazar inspector_id inexistente (fk_inspecciones_inspector).');
        } catch (DatabaseException) {
        }

        // un insert válido sí debe continuar funcionando
        $uuidInvalido = Uuid::v4();
        $this->conn->table('inspecciones')->insert($this->inspeccionValida($uuidInvalido));
        $this->assertSame(
            $uuidInvalido,
            $this->conn->table('inspecciones')->where('uuid', $uuidInvalido)->get()->getRow()->uuid,
            'Un insert válido debe seguir funcionando (FK no bloquea datos correctos).'
        );
    }

    public function testForeignKeyDeFotografiasContinua(): void
    {
        $inspeccionId = $this->insertarInspeccion(Uuid::v4());

        try {
            $this->conn->table('fotografias')->insert($this->fotografiaValida(Uuid::v4(), 999999999));
            $this->fail('Debería rechazar inspeccion_id inexistente (fk_fotografias_inspeccion).');
        } catch (DatabaseException) {
        }

        $this->conn->table('fotografias')->insert($this->fotografiaValida(Uuid::v4(), $inspeccionId));

        $this->assertSame(1, $this->conn->table('fotografias')->where('inspeccion_id', $inspeccionId)->countAllResults());
    }

    /* =====================================================================
       PASO 11 — Modelos
       ===================================================================== */

    public function testInspeccionModelInsertaConUuidYRecuperaPorUuid(): void
    {
        $modelo = new InspeccionModel();
        $uuid   = Uuid::v4();

        $id = $modelo->insert($this->inspeccionValida($uuid));

        $this->assertIsInt($id);

        $registro = $modelo->findByUuid($uuid);

        $this->assertIsObject($registro);
        $this->assertSame($id, (int) $registro->id);
        $this->assertSame($uuid, $registro->uuid);
        $this->assertSame((string) $this->obraId, (string) $registro->obra_id);
    }

    public function testInspeccionModelRechazaUuidInvalido(): void
    {
        $modelo = new InspeccionModel();

        $id = $modelo->insert($this->inspeccionValida('esto-no-es-un-uuid'));

        $this->assertFalse($id);
        $this->assertStringContainsString('no es un UUID válido', $modelo->errors()['uuid']);
    }

    public function testInspeccionModelRechazaSinUuid(): void
    {
        $modelo = new InspeccionModel();

        $datos  = $this->inspeccionValida(Uuid::v4());
        unset($datos['uuid']);

        $id = $modelo->insert($datos);

        $this->assertFalse($id);
        $this->assertArrayHasKey('uuid', $modelo->errors());
    }

    public function testInspeccionModelNoDependeDeObraYFecha(): void
    {
        $modelo = new InspeccionModel();

        $uuidA = Uuid::v4();
        $uuidB = Uuid::v4();

        $this->assertIsInt($modelo->insert($this->inspeccionValida($uuidA, '2026-09-23')));
        $this->assertIsInt($modelo->insert($this->inspeccionValida($uuidB, '2026-09-23')));

        $this->assertNotSame($uuidA, $uuidB);
        $this->assertSame(2, $modelo->where('obra_id', $this->obraId)->where('fecha_inspeccion', '2026-09-23')->countAllResults());
        $this->assertNotNull($modelo->findByUuid($uuidA));
        $this->assertNotNull($modelo->findByUuid($uuidB));
    }

    public function testInspeccionModelRechazaUuidDuplicadoEnLaBase(): void
    {
        $modelo = new InspeccionModel();
        $uuid   = Uuid::v4();

        $modelo->insert($this->inspeccionValida($uuid));

        $this->expectException(DatabaseException::class);
        $modelo->insert($this->inspeccionValida($uuid));
    }

    public function testFotografiaModelInsertaConUuidYRecuperaPorUuid(): void
    {
        $inspeccionId = $this->insertarInspeccion(Uuid::v4());
        $modelo       = new FotografiaModel();
        $uuid         = Uuid::v4();

        $id = $modelo->insert($this->fotografiaValida($uuid, $inspeccionId));

        $this->assertIsInt($id);

        $registro = $modelo->findByUuid($uuid);

        $this->assertIsObject($registro);
        $this->assertSame($id, (int) $registro->id);
        $this->assertSame($uuid, $registro->uuid);
        $this->assertSame((string) $inspeccionId, (string) $registro->inspeccion_id);
    }

    public function testFotografiaModelRechazaUuidInvalido(): void
    {
        $inspeccionId = $this->insertarInspeccion(Uuid::v4());
        $modelo       = new FotografiaModel();

        $id = $modelo->insert($this->fotografiaValida('mal-formato', $inspeccionId));

        $this->assertFalse($id);
        $this->assertStringContainsString('no es un UUID válido', $modelo->errors()['uuid']);
    }

    public function testFotografiaModelRechazaUuidDuplicadoEnLaBase(): void
    {
        $inspeccionId = $this->insertarInspeccion(Uuid::v4());
        $modelo       = new FotografiaModel();
        $uuid         = Uuid::v4();

        $modelo->insert($this->fotografiaValida($uuid, $inspeccionId));

        $this->expectException(DatabaseException::class);
        $modelo->insert($this->fotografiaValida($uuid, $inspeccionId));
    }

    /* =====================================================================
       Helpers de inspección de esquema
       ===================================================================== */

    private function indiceUnicoContiene(string $tabla, string $columna): bool
    {
        $indices = $this->conn->query('PRAGMA index_list(' . $this->tabla($tabla) . ')')->getResultArray();

        foreach ($indices as $indice) {
            if (! (bool) $indice['unique']) {
                continue;
            }

            $columnas = $this->conn->query('PRAGMA index_info(' . $indice['name'] . ')')->getResultArray();

            foreach ($columnas as $col) {
                if ($col['name'] === $columna) {
                    return true;
                }
            }
        }

        return false;
    }
}