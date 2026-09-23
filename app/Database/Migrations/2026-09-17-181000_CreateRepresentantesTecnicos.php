<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRepresentantesTecnicos extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'nombre' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'apellido' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'titulo_profesional_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'matricula' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'null'       => true,
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

        // Índice para la clave foránea
        $this->forge->addKey('titulo_profesional_id');

        // La matrícula es única; el UNIQUE permite múltiples NULL y solo
        // bloquea matrículas no nulas duplicadas.
        $this->forge->addUniqueKey('matricula');

        // Relaciones: ON UPDATE CASCADE / ON DELETE RESTRICT
        $this->forge->addForeignKey(
            'titulo_profesional_id',
            'tipos_titulo_profesional',
            'id',
            'CASCADE',
            'RESTRICT'
        );

        $this->forge->createTable('representantes_tecnicos', false, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('representantes_tecnicos', true);
    }
}