<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class Dashboard extends BaseController
{
    public function index(): string
    {
        $session = session();

        $data = [
            'titulo'    => 'Panel de Administrador',
            'user_name' => $session->get('user_name'),
            'username'  => $session->get('username'),
            'roles'     => $session->get('roles') ?? [],
        ];

        return view('admin/dashboard', $data);
    }
}
