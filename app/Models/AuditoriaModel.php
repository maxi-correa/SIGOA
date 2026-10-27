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

    /**
     * Registra una fila por cada campo cuyo valor cambió entre `$antes` y
     * `$despues`. Los campos sin cambio no generan filas.
     *
     * @param array<string, scalar|null> $antes
     * @param array<string, scalar|null> $despues
     *
     * @return int Cantidad de filas registradas.
     */
    public function registrarCambios(
        string $accion,
        string $entidad,
        int $registroId,
        ?int $usuarioId,
        array $antes,
        array $despues,
        ?string $observaciones = null,
    ): int {
        $registradas = 0;

        foreach ($despues as $campo => $valorNuevo) {
            $valorAnterior = $antes[$campo] ?? null;

            if ((string) $valorAnterior === (string) $valorNuevo) {
                continue;
            }

            $this->registrar(
                $accion,
                $entidad,
                $registroId,
                $usuarioId,
                (string) $campo,
                $valorAnterior === null ? null : (string) $valorAnterior,
                $valorNuevo === null ? null : (string) $valorNuevo,
                $observaciones,
            );

            $registradas++;
        }

        return $registradas;
    }

    /**
     * Historial de recepciones de una obra: altas y correcciones de la
     * provisoria y de la definitiva, y cambios de plazo o estado de la obra
     * ligados a ellas. Más reciente primero, con el usuario resuelto.
     *
     * @return list<object>
     */
    public function historialRecepciones(int $obraId, ?int $provisoriaId, ?int $definitivaId): array
    {
        $builder = $this->db->table($this->table . ' a')
            ->select('a.*, u.nombre AS usuario_nombre, u.apellido AS usuario_apellido')
            ->join('usuarios u', 'u.id = a.usuario_id', 'left')
            ->groupStart()
                ->groupStart()
                    ->where('a.entidad', 'obras')
                    ->where('a.registro_id', $obraId)
                    ->whereIn('a.campo', ['plazo_conservacion_dias', 'estado_obra_id'])
                ->groupEnd();

        if ($provisoriaId !== null) {
            $builder->orGroupStart()
                ->where('a.entidad', 'recepciones_provisorias')
                ->where('a.registro_id', $provisoriaId)
            ->groupEnd();
        }

        if ($definitivaId !== null) {
            $builder->orGroupStart()
                ->where('a.entidad', 'recepciones_definitivas')
                ->where('a.registro_id', $definitivaId)
            ->groupEnd();
        }

        return $builder
            ->groupEnd()
            ->orderBy('a.fecha_hora', 'DESC')
            ->orderBy('a.id', 'DESC')
            ->get()
            ->getResultObject();
    }

    private function dispositivo(): ?string
    {
        $agente = service('request')->getUserAgent()->getAgentString();

        return $agente === '' ? null : mb_substr($agente, 0, 255);
    }
}
