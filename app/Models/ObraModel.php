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
            ->select('obras.barrio_id, obras.empresa_id, obras.tipo_licitacion_id, obras.estado_obra_id')
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
     * Detalle de una obra con los nombres de catálogos resueltos.
     *
     * Se utiliza en la ficha de obra, donde se muestran los datos
     * identificatorios junto con los valores de catálogo.
     */
    public function findDetalle(int $id): ?object
    {
        return $this
            ->select('obras.*')
            ->select('barrios.nombre AS barrio_nombre')
            ->select('empresas.razon_social AS empresa_razon_social')
            ->select('tipos_licitacion.tipo_licitacion AS tipo_licitacion_nombre')
            ->select('estados_obra.estado AS estado_nombre')
            ->join('barrios', 'barrios.id = obras.barrio_id', 'left')
            ->join('empresas', 'empresas.id = obras.empresa_id', 'left')
            ->join('tipos_licitacion', 'tipos_licitacion.id = obras.tipo_licitacion_id', 'left')
            ->join('estados_obra', 'estados_obra.id = obras.estado_obra_id', 'inner')
            ->where('obras.id', $id)
            ->first();
    }

    /**
     * Listado de obras a las que el usuario puede consultar el historial de
     * inspecciones (Fase E.6).
     *
     * Es una consulta de **solo lectura** que resuelve en un único SQL lo que
     * la tarjeta del listado muestra: estado, inspector vigente y representante
     * técnico vigente. Las dos asignaciones se resuelven con `LEFT JOIN` sobre
     * la fila abierta (`fecha_fin IS NULL`) porque ambas pueden faltar y el
     * listado no debe descartar la obra por eso.
     *
     * El filtro por inspector es una subconsulta y no un `JOIN` directo: un
     * inspector puede tener varios períodos de asignación en la misma obra y
     * un `JOIN` multiplicaría las filas y duplicaría tarjetas.
     *
     * @param int|null $inspectorUsuarioId `usuario_id` de un inspector: limita
     *                                    a las obras que tuvo asignadas
     *                                    (vigentes o históricas). `null`
     *                                    devuelve todas las obras.
     *
     * @return list<object>
     */
    public function listarParaConsulta(?int $inspectorUsuarioId = null): array
    {
        return $this->select('obras.id, obras.codigo, obras.expediente_municipal, obras.nombre')
            ->select('estados_obra.estado AS estado_nombre')
            ->select('usuarios.nombre AS inspector_nombre, usuarios.apellido AS inspector_apellido')
            ->select(
                'representantes_tecnicos.nombre AS representante_nombre, '
                . 'representantes_tecnicos.apellido AS representante_apellido'
            )
            ->join('estados_obra', 'estados_obra.id = obras.estado_obra_id', 'inner')
            ->join('inspectores_obras', 'inspectores_obras.obra_id = obras.id AND inspectores_obras.fecha_fin IS NULL', 'left')
            ->join('usuarios', 'usuarios.id = inspectores_obras.usuario_id', 'left')
            ->join(
                'obras_representantes_tecnicos',
                'obras_representantes_tecnicos.obra_id = obras.id AND obras_representantes_tecnicos.fecha_fin IS NULL',
                'left'
            )
            ->join(
                'representantes_tecnicos',
                'representantes_tecnicos.id = obras_representantes_tecnicos.representante_tecnico_id',
                'left'
            )
            ->when(
                $inspectorUsuarioId !== null,
                static function ($builder) use ($inspectorUsuarioId): void {
                    $builder->whereIn('obras.id', static function ($query) use ($inspectorUsuarioId): void {
                        $query
                            ->select('obra_id')
                            ->from('inspectores_obras')
                            ->where('usuario_id', $inspectorUsuarioId);
                    });
                }
            )
            ->orderBy('obras.nombre', 'ASC')
            ->orderBy('obras.id', 'ASC')
            ->findAll();
    }

    /**
     * Verifica si ya existe una obra con el expediente municipal indicado.
     *
     * El expediente se compara en mayúsculas, igual que como se almacena.
     * Si se indica $exceptoId, la obra con ese ID no cuenta como duplicada
     * (se usa al editar para permitir conservar el propio expediente).
     */
    public function existeExpediente(string $expediente, ?int $exceptoId = null): bool
    {
        $this->where('expediente_municipal', mb_strtoupper($expediente));

        if ($exceptoId !== null) {
            $this->where('id !=', $exceptoId);
        }

        return $this->countAllResults() > 0;
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

    /**
     * Actualiza únicamente los campos básicos de una obra existente.
     *
     * El código y la fecha de creación NO se tocan; `updated_at` se
     * actualiza a la fecha/hora de la modificación.
     */
    public function actualizar(int $id, array $datos): bool
    {
        $registro = [
            'expediente_municipal' => $datos['expediente_municipal'],
            'nombre'               => $datos['nombre'],
            'barrio_id'            => $datos['barrio_id'],
            'empresa_id'           => $datos['empresa_id'],
            'tipo_licitacion_id'   => $datos['tipo_licitacion_id'],
            'numero_licitacion'    => $datos['numero_licitacion'],
            'estado_obra_id'       => $datos['estado_obra_id'],
            'updated_at'           => date('Y-m-d H:i:s'),
        ];

        return $this->update($id, $registro);
    }

    /**
     * Actualiza los datos de la ficha: expediente contable, fecha de
     * inicio y plazo original.
     *
     * `updated_at` se refresca y `created_at` se conserva.
     */
    public function actualizarFicha(int $id, array $datos): bool
    {
        return $this->update($id, [
            'expediente_contable'   => $datos['expediente_contable'],
            'fecha_inicio'          => $datos['fecha_inicio'],
            'plazo_original_valor'  => $datos['plazo_original_valor'],
            'plazo_original_unidad' => $datos['plazo_original_unidad'],
            'plazo_original_dias'   => $datos['plazo_original_dias'],
            'updated_at'            => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Refresca únicamente `updated_at` de una obra.
     *
     * Se usa cuando una operación relacionada (por ejemplo un cambio de
     * inspector) representa una modificación real de la obra.
     */
    public function touch(int $id): bool
    {
        return $this->update($id, ['updated_at' => date('Y-m-d H:i:s')]);
    }
}