<?php

namespace App\Controllers;

use App\Models\UsuarioModel;

/**
 * Gestión de usuarios — únicamente consulta/listado por el momento.
 *
 * Accesible solo para SUPERADMINISTRADOR y ADMINISTRADOR.
 * La autorización se controla mediante el filtro RoleFilter en las rutas.
 */
class Usuarios extends BaseController
{
    protected UsuarioModel $usuarioModel;

    public function __construct()
    {
        $this->usuarioModel = new UsuarioModel();
    }

    public function index()
    {
        $session = session();

        $roles = $session->get('roles') ?? [];

        $data = [
            'titulo'    => 'Gestión de usuarios',
            'user_name' => $session->get('user_name'),
            'username'  => $session->get('username'),
            'roles'     => $roles,
            'usuarios'  => $this->usuarioModel->findAllConRoles(),
            'user_id'   => $session->get('user_id'),
        ];

        return view('usuarios/index', $data);
    }
}
