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
        $rolesDatos = ['SUPERADMINISTRADOR', 'ADMINISTRADOR', 'INSPECTOR'];

        $data = [
            'titulo'                 => 'Mis Datos',
            'user_name'              => $session->get('user_name'),
            'username'               => $session->get('username'),
            'roles'                  => $roles,
            'rol_principal'          => $this->getRolPrincipal($roles),
            'dashboard_url'          => $this->getDashboardPath($roles),
            'usuario'                => $usuario,
            'puede_modificar_datos'  => array_intersect($rolesDatos, $roles) !== [],
            'puede_modificar_email'  => array_intersect($rolesEmail, $roles) !== [],
        ];

        return view('mis_datos/index', $data);
    }

    /**
     * Modifica el nombre del usuario autenticado.
     *
     * SUPERADMINISTRADOR, ADMINISTRADOR e INSPECTOR. CONSULTA no tiene
     * ruta disponible: el filtro de rol lo rechaza en backend.
     */
    public function updateNombre()
    {
        return $this->actualizarDatoPersonal('nombre');
    }

    /**
     * Modifica el apellido del usuario autenticado.
     *
     * SUPERADMINISTRADOR, ADMINISTRADOR e INSPECTOR. CONSULTA no tiene
     * ruta disponible: el filtro de rol lo rechaza en backend.
     */
    public function updateApellido()
    {
        return $this->actualizarDatoPersonal('apellido');
    }

    /**
     * Modifica el correo electrónico del usuario autenticado.
     *
     * SUPERADMINISTRADOR, ADMINISTRADOR e INSPECTOR.
     */
    public function updateEmail()
    {
        return $this->actualizarDatoPersonal('email');
    }

    /**
     * Actualiza un dato personal propio (nombre, apellido o email).
     *
     * La columna a modificar se define internamente por cada endpoint:
     * nunca se toma del formulario. El usuario se identifica únicamente
     * mediante session('user_id').
     */
    private function actualizarDatoPersonal(string $campo)
    {
        $session = session();

        $usuario = $this->obtenerUsuarioAutenticado();

        if ($usuario === null) {
            $session->destroy();

            return redirect()->to('/login');
        }

        $roles = $session->get('roles') ?? [];

        if (array_intersect(['SUPERADMINISTRADOR', 'ADMINISTRADOR', 'INSPECTOR'], $roles) === []) {
            return redirect()->to('/mis-datos')
                ->with('error', 'No tiene autorización para modificar sus datos personales.');
        }

        $valor = trim((string) $this->request->getPost('valor'));

        $error = $this->validarDatoPersonal($campo, $valor);

        if ($error !== null) {
            return redirect()->to('/mis-datos')
                ->withInput()
                ->with('error', $error)
                ->with('reabrir_dato', $campo);
        }

        $aGuardar = $valor;

        if ($campo === 'email') {
            $aGuardar = ($valor === '') ? null : $valor;
        } else {
            $aGuardar = mb_strtoupper($valor);
        }

        if (! $this->usuarioModel->actualizarDatosPersonales((int) $usuario->id, [$campo => $aGuardar])) {
            return redirect()->to('/mis-datos')
                ->with('error', 'No fue posible guardar la información.');
        }

        /* El nombre visible en la topbar se deriva de nombre + apellido */
        if ($campo === 'nombre' || $campo === 'apellido') {
            $actualizado = $this->usuarioModel->find((int) $usuario->id);

            if ($actualizado !== null) {
                $session->set('user_name', $actualizado->nombre . ' ' . $actualizado->apellido);
            }
        }

        $mensajes = [
            'nombre'   => 'Su nombre fue actualizado correctamente.',
            'apellido' => 'Su apellido fue actualizado correctamente.',
            'email'    => 'El correo electrónico fue actualizado correctamente.',
        ];

        return redirect()->to('/mis-datos')
            ->with('success', $mensajes[$campo]);
    }

    /**
     * Valida un dato personal enviado desde Mis Datos.
     *
     * Devuelve el mensaje de error o null si el valor es válido.
     */
    private function validarDatoPersonal(string $campo, string $valor): ?string
    {
        if ($campo === 'email') {
            if ($valor === '') {
                return 'Debe ingresar un correo electrónico.';
            }

            if (strlen($valor) > 150 || ! filter_var($valor, FILTER_VALIDATE_EMAIL)) {
                return 'El correo electrónico ingresado no es válido.';
            }

            return null;
        }

        $etiqueta = $campo === 'apellido' ? 'El apellido' : 'El nombre';

        if ($valor === '') {
            return $etiqueta . ' es obligatorio.';
        }

        if (mb_strlen($valor) > 100) {
            return $etiqueta . ' no puede superar los 100 caracteres.';
        }

        return null;
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

        /* --- 0. Verificar que la contraseña actual haya sido validada previamente --- */
        if (! $session->get('contrasena_verificada')) {
            return redirect()->to('/mis-datos')
                ->with('error', 'Debe verificar su contraseña actual antes de cambiarla.');
        }

        $nueva     = (string) $this->request->getPost('nueva_contrasena');
        $confirmar = (string) $this->request->getPost('confirmar_contrasena');

        /* --- 1. Campos obligatorios --- */
        if ($nueva === '' || $confirmar === '') {
            return redirect()->back()
                ->with('error', 'Debe completar todos los campos del formulario.')
                ->with('reabrir_cambiar', true);
        }

        /* --- 2. Reglas de la nueva contraseña (mismas que el login) --- */
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

        /* --- 3. Generar hash y persistir --- */
        $hash = password_hash($nueva, PASSWORD_DEFAULT);

        if (! $this->usuarioModel->updatePassword((int) $usuario->id, $hash)) {
            return redirect()->to('/mis-datos')
                ->with('error', 'No fue posible guardar la información.');
        }

        $session->remove('contrasena_verificada');

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

        $session = session();
        $session->set('contrasena_verificada', true);

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