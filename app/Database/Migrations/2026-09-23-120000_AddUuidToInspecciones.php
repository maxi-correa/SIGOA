<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Agrega la identidad UUID estable a `inspecciones` (F.2 / §52.3).
 *
 * El `id` autoincremental se conserva como identidad interna del servidor.
 * El `uuid` (CHAR(36)) es la identidad estable de la entidad para operaciones
 * offline y sincronización futuras, y funcionará como clave de idempotencia.
 *
 * Estrategia segura también para tablas pobladas: se agrega la columna
 * anulable, se rellenan las filas existentes con `UUID()` de MySQL/MariaDB y
 * recién entonces se aplica NOT NULL y el índice UNIQUE.
 */
class AddUuidToInspecciones extends Migration
{
    public function up()
    {
        $this->forge->addColumn('inspecciones', [
            'uuid' => [
                'type'       => 'CHAR',
                'constraint' => 36,
                'null'       => true,
                'after'      => 'id',
            ],
        ]);

        $this->db->query('UPDATE inspecciones SET uuid = UUID() WHERE uuid IS NULL');

        $this->forge->modifyColumn('inspecciones', [
            'uuid' => [
                'type'       => 'CHAR',
                'constraint' => 36,
                'null'       => false,
            ],
        ]);

        $this->db->query('ALTER TABLE inspecciones ADD UNIQUE KEY uq_inspecciones_uuid (uuid)');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE inspecciones DROP INDEX uq_inspecciones_uuid');
        $this->forge->dropColumn('inspecciones', 'uuid');
    }
}