<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateObras extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],

            'codigo' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],

            'expediente_municipal' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],

            'numero_licitacion' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],

            'tipo_licitacion_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],

            'nombre' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
            ],

            'barrio_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],

            'empresa_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],

            'monto_contrato' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,3',
                'null'       => true,
            ],

            'monto_contractual_vigente' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,3',
                'null'       => true,
            ],

            'expediente_contable' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'null'       => true,
            ],

            'fecha_inicio' => [
                'type' => 'DATE',
                'null' => true,
            ],

            'plazo_original_valor' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'null'       => true,
            ],

            'plazo_original_unidad' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'null'       => true,
            ],

            'plazo_original_dias' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],

            'estado_obra_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],

            'observacion_general' => [
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
        $this->forge->addKey('tipo_licitacion_id');
        $this->forge->addKey('barrio_id');
        $this->forge->addKey('empresa_id');
        $this->forge->addKey('estado_obra_id');

        // El código SIGOA identifica de manera única a cada obra
        $this->forge->addUniqueKey('codigo');

        // Relaciones
        $this->forge->addForeignKey(
            'tipo_licitacion_id',
            'tipos_licitacion',
            'id',
            'RESTRICT',
            'CASCADE'
        );

        $this->forge->addForeignKey(
            'barrio_id',
            'barrios',
            'id',
            'RESTRICT',
            'CASCADE'
        );

        $this->forge->addForeignKey(
            'empresa_id',
            'empresas',
            'id',
            'RESTRICT',
            'CASCADE'
        );

        $this->forge->addForeignKey(
            'estado_obra_id',
            'estados_obra',
            'id',
            'RESTRICT',
            'CASCADE'
        );

        $this->forge->createTable('obras');
    }

    public function down()
    {
        $this->forge->dropTable('obras', true);
    }
}