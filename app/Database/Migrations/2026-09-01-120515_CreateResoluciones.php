<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateResoluciones extends Migration
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

            'tipo_resolucion_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],

            'numero' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],

            'anio' => [
                'type'     => 'SMALLINT',
                'unsigned' => true,
            ],

            'fecha_resolucion' => [
                'type' => 'DATE',
            ],

            'monto' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,3',
                'null'       => true,
            ],

            'dias' => [
                'type'     => 'INT',
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

        // Clave primaria
        $this->forge->addKey('id', true);

        // Índices para las claves foráneas
        $this->forge->addKey('obra_id');
        $this->forge->addKey('tipo_resolucion_id');

        // Una misma obra no puede tener dos resoluciones
        // con exactamente el mismo número y año.
        $this->forge->addUniqueKey([
            'obra_id',
            'numero',
            'anio',
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
            'tipo_resolucion_id',
            'tipos_resolucion',
            'id',
            'RESTRICT',
            'CASCADE'
        );

        $this->forge->createTable('resoluciones');
    }

    public function down()
    {
        $this->forge->dropTable('resoluciones', true);
    }
}