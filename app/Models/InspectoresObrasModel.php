<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Asignaciones de inspectores a obras.
 *
 * Conserva el historial de asignaciones: cada fila representa un período
 * con `fecha_inicio` y `fecha_fin`. La asignación vigente es aquella cuya
 * `fecha_fin` es NULL.
 */
class InspectoresObrasModel extends Model
{
    protected $table = 'inspectores_obras';

    protected $primaryKey = 'id';

    protected $returnType = 'object';

    protected $allowedFields = [
        'obra_id',
        'usuario_id',
        'fecha_inicio',
        'fecha_fin',
        'documento_id',
        'observaciones',
        'created_at',
    ];

    protected $useTimestamps = false;

    protected $useSoftDeletes = false;

    /**
     * Asignación vigente de una obra (fecha_fin NULL).
     *
     * Si existieran varias filas abiertas por datos heredados, se toma la
     * de fecha_inicio más reciente.
     */
    public function findVigentePorObra(int $obraId): ?object
    {
        return $this
            ->where('obra_id', $obraId)
            ->where('fecha_fin', null)
            ->orderBy('fecha_inicio', 'DESC')
            ->orderBy('id', 'DESC')
            ->first();
    }

    /**
     * Asignación vigente con los datos del usuario inspector.
     */
    public function findVigenteConInspector(int $obraId): ?object
    {
        return $this
            ->select('inspectores_obras.*')
            ->select('usuarios.nombre AS inspector_nombre, usuarios.apellido AS inspector_apellido')
            ->join('usuarios', 'usuarios.id = inspectores_obras.usuario_id', 'inner')
            ->where('inspectores_obras.obra_id', $obraId)
            ->where('inspectores_obras.fecha_fin', null)
            ->orderBy('inspectores_obras.fecha_inicio', 'DESC')
            ->orderBy('inspectores_obras.id', 'DESC')
            ->first();
    }

    /**
     * Obras asignadas de forma vigente a un inspector.
     *
     * Devuelve las obras cuya asignación actual (fecha_fin NULL) corresponde
     * al usuario indicado, con los nombres de catálogo resueltos
     * (tipo de licitación y estado). Es una consulta de solo lectura.
     *
     * @return list<object>
     */
    public function listarVigentesConObra(int $usuarioId): array
    {
        return $this
            ->select('obras.id, obras.expediente_municipal, obras.nombre, obras.numero_licitacion')
            ->select('obras.tipo_licitacion_id, obras.estado_obra_id')
            ->select('tipos_licitacion.tipo_licitacion AS tipo_licitacion_nombre')
            ->select('estados_obra.estado AS estado_nombre')
            ->join('obras', 'obras.id = inspectores_obras.obra_id', 'inner')
            ->join('tipos_licitacion', 'tipos_licitacion.id = obras.tipo_licitacion_id', 'left')
            ->join('estados_obra', 'estados_obra.id = obras.estado_obra_id', 'inner')
            ->where('inspectores_obras.usuario_id', $usuarioId)
            ->where('inspectores_obras.fecha_fin', null)
            ->orderBy('obras.nombre', 'ASC')
            ->orderBy('obras.id', 'ASC')
            ->findAll();
    }

    /**
     * Verifica si un inspector es la asignación vigente de una obra.
     *
     * Se usa para validar la pertenencia del inspector a la obra antes de
     * permitirle acceder al detalle operativo.
     */
    public function esVigente(int $obraId, int $usuarioId): bool
    {
        return $this
            ->where('obra_id', $obraId)
            ->where('usuario_id', $usuarioId)
            ->where('fecha_fin', null)
            ->countAllResults() > 0;
    }

    /**
     * Cierra una asignación estableciendo su fecha de fin.
     */
    public function cerrarVigente(int $id, string $fechaFin): bool
    {
        return $this->update($id, ['fecha_fin' => $fechaFin]);
    }

    /**
     * Registra una nueva asignación vigente para una obra.
     */
    public function crearAsignacion(int $obraId, int $usuarioId, string $fechaInicio): int
    {
        return (int) $this->insert([
            'obra_id'     => $obraId,
            'usuario_id'  => $usuarioId,
            'fecha_inicio' => $fechaInicio,
            'created_at'  => date('Y-m-d H:i:s'),
        ], true);
    }

    /**
     * Reemplaza la asignación vigente de una obra de forma atómica.
     *
     * Cierra la asignación abierta y crea la nueva dentro de una misma
     * transacción, para no dejar la obra sin inspector si una operación
     * falla. Si la asignación anterior no existe, solo crea la nueva.
     */
    public function cambiarAsignacion(int $obraId, int $usuarioId, string $fechaCambio): bool
    {
        $this->db->transStart();

        $vigente = $this->findVigentePorObra($obraId);

        if ($vigente !== null) {
            $this->cerrarVigente((int) $vigente->id, $fechaCambio);
        }

        $this->crearAsignacion($obraId, $usuarioId, $fechaCambio);

        $this->db->transComplete();

        return $this->db->transStatus();
    }
}
