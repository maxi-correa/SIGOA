<?php

namespace App\Models;

use CodeIgniter\Model;

class RepresentanteTecnicoModel extends Model
{
    protected $table = 'representantes_tecnicos';

    protected $primaryKey = 'id';

    protected $returnType = 'object';

    protected $allowedFields = [
        'titulo_profesional_id',
        'nombre',
        'apellido',
        'matricula',
        'activo',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = false;

    protected $useSoftDeletes = false;

    /**
     * Todos los representantes técnicos ordenados por apellido y nombre.
     *
     * @return list<object>
     */
    public function listarTodas(): array
    {
        return $this
            ->orderBy('apellido', 'ASC')
            ->orderBy('nombre', 'ASC')
            ->findAll();
    }

    /**
     * Representantes técnicos con el nombre de su título profesional.
     *
     * Incluye representantes activos e inactivos: se utiliza en la gestión
     * del padrón, donde el estado se muestra explícitamente.
     *
     * @return list<object>
     */
    public function listarTodasConTitulo(): array
    {
        return $this->listarConTitulo(false);
    }

    /**
     * Representantes técnicos activos con el nombre de su título.
     *
     * Se utiliza para poblar los selectores donde solo deben ofrecerse
     * representantes vigentes, como el cambio de representante en la ficha
     * de obra.
     *
     * @return list<object>
     */
    public function listarActivasConTitulo(): array
    {
        return $this->listarConTitulo(true);
    }

    /**
     * Representantes técnicos activos ordenados por apellido y nombre.
     *
     * @return list<object>
     */
    public function listarActivas(): array
    {
        return $this
            ->where('activo', 1)
            ->orderBy('apellido', 'ASC')
            ->orderBy('nombre', 'ASC')
            ->findAll();
    }

    /**
     * Consulta con el título profesional, opcionalmente solo activos.
     *
     * @return list<object>
     */
    private function listarConTitulo(bool $soloActivos): array
    {
        $builder = $this
            ->select('representantes_tecnicos.*')
            ->select('tipos_titulo_profesional.titulo AS titulo_profesional_nombre')
            ->join('tipos_titulo_profesional', 'tipos_titulo_profesional.id = representantes_tecnicos.titulo_profesional_id', 'inner');

        if ($soloActivos) {
            $builder->where('representantes_tecnicos.activo', 1);
        }

        return $builder
            ->orderBy('representantes_tecnicos.apellido', 'ASC')
            ->orderBy('representantes_tecnicos.nombre', 'ASC')
            ->findAll();
    }

    /**
     * Representante técnico por matrícula.
     *
     * La matrícula es opcional, por lo que solo se busca por valores
     * no nulos. Devuelve null si no existe o el campo es NULL.
     */
    public function buscarPorMatricula(string $matricula): ?object
    {
        return $this->where('matricula', $matricula)->first();
    }

    /**
     * Indica si una matrícula no nula ya está registrada.
     *
     * Permite excluir a un representante (edición) de la comprobación.
     */
    public function existeMatricula(string $matricula, ?int $exceptoId = null): bool
    {
        $this->where('matricula', $matricula);

        if ($exceptoId !== null) {
            $this->where('id !=', $exceptoId);
        }

        return $this->countAllResults() > 0;
    }

    /**
     * Crea un representante técnico y devuelve el ID insertado.
     *
     * `useTimestamps` está desactivado, por lo que las fechas se
     * asignan explícitamente.
     *
     * @param array<string, mixed> $datos
     */
    public function crear(array $datos): int
    {
        $ahora = date('Y-m-d H:i:s');

        return (int) $this->insert([
            'titulo_profesional_id' => $datos['titulo_profesional_id'],
            'nombre'                => $datos['nombre'],
            'apellido'              => $datos['apellido'],
            'matricula'             => $datos['matricula'],
            'activo'                => 1,
            'created_at'            => $ahora,
            'updated_at'            => $ahora,
        ], true);
    }

    /**
     * Activa o desactiva un representante técnico.
     *
     * El estado reemplaza a la eliminación física: el representante y su
     * historial de asignaciones se conservan intactos.
     */
    public function cambiarActivo(int $id, int $activo): bool
    {
        return $this->update($id, [
            'activo'     => $activo === 1 ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Actualiza los datos de un representante técnico existente.
     *
     * `created_at` NO se modifica; `updated_at` se refresca.
     *
     * @param array<string, mixed> $datos
     */
    public function actualizar(int $id, array $datos): bool
    {
        return $this->update($id, [
            'titulo_profesional_id' => $datos['titulo_profesional_id'],
            'nombre'                => $datos['nombre'],
            'apellido'              => $datos['apellido'],
            'matricula'             => $datos['matricula'],
            'updated_at'            => date('Y-m-d H:i:s'),
        ]);
    }
}