<?php

namespace App\Models;

use CodeIgniter\Model;

class TipoDocumentoModel extends Model
{
    protected $table = 'tipos_documento';

    protected $primaryKey = 'id';

    protected $returnType = 'object';

    protected $useTimestamps = false;

    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'tipo_documento',
        'activo',
    ];

    /**
     * Obtiene todos los tipos de documento activos ordenados por nombre.
     *
     * @return list<object>
     */
    public function findAllActivos(): array
    {
        $tipos = $this->where('activo', 1)
            ->orderBy('tipo_documento', 'ASC')
            ->findAll();

        return $tipos;
    }

    /**
     * Busca un tipo de documento por su nombre exacto.
     */
    public function findByNombre(string $nombre): ?object
    {
        $nombre = trim($nombre);

        return $this->where('tipo_documento', $nombre)
            ->where('activo', 1)
            ->first();
    }
}
