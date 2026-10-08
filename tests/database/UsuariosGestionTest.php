<?php

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Gestión de usuarios y edición de datos personales.
 *
 * Cubre el alta (SUPERADMINISTRADOR y ADMINISTRADOR; este último no puede
 * asignar el rol SUPERADMINISTRADOR), la edición administrativa
 * (SUPERADMINISTRADOR/ADMINISTRADOR), la autorización por rol en ambas
 * pantallas y la edición de nombre/apellido/email en Mis Datos
 * (SUPERADMINISTRADOR, ADMINISTRADOR e INSPECTOR; CONSULTA sin acceso).
 *
 * Usa la conexión `tests` (SQLite en memoria) y crea únicamente las
 * tablas que el flujo necesita: usuarios, roles y usuarios_roles.
 *
 * @internal
 */
final class UsuariosGestionTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private BaseConnection $conn;

    private int $superadminId;

    private int $adminId;

    private int $inspectorId;

    private int $consultaId;

    private int $objetivoId;

    private int $rolInspectorId;

    private int $rolSuperadminId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conn = db_connect();

        $this->crearTablas();
        $this->limpiarTablas();
        $this->sembrarRoles();
        $this->sembrarUsuarios();
    }

    protected function tearDown(): void
    {
        $this->limpiarTablas();

        parent::tearDown();
    }

    /* ================================================================
       ESQUEMA Y DATOS DE PRUEBA
       ================================================================ */

    private function tabla(string $nombre): string
    {
        return $this->conn->prefixTable($nombre);
    }

    private function crearTablas(): void
    {
        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('usuarios') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre VARCHAR(100) NULL,
            apellido VARCHAR(100) NULL,
            usuario VARCHAR(100) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            email VARCHAR(150) NULL,
            activo TINYINT NOT NULL DEFAULT 1,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('roles') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre VARCHAR(60) NOT NULL,
            activo TINYINT NOT NULL DEFAULT 1
        )');

        $this->conn->query('CREATE TABLE IF NOT EXISTS ' . $this->tabla('usuarios_roles') . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            usuario_id INTEGER NOT NULL,
            rol_id INTEGER NOT NULL,
            created_at DATETIME NULL
        )');
    }

    private function limpiarTablas(): void
    {
        foreach (['usuarios_roles', 'usuarios', 'roles'] as $tabla) {
            if ($this->conn->tableExists($tabla)) {
                $this->conn->query('DELETE FROM ' . $this->tabla($tabla));
            }
        }
    }

    private function sembrarRoles(): void
    {
        $ids = [];

        foreach (['SUPERADMINISTRADOR', 'ADMINISTRADOR', 'INSPECTOR', 'CONSULTA'] as $nombre) {
            $this->conn->table('roles')->insert([
                'nombre' => $nombre,
                'activo' => 1,
            ]);
            $ids[$nombre] = (int) $this->conn->insertID();
        }

        $this->rolInspectorId  = $ids['INSPECTOR'];
        $this->rolSuperadminId = $ids['SUPERADMINISTRADOR'];
    }

    private function sembrarUsuarios(): void
    {
        $this->superadminId  = $this->insertarUsuario('super.prueba', 'SUPERADMINISTRADOR');
        $this->adminId       = $this->insertarUsuario('admin.prueba', 'ADMINISTRADOR');
        $this->inspectorId   = $this->insertarUsuario('inspector.prueba', 'INSPECTOR');
        $this->consultaId    = $this->insertarUsuario('consulta.prueba', 'CONSULTA');
        $this->objetivoId    = $this->insertarUsuario('objetivo', 'INSPECTOR');
    }

    private function insertarUsuario(string $usuario, string $rol): int
    {
        $datos = [
            'nombre'        => 'NOMBRE',
            'apellido'      => 'APELLIDO',
            'usuario'       => $usuario,
            'password_hash' => password_hash('Objetivo123', PASSWORD_DEFAULT),
            'email'         => $usuario . '@correo.com',
            'activo'        => 1,
            'created_at'    => date('Y-m-d H:i:s'),
        ];

        if ($usuario === 'inspector.prueba') {
            $datos['nombre']   = 'ANA';
            $datos['apellido'] = 'GARCIA';
        }

        $this->conn->table('usuarios')->insert($datos);
        $usuarioId = (int) $this->conn->insertID();

        $this->conn->table('usuarios_roles')->insert([
            'usuario_id' => $usuarioId,
            'rol_id'     => $this->idRol($rol),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $usuarioId;
    }

    private function idRol(string $nombre): int
    {
        $rol = $this->conn->table('roles')->where('nombre', $nombre)->get()->getRow();

        return (int) $rol->id;
    }

    private function sesion(string $rol, int $userId): array
    {
        return [
            'logged_in' => true,
            'activo'    => true,
            'user_id'   => $userId,
            'username'  => 'prueba.' . strtolower($rol),
            'user_name' => 'PRUEBA ' . $rol,
            'roles'     => [$rol],
        ];
    }

    private function contarUsuarios(): int
    {
        return (int) $this->conn->table('usuarios')->countAllResults();
    }

    private function usuarioPorLogin(string $usuario): ?object
    {
        return $this->conn->table('usuarios')->where('usuario', $usuario)->get()->getRow();
    }

    /* ================================================================
       ALTA DE USUARIOS
       ================================================================ */

    public function testAltaDeUsuarioCreaRegistroConRolYHash(): void
    {
        $antes = $this->contarUsuarios();

        $resultado = $this->withSession($this->sesion('SUPERADMINISTRADOR', $this->superadminId))
            ->post('/usuarios/crear', [
                'nombre'            => 'Juan',
                'apellido'          => 'Perez',
                'usuario'           => 'JPerez',
                'email'             => 'juan@correo.com',
                'rol_id'            => $this->rolInspectorId,
                'password'          => 'Secreta123',
                'confirmar_password' => 'Secreta123',
                'csrf_test_name'    => csrf_hash(),
            ]);

        $resultado->assertRedirectTo('/usuarios');
        $this->assertSame($antes + 1, $this->contarUsuarios());

        $creado = $this->usuarioPorLogin('jperez');

        $this->assertNotNull($creado);
        $this->assertSame('JUAN', $creado->nombre);
        $this->assertSame('PEREZ', $creado->apellido);
        $this->assertSame('juan@correo.com', $creado->email);
        $this->assertSame(1, (int) $creado->activo);
        $this->assertNotSame('Secreta123', $creado->password_hash);
        $this->assertTrue(password_verify('Secreta123', $creado->password_hash));

        $rolAsignado = $this->conn->table('usuarios_roles')
            ->where('usuario_id', (int) $creado->id)
            ->get()->getRow();

        $this->assertNotNull($rolAsignado);
        $this->assertSame($this->rolInspectorId, (int) $rolAsignado->rol_id);
    }

    public function testAltaNoAdmiteUsuarioDuplicado(): void
    {
        $antes = $this->contarUsuarios();

        $resultado = $this->withSession($this->sesion('SUPERADMINISTRADOR', $this->superadminId))
            ->post('/usuarios/crear', [
                'nombre'            => 'Otro',
                'apellido'          => 'Usuario',
                'usuario'           => 'OBJETIVO',
                'rol_id'            => $this->rolInspectorId,
                'password'          => 'Secreta123',
                'confirmar_password' => 'Secreta123',
                'csrf_test_name'    => csrf_hash(),
            ]);

        $resultado->assertRedirectTo('/usuarios');
        $this->assertSame($antes, $this->contarUsuarios());
    }

    public function testAltaRechazaContrasenasQueNoCoinciden(): void
    {
        $antes = $this->contarUsuarios();

        $resultado = $this->withSession($this->sesion('SUPERADMINISTRADOR', $this->superadminId))
            ->post('/usuarios/crear', [
                'nombre'            => 'Otro',
                'apellido'          => 'Usuario',
                'usuario'           => 'otro.usuario',
                'rol_id'            => $this->rolInspectorId,
                'password'          => 'Secreta123',
                'confirmar_password' => 'Distinta123',
                'csrf_test_name'    => csrf_hash(),
            ]);

        $resultado->assertRedirectTo('/usuarios');
        $this->assertSame($antes, $this->contarUsuarios());
    }

    public function testAltaRechazaContrasenaDebil(): void
    {
        $antes = $this->contarUsuarios();

        $resultado = $this->withSession($this->sesion('SUPERADMINISTRADOR', $this->superadminId))
            ->post('/usuarios/crear', [
                'nombre'            => 'Otro',
                'apellido'          => 'Usuario',
                'usuario'           => 'otro.usuario',
                'rol_id'            => $this->rolInspectorId,
                'password'          => 'corta1',
                'confirmar_password' => 'corta1',
                'csrf_test_name'    => csrf_hash(),
            ]);

        $resultado->assertRedirectTo('/usuarios');
        $this->assertSame($antes, $this->contarUsuarios());
    }

    public function testAltaRechazaEmailInvalido(): void
    {
        $antes = $this->contarUsuarios();

        $resultado = $this->withSession($this->sesion('SUPERADMINISTRADOR', $this->superadminId))
            ->post('/usuarios/crear', [
                'nombre'            => 'Otro',
                'apellido'          => 'Usuario',
                'usuario'           => 'otro.usuario',
                'email'             => 'no-es-email',
                'rol_id'            => $this->rolInspectorId,
                'password'          => 'Secreta123',
                'confirmar_password' => 'Secreta123',
                'csrf_test_name'    => csrf_hash(),
            ]);

        $resultado->assertRedirectTo('/usuarios');
        $this->assertSame($antes, $this->contarUsuarios());
    }

    /* ================================================================
       EDICIÓN ADMINISTRATIVA
       ================================================================ */

    public function testEdicionActualizaDatosPersonalesSinTocarLoginRolNiContrasena(): void
    {
        $objetivo = $this->usuarioPorLogin('objetivo');
        $hashPrevio = $objetivo->password_hash;

        $resultado = $this->withSession($this->sesion('ADMINISTRADOR', $this->adminId))
            ->post('/usuarios/actualizar', [
                'usuario_id'  => $this->objetivoId,
                'nombre'      => 'modificado',
                'apellido'    => 'tambien',
                'email'       => 'nuevo@correo.com',
                'activo'      => '1',
                'csrf_test_name' => csrf_hash(),
            ]);

        $resultado->assertRedirectTo('/usuarios');

        $editado = $this->usuarioPorLogin('objetivo');

        $this->assertSame('MODIFICADO', $editado->nombre);
        $this->assertSame('TAMBIEN', $editado->apellido);
        $this->assertSame('nuevo@correo.com', $editado->email);
        $this->assertSame(1, (int) $editado->activo);
        $this->assertSame('objetivo', $editado->usuario);
        $this->assertSame($hashPrevio, $editado->password_hash);

        $rolAsignado = $this->conn->table('usuarios_roles')
            ->where('usuario_id', $this->objetivoId)
            ->get()->getRow();

        $this->assertNotNull($rolAsignado);
        $this->assertSame($this->rolInspectorId, (int) $rolAsignado->rol_id);
    }

    public function testEdicionNoPermiteModificarElPropioRegistro(): void
    {
        $admin = $this->usuarioPorLogin('admin.prueba');
        $nombrePrevio = $admin->nombre;

        $resultado = $this->withSession($this->sesion('ADMINISTRADOR', $this->adminId))
            ->post('/usuarios/actualizar', [
                'usuario_id'     => $this->adminId,
                'nombre'         => 'cambiado',
                'apellido'       => 'cambiado',
                'activo'         => '0',
                'csrf_test_name' => csrf_hash(),
            ]);

        $resultado->assertRedirectTo('/usuarios');

        $sinCambios = $this->usuarioPorLogin('admin.prueba');

        $this->assertSame($nombrePrevio, $sinCambios->nombre);
        $this->assertSame(1, (int) $sinCambios->activo);
    }

    public function testAdministradorNoPuedeInactivarUsuarios(): void
    {
        $resultado = $this->withSession($this->sesion('ADMINISTRADOR', $this->adminId))
            ->post('/usuarios/actualizar', [
                'usuario_id'     => $this->objetivoId,
                'nombre'         => 'modificado',
                'apellido'       => 'tambien',
                'activo'         => '0',
                'csrf_test_name' => csrf_hash(),
            ]);

        $resultado->assertRedirectTo('/usuarios');
        $resultado->assertSessionHas('error', 'No tiene autorización para inactivar usuarios.');

        $sinCambios = $this->usuarioPorLogin('objetivo');

        $this->assertSame('NOMBRE', $sinCambios->nombre);
        $this->assertSame(1, (int) $sinCambios->activo);
    }

    public function testAdministradorPuedeCrearUsuarioConRolPermitido(): void
    {
        $antes = $this->contarUsuarios();

        $resultado = $this->withSession($this->sesion('ADMINISTRADOR', $this->adminId))
            ->post('/usuarios/crear', [
                'nombre'             => 'Laura',
                'apellido'           => 'Gomez',
                'usuario'            => 'lgomez',
                'email'              => 'laura@correo.com',
                'rol_id'             => $this->rolInspectorId,
                'password'           => 'Secreta123',
                'confirmar_password' => 'Secreta123',
                'csrf_test_name'     => csrf_hash(),
            ]);

        $resultado->assertRedirectTo('/usuarios');
        $this->assertSame($antes + 1, $this->contarUsuarios());

        $creado = $this->usuarioPorLogin('lgomez');

        $this->assertNotNull($creado);
        $this->assertSame('LAURA', $creado->nombre);
        $this->assertSame('GOMEZ', $creado->apellido);
        $this->assertSame('laura@correo.com', $creado->email);
        $this->assertSame(1, (int) $creado->activo);
        $this->assertTrue(password_verify('Secreta123', $creado->password_hash));

        $rolAsignado = $this->conn->table('usuarios_roles')
            ->where('usuario_id', (int) $creado->id)
            ->get()->getRow();

        $this->assertNotNull($rolAsignado);
        $this->assertSame($this->rolInspectorId, (int) $rolAsignado->rol_id);
    }

    public function testAdministradorNoPuedeCrearUsuarioConRolSuperadministrador(): void
    {
        $antes = $this->contarUsuarios();

        $resultado = $this->withSession($this->sesion('ADMINISTRADOR', $this->adminId))
            ->post('/usuarios/crear', [
                'nombre'             => 'Intento',
                'apellido'           => 'Fraude',
                'usuario'            => 'intentofraude',
                'rol_id'             => $this->rolSuperadminId,
                'password'           => 'Secreta123',
                'confirmar_password' => 'Secreta123',
                'csrf_test_name'     => csrf_hash(),
            ]);

        $resultado->assertRedirectTo('/usuarios');
        $this->assertSame($antes, $this->contarUsuarios());
        $this->assertNull($this->usuarioPorLogin('intentofraude'));
    }

    /* ================================================================
       AUTORIZACIÓN POR ROL
       ================================================================ */

    public function testRolConsultaNoPuedeEditarUsuarios(): void
    {
        $objetivo = $this->usuarioPorLogin('objetivo');
        $nombrePrevio = $objetivo->nombre;

        $resultado = $this->withSession($this->sesion('CONSULTA', $this->consultaId))
            ->post('/usuarios/actualizar', [
                'usuario_id'     => $this->objetivoId,
                'nombre'         => 'intruso',
                'apellido'       => 'intruso',
                'activo'         => '0',
                'csrf_test_name' => csrf_hash(),
            ]);

        $resultado->assertRedirectTo('/dashboard');

        $sinCambios = $this->usuarioPorLogin('objetivo');
        $this->assertSame($nombrePrevio, $sinCambios->nombre);
        $this->assertSame(1, (int) $sinCambios->activo);
    }

    public function testRolInspectorNoPuedeAccederAGestionDeUsuarios(): void
    {
        $resultado = $this->withSession($this->sesion('INSPECTOR', $this->inspectorId))
            ->get('/usuarios');

        $resultado->assertRedirectTo('/dashboard');
    }

    public function testBotonDeAltaVisibleParaGestoresConRolesFiltrados(): void
    {
        $comoSuperadmin = $this->withSession($this->sesion('SUPERADMINISTRADOR', $this->superadminId))
            ->get('/usuarios');

        $comoSuperadmin->assertStatus(200);
        $comoSuperadmin->assertSeeElement('button#btnAgregarUsuario');
        $comoSuperadmin->assertSee('data-accion="editar"');
        $comoSuperadmin->assertSee('Mis Datos');

        $comoSuperadmin->assertSeeElement('select#alta_rol_id');
        $comoSuperadmin->assertSee('SUPERADMINISTRADOR', 'select#alta_rol_id');
        $comoSuperadmin->assertSee('ADMINISTRADOR', 'select#alta_rol_id');
        $comoSuperadmin->assertSee('INSPECTOR', 'select#alta_rol_id');
        $comoSuperadmin->assertSee('CONSULTA', 'select#alta_rol_id');

        $comoAdmin = $this->withSession($this->sesion('ADMINISTRADOR', $this->adminId))
            ->get('/usuarios');

        $comoAdmin->assertStatus(200);
        $comoAdmin->assertSeeElement('button#btnAgregarUsuario');
        $comoAdmin->assertSee('data-accion="editar"');

        $comoAdmin->assertSeeElement('select#alta_rol_id');
        $comoAdmin->assertDontSee('SUPERADMINISTRADOR', 'select#alta_rol_id');
        $comoAdmin->assertSee('ADMINISTRADOR', 'select#alta_rol_id');
        $comoAdmin->assertSee('INSPECTOR', 'select#alta_rol_id');
        $comoAdmin->assertSee('CONSULTA', 'select#alta_rol_id');
    }

    /* ================================================================
       MIS DATOS — EDICIÓN DE DATOS PERSONALES PROPIOS
       ================================================================ */

    public function testInspectorPuedeActualizarSuNombreEnMisDatos(): void
    {
        $resultado = $this->withSession($this->sesion('INSPECTOR', $this->inspectorId))
            ->post('/mis-datos/nombre', [
                'valor'          => 'maria',
                'csrf_test_name' => csrf_hash(),
            ]);

        $resultado->assertRedirectTo('/mis-datos');

        $inspector = $this->usuarioPorLogin('inspector.prueba');
        $this->assertSame('MARIA', $inspector->nombre);
        $this->assertSame('GARCIA', $inspector->apellido);

        $this->assertSame('MARIA GARCIA', session()->get('user_name'));
    }

    public function testRolConsultaNoPuedeActualizarDatosPersonalesEnMisDatos(): void
    {
        $consulta = $this->usuarioPorLogin('consulta.prueba');
        $nombrePrevio = $consulta->nombre;

        $resultado = $this->withSession($this->sesion('CONSULTA', $this->consultaId))
            ->post('/mis-datos/nombre', [
                'valor'          => 'intruso',
                'csrf_test_name' => csrf_hash(),
            ]);

        $resultado->assertRedirectTo('/dashboard');

        $sinCambios = $this->usuarioPorLogin('consulta.prueba');
        $this->assertSame($nombrePrevio, $sinCambios->nombre);
    }

    public function testEmailInvalidoEnMisDatosNoSeGuarda(): void
    {
        $inspector = $this->usuarioPorLogin('inspector.prueba');
        $emailPrevio = $inspector->email;

        $resultado = $this->withSession($this->sesion('INSPECTOR', $this->inspectorId))
            ->post('/mis-datos/email', [
                'valor'          => 'no-es-email',
                'csrf_test_name' => csrf_hash(),
            ]);

        $resultado->assertRedirectTo('/mis-datos');

        $sinCambios = $this->usuarioPorLogin('inspector.prueba');
        $this->assertSame($emailPrevio, $sinCambios->email);
    }

    public function testNombreVacioEnMisDatosNoSeGuarda(): void
    {
        $inspector = $this->usuarioPorLogin('inspector.prueba');
        $nombrePrevio = $inspector->nombre;

        $resultado = $this->withSession($this->sesion('INSPECTOR', $this->inspectorId))
            ->post('/mis-datos/nombre', [
                'valor'          => '   ',
                'csrf_test_name' => csrf_hash(),
            ]);

        $resultado->assertRedirectTo('/mis-datos');

        $sinCambios = $this->usuarioPorLogin('inspector.prueba');
        $this->assertSame($nombrePrevio, $sinCambios->nombre);
    }
}
