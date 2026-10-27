<?php

namespace App\Services;

use App\Libraries\PlazoObra;
use App\Models\AuditoriaModel;
use App\Models\EstadoObraModel;
use App\Models\ObraModel;
use App\Models\RecepcionDefinitivaModel;
use App\Models\RecepcionProvisoriaModel;

/**
 * Corrección in situ de recepciones (RP/RD).
 *
 * Permite corregir la provisoria, la definitiva o ambas en una misma
 * operación atómica. Reglas:
 *  - se permite solo si la obra está EN PLAZO DE CONSERVACIÓN o FINALIZADA;
 *  - la provisoria y la definitiva se validan con los valores resultantes de
 *    la corrección conjunta: una corrección incompatible con la otra se rechaza;
 *  - cambiar la provisoria recalcula la fecha prevista de la definitiva;
 *  - cada campo modificado se registra en `auditoria` con valor anterior,
 *    valor nuevo, usuario y fecha; los campos sin cambio no generan filas;
 *  - no cambia el estado de la obra ni crea o elimina recepciones.
 */
class RecepcionCorreccionService
{
    /**
     * @param array{fecha_recepcion: ?string, plazo_conservacion_dias: ?int, observaciones: ?string, documento_id: ?int}|null $provisoria
     * @param array{fecha_recepcion: ?string, observaciones: ?string, documento_id: ?int}|null                                 $definitiva
     *
     * @return array{corregida: bool, errores: list<string>}
     */
    public function corregir(int $obraId, ?array $provisoria, ?array $definitiva, ?int $usuarioId): array
    {
        if ($provisoria === null && $definitiva === null) {
            return ['corregida' => false, 'errores' => ['No se indicaron datos para corregir.']];
        }

        $db = db_connect();
        $db->transStart();

        try {
            $this->bloquearObra($obraId);

            $obra = (new ObraModel())->findDetalle($obraId);

            if ($obra === null) {
                return $this->fallar($db, ['La obra seleccionada no existe.']);
            }

            if (! (new EstadoObraModel())->permiteCorregirRecepcionProvisoria((string) ($obra->estado_nombre ?? ''))) {
                return $this->fallar($db, ['El estado actual de la obra no permite corregir las recepciones.']);
            }

            $actualProvisoria = (new RecepcionProvisoriaModel())->porObra($obraId);
            $actualDefinitiva = (new RecepcionDefinitivaModel())->porObra($obraId);

            if ($provisoria !== null && $actualProvisoria === null) {
                return $this->fallar($db, ['La obra no tiene recepción provisoria registrada.']);
            }

            if ($definitiva !== null && $actualDefinitiva === null) {
                return $this->fallar($db, ['La obra no tiene recepción definitiva registrada.']);
            }

            $provisoriaFinal = $actualProvisoria !== null
                ? $this->valoresProvisoria($actualProvisoria)
                : null;

            if ($provisoria !== null) {
                $provisoriaFinal = [
                    'fecha_recepcion'           => $provisoria['fecha_recepcion'] ?? null,
                    'plazo_conservacion_dias'   => $provisoria['plazo_conservacion_dias'] ?? null,
                    'observaciones'             => $provisoria['observaciones'] ?? null,
                    'documento_id'              => $provisoria['documento_id'] ?? null,
                ];

                $errores = $this->validarProvisoria($obra, $provisoriaFinal);

                if ($errores !== []) {
                    return $this->fallar($db, $errores);
                }

                $provisoriaFinal['fecha_prevista_definitiva'] = PlazoObra::fechaPrevistaDefinitiva(
                    (string) $provisoriaFinal['fecha_recepcion'],
                    (int) $provisoriaFinal['plazo_conservacion_dias']
                );
            }

            $definitivaFinal = $actualDefinitiva !== null
                ? $this->valoresDefinitiva($actualDefinitiva)
                : null;

            if ($definitiva !== null) {
                $definitivaFinal = [
                    'fecha_recepcion' => $definitiva['fecha_recepcion'] ?? null,
                    'observaciones'   => $definitiva['observaciones'] ?? null,
                    'documento_id'    => $definitiva['documento_id'] ?? null,
                ];
            }

            if ($actualDefinitiva !== null && $provisoriaFinal !== null) {
                $errores = $this->validarCompatibilidadConDefinitiva($obra, $provisoriaFinal, $definitivaFinal);

                if ($errores !== []) {
                    return $this->fallar($db, $errores);
                }
            }

            $auditoria = new AuditoriaModel();

            if ($provisoria !== null && $actualProvisoria !== null) {
                $antes   = $this->valoresProvisoria($actualProvisoria);
                $despues = $provisoriaFinal;

                if (! (new RecepcionProvisoriaModel())->actualizar((int) $actualProvisoria->id, $despues)) {
                    throw new \RuntimeException('No se pudo corregir la recepción provisoria.');
                }

                $auditoria->registrarCambios(
                    'CORRECCION',
                    'recepciones_provisorias',
                    (int) $actualProvisoria->id,
                    $usuarioId,
                    $antes,
                    $despues,
                    'Corrección de recepción provisoria'
                );

                $plazoAnterior = (string) ($obra->plazo_conservacion_dias ?? '');
                $plazoNuevo    = (int) $despues['plazo_conservacion_dias'];

                if (! (new ObraModel())->fijarPlazoConservacion($obraId, $plazoNuevo)) {
                    throw new \RuntimeException('No se pudo fijar el plazo de conservación de la obra.');
                }

                $auditoria->registrarCambios(
                    'CAMBIO',
                    'obras',
                    $obraId,
                    $usuarioId,
                    ['plazo_conservacion_dias' => $plazoAnterior],
                    ['plazo_conservacion_dias' => (string) $plazoNuevo],
                    'Corrección de recepción provisoria'
                );
            }

            if ($definitiva !== null && $actualDefinitiva !== null) {
                if (! (new RecepcionDefinitivaModel())->actualizar((int) $actualDefinitiva->id, $definitivaFinal)) {
                    throw new \RuntimeException('No se pudo corregir la recepción definitiva.');
                }

                $auditoria->registrarCambios(
                    'CORRECCION',
                    'recepciones_definitivas',
                    (int) $actualDefinitiva->id,
                    $usuarioId,
                    $this->valoresDefinitiva($actualDefinitiva),
                    $definitivaFinal,
                    'Corrección de recepción definitiva'
                );
            }

            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            $db->resetTransStatus();

            return ['corregida' => false, 'errores' => ['No se pudo guardar la corrección. No se modificó ningún dato.']];
        }

        if (! $db->transStatus()) {
            $db->resetTransStatus();

            return ['corregida' => false, 'errores' => ['No se pudo guardar la corrección. No se modificó ningún dato.']];
        }

        return ['corregida' => true, 'errores' => []];
    }

    /**
     * @param array{fecha_recepcion: ?string, plazo_conservacion_dias: ?int, documento_id: ?int} $datos
     *
     * @return list<string>
     */
    private function validarProvisoria(object $obra, array $datos): array
    {
        return array_values(array_filter([
            RecepcionValidacion::fechaProvisoria(
                $datos['fecha_recepcion'],
                $obra->fecha_inicio !== null ? (string) $obra->fecha_inicio : null
            ),
            RecepcionValidacion::plazo($datos['plazo_conservacion_dias']),
            RecepcionValidacion::acta(
                (int) $obra->id,
                $datos['documento_id'],
                RecepcionValidacion::TIPO_ACTA_PROVISORIA
            ),
        ]));
    }

    /**
     * La definitiva resultante debe seguir siendo válida frente a la
     * provisoria resultante (incluida la fecha prevista recalculada).
     *
     * @param array<string, mixed>      $provisoria
     * @param array<string, mixed>|null $definitiva
     *
     * @return list<string>
     */
    private function validarCompatibilidadConDefinitiva(object $obra, array $provisoria, ?array $definitiva): array
    {
        if ($definitiva === null) {
            return [];
        }

        $error = RecepcionValidacion::fechaDefinitiva(
            $definitiva['fecha_recepcion'],
            (string) $provisoria['fecha_recepcion'],
            (int) $provisoria['plazo_conservacion_dias']
        );

        if ($error !== null) {
            return ['La corrección es incompatible con la recepción definitiva: ' . $error];
        }

        $error = RecepcionValidacion::acta(
            (int) $obra->id,
            $definitiva['documento_id'],
            RecepcionValidacion::TIPO_ACTA_DEFINITIVA
        );

        return $error === null ? [] : [$error];
    }

    /**
     * @return array<string, string|null>
     */
    private function valoresProvisoria(object $fila): array
    {
        return [
            'fecha_recepcion'           => (string) $fila->fecha_recepcion,
            'plazo_conservacion_dias'   => (string) (int) $fila->plazo_conservacion_dias,
            'observaciones'             => $fila->observaciones !== null ? (string) $fila->observaciones : null,
            'documento_id'              => $fila->documento_id !== null ? (string) (int) $fila->documento_id : null,
            'fecha_prevista_definitiva' => (string) $fila->fecha_prevista_definitiva,
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function valoresDefinitiva(object $fila): array
    {
        return [
            'fecha_recepcion' => (string) $fila->fecha_recepcion,
            'observaciones'   => $fila->observaciones !== null ? (string) $fila->observaciones : null,
            'documento_id'    => $fila->documento_id !== null ? (string) (int) $fila->documento_id : null,
        ];
    }

    /**
     * @param list<string> $errores
     *
     * @return array{corregida: bool, errores: list<string>}
     */
    private function fallar(\CodeIgniter\Database\BaseConnection $db, array $errores): array
    {
        $db->transRollback();
        $db->resetTransStatus();

        return ['corregida' => false, 'errores' => $errores];
    }

    private function bloquearObra(int $obraId): void
    {
        $db = db_connect();

        if (stripos($db->getPlatform(), 'sqlite') !== false) {
            return;
        }

        $db->query('SELECT id FROM ' . $db->prefixTable('obras') . ' WHERE id = ? FOR UPDATE', [$obraId]);
    }
}
