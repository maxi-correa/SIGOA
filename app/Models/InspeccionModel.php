<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Inspecciones de obra.
 *
 * El `id` es la identidad interna/autoincremental del servidor; el `uuid` es la
 * identidad estable de la entidad para operaciones offline y sincronización
 * futuras (F.2 / §52.3). El `uuid` no reemplaza al `id`.
 *
 * Una obra puede tener múltiples inspecciones en la misma fecha: la identidad
 * de cada inspección es su `uuid` (F.5). La unicidad del `uuid` queda delegada
 * al índice UNIQUE de la base de datos.
 *
 * No incluye todavía campos de sincronización (estado, remote_id, hash, cola):
 * esa información pertenece al almacenamiento local futuro.
 */
class InspeccionModel extends Model
{
    protected $table = 'inspecciones';

    protected $primaryKey = 'id';

    protected $returnType = 'object';

    protected $allowedFields = [
        'uuid',
        'obra_id',
        'inspector_id',
        'fecha_inspeccion',
        'hora_inspeccion',
        'observacion',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = false;

    protected $useSoftDeletes = false;

    protected $validationRules = [
        'uuid' => 'required|max_length[36]|uuid_valid',
    ];

    protected $validationMessages = [
        'uuid' => [
            'required'   => 'El campo uuid es obligatorio.',
            'max_length' => 'El campo uuid excede el largo permitido.',
            'uuid_valid' => 'El campo uuid no es un UUID válido.',
        ],
    ];

    public function findByUuid(string $uuid): ?object
    {
        return $this->where('uuid', $uuid)->first();
    }
}