<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCertificados extends Migration
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

            'mes' => [
                'type'     => 'TINYINT',
                'unsigned' => true,
            ],

            'anio' => [
                'type'     => 'SMALLINT',
                'unsigned' => true,
            ],

            'fecha_emision' => [
                'type' => 'DATE',
                'null' => true,
            ],

            'monto_bruto' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,3',
            ],

            'descuento_anticipo' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,3',
                'null'       => true,
            ],

            'fondo_reparo' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,3',
                'null'       => true,
            ],

            'monto_neto' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,3',
                'null'       => true,
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

        // Una obra no puede tener dos veces el mismo número de certificado.
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

        $this->forge->createTable('certificados');
    }

    public function down()
    {
        $this->forge->dropTable('certificados', true);
    }
}