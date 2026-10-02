<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Fotografías de inspecciones.
 *
 * El `id` es la identidad interna/autoincremental del servidor; el `uuid` es la
 * identidad estable de la entidad para operaciones offline y sincronización
 * futuras (F.2 / §52.3). El `uuid` no reemplaza al `id`.
 *
 * La relación con la inspección se mantiene por `inspeccion_id` (id del
 * servidor). No incluye `hash_sha256` (fuera de V1 por F.2) ni campos de
 * sincronización: esa información pertenece al almacenamiento local futuro.
 */
class FotografiaModel extends Model
{
    protected $table = 'fotografias';

    protected $primaryKey = 'id';

    protected $returnType = 'object';

    protected $allowedFields = [
        'uuid',
        'inspeccion_id',
        'nombre_archivo',
        'ruta_relativa',
        'ruta_thumbnail',
        'extension',
        'mime_type',
        'tamano_bytes',
        'ancho',
        'alto',
        'fecha_hora_captura',
        'fecha_hora_carga',
        'latitud',
        'longitud',
        'dispositivo',
        'anulada',
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

    /**
     * Fotografías no anuladas de una inspección, para su galería (E.5).
     *
     * Orden: `id ASC`, es decir, el orden en que el servidor registró cada
     * fotografía. No existe una regla previa documentada que ordene las
     * fotografías de una inspección y ninguna se deduce del nombre físico del
     * archivo; `id ASC` es estable entre visitas y coincide con la secuencia de
     * captura de la inspección.
     *
     * `anulada = 0` es parte de la consulta y no un filtro del controlador: una
     * fotografía anulada no es una fotografía de la inspección.
     *
     * @return list<object>
     */
    public function listarPorInspeccion(int $inspeccionId): array
    {
        return $this->where('inspeccion_id', $inspeccionId)
            ->where('anulada', false)
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    /**
     * Fotografía localizable por su identidad técnica y no anulada (E.5).
     *
     * Es la contraparte de lectura de `findByUuid()`, que se reserva para la
     * idempotencia de la sincronización y por eso ignora `anulada`: el
     * sincronizador debe reencontrar una fotografía anulada para no duplicarla.
     */
    public function findVisiblePorUuid(string $uuid): ?object
    {
        return $this->where('uuid', $uuid)
            ->where('anulada', false)
            ->first();
    }
}
