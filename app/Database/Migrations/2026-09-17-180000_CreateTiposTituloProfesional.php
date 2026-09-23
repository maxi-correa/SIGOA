<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTiposTituloProfesional extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'titulo' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'descripcion' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'activo' => [
                'type'    => 'BOOLEAN',
                'default' => true,
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

        // El título profesional identifica de manera única al catálogo
        $this->forge->addUniqueKey('titulo');

        $this->forge->createTable('tipos_titulo_profesional', false, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('tipos_titulo_profesional', true);
    }
}