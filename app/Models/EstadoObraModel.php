<?php

namespace App\Models;

use CodeIgniter\Model;

class EstadoObraModel extends Model
{
    protected $table = 'estados_obra';

    protected $primaryKey = 'id';

    protected $returnType = 'object';

    protected $useTimestamps = false;

    protected $useSoftDeletes = false;

    /**
     * Estado de obra por nombre.
     */
    public function findByEstado(string $estado): ?object
    {
        return $this->where('estado', $estado)->first();
    }

    /**
     * Estados de obra activos en orden lógico del catálogo.
     *
     * @return list<object>
     */
    public function findAllActivos(): array
    {
        return $this
            ->where('activo', 1)
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    /**
     * Estado inicial obligatorio de toda obra nueva.
     */
    public function findPrevioInicio(): ?object
    {
        return $this->findByEstado('PREVIO INICIO');
    }
}