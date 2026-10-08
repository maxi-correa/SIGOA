<?php

namespace App\Controllers;

use App\Models\UsuarioModel;

/**
 * Gestión de usuarios: listado, edición administrativa y alta.
 *
 * Accesible solo para SUPERADMINISTRADOR y ADMINISTRADOR. El alta de
 * usuarios queda restringido a SUPERADMINISTRADOR.
 *
 * La autorización principal se controla mediante el filtro RoleFilter en
 * las rutas; cada método repite la verificación de roles como defensa en
 * profundidad. El usuario autenticado no puede editarse a sí mismo desde
 * esta pantalla: para sus datos personales utiliza Mis Datos.
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

        $puedeAgregar = in_array('SUPERADMINISTRADOR', $roles, true);

        $data = [
            'titulo'          => 'Gestión de usuarios',
            'user_name'       => $session->get('user_name'),
            'username'        => $session->get('username'),
            'roles'           => $roles,
            'usuarios'        => $this->usuarioModel->findAllConRoles(),
            'user_id'         => $session->get('user_id'),
            'puede_agregar'   => $puedeAgregar,
            'roles_disponibles' => $puedeAgregar
                ? $this->usuarioModel->findRolesActivos()
                : [],
        ];

        return view('usuarios/index', $data);
    }

    /**
     * Edición administrativa de un usuario: nombre, apellido, email y
     * estado Activo/Inactivo.
     *
     * No permite modificar usuario/login, rol ni contraseña: esos campos
     * no se leen del formulario. El usuario autenticado no puede editar su
     * propio registro desde aquí, evitando que un administrador se
     * desactive accidentalmente a sí mismo.
     */
    public function actualizar()
    {
        $session = session();

        if (! $this->esGestor()) {
            return redirect()->to('/usuarios')
                ->with('error', 'No tiene autorización para modificar usuarios.');
        }

        $usuarioId = (int) $this->request->getPost('usuario_id');
        $usuario   = $this->usuarioModel->find($usuarioId);

        if ($usuario === null) {
            return redirect()->to('/usuarios')
                ->with('error', 'El usuario seleccionado no existe.');
        }

        if ($usuarioId === (int) $session->get('user_id')) {
            return redirect()->to('/usuarios')
                ->with('error', 'No puede modificar su propio usuario desde esta pantalla. Utilice Mis Datos.');
        }

        // Restricciones para administradores: no pueden desactivar a superadministrador
        $roles = $session->get('roles') ?? [];
        $esSuperadmin = in_array('SUPERADMINISTRADOR', $roles, true);
        if (! $esSuperadmin) {
            $rolesUsuario = $this->usuarioModel->findRolesByUsuarioId($usuarioId);
            if (in_array('SUPERADMINISTRADOR', $rolesUsuario, true)) {
                return redirect()->to('/usuarios')
                    ->with('error', 'No tiene autorización para modificar un usuario con rol SUPERADMINISTRADOR.');
            }
            $datos = $this->tomarDatosEdicion();
            if ($datos['activo'] === 0) {
                return redirect()->to('/usuarios')
                    ->with('error', 'No tiene autorización para inactivar usuarios.');
            }
        } else {
            $datos = $this->tomarDatosEdicion();
        }

        $errores = $this->validarDatosPersonales($datos);

        if ($errores !== []) {
            return redirect()->to('/usuarios')
                ->withInput()
                ->with('errores_usuario', $errores)
                ->with('reabrir_modal_usuario', 'edicion');
        }

        $actualizado = $this->usuarioModel->actualizarDatosPersonales($usuarioId, [
            'nombre'  => $datos['nombre'],
            'apellido' => $datos['apellido'],
            'email'   => $datos['email'],
            'activo'  => $datos['activo'],
        ]);

        return redirect()->to('/usuarios')
            ->with('success', 'Los datos del usuario fueron actualizados correctamente.');
    }

    /**
     * Alta de un usuario con un rol asignado.
     *
     * Disponible únicamente para SUPERADMINISTRADOR (filtro de ruta +
     * verificación aquí). La contraseña se almacena únicamente como hash
     * y el usuario nace Activo.
     */
    public function crear()
    {
        $session = session();

        if (! in_array('SUPERADMINISTRADOR', $session->get('roles') ?? [], true)) {
            return redirect()->to('/usuarios')
                ->with('error', 'No tiene autorización para crear usuarios.');
        }

        $rolesDisponibles = $this->usuarioModel->findRolesActivos();
        $datos            = $this->tomarDatosAlta();
        $errores          = $this->validarDatosAlta($datos, $rolesDisponibles);

        if ($errores !== []) {
            return redirect()->to('/usuarios')
                ->withInput()
                ->with('errores_usuario', $errores)
                ->with('reabrir_modal_usuario', 'alta');
        }

        $rolId = 0;

        foreach ($rolesDisponibles as $rol) {
            if ((int) $rol->id === $datos['rol_id']) {
                $rolId = (int) $rol->id;
                break;
            }
        }

        $ahora = date('Y-m-d H:i:s');

        $usuarioId = $this->usuarioModel->crearConRol([
            'nombre'        => $datos['nombre'],
            'apellido'      => $datos['apellido'],
            'usuario'       => $datos['usuario'],
            'password_hash' => password_hash($datos['password'], PASSWORD_DEFAULT),
            'email'         => $datos['email'],
            'activo'        => 1,
            'created_at'    => $ahora,
            'updated_at'    => $ahora,
        ], $rolId);

        if ($usuarioId === null) {
            return redirect()->to('/usuarios')
                ->withInput()
                ->with('errores_usuario', ['No fue posible registrar el usuario. Verifique que el usuario no esté duplicado.'])
                ->with('reabrir_modal_usuario', 'alta');
        }

        return redirect()->to('/usuarios')
            ->with('success', 'El usuario fue registrado correctamente.');
    }

    /* ================================================================
       MÉTODOS PRIVADOS
       ================================================================ */

    /**
     * Indica si el usuario autenticado puede gestionar usuarios.
     */
    private function esGestor(): bool
    {
        $roles = session()->get('roles') ?? [];

        return array_intersect(['SUPERADMINISTRADOR', 'ADMINISTRADOR'], $roles) !== [];
    }

    /**
     * Toma y normaliza los datos del formulario de edición.
     *
     * Solo se leen los campos permitidos: usuario, rol y contraseña no
     * pueden provenir de este formulario.
     *
     * @return array<string, mixed>
     */
    private function tomarDatosEdicion(): array
    {
        $nombre   = trim((string) $this->request->getPost('nombre'));
        $apellido = trim((string) $this->request->getPost('apellido'));
        $email    = trim((string) $this->request->getPost('email'));
        $activo   = $this->request->getPost('activo');

        return [
            'nombre'   => mb_strtoupper($nombre),
            'apellido' => mb_strtoupper($apellido),
            'email'    => ($email === '') ? null : $email,
            'activo'   => ((string) $activo === '1') ? 1 : 0,
        ];
    }

    /**
     * Toma y normaliza los datos del formulario de alta.
     *
     * @return array<string, mixed>
     */
    private function tomarDatosAlta(): array
    {
        $nombre    = trim((string) $this->request->getPost('nombre'));
        $apellido  = trim((string) $this->request->getPost('apellido'));
        $usuario   = mb_strtolower(trim((string) $this->request->getPost('usuario')));
        $email     = trim((string) $this->request->getPost('email'));
        $rolId     = (int) $this->request->getPost('rol_id');
        $password  = (string) $this->request->getPost('password');
        $confirmar = (string) $this->request->getPost('confirmar_password');

        return [
            'nombre'          => mb_strtoupper($nombre),
            'apellido'        => mb_strtoupper($apellido),
            'usuario'         => $usuario,
            'email'           => ($email === '') ? null : $email,
            'rol_id'          => $rolId,
            'password'        => $password,
            'confirmar'       => $confirmar,
        ];
    }

    /**
     * Valida los datos personales del formulario de edición.
     *
     * @param array<string, mixed> $datos
     *
     * @return list<string>
     */
    private function validarDatosPersonales(array $datos): array
    {
        $errores = [];

        if ($datos['nombre'] === '') {
            $errores[] = 'El nombre es obligatorio.';
        } elseif (mb_strlen((string) $datos['nombre']) > 100) {
            $errores[] = 'El nombre no puede superar los 100 caracteres.';
        }

        if ($datos['apellido'] === '') {
            $errores[] = 'El apellido es obligatorio.';
        } elseif (mb_strlen((string) $datos['apellido']) > 100) {
            $errores[] = 'El apellido no puede superar los 100 caracteres.';
        }

        if ($datos['email'] !== null) {
            if (strlen($datos['email']) > 150 || ! filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
                $errores[] = 'El correo electrónico ingresado no es válido.';
            }
        }

        return $errores;
    }

    /**
     * Valida los datos del formulario de alta.
     *
     * @param array<string, mixed> $datos
     * @param list<object>         $rolesDisponibles
     *
     * @return list<string>
     */
    private function validarDatosAlta(array $datos, array $rolesDisponibles): array
    {
        $errores = $this->validarDatosPersonales($datos);

        if ($datos['usuario'] === '') {
            $errores[] = 'El usuario es obligatorio.';
        } elseif (mb_strlen($datos['usuario']) > 100) {
            $errores[] = 'El usuario no puede superar los 100 caracteres.';
        } elseif (mb_strlen($datos['usuario']) < 3) {
            $errores[] = 'El usuario debe tener al menos 3 caracteres.';
        } elseif ($this->usuarioModel->existeUsuario($datos['usuario'])) {
            $errores[] = 'Ya existe un usuario con ese nombre de usuario.';
        }

        $rolValido = false;

        foreach ($rolesDisponibles as $rol) {
            if ((int) $rol->id === $datos['rol_id']) {
                $rolValido = true;
                break;
            }
        }

        if (! $rolValido) {
            $errores[] = 'Debe seleccionar un rol válido.';
        }

        $errores = array_merge($errores, $this->validarContrasenas(
            (string) $datos['password'],
            (string) $datos['confirmar']
        ));

        return $errores;
    }

    /**
     * Valida la contraseña de alta con las mismas reglas del sistema
     * (login y cambio de contraseña): mínimo 9 caracteres, una mayúscula
     * y un número, y confirmación coincidente.
     *
     * @return list<string>
     */
    private function validarContrasenas(string $password, string $confirmar): array
    {
        $errores = [];

        if ($password === '') {
            $errores[] = 'La contraseña es obligatoria.';

            return $errores;
        }

        if ($confirmar === '') {
            $errores[] = 'Debe confirmar la contraseña.';

            return $errores;
        }

        if (strlen($password) < 9) {
            $errores[] = 'La contraseña debe tener al menos 9 caracteres.';
        } elseif (! preg_match('/[A-Z]/', $password)) {
            $errores[] = 'La contraseña debe contener al menos una letra mayúscula.';
        } elseif (! preg_match('/[0-9]/', $password)) {
            $errores[] = 'La contraseña debe contener al menos un número.';
        }

        if ($password !== $confirmar) {
            $errores[] = 'La confirmación de la contraseña no coincide.';
        }

        return $errores;
    }
}
