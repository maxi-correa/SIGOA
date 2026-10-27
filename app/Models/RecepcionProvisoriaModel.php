<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Recepción provisoria de una obra.
 *
 * Una obra tiene una única recepción provisoria. La fecha prevista de la
 * recepción definitiva se conserva en la misma fila como snapshot del plazo
 * de conservación aplicado.
 */
class RecepcionProvisoriaModel extends Model
{
    protected $table = 'recepciones_provisorias';

    protected $primaryKey = 'id';

    protected $returnType = 'object';

    protected $allowedFields = [
        'obra_id',
        'fecha_recepcion',
        'documento_id',
        'observaciones',
        'plazo_conservacion_dias',
        'fecha_prevista_definitiva',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = false;

    protected $useSoftDeletes = false;

    /**
     * Recepción provisoria de la obra, o null si aún no fue registrada.
     */
    public function porObra(int $obraId): ?object
    {
        return $this->where('obra_id', $obraId)->first();
    }

    /**
     * Indica si la obra ya tiene recepción provisoria.
     */
    public function tienePorObra(int $obraId): bool
    {
        return $this->porObra($obraId) !== null;
    }

    /**
     * Persiste la recepción provisoria de una obra.
     *
     * La fecha prevista de la definitiva y el plazo aplicado llegan ya
     * calculados y validados por el controlador.
     *
     * @param array<string, mixed> $datos
     */
    public function crear(int $obraId, array $datos): bool
    {
        $ahora = date('Y-m-d H:i:s');

        return (bool) $this->insert([
            'obra_id'                   => $obraId,
            'fecha_recepcion'           => $datos['fecha_recepcion'],
            'documento_id'              => $datos['documento_id'] ?? null,
            'observaciones'             => $datos['observaciones'] ?? null,
            'plazo_conservacion_dias'   => (int) $datos['plazo_conservacion_dias'],
            'fecha_prevista_definitiva' => $datos['fecha_prevista_definitiva'],
            'created_at'                => $ahora,
            'updated_at'                => $ahora,
        ], true);
    }

    /**
     * Corrige los datos de una recepción provisoria existente.
     *
     * Solo se usa mientras no exista recepción definitiva; esa
     * precondición se valida en el controlador dentro de la transacción.
     *
     * @param array<string, mixed> $datos
     */
    public function actualizar(int $id, array $datos): bool
    {
        if ($this->find($id) === null) {
            return false;
        }

        return $this->db->table($this->table)
            ->where('id', $id)
            ->update([
                'fecha_recepcion'           => $datos['fecha_recepcion'],
                'documento_id'              => $datos['documento_id'] ?? null,
                'observaciones'             => $datos['observaciones'] ?? null,
                'plazo_conservacion_dias'   => (int) $datos['plazo_conservacion_dias'],
                'fecha_prevista_definitiva' => $datos['fecha_prevista_definitiva'],
                'updated_at'                => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Bloquea la edición directa del modelo.
     *
     * La corrección solo se admite a través de `actualizar()`, que recibe
     * los campos ya normalizados y mantiene la fecha prevista coherente.
     *
     * @param array<string, mixed>|object|null $row
     */
    public function update($id = null, $row = null): bool
    {
        return false;
    }

    /**
     * Impide eliminar una recepción provisoria.
     */
    public function delete($id = null, bool $purge = false): bool
    {
        return false;
    }

    /**
     * Bloquea la inserción masiva.
     *
     * @param list<object|array<string, mixed>>|null $set
     *
     * @return false
     */
    public function insertBatch(?array $set = null, ?bool $escape = null, int $batchSize = 100, bool $testing = false)
    {
        return false;
    }

    /**
     * Bloquea la edición masiva.
     *
     * @param list<object|array<string, mixed>>|null $set
     *
     * @return false
     */
    public function updateBatch(?array $set = null, ?string $index = null, int $batchSize = 100, bool $returnSQL = false)
    {
        return false;
    }
}
