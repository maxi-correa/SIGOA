<?php

use App\Models\DocumentoModel;
use CodeIgniter\Config\Factories;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Versionado de documentos de obra y acciones de la pantalla de
 * documentación.
 *
 * Cubre:
 *
 * * `DocumentoModel::crearVersionDe()`: la versión vigente pasa a historial
 *   (`es_version_actual = 0`), la nueva hereda obra/tipo/eventos y el
 *   documento raíz se conserva; nunca se elimina una versión anterior.
 * * Reglas de `reemplazar`: solo administradores, solo la obra del propio
 *   documento y solo la versión vigente.
 * * `subir` rechaza un segundo documento de un tipo no múltiple.
 * * `ubicacion` responde 403 a CONSULTA (solo administradores).
 *
 * Usa la conexión `tests` (SQLite en memoria) con un esquema mínimo que
 * reproduce los nombres reales de columnas.
 *
 * @internal
 */
final class DocumentacionVersionadoTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private BaseConnection $conn;

    private string $raizAlmacenamiento;

    private const TIPO_ACTA_NEUTRALIZACION = 4;
    private const TIPO_ACTA_REINICIO = 5;
    private const TIPO_OTROS = 16;

    protected function setUp(): void
    {
        parent::setUp();

        /* Nunca se toca la raíz real de almacenamiento (en producción, un
           recurso UNC de red): se inyecta una raíz temporal propia. */
        $this->raizAlmacenamiento = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sigoa_doc_' . bin2hex(random_bytes(4));

        $config              = new \Config\SigoaStorage();
        $config->storagePath = $this->raizAlmacenamiento;

        Factories::injectMock('config', 'SigoaStorage', $config);

        $this->conn = db_connect();

        foreach (['documentos', 'neutralizaciones', 'tipos_documento', 'usuarios', 'obras', 'estados_obra'] as $tabla) {
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
            nombre VARCHAR(200) NOT NULL,
            fecha_inicio DATE NULL,
            estado_obra_id INTEGER NOT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');

        $this->conn->query('CREATE TABLE ' . $this->tabla('usuarios') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre VARCHAR(120) NULL,
            apellido VARCHAR(120) NULL
        )');

        $this->conn->query('CREATE TABLE ' . $this->tabla('tipos_documento') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tipo_documento VARCHAR(100) NOT NULL,
            activo INTEGER NOT NULL DEFAULT 1
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

        $this->conn->query('CREATE TABLE ' . $this->tabla('documentos') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            obra_id INTEGER NULL,
            tipo_documento_id INTEGER NOT NULL,
            nombre_archivo VARCHAR(255) NOT NULL,
            nombre_archivo_original VARCHAR(255) NULL,
            ruta_relativa VARCHAR(500) NOT NULL,
            extension VARCHAR(10) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            tamano_bytes INTEGER NULL,
            fecha_documento DATE NULL,
            usuario_id_carga INTEGER NULL,
            neutralizacion_id INTEGER NULL,
            reinicio_id INTEGER NULL,
            observaciones TEXT NULL,
            version INTEGER NOT NULL DEFAULT 1,
            es_version_actual INTEGER NOT NULL DEFAULT 1,
            documento_padre_id INTEGER NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )');

        $this->sembrarDatos();
    }

    protected function tearDown(): void
    {
        foreach (['documentos', 'neutralizaciones', 'tipos_documento', 'usuarios', 'obras', 'estados_obra'] as $tabla) {
            $this->conn->query('DROP TABLE IF EXISTS ' . $this->tabla($tabla));
        }

        $this->eliminarTree($this->raizAlmacenamiento);

        parent::tearDown();
    }

    private function sembrarDatos(): void
    {
        $this->conn->table('estados_obra')->insert(['id' => 2, 'estado' => 'EN EJECUCIÓN', 'activo' => 1]);

        foreach ([[1, 'OBR-000001'], [2, 'OBR-000002']] as $obra) {
            $this->conn->table('obras')->insert([
                'id'             => $obra[0],
                'codigo'         => $obra[1],
                'nombre'         => 'OBRA ' . $obra[0],
                'fecha_inicio'   => '2026-01-15',
                'estado_obra_id' => 2,
                'created_at'     => '2026-01-01 08:00:00',
                'updated_at'     => '2026-01-01 08:00:00',
            ]);
        }

        $this->conn->table('usuarios')->insert(['id' => 1, 'nombre' => 'JUANA', 'apellido' => 'PEREZ']);

        $this->conn->table('tipos_documento')->insertBatch([
            ['id' => self::TIPO_ACTA_NEUTRALIZACION, 'tipo_documento' => 'ACTA DE NEUTRALIZACIÓN', 'activo' => 1],
            ['id' => self::TIPO_ACTA_REINICIO, 'tipo_documento' => 'ACTA DE REINICIO', 'activo' => 1],
            ['id' => self::TIPO_OTROS, 'tipo_documento' => 'OTROS', 'activo' => 1],
        ]);

        $this->conn->table('neutralizaciones')->insert([
            'id' => 1, 'obra_id' => 1, 'numero' => 1, 'fecha_inicio' => '2026-03-01',
            'fecha_reinicio' => '2026-04-01',
            'created_at' => '2026-03-01 09:00:00', 'updated_at' => '2026-04-01 09:00:00',
        ]);

        $this->conn->table('documentos')->insertBatch([
            [
                'id' => 1, 'obra_id' => 1, 'tipo_documento_id' => self::TIPO_ACTA_NEUTRALIZACION,
                'nombre_archivo' => 'fisico1.pdf', 'nombre_archivo_original' => 'ACTA_NEUTRALIZACION_v1.pdf',
                'ruta_relativa' => 'OBR-000001/fisico1.pdf', 'extension' => 'pdf', 'mime_type' => 'application/pdf',
                'tamano_bytes' => 1024, 'fecha_documento' => '2026-03-02', 'usuario_id_carga' => 1,
                'neutralizacion_id' => 1, 'reinicio_id' => null, 'observaciones' => 'Primera versión',
                'version' => 1, 'es_version_actual' => 1, 'documento_padre_id' => null,
                'created_at' => '2026-03-02 10:00:00', 'updated_at' => '2026-03-02 10:00:00',
            ],
            [
                'id' => 2, 'obra_id' => 2, 'tipo_documento_id' => self::TIPO_OTROS,
                'nombre_archivo' => 'fisico2.pdf', 'nombre_archivo_original' => 'OTROS_OBRA2.pdf',
                'ruta_relativa' => 'OBR-000002/fisico2.pdf', 'extension' => 'pdf', 'mime_type' => 'application/pdf',
                'tamano_bytes' => 512, 'fecha_documento' => null, 'usuario_id_carga' => 1,
                'neutralizacion_id' => null, 'reinicio_id' => null, 'observaciones' => null,
                'version' => 1, 'es_version_actual' => 1, 'documento_padre_id' => null,
                'created_at' => '2026-04-03 10:00:00', 'updated_at' => '2026-04-03 10:00:00',
            ],
        ]);
    }

    /* ------------------------------------------------------------------
     * crearVersionDe: versionado transaccional
     * ------------------------------------------------------------------ */

    public function testCrearVersionDeMarcaLaAnteriorComoHistoricoYHeredaElEvento(): void
    {
        $modelo = new DocumentoModel();

        $nuevoId = $modelo->crearVersionDe(1, [
            'nombre_archivo'          => 'nuevo.pdf',
            'nombre_archivo_original' => 'ACTA_NEUTRALIZACION_v2.pdf',
            'ruta_relativa'           => 'OBR-000001/nuevo.pdf',
            'extension'               => 'pdf',
            'mime_type'               => 'application/pdf',
            'tamano_bytes'            => 2048,
            'fecha_documento'         => '2026-05-01',
            'observaciones'           => 'Segunda versión',
            'usuario_id_carga'        => 1,
        ]);

        $this->assertNotNull($nuevoId);

        $anterior = $modelo->find(1);
        $nuevo    = $modelo->find($nuevoId);

        $this->assertSame(0, (int) $anterior->es_version_actual, 'La versión reemplazada pasa a historial.');
        $this->assertSame(2, (int) $nuevo->version);
        $this->assertSame(1, (int) $nuevo->es_version_actual);
        $this->assertSame(1, (int) $nuevo->documento_padre_id, 'La nueva versión cuelga del documento raíz.');
        $this->assertSame(1, (int) $nuevo->obra_id, 'Hereda la obra.');
        $this->assertSame(self::TIPO_ACTA_NEUTRALIZACION, (int) $nuevo->tipo_documento_id, 'Hereda el tipo.');
        $this->assertSame(1, (int) $nuevo->neutralizacion_id, 'Hereda la neutralización.');
        $this->assertNull($nuevo->reinicio_id);

        $this->assertSame(3, (int) $this->conn->table('documentos')->countAllResults(false),
            'No se elimina ninguna versión: la anterior permanece en la tabla (2 iniciales + 1 nueva).');
    }

    public function testCrearVersionDeConservaLaCadenaDeVersiones(): void
    {
        $modelo = new DocumentoModel();

        $v2 = $modelo->crearVersionDe(1, [
            'nombre_archivo' => 'v2.pdf', 'nombre_archivo_original' => 'v2.pdf',
            'ruta_relativa' => 'OBR-000001/v2.pdf', 'extension' => 'pdf',
            'mime_type' => 'application/pdf', 'tamano_bytes' => 10,
        ]);

        $v3 = $modelo->crearVersionDe($v2, [
            'nombre_archivo' => 'v3.pdf', 'nombre_archivo_original' => 'v3.pdf',
            'ruta_relativa' => 'OBR-000001/v3.pdf', 'extension' => 'pdf',
            'mime_type' => 'application/pdf', 'tamano_bytes' => 20,
        ]);

        $this->assertNotNull($v3);

        $versiones = $modelo->listarVersiones($v3);

        $this->assertCount(3, $versiones);
        $this->assertSame([1, 2, 3], array_map(static fn ($v) => (int) $v->version, $versiones));
        $this->assertSame(1, (int) $modelo->find($v3)->documento_padre_id,
            'Todas las versiones cuelgan del documento raíz original.');
    }

    public function testCrearVersionDeRechazaUnaVersionNoVigente(): void
    {
        $modelo = new DocumentoModel();

        $nuevoId = $modelo->crearVersionDe(1, [
            'nombre_archivo' => 'nuevo.pdf', 'nombre_archivo_original' => 'nuevo.pdf',
            'ruta_relativa' => 'OBR-000001/nuevo.pdf', 'extension' => 'pdf',
            'mime_type' => 'application/pdf', 'tamano_bytes' => 10,
        ]);

        $this->assertNotNull($nuevoId);

        $reintento = $modelo->crearVersionDe(1, [
            'nombre_archivo' => 'otro.pdf', 'nombre_archivo_original' => 'otro.pdf',
            'ruta_relativa' => 'OBR-000001/otro.pdf', 'extension' => 'pdf',
            'mime_type' => 'application/pdf', 'tamano_bytes' => 10,
        ]);

        $this->assertNull($reintento, 'El documento 1 ya no es la versión vigente.');
        $this->assertSame(1, (int) $this->conn->table('documentos')->where('es_version_actual', 0)->countAllResults(false),
            'Solo el documento reemplazado queda como histórico.');
        $this->assertSame(2, (int) $this->conn->table('documentos')->where('es_version_actual', 1)->countAllResults(false));
    }

    /* ------------------------------------------------------------------
     * siguienteVersion: versión derivada del historial
     * ------------------------------------------------------------------ */

    public function testSiguienteVersionSinDocumentoPrevioEsUno(): void
    {
        $modelo = new DocumentoModel();

        $this->assertSame(1, $modelo->siguienteVersion(1, self::TIPO_OTROS, null, null));
    }

    public function testSiguienteVersionDerivaDelVigente(): void
    {
        $modelo = new DocumentoModel();

        $this->assertSame(2, $modelo->siguienteVersion(1, self::TIPO_ACTA_NEUTRALIZACION, 1, null));

        $modelo->crearVersionDe(1, [
            'nombre_archivo' => 'v2.pdf', 'nombre_archivo_original' => 'v2.pdf',
            'ruta_relativa' => 'OBR-000001/v2.pdf', 'extension' => 'pdf',
            'mime_type' => 'application/pdf', 'tamano_bytes' => 10,
        ]);

        $this->assertSame(3, $modelo->siguienteVersion(1, self::TIPO_ACTA_NEUTRALIZACION, 1, null),
            'La versión siguiente se calcula desde el historial persistido, no desde nombres en disco.');
    }

    /* ------------------------------------------------------------------
     * reemplazar: permisos y validaciones previas al archivo
     * ------------------------------------------------------------------ */

    public function testReemplazarRechazaAConsulta(): void
    {
        $resultado = $this->withSession($this->sesion(['CONSULTA']))
            ->post('/obras/documentacion/reemplazar', [
                'documento_id'  => 1,
                'obra_id'       => 1,
                'csrf_test_name' => csrf_hash(),
            ]);

        $resultado->assertRedirectTo('/dashboard');

        $this->assertSame(1, (int) $this->conn->table('documentos')->where('obra_id', 1)->where('es_version_actual', 1)->countAllResults(false),
            'La acción rechazada no debe crear versiones.');
    }

    public function testReemplazarRechazaOtraObra(): void
    {
        $resultado = $this->withSession($this->sesion(['ADMINISTRADOR']))
            ->post('/obras/documentacion/reemplazar', [
                'documento_id'  => 1,
                'obra_id'       => 2,
                'csrf_test_name' => csrf_hash(),
            ]);

        $resultado->assertRedirectTo('/obras/documentacion/1');

        $errores = session()->getFlashdata('errores_documento');
        $this->assertIsArray($errores);
        $this->assertStringContainsString('no coincide', implode(' ', $errores));
    }

    public function testReemplazarRechazaUnaVersionNoVigente(): void
    {
        $modelo = new DocumentoModel();

        $modelo->crearVersionDe(1, [
            'nombre_archivo' => 'v2.pdf', 'nombre_archivo_original' => 'v2.pdf',
            'ruta_relativa' => 'OBR-000001/v2.pdf', 'extension' => 'pdf',
            'mime_type' => 'application/pdf', 'tamano_bytes' => 10,
        ]);

        $resultado = $this->withSession($this->sesion(['ADMINISTRADOR']))
            ->post('/obras/documentacion/reemplazar', [
                'documento_id'  => 1,
                'obra_id'       => 1,
                'csrf_test_name' => csrf_hash(),
            ]);

        $resultado->assertRedirectTo('/obras/documentacion/1');
        $this->assertSame(3, (int) $this->conn->table('documentos')->countAllResults(false),
            'No se crea una versión a partir de un documento ya histórico (2 iniciales + 1 versión).');
    }

    /* ------------------------------------------------------------------
     * subir: rechaza duplicados de tipos no múltiples
     * ------------------------------------------------------------------ */

    public function testSubirRechazaUnTipoNoMultipleYaVigente(): void
    {
        $subir = $this->withSession($this->sesion(['ADMINISTRADOR']))
            ->post('/obras/documentacion/subir', [
                'obra_id'           => 1,
                'tipo_documento_id' => self::TIPO_ACTA_NEUTRALIZACION,
                'neutralizacion_id' => 1,
                'csrf_test_name'    => csrf_hash(),
            ]);

        $subir->assertRedirectTo('/obras/documentacion/1');

        $this->assertSame(1, (int) $this->conn->table('documentos')->where('obra_id', 1)->countAllResults(false),
            'No se crea un segundo documento de un tipo por evento ya satisfecho.');
    }

    /* ------------------------------------------------------------------
     * subir: un error de validación reabre el modal y conserva lo cargado
     * ------------------------------------------------------------------ */

    public function testSubirConErrorSolicitaReabrirElModalYConservaLosDatos(): void
    {
        $subir = $this->withSession($this->sesion(['ADMINISTRADOR']))
            ->post('/obras/documentacion/subir', [
                'obra_id'           => 1,
                'tipo_documento_id' => 999999,
                'observaciones'     => 'Observación a conservar',
                'csrf_test_name'    => csrf_hash(),
            ]);

        $subir->assertRedirectTo('/obras/documentacion/1');

        $this->assertSame('alta', session()->getFlashdata('reabrir_modal_documento'),
            'El backend solicita reabrir el modal tras un error de validación.');

        $errores = session()->getFlashdata('errores_documento');
        $this->assertIsArray($errores);

        $this->assertSame(
            'Observación a conservar',
            old('observaciones'),
            'withInput() conserva los datos enviados para rellenar el formulario reabierto.'
        );
    }

    /* ------------------------------------------------------------------
     * ubicacion: solo administradores
     * ------------------------------------------------------------------ */

    public function testUbicacionRechazaAConsulta(): void
    {
        $resultado = $this->withSession($this->sesion(['CONSULTA']))
            ->withHeaders(['Accept' => 'application/json'])
            ->get('/obras/documentacion/ubicacion/1');

        $resultado->assertStatus(403);
    }

    public function testUbicacionDevuelveLaRutaDelArchivoAutorizado(): void
    {
        $this->crearArchivoFisico('OBR-000001/fisico1.pdf', '%PDF-1.4 contenido');

        $resultado = $this->withSession($this->sesion(['ADMINISTRADOR']))
            ->withHeaders(['Accept' => 'application/json'])
            ->get('/obras/documentacion/ubicacion/1');

        $resultado->assertStatus(200);

        $json = json_decode($resultado->getJSON(), true);

        $this->assertTrue($json['ok']);
        $this->assertStringContainsString('fisico1.pdf', $json['ruta_completa']);
        $this->assertStringContainsString('OBR-000001', $json['directorio']);
        $this->assertStringNotContainsString('fisico1.pdf', $json['directorio']);
    }

    /**
     * Una ruta relativa con travesía no autorizada no debe resolverse.
     */
    public function testUbicacionRechazaRutasFueraDeLaRaiz(): void
    {
        $this->conn->table('documentos')->where('id', 1)->update([
            'ruta_relativa' => '../../etc/passwd',
        ]);

        $resultado = $this->withSession($this->sesion(['ADMINISTRADOR']))
            ->withHeaders(['Accept' => 'application/json'])
            ->get('/obras/documentacion/ubicacion/1');

        $resultado->assertStatus(403);
    }

    /* ------------------------------------------------------------------
     * Utilidades
     * ------------------------------------------------------------------ */

    private function crearArchivoFisico(string $relativa, string $contenido): void
    {
        $ruta       = $this->raizAlmacenamiento . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativa);
        $directorio = dirname($ruta);

        if (! is_dir($directorio)) {
            mkdir($directorio, 0755, true);
        }

        file_put_contents($ruta, $contenido);
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

    /** @param list<string> $roles */
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

    private function tabla(string $nombre): string
    {
        return $this->conn->prefixTable($nombre);
    }
}
