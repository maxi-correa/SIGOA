<?php

namespace App\Models;

use CodeIgniter\Model;

class EmpresaModel extends Model
{
    protected $table = 'empresas';

    protected $primaryKey = 'id';

    protected $returnType = 'object';

    protected $useTimestamps = false;

    protected $useSoftDeletes = false;

    /**
     * Empresas activas ordenadas por razón social para selectores.
     *
     * @return list<object>
     */
    public function findAllActivas(): array
    {
        return $this
            ->where('activo', 1)
            ->orderBy('razon_social', 'ASC')
            ->findAll();
    }
}