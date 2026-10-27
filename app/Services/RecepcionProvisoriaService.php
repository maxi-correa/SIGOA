<?php

namespace App\Services;

use App\Libraries\PlazoObra;
use App\Models\AuditoriaModel;
use App\Models\EstadoObraModel;
use App\Models\ObraModel;
use App\Models\RecepcionDefinitivaModel;
use App\Models\RecepcionProvisoriaModel;

/**
 * Alta de la recepción provisoria (BLOQUES 3 y 5).
 *
 * Sirve tanto para el registro normal como para la regularización de una
 * obra cuya acta de recepción provisoria ya existe como documento pero no
 * tiene recepción administrativa. En ambos casos la fecha la confirma el
 * usuario: nunca se toma de la fecha de carga del documento.
 *
 * La recepción, el plazo de la obra, el cambio de estado (si corresponde) y
 * la auditoría se registran en una única transacción. Nunca crea la
 * recepción definitiva ni finaliza la obra.
 */
class RecepcionProvisoriaService
{
    /**
     * @param array{fecha_recepcion: ?string, plazo_conservacion_dias: ?int, observaciones: ?string, documento_id: ?int} $datos
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

            $obra = (new ObraModel())->findDetalle($obraId);

            if (
                $obra === null
                || (new RecepcionProvisoriaModel())->tienePorObra($obraId)
                || (new RecepcionDefinitivaModel())->tienePorObra($obraId)
                || ! (new EstadoObraModel())->permiteRegistrarRecepcionProvisoria((string) ($obra->estado_nombre ?? ''))
            ) {
                return $this->fallar($db, ['No se pudo registrar la recepción provisoria. Verifique el estado de la obra.']);
            }

            $errores = $this->validarDatos($obra, $datos);

            if ($errores !== []) {
                return $this->fallar($db, $errores);
            }

            $prevista = PlazoObra::fechaPrevistaDefinitiva(
                (string) $datos['fecha_recepcion'],
                (int) $datos['plazo_conservacion_dias']
            );

            $provisoriaId = (new RecepcionProvisoriaModel())->crear($obraId, $datos + [
                'fecha_prevista_definitiva' => $prevista,
            ]);

            if ($provisoriaId === null) {
                return $this->fallar($db, ['No se pudo registrar la recepción provisoria.']);
            }

            $auditoria = new AuditoriaModel();
            $auditoria->registrar(
                'ALTA',
                'recepciones_provisorias',
                $provisoriaId,
                $usuarioId,
                null,
                null,
                json_encode([
                    'obra_id'                   => $obraId,
                    'fecha_recepcion'           => $datos['fecha_recepcion'],
                    'plazo_conservacion_dias'   => (int) $datos['plazo_conservacion_dias'],
                    'fecha_prevista_definitiva' => $prevista,
                    'documento_id'              => $datos['documento_id'] ?? null,
                ], JSON_UNESCAPED_UNICODE),
            );

            $plazoAnterior = (string) ($obra->plazo_conservacion_dias ?? '');

            if (! (new ObraModel())->fijarPlazoConservacion($obraId, (int) $datos['plazo_conservacion_dias'])) {
                throw new \RuntimeException('No se pudo fijar el plazo de conservación de la obra.');
            }

            $auditoria->registrarCambios(
                'CAMBIO',
                'obras',
                $obraId,
                $usuarioId,
                ['plazo_conservacion_dias' => $plazoAnterior],
                ['plazo_conservacion_dias' => (string) (int) $datos['plazo_conservacion_dias']],
                'Alta de recepción provisoria'
            );

            if ($this->esEstado($obra, EstadoObraModel::ESTADO_EN_EJECUCION)) {
                $this->cambiarEstado($obraId, $obra, EstadoObraModel::ESTADO_EN_PLAZO_CONSERVACION, $usuarioId);
            }

            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            $db->resetTransStatus();

            return ['registrada' => false, 'errores' => ['No se pudo registrar la recepción provisoria.']];
        }

        if (! $db->transStatus()) {
            $db->resetTransStatus();

            return ['registrada' => false, 'errores' => ['No se pudo registrar la recepción provisoria.']];
        }

        return ['registrada' => true, 'errores' => []];
    }

    /**
     * @return list<string>
     */
    private function validarPrevia(int $obraId, array $datos): array
    {
        $obra = (new ObraModel())->findDetalle($obraId);

        if ($obra === null) {
            return ['La obra seleccionada no existe.'];
        }

        if ((new RecepcionProvisoriaModel())->tienePorObra($obraId)) {
            return ['La obra ya tiene una recepción provisoria registrada.'];
        }

        if ((new RecepcionDefinitivaModel())->tienePorObra($obraId)) {
            return ['La obra ya tiene una recepción definitiva registrada.'];
        }

        if (! (new EstadoObraModel())->permiteRegistrarRecepcionProvisoria((string) ($obra->estado_nombre ?? ''))) {
            return ['El estado actual de la obra no permite registrar la recepción provisoria.'];
        }

        return $this->validarDatos($obra, $datos);
    }

    /**
     * @return list<string>
     */
    private function validarDatos(object $obra, array $datos): array
    {
        $errores = array_filter([
            RecepcionValidacion::fechaProvisoria(
                $datos['fecha_recepcion'] ?? null,
                $obra->fecha_inicio !== null ? (string) $obra->fecha_inicio : null
            ),
            RecepcionValidacion::plazo($datos['plazo_conservacion_dias'] ?? null),
            RecepcionValidacion::acta(
                (int) $obra->id,
                $datos['documento_id'] ?? null,
                RecepcionValidacion::TIPO_ACTA_PROVISORIA
            ),
        ]);

        return array_values($errores);
    }

    private function esEstado(object $obra, string $estado): bool
    {
        return mb_strtoupper(trim((string) ($obra->estado_nombre ?? ''))) === mb_strtoupper($estado);
    }

    private function cambiarEstado(int $obraId, object $obra, string $estadoNombre, ?int $usuarioId): void
    {
        $destino = (new EstadoObraModel())->findByEstado($estadoNombre);

        if ($destino === null) {
            throw new \RuntimeException('Falta el estado ' . $estadoNombre . ' en el catálogo.');
        }

        if (! (new ObraModel())->update($obraId, [
            'estado_obra_id' => (int) $destino->id,
            'updated_at'     => date('Y-m-d H:i:s'),
        ])) {
            throw new \RuntimeException('No se pudo cambiar el estado de la obra.');
        }

        (new AuditoriaModel())->registrarCambios(
            'CAMBIO_ESTADO',
            'obras',
            $obraId,
            $usuarioId,
            ['estado_obra_id' => (string) $obra->estado_obra_id],
            ['estado_obra_id' => (string) $destino->id],
            'Alta de recepción provisoria'
        );
    }

    /**
     * @param list<string> $errores
     *
     * @return array{registrada: bool, errores: list<string>}
     */
    private function fallar(\CodeIgniter\Database\BaseConnection $db, array $errores): array
    {
        $db->transRollback();
        $db->resetTransStatus();

        return ['registrada' => false, 'errores' => $errores];
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
