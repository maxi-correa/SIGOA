<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEstadosObra extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'estado' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'descripcion' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'activo' => [
                'type'    => 'BOOLEAN',
                'default' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('estado');

        $this->forge->createTable('estados_obra');
    }

    public function down()
    {
        $this->forge->dropTable('estados_obra', true);
    }
}