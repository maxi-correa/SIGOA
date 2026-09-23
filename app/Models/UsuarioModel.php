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
     * Devuelve todos los usuarios ordenados alfabéticamente,
     * con los roles asociados a cada uno.
     *
     * @return list<object>
     */
    public function findAllConRoles(): array
    {
        $usuarios = $this
            ->orderBy('apellido', 'ASC')
            ->orderBy('nombre', 'ASC')
            ->findAll();

        foreach ($usuarios as $usuario) {
            $usuario->roles = $this->findRolesByUsuarioId((int) $usuario->id);
        }

        return $usuarios;
    }

    /**
     * Usuarios activos con rol INSPECTOR, ordenados alfabéticamente.
     *
     * Es la única fuente válida para asignar o cambiar el inspector de
     * una obra. No se consideran usuarios inactivos ni otros roles.
     *
     * @return list<object>
     */
    public function findInspectoresActivos(): array
    {
        return $this->db
            ->table('usuarios')
            ->distinct()
            ->select('usuarios.id, usuarios.nombre, usuarios.apellido')
            ->join('usuarios_roles', 'usuarios_roles.usuario_id = usuarios.id', 'inner')
            ->join('roles', 'roles.id = usuarios_roles.rol_id', 'inner')
            ->where('roles.nombre', 'INSPECTOR')
            ->where('roles.activo', 1)
            ->where('usuarios.activo', 1)
            ->orderBy('usuarios.apellido', 'ASC')
            ->orderBy('usuarios.nombre', 'ASC')
            ->get()
            ->getResultObject();
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
