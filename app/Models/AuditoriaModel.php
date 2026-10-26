<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Registro de auditoría de acciones relevantes (tabla `auditoria`).
 *
 * Solo inserta filas: no expone actualización ni eliminación.
 */
class AuditoriaModel extends Model
{
    protected $table = 'auditoria';

    protected $primaryKey = 'id';

    protected $returnType = 'object';

    protected $useTimestamps = false;

    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'usuario_id',
        'fecha_hora',
        'accion',
        'entidad',
        'registro_id',
        'campo',
        'valor_anterior',
        'valor_nuevo',
        'dispositivo',
        'observaciones',
    ];

    /**
     * Registra una acción sobre una entidad.
     *
     * Para un alta, `campo` y `valor_anterior` van en null y `valor_nuevo`
     * puede resumir el registro creado. Para un cambio, se informa el campo
     * con su valor anterior y el nuevo.
     *
     * @return bool true si la fila se insertó; ante error de base de datos se lanza excepción.
     */
    public function registrar(
        string $accion,
        string $entidad,
        ?int $registroId,
        ?int $usuarioId,
        ?string $campo = null,
        ?string $valorAnterior = null,
        ?string $valorNuevo = null,
        ?string $observaciones = null,
    ): bool {
        return (bool) $this->insert([
            'usuario_id'     => $usuarioId,
            'fecha_hora'     => date('Y-m-d H:i:s'),
            'accion'         => $accion,
            'entidad'        => $entidad,
            'registro_id'    => $registroId,
            'campo'          => $campo,
            'valor_anterior' => $valorAnterior,
            'valor_nuevo'    => $valorNuevo,
            'dispositivo'    => $this->dispositivo(),
            'observaciones'  => $observaciones,
        ], true);
    }

    private function dispositivo(): ?string
    {
        $agente = service('request')->getUserAgent()->getAgentString();

        return $agente === '' ? null : mb_substr($agente, 0, 255);
    }
}
