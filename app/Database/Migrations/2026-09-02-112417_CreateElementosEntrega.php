<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateElementosEntrega extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],

            'obra_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],

            'nombre' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],

            'cantidad' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,3',
                'null'       => true,
            ],

            'estado' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'default'    => 'PENDIENTE',
            ],

            'fecha_recepcion' => [
                'type' => 'DATE',
                'null' => true,
            ],

            'observaciones' => [
                'type' => 'TEXT',
                'null' => true,
            ],

            'created_at' => [
                'type' => 'DATETIME',
            ],

            'updated_at' => [
                'type' => 'DATETIME',
            ],
        ]);

        $this->forge->addKey('id', true);

        $this->forge->addForeignKey(
            'obra_id',
            'obras',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->forge->createTable('elementos_entrega');
    }

    public function down()
    {
        $this->forge->dropTable('elementos_entrega');
    }
}