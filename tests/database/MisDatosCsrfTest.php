<?php

use App\Models\UsuarioModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * CSRF en Mis Datos (regresión del bug "Cambiar contraseña después de
 * Verificar contraseña").
 *
 * Con `regenerate = true`, la petición de verificación que valida el CSRF
 * rota el token: el campo oculto del formulario de cambio de contraseña
 * quedaba con el token anterior y el POST nativo de cambio era rechazado con
 * "Acción no permitida". La corrección refresca ese campo con el token de la
 * cookie vigente antes del envío (mecanismo de Fase B).
 *
 * La suite usa la conexión `tests` (SQLite en memoria). Aquí se crea solo el
 * esquema mínimo de la tabla `usuarios`.
 *
 * @internal
 */
final class MisDatosCsrfTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private BaseConnection $conn;

    private int $usuarioId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conn = db_connect();

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

        $this->conn->query('DELETE FROM ' . $this->tabla('usuarios'));

        $this->conn->table('usuarios')->insert([
            'usuario'       => 'PRUEBA_CSRF',
            'password_hash' => password_hash('Activa123', PASSWORD_DEFAULT),
            'activo'        => 1,
        ]);

        $this->usuarioId = (int) $this->conn->insertID();
    }

    protected function tearDown(): void
    {
        $this->conn->query('DELETE FROM ' . $this->tabla('usuarios'));

        parent::tearDown();
    }

    private function tabla(string $nombre): string
    {
        return $this->conn->prefixTable($nombre);
    }

    private function sesionAutenticada(bool $contrasenaVerificada = false): array
    {
        $sesion = [
            'logged_in' => true,
            'activo'    => true,
            'user_id'   => $this->usuarioId,
            'username'  => 'PRUEBA_CSRF',
            'user_name' => 'PRUEBA_CSRF',
            'roles'     => ['CONSULTA'],
        ];

        if ($contrasenaVerificada) {
            $sesion['contrasena_verificada'] = true;
        }

        return $sesion;
    }

    public function testVerificarContrasenaConTokenValidoDevuelveExito(): void
    {
        $token = csrf_hash();

        $resultado = $this->withSession($this->sesionAutenticada())
            ->post('/mis-datos/verificar-password', [
                'password'       => 'Activa123',
                'csrf_test_name' => $token,
            ]);

        $resultado->assertStatus(200);
        $resultado->assertJSONFragment(['success' => true]);
    }

    public function testVerificarContrasenaIncorrectaSigueRechazada(): void
    {
        $token = csrf_hash();

        $resultado = $this->withSession($this->sesionAutenticada())
            ->post('/mis-datos/verificar-password', [
                'password'       => 'Incorrecta9',
                'csrf_test_name' => $token,
            ]);

        $resultado->assertJSONFragment(['success' => false]);
    }

    public function testCambiarContrasenaTrasVerificarUsaElTokenVigente(): void
    {
        $tokenVerificar = csrf_hash();

        $this->withSession($this->sesionAutenticada())
            ->post('/mis-datos/verificar-password', [
                'password'       => 'Activa123',
                'csrf_test_name' => $tokenVerificar,
            ]);

        $tokenCambio = csrf_hash();
        $this->assertNotSame($tokenVerificar, $tokenCambio);

        $resultado = $this->withSession($this->sesionAutenticada(true))
            ->post('/mis-datos/password', [
                'csrf_test_name'       => $tokenCambio,
                'nueva_contrasena'     => 'NuevaClave1',
                'confirmar_contrasena' => 'NuevaClave1',
            ]);

        $resultado->assertRedirectTo('/mis-datos');

        $hashAlmacenado = (new UsuarioModel())->find($this->usuarioId)->password_hash;
        $this->assertTrue(password_verify('NuevaClave1', $hashAlmacenado));
    }

    public function testCambiarContrasenaSinVerificarSigueRechazada(): void
    {
        $token = csrf_hash();

        $resultado = $this->withSession($this->sesionAutenticada())
            ->post('/mis-datos/password', [
                'csrf_test_name'       => $token,
                'nueva_contrasena'     => 'NuevaClave1',
                'confirmar_contrasena' => 'NuevaClave1',
            ]);

        $resultado->assertRedirectTo('/mis-datos');
        $this->assertTrue(password_verify('Activa123', (new UsuarioModel())->find($this->usuarioId)->password_hash));
    }

    public function testTokenAnteriorALaRegeneracionEsRechazado(): void
    {
        $tokenVerificar = csrf_hash();

        $this->withSession($this->sesionAutenticada())
            ->post('/mis-datos/verificar-password', [
                'password'       => 'Activa123',
                'csrf_test_name' => $tokenVerificar,
            ]);

        $this->expectException(SecurityException::class);

        $this->withSession($this->sesionAutenticada(true))
            ->post('/mis-datos/password', [
                'csrf_test_name'       => $tokenVerificar,
                'nueva_contrasena'     => 'NuevaClave1',
                'confirmar_contrasena' => 'NuevaClave1',
            ]);
    }

    public function testCambiarContrasenaSinTokenEsRechazado(): void
    {
        $this->expectException(SecurityException::class);

        $this->withSession($this->sesionAutenticada(true))
            ->post('/mis-datos/password', [
                'nueva_contrasena'     => 'NuevaClave1',
                'confirmar_contrasena' => 'NuevaClave1',
            ]);
    }

    public function testCambiarContrasenaConTokenInvalidoEsRechazado(): void
    {
        $this->expectException(SecurityException::class);

        $this->withSession($this->sesionAutenticada(true))
            ->post('/mis-datos/password', [
                'csrf_test_name'       => 'token-invalido',
                'nueva_contrasena'     => 'NuevaClave1',
                'confirmar_contrasena' => 'NuevaClave1',
            ]);
    }
}