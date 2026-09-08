<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class TiposResolucionSeeder extends Seeder
{
    public function run()
    {
        $tiposResolucion = [
            [
                'tipo_resolucion' => 'ADJUDICACIÓN',
                'activo'          => true,
            ],
            [
                'tipo_resolucion' => 'APROBACIÓN DE CUADRO COMPARATIVO',
                'activo'          => true,
            ],
            [
                'tipo_resolucion' => 'APROBACIÓN DE AMPLIACIÓN DE PLAZO',
                'activo'          => true,
            ],
            [
                'tipo_resolucion' => 'OTRO',
                'activo'          => true,
            ],
        ];

        foreach ($tiposResolucion as $tipo) {
            $existe = $this->db
                ->table('tipos_resolucion')
                ->where('tipo_resolucion', $tipo['tipo_resolucion'])
                ->countAllResults();

            if ($existe === 0) {
                $this->db
                    ->table('tipos_resolucion')
                    ->insert($tipo);
            }
        }
    }
}
