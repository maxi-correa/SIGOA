<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTiposLicitacion extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'tipo_licitacion' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'activo' => [
                'type'    => 'BOOLEAN',
                'default' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('tipo_licitacion');

        $this->forge->createTable('tipos_licitacion');
    }

    public function down()
    {
        $this->forge->dropTable('tipos_licitacion', true);
    }
}