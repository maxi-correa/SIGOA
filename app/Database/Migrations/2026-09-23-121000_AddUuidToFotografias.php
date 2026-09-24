<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Agrega la identidad UUID estable a `fotografias` (F.2 / §52.3).
 *
 * El `id` autoincremental se conserva como identidad interna del servidor.
 * El `uuid` (CHAR(36)) es la identidad estable de la entidad para operaciones
 * offline y sincronización futuras, y funcionará como clave de idempotencia.
 *
 * Misma estrategia que AddUuidToInspecciones: columna anulable, backfill con
 * `UUID()`, NOT NULL y UNIQUE.
 */
class AddUuidToFotografias extends Migration
{
    public function up()
    {
        $this->forge->addColumn('fotografias', [
            'uuid' => [
                'type'       => 'CHAR',
                'constraint' => 36,
                'null'       => true,
                'after'      => 'id',
            ],
        ]);

        $this->db->query('UPDATE fotografias SET uuid = UUID() WHERE uuid IS NULL');

        $this->forge->modifyColumn('fotografias', [
            'uuid' => [
                'type'       => 'CHAR',
                'constraint' => 36,
                'null'       => false,
            ],
        ]);

        $this->db->query('ALTER TABLE fotografias ADD UNIQUE KEY uq_fotografias_uuid (uuid)');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE fotografias DROP INDEX uq_fotografias_uuid');
        $this->forge->dropColumn('fotografias', 'uuid');
    }
}