<?php

namespace App\Controllers\Inspector;

use App\Controllers\BaseController;
use App\Libraries\Uuid;
use App\Models\InspectoresObrasModel;
use App\Models\InspeccionModel;
use App\Models\ObraModel;
use App\Models\OperacionSincronizacionModel;

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
     */
    private function registrarOperacion(int $usuarioId, string $uuid, string $estado, ?string $error = null): void
    {
        try {
            (new OperacionSincronizacionModel())->registrar(
                $usuarioId,
                'alta',
                'inspecciones',
                $uuid,
                $estado,
                $error
            );
        } catch (\Throwable $e) {
            log_message('error', 'SIGOA sync: no se pudo registrar la operación uuid=' . $uuid . ' — ' . $e->getMessage());
        }
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