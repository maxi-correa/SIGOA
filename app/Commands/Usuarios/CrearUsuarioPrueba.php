<?php

namespace App\Commands\Usuarios;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use Throwable;

/**
 * Comando de desarrollo para crear un usuario de prueba.
 *
 * No forma parte del mecanismo definitivo de administración de usuarios.
 * Se utiliza únicamente para disponer de credenciales con las que validar
 * la autenticación durante el desarrollo.
 */
class CrearUsuarioPrueba extends BaseCommand
{
    protected $group = 'Usuarios';

    protected $name = 'usuarios:crear-prueba';

    protected $description = 'Crea un usuario de prueba (desarrollo) con la contraseña hasheada y el rol indicado.';

    protected $usage = 'usuarios:crear-prueba [usuario] [password]';

    protected $arguments = [
        'usuario'  => 'Nombre de usuario (login). Si no se indica, se solicita por consola.',
        'password' => 'Contraseña (mínimo 8 caracteres). Si no se indica, se solicita por consola.',
    ];

    protected $options = [
        '-r'         => 'Nombre del rol a asignar (por defecto: SUPERADMINISTRADOR).',
        '--rol'      => 'Nombre del rol a asignar (por defecto: SUPERADMINISTRADOR).',
        '--nombre'   => 'Nombre real del usuario (por defecto: PRUEBA).',
        '--apellido' => 'Apellido real del usuario (por defecto: PRUEBA).',
        '--email'    => 'Correo electrónico del usuario (opcional).',
    ];

    /**
     * @param list<string> $params
     *
     * @return int
     */
    public function run(array $params)
    {
        $usuario = array_shift($params)
            ?? CLI::prompt('Usuario (login)', null, 'required|min_length[3]');
        $usuario = mb_strtolower(trim((string) $usuario));

        if ($usuario === '') {
            CLI::error('Debe indicar un nombre de usuario.');

            return EXIT_USER_INPUT;
        }

        $password = array_shift($params)
            ?? CLI::prompt('Contraseña (mínimo 8 caracteres)', null, 'required|min_length[8]');

        if (strlen((string) $password) < 8) {
            CLI::error('La contraseña debe tener al menos 8 caracteres.');

            return EXIT_USER_INPUT;
        }

        $rolNombre = mb_strtoupper(trim((string) (CLI::getOption('rol') ?? CLI::getOption('r') ?? 'SUPERADMINISTRADOR')));
        $nombre    = mb_strtoupper(trim((string) (CLI::getOption('nombre') ?? 'PRUEBA')));
        $apellido  = mb_strtoupper(trim((string) (CLI::getOption('apellido') ?? 'PRUEBA')));
        $email     = CLI::getOption('email') ?: null;

        $db = Database::connect();

        $existe = $db->table('usuarios')
            ->where('usuario', $usuario)
            ->countAllResults();

        if ($existe > 0) {
            CLI::error("Ya existe un usuario con el nombre \"{$usuario}\".");

            return EXIT_ERROR;
        }

        $rol = $db->table('roles')
            ->where('nombre', $rolNombre)
            ->where('activo', 1)
            ->get()
            ->getRow();

        if ($rol === null) {
            CLI::error("No existe un rol activo llamado \"{$rolNombre}\".");
            CLI::write('Roles disponibles:', 'yellow');

            $roles = $db->table('roles')
                ->select('nombre')
                ->orderBy('nombre', 'ASC')
                ->get()
                ->getResult();

            foreach ($roles as $r) {
                CLI::write('  - ' . $r->nombre);
            }

            return EXIT_USER_INPUT;
        }

        $ahora = date('Y-m-d H:i:s');

        $db->transBegin();

        try {
            $db->table('usuarios')->insert([
                'nombre'        => $nombre,
                'apellido'      => $apellido,
                'usuario'       => $usuario,
                'password_hash' => password_hash((string) $password, PASSWORD_DEFAULT),
                'email'         => $email,
                'activo'        => 1,
                'created_at'    => $ahora,
                'updated_at'    => $ahora,
            ]);

            $usuarioId = (int) $db->insertID();

            $db->table('usuarios_roles')->insert([
                'usuario_id' => $usuarioId,
                'rol_id'     => $rol->id,
                'created_at' => $ahora,
            ]);

            if (! $db->transStatus()) {
                $db->transRollback();
                CLI::error('No se pudo crear el usuario de prueba. Se revirtió la operación.');

                return EXIT_DATABASE;
            }

            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();
            CLI::error('Ocurrió un error al crear el usuario de prueba: ' . $e->getMessage());

            return EXIT_DATABASE;
        }

        CLI::write('Usuario de prueba creado correctamente.', 'green');

        CLI::table(
            [
                ['ID', (string) $usuarioId],
                ['Usuario', $usuario],
                ['Nombre', $nombre],
                ['Apellido', $apellido],
                ['Correo', $email ?? '(sin correo)'],
                ['Rol', $rol->nombre],
                ['Activo', 'Sí'],
            ],
            ['Campo', 'Valor']
        );

        return EXIT_SUCCESS;
    }
}