<?php

namespace App\Models;

use CodeIgniter\Model;

class BarrioModel extends Model
{
    protected $table = 'barrios';

    protected $primaryKey = 'id';

    protected $returnType = 'object';

    protected $useTimestamps = false;

    protected $useSoftDeletes = false;

    /**
     * Barrios activos ordenados por nombre para selectores.
     *
     * @return list<object>
     */
    public function findAllActivos(): array
    {
        return $this
            ->where('activo', 1)
            ->orderBy('nombre', 'ASC')
            ->findAll();
    }
}