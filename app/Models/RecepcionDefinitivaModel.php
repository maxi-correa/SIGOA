<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Recepción definitiva de una obra.
 *
 * Una obra tiene una única recepción definitiva, posterior al plazo de
 * conservación iniciado con la recepción provisoria.
 */
class RecepcionDefinitivaModel extends Model
{
    protected $table = 'recepciones_definitivas';

    protected $primaryKey = 'id';

    protected $returnType = 'object';

    protected $allowedFields = [
        'obra_id',
        'fecha_recepcion',
        'documento_id',
        'observaciones',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = false;

    protected $useSoftDeletes = false;

    /**
     * Recepción definitiva de la obra, o null si aún no fue registrada.
     */
    public function porObra(int $obraId): ?object
    {
        return $this->where('obra_id', $obraId)->first();
    }

    /**
     * Indica si la obra ya tiene recepción definitiva.
     */
    public function tienePorObra(int $obraId): bool
    {
        return $this->porObra($obraId) !== null;
    }

    /**
     * Persiste la recepción definitiva de una obra.
     *
     * @param array<string, mixed> $datos
     */
    public function crear(int $obraId, array $datos): ?int
    {
        $ahora = date('Y-m-d H:i:s');

        $id = $this->insert([
            'obra_id'         => $obraId,
            'fecha_recepcion' => $datos['fecha_recepcion'],
            'documento_id'    => $datos['documento_id'] ?? null,
            'observaciones'   => $datos['observaciones'] ?? null,
            'created_at'      => $ahora,
            'updated_at'      => $ahora,
        ], true);

        return $id === false ? null : (int) $id;
    }

    /**
     * Bloquea la edición directa del modelo.
     *
     * La recepción definitiva es un hecho administrativo inmutable: no se
     * corrige ni se elimina desde la aplicación.
     *
     * @param array<string, mixed>|object|null $row
     */
    public function update($id = null, $row = null): bool
    {
        return false;
    }

    /**
     * Impide eliminar una recepción definitiva.
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
