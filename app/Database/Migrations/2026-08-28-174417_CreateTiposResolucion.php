<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTiposResolucion extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'tipo_resolucion' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'activo' => [
                'type'    => 'BOOLEAN',
                'default' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('tipo_resolucion');

        $this->forge->createTable('tipos_resolucion');
    }

    public function down()
    {
        $this->forge->dropTable('tipos_resolucion', true);
    }
}