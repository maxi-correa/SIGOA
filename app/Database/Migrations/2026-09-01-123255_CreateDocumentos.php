<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDocumentos extends Migration
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
                'null'     => true,
            ],

            'tipo_documento_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],

            'nombre_archivo' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],

            'ruta_relativa' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
            ],

            'extension' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
            ],

            'mime_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],

            'tamano_bytes' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
            ],

            'fecha_documento' => [
                'type' => 'DATE',
                'null' => true,
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
        $this->forge->addKey('tipo_documento_id');

        // Relaciones
        $this->forge->addForeignKey(
            'obra_id',
            'obras',
            'id',
            'RESTRICT',
            'CASCADE'
        );

        $this->forge->addForeignKey(
            'tipo_documento_id',
            'tipos_documento',
            'id',
            'RESTRICT',
            'CASCADE'
        );

        $this->forge->createTable('documentos');
    }

    public function down()
    {
        $this->forge->dropTable('documentos', true);
    }
}