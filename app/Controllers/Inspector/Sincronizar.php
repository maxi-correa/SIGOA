<?php

namespace App\Controllers\Inspector;

use App\Controllers\BaseController;
use App\Libraries\Uuid;
use App\Models\InspectoresObrasModel;
use App\Models\InspeccionModel;
use App\Models\FotografiaModel;
use App\Models\ObraModel;
use App\Models\OperacionSincronizacionModel;
use App\Services\FotografiaArchivo;

/**
 * Fase D.3 — Sincronización de inspecciones (servidor).
 *
 * Endpoint de alta confirmada de las inspecciones capturadas offline en el
 * dispositivo (IndexedDB). Contrato definido en docs/SIGOA.md §55:
 *
 *   * `POST /inspector/sincronizar/inspecciones` (grupo inspector; filtros
 *     `auth` + `role:INSPECTOR` + CSRF global);
 *   * cuerpo JSON: `{"inspecciones": [ ... ]}`;
 *   * **idempotente por `uuid`**: si el uuid ya existe responde
 *     `ALREADY_SYNCED` con el `id` del registro existente, sin duplicar;
 *   * la identidad del inspector proviene de la **sesión**
 *     (`session('user_id')`), NUNCA del payload;
 *   * autorización histórica (§52.4): `InspectoresObrasModel::fueVigente()`
 *     sobre la fecha de la inspección. No se evalúa el estado actual de la
 *     obra: la restricción de estado aplica a la creación (F.7 / §52.5);
 *   * procesamiento por ítem (sin transacción global): cada inspección es
 *     atómica y un ítem fallido no rechaza el lote;
 *   * respuestas: `200 {ok, results, resumen}` / `422 {ok:false, error}`;
 *     per-ítem: `SYNCED` / `ALREADY_SYNCED` / `REJECTED` / `ERROR`.
 *
 * Se registra toda operación (éxito o rechazo permanente) en
 * `operaciones_sincronizacion` (§52.16) para trazabilidad.
 *
 * Desde la Fase D.4 el mismo controlador expone la alta de fotografías
 * (`fotografias()`), que hereda la autorización de su inspección y respeta
 * el orden inspecciones → fotografías (§52.13, §56).
 */
class Sincronizar extends BaseController
{
    private const LIMITE_OBSERVACION = 5000;

    /**
     * Alta confirmada de inspecciones por lote (idempotente por uuid).
     */
    public function inspecciones()
    {
        try {
            $cuerpo = $this->request->getJSON(true);
        } catch (\Throwable) {
            return $this->respuestaError(422, 'VALIDATION_ERROR', [
                'inspecciones' => 'El cuerpo de la petición debe ser JSON válido.',
            ]);
        }

        if (! is_array($cuerpo) || ! isset($cuerpo['inspecciones']) || ! is_array($cuerpo['inspecciones'])) {
            return $this->respuestaError(422, 'VALIDATION_ERROR', [
                'inspecciones' => 'El lote debe contener un arreglo "inspecciones".',
            ]);
        }

        $usuarioId = (int) session()->get('user_id');

        $results = [];
        $resumen = [
            'procesadas'       => count($cuerpo['inspecciones']),
            'sincronizadas'    => 0,
            'ya_sincronizadas' => 0,
            'rechazadas'       => 0,
            'errores'          => 0,
        ];

        foreach ($cuerpo['inspecciones'] as $item) {
            $resultado = $this->procesarInspeccion($item, $usuarioId);

            switch ($resultado['estado']) {
                case 'SYNCED':
                    ++$resumen['sincronizadas'];
                    break;

                case 'ALREADY_SYNCED':
                    ++$resumen['ya_sincronizadas'];
                    break;

                case 'REJECTED':
                    ++$resumen['rechazadas'];
                    break;

                case 'ERROR':
                    ++$resumen['errores'];
                    break;
            }

            $results[] = $resultado;
        }

        return $this->respuesta(200, [
            'ok'      => true,
            'results' => $results,
            'resumen' => $resumen,
        ]);
    }

    /* ==================================================================
       Fase D.4 — Alta de fotografías (multipart)
       ================================================================== */

    /**
     * Alta confirmada de una fotografía de inspección (Fase D.4, §56).
     *
     * Contrato: `multipart/form-data` con `uuid`, `inspeccion_uuid` y el
     * archivo en `archivo`. El thumbnail **no** se recibe: se regenera en el
     * servidor a partir del archivo ya optimizado por el cliente (§52.9).
     *
     * La fotografía hereda la autorización de su inspección:
     *
     *  1. la inspección se localiza por su UUID;
     *  2. se comprueba que pertenece al inspector autenticado (`403` si no);
     *  3. se revalida la autorización histórica de D.3 en la fecha de la
     *     inspección (`fueVigente`);
     *  4. nunca se confía en `obra_id`, `inspector_id`, `inspeccion_id`,
     *     nombre de archivo ni extensión enviados por el cliente.
     *
     * Idempotencia por `uuid` de fotografía (`uq_fotografias_uuid`): un
     * reenvío devuelve `ALREADY_SYNCED` sin duplicar fila ni archivos, y
     * repara el archivo físico si la fila existe pero el archivo se perdió.
     */
    public function fotografias()
    {
        $usuarioId = (int) session()->get('user_id');

        $uuidFoto = $this->request->getPost('uuid');
        $uuidInspeccion = $this->request->getPost('inspeccion_uuid');

        /* --- Validación de identidad ---------------------------------- */

        if (! is_string($uuidFoto) || $uuidFoto === '') {
            return $this->respuestaError(422, 'VALIDATION_ERROR', [
                'uuid' => 'El campo uuid es obligatorio.',
            ]);
        }

        if (mb_strlen($uuidFoto) > 36 || ! Uuid::isValid($uuidFoto)) {
            return $this->respuestaError(422, 'VALIDATION_ERROR', [
                'uuid' => 'El uuid de la fotografía no es un UUID válido.',
            ]);
        }

        if (! is_string($uuidInspeccion) || $uuidInspeccion === '') {
            return $this->respuestaError(422, 'VALIDATION_ERROR', [
                'inspeccion_uuid' => 'El campo inspeccion_uuid es obligatorio.',
            ]);
        }

        if (mb_strlen($uuidInspeccion) > 36 || ! Uuid::isValid($uuidInspeccion)) {
            return $this->respuestaError(422, 'VALIDATION_ERROR', [
                'inspeccion_uuid' => 'El uuid de la inspección no es un UUID válido.',
            ]);
        }

        /* --- Autorización heredada de la inspección --------------------- */

        $inspeccion = (new InspeccionModel())->findByUuid($uuidInspeccion);

        if ($inspeccion === null) {
            return $this->respuestaError(422, 'VALIDATION_ERROR', [
                'inspeccion_uuid' => 'La inspección indicada todavía no fue sincronizada en el servidor.',
            ]);
        }

        /* La pertenencia se verifica ANTES de consultar la idempotencia para
           no revelar la existencia de fotografías de otra inspección. */
        if ((int) $inspeccion->inspector_id !== $usuarioId) {
            return $this->respuesta(403, ['ok' => false, 'error' => 'FORBIDDEN']);
        }

        $obraId = (int) $inspeccion->obra_id;

        if (! (new InspectoresObrasModel())->fueVigente($obraId, $usuarioId, (string) $inspeccion->fecha_inspeccion)) {
            $mensaje = 'El inspector no estaba asignado a la obra en la fecha de la inspección.';

            $this->registrarOperacion($usuarioId, $uuidFoto, OperacionSincronizacionModel::ESTADO_ERROR, $mensaje, 'fotografias');

            return $this->respuesta(422, [
                'ok'      => false,
                'error'   => 'HISTORICAL_AUTHORIZATION_FAILED',
                'details' => ['mensaje' => $mensaje],
            ]);
        }

        $fotografias = new FotografiaModel();
        $archivos = new FotografiaArchivo();

        /* --- Idempotencia por uuid de fotografía ------------------------ */

        $existente = $fotografias->findByUuid($uuidFoto);

        if ($existente !== null) {
            if ((int) $existente->inspeccion_id !== (int) $inspeccion->id) {
                return $this->respuesta(422, [
                    'ok'      => false,
                    'error'   => 'UUID_EN_CONFLICTO',
                    'details' => ['mensaje' => 'Ese uuid de fotografía ya pertenece a otra inspección.'],
                ]);
            }

            return $this->respuesta(200, [
                'ok'      => true,
                'results' => [$this->reenvioIdempotente($existente, $uuidFoto, $archivos)],
                'resumen' => [
                    'procesadas'       => 1,
                    'sincronizadas'    => 0,
                    'ya_sincronizadas' => 1,
                    'rechazadas'       => 0,
                    'errores'          => 0,
                ],
            ]);
        }

        /* --- Validación real del archivo -------------------------------- */

        $resultadoArchivo = $this->validarArchivoRecibido($archivos);

        if (! $resultadoArchivo['ok']) {
            return $this->respuesta(422, [
                'ok'      => false,
                'error'   => $resultadoArchivo['error'],
                'details' => ['mensaje' => $resultadoArchivo['mensaje']],
            ]);
        }

        $obra = (new ObraModel())->find($obraId);

        if ($obra === null || ! isset($obra->codigo)) {
            return $this->respuesta(422, [
                'ok'      => false,
                'error'   => 'OBRA_NOT_FOUND',
                'details' => ['mensaje' => 'La obra de la inspección no existe.'],
            ]);
        }

        /* --- Escritura del archivo y del thumbnail ---------------------- */
        /* Orden: validar (hecho) → carpetas → imagen → thumbnail → base de
           datos. Si la base de datos falla, se borran solo los archivos
           recién creados por esta operación. */

        $escritura = $archivos->escribirImagen(
            $resultadoArchivo['ruta_origen'],
            (string) $obra->codigo,
            (string) $inspeccion->fecha_inspeccion,
            (string) $inspeccion->uuid,
            (int) $inspeccion->id,
            $resultadoArchivo['datos']
        );

        if (! $escritura['ok']) {
            return $this->respuestaErrorOperacion($usuarioId, $uuidFoto, $escritura['error'], $escritura['mensaje']);
        }

        $thumbnail = $archivos->escribirThumbnail(
            $escritura['ruta_absoluta'],
            (string) $obra->codigo,
            (string) $inspeccion->fecha_inspeccion,
            (string) $inspeccion->uuid,
            (int) $inspeccion->id
        );

        if (! $thumbnail['ok']) {
            $archivos->eliminarRelativo($escritura['ruta_relativa']);

            return $this->respuestaErrorOperacion($usuarioId, $uuidFoto, $thumbnail['error'], $thumbnail['mensaje']);
        }

        /* --- Inserción en base de datos ---------------------------------- */

        $datos = $resultadoArchivo['datos'];
        $ahora = date('Y-m-d H:i:s');

        try {
            $id = $fotografias->insert([
                'uuid'              => $uuidFoto,
                'inspeccion_id'     => (int) $inspeccion->id,
                'nombre_archivo'    => $escritura['nombre_archivo'],
                'ruta_relativa'     => $escritura['ruta_relativa'],
                'ruta_thumbnail'    => $thumbnail['ruta_thumbnail'],
                'extension'         => $datos['extension'],
                'mime_type'         => $datos['mime'],
                'tamano_bytes'      => $datos['tamano'],
                'ancho'             => $datos['ancho'],
                'alto'              => $datos['alto'],
                'fecha_hora_captura' => $this->normalizarFechaHora($this->request->getPost('fecha_hora_captura')),
                'fecha_hora_carga'  => $ahora,
                'latitud'           => $this->normalizarCoordenada($this->request->getPost('latitud'), -90, 90),
                'longitud'          => $this->normalizarCoordenada($this->request->getPost('longitud'), -180, 180),
                'dispositivo'       => $this->normalizarDispositivo($this->request->getPost('dispositivo')),
                'anulada'           => false,
                'created_at'        => $ahora,
                'updated_at'        => $ahora,
            ], true);
        } catch (\Throwable $e) {
            log_message('error', 'SIGOA sync: no se pudo insertar la fotografía uuid=' . $uuidFoto . ' — ' . $e->getMessage());

            $archivos->eliminarRelativo($escritura['ruta_relativa']);
            $archivos->eliminarRelativo($thumbnail['ruta_thumbnail']);

            return $this->respuestaErrorOperacion($usuarioId, $uuidFoto, 'SERVER_ERROR', 'No fue posible registrar la fotografía en el servidor. Reintente más tarde.');
        }

        if ($id === false || $id === null) {
            $archivos->eliminarRelativo($escritura['ruta_relativa']);
            $archivos->eliminarRelativo($thumbnail['ruta_thumbnail']);

            return $this->respuestaErrorOperacion($usuarioId, $uuidFoto, 'SERVER_ERROR', 'No fue posible registrar la fotografía en el servidor. Reintente más tarde.');
        }

        $this->registrarOperacion($usuarioId, $uuidFoto, OperacionSincronizacionModel::ESTADO_PROCESADA, null, 'fotografias');

        return $this->respuesta(200, [
            'ok'      => true,
            'results' => [[
                'uuid'           => $uuidFoto,
                'estado'         => 'SYNCED',
                'id'             => (int) $id,
                'inspeccion_id'  => (int) $inspeccion->id,
                'nombre_archivo' => $escritura['nombre_archivo'],
                'ruta_relativa'  => $escritura['ruta_relativa'],
                'ruta_thumbnail' => $thumbnail['ruta_thumbnail'],
            ]],
            'resumen' => [
                'procesadas'       => 1,
                'sincronizadas'    => 1,
                'ya_sincronizadas' => 0,
                'rechazadas'       => 0,
                'errores'          => 0,
            ],
        ]);
    }

    /**
     * Resultado de un reenvío idempotente de una fotografía ya existente.
     *
     * Si la fila existe pero el archivo físico o el thumbnail se perdieron
     * (por ejemplo, por una interrupción), se reparan con el contenido
     * reenviado por el dispositivo —que conserva los blobs hasta recibir la
     * confirmación— en lugar de ocultar la inconsistencia. Solo se
     * sobrescriben los archivos de esa misma fila, nunca los de otra
     * fotografía.
     */
    private function reenvioIdempotente(object $existente, string $uuidFoto, FotografiaArchivo $archivos): array
    {
        $resultado = [
            'uuid'           => $uuidFoto,
            'estado'         => 'ALREADY_SYNCED',
            'id'             => (int) $existente->id,
            'inspeccion_id'  => (int) $existente->inspeccion_id,
            'nombre_archivo' => (string) $existente->nombre_archivo,
            'ruta_relativa'  => (string) $existente->ruta_relativa,
            'ruta_thumbnail' => (string) $existente->ruta_thumbnail,
            'reparado'       => false,
        ];

        if ($archivos->existeRelativo($resultado['ruta_relativa'])
            && $archivos->existeRelativo($resultado['ruta_thumbnail'])
        ) {
            return $resultado;
        }

        $resultadoArchivo = $this->validarArchivoRecibido($archivos);

        if (! $resultadoArchivo['ok']) {
            /* No se puede reparar: se informa sin borrar la fila existente. */
            return $resultado;
        }

        $reparado = false;

        if (! $archivos->existeRelativo($resultado['ruta_relativa'])) {
            $reparado = $archivos->repararArchivo(
                $resultadoArchivo['ruta_origen'],
                $resultado['ruta_relativa']
            ) || $reparado;
        }

        if (! $archivos->existeRelativo($resultado['ruta_thumbnail'])) {
            $reparado = $archivos->repararThumbnail(
                $resultadoArchivo['ruta_origen'],
                $resultado['ruta_thumbnail']
            ) || $reparado;
        }

        $resultado['reparado'] = $reparado;

        return $resultado;
    }

    /**
     * Valida el archivo `archivo` recibido en multipart.
     *
     * @return array{ok: bool, error?: string, mensaje?: string,
     *               ruta_origen?: string, datos?: array}
     */
    private function validarArchivoRecibido(FotografiaArchivo $archivos): array
    {
        $archivo = $this->request->getFile('archivo');

        if ($archivo === null) {
            return ['ok' => false, 'error' => 'ARCHIVO_INVALIDO', 'mensaje' => 'No se recibió el archivo de la fotografía.'];
        }

        $codigoError = (int) $archivo->getError();

        if ($codigoError !== UPLOAD_ERR_OK) {
            return [
                'ok'      => false,
                'error'   => 'ARCHIVO_INVALIDO',
                'mensaje' => $this->mensajeErrorSubida($codigoError),
            ];
        }

        $ruta = $archivo->getPathname();

        $datos = $archivos->inspeccionar($ruta, $archivo->getSize());

        if (! $datos['ok']) {
            return ['ok' => false, 'error' => $datos['error'], 'mensaje' => $datos['mensaje']];
        }

        return ['ok' => true, 'ruta_origen' => $ruta, 'datos' => $datos];
    }

    /**
     * Registra el fallo de una operación y devuelve la respuesta 422
     * correspondiente.
     *
     * Se usa para los rechazos permanentes de una fotografía: la
     * trazabilidad queda escrita antes de responder, y la entidad local
     * podrá consultarla para ofrecer el reintento manual.
     */
    private function respuestaErrorOperacion(int $usuarioId, string $uuid, string $error, string $mensaje)
    {
        $this->registrarOperacion($usuarioId, $uuid, OperacionSincronizacionModel::ESTADO_ERROR, $mensaje, 'fotografias');

        return $this->respuesta(422, [
            'ok'      => false,
            'error'   => $error,
            'details' => ['mensaje' => $mensaje],
        ]);
    }

    /**
     * Procesa un ítem del lote de forma atómica.
     *
     * @return array{uuid:string|null, estado:string, id?:int, error?:string, mensaje?:string}
     */
    private function procesarInspeccion(mixed $item, int $usuarioId): array
    {
        if (! is_array($item)) {
            return $this->rechazo(null, 'VALIDATION_ERROR', 'Cada ítem del lote debe ser un objeto JSON.');
        }

        $uuid = $item['uuid'] ?? null;

        if (! is_string($uuid) || $uuid === '') {
            return $this->rechazo($uuid, 'VALIDATION_ERROR', 'El campo uuid es obligatorio.');
        }

        if (mb_strlen($uuid) > 36 || ! Uuid::isValid($uuid)) {
            return $this->rechazo($uuid, 'INVALID_UUID', 'El uuid no es un UUID válido.');
        }

        /* Idempotencia: un uuid ya presente no se duplica (§52.15). */
        $existente = (new InspeccionModel())->findByUuid($uuid);

        if ($existente !== null) {
            return [
                'uuid'   => $uuid,
                'estado' => 'ALREADY_SYNCED',
                'id'     => (int) $existente->id,
            ];
        }

        $obraId = $item['obra_id'] ?? null;

        if (! is_numeric($obraId) || (int) $obraId <= 0) {
            return $this->rechazo($uuid, 'VALIDATION_ERROR', 'El campo obra_id es obligatorio y debe ser un entero positivo.');
        }

        $obraId = (int) $obraId;

        if ((new ObraModel())->find($obraId) === null) {
            return $this->rechazo($uuid, 'OBRA_NOT_FOUND', 'La obra indicada no existe.');
        }

        $fecha = $item['fecha_inspeccion'] ?? null;

        if (! is_string($fecha) || ! $this->esFechaValida($fecha)) {
            return $this->rechazo($uuid, 'VALIDATION_ERROR', 'El campo fecha_inspeccion debe ser una fecha válida (AAAA-MM-DD).');
        }

        $hora = $item['hora_inspeccion'] ?? null;

        if ($hora !== null && $hora !== '') {
            if (! is_string($hora) || ! $this->esHoraValida($hora)) {
                return $this->rechazo($uuid, 'VALIDATION_ERROR', 'El campo hora_inspeccion debe ser una hora válida (HH:MM:SS).');
            }
        } else {
            $hora = null;
        }

        $observacion = $item['observacion'] ?? null;

        if ($observacion !== null && ! is_string($observacion)) {
            return $this->rechazo($uuid, 'VALIDATION_ERROR', 'El campo observacion debe ser un texto.');
        }

        if ($observacion !== null && mb_strlen($observacion) > self::LIMITE_OBSERVACION) {
            return $this->rechazo($uuid, 'VALIDATION_ERROR', 'El campo observacion no puede superar los ' . self::LIMITE_OBSERVACION . ' caracteres.');
        }

        /* Autorización histórica del inspector (§52.4). */
        if (! (new InspectoresObrasModel())->fueVigente($obraId, $usuarioId, $fecha)) {
            $mensaje = 'El inspector no estaba asignado a la obra en la fecha de la inspección.';
            $this->registrarOperacion($usuarioId, $uuid, OperacionSincronizacionModel::ESTADO_ERROR, $mensaje);

            return $this->rechazo($uuid, 'HISTORICAL_AUTHORIZATION_FAILED', $mensaje);
        }

        try {
            $id = (new InspeccionModel())->insert([
                'uuid'             => $uuid,
                'obra_id'          => $obraId,
                'inspector_id'     => $usuarioId,
                'fecha_inspeccion' => $fecha,
                'hora_inspeccion'  => $hora,
                'observacion'      => ($observacion === null || $observacion === '') ? null : $observacion,
                'created_at'       => date('Y-m-d H:i:s'),
                'updated_at'       => date('Y-m-d H:i:s'),
            ], true);
        } catch (\Throwable $e) {
            log_message('error', 'SIGOA sync: no se pudo insertar la inspección uuid=' . $uuid . ' — ' . $e->getMessage());
            $this->registrarOperacion($usuarioId, $uuid, OperacionSincronizacionModel::ESTADO_ERROR, 'Excepción interna al insertar.');

            return $this->resultadoError($uuid, 'SERVER_ERROR', 'No fue posible registrar la inspección en el servidor. Reintente más tarde.');
        }

        if ($id === false) {
            log_message('error', 'SIGOA sync: el modelo rechazó la inspección uuid=' . $uuid);
            $this->registrarOperacion($usuarioId, $uuid, OperacionSincronizacionModel::ESTADO_ERROR, 'Validación del modelo rechazó la inspección.');

            return $this->resultadoError($uuid, 'SERVER_ERROR', 'No fue posible registrar la inspección en el servidor. Reintente más tarde.');
        }

        $this->registrarOperacion($usuarioId, $uuid, OperacionSincronizacionModel::ESTADO_PROCESADA);

        return [
            'uuid'   => $uuid,
            'estado' => 'SYNCED',
            'id'     => (int) $id,
        ];
    }

    /**
     * Resultado `REJECTED` (rechazo permanente/validación) por ítem.
     */
    private function rechazo(?string $uuid, string $error, string $mensaje): array
    {
        return [
            'uuid'    => $uuid,
            'estado'  => 'REJECTED',
            'error'   => $error,
            'mensaje' => $mensaje,
        ];
    }

    /**
     * Resultado `ERROR` (falla no anticipada del servidor) por ítem.
     */
    private function resultadoError(string $uuid, string $error, string $mensaje): array
    {
        return [
            'uuid'    => $uuid,
            'estado'  => 'ERROR',
            'error'   => $error,
            'mensaje' => $mensaje,
        ];
    }

    /**
     * Registra la operación de sincronización en `operaciones_sincronizacion`.
     *
     * La escritura es mejor esfuerzo para la trazabilidad: una falla aquí no
     * debe impedir que la inspección quede sincronizada.
     *
     * @param string $entidad `inspecciones` (D.3) o `fotografias` (D.4).
     */
    private function registrarOperacion(
        int $usuarioId,
        string $uuid,
        string $estado,
        ?string $error = null,
        string $entidad = 'inspecciones'
    ): void {
        try {
            (new OperacionSincronizacionModel())->registrar(
                $usuarioId,
                'alta',
                $entidad,
                $uuid,
                $estado,
                $error
            );
        } catch (\Throwable $e) {
            log_message('error', 'SIGOA sync: no se pudo registrar la operación uuid=' . $uuid . ' — ' . $e->getMessage());
        }
    }

    /**
     * Mensaje legible para un código de error de subida de PHP.
     */
    private function mensajeErrorSubida(int $codigo): string
    {
        return match ($codigo) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'La fotografía supera el tamaño máximo permitido.',
            UPLOAD_ERR_PARTIAL                        => 'La fotografía se subió de forma incompleta. Reintente.',
            UPLOAD_ERR_NO_FILE                        => 'No se seleccionó ninguna fotografía.',
            UPLOAD_ERR_NO_TMP_DIR                     => 'El servidor no tiene un directorio temporal disponible.',
            UPLOAD_ERR_CANT_WRITE                     => 'El servidor no pudo escribir el archivo recibido.',
            UPLOAD_ERR_EXTENSION                      => 'Una extensión de PHP bloqueó la subida.',
            default                                   => 'No fue posible recibir la fotografía. Reintente.',
        };
    }

    /**
     * Normaliza la fecha/hora de captura informada por el dispositivo.
     *
     * El dispositivo puede enviar ISO 8601 (lo que produce `Date` en el
     * navegador) o el formato `YYYY-MM-DD HH:MM:SS` de MySQL. Se acepta
     * cualquiera de los dos y se normaliza al formato de la columna; un valor
     * ilegible se descarta en lugar de Stored.
     */
    private function normalizarFechaHora(mixed $valor): ?string
    {
        if (! is_string($valor) || trim($valor) === '') {
            return null;
        }

        $valor = trim($valor);

        if (preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/', $valor) === 1) {
            return $valor;
        }

        try {
            $fecha = new \DateTimeImmutable($valor);
        } catch (\Throwable) {
            return null;
        }

        return $fecha->format('Y-m-d H:i:s');
    }

    /**
     * Normaliza una coordenada opcional dentro de su rango.
     */
    private function normalizarCoordenada(mixed $valor, float $minimo, float $maximo): ?float
    {
        if ($valor === null || $valor === '' || ! is_numeric($valor)) {
            return null;
        }

        $numero = (float) $valor;

        if ($numero < $minimo || $numero > $maximo) {
            return null;
        }

        return $numero;
    }

    /**
     * Normaliza el nombre del dispositivo informado por el cliente.
     */
    private function normalizarDispositivo(mixed $valor): ?string
    {
        if (! is_string($valor)) {
            return null;
        }

        $valor = trim($valor);

        if ($valor === '') {
            return null;
        }

        return mb_substr($valor, 0, 255);
    }

    private function esFechaValida(string $valor): bool
    {
        $formato = 'Y-m-d';
        $fecha   = \DateTime::createFromFormat('!' . $formato, $valor);

        if ($fecha === false) {
            return false;
        }

        $errores = \DateTime::getLastErrors();

        $conErrores = $errores !== false
            && ($errores['warning_count'] > 0 || $errores['error_count'] > 0);

        return ! $conErrores && $fecha->format($formato) === $valor;
    }

    private function esHoraValida(string $valor): bool
    {
        return preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d\z/', $valor) === 1;
    }

    private function respuesta(int $status, array $datos)
    {
        return $this->response
            ->setStatusCode($status)
            ->setJSON($datos);
    }

    private function respuestaError(int $status, string $error, array $details = [])
    {
        $datos = ['ok' => false, 'error' => $error];

        if ($details !== []) {
            $datos['details'] = $details;
        }

        return $this->respuesta($status, $datos);
    }
}