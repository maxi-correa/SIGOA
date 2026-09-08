<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class RolesSeeder extends Seeder
{
    public function run()
    {
        $roles = [
            [
                'nombre'      => 'SUPERADMINISTRADOR',
                'descripcion' => 'Acceso completo al sistema, incluyendo la administración de usuarios, roles, configuraciones y datos.',
                'activo'      => true,
            ],
            [
                'nombre'      => 'ADMINISTRADOR',
                'descripcion' => 'Gestión administrativa de obras, empresas, documentación, usuarios y demás información del sistema.',
                'activo'      => true,
            ],
            [
                'nombre'      => 'INSPECTOR',
                'descripcion' => 'Registro y consulta de inspecciones, fotografías, avances y documentación relacionada con las obras asignadas.',
                'activo'      => true,
            ],
            [
                'nombre'      => 'CONSULTA',
                'descripcion' => 'Acceso de solo lectura a la información de las obras y documentación disponible en el sistema.',
                'activo'      => true,
            ],
        ];

        foreach ($roles as $rol) {
            $existe = $this->db
                ->table('roles')
                ->where('nombre', $rol['nombre'])
                ->countAllResults();

            if ($existe === 0) {
                $this->db
                    ->table('roles')
                    ->insert($rol);
            }
        }
    }
}