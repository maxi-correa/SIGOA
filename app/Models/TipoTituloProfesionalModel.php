<?php

namespace App\Models;

use CodeIgniter\Model;

class TipoTituloProfesionalModel extends Model
{
    protected $table = 'tipos_titulo_profesional';

    protected $primaryKey = 'id';

    protected $returnType = 'object';

    protected $useTimestamps = false;

    protected $useSoftDeletes = false;

    /**
     * Títulos profesionales activos ordenados alfabéticamente.
     *
     * @return list<object>
     */
    public function findAllActivos(): array
    {
        return $this
            ->where('activo', 1)
            ->orderBy('titulo', 'ASC')
            ->findAll();
    }
}