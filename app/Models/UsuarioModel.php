<?php

namespace App\Models;

use CodeIgniter\Model;

class UsuarioModel extends Model
{
    protected $table = 'usuarios';

    protected $primaryKey = 'id';

    protected $returnType = 'object';

    protected $allowedFields = [
        'nombre',
        'apellido',
        'usuario',
        'password_hash',
        'email',
        'activo',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = false;

    protected $useSoftDeletes = false;

    public function findByUsuario(string $usuario): ?object
    {
        return $this->where('usuario', $usuario)->first();
    }

    /**
     * Obtiene los nombres de los roles activos de un usuario.
     *
     * @return list<string>
     */
    public function findRolesByUsuarioId(int $usuarioId): array
    {
        $roles = $this->db
            ->table('usuarios_roles')
            ->select('roles.nombre')
            ->join('roles', 'roles.id = usuarios_roles.rol_id', 'inner')
            ->where('usuarios_roles.usuario_id', $usuarioId)
            ->where('roles.activo', 1)
            ->get()
            ->getResultArray();

        return array_column($roles, 'nombre');
    }
}
