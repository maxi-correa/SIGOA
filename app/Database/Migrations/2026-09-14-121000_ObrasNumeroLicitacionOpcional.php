<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * El número de licitación es opcional en la obra.
 * Una obra puede existir sin número de licitación adjudicado.
 *
 * La columna numero_licitacion pasa a ser NULL.
 */
class ObrasNumeroLicitacionOpcional extends Migration
{
    public function up()
    {
        $this->forge->modifyColumn('obras', [
            'numero_licitacion' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'null'       => true,
            ],
        ]);
    }

    public function down()
    {
        $this->forge->modifyColumn('obras', [
            'numero_licitacion' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'null'       => false,
            ],
        ]);
    }
}