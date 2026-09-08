<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInspectoresObras extends Migration
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

            'usuario_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],

            'fecha_inicio' => [
                'type' => 'DATE',
            ],

            'fecha_fin' => [
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
        ]);

        // Clave primaria
        $this->forge->addKey('id', true);

        // Índices para las claves foráneas
        $this->forge->addKey('obra_id');
        $this->forge->addKey('usuario_id');
        $this->forge->addKey('documento_id');

        // Relaciones
        $this->forge->addForeignKey(
            'obra_id',
            'obras',
            'id',
            'RESTRICT',
            'CASCADE'
        );

        $this->forge->addForeignKey(
            'usuario_id',
            'usuarios',
            'id',
            'RESTRICT',
            'CASCADE'
        );

        /*
         * documento_id se relacionará con documentos.id
         * cuando la tabla documentos sea creada.
         */

        $this->forge->createTable('inspectores_obras');
    }

    public function down()
    {
        $this->forge->dropTable('inspectores_obras', true);
    }
}