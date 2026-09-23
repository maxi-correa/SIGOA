<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class TiposTituloProfesionalSeeder extends Seeder
{
    public function run()
    {
        $ahora = date('Y-m-d H:i:s');

        $titulos = [
            [
                'titulo'      => 'INGENIERO',
                'descripcion' => null,
                'activo'      => true,
                'created_at'  => $ahora,
                'updated_at'  => $ahora,
            ],
            [
                'titulo'      => 'ARQUITECTO',
                'descripcion' => null,
                'activo'      => true,
                'created_at'  => $ahora,
                'updated_at'  => $ahora,
            ],
            [
                'titulo'      => 'MAESTRO MAYOR DE OBRA',
                'descripcion' => null,
                'activo'      => true,
                'created_at'  => $ahora,
                'updated_at'  => $ahora,
            ],
        ];

        foreach ($titulos as $titulo) {
            $existe = $this->db
                ->table('tipos_titulo_profesional')
                ->where('titulo', $titulo['titulo'])
                ->countAllResults();

            if ($existe === 0) {
                $this->db
                    ->table('tipos_titulo_profesional')
                    ->insert($titulo);
            }
        }
    }
}