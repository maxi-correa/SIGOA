<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAuditoria extends Migration
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
                'null'     => true,
            ],

            'fecha_hora' => [
                'type' => 'DATETIME',
            ],

            'accion' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],

            'entidad' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],

            'registro_id' => [
                'type'       => 'BIGINT',
                'unsigned'   => true,
                'null'       => true,
            ],

            'campo' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],

            'valor_anterior' => [
                'type' => 'TEXT',
                'null' => true,
            ],

            'valor_nuevo' => [
                'type' => 'TEXT',
                'null' => true,
            ],

            'dispositivo' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],

            'observaciones' => [
                'type' => 'TEXT',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);

        $this->forge->addForeignKey(
            'usuario_id',
            'usuarios',
            'id',
            'SET NULL',
            'CASCADE'
        );

        $this->forge->createTable('auditoria');
    }

    public function down()
    {
        $this->forge->dropTable('auditoria');
    }
}