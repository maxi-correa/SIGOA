<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Al hacer el alta inicial de una obra, el tipo de licitación es opcional
 * (la obra puede existir como expediente previo a la adjudicación).
 *
 * La columna tipo_licitacion_id pasa a ser NULL.
 */
class ObrasTipoLicitacionOpcional extends Migration
{
    public function up()
    {
        $this->forge->modifyColumn('obras', [
            'tipo_licitacion_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
        ]);
    }

    public function down()
    {
        $this->forge->modifyColumn('obras', [
            'tipo_licitacion_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => false,
            ],
        ]);
    }
}