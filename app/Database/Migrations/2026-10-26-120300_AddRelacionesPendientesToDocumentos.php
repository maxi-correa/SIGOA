<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

class AddRelacionesPendientesToDocumentos extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('documentos')) {
            throw new RuntimeException('No existe la tabla documentos.');
        }

        $relaciones = $this->relaciones();
        $referencias = array_unique(array_column($relaciones, 'referencia'));

        foreach ($referencias as $referencia) {
            if (! $this->db->tableExists($referencia)) {
                throw new RuntimeException('No existe la tabla referenciada ' . $referencia . '.');
            }
        }

        $huerfanos = [];

        foreach ($relaciones as $relacion) {
            $total = $this->contarHuerfanos($relacion['columna'], $relacion['referencia']);

            if ($total > 0) {
                $huerfanos[] = $relacion['columna'] . '=' . $total;
            }
        }

        if ($huerfanos !== []) {
            throw new RuntimeException(
                'No se pueden crear las claves foráneas de documentos. '
                . 'Existen valores sin referencia en: ' . implode(', ', $huerfanos) . '.'
            );
        }

        $alteraciones = [];

        foreach ($relaciones as $relacion) {
            $foreignKey = $this->obtenerForeignKey($relacion['columna']);

            if ($foreignKey !== null) {
                $referenciaEsperada = $this->db->prefixTable($relacion['referencia']);

                if (
                    $foreignKey['REFERENCED_TABLE_NAME'] !== $referenciaEsperada
                    || strtoupper($foreignKey['DELETE_RULE']) !== $relacion['on_delete']
                    || strtoupper($foreignKey['UPDATE_RULE']) !== 'CASCADE'
                ) {
                    throw new RuntimeException(
                        'La clave foránea existente para documentos.' . $relacion['columna']
                        . ' no coincide con la relación esperada.'
                    );
                }
            }

            if (! $this->existeIndiceQueComiencePor('documentos', $relacion['columna'])) {
                $alteraciones[] = 'ADD INDEX `' . $relacion['indice'] . '` (`' . $relacion['columna'] . '`)';
            }

            if ($foreignKey === null) {
                $alteraciones[] = 'ADD CONSTRAINT `' . $relacion['fk'] . '` '
                    . 'FOREIGN KEY (`' . $relacion['columna'] . '`) '
                    . 'REFERENCES ' . $this->db->escapeIdentifiers($this->db->prefixTable($relacion['referencia']))
                    . ' (`id`) ON DELETE ' . $relacion['on_delete'] . ' ON UPDATE CASCADE';
            }
        }

        if ($alteraciones !== []) {
            $this->db->query(
                'ALTER TABLE ' . $this->db->escapeIdentifiers($this->db->prefixTable('documentos'))
                . ' ' . implode(', ', $alteraciones)
            );
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('documentos')) {
            return;
        }

        $tabla = $this->db->escapeIdentifiers($this->db->prefixTable('documentos'));

        foreach ($this->relaciones() as $relacion) {
            if ($this->existeForeignKeyPorNombre($relacion['fk'])) {
                $this->db->query(
                    'ALTER TABLE ' . $tabla . ' DROP FOREIGN KEY `' . $relacion['fk'] . '`'
                );
            }
        }

        foreach ($this->relaciones() as $relacion) {
            if ($this->existeIndicePorNombre($relacion['indice'])) {
                $this->db->query(
                    'ALTER TABLE ' . $tabla . ' DROP INDEX `' . $relacion['indice'] . '`'
                );
            }
        }
    }

    private function relaciones(): array
    {
        return [
            [
                'columna' => 'usuario_id_carga',
                'referencia' => 'usuarios',
                'indice' => 'idx_documentos_usuario_id_carga',
                'fk' => 'fk_documentos_usuario_id_carga',
                'on_delete' => 'SET NULL',
            ],
            [
                'columna' => 'neutralizacion_id',
                'referencia' => 'neutralizaciones',
                'indice' => 'idx_documentos_neutralizacion_id',
                'fk' => 'fk_documentos_neutralizacion_id',
                'on_delete' => 'RESTRICT',
            ],
            [
                'columna' => 'reinicio_id',
                'referencia' => 'neutralizaciones',
                'indice' => 'idx_documentos_reinicio_id',
                'fk' => 'fk_documentos_reinicio_id',
                'on_delete' => 'RESTRICT',
            ],
            [
                'columna' => 'documento_padre_id',
                'referencia' => 'documentos',
                'indice' => 'idx_documentos_documento_padre_id',
                'fk' => 'fk_documentos_documento_padre_id',
                'on_delete' => 'RESTRICT',
            ],
        ];
    }

    private function contarHuerfanos(string $columna, string $referencia): int
    {
        $fila = $this->db->query(
            'SELECT COUNT(*) AS total '
            . 'FROM ' . $this->db->escapeIdentifiers($this->db->prefixTable('documentos')) . ' d '
            . 'LEFT JOIN ' . $this->db->escapeIdentifiers($this->db->prefixTable($referencia)) . ' r '
            . 'ON r.`id` = d.`' . $columna . '` '
            . 'WHERE d.`' . $columna . '` IS NOT NULL AND r.`id` IS NULL'
        )->getRow();

        return $fila !== null ? (int) $fila->total : 0;
    }

    private function obtenerForeignKey(string $columna): ?array
    {
        $fila = $this->db->query(
            'SELECT k.CONSTRAINT_NAME, k.REFERENCED_TABLE_NAME, r.DELETE_RULE, r.UPDATE_RULE '
            . 'FROM information_schema.KEY_COLUMN_USAGE k '
            . 'INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS r '
            . 'ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA '
            . 'AND r.TABLE_NAME = k.TABLE_NAME '
            . 'AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME '
            . 'WHERE k.CONSTRAINT_SCHEMA = DATABASE() '
            . 'AND k.TABLE_NAME = ? '
            . 'AND k.COLUMN_NAME = ? '
            . 'AND k.REFERENCED_TABLE_NAME IS NOT NULL '
            . 'LIMIT 1',
            [$this->db->prefixTable('documentos'), $columna]
        )->getRowArray();

        return $fila ?: null;
    }

    private function existeIndiceQueComiencePor(string $tabla, string $columna): bool
    {
        $filas = $this->db->query(
            'SELECT INDEX_NAME, COLUMN_NAME '
            . 'FROM information_schema.STATISTICS '
            . 'WHERE TABLE_SCHEMA = DATABASE() '
            . 'AND TABLE_NAME = ? '
            . 'AND SEQ_IN_INDEX = 1',
            [$this->db->prefixTable($tabla)]
        )->getResultArray();

        foreach ($filas as $fila) {
            if ($fila['COLUMN_NAME'] === $columna) {
                return true;
            }
        }

        return false;
    }

    private function existeForeignKeyPorNombre(string $nombre): bool
    {
        $fila = $this->db->query(
            'SELECT COUNT(*) AS total '
            . 'FROM information_schema.TABLE_CONSTRAINTS '
            . 'WHERE CONSTRAINT_SCHEMA = DATABASE() '
            . 'AND TABLE_NAME = ? '
            . 'AND CONSTRAINT_NAME = ? '
            . 'AND CONSTRAINT_TYPE = ?',
            [$this->db->prefixTable('documentos'), $nombre, 'FOREIGN KEY']
        )->getRow();

        return $fila !== null && (int) $fila->total > 0;
    }

    private function existeIndicePorNombre(string $nombre): bool
    {
        $fila = $this->db->query(
            'SELECT COUNT(*) AS total '
            . 'FROM information_schema.STATISTICS '
            . 'WHERE TABLE_SCHEMA = DATABASE() '
            . 'AND TABLE_NAME = ? '
            . 'AND INDEX_NAME = ?',
            [$this->db->prefixTable('documentos'), $nombre]
        )->getRow();

        return $fila !== null && (int) $fila->total > 0;
    }
}
