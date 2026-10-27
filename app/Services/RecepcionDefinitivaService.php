<?php

namespace App\Services;

use App\Models\AuditoriaModel;
use App\Models\EstadoObraModel;
use App\Models\ObraModel;
use App\Models\RecepcionDefinitivaModel;
use App\Models\RecepcionProvisoriaModel;

/**
 * Alta de la recepción definitiva (RP/RD).
 *
 * Reglas de servidor:
 *  - la obra debe tener recepción provisoria y no tener definitiva;
 *  - la fecha no puede ser futura ni anterior a la fecha prevista calculada
 *    con la provisoria (`PlazoObra::permiteRecepcionDefinitiva()`);
 *  - el vencimiento del plazo no genera la definitiva: solo la crea esta acción;
 *  - el acta vinculada es opcional, pero si se informa debe ser una versión
 *    vigente del tipo ACTA DE RECEPCIÓN DEFINITIVA de la misma obra;
 *  - la definitiva, el cambio de estado a FINALIZADA (si corresponde) y la
 *    auditoría se registran en una única transacción.
 */
class RecepcionDefinitivaService
{
    public const TIPO_ACTA = RecepcionValidacion::TIPO_ACTA_DEFINITIVA;

    /**
     * Registra la recepción definitiva de una obra.
     *
     * @param array{fecha_recepcion: ?string, observaciones: ?string, documento_id: ?int} $datos
     *
     * @return array{registrada: bool, errores: list<string>}
     */
    public function registrar(int $obraId, array $datos, ?int $usuarioId): array
    {
        $errores = $this->validarPrevia($obraId, $datos);

        if ($errores !== []) {
            return ['registrada' => false, 'errores' => $errores];
        }

        $db = db_connect();
        $db->transStart();

        try {
            $this->bloquearObra($obraId);

            $obra       = (new ObraModel())->findDetalle($obraId);
            $provisoria = (new RecepcionProvisoriaModel())->porObra($obraId);
            $definitiva = new RecepcionDefinitivaModel();

            if ($obra === null || $provisoria === null || $definitiva->tienePorObra($obraId)) {
                $db->transRollback();

                return ['registrada' => false, 'errores' => ['No se pudo registrar la recepción definitiva. Verifique el estado de la obra.']];
            }

            $estadoModel = new EstadoObraModel();

            if (! $estadoModel->permiteRegistrarRecepcionDefinitiva((string) ($obra->estado_nombre ?? ''))) {
                $db->transRollback();

                return ['registrada' => false, 'errores' => ['El estado actual de la obra no permite registrar la recepción definitiva.']];
            }

            $errores = array_merge(
                $this->validarFecha($provisoria, $datos),
                $this->validarDocumento($obraId, $datos['documento_id'] ?? null)
            );

            if ($errores !== []) {
                $db->transRollback();

                return ['registrada' => false, 'errores' => $errores];
            }

            $definitivaId = $definitiva->crear($obraId, $datos);

            if ($definitivaId === null) {
                $db->transRollback();

                return ['registrada' => false, 'errores' => ['No se pudo registrar la recepción definitiva.']];
            }

            $auditoria = new AuditoriaModel();
            $auditoria->registrar(
                'ALTA',
                'recepciones_definitivas',
                $definitivaId,
                $usuarioId,
                null,
                null,
                json_encode([
                    'obra_id'         => $obraId,
                    'fecha_recepcion' => $datos['fecha_recepcion'],
                    'documento_id'    => $datos['documento_id'] ?? null,
                ], JSON_UNESCAPED_UNICODE),
            );

            if (mb_strtoupper(trim((string) ($obra->estado_nombre ?? ''))) === mb_strtoupper(EstadoObraModel::ESTADO_EN_PLAZO_CONSERVACION)) {
                $finalizada = $estadoModel->findByEstado(EstadoObraModel::ESTADO_FINALIZADA);

                if ($finalizada === null) {
                    throw new \RuntimeException('Falta el estado FINALIZADA en el catálogo.');
                }

                $cambiada = (new ObraModel())->update($obraId, [
                    'estado_obra_id' => (int) $finalizada->id,
                    'updated_at'     => date('Y-m-d H:i:s'),
                ]);

                if (! $cambiada) {
                    throw new \RuntimeException('No se pudo cambiar el estado de la obra.');
                }

                $auditoria->registrar(
                    'CAMBIO_ESTADO',
                    'obras',
                    $obraId,
                    $usuarioId,
                    'estado_obra_id',
                    (string) $obra->estado_obra_id,
                    (string) $finalizada->id,
                    'Registro de recepción definitiva'
                );
            }

            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            $db->resetTransStatus();

            return ['registrada' => false, 'errores' => ['No se pudo registrar la recepción definitiva.']];
        }

        if (! $db->transStatus()) {
            $db->resetTransStatus();

            return ['registrada' => false, 'errores' => ['No se pudo registrar la recepción definitiva.']];
        }

        return ['registrada' => true, 'errores' => []];
    }

    /**
     * Validaciones previas a la transacción (se repiten dentro de ella).
     *
     * @param array{fecha_recepcion: ?string, observaciones: ?string, documento_id: ?int} $datos
     *
     * @return list<string>
     */
    private function validarPrevia(int $obraId, array $datos): array
    {
        $provisoria = (new RecepcionProvisoriaModel())->porObra($obraId);

        if ($provisoria === null) {
            return ['Debe registrar la recepción provisoria antes de la definitiva.'];
        }

        if ((new RecepcionDefinitivaModel())->tienePorObra($obraId)) {
            return ['La obra ya tiene una recepción definitiva registrada.'];
        }

        return $this->validarFecha($provisoria, $datos);
    }

    /**
     * @param array{fecha_recepcion: ?string} $datos
     *
     * @return list<string>
     */
    private function validarFecha(object $provisoria, array $datos): array
    {
        $error = RecepcionValidacion::fechaDefinitiva(
            $datos['fecha_recepcion'],
            (string) $provisoria->fecha_recepcion,
            (int) $provisoria->plazo_conservacion_dias
        );

        return $error === null ? [] : [$error];
    }

    /**
     * @return list<string>
     */
    private function validarDocumento(int $obraId, ?int $documentoId): array
    {
        $error = RecepcionValidacion::acta($obraId, $documentoId, RecepcionValidacion::TIPO_ACTA_DEFINITIVA);

        return $error === null ? [] : [$error];
    }

    /**
     * Serializa las escrituras concurrentes sobre la obra (no aplica en SQLite).
     */
    private function bloquearObra(int $obraId): void
    {
        $db = db_connect();

        if (stripos($db->getPlatform(), 'sqlite') !== false) {
            return;
        }

        $db->query('SELECT id FROM ' . $db->prefixTable('obras') . ' WHERE id = ? FOR UPDATE', [$obraId]);
    }
}
