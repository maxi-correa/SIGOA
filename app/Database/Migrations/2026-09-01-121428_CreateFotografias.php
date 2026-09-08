<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFotografias extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],

            'inspeccion_id' => [
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

            'ruta_thumbnail' => [
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

            'ancho' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],

            'alto' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],

            'fecha_hora_captura' => [
                'type' => 'DATETIME',
                'null' => true,
            ],

            'fecha_hora_carga' => [
                'type' => 'DATETIME',
            ],

            'latitud' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,7',
                'null'       => true,
            ],

            'longitud' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,7',
                'null'       => true,
            ],

            'dispositivo' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],

            'anulada' => [
                'type'    => 'BOOLEAN',
                'default' => false,
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
        $this->forge->addKey('inspeccion_id');

        // Relación con inspecciones
        $this->forge->addForeignKey(
            'inspeccion_id',
            'inspecciones',
            'id',
            'RESTRICT',
            'CASCADE'
        );

        $this->forge->createTable('fotografias');
    }

    public function down()
    {
        $this->forge->dropTable('fotografias', true);
    }
}