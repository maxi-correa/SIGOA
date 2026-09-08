<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOperacionesSincronizacion extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],

            'usuario_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],

            'tipo_operacion' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],

            'entidad' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],

            'registro_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],

            'estado' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],

            'intentos' => [
                'type'     => 'INT',
                'unsigned' => true,
                'default'  => 0,
            ],

            'ultimo_intento' => [
                'type' => 'DATETIME',
                'null' => true,
            ],

            'error' => [
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
            'usuario_id',
            'usuarios',
            'id',
            'RESTRICT',
            'CASCADE'
        );

        $this->forge->createTable('operaciones_sincronizacion');
    }

    public function down()
    {
        $this->forge->dropTable('operaciones_sincronizacion');
    }
}