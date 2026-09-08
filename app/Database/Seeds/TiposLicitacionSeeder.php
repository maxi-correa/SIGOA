<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class TiposLicitacionSeeder extends Seeder
{
    public function run()
    {
        $tiposLicitacion = [
            [
                'tipo_licitacion' => 'LICITACIÓN PÚBLICA',
                'activo'          => true,
            ],
            [
                'tipo_licitacion' => 'LICITACIÓN PRIVADA',
                'activo'          => true,
            ],
            [
                'tipo_licitacion' => 'CONCURSO PÚBLICO',
                'activo'          => true,
            ],
            [
                'tipo_licitacion' => 'CONCURSO PRIVADO DE PRECIOS',
                'activo'          => true,
            ],
            [
                'tipo_licitacion' => 'CONTRATACIÓN DIRECTA',
                'activo'          => true,
            ],
        ];

        foreach ($tiposLicitacion as $tipo) {
            $existe = $this->db
                ->table('tipos_licitacion')
                ->where('tipo_licitacion', $tipo['tipo_licitacion'])
                ->countAllResults();

            if ($existe === 0) {
                $this->db
                    ->table('tipos_licitacion')
                    ->insert($tipo);
            }
        }
    }
}
