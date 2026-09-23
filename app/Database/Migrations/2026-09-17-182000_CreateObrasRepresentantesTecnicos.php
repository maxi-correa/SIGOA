<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateObrasRepresentantesTecnicos extends Migration
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
            'representante_tecnico_id' => [
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
            'created_at' => [
                'type' => 'DATETIME',
            ],
        ]);

        // Clave primaria
        $this->forge->addKey('id', true);

        // Índices para las claves foráneas
        $this->forge->addKey('obra_id');
        $this->forge->addKey('representante_tecnico_id');

        // Relaciones
        $this->forge->addForeignKey(
            'obra_id',
            'obras',
            'id',
            'CASCADE',
            'CASCADE'
        );

        // ON UPDATE CASCADE / ON DELETE RESTRICT
        $this->forge->addForeignKey(
            'representante_tecnico_id',
            'representantes_tecnicos',
            'id',
            'CASCADE',
            'RESTRICT'
        );

        $this->forge->createTable('obras_representantes_tecnicos', false, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('obras_representantes_tecnicos', true);
    }
}