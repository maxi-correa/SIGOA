<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

class AddPlazoConservacionToObras extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('obras')) {
            throw new RuntimeException('No existe la tabla obras.');
        }

        $tabla = $this->db->prefixTable('obras');
        $alteraciones = [];

        if (! $this->db->fieldExists('plazo_conservacion_dias', 'obras')) {
            $alteraciones[] = 'ADD COLUMN `plazo_conservacion_dias` INT UNSIGNED NOT NULL DEFAULT 180';
        }

        if (! $this->existeCheck('obras', 'chk_obras_plazo_conservacion_positivo')) {
            $alteraciones[] = 'ADD CONSTRAINT `chk_obras_plazo_conservacion_positivo` CHECK (`plazo_conservacion_dias` > 0)';
        }

        if ($alteraciones !== []) {
            $this->db->query(
                'ALTER TABLE ' . $this->db->escapeIdentifiers($tabla)
                . ' ' . implode(', ', $alteraciones)
            );
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('obras')) {
            return;
        }

        $tabla = $this->db->escapeIdentifiers($this->db->prefixTable('obras'));

        if ($this->existeCheck('obras', 'chk_obras_plazo_conservacion_positivo')) {
            $this->db->query(
                'ALTER TABLE ' . $tabla
                . ' DROP CHECK `chk_obras_plazo_conservacion_positivo`'
            );
        }

        if ($this->db->fieldExists('plazo_conservacion_dias', 'obras')) {
            $this->db->query(
                'ALTER TABLE ' . $tabla
                . ' DROP COLUMN `plazo_conservacion_dias`'
            );
        }
    }

    private function existeCheck(string $tabla, string $nombre): bool
    {
        $fila = $this->db->query(
            'SELECT COUNT(*) AS total '
            . 'FROM information_schema.TABLE_CONSTRAINTS '
            . 'WHERE CONSTRAINT_SCHEMA = DATABASE() '
            . 'AND TABLE_NAME = ? '
            . 'AND CONSTRAINT_NAME = ? '
            . 'AND CONSTRAINT_TYPE = ?',
            [$this->db->prefixTable($tabla), $nombre, 'CHECK']
        )->getRow();

        return $fila !== null && (int) $fila->total > 0;
    }
}
