<?php

namespace App\Controllers;

use App\Models\UsuarioModel;

class MisDatos extends BaseController
{
    protected UsuarioModel $usuarioModel;

    public function __construct()
    {
        $this->usuarioModel = new UsuarioModel();
    }

    /**
     * Pantalla "Mis Datos": muestra los datos personales del usuario
     * autenticado.
     */
    public function index()
    {
        $session = session();

        $usuario = $this->obtenerUsuarioAutenticado();

        if ($usuario === null) {
            $session->destroy();

            return redirect()->to('/login');
        }

        $roles = $session->get('roles') ?? [];

        $rolesEmail = ['ADMINISTRADOR', 'SUPERADMINISTRADOR'];

        $data = [
            'titulo'                 => 'Mis Datos',
            'user_name'              => $session->get('user_name'),
            'username'               => $session->get('username'),
            'roles'                  => $roles,
            'rol_principal'          => $this->getRolPrincipal($roles),
            'dashboard_url'          => $this->getDashboardPath($roles),
            'usuario'                => $usuario,
            'puede_modificar_email'  => array_intersect($rolesEmail, $roles) !== [],
        ];

        return view('mis_datos/index', $data);
    }

    /**
     * Modifica el correo electrónico del usuario autenticado.
     *
     * Solamente ADMINISTRADOR y SUPERADMINISTRADOR pueden modificar su email.
     */
    public function updateEmail()
    {
        $session = session();

        $usuario = $this->obtenerUsuarioAutenticado();

        if ($usuario === null) {
            $session->destroy();

            return redirect()->to('/login');
        }

        $roles = $session->get('roles') ?? [];

        $puedeModificar = array_intersect(['ADMINISTRADOR', 'SUPERADMINISTRADOR'], $roles) !== [];

        if (! $puedeModificar) {
            return redirect()->to('/mis-datos')
                ->with('error', 'No tiene autorización para modificar el correo electrónico.');
        }

        $email = trim((string) $this->request->getPost('email'));

        if ($email === '') {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Debe ingresar un correo electrónico.')
                ->with('reabrir_email', true);
        }

        if (strlen($email) > 150 || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'El correo electrónico ingresado no es válido.')
                ->with('reabrir_email', true);
        }

        if (! $this->usuarioModel->updateEmail((int) $usuario->id, $email)) {
            return redirect()->to('/mis-datos')
                ->with('error', 'No fue posible guardar la información.');
        }

        return redirect()->to('/mis-datos')
            ->with('success', 'El correo electrónico fue actualizado correctamente.');
    }

    /**
     * Cambia la contraseña del usuario autenticado.
     *
     * Disponible para todos los roles autenticados. La contraseña nueva se
     * almacena únicamente como hash generado con password_hash().
     */
    public function changePassword()
    {
        $session = session();

        $usuario = $this->obtenerUsuarioAutenticado();

        if ($usuario === null) {
            $session->destroy();

            return redirect()->to('/login');
        }

        $actual     = (string) $this->request->getPost('password_actual');
        $nueva      = (string) $this->request->getPost('nueva_contrasena');
        $confirmar  = (string) $this->request->getPost('confirmar_contrasena');

        /* --- 1. Campos obligatorios --- */
        if ($actual === '' || $nueva === '' || $confirmar === '') {
            return redirect()->back()
                ->with('error', 'Debe completar todos los campos del formulario.')
                ->with('reabrir_cambiar', true);
        }

        /* --- 2. Validar contraseña actual --- */
        if (! password_verify($actual, $usuario->password_hash)) {
            return redirect()->back()
                ->with('error', 'La contraseña actual no es correcta.')
                ->with('reabrir_cambiar', true);
        }

        /* --- 3. Reglas de la nueva contraseña (mismas que el login) --- */
        if (strlen($nueva) < 9) {
            return redirect()->back()
                ->with('error', 'La nueva contraseña debe tener al menos 9 caracteres.')
                ->with('reabrir_cambiar', true);
        }

        if (! preg_match('/[A-Z]/', $nueva)) {
            return redirect()->back()
                ->with('error', 'La nueva contraseña debe contener al menos una letra mayúscula.')
                ->with('reabrir_cambiar', true);
        }

        if (! preg_match('/[0-9]/', $nueva)) {
            return redirect()->back()
                ->with('error', 'La nueva contraseña debe contener al menos un número.')
                ->with('reabrir_cambiar', true);
        }

        if ($nueva !== $confirmar) {
            return redirect()->back()
                ->with('error', 'La confirmación de la nueva contraseña no coincide.')
                ->with('reabrir_cambiar', true);
        }

        if (password_verify($nueva, $usuario->password_hash)) {
            return redirect()->back()
                ->with('error', 'La nueva contraseña debe ser distinta de la actual.')
                ->with('reabrir_cambiar', true);
        }

        /* --- 4. Generar hash y persistir --- */
        $hash = password_hash($nueva, PASSWORD_DEFAULT);

        if (! $this->usuarioModel->updatePassword((int) $usuario->id, $hash)) {
            return redirect()->to('/mis-datos')
                ->with('error', 'No fue posible guardar la información.');
        }

        return redirect()->to('/mis-datos')
            ->with('success', 'Su contraseña fue actualizada correctamente.');
    }

    /**
     * Verifica la contraseña actual del usuario autenticado.
     *
     * Se invoca mediante AJAX desde el modal "Ver contraseña".
     * La contraseña original NO puede recuperarse del hash: este endpoint
     * únicamente confirma que la contraseña ingresada coincide.
     *
     * Respuesta JSON: { success: true } o { success: false, error: string }.
     */
    public function verifyPassword()
    {
        $usuario = $this->obtenerUsuarioAutenticado();

        if ($usuario === null) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON(['success' => false, 'error' => 'Su sesión expiró. Vuelva a iniciar sesión.']);
        }

        $password = (string) $this->request->getPost('password');

        if ($password === '') {
            return $this->response
                ->setJSON(['success' => false, 'error' => 'Debe ingresar su contraseña actual.']);
        }

        if (! password_verify($password, $usuario->password_hash)) {
            return $this->response
                ->setJSON(['success' => false, 'error' => 'La contraseña ingresada no es correcta.']);
        }

        return $this->response->setJSON(['success' => true]);
    }

    /**
     * Obtiene el usuario autenticado actualmente en sesión.
     *
     * Se identifica al usuario mediante session('user_id'). Nunca se confía
     * en un identificador enviado desde el frontend.
     */
    private function obtenerUsuarioAutenticado(): ?object
    {
        $userId = session()->get('user_id');

        if ($userId === null) {
            return null;
        }

        $usuario = $this->usuarioModel->find((int) $userId);

        if ($usuario === null || ! $usuario->activo) {
            return null;
        }

        return $usuario;
    }
}