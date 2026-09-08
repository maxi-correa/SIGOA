<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTiposDocumento extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'tipo_documento' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'activo' => [
                'type'    => 'BOOLEAN',
                'default' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('tipo_documento');

        $this->forge->createTable('tipos_documento');
    }

    public function down()
    {
        $this->forge->dropTable('tipos_documento', true);
    }
}