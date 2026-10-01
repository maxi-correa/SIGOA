<?php

use App\Libraries\Uuid;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;

/**
 * Sincronización servidor de fotografías (Fase D.4, docs/SIGOA.md §56).
 *
 * Endpoint `POST /inspector/sincronizar/fotografias`: alta confirmada por
 * uuid (idempotente) con autorización heredada de la inspección (§52.4),
 * identidad tomada de la sesión (nunca del payload), validación real del
 * archivo (finfo + getimagesize), escritura en la estructura definitiva
 * `OBR-XXXXXX/YYYY-MM-DD/HH-mm-ss/{IMAGENES,THUMBNAILS}` (Fase E.2: la
 * carpeta se deriva de fecha + hora de la inspección, con sufijo ordinal ante
 * coincidencias, nunca del UUID) y thumbnail regenerado en el servidor. CSRF
 * global y filtros auth + role:INSPECTOR.
 *
 * Usa la conexión `tests` (SQLite en memoria compartida con el grupo) y una
 * raíz de almacenamiento temporal, eliminada al finalizar.
 *
 * @internal
 */
final class SincronizarFotografiasTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private BaseConnection $conn;

    private string $tmp;

    private string $raizOriginal;

    private int $obraId;

    private int $inspectorId;

    private int $otroInspectorId;

    private int $inspeccionId;

    private string $uuidInspeccion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conn = db_connect();

        $this->tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sigoa_d4_' . bin2hex(random_bytes(4));
        mkdir($this->tmp, 0755, true);

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

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('inspectores_obras') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            obra_id INTEGER NOT NULL,
            usuario_id INTEGER NOT NULL,
            fecha_inicio DATE NULL,
            fecha_fin DATE NULL,
            documento_id INTEGER NULL,
            observaciones TEXT NULL,
            created_at DATETIME NULL,
            FOREIGN KEY (obra_id) REFERENCES ' . $this->tabla('obras') . ' (id),
            FOREIGN KEY (usuario_id) REFERENCES ' . $this->tabla('usuarios') . ' (id)
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
            updated_at DATETIME NULL,
            FOREIGN KEY (inspeccion_id) REFERENCES ' . $this->tabla('inspecciones') . ' (id)
        )');

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('operaciones_sincronizacion') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            usuario_id INTEGER NOT NULL,
            tipo_operacion VARCHAR(30) NOT NULL,
            entidad VARCHAR(100) NOT NULL,
            registro_id VARCHAR(100) NULL,
            estado VARCHAR(30) NOT NULL,
            intentos INTEGER NOT NULL DEFAULT 0,
            ultimo_intento DATETIME NULL,
            error TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            FOREIGN KEY (usuario_id) REFERENCES ' . $this->tabla('usuarios') . ' (id)
        )');

        $this->limpiar();

        $this->conn->table('obras')->insert([
            'codigo'         => 'OBR-000001',
            'nombre'         => 'OBRA SINCRONIZACIÓN D4',
            'estado_obra_id' => 1,
        ]);
        $this->obraId = (int) $this->conn->insertID();

        $this->conn->table('usuarios')->insert([
            'usuario'       => 'PRUEBA_INSPECTOR_D4',
            'password_hash' => password_hash('Inspector123', PASSWORD_DEFAULT),
            'activo'        => 1,
        ]);
        $this->inspectorId = (int) $this->conn->insertID();

        $this->conn->table('usuarios')->insert([
            'usuario'       => 'PRUEBA_OTRO_D4',
            'password_hash' => password_hash('Inspector123', PASSWORD_DEFAULT),
            'activo'        => 1,
        ]);
        $this->otroInspectorId = (int) $this->conn->insertID();

        $this->conn->table('inspectores_obras')->insert([
            'obra_id'      => $this->obraId,
            'usuario_id'   => $this->inspectorId,
            'fecha_inicio' => '2026-03-01',
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        $this->uuidInspeccion = Uuid::v4();

        $this->conn->table('inspecciones')->insert([
            'uuid'             => $this->uuidInspeccion,
            'obra_id'          => $this->obraId,
            'inspector_id'     => $this->inspectorId,
            'fecha_inspeccion' => '2026-03-15',
            'hora_inspeccion'  => '10:30:00',
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);
        $this->inspeccionId = (int) $this->conn->insertID();

        $config = config('SigoaStorage');
        $this->raizOriginal = $config->storagePath;
        $config->storagePath = $this->tmp;
    }

    protected function tearDown(): void
    {
        config('SigoaStorage')->storagePath = $this->raizOriginal;

        $this->limpiar();

        $this->eliminarTree($this->tmp);

        service('superglobals')->setFilesArray([]);
        service('superglobals')->setPostArray([]);

        parent::tearDown();
    }

    /* =================================================================
       Utilidades
       ================================================================= */

    private function tabla(string $nombre): string
    {
        return $this->conn->prefixTable($nombre);
    }

    private function limpiar(): void
    {
        foreach (['operaciones_sincronizacion', 'fotografias', 'inspectores_obras', 'inspecciones', 'obras', 'usuarios'] as $tabla) {
            $this->conn->query('DELETE FROM ' . $this->tabla($tabla));
        }
    }

    private function eliminarTree(string $ruta): void
    {
        if ($ruta === '' || ! is_dir($ruta)) {
            return;
        }

        $entradas = scandir($ruta);

        foreach ($entradas as $entrada) {
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

    private function sesionInspector(): array
    {
        return [
            'logged_in' => true,
            'activo'    => true,
            'user_id'   => $this->inspectorId,
            'username'  => 'PRUEBA_INSPECTOR_D4',
            'user_name' => 'PRUEBA_INSPECTOR_D4',
            'roles'     => ['INSPECTOR'],
        ];
    }

    /**
     * Crea un JPEG real en disco para usar como archivo subido.
     */
    private function crearJpeg(int $ancho = 800, int $alto = 600, string $nombre = 'origen.jpg'): string
    {
        $ruta = $this->tmp . DIRECTORY_SEPARATOR . $nombre;

        $imagen = imagecreatetruecolor($ancho, $alto);
        imagefilledrectangle($imagen, 0, 0, $ancho, $alto, imagecolorallocate($imagen, 40, 90, 160));
        imagejpeg($imagen, $ruta, 90);
        imagedestroy($imagen);

        return $ruta;
    }

    private function crearArchivoConContenido(string $contenido, string $nombre): string
    {
        $ruta = $this->tmp . DIRECTORY_SEPARATOR . $nombre;

        file_put_contents($ruta, $contenido);

        return $ruta;
    }

    private function crearArchivoGrande(string $nombre): string
    {
        $ruta = $this->tmp . DIRECTORY_SEPARATOR . $nombre;

        $manejador = fopen($ruta, 'wb');
        fseek($manejador, 8388608 + 1024);
        fwrite($manejador, '0');
        fclose($manejador);

        return $ruta;
    }

    private function parametros(array $extra = []): array
    {
        return array_merge([
            'uuid'             => Uuid::v4(),
            'inspeccion_uuid'  => $this->uuidInspeccion,
        ], $extra);
    }

    /**
     * Ejecuta el endpoint con un archivo real.
     */
    private function enviar(
        array $params,
        ?string $rutaArchivo = null,
        ?array $sesion = null,
        bool $conCsrf = true,
        string $nombreCliente = 'captura.jpg'
    ): TestResponse {
        if ($sesion === null) {
            $sesion = $this->sesionInspector();
        }

        $headers = [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept'           => 'application/json',
        ];

        if ($conCsrf) {
            $headers['X-CSRF-TOKEN'] = csrf_hash();
        }

        if ($rutaArchivo !== null) {
            service('superglobals')->setFilesArray([
                'archivo' => [
                    'name'     => $nombreCliente,
                    'type'     => 'image/jpeg',
                    'tmp_name' => $rutaArchivo,
                    'error'    => UPLOAD_ERR_OK,
                    'size'     => (int) filesize($rutaArchivo),
                ],
            ]);
        } else {
            service('superglobals')->setFilesArray([]);
        }

        return $this->withHeaders($headers)
            ->withSession($sesion)
            ->post('/inspector/sincronizar/fotografias', $params);
    }

    private function cuerpo(TestResponse $respuesta): array
    {
        return json_decode($respuesta->getJSON(), true) ?? [];
    }

    private function primeraFotografia(): ?object
    {
        return $this->conn->table('fotografias')->orderBy('id', 'ASC')->get()->getRow();
    }

    /**
     * Revoca la asignación del inspector, de modo que la autorización
     * histórica de la fecha de inspección falle.
     */
    private function revocarAsignacion(): void
    {
        $this->conn->table('inspectores_obras')
            ->where('obra_id', $this->obraId)
            ->where('usuario_id', $this->inspectorId)
            ->delete();
    }

    /**
     * Resuelve una ruta relativa de la base al directorio temporal de prueba.
     */
    private function rutaAbsoluta(string $relativa): string
    {
        return $this->tmp . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativa);
    }

    /* =================================================================
       Seguridad: autenticación, rol y CSRF
       ================================================================= */

    public function testSinSesionResponde401Json(): void
    {
        $resultado = $this->enviar($this->parametros(), $this->crearJpeg(), []);

        $resultado->assertStatus(401);
        $resultado->assertJSONFragment(['ok' => false, 'error' => 'AUTH_REQUIRED']);
    }

    public function testRolDistintoResponde403Json(): void
    {
        $resultado = $this->enviar($this->parametros(), $this->crearJpeg(), [
            'logged_in' => true,
            'activo'    => true,
            'user_id'   => $this->inspectorId,
            'roles'     => ['CONSULTA'],
        ]);

        $resultado->assertStatus(403);
        $resultado->assertJSONFragment(['ok' => false, 'error' => 'FORBIDDEN']);
    }

    public function testSinTokenCsrfEsRechazado(): void
    {
        $resultado = $this->enviar($this->parametros(), $this->crearJpeg(), $this->sesionInspector(), false);

        $resultado->assertStatus(403);
        $resultado->assertJSONFragment(['ok' => false, 'error' => 'CSRF_INVALID']);
    }

    public function testTokenCsrfVencidoEnPeticionApiResponde403Json(): void
    {
        $resultado = $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept'           => 'application/json',
            'X-CSRF-TOKEN'     => 'token-vencido',
        ])
            ->withSession($this->sesionInspector())
            ->post('/inspector/sincronizar/fotografias', $this->parametros());

        $resultado->assertStatus(403);
        $resultado->assertJSONFragment(['ok' => false, 'error' => 'CSRF_INVALID']);
    }

    /* =================================================================
       Alta correcta y almacenamiento definitivo
       ================================================================= */

    public function testFotografiaSeSincronizaYEscribeArchivos(): void
    {
        $resultado = $this->enviar($this->parametros(), $this->crearJpeg());

        $resultado->assertStatus(200);
        $resultado->assertJSONFragment(['ok' => true]);

        $cuerpo = $this->cuerpo($resultado);

        $this->assertSame('SYNCED', $cuerpo['results'][0]['estado']);

        $registro = $this->primeraFotografia();

        $this->assertNotNull($registro);
        $this->assertSame((string) $this->inspeccionId, (string) $registro->inspeccion_id);
        $this->assertSame('image/jpeg', $registro->mime_type);
        $this->assertSame('jpg', $registro->extension);

        /* Estructura anidada: OBR-XXXXXX / fecha / hora / IMAGENES.
           El nombre de la carpeta viene de fecha_inspeccion + hora_inspeccion
           (11-14-00), nunca del UUID (Fase E.2, §52.8). */
        $this->assertStringStartsWith('OBR-000001/2026-03-15/10-30-00/IMAGENES/', $registro->ruta_relativa);
        $this->assertStringStartsWith('OBR-000001/2026-03-15/10-30-00/THUMBNAILS/', $registro->ruta_thumbnail);
        $this->assertStringNotContainsString($this->uuidInspeccion, $registro->ruta_relativa);
        $this->assertStringNotContainsString($this->uuidInspeccion, $registro->ruta_thumbnail);

        /* El nombre físico lo decide el servidor, no el cliente:
           INS-{id de inspección en 5 dígitos}-{Ymd-His}-{random6}.{ext} */
        $this->assertMatchesRegularExpression(
            '/\AINS-' . str_pad((string) $this->inspeccionId, 5, '0', STR_PAD_LEFT) . '-\d{8}-\d{6}-[0-9a-f]{6}\.jpg\z/',
            $registro->nombre_archivo
        );

        $this->assertFileExists($this->rutaAbsoluta($registro->ruta_relativa));
        $this->assertFileExists($this->rutaAbsoluta($registro->ruta_thumbnail));
    }

    /* =================================================================
       Fase E.2 — carpeta física derivada de fecha + hora
       ================================================================= */

    /**
     * `IMAGENES/` y `THUMBNAILS/` existen dentro de la carpeta de la
     * inspección, y en ningún otro nivel.
     */
    public function testImagenesYThumbnailsSeCreanDentroDeLaCarpetaDeInspeccion(): void
    {
        $this->enviar($this->parametros(), $this->crearJpeg());

        $carpeta = $this->tmp . DIRECTORY_SEPARATOR . 'OBR-000001'
            . DIRECTORY_SEPARATOR . '2026-03-15' . DIRECTORY_SEPARATOR . '10-30-00';

        $this->assertTrue(is_dir($carpeta . DIRECTORY_SEPARATOR . 'IMAGENES'));
        $this->assertTrue(is_dir($carpeta . DIRECTORY_SEPARATOR . 'THUMBNAILS'));

        /* No se reintroduce el esquema legacy de nivel de obra. */
        $obra = $this->tmp . DIRECTORY_SEPARATOR . 'OBR-000001';
        $this->assertFalse(is_dir($obra . DIRECTORY_SEPARATOR . 'IMAGENES'));
        $this->assertFalse(is_dir($obra . DIRECTORY_SEPARATOR . 'THUMBNAILS'));
    }

    public function testInspeccionSinHoraUsaCarpetaSinHora(): void
    {
        $this->conn->table('inspecciones')->where('id', $this->inspeccionId)
            ->update(['hora_inspeccion' => null]);

        $resultado = $this->enviar($this->parametros(), $this->crearJpeg());

        $resultado->assertStatus(200);

        $registro = $this->primeraFotografia();

        $this->assertStringStartsWith('OBR-000001/2026-03-15/SIN-HORA/IMAGENES/', $registro->ruta_relativa);
        $this->assertStringStartsWith('OBR-000001/2026-03-15/SIN-HORA/THUMBNAILS/', $registro->ruta_thumbnail);
        $this->assertFileExists($this->rutaAbsoluta($registro->ruta_relativa));
    }

    public function testInspeccionAMedianocheUsaCarpetaDeMedianoche(): void
    {
        $this->conn->table('inspecciones')->where('id', $this->inspeccionId)
            ->update(['hora_inspeccion' => '00:00:00']);

        $resultado = $this->enviar($this->parametros(), $this->crearJpeg());

        $resultado->assertStatus(200);

        $registro = $this->primeraFotografia();

        $this->assertStringStartsWith('OBR-000001/2026-03-15/00-00-00/IMAGENES/', $registro->ruta_relativa);
        $this->assertStringNotContainsString('SIN-HORA', $registro->ruta_relativa);
    }

    /**
     * Dos inspecciones de la misma obra, fecha y hora reciben carpetas
     * distintas: la base conserva el nombre y la segunda gana el sufijo `-2`.
     */
    public function testDosInspeccionesConMismaFechaYHoraNoCompartenCarpeta(): void
    {
        $this->enviar($this->parametros(['inspeccion_uuid' => $this->uuidInspeccion]), $this->crearJpeg(800, 600, 'a.jpg'));

        $segundaUuid = Uuid::v4();

        $this->conn->table('inspecciones')->insert([
            'uuid'             => $segundaUuid,
            'obra_id'          => $this->obraId,
            'inspector_id'     => $this->inspectorId,
            'fecha_inspeccion' => '2026-03-15',
            'hora_inspeccion'  => '10:30:00',
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);

        $this->enviar($this->parametros(['inspeccion_uuid' => $segundaUuid]), $this->crearJpeg(800, 600, 'b.jpg'));

        $rutas = $this->conn->table('fotografias')->orderBy('id', 'ASC')->get()->getResultArray();

        $this->assertCount(2, $rutas);
        $this->assertStringStartsWith('OBR-000001/2026-03-15/10-30-00/IMAGENES/', $rutas[0]['ruta_relativa']);
        $this->assertStringStartsWith('OBR-000001/2026-03-15/10-30-00-2/IMAGENES/', $rutas[1]['ruta_relativa']);

        /* Ambas carpetas existen en disco y cada una guarda lo suyo. */
        $this->assertFileExists($this->rutaAbsoluta($rutas[0]['ruta_relativa']));
        $this->assertFileExists($this->rutaAbsoluta($rutas[1]['ruta_relativa']));
        $this->assertNotSame(
            dirname($rutas[0]['ruta_relativa']),
            dirname($rutas[1]['ruta_relativa'])
        );
    }

    /**
     * El sufijo lo decide `inspecciones.id ASC`, no el orden en que se
     *_insertaron_ las inspecciones: la de menor id conserva el nombre base
     * aunque se haya creado después.
     */
    public function testElSufijoLoDeterminaElIdDeInspeccion(): void
    {
        $tardiaUuid = Uuid::v4();

        $this->conn->query('DELETE FROM ' . $this->tabla('inspecciones'));

        /* La inspección de menor id se inserta segunda a propósito. */
        $this->conn->table('inspecciones')->insert([
            'id'               => 50,
            'uuid'             => $this->uuidInspeccion,
            'obra_id'          => $this->obraId,
            'inspector_id'     => $this->inspectorId,
            'fecha_inspeccion' => '2026-03-15',
            'hora_inspeccion'  => '10:30:00',
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);

        $this->conn->table('inspecciones')->insert([
            'id'               => 100,
            'uuid'             => $tardiaUuid,
            'obra_id'          => $this->obraId,
            'inspector_id'     => $this->inspectorId,
            'fecha_inspeccion' => '2026-03-15',
            'hora_inspeccion'  => '10:30:00',
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);

        /* Se sincroniza primero la de mayor id: aun así recibe el sufijo. */
        $this->enviar($this->parametros(['inspeccion_uuid' => $tardiaUuid]), $this->crearJpeg(800, 600, 'a.jpg'));
        $this->enviar($this->parametros(['inspeccion_uuid' => $this->uuidInspeccion]), $this->crearJpeg(800, 600, 'b.jpg'));

        $rutas = $this->conn->table('fotografias')->orderBy('id', 'ASC')->get()->getResultArray();

        $this->assertCount(2, $rutas);
        $this->assertStringStartsWith('OBR-000001/2026-03-15/10-30-00-2/IMAGENES/', $rutas[0]['ruta_relativa']);
        $this->assertStringStartsWith('OBR-000001/2026-03-15/10-30-00/IMAGENES/', $rutas[1]['ruta_relativa']);
    }

    public function testMedianocheYSinHoraProducenCarpetasDistintas(): void
    {
        $sinHoraUuid = Uuid::v4();

        $this->conn->table('inspecciones')->insert([
            'uuid'             => $sinHoraUuid,
            'obra_id'          => $this->obraId,
            'inspector_id'     => $this->inspectorId,
            'fecha_inspeccion' => '2026-03-15',
            'hora_inspeccion'  => null,
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);

        $this->enviar($this->parametros(['inspeccion_uuid' => $sinHoraUuid]), $this->crearJpeg(800, 600, 'a.jpg'));

        $this->conn->table('inspecciones')->where('id', $this->inspeccionId)
            ->update(['hora_inspeccion' => '00:00:00']);

        $this->enviar($this->parametros(['inspeccion_uuid' => $this->uuidInspeccion]), $this->crearJpeg(800, 600, 'b.jpg'));

        $rutas = $this->conn->table('fotografias')->orderBy('id', 'ASC')->get()->getResultArray();

        $this->assertCount(2, $rutas);
        $this->assertStringStartsWith('OBR-000001/2026-03-15/SIN-HORA/IMAGENES/', $rutas[0]['ruta_relativa']);
        $this->assertStringStartsWith('OBR-000001/2026-03-15/00-00-00/IMAGENES/', $rutas[1]['ruta_relativa']);
    }

    /**
     * Ninguna ruta almacenada contiene el uuid de la inspección.
     */
    public function testNingunaRutaAlmacenadaContieneElUuidDeInspeccion(): void
    {
        $this->enviar($this->parametros(), $this->crearJpeg());

        $registro = $this->primeraFotografia();

        $this->assertDoesNotMatchRegularExpression(
            '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i',
            $registro->ruta_relativa
        );
        $this->assertDoesNotMatchRegularExpression(
            '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i',
            $registro->ruta_thumbnail
        );

        /* Tampoco existe ninguna carpeta con el nombre del uuid. */
        $fecha = $this->tmp . DIRECTORY_SEPARATOR . 'OBR-000001' . DIRECTORY_SEPARATOR . '2026-03-15';

        $this->assertFalse(is_dir($fecha . DIRECTORY_SEPARATOR . $this->uuidInspeccion));
    }

    public function testThumbnailSeGeneraEnElServidorAcotadoA400Px(): void
    {
        $resultado = $this->enviar($this->parametros(), $this->crearJpeg(1600, 900));

        $resultado->assertStatus(200);

        $registro = $this->primeraFotografia();
        $thumb = $this->rutaAbsoluta($registro->ruta_thumbnail);

        $this->assertFileExists($thumb);

        $info = getimagesize($thumb);

        $this->assertNotFalse($info);
        $this->assertSame('image/jpeg', $info['mime']);
        $this->assertSame(400, max($info[0], $info[1]));
    }

    public function testNombreEnviadoPorElClienteEsIgnorado(): void
    {
        $resultado = $this->enviar(
            $this->parametros(),
            $this->crearJpeg(),
            $this->sesionInspector(),
            true,
            '../../../../evil.php'
        );

        $resultado->assertStatus(200);

        $registro = $this->primeraFotografia();

        $this->assertStringNotContainsString('evil', $registro->nombre_archivo);
        $this->assertStringNotContainsString('..', $registro->ruta_relativa);
        $this->assertStringNotContainsString('..', $registro->ruta_thumbnail);
    }

    public function testCoordenadasYDispositivoSeNormalizan(): void
    {
        $resultado = $this->enviar($this->parametros([
            'fecha_hora_captura' => '2026-03-15T10:30:00.000Z',
            'latitud'            => '-31.4201',
            'longitud'           => '-64.1888',
            'dispositivo'        => '  Samsung A54  ',
        ]), $this->crearJpeg());

        $resultado->assertStatus(200);

        $registro = $this->primeraFotografia();

        $this->assertSame('Samsung A54', $registro->dispositivo);
        $this->assertNotNull($registro->latitud);
        $this->assertNotNull($registro->longitud);
        $this->assertNotNull($registro->fecha_hora_captura);
    }

    public function testCoordenadasFueraDeRangoSeDescartan(): void
    {
        $resultado = $this->enviar($this->parametros([
            'latitud'  => '150',
            'longitud' => 'no-es-un-numero',
        ]), $this->crearJpeg());

        $resultado->assertStatus(200);

        $registro = $this->primeraFotografia();

        $this->assertNull($registro->latitud);
        $this->assertNull($registro->longitud);
    }

    /* =================================================================
       Idempotencia y reparación
       ================================================================= */

    public function testReenvioDelMismoUuidEsIdempotente(): void
    {
        $params = $this->parametros();

        $primero = $this->enviar($params, $this->crearJpeg(800, 600, 'a.jpg'));
        $cuerpo1 = $this->cuerpo($primero);

        $segundo = $this->enviar($params, $this->crearJpeg(800, 600, 'b.jpg'));
        $cuerpo2 = $this->cuerpo($segundo);

        $this->assertSame('SYNCED', $cuerpo1['results'][0]['estado']);
        $this->assertSame('ALREADY_SYNCED', $cuerpo2['results'][0]['estado']);
        $this->assertSame((int) $cuerpo1['results'][0]['id'], (int) $cuerpo2['results'][0]['id']);

        $this->assertSame(
            1,
            (int) $this->conn->table('fotografias')->where('uuid', $params['uuid'])->countAllResults(),
            'No debe duplicarse la fotografía.'
        );
    }

    public function testReenvioReparaArchivoFisicoPerdido(): void
    {
        $params = $this->parametros();

        $this->enviar($params, $this->crearJpeg(800, 600, 'a.jpg'));

        $registro = $this->primeraFotografia();
        $rutaImagen = $this->rutaAbsoluta($registro->ruta_relativa);
        $rutaThumb = $this->rutaAbsoluta($registro->ruta_thumbnail);

        $this->assertFileExists($rutaImagen);

        unlink($rutaImagen);
        unlink($rutaThumb);

        $this->assertFileDoesNotExist($rutaImagen);

        $segundo = $this->enviar($params, $this->crearJpeg(800, 600, 'b.jpg'));
        $cuerpo = $this->cuerpo($segundo);

        $this->assertSame('ALREADY_SYNCED', $cuerpo['results'][0]['estado']);
        $this->assertTrue((bool) $cuerpo['results'][0]['reparado']);
        $this->assertFileExists($rutaImagen);
        $this->assertFileExists($rutaThumb);
    }

    public function testReenvioSinFaltantesNoRepara(): void
    {
        $params = $this->parametros();

        $this->enviar($params, $this->crearJpeg(800, 600, 'a.jpg'));

        $segundo = $this->enviar($params, $this->crearJpeg(800, 600, 'b.jpg'));

        $this->assertFalse((bool) $this->cuerpo($segundo)['results'][0]['reparado']);
    }

    /* =================================================================
       Autorización heredada de la inspección
       ================================================================= */

    public function testInspeccionInexistenteEsRechazada(): void
    {
        $resultado = $this->enviar(
            $this->parametros(['inspeccion_uuid' => Uuid::v4()]),
            $this->crearJpeg()
        );

        $resultado->assertStatus(422);
        $resultado->assertJSONFragment(['ok' => false, 'error' => 'VALIDATION_ERROR']);

        $this->assertSame(0, (int) $this->conn->table('fotografias')->countAllResults());
    }

    public function testInspeccionDeOtroInspectorResponde403(): void
    {
        $this->conn->table('inspecciones')->where('id', $this->inspeccionId)
            ->update(['inspector_id' => $this->otroInspectorId]);

        $resultado = $this->enviar($this->parametros(), $this->crearJpeg());

        $resultado->assertStatus(403);
        $resultado->assertJSONFragment(['ok' => false, 'error' => 'FORBIDDEN']);

        $this->assertSame(0, (int) $this->conn->table('fotografias')->countAllResults());
    }

    public function testRechazadaCuandoLaAutorizacionHistoricaFalla(): void
    {
        $this->revocarAsignacion();

        $resultado = $this->enviar($this->parametros(), $this->crearJpeg());

        $resultado->assertStatus(422);
        $this->assertSame('HISTORICAL_AUTHORIZATION_FAILED', $this->cuerpo($resultado)['error']);

        $this->assertSame(0, (int) $this->conn->table('fotografias')->countAllResults());
    }

    public function testUuidDeFotografiaDeOtraInspeccionEsRechazado(): void
    {
        $params = $this->parametros();
        $this->enviar($params, $this->crearJpeg(800, 600, 'a.jpg'));

        /* Se reenvía el mismo uuid de fotografía contra otra inspección propia. */
        $otraUuid = Uuid::v4();

        $this->conn->table('inspecciones')->insert([
            'uuid'             => $otraUuid,
            'obra_id'          => $this->obraId,
            'inspector_id'     => $this->inspectorId,
            'fecha_inspeccion' => '2026-03-16',
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);

        $resultado = $this->enviar(
            ['uuid' => $params['uuid'], 'inspeccion_uuid' => $otraUuid],
            $this->crearJpeg(800, 600, 'b.jpg')
        );

        $resultado->assertStatus(422);
        $this->assertSame('UUID_EN_CONFLICTO', $this->cuerpo($resultado)['error']);
    }

    /* =================================================================
       Validación del archivo recibido
       ================================================================= */

    public function testUuidInvalidoEsRechazado(): void
    {
        $resultado = $this->enviar($this->parametros(['uuid' => 'no-es-un-uuid']), $this->crearJpeg());

        $resultado->assertStatus(422);
        $resultado->assertJSONFragment(['ok' => false, 'error' => 'VALIDATION_ERROR']);
    }

    public function testUuidObligatorioEsRechazado(): void
    {
        $resultado = $this->enviar(['inspeccion_uuid' => $this->uuidInspeccion], $this->crearJpeg());

        $resultado->assertStatus(422);
        $this->assertArrayHasKey('uuid', $this->cuerpo($resultado)['details']);
    }

    public function testSinArchivoEsRechazado(): void
    {
        $resultado = $this->enviar($this->parametros());

        $resultado->assertStatus(422);
        $this->assertSame('ARCHIVO_INVALIDO', $this->cuerpo($resultado)['error']);

        $this->assertSame(0, (int) $this->conn->table('fotografias')->countAllResults());
    }

    public function testMimeNoPermitidoEsRechazado(): void
    {
        $ruta = $this->crearArchivoConContenido('Esto es texto plano, no una imagen.', 'nota.txt');

        $resultado = $this->enviar($this->parametros(), $ruta);

        $resultado->assertStatus(422);
        $this->assertSame('MIME_NO_PERMITIDO', $this->cuerpo($resultado)['error']);

        $this->assertSame(0, (int) $this->conn->table('fotografias')->countAllResults());
    }

    public function testArchivoCorruptoEsRechazado(): void
    {
        /* JPEG con cabecera válida pero cuerpo vacío. */
        $ruta = $this->crearArchivoConContenido("\xFF\xD8\xFF\xE0" . str_repeat("\x00", 64), 'roto.jpg');

        $resultado = $this->enviar($this->parametros(), $ruta);

        $resultado->assertStatus(422);
        $this->assertContains(
            $this->cuerpo($resultado)['error'],
            ['ARCHIVO_CORRUPTO', 'MIME_NO_PERMITIDO'],
            'Un archivo que no es una imagen válida debe rechazarse.'
        );
    }

    public function testArchivoVacioEsRechazado(): void
    {
        $ruta = $this->crearArchivoConContenido('', 'vacio.jpg');

        $resultado = $this->enviar($this->parametros(), $ruta);

        $resultado->assertStatus(422);
        $this->assertSame('ARCHIVO_INVALIDO', $this->cuerpo($resultado)['error']);
    }

    public function testArchivoDemasiadoGrandeEsRechazado(): void
    {
        $resultado = $this->enviar($this->parametros(), $this->crearArchivoGrande('grande.jpg'));

        $resultado->assertStatus(422);
        $this->assertSame('ARCHIVO_DEMASIADO_GRANDE', $this->cuerpo($resultado)['error']);

        $this->assertSame(0, (int) $this->conn->table('fotografias')->countAllResults());
    }

    public function testArchivoRechazadoNoDejaResiduoEnDisco(): void
    {
        $ruta = $this->crearArchivoConContenido('texto plano', 'basura.txt');

        $this->enviar($this->parametros(), $ruta);

        $entradas = array_diff(scandir($this->tmp), ['.', '..', basename($ruta)]);

        $this->assertSame([], array_values($entradas), 'No debe crearse ninguna carpeta de obra al rechazar el archivo.');
    }

    /* =================================================================
       Trazabilidad
       ================================================================= */

    public function testAltaExitosaQuedaRegistradaComoFotografiaProcesada(): void
    {
        $params = $this->parametros();

        $this->enviar($params, $this->crearJpeg());

        $operacion = $this->conn->table('operaciones_sincronizacion')
            ->where('entidad', 'fotografias')
            ->where('registro_id', $params['uuid'])
            ->get()
            ->getRow();

        $this->assertNotNull($operacion);
        $this->assertSame('PROCESADA', $operacion->estado);
        $this->assertSame((string) $this->inspectorId, (string) $operacion->usuario_id);
    }

    public function testFalloDeAutorizacionQuedaRegistrado(): void
    {
        $this->revocarAsignacion();

        $params = $this->parametros();

        $this->enviar($params, $this->crearJpeg());

        $operacion = $this->conn->table('operaciones_sincronizacion')
            ->where('entidad', 'fotografias')
            ->where('registro_id', $params['uuid'])
            ->get()
            ->getRow();

        $this->assertNotNull($operacion);
        $this->assertSame('ERROR', $operacion->estado);
    }

    /**
     * Una `hora_inspeccion` ilegible se rechaza en lugar de guardarse dentro de
     * una carpeta `SIN-HORA`: esa carpeta pertenece a las inspecciones sin hora
     * y el error debe quedar visible, no oculto detrás de un nombre válido.
     */
    public function testHoraIlegibleNoSeGuardaComoSiFueraSinHora(): void
    {
        $this->conn->table('inspecciones')->where('id', $this->inspeccionId)
            ->update(['hora_inspeccion' => 'no-es-una-hora']);

        $resultado = $this->enviar($this->parametros(), $this->crearJpeg());

        $resultado->assertStatus(422);
        $this->assertSame('STORAGE_ERROR', $this->cuerpo($resultado)['error']);

        $this->assertSame(0, (int) $this->conn->table('fotografias')->countAllResults());

        /* No se crea ninguna carpeta: ni `SIN-HORA` ni ninguna otra. */
        $fecha = $this->tmp . DIRECTORY_SEPARATOR . 'OBR-000001' . DIRECTORY_SEPARATOR . '2026-03-15';
        $this->assertFalse(is_dir($fecha . DIRECTORY_SEPARATOR . 'SIN-HORA'));
        $this->assertSame(
            [],
            is_dir($fecha) ? array_values(array_diff(scandir($fecha) ?: [], ['.', '..'])) : [],
            'Una hora ilegible no debe dejar carpetas de inspección en el almacenamiento.'
        );
    }
}
