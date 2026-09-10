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
        ];

        return view('auth/login', $data);
    }

    public function attemptLogin()
    {
        $usuario  = trim((string) $this->request->getPost('usuario'));
        $password = (string) $this->request->getPost('password');

        /* --- 1. Campos obligatorios --- */
        if ($usuario === '' && $password === '') {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Debe completar el usuario y la contraseña.');
        }

        if ($usuario === '') {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Falta ingresar el usuario.');
        }

        if ($password === '') {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Falta completar la contraseña.');
        }

        /* --- 2. Reglas de contraseña --- */
        if (strlen($password) < 9) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'La contraseña debe tener al menos 9 caracteres.');
        }

        if (! preg_match('/[A-Z]/', $password)) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'La contraseña debe contener al menos una letra mayúscula.');
        }

        if (! preg_match('/[0-9]/', $password)) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'La contraseña debe contener al menos un número.');
        }

        /* --- 3. Autenticación --- */
        $user = $this->usuarioModel->findByUsuario($usuario);

        if ($user === null || ! password_verify($password, $user->password_hash)) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Los datos ingresados no son correctos.');
        }

        if (! $user->activo) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Los datos ingresados no son correctos.');
        }

        /* --- 4. Sesión --- */
        $session = session();

        $session->set([
            'logged_in'  => true,
            'user_id'    => $user->id,
            'user_name'  => $user->nombre . ' ' . $user->apellido,
            'username'   => $user->usuario,
        ]);

        /* --- 5. Recordar usuario --- */
        if ($this->request->getPost('remember_user')) {
            $this->response->setCookie([
                'name'     => 'sigoa_remember_user',
                'value'    => $user->usuario,
                'expire'   => 30 * 86400,
                'path'     => '/',
                'secure'   => false,
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        } elseif ($this->request->getCookie('sigoa_remember_user') !== null) {
            $this->response->deleteCookie('sigoa_remember_user');
        }

        return redirect()->to('/dashboard')->withCookies();
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
