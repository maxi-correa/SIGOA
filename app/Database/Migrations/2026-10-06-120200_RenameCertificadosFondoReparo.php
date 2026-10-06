<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Renombra `certificados.fondo_reparo` a `retencion_fondo_reparo`.
 *
 * El campo es el importe retenido en ese certificado, no un porcentaje
 * ni el fondo teórico de la obra.
 */
class RenameCertificadosFondoReparo extends Migration
{
    public function up()
    {
        $this->forge->modifyColumn('certificados', [
            'fondo_reparo' => [
                'name'       => 'retencion_fondo_reparo',
                'type'       => 'DECIMAL',
                'constraint' => '15,3',
                'null'       => true,
            ],
        ]);
    }

    public function down()
    {
        $this->forge->modifyColumn('certificados', [
            'retencion_fondo_reparo' => [
                'name'       => 'fondo_reparo',
                'type'       => 'DECIMAL',
                'constraint' => '15,3',
                'null'       => true,
            ],
        ]);
    }
}
