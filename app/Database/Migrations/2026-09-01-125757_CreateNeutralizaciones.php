<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateNeutralizaciones extends Migration
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

            'numero' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],

            'fecha_inicio' => [
                'type' => 'DATE',
            ],

            'fecha_reinicio' => [
                'type' => 'DATE',
                'null' => true,
            ],

            'documento_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
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

        $this->forge->addKey('obra_id');
        $this->forge->addKey('documento_id');

        // La numeración comienza nuevamente en cada obra.
        $this->forge->addUniqueKey([
            'obra_id',
            'numero',
        ]);

        $this->forge->addForeignKey(
            'obra_id',
            'obras',
            'id',
            'RESTRICT',
            'CASCADE'
        );

        $this->forge->addForeignKey(
            'documento_id',
            'documentos',
            'id',
            'RESTRICT',
            'SET NULL'
        );

        $this->forge->createTable('neutralizaciones');
    }

    public function down()
    {
        $this->forge->dropTable('neutralizaciones', true);
    }
}