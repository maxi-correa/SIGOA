<?php

namespace App\Controllers\Inspector;

use App\Controllers\BaseController;
use App\Models\InspectoresObrasModel;

class Dashboard extends BaseController
{
    public function index(): string
    {
        $session  = session();
        $usuarioId = (int) $session->get('user_id');

        $obras = (new InspectoresObrasModel())->listarVigentesConObra($usuarioId);

        $data = [
            'titulo'    => 'Mis obras',
            'user_name' => $session->get('user_name'),
            'username'  => $session->get('username'),
            'roles'     => $session->get('roles') ?? [],
            'obras'     => $obras,
        ];

        return view('inspector/dashboard', $data);
    }
}
