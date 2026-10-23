<?php

use App\Models\DocumentoModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Eventos de neutralización y reinicio en la gestión documental.
 *
 * La neutralización y el reinicio viven en la tabla `neutralizaciones`
 * (`fecha_inicio` es la fecha de neutralización y `fecha_reinicio` la del
 * reinicio). Esta prueba cubre que:
 *
 * * la consulta de la pantalla usa las columnas reales del esquema y no
 *   falla con «Unknown column»;
 * * cada documento muestra el evento de su propia obra, sin mezclar
 *   períodos de obras distintas;
 * * el acta de reinicio se habilita solo con un reinicio registrado y su
 *   acta de neutralización correspondiente cargada;
 * * el acta de neutralización se habilita solo con una fecha registrada;
 * * CONSULTA puede ver la pantalla pero no subir documentos.
 *
 * Usa la conexión `tests` (SQLite en memoria) con un esquema mínimo que
 * reproduce los nombres reales de columnas de `neutralizaciones` y
 * `documentos`, incluidas las columnas de la migración
 * `2026-10-16-140000_AddDocumentacionFieldsToDocumentos`.
 *
 * @internal
 */
final class DocumentacionEventosTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private BaseConnection $conn;

    private const TIPO_ACTA_NEUTRALIZACION = 4;
    private const TIPO_ACTA_REINICIO = 5;
    private const TIPO_OTROS = 16;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conn = db_connect();

        foreach ([
            'documentos',
            'neutralizaciones',
            'tipos_documento',
            'usuarios',
            'obras',
            'estados_obra',
            'barrios',
            'empresas',
            'tipos_licitacion',
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

        $this->conn->query('CREATE TABLE ' . $this->tabla('barrios') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre VARCHAR(200) NULL,
            activo INTEGER NOT NULL DEFAULT 1
        )');

        $this->conn->query('CREATE TABLE ' . $this->tabla('empresas') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            razon_social VARCHAR(200) NULL,
            activo INTEGER NOT NULL DEFAULT 1
        )');

        $this->conn->query('CREATE TABLE ' . $this->tabla('tipos_licitacion') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tipo_licitacion VARCHAR(120) NULL,
            activo INTEGER NOT NULL DEFAULT 1
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

        /* Estructura real: migración 2026-09-01-125757_CreateNeutralizaciones.
           No existe ninguna columna `fecha_neutralizacion`. */
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

        /* Estructura real: migraciones 2026-09-01-123255_CreateDocumentos y
           2026-10-16-140000_AddDocumentacionFieldsToDocumentos. */
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
        foreach ([
            'documentos',
            'neutralizaciones',
            'tipos_documento',
            'usuarios',
            'obras',
            'estados_obra',
            'barrios',
            'empresas',
            'tipos_licitacion',
        ] as $tabla) {
            $this->conn->query('DROP TABLE IF EXISTS ' . $this->tabla($tabla));
        }

        parent::tearDown();
    }

    /**
     * Obras 1 a 4 en EN EJECUCIÓN, neutralizaciones reiniciadas y abiertas
     * en obras distintas, y documentos vinculados en la obra 1.
     */
    private function sembrarDatos(): void
    {
        $this->conn->table('estados_obra')->insert([
            'id' => 2, 'estado' => 'EN EJECUCIÓN', 'activo' => 1,
        ]);
        $this->conn->table('barrios')->insert(['id' => 1, 'nombre' => 'CENTRO', 'activo' => 1]);
        $this->conn->table('empresas')->insert(['id' => 1, 'razon_social' => 'CONSTRUCTORA SA', 'activo' => 1]);
        $this->conn->table('tipos_licitacion')->insert(['id' => 1, 'tipo_licitacion' => 'PRECIO GLOBAL', 'activo' => 1]);

        foreach ([[1, 'OBR-000001', 'EXP-001'], [2, 'OBR-000002', 'EXP-002'], [3, 'OBR-000003', 'EXP-003'], [4, 'OBR-000004', 'EXP-004'], [5, 'OBR-000005', 'EXP-005']] as $obra) {
            $this->conn->table('obras')->insert([
                'id'                 => $obra[0],
                'codigo'             => $obra[1],
                'expediente_municipal' => $obra[2],
                'nombre'             => 'OBRA DOCUMENTACION ' . $obra[0],
                'barrio_id'          => 1,
                'empresa_id'         => 1,
                'tipo_licitacion_id' => 1,
                'estado_obra_id'     => 2,
                'fecha_inicio'       => '2026-01-15',
                'created_at'         => '2026-01-01 08:00:00',
                'updated_at'         => '2026-01-01 08:00:00',
            ]);
        }

        $this->conn->table('tipos_documento')->insertBatch([
            ['id' => self::TIPO_ACTA_NEUTRALIZACION, 'tipo_documento' => 'ACTA DE NEUTRALIZACIÓN', 'activo' => 1],
            ['id' => self::TIPO_ACTA_REINICIO, 'tipo_documento' => 'ACTA DE REINICIO', 'activo' => 1],
            ['id' => self::TIPO_OTROS, 'tipo_documento' => 'OTROS', 'activo' => 1],
        ]);

        $this->conn->table('usuarios')->insert([
            'id' => 1, 'nombre' => 'JUANA', 'apellido' => 'PEREZ',
        ]);

        /* n1 (obra 1) reiniciada; n2 (obra 1) abierta; n3 (obra 2)
           reiniciada sin acta; n4 (obra 3) abierta; n5 (obra 5) reiniciada
           con acta de neutralización y sin acta de reinicio: reinicio
           pendiente de documentar; obra 4 sin eventos. */
        $this->conn->table('neutralizaciones')->insertBatch([
            ['id' => 1, 'obra_id' => 1, 'numero' => 1, 'fecha_inicio' => '2026-03-01', 'fecha_reinicio' => '2026-04-01', 'created_at' => '2026-03-01 09:00:00', 'updated_at' => '2026-04-01 09:00:00'],
            ['id' => 2, 'obra_id' => 1, 'numero' => 2, 'fecha_inicio' => '2026-05-01', 'fecha_reinicio' => null, 'created_at' => '2026-05-01 09:00:00', 'updated_at' => '2026-05-01 09:00:00'],
            ['id' => 3, 'obra_id' => 2, 'numero' => 1, 'fecha_inicio' => '2026-02-01', 'fecha_reinicio' => '2026-02-20', 'created_at' => '2026-02-01 09:00:00', 'updated_at' => '2026-02-20 09:00:00'],
            ['id' => 4, 'obra_id' => 3, 'numero' => 1, 'fecha_inicio' => '2026-06-01', 'fecha_reinicio' => null, 'created_at' => '2026-06-01 09:00:00', 'updated_at' => '2026-06-01 09:00:00'],
            ['id' => 5, 'obra_id' => 5, 'numero' => 1, 'fecha_inicio' => '2026-03-10', 'fecha_reinicio' => '2026-04-10', 'created_at' => '2026-03-10 09:00:00', 'updated_at' => '2026-04-10 09:00:00'],
        ]);

        $this->conn->table('documentos')->insertBatch([
            [
                'id' => 1, 'obra_id' => 1, 'tipo_documento_id' => self::TIPO_ACTA_NEUTRALIZACION,
                'nombre_archivo' => 'fisico1.pdf', 'nombre_archivo_original' => 'ACTA_NEUTRALIZACION_1.pdf',
                'ruta_relativa' => 'OBR-000001/fisico1.pdf', 'extension' => 'pdf', 'mime_type' => 'application/pdf',
                'tamano_bytes' => 1024, 'fecha_documento' => '2026-03-02', 'usuario_id_carga' => 1,
                'neutralizacion_id' => 1, 'reinicio_id' => null, 'version' => 1, 'es_version_actual' => 1,
                'created_at' => '2026-03-02 10:00:00', 'updated_at' => '2026-03-02 10:00:00',
            ],
            [
                'id' => 2, 'obra_id' => 1, 'tipo_documento_id' => self::TIPO_ACTA_REINICIO,
                'nombre_archivo' => 'fisico2.pdf', 'nombre_archivo_original' => 'ACTA_REINICIO_1.pdf',
                'ruta_relativa' => 'OBR-000001/fisico2.pdf', 'extension' => 'pdf', 'mime_type' => 'application/pdf',
                'tamano_bytes' => 2048, 'fecha_documento' => '2026-04-02', 'usuario_id_carga' => 1,
                'neutralizacion_id' => 1, 'reinicio_id' => 1, 'version' => 1, 'es_version_actual' => 1,
                'created_at' => '2026-04-02 10:00:00', 'updated_at' => '2026-04-02 10:00:00',
            ],
            [
                'id' => 3, 'obra_id' => 1, 'tipo_documento_id' => self::TIPO_OTROS,
                'nombre_archivo' => 'fisico3.pdf', 'nombre_archivo_original' => 'INFORME_VARIOS.pdf',
                'ruta_relativa' => 'OBR-000001/fisico3.pdf', 'extension' => 'pdf', 'mime_type' => 'application/pdf',
                'tamano_bytes' => 512, 'fecha_documento' => null, 'usuario_id_carga' => null,
                'neutralizacion_id' => null, 'reinicio_id' => null, 'version' => 1, 'es_version_actual' => 1,
                'created_at' => '2026-04-03 10:00:00', 'updated_at' => '2026-04-03 10:00:00',
            ],
            [
                'id' => 4, 'obra_id' => 2, 'tipo_documento_id' => self::TIPO_ACTA_REINICIO,
                'nombre_archivo' => 'fisico4.pdf', 'nombre_archivo_original' => 'ACTA_REINICIO_OBRA2.pdf',
                'ruta_relativa' => 'OBR-000002/fisico4.pdf', 'extension' => 'pdf', 'mime_type' => 'application/pdf',
                'tamano_bytes' => 4096, 'fecha_documento' => '2026-02-21', 'usuario_id_carga' => null,
                'neutralizacion_id' => 3, 'reinicio_id' => 3, 'version' => 1, 'es_version_actual' => 1,
                'created_at' => '2026-02-21 10:00:00', 'updated_at' => '2026-02-21 10:00:00',
            ],
            [
                'id' => 5, 'obra_id' => 5, 'tipo_documento_id' => self::TIPO_ACTA_NEUTRALIZACION,
                'nombre_archivo' => 'fisico5.pdf', 'nombre_archivo_original' => 'ACTA_NEUTRALIZACION_OBRA5.pdf',
                'ruta_relativa' => 'OBR-000005/fisico5.pdf', 'extension' => 'pdf', 'mime_type' => 'application/pdf',
                'tamano_bytes' => 1024, 'fecha_documento' => '2026-03-11', 'usuario_id_carga' => 1,
                'neutralizacion_id' => 5, 'reinicio_id' => null, 'version' => 1, 'es_version_actual' => 1,
                'created_at' => '2026-03-11 10:00:00', 'updated_at' => '2026-03-11 10:00:00',
            ],
        ]);
    }

    /* ------------------------------------------------------------------
     * La consulta usa las columnas reales del esquema
     * ------------------------------------------------------------------ */

    public function testLaConsultaDeDocumentacionObtieneLasFechasRealesDeLosEventos(): void
    {
        try {
            $filas = (new DocumentoModel())->listarPorObra(1);
        } catch (\Throwable $e) {
            $this->fail('La consulta de documentación falló con las columnas reales: ' . $e->getMessage());
        }

        $this->assertCount(3, $filas);

        $porId = [];
        foreach ($filas as $fila) {
            $porId[(int) $fila->id] = $fila;
        }

        $acta = $porId[1];
        $this->assertSame('2026-03-01', $acta->fecha_neutralizacion, 'El acta de neutralización debe mostrar la fecha de su período.');
        $this->assertNull($acta->fecha_reinicio, 'El acta de neutralización no referencia un reinicio.');
        $this->assertSame(1, (int) $acta->neutralizacion_numero);
        $this->assertNull($acta->reinicio_numero, 'El acta de neutralización no está vinculada a un reinicio.');
        $this->assertSame('JUANA', $acta->usuario_nombre);
        $this->assertSame('PEREZ', $acta->usuario_apellido);

        $reinicio = $porId[2];
        $this->assertSame('2026-04-01', $reinicio->fecha_reinicio, 'El acta de reinicio debe mostrar la fecha del reinicio documentado.');
        $this->assertSame(1, (int) $reinicio->reinicio_numero);
        $this->assertSame('2026-03-01', $reinicio->fecha_neutralizacion, 'El acta de reinicio también queda asociado a su neutralización.');

        $otros = $porId[3];
        $this->assertNull($otros->fecha_neutralizacion);
        $this->assertNull($otros->fecha_reinicio);
        $this->assertNull($otros->usuario_nombre);
    }

    public function testElCodigoDeLaConsultaNoReferenciaColumnasInexistentes(): void
    {
        $codigo = file_get_contents(APPPATH . 'Models/DocumentoModel.php');

        $this->assertNotFalse($codigo);
        $this->assertStringNotContainsString('fecha_reinicio_documentada', $codigo);
        $this->assertStringContainsString('n.fecha_inicio as fecha_neutralizacion', $codigo);
        $this->assertStringContainsString('r.fecha_reinicio as fecha_reinicio', $codigo);

        $vista = file_get_contents(APPPATH . 'Views/obras/documentacion.php');

        $this->assertNotFalse($vista);
        $this->assertStringNotContainsString('$n->fecha_neutralizacion', $vista);
    }

    /* ------------------------------------------------------------------
     * Los eventos no se mezclan entre obras
     * ------------------------------------------------------------------ */

    public function testLosEventosDeUnaObraNoAfectanALaOtra(): void
    {
        $obra1 = (new DocumentoModel())->listarPorObra(1);
        $obra2 = (new DocumentoModel())->listarPorObra(2);

        foreach ($obra1 as $fila) {
            $this->assertSame(1, (int) $fila->obra_id);
        }

        $this->assertCount(1, $obra2);
        $this->assertSame(2, (int) $obra2[0]->obra_id);
        $this->assertSame('2026-02-20', $obra2[0]->fecha_reinicio, 'La fecha proviene de la neutralización de la obra 2, no de la obra 1.');
        $this->assertNull($obra2[0]->usuario_nombre);

        $reinicioObra1 = null;
        foreach ($obra1 as $fila) {
            if ((int) $fila->id === 2) {
                $reinicioObra1 = $fila;
            }
        }

        $this->assertNotNull($reinicioObra1);
        $this->assertSame('2026-04-01', $reinicioObra1->fecha_reinicio);
        $this->assertNotSame('2026-02-20', $reinicioObra1->fecha_reinicio);
    }

    /* ------------------------------------------------------------------
     * La pantalla carga con los eventos reales
     * ------------------------------------------------------------------ */

    public function testLaPantallaDocumentacionRenderizaLosEventosDeLaObra(): void
    {
        $resultado = $this->withSession($this->sesion(['ADMINISTRADOR']))
            ->get('/obras/documentacion/1');

        $resultado->assertStatus(200);

        $cuerpo = $this->cuerpo($resultado);

        $this->assertStringContainsString('OBRA DOCUMENTACION 1', $cuerpo);
        $this->assertStringContainsString('Neutralización N° 1 - 01/03/2026', $cuerpo);
        $this->assertStringContainsString('Reinicio N° 1 - 01/04/2026', $cuerpo);
        $this->assertStringContainsString('Versión vigente', $cuerpo);
        $this->assertStringNotContainsString('No hay documentación cargada', $cuerpo);
    }

    public function testLaPantallaDocumentacionDeUnaObraSinDocumentosMuestraElEstadoVacio(): void
    {
        $resultado = $this->withSession($this->sesion(['CONSULTA']))
            ->get('/obras/documentacion/4');

        $resultado->assertStatus(200);

        $this->assertStringContainsString('No hay documentación cargada', $this->cuerpo($resultado));
    }

    /* ------------------------------------------------------------------
     * Reglas por tipo documental
     * ------------------------------------------------------------------ */

    public function testElActaDeReinicioSeHabilitaConReinicioRegistradoYActaDeNeutralizacionCargada(): void
    {
        $resultado = $this->withSession($this->sesion(['ADMINISTRADOR']))
            ->get('/obras/documentacion/5');

        $resultado->assertStatus(200);

        $this->assertSame('1', $this->habilitadoDe($resultado, self::TIPO_ACTA_REINICIO),
            'La obra 5 tiene la neutralización 5 reiniciada y su acta de neutralización cargada: el acta de reinicio está pendiente y disponible.');
    }

    /**
     * Cuando el reinicio ya está documentado (versión vigente), el tipo deja
     * de ofrecerse en «Agregar documento»: solo queda «Reemplazar documento».
     */
    public function testElActaDeReinicioDocumentadaDejaDeOfrecerseEnAgregar(): void
    {
        $resultado = $this->withSession($this->sesion(['ADMINISTRADOR']))
            ->get('/obras/documentacion/1');

        $resultado->assertStatus(200);

        $this->assertNull($this->habilitadoDe($resultado, self::TIPO_ACTA_REINICIO),
            'La obra 1 ya tiene el acta de reinicio de la neutralización 1; el tipo no debe ofrecerse.');
    }

    public function testElActaDeReinicioSeDeshabilitaSinActaDeNeutralizacionCorrespondiente(): void
    {
        $resultado = $this->withSession($this->sesion(['ADMINISTRADOR']))
            ->get('/obras/documentacion/2');

        $resultado->assertStatus(200);

        $this->assertSame('0', $this->habilitadoDe($resultado, self::TIPO_ACTA_REINICIO));
        $this->assertStringContainsString(
            'Debe existir el acta de neutralización correspondiente cargada para la obra.',
            $this->cuerpo($resultado)
        );
    }

    public function testElActaDeReinicioSeDeshabilitaSinFechaDeReinicioRegistrada(): void
    {
        $resultado = $this->withSession($this->sesion(['ADMINISTRADOR']))
            ->get('/obras/documentacion/3');

        $resultado->assertStatus(200);

        $this->assertSame('0', $this->habilitadoDe($resultado, self::TIPO_ACTA_REINICIO));
        $this->assertStringContainsString(
            'Debe estar registrada la fecha del reinicio que se desea documentar.',
            $this->cuerpo($resultado)
        );
    }

    public function testElActaDeNeutralizacionSeHabilitaConFechaRegistradaYSeDeshabilitaSinElla(): void
    {
        $conFecha = $this->withSession($this->sesion(['ADMINISTRADOR']))
            ->get('/obras/documentacion/3');

        $conFecha->assertStatus(200);
        $this->assertSame('1', $this->habilitadoDe($conFecha, self::TIPO_ACTA_NEUTRALIZACION),
            'La obra 3 tiene la neutralización 4 con fecha de neutralización registrada.');

        $sinFecha = $this->withSession($this->sesion(['ADMINISTRADOR']))
            ->get('/obras/documentacion/4');

        $sinFecha->assertStatus(200);
        $this->assertSame('0', $this->habilitadoDe($sinFecha, self::TIPO_ACTA_NEUTRALIZACION));
        $this->assertStringContainsString(
            'Debe existir una fecha de neutralización registrada.',
            $this->cuerpo($sinFecha)
        );
    }

    public function testElSelectorDeReinicioSoloOfrecePeriodosConReinicioRegistrado(): void
    {
        $resultado = $this->withSession($this->sesion(['ADMINISTRADOR']))
            ->get('/obras/documentacion/5');

        $resultado->assertStatus(200);

        $cuerpo = $this->cuerpo($resultado);
        $select = $this->fragmento($cuerpo, 'id="reinicio_id"', '</select>');

        $this->assertStringContainsString('Reinicio 10/04/2026', $select);
        $this->assertStringNotContainsString('value="2"',
            $select, 'La neutralización abierta (N° 2) no debe ofrecerse como reinicio.');
    }

    /**
     * El selector de reinicio solo lista los reinicios aún no documentados,
     * no los que ya tienen acta vigente.
     */
    public function testElSelectorDeReinicioNoOfreceReiniciosYaDocumentados(): void
    {
        $resultado = $this->withSession($this->sesion(['ADMINISTRADOR']))
            ->get('/obras/documentacion/2');

        $resultado->assertStatus(200);

        $cuerpo = $this->cuerpo($resultado);
        $select = $this->fragmento($cuerpo, 'id="reinicio_id"', '</select>');

        $this->assertStringNotContainsString('Reinicio', $select,
            'La obra 2 ya tiene documentado su reinicio; no debe ofrecerse otro.');
    }

    /* ------------------------------------------------------------------
     * Versionado: la versión actual se obtiene con la API del Query Builder
     * ------------------------------------------------------------------ */

    /**
     * Regresión: `obtenerVersionActual` construye la consulta con
     * `$this->db->table()`, y en el Query Builder de CI 4.7.4 no existe
     * `first()` (es un método del Model). Debe usar `get()->getRow()`.
     */
    public function testObtenerVersionActualDevuelveLaVersionVigenteDelEvento(): void
    {
        $modelo = new DocumentoModel();

        $acta = $modelo->obtenerVersionActual(1, self::TIPO_ACTA_NEUTRALIZACION, 1, null);
        $this->assertNotNull($acta);
        $this->assertSame(1, (int) $acta->id);
        $this->assertSame('ACTA_NEUTRALIZACION_1.pdf', $acta->nombre_archivo_original);

        $reinicio = $modelo->obtenerVersionActual(1, self::TIPO_ACTA_REINICIO, 1, 1);
        $this->assertNotNull($reinicio);
        $this->assertSame(2, (int) $reinicio->id);

        $sinEventos = $modelo->obtenerVersionActual(1, self::TIPO_OTROS, null, null);
        $this->assertNotNull($sinEventos);
        $this->assertSame(3, (int) $sinEventos->id);

        $inexistente = $modelo->obtenerVersionActual(3, self::TIPO_ACTA_REINICIO, null, null);
        $this->assertNull($inexistente, 'La obra 3 no tiene acta de reinicio cargado.');
    }

    /**
     * El filtro de pertenencia es por evento: una neutralización de otra
     * obra no puede devolver la versión vigente.
     */
    public function testObtenerVersionActualNoAtraviesaObras(): void
    {
        $modelo = new DocumentoModel();

        $this->assertNull(
            $modelo->obtenerVersionActual(2, self::TIPO_ACTA_NEUTRALIZACION, 1, null),
            'La neutralización 1 pertenece a la obra 1, no a la obra 2.'
        );

        $this->assertNull(
            $modelo->obtenerVersionActual(1, self::TIPO_ACTA_REINICIO, 3, 3),
            'La neutralización 3 pertenece a la obra 2.'
        );
    }

    /* ------------------------------------------------------------------
     * Permisos: CONSULTA ve la pantalla pero no sube
     * ------------------------------------------------------------------ */

    public function testConsultaPuedeVerPeroNoCargarDocumentos(): void
    {
        $ver = $this->withSession($this->sesion(['CONSULTA']))
            ->get('/obras/documentacion/1');

        $ver->assertStatus(200);
        $this->assertStringNotContainsString('Agregar documento', $this->cuerpo($ver));

        $subir = $this->withSession($this->sesion(['CONSULTA']))
            ->post('/obras/documentacion/subir', [
                'obra_id'           => 1,
                'tipo_documento_id' => self::TIPO_OTROS,
                'csrf_test_name'    => csrf_hash(),
            ]);

        $subir->assertRedirectTo('/dashboard');

        $this->assertSame(5, (int) $this->conn->table('documentos')->countAllResults(false),
            'La carga rechazada no debe crear registros.');
    }

    /* ------------------------------------------------------------------
     * El acta de reinicio se asocia a su neutralización y a su obra
     * ------------------------------------------------------------------ */

    public function testLaCargaDeActaDeReinicioValidaEventosAntesDeEscribirArchivos(): void
    {
        $controlador = file_get_contents(APPPATH . 'Controllers/Documentacion.php');

        $this->assertNotFalse($controlador);

        $inicio = strpos($controlador, 'public function subir(');
        $this->assertIsInt($inicio);

        $cuerpo = substr($controlador, (int) $inicio);

        $posEventos     = strpos($cuerpo, "getPost('reinicio_id')");
        $posMover       = strpos($cuerpo, '$file->move(');
        $posValidarPdf  = strpos($cuerpo, "getExtension() !== 'pdf'");

        $this->assertIsInt($posEventos, 'La rama de reinicio debe validar el evento.');
        $this->assertIsInt($posMover);
        $this->assertIsInt($posValidarPdf);
        $this->assertLessThan($posValidarPdf, $posEventos,
            'El evento se valida antes de procesar el archivo.');
        $this->assertLessThan($posMover, $posEventos,
            'El evento se valida antes de mover el archivo al almacenamiento.');

        /* Vínculo con la neutralización de la obra y regla del reinicio. */
        $this->assertStringContainsString("where('obra_id', \$obraId)", $cuerpo);
        $this->assertStringContainsString('existeActaNeutralizacion($obraId, (int) $n->id)', $cuerpo);
        $this->assertStringContainsString('empty($n->fecha_reinicio)', $cuerpo);
        $this->assertStringContainsString('$neutralizacionId = (int) $n->id;', $cuerpo,
            'El acta de reinicio se asocia también a la neutralización que lo originó.');
    }

    /* ------------------------------------------------------------------
     * Utilidades
     * ------------------------------------------------------------------ */

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

    private function cuerpo(object $resultado): string
    {
        return html_entity_decode(
            (string) preg_replace('/\s+/', ' ', $resultado->getBody()),
            ENT_QUOTES
        );
    }

    /**
     * Valor de `data-habilitado` de la opción de un tipo documental.
     */
    private function habilitadoDe(object $resultado, int $tipoId): ?string
    {
        $cuerpo = $this->cuerpo($resultado);

        if (preg_match('/<option value="' . $tipoId . '"[^>]*>/', $cuerpo, $coincidencia) !== 1) {
            return null;
        }

        if (preg_match('/data-habilitado="(\d)"/', $coincidencia[0], $valor) !== 1) {
            return null;
        }

        return $valor[1];
    }

    /**
     * Fragmento de HTML entre dos marcas (cuerpo normalizado).
     */
    private function fragmento(string $cuerpo, string $desde, string $hasta): string
    {
        $pos = strpos($cuerpo, $desde);

        $this->assertIsInt($pos, "No se encontró la marca «{$desde}»");

        $fin = strpos($cuerpo, $hasta, (int) $pos);

        $this->assertIsInt($fin, "No se encontró el cierre «{$hasta}»");

        return substr($cuerpo, (int) $pos, (int) $fin - (int) $pos);
    }

    private function tabla(string $nombre): string
    {
        return $this->conn->prefixTable($nombre);
    }
}
