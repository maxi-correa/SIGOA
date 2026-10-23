<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddForeignKeysToDocumentos extends Migration
{
    public function up()
    {
        // Agregar FKs si no existen (evitar errores si ya aplicadas)
        try {
            $this->forge->addForeignKey(
                'usuario_id_carga',
                'usuarios',
                'id',
                'RESTRICT',
                'CASCADE'
            );
        } catch (\Throwable $e) {
        }

        try {
            $this->forge->addForeignKey(
                'neutralizacion_id',
                'neutralizaciones',
                'id',
                'RESTRICT',
                'CASCADE'
            );
        } catch (\Throwable $e) {
        }

        try {
            $this->forge->addForeignKey(
                'reinicio_id',
                'neutralizaciones',
                'id',
                'RESTRICT',
                'CASCADE'
            );
        } catch (\Throwable $e) {
        }

        try {
            $this->forge->addForeignKey(
                'documento_padre_id',
                'documentos',
                'id',
                'RESTRICT',
                'CASCADE'
            );
        } catch (\Throwable $e) {
        }
    }

    public function down()
    {
        // No eliminar FKs de forma destructiva
    }
}
