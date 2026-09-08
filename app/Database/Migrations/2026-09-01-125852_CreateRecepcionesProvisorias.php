<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRecepcionesProvisorias extends Migration
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

            'fecha_recepcion' => [
                'type' => 'DATE',
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

        // Una obra tiene una única recepción provisoria.
        $this->forge->addUniqueKey('obra_id');

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

        $this->forge->createTable('recepciones_provisorias');
    }

    public function down()
    {
        $this->forge->dropTable('recepciones_provisorias', true);
    }
}