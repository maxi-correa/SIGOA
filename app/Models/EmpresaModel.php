<?php

namespace App\Models;

use CodeIgniter\Model;

class EmpresaModel extends Model
{
    protected $table = 'empresas';

    protected $primaryKey = 'id';

    protected $returnType = 'object';

    protected $allowedFields = [
        'razon_social',
        'cuit',
        'domicilio',
        'telefono',
        'email',
        'ruta_logo',
        'activo',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = false;

    protected $useSoftDeletes = false;

    /**
     * Todas las empresas ordenadas por razón social (listado administrativo).
     *
     * @return list<object>
     */
    public function listarTodas(): array
    {
        return $this
            ->orderBy('razon_social', 'ASC')
            ->findAll();
    }

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

    /**
     * Crea una empresa y devuelve el ID insertado.
     */
    public function crear(array $datos): int
    {
        $ahora = date('Y-m-d H:i:s');

        $registro = [
            'razon_social' => $datos['razon_social'],
            'cuit'         => $datos['cuit'],
            'domicilio'    => $datos['domicilio'],
            'telefono'     => $datos['telefono'],
            'email'        => $datos['email'],
            'activo'       => $datos['activo'],
            'created_at'   => $ahora,
            'updated_at'   => $ahora,
        ];

        return (int) $this->insert($registro, true);
    }

    /**
     * Actualiza los datos de una empresa existente.
     *
     * `created_at` NO se modifica; `updated_at` se refresca.
     */
    public function actualizar(int $id, array $datos): bool
    {
        $registro = [
            'razon_social' => $datos['razon_social'],
            'cuit'         => $datos['cuit'],
            'domicilio'    => $datos['domicilio'],
            'telefono'     => $datos['telefono'],
            'email'        => $datos['email'],
            'activo'       => $datos['activo'],
            'updated_at'   => date('Y-m-d H:i:s'),
        ];

        return $this->update($id, $registro);
    }

    /**
     * Asocia el logo vigente de una empresa.
     *
     * Solo se modifica `ruta_logo` y `updated_at`. `created_at` se conserva.
     */
    public function actualizarLogo(int $id, string $rutaRelativa): bool
    {
        return $this->update($id, [
            'ruta_logo' => $rutaRelativa,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Quita la referencia del logo de una empresa.
     *
     * Solo se modifica `ruta_logo` y `updated_at`. `created_at` se conserva.
     */
    public function quitarLogo(int $id): bool
    {
        return $this->update($id, [
            'ruta_logo' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
