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
     * Indica si la obra ya tiene recepción definitiva.
     */
    public function tienePorObra(int $obraId): bool
    {
        return $this->where('obra_id', $obraId)->countAllResults() > 0;
    }
}
