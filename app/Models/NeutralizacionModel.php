<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Neutralizaciones de obra (períodos de suspensión de la ejecución).
 *
 * Este bloque solo necesita saber si la obra tiene una neutralización
 * abierta (sin fecha de reinicio) para no presentar una fecha de
 * finalización como vigente. El alta, la edición y el reinicio de las
 * neutralizaciones corresponden a otra etapa y no se implementan aquí.
 */
class NeutralizacionModel extends Model
{
    protected $table = 'neutralizaciones';

    protected $primaryKey = 'id';

    protected $returnType = 'object';

    protected $useTimestamps = false;

    protected $useSoftDeletes = false;

    /**
     * Indica si la obra tiene una neutralización abierta.
     *
     * Una neutralización abierta es la que aún no registra fecha de
     * reinicio (`fecha_reinicio IS NULL`).
     */
    public function tieneAbierta(int $obraId): bool
    {
        return $this
            ->where('obra_id', $obraId)
            ->where('fecha_reinicio', null)
            ->countAllResults() > 0;
    }
}
