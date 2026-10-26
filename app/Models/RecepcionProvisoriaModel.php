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
     * Indica si la obra ya tiene recepción provisoria.
     */
    public function tienePorObra(int $obraId): bool
    {
        return $this->where('obra_id', $obraId)->countAllResults() > 0;
    }
}
