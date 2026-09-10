<?php

namespace App\Controllers\Superadmin;

use App\Controllers\BaseController;

class Dashboard extends BaseController
{
    public function index(): string
    {
        $session = session();

        $data = [
            'titulo'    => 'Panel de Superadministrador',
            'user_name' => $session->get('user_name'),
            'username'  => $session->get('username'),
            'roles'     => $session->get('roles') ?? [],
        ];

        return view('superadmin/dashboard', $data);
    }
}
