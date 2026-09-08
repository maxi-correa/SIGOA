<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class EstadosObraSeeder extends Seeder
{
    public function run()
    {
        $estados = [
            [
                'estado'      => 'PREVIO INICIO',
                'descripcion' => 'Obra contratada o en condiciones administrativas para iniciar, sin inicio efectivo de los trabajos.',
                'activo'      => true,
            ],
            [
                'estado'      => 'EN EJECUCIÓN',
                'descripcion' => 'Obra con trabajos iniciados y en ejecución.',
                'activo'      => true,
            ],
            [
                'estado'      => 'NEUTRALIZADA',
                'descripcion' => 'Obra con ejecución temporalmente suspendida mediante acta de neutralización.',
                'activo'      => true,
            ],
            [
                'estado'      => 'EN PLAZO DE CONSERVACIÓN',
                'descripcion' => 'Obra finalizada que se encuentra dentro del período contractual de conservación.',
                'activo'      => true,
            ],
            [
                'estado'      => 'FINALIZADA',
                'descripcion' => 'Obra cuyo período de conservación ha concluido y se encuentra finalizada.',
                'activo'      => true,
            ],
        ];

        foreach ($estados as $estado) {
            $existe = $this->db
                ->table('estados_obra')
                ->where('estado', $estado['estado'])
                ->countAllResults();

            if ($existe === 0) {
                $this->db
                    ->table('estados_obra')
                    ->insert($estado);
            }
        }
    }
}
