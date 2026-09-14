<?php

namespace App\Models;

use CodeIgniter\Model;

class ObraModel extends Model
{
    protected $table = 'obras';

    protected $primaryKey = 'id';

    protected $returnType = 'object';

    protected $allowedFields = [
        'codigo',
        'expediente_municipal',
        'numero_licitacion',
        'tipo_licitacion_id',
        'nombre',
        'barrio_id',
        'empresa_id',
        'monto_contrato',
        'monto_contractual_vigente',
        'expediente_contable',
        'fecha_inicio',
        'plazo_original_valor',
        'plazo_original_unidad',
        'plazo_original_dias',
        'estado_obra_id',
        'observacion_general',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = false;

    protected $useSoftDeletes = false;

    /**
     * Listado paginado de obras con los nombres de catálogos resueltos.
     *
     * Las obras se ordenan por created_at DESC (las más recientes primero).
     * Devuelve la lista de la página actual; el paginador queda disponible
     * en $this->pager.
     *
     * @return list<object>
     */
    public function listarPaginado(int $perPage = 10): array
    {
        $this->select('obras.id, obras.codigo, obras.expediente_municipal, obras.nombre, obras.numero_licitacion, obras.created_at')
            ->select('barrios.nombre AS barrio_nombre')
            ->select('empresas.razon_social AS empresa_razon_social')
            ->select('tipos_licitacion.tipo_licitacion AS tipo_licitacion_nombre')
            ->select('estados_obra.estado AS estado_nombre')
            ->join('barrios', 'barrios.id = obras.barrio_id', 'left')
            ->join('empresas', 'empresas.id = obras.empresa_id', 'left')
            ->join('tipos_licitacion', 'tipos_licitacion.id = obras.tipo_licitacion_id', 'left')
            ->join('estados_obra', 'estados_obra.id = obras.estado_obra_id', 'inner')
            ->orderBy('obras.created_at', 'DESC')
            ->orderBy('obras.id', 'DESC');

        return $this->paginate($perPage);
    }

    /**
     * Verifica si ya existe una obra con el expediente municipal indicado.
     */
    public function existeExpediente(string $expediente): bool
    {
        return $this->where('expediente_municipal', $expediente)->countAllResults() > 0;
    }

    /**
     * Genera el próximo código secuencial de obra (OBR-000001, OBR-000002, ...).
     */
    public function generarCodigo(): string
    {
        $fila = $this
            ->select('codigo')
            ->orderBy('id', 'DESC')
            ->limit(1)
            ->get()
            ->getRow();

        $ultimo = $fila !== null ? (string) $fila->codigo : '';

        $secuencial = 0;

        if (preg_match('/^OBR-(\d{6})$/', $ultimo, $coincidencias)) {
            $secuencial = (int) $coincidencias[1];
        }

        $secuencial++;

        return 'OBR-' . str_pad((string) $secuencial, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Crea el registro inicial de una obra.
     *
     * El estado se impone aquí (PREVIO INICIO) de forma independiente de
     * cualquier valor enviado por el cliente. devuelve el ID insertado.
     */
    public function crear(array $datos): int
    {
        $ahora = date('Y-m-d H:i:s');

        $registro = [
            'codigo'             => $datos['codigo'],
            'expediente_municipal' => $datos['expediente_municipal'],
            'nombre'             => $datos['nombre'],
            'barrio_id'          => $datos['barrio_id'],
            'empresa_id'         => $datos['empresa_id'],
            'tipo_licitacion_id' => $datos['tipo_licitacion_id'],
            'numero_licitacion'  => $datos['numero_licitacion'],
            'estado_obra_id'     => $datos['estado_obra_id'],
            'created_at'         => $ahora,
            'updated_at'         => $ahora,
        ];

        return (int) $this->insert($registro, true);
    }
}