<?php

namespace App\Models;

use CodeIgniter\Model;

class TipoLicitacionModel extends Model
{
    protected $table = 'tipos_licitacion';

    protected $primaryKey = 'id';

    protected $returnType = 'object';

    protected $useTimestamps = false;

    protected $useSoftDeletes = false;

    /**
     * Tipos de licitación activos ordenados alfabéticamente.
     *
     * @return list<object>
     */
    public function findAllActivos(): array
    {
        return $this
            ->where('activo', 1)
            ->orderBy('tipo_licitacion', 'ASC')
            ->findAll();
    }
}