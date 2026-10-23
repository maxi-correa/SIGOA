<?php

use App\Models\DocumentoModel;
use App\Models\InspectoresObrasModel;
use CodeIgniter\Config\Factories;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Documentación disponible del inspector (consulta, descarga y apertura).
 *
 * Cubre:
 *
 * * `DocumentoModel::listarVigentesPorObra()`: solo devuelve la versión
 *   vigente de cada documento lógico, nunca el historial.
 * * Autorización: el inspector solo alcanza la documentación de las obras
 *   que tiene asignadas **de forma vigente**; el acceso directo por URL a
 *   otra obra se rechaza.
 * * El rol es el del grupo de rutas: un rol administrativo no entra por la
 *   pantalla del inspector.
 * * La entrega del PDF: responde 403 sin asignación vigente, 404 si el
 *   documento no existe y 200 con el archivo cuando la asignación es
 *   válida; nunca expone la ruta física del servidor.
 *
 * Usa la conexión `tests` (SQLite en memoria) con un esquema mínimo que
 * reproduce los nombres reales de columnas.
 *
 * @internal
 */
final class InspectorDocumentacionTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private BaseConnection $conn;

    private string $raizAlmacenamiento;

    private const TIPO_ACTA = 1;
    private const TIPO_OTROS = 2;

    protected function setUp(): void
    {
        parent::setUp();

        /* Nunca se toca la raíz real de almacenamiento: se inyecta una raíz
           temporal propia. */
        $this->raizAlmacenamiento = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sigoa_docinsp_' . bin2hex(random_bytes(4));

        $config              = new \Config\SigoaStorage();
        $config->storagePath = $this->raizAlmacenamiento;

        Factories::injectMock('config', 'SigoaStorage', $config);

        $this->conn = db_connect();

        $tablas = ['documentos', 'inspectores_obras', 'tipos_documento', 'tipos_licitacion', 'usuarios', 'obras', 'empresas', 'barrios', 'neutralizaciones', 'estados_obra'];

        foreach ($tablas as $tabla) {
            $this->conn->query('DROP TABLE IF EXISTS ' . $this->tabla($tabla));
        }

        $this->conn->query('CREATE TABLE ' . $this->tabla('estados_obra') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            estado VARCHAR(60) NOT NULL,
            descripcion TEXT NULL,
            activo INTEGER NOT NULL DEFAULT 1
        )');

        $this->conn->query('CREATE TABLE ' . $this->tabla('barrios') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre VARCHAR(120) NOT NULL
        )');

        $this->conn->query('CREATE TABLE ' . $this->tabla('empresas') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            razon_social VARCHAR(200) NOT NULL
        )');

        $this->conn->query('CREATE TABLE ' . $this->tabla('tipos_licitacion') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tipo_licitacion VARCHAR(100) NOT NULL
        )');

        $this->conn->query('CREATE TABLE ' . $this->tabla('obras') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            codigo VARCHAR(20) NOT NULL,
            expediente_municipal VARCHAR(60) NULL,
            nombre VARCHAR(200) NOT NULL,
            fecha_inicio DATE NULL,
            barrio_id INTEGER NULL,
            empresa_id INTEGER NULL,
            tipo_licitacion_id INTEGER NULL,
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

        $this->conn->query('CREATE TABLE ' . $this->tabla('inspectores_obras') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            obra_id INTEGER NOT NULL,
            usuario_id INTEGER NOT NULL,
            fecha_inicio DATE NULL,
            fecha_fin DATE NULL,
            documento_id INTEGER NULL,
            observaciones TEXT NULL,
            created_at DATETIME NULL
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
        foreach (['documentos', 'inspectores_obras', 'tipos_documento', 'tipos_licitacion', 'usuarios', 'obras', 'empresas', 'barrios', 'estados_obra'] as $tabla) {
            $this->conn->query('DROP TABLE IF EXISTS ' . $this->tabla($tabla));
        }

        $this->eliminarTree($this->raizAlmacenamiento);

        parent::tearDown();
    }

    private function sembrarDatos(): void
    {
        $this->conn->table('estados_obra')->insert(['id' => 2, 'estado' => 'EN EJECUCIÓN', 'activo' => 1]);

        $this->conn->table('barrios')->insert(['id' => 1, 'nombre' => 'CENTRO']);
        $this->conn->table('empresas')->insert(['id' => 1, 'razon_social' => 'CONSTRUCTORA DEL SUR S.A.']);
        $this->conn->table('tipos_licitacion')->insert(['id' => 1, 'tipo_licitacion' => 'LICITACIÓN PÚBLICA']);

        foreach ([[1, 'OBR-000001'], [2, 'OBR-000002']] as $obra) {
            $this->conn->table('obras')->insert([
                'id'             => $obra[0],
                'codigo'         => $obra[1],
                'expediente_municipal' => 'EXP-00000' . $obra[0],
                'nombre'         => 'OBRA ' . $obra[0],
                'fecha_inicio'   => '2026-01-15',
                'barrio_id'      => 1,
                'empresa_id'     => 1,
                'tipo_licitacion_id' => 1,
                'estado_obra_id' => 2,
                'created_at'     => '2026-01-01 08:00:00',
                'updated_at'     => '2026-01-01 08:00:00',
            ]);
        }

        $this->conn->table('usuarios')->insertBatch([
            ['id' => 1, 'nombre' => 'JUANA', 'apellido' => 'PEREZ'],
            ['id' => 2, 'nombre' => 'CARLOS', 'apellido' => 'GOMEZ'],
        ]);

        $this->conn->table('tipos_documento')->insertBatch([
            ['id' => self::TIPO_ACTA, 'tipo_documento' => 'ACTA DE INICIO', 'activo' => 1],
            ['id' => self::TIPO_OTROS, 'tipo_documento' => 'OTROS', 'activo' => 1],
        ]);

        /* El inspector 1 tiene la obra 1 abierta; la asignación sobre la
           obra 2 está cerrada (histórico). */
        $this->conn->table('inspectores_obras')->insertBatch([
            [
                'id' => 1, 'obra_id' => 1, 'usuario_id' => 1,
                'fecha_inicio' => '2026-02-01', 'fecha_fin' => null,
                'created_at' => '2026-02-01 08:00:00',
            ],
            [
                'id' => 2, 'obra_id' => 2, 'usuario_id' => 1,
                'fecha_inicio' => '2026-02-01', 'fecha_fin' => '2026-03-01',
                'created_at' => '2026-02-01 08:00:00',
            ],
        ]);

        $this->conn->table('documentos')->insertBatch([
            [
                'id' => 1, 'obra_id' => 1, 'tipo_documento_id' => self::TIPO_ACTA,
                'nombre_archivo' => 'OBR-000001_ACTA_INICIO_v1.pdf', 'nombre_archivo_original' => 'ACTA INICIO.pdf',
                'ruta_relativa' => 'OBR-000001/OBR-000001_ACTA_INICIO_v1.pdf', 'extension' => 'pdf',
                'mime_type' => 'application/pdf', 'tamano_bytes' => 9,
                'fecha_documento' => '2026-03-02', 'usuario_id_carga' => 2,
                'neutralizacion_id' => null, 'reinicio_id' => null, 'observaciones' => null,
                'version' => 1, 'es_version_actual' => 0, 'documento_padre_id' => null,
                'created_at' => '2026-03-02 10:00:00', 'updated_at' => '2026-03-02 10:00:00',
            ],
            [
                'id' => 2, 'obra_id' => 1, 'tipo_documento_id' => self::TIPO_ACTA,
                'nombre_archivo' => 'OBR-000001_ACTA_INICIO_v2.pdf', 'nombre_archivo_original' => 'ACTA INICIO.pdf',
                'ruta_relativa' => 'OBR-000001/OBR-000001_ACTA_INICIO_v2.pdf', 'extension' => 'pdf',
                'mime_type' => 'application/pdf', 'tamano_bytes' => 9,
                'fecha_documento' => '2026-05-02', 'usuario_id_carga' => 2,
                'neutralizacion_id' => null, 'reinicio_id' => null, 'observaciones' => 'Segunda versión',
                'version' => 2, 'es_version_actual' => 1, 'documento_padre_id' => 1,
                'created_at' => '2026-05-02 10:00:00', 'updated_at' => '2026-05-02 10:00:00',
            ],
            [
                'id' => 3, 'obra_id' => 1, 'tipo_documento_id' => self::TIPO_OTROS,
                'nombre_archivo' => 'OBR-000001_OTROS.pdf', 'nombre_archivo_original' => 'OTROS.pdf',
                'ruta_relativa' => 'OBR-000001/OBR-000001_OTROS.pdf', 'extension' => 'pdf',
                'mime_type' => 'application/pdf', 'tamano_bytes' => 9,
                'fecha_documento' => null, 'usuario_id_carga' => 2,
                'neutralizacion_id' => null, 'reinicio_id' => null, 'observaciones' => null,
                'version' => 1, 'es_version_actual' => 1, 'documento_padre_id' => null,
                'created_at' => '2026-06-02 10:00:00', 'updated_at' => '2026-06-02 10:00:00',
            ],
            [
                'id' => 4, 'obra_id' => 2, 'tipo_documento_id' => self::TIPO_OTROS,
                'nombre_archivo' => 'OBR-000002_OTROS.pdf', 'nombre_archivo_original' => 'OTROS OBRA 2.pdf',
                'ruta_relativa' => 'OBR-000002/OBR-000002_OTROS.pdf', 'extension' => 'pdf',
                'mime_type' => 'application/pdf', 'tamano_bytes' => 9,
                'fecha_documento' => null, 'usuario_id_carga' => 2,
                'neutralizacion_id' => null, 'reinicio_id' => null, 'observaciones' => null,
                'version' => 1, 'es_version_actual' => 1, 'documento_padre_id' => null,
                'created_at' => '2026-06-03 10:00:00', 'updated_at' => '2026-06-03 10:00:00',
            ],
        ]);
    }

    /* ------------------------------------------------------------------
     * Listado de documentos vigentes
     * ------------------------------------------------------------------ */

    public function testListarVigentesPorObraDevuelveSoloLaVersionActual(): void
    {
        $vigentes = (new DocumentoModel())->listarVigentesPorObra(1);

        $this->assertCount(2, $vigentes, 'La obra 1 tiene dos documentos lógicos, no tres documentos.');

        $ids = array_map(static fn ($d) => (int) $d->id, $vigentes);

        $this->assertContains(2, $ids, 'La versión vigente del acta es el documento 2.');
        $this->assertContains(3, $ids);
        $this->assertNotContains(1, $ids, 'La versión 1 del acta es historial y no se lista.');
    }

    public function testListarPorObraMantieneElHistorialCompleto(): void
    {
        $todos = (new DocumentoModel())->listarPorObra(1);

        $this->assertCount(3, $todos, 'La consulta administrativa sigue viendo el historial.');
    }

    public function testListarVigentesPorObraResuelveElTipoYElUsuarioDeCarga(): void
    {
        $vigentes = (new DocumentoModel())->listarVigentesPorObra(1);

        foreach ($vigentes as $documento) {
            $this->assertNotEmpty($documento->tipo_documento);
            $this->assertSame('GOMEZ', $documento->usuario_apellido);
        }
    }

    /* ------------------------------------------------------------------
     * Autorización
     * ------------------------------------------------------------------ */

    public function testAsignacionVigenteHabilitaLaConsulta(): void
    {
        $this->assertTrue((new InspectoresObrasModel())->esVigente(1, 1));
        $this->assertFalse((new InspectoresObrasModel())->esVigente(2, 1), 'La asignación cerrada no habilita el acceso.');
        $this->assertFalse((new InspectoresObrasModel())->esVigente(1, 2));
    }

    public function testPantallaDeDocumentacionSeAbreConAsignacionVigente(): void
    {
        $resultado = $this->withSession($this->sesion())
            ->get('/inspector/documentacion/1');

        $resultado->assertStatus(200);
        $resultado->assertSee('Documentación disponible');
    }

    public function testPantallaDeDocumentacionRechazaObraSinAsignacionVigente(): void
    {
        $resultado = $this->withSession($this->sesion())
            ->get('/inspector/documentacion/2');

        $resultado->assertRedirectTo('/inspector/dashboard');
    }

    public function testPantallaDeDocumentacionRechazaObraInexistente(): void
    {
        $resultado = $this->withSession($this->sesion())
            ->get('/inspector/documentacion/999');

        $resultado->assertRedirectTo('/inspector/dashboard');
    }

    public function testApiDeDocumentacionDevuelveSoloLosDocumentosVigentes(): void
    {
        $resultado = $this->withSession($this->sesion())
            ->withHeaders(['Accept' => 'application/json'])
            ->get('/inspector/documentacion/api/1');

        $resultado->assertStatus(200);

        $json = json_decode($resultado->getJSON(), true);

        $this->assertTrue($json['ok']);
        $this->assertCount(2, $json['payload']['documentos']);

        $ids = array_map(static fn ($d) => (int) $d['id'], $json['payload']['documentos']);

        $this->assertContains(2, $ids);
        $this->assertNotContains(1, $ids, 'El historial no se entrega al inspector.');

        /* El emparejamiento con la copia local se hace por documento
           lógico: la v2 cuelga del documento raíz 1. */
        $logicos = array_map(static fn ($d) => (int) $d['logico_id'], $json['payload']['documentos']);

        $this->assertContains(1, $logicos, 'El documento lógico del acta es su documento raíz.');
        $this->assertSame(1, (int) $json['payload']['obra']['id']);
        $this->assertSame(1, (int) $json['payload']['usuario']['id']);
    }

    public function testApiDeDocumentacionRechazaObraSinAsignacionVigente(): void
    {
        $resultado = $this->withSession($this->sesion())
            ->withHeaders(['Accept' => 'application/json'])
            ->get('/inspector/documentacion/api/2');

        $resultado->assertStatus(403);
    }

    public function testRolAdministrativoNoEntraEnLaPantallaDelInspector(): void
    {
        /* El filtro de rol redirige a /dashboard en peticiones HTML y
           responde 403 en las que esperan JSON. */
        $html = $this->withSession($this->sesion(['ADMINISTRADOR']))
            ->get('/inspector/documentacion/1');

        $html->assertRedirectTo('/dashboard');

        $json = $this->withSession($this->sesion(['ADMINISTRADOR']))
            ->withHeaders(['Accept' => 'application/json'])
            ->get('/inspector/documentacion/api/1');

        $json->assertStatus(403);
    }

    /* ------------------------------------------------------------------
     * Entrega del PDF
     * ------------------------------------------------------------------ */

    public function testVerDocumentoEntregaElPdfConAsignacionVigente(): void
    {
        $this->crearArchivoFisico('OBR-000001/OBR-000001_ACTA_INICIO_v2.pdf', '%PDF-1.4 acta');

        $resultado = $this->withSession($this->sesion())
            ->get('/inspector/documentacion/ver/2');

        $resultado->assertStatus(200);
        $resultado->assertSee('%PDF-1.4 acta');
    }

    public function testVerDocumentoRechazaDocumentoDeObraSinAsignacionVigente(): void
    {
        $resultado = $this->withSession($this->sesion())
            ->get('/inspector/documentacion/ver/4');

        $resultado->assertStatus(403);
    }

    public function testVerDocumentoRechazaDocumentoInexistente(): void
    {
        $resultado = $this->withSession($this->sesion())
            ->get('/inspector/documentacion/ver/999');

        $resultado->assertStatus(404);
    }

    public function testVerDocumentoRechazaUnaObraQueNoCoincide(): void
    {
        $resultado = $this->withSession($this->sesion())
            ->get('/inspector/documentacion/ver/2?obra_id=2');

        $resultado->assertStatus(403);
    }

    public function testVerDocumentoNoExponeLaRutaFisica(): void
    {
        $this->crearArchivoFisico('OBR-000001/OBR-000001_ACTA_INICIO_v2.pdf', '%PDF-1.4 acta');

        $resultado = $this->withSession($this->sesion())
            ->get('/inspector/documentacion/ver/2');

        $cuerpo = (string) $resultado->getBody();

        $this->assertStringNotContainsString($this->raizAlmacenamiento, $cuerpo);
        $this->assertStringNotContainsString('OBR-000001_ACTA_INICIO_v2.pdf', $cuerpo,
            'La respuesta entrega el contenido, no la ubicación del archivo en el servidor.');
    }

    public function testVerDocumentoRechazaRutaFueraDeLaRaiz(): void
    {
        $this->conn->table('documentos')->where('id', 2)->update([
            'ruta_relativa' => '../../etc/passwd',
        ]);

        $resultado = $this->withSession($this->sesion())
            ->get('/inspector/documentacion/ver/2');

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
    private function sesion(array $roles = ['INSPECTOR']): array
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
