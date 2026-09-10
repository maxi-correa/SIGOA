<?php

namespace App\Controllers\Inspector;

use App\Controllers\BaseController;

class Dashboard extends BaseController
{
    public function index(): string
    {
        $session = session();

        $data = [
            'titulo'    => 'Panel de Inspector',
            'user_name' => $session->get('user_name'),
            'username'  => $session->get('username'),
            'roles'     => $session->get('roles') ?? [],
        ];

        return view('inspector/dashboard', $data);
    }
}
