<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Resoluciones (actos administrativos) de una obra.
 *
 * Un trámite puede registrarse incompleto (sin número, año ni fecha) y
 * completarse luego. La confirmación es una acción explícita: a partir de
 * ese momento la fila es inmutable y no puede editarse ni eliminarse.
 *
 * El monto y los días se calculan/validan según el tipo de resolución en
 * el controlador (ResolucionObra); aquí solo se persisten.
 */
class ResolucionModel extends Model
{
    /** Campos editables. `confirmada` queda fuera: la fija `confirmar()`. */
    protected $allowedFields = [
        'obra_id',
        'tipo_resolucion_id',
        'numero',
        'anio',
        'fecha_resolucion',
        'monto',
        'dias',
        'observaciones',
        'created_at',
        'updated_at',
    ];

    protected $table = 'resoluciones';

    protected $primaryKey = 'id';

    protected $returnType = 'object';

    protected $useTimestamps = false;

    protected $useSoftDeletes = false;

    /**
     * Resoluciones de una obra con el nombre del tipo resuelto.
     *
     * @return list<object>
     */
    public function listarPorObra(int $obraId): array
    {
        return $this
            ->select('resoluciones.*')
            ->select('tipos_resolucion.tipo_resolucion AS tipo_nombre')
            ->join('tipos_resolucion', 'tipos_resolucion.id = resoluciones.tipo_resolucion_id', 'left')
            ->where('resoluciones.obra_id', $obraId)
            ->orderBy('resoluciones.anio', 'ASC')
            ->orderBy('resoluciones.numero', 'ASC')
            ->orderBy('resoluciones.id', 'ASC')
            ->findAll();
    }

    /**
     * Persiste un trámite nuevo (nace sin confirmar).
     *
     * @param array<string, mixed> $datos
     */
    public function crear(int $obraId, array $datos): bool
    {
        $ahora = date('Y-m-d H:i:s');

        return (bool) $this->insert([
            'obra_id'            => $obraId,
            'tipo_resolucion_id' => $datos['tipo_resolucion_id'],
            'numero'             => $datos['numero'] ?? null,
            'anio'               => $datos['anio'] ?? null,
            'fecha_resolucion'   => $datos['fecha_resolucion'] ?? null,
            'monto'              => $datos['monto'] ?? null,
            'dias'               => $datos['dias'] ?? null,
            'observaciones'      => $datos['observaciones'] ?? null,
            'created_at'         => $ahora,
            'updated_at'         => $ahora,
        ], true);
    }

    /**
     * Actualiza un trámite no confirmado.
     *
     * @param array<string, mixed> $datos
     */
    public function actualizar(int $id, array $datos): bool
    {
        if ($this->estaConfirmada($id)) {
            return false;
        }

        $datos['updated_at'] = date('Y-m-d H:i:s');

        return parent::update($id, $datos);
    }

    /**
     * Confirma un trámite: lo marca como inmutable. No confirma dos veces.
     */
    public function confirmar(int $id): bool
    {
        if ($this->estaConfirmada($id)) {
            return false;
        }

        return $this->db->table($this->table)
            ->where('id', $id)
            ->update([
                'confirmada' => 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Indica si un trámite está confirmado.
     */
    public function estaConfirmada(int $id): bool
    {
        $fila = $this->db->table($this->table)
            ->select('confirmada')
            ->where('id', $id)
            ->get()
            ->getRow();

        return $fila !== null && (int) $fila->confirmada === 1;
    }

    /**
     * Indica si ya existe otra resolución de la obra con el mismo número
     * y año. Solo aplica cuando ambos están informados.
     */
    public function existeNumeroAnio(int $obraId, int $numero, int $anio, ?int $exceptoId = null): bool
    {
        $builder = $this->db->table($this->table)
            ->where('obra_id', $obraId)
            ->where('numero', $numero)
            ->where('anio', $anio);

        if ($exceptoId !== null) {
            $builder->where('id !=', $exceptoId);
        }

        return $builder->countAllResults() > 0;
    }

    /**
     * Bloquea cualquier escritura directa sobre un trámite confirmado.
     *
     * @param array<string, mixed>|object|null $row
     */
    public function update($id = null, $row = null): bool
    {
        if ($id !== null && $this->estaConfirmada((int) $id)) {
            return false;
        }

        return parent::update($id, $row);
    }

    /**
     * Impide eliminar un trámite confirmado.
     */
    public function delete($id = null, bool $purge = false): bool
    {
        if ($id !== null && $this->estaConfirmada((int) $id)) {
            return false;
        }

        return parent::delete($id, $purge);
    }
}
