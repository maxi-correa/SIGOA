<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class TiposDocumentoSeeder extends Seeder
{
    public function run()
    {
        $tiposDocumento = [
            [
                'tipo_documento' => 'ACTA DE ENTREGA DE VEHÍCULO',
                'activo'         => true,
            ],
            [
                'tipo_documento' => 'ACTA DE DEVOLUCIÓN DE VEHÍCULO',
                'activo'         => true,
            ],
            [
                'tipo_documento' => 'ACTA DE INICIO',
                'activo'         => true,
            ],
            [
                'tipo_documento' => 'ACTA DE NEUTRALIZACIÓN',
                'activo'         => true,
            ],
            [
                'tipo_documento' => 'ACTA DE REINICIO',
                'activo'         => true,
            ],
            [
                'tipo_documento' => 'ACTA DE RECEPCIÓN PROVISORIA',
                'activo'         => true,
            ],
            [
                'tipo_documento' => 'ACTA DE RECEPCIÓN DEFINITIVA',
                'activo'         => true,
            ],
            [
                'tipo_documento' => 'DESIGNACIÓN DE INSPECCIÓN',
                'activo'         => true,
            ],
            [
                'tipo_documento' => 'CERTIFICADO',
                'activo'         => true,
            ],
            [
                'tipo_documento' => 'FOJA DE MEDICIÓN',
                'activo'         => true,
            ],
            [
                'tipo_documento' => 'ANTICIPO FINANCIERO',
                'activo'         => true,
            ],
            [
                'tipo_documento' => 'FONDO DE REPARO',
                'activo'         => true,
            ],
            [
                'tipo_documento' => 'PÓLIZA',
                'activo'         => true,
            ],
            [
                'tipo_documento' => 'PLIEGO',
                'activo'         => true,
            ],
            [
                'tipo_documento' => 'PLANO',
                'activo'         => true,
            ],
            [
                'tipo_documento' => 'OTROS',
                'activo'         => true,
            ],
        ];

        foreach ($tiposDocumento as $tipo) {
            $existe = $this->db
                ->table('tipos_documento')
                ->where('tipo_documento', $tipo['tipo_documento'])
                ->countAllResults();

            if ($existe === 0) {
                $this->db
                    ->table('tipos_documento')
                    ->insert($tipo);
            }
        }
    }
}
