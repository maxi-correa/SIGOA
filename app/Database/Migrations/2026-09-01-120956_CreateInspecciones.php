<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInspecciones extends Migration
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

            'inspector_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],

            'fecha_inspeccion' => [
                'type' => 'DATE',
            ],

            'hora_inspeccion' => [
                'type' => 'TIME',
                'null' => true,
            ],

            'observacion' => [
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

        // Clave primaria
        $this->forge->addKey('id', true);

        // Índices para las claves foráneas
        $this->forge->addKey('obra_id');
        $this->forge->addKey('inspector_id');

        // Una obra solo puede tener una inspección por día.
        $this->forge->addUniqueKey([
            'obra_id',
            'fecha_inspeccion',
        ]);

        // Relaciones
        $this->forge->addForeignKey(
            'obra_id',
            'obras',
            'id',
            'RESTRICT',
            'CASCADE'
        );

        $this->forge->addForeignKey(
            'inspector_id',
            'usuarios',
            'id',
            'RESTRICT',
            'CASCADE'
        );

        $this->forge->createTable('inspecciones');
    }

    public function down()
    {
        $this->forge->dropTable('inspecciones', true);
    }
}