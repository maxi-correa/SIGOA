<?php

namespace App\Controllers;

use App\Models\UsuarioModel;

class Auth extends BaseController
{
    protected UsuarioModel $usuarioModel;

    public function __construct()
    {
        $this->usuarioModel = new UsuarioModel();
    }

    public function login(): string
    {
        $data = [
            'titulo' => 'Iniciar sesión',
            'error'  => '',
        ];

        return view('auth/login', $data);
    }

    public function attemptLogin()
    {
        $usuario  = $this->request->getPost('usuario');
        $password = $this->request->getPost('password');

        $user = $this->usuarioModel->findByUsuario((string) $usuario);

        if ($user === null || ! password_verify((string) $password, $user->password_hash)) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Credenciales incorrectas.');
        }

        if (! $user->activo) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Credenciales incorrectas.');
        }

        $session = session();

        $session->set([
            'logged_in'  => true,
            'user_id'    => $user->id,
            'user_name'  => $user->nombre . ' ' . $user->apellido,
            'username'   => $user->usuario,
        ]);

        return redirect()->to('/dashboard');
    }

    public function dashboard(): string
    {
        $data = [
            'titulo'    => 'Panel de control',
            'user_name' => session()->get('user_name'),
            'username'  => session()->get('username'),
        ];

        return view('auth/dashboard', $data);
    }

    public function logout()
    {
        $session = session();

        $session->destroy();

        return redirect()->to('/login');
    }
}
