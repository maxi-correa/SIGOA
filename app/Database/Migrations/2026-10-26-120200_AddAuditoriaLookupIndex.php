<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

class AddAuditoriaLookupIndex extends Migration
{
    private const INDICE = 'idx_auditoria_entidad_registro_fecha';

    public function up()
    {
        if (! $this->db->tableExists('auditoria')) {
            throw new RuntimeException('No existe la tabla auditoria.');
        }

        if ($this->existeIndiceConColumnas('auditoria', ['entidad', 'registro_id', 'fecha_hora'])) {
            return;
        }

        $this->db->query(
            'ALTER TABLE ' . $this->db->escapeIdentifiers($this->db->prefixTable('auditoria'))
            . ' ADD INDEX `' . self::INDICE . '` (`entidad`, `registro_id`, `fecha_hora`)'
        );
    }

    public function down()
    {
        if (! $this->db->tableExists('auditoria') || ! $this->existeIndicePorNombre('auditoria', self::INDICE)) {
            return;
        }

        $this->db->query(
            'ALTER TABLE ' . $this->db->escapeIdentifiers($this->db->prefixTable('auditoria'))
            . ' DROP INDEX `' . self::INDICE . '`'
        );
    }

    private function existeIndiceConColumnas(string $tabla, array $columnas): bool
    {
        $filas = $this->db->query(
            'SELECT INDEX_NAME, COLUMN_NAME '
            . 'FROM information_schema.STATISTICS '
            . 'WHERE TABLE_SCHEMA = DATABASE() '
            . 'AND TABLE_NAME = ? '
            . 'ORDER BY INDEX_NAME, SEQ_IN_INDEX',
            [$this->db->prefixTable($tabla)]
        )->getResultArray();

        $indices = [];

        foreach ($filas as $fila) {
            $indices[$fila['INDEX_NAME']][] = $fila['COLUMN_NAME'];
        }

        foreach ($indices as $columnasIndice) {
            if ($columnasIndice === $columnas) {
                return true;
            }
        }

        return false;
    }

    private function existeIndicePorNombre(string $tabla, string $nombre): bool
    {
        $fila = $this->db->query(
            'SELECT COUNT(*) AS total '
            . 'FROM information_schema.STATISTICS '
            . 'WHERE TABLE_SCHEMA = DATABASE() '
            . 'AND TABLE_NAME = ? '
            . 'AND INDEX_NAME = ?',
            [$this->db->prefixTable($tabla), $nombre]
        )->getRow();

        return $fila !== null && (int) $fila->total > 0;
    }
}
