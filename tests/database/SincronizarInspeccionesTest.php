<?php

use App\Libraries\Uuid;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;

/**
 * Sincronización servidor de inspecciones (Fase D.3, docs/SIGOA.md §55).
 *
 * Endpoint `POST /inspector/sincronizar/inspecciones`: alta confirmada por
 * uuid (idempotente) con autorización histórica del inspector (§52.4),
 * identidad tomada de la sesión (nunca del payload), CSRF global y filtros
 * auth + role:INSPECTOR.
 *
 * Usa la conexión `tests` (SQLite en memoria compartida con el grupo). Las
 * declaraciones usan `CREATE TABLE IF NOT EXISTS` respetando los esquemas
 * canónicos que fijan los primeros tests del grupo (ObFase C / D.2).
 *
 * @internal
 */
final class SincronizarInspeccionesTest extends CIUnitTestCase
{
    use FeatureTestTrait;

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
            'codigo'         => 'OBR-D3',
            'nombre'         => 'OBRA SINCRONIZACIÓN D3',
            'estado_obra_id' => 1,
        ]);
        $this->obraId = (int) $this->conn->insertID();

        $this->conn->table('usuarios')->insert([
            'usuario'       => 'PRUEBA_INSPECTOR_D3',
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
        foreach (['inspectores_obras', 'operaciones_sincronizacion', 'inspecciones', 'obras', 'usuarios'] as $tabla) {
            $this->conn->query('DELETE FROM ' . $this->tabla($tabla));
        }
    }

    private function sesionInspector(): array
    {
        return [
            'logged_in' => true,
            'activo'    => true,
            'user_id'   => $this->inspectorId,
            'username'  => 'PRUEBA_INSPECTOR_D3',
            'user_name' => 'PRUEBA_INSPECTOR_D3',
            'roles'     => ['INSPECTOR'],
        ];
    }

    private function asignar(string $fechaInicio, ?string $fechaFin, int $obraId = null, int $usuarioId = null): void
    {
        $this->conn->table('inspectores_obras')->insert([
            'obra_id'      => $obraId ?? $this->obraId,
            'usuario_id'   => $usuarioId ?? $this->inspectorId,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin'    => $fechaFin,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    private function item(string $uuid, ?int $obraId = null, string $fecha = '2026-03-15', ?string $hora = '10:30:00', ?string $observacion = 'Inspección de prueba'): array
    {
        $item = [
            'uuid'             => $uuid,
            'obra_id'          => $obraId ?? $this->obraId,
            'fecha_inspeccion' => $fecha,
            'hora_inspeccion'  => $hora,
            'observacion'      => $observacion,
        ];

        if ($observacion === null) {
            $item['observacion'] = null;
        }

        return $item;
    }

    private function postLote(array $items, ?array $sesion = null, bool $conCsrf = true): TestResponse
    {
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

        return $this->withHeaders($headers)
            ->withBodyFormat('json')
            ->withSession($sesion)
            ->post('/inspector/sincronizar/inspecciones', ['inspecciones' => $items]);
    }

    private function primeraInspeccion(int $obraId): ?object
    {
        return $this->conn->table('inspecciones')->where('obra_id', $obraId)->orderBy('id', 'ASC')->get()->getRow();
    }

    /* =================================================================
       Seguridad: autenticación, rol y CSRF
       ================================================================= */

    public function testSinSesionResponde401Json(): void
    {
        $resultado = $this->postLote([$this->item(Uuid::v4())], []);

        $resultado->assertStatus(401);
        $resultado->assertJSONFragment(['ok' => false, 'error' => 'AUTH_REQUIRED']);
    }

    public function testRolDistintoResponde403Json(): void
    {
        $resultado = $this->postLote(
            [$this->item(Uuid::v4())],
            [
                'logged_in' => true,
                'activo'    => true,
                'user_id'   => $this->inspectorId,
                'roles'     => ['CONSULTA'],
            ]
        );

        $resultado->assertStatus(403);
        $resultado->assertJSONFragment(['ok' => false, 'error' => 'FORBIDDEN']);
    }

    public function testSinTokenCsrfEsRechazado(): void
    {
        $this->expectException(CodeIgniter\Security\Exceptions\SecurityException::class);

        $this->postLote([$this->item(Uuid::v4())], $this->sesionInspector(), false);
    }

    /* =================================================================
       Alta correcta e idempotencia
       ================================================================= */

    public function testInspectorAsignadoEnLaFechaSincroniza(): void
    {
        $this->asignar('2026-03-01', null);

        $uuid = Uuid::v4();

        $resultado = $this->postLote([$this->item($uuid)]);

        $resultado->assertStatus(200);
        $resultado->assertJSONFragment(['ok' => true]);

        $registro = $this->primeraInspeccion($this->obraId);

        $this->assertNotNull($registro, 'La inspección debería haberse creado en el servidor.');
        $this->assertSame($uuid, $registro->uuid);
        $this->assertSame((string) $this->inspectorId, (string) $registro->inspector_id);
        $this->assertSame('2026-03-15', $registro->fecha_inspeccion);

        $body = json_decode($resultado->getJSON(), true);
        $this->assertSame('SYNCED', $body['results'][0]['estado']);
        $this->assertSame((int) $registro->id, (int) $body['results'][0]['id']);
    }

    public function testInspectorIdDelPayloadEsIgnorado(): void
    {
        $this->asignar('2026-03-01', null);

        $item = $this->item(Uuid::v4());
        $item['inspector_id'] = 999999;

        $this->postLote([$item]);

        $registro = $this->primeraInspeccion($this->obraId);

        $this->assertSame((string) $this->inspectorId, (string) $registro->inspector_id, 'La identidad debe salir de la sesión, no del payload.');
    }

    public function testReenvioDelMismoUuidEsIdempotente(): void
    {
        $this->asignar('2026-03-01', null);

        $uuid = Uuid::v4();

        // El singleton de Response hace que TestResponse se sobrescriba con la
        // siguiente petición: se captura el body de cada respuesta de inmediato.
        $primero = $this->postLote([$this->item($uuid)]);
        $body1   = json_decode($primero->getJSON(), true);

        $segundo = $this->postLote([$this->item($uuid)]);
        $body2   = json_decode($segundo->getJSON(), true);

        $this->assertSame('SYNCED', $body1['results'][0]['estado']);
        $this->assertSame('ALREADY_SYNCED', $body2['results'][0]['estado']);
        $this->assertSame((int) $body1['results'][0]['id'], (int) $body2['results'][0]['id']);

        $this->assertSame(
            1,
            (int) $this->conn->table('inspecciones')->where('uuid', $uuid)->countAllResults(),
            'No debe duplicarse la inspección.'
        );
    }

    /* =================================================================
       Autorización histórica (§52.4)
       ================================================================= */

    public function testRechazadaCuandoLaAsignacionComienzaDespuesDeLaFecha(): void
    {
        $this->asignar('2026-04-01', null);

        $resultado = $this->postLote([$this->item(Uuid::v4(), null, '2026-03-15')]);

        $resultado->assertStatus(200);
        $body = json_decode($resultado->getJSON(), true);

        $this->assertSame('REJECTED', $body['results'][0]['estado']);
        $this->assertSame('HISTORICAL_AUTHORIZATION_FAILED', $body['results'][0]['error']);
        $this->assertSame(0, (int) $this->conn->table('inspecciones')->countAllResults());
    }

    public function testRechazadaCuandoLaAsignacionFinalizoAntesDeLaFecha(): void
    {
        $this->asignar('2026-03-01', '2026-03-10');

        $resultado = $this->postLote([$this->item(Uuid::v4(), null, '2026-03-15')]);

        $body = json_decode($resultado->getJSON(), true);

        $this->assertSame('REJECTED', $body['results'][0]['estado']);
        $this->assertSame('HISTORICAL_AUTHORIZATION_FAILED', $body['results'][0]['error']);
    }

    public function testSinRelacionHistoricaEsRechazada(): void
    {
        $resultado = $this->postLote([$this->item(Uuid::v4(), null, '2026-03-15')]);

        $body = json_decode($resultado->getJSON(), true);

        $this->assertSame('REJECTED', $body['results'][0]['estado']);
        $this->assertSame('HISTORICAL_AUTHORIZATION_FAILED', $body['results'][0]['error']);
    }

    public function testReasignacionReconocePeriodoCerradoDelInspectorAnterior(): void
    {
        $this->asignar('2026-01-01', '2026-03-31');
        $this->asignar('2026-04-01', null, $this->obraId, $this->inspectorId);

        $resultado = $this->postLote([$this->item(Uuid::v4(), null, '2026-03-31')]);

        $resultado->assertStatus(200);

        $body = json_decode($resultado->getJSON(), true);

        $this->assertSame('SYNCED', $body['results'][0]['estado']);
    }

    public function testNoRechazaPorEstadoActualDeLaObra(): void
    {
        $this->asignar('2026-03-01', null);

        $this->conn->table('obras')->where('id', $this->obraId)->update(['estado_obra_id' => 99]);

        $resultado = $this->postLote([$this->item(Uuid::v4())]);

        $resultado->assertStatus(200);

        $body = json_decode($resultado->getJSON(), true);

        $this->assertSame('SYNCED', $body['results'][0]['estado'], 'El estado actual de la obra no condiciona la sincronización (F.7/§52.5).');
    }

    /* =================================================================
       Validación por ítem
       ================================================================= */

    public function testObraInexistenteEsRechazada(): void
    {
        $this->asignar('2026-03-01', null);

        $resultado = $this->postLote([$this->item(Uuid::v4(), 999999999)]);

        $body = json_decode($resultado->getJSON(), true);

        $this->assertSame('REJECTED', $body['results'][0]['estado']);
        $this->assertSame('OBRA_NOT_FOUND', $body['results'][0]['error']);
    }

    public function testUuidInvalidoEsRechazado(): void
    {
        $this->asignar('2026-03-01', null);

        $resultado = $this->postLote([$this->item('esto-no-es-un-uuid')]);

        $body = json_decode($resultado->getJSON(), true);

        $this->assertSame('REJECTED', $body['results'][0]['estado']);
        $this->assertSame('INVALID_UUID', $body['results'][0]['error']);
    }

    public function testUuidObligatorio(): void
    {
        $this->asignar('2026-03-01', null);

        $item = $this->item(Uuid::v4());
        unset($item['uuid']);

        $resultado = $this->postLote([$item]);

        $body = json_decode($resultado->getJSON(), true);

        $this->assertSame('REJECTED', $body['results'][0]['estado']);
        $this->assertSame('VALIDATION_ERROR', $body['results'][0]['error']);
    }

    public function testFechaInvalidaEsRechazada(): void
    {
        $this->asignar('2026-03-01', null);

        $resultado = $this->postLote([$this->item(Uuid::v4(), null, '15/03/2026')]);

        $body = json_decode($resultado->getJSON(), true);

        $this->assertSame('REJECTED', $body['results'][0]['estado']);
        $this->assertSame('VALIDATION_ERROR', $body['results'][0]['error']);
    }

    public function testFechaImposibleEsRechazada(): void
    {
        $this->asignar('2026-03-01', null);

        $resultado = $this->postLote([$this->item(Uuid::v4(), null, '2026-02-31')]);

        $body = json_decode($resultado->getJSON(), true);

        $this->assertSame('REJECTED', $body['results'][0]['estado']);
        $this->assertSame('VALIDATION_ERROR', $body['results'][0]['error']);
    }

    public function testHoraInvalidaEsRechazada(): void
    {
        $this->asignar('2026-03-01', null);

        $resultado = $this->postLote([$this->item(Uuid::v4(), null, '2026-03-15', '25:99:00')]);

        $body = json_decode($resultado->getJSON(), true);

        $this->assertSame('REJECTED', $body['results'][0]['estado']);
        $this->assertSame('VALIDATION_ERROR', $body['results'][0]['error']);
    }

    public function testObservacionExcesivaEsRechazada(): void
    {
        $this->asignar('2026-03-01', null);

        $muyLarga = str_repeat('x', 5001);

        $resultado = $this->postLote([$this->item(Uuid::v4(), null, '2026-03-15', '10:30:00', $muyLarga)]);

        $body = json_decode($resultado->getJSON(), true);

        $this->assertSame('REJECTED', $body['results'][0]['estado']);
        $this->assertSame('VALIDATION_ERROR', $body['results'][0]['error']);
    }

    public function testHoraYObervacionOpcionales(): void
    {
        $this->asignar('2026-03-01', null);

        $resultado = $this->postLote([$this->item(Uuid::v4(), null, '2026-03-15', null, null)]);

        $resultado->assertStatus(200);

        $registro = $this->primeraInspeccion($this->obraId);

        $this->assertNull($registro->hora_inspeccion);
        $this->assertNull($registro->observacion);
    }

    /* =================================================================
       Lote
       ================================================================= */

    public function testErrorDeLoteGlobalDevuelve422(): void
    {
        $token = csrf_hash();

        $resultado = $this->withSession($this->sesionInspector())
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'X-CSRF-TOKEN'     => $token,
            ])
            ->withBodyFormat('json')
            ->post('/inspector/sincronizar/inspecciones', ['datos' => []]);

        $resultado->assertStatus(422);
        $resultado->assertJSONFragment(['ok' => false, 'error' => 'VALIDATION_ERROR']);
    }

    public function testLoteVacioDevuelveOkConResumenEnCeros(): void
    {
        $resultado = $this->postLote([]);

        $resultado->assertStatus(200);
        $resultado->assertJSONFragment(['ok' => true]);

        $body = json_decode($resultado->getJSON(), true);

        $this->assertSame([], $body['results']);
        $this->assertSame(0, $body['resumen']['procesadas']);
    }

    public function testLoteMixtoProcesaCadaItem(): void
    {
        $this->asignar('2026-03-01', null);

        $uuidValido  = Uuid::v4();
        $uuidRechazo = Uuid::v4();

        $resultado = $this->postLote([
            $this->item($uuidValido, null, '2026-03-15'),
            $this->item($uuidRechazo, null, '2026-01-15'),
        ]);

        $resultado->assertStatus(200);

        $body = json_decode($resultado->getJSON(), true);

        $this->assertCount(2, $body['results']);
        $this->assertSame(1, $body['resumen']['sincronizadas']);
        $this->assertSame(1, $body['resumen']['rechazadas']);

        $estados = [];
        foreach ($body['results'] as $r) {
            $estados[$r['uuid']] = $r['estado'];
        }

        $this->assertSame('SYNCED', $estados[$uuidValido]);
        $this->assertSame('REJECTED', $estados[$uuidRechazo]);
    }

    /* =================================================================
       Registro en operaciones_sincronizacion
       ================================================================= */

    public function testAltaExitosaQuedaRegistradaComoProcesada(): void
    {
        $this->asignar('2026-03-01', null);

        $uuid = Uuid::v4();

        $this->postLote([$this->item($uuid)]);

        $operacion = $this->conn->table('operaciones_sincronizacion')
            ->where('entidad', 'inspecciones')
            ->where('registro_id', $uuid)
            ->get()
            ->getRow();

        $this->assertNotNull($operacion);
        $this->assertSame('PROCESADA', $operacion->estado);
        $this->assertSame((string) $this->inspectorId, (string) $operacion->usuario_id);
    }
}