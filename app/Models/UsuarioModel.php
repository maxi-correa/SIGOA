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
     * Actualiza el correo electrónico de un usuario y su updated_at.
     *
     * El modelo no utiliza timestamps automáticos (useTimestamps = false),
     * por lo que updated_at se asigna explícitamente.
     */
    public function updateEmail(int $usuarioId, string $email): bool
    {
        return $this->update($usuarioId, [
            'email'      => $email,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Actualiza el password_hash de un usuario y su updated_at.
     *
     * Recibe el hash ya generado. Nunca se almacena una contraseña en
     * texto plano.
     */
    public function updatePassword(int $usuarioId, string $passwordHash): bool
    {
        return $this->update($usuarioId, [
            'password_hash' => $passwordHash,
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);
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
