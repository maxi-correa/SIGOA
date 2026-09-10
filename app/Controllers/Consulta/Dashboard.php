<?php

namespace App\Controllers\Consulta;

use App\Controllers\BaseController;

class Dashboard extends BaseController
{
    public function index(): string
    {
        $session = session();

        $data = [
            'titulo'    => 'Panel de Consulta',
            'user_name' => $session->get('user_name'),
            'username'  => $session->get('username'),
            'roles'     => $session->get('roles') ?? [],
        ];

        return view('consulta/dashboard', $data);
    }
}
