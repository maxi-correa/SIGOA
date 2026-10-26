<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

class AddPlazoConservacionToRecepcionesProvisorias extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('recepciones_provisorias')) {
            throw new RuntimeException('No existe la tabla recepciones_provisorias.');
        }

        $total = (int) $this->db->table('recepciones_provisorias')->countAllResults();
        $faltanColumnas = ! $this->db->fieldExists('plazo_conservacion_dias', 'recepciones_provisorias')
            || ! $this->db->fieldExists('fecha_prevista_definitiva', 'recepciones_provisorias');

        if ($faltanColumnas && $total > 0) {
            throw new RuntimeException(
                'La tabla recepciones_provisorias contiene ' . $total
                . ' fila(s). No se pueden agregar las columnas obligatorias de conservación '
                . 'sin definir previamente esos valores.'
            );
        }

        if ($total > 0 && (
            ! $this->existeCheck('recepciones_provisorias', 'chk_recepciones_provisorias_plazo_conservacion_positivo')
            || ! $this->existeCheck('recepciones_provisorias', 'chk_recepciones_provisorias_fecha_prevista')
        )) {
            $tabla = $this->db->escapeIdentifiers($this->db->prefixTable('recepciones_provisorias'));
            $fila = $this->db->query(
                'SELECT COUNT(*) AS total FROM ' . $tabla
                . ' WHERE `plazo_conservacion_dias` <= 0'
                . ' OR `fecha_prevista_definitiva` < `fecha_recepcion`'
            )->getRow();

            if ($fila !== null && (int) $fila->total > 0) {
                throw new RuntimeException(
                    'Existen recepciones provisorias con plazo o fecha prevista inválidos. '
                    . 'Regularícelas antes de aplicar las restricciones.'
                );
            }
        }

        $alteraciones = [];

        if (! $this->db->fieldExists('plazo_conservacion_dias', 'recepciones_provisorias')) {
            $alteraciones[] = 'ADD COLUMN `plazo_conservacion_dias` INT UNSIGNED NOT NULL';
        }

        if (! $this->db->fieldExists('fecha_prevista_definitiva', 'recepciones_provisorias')) {
            $alteraciones[] = 'ADD COLUMN `fecha_prevista_definitiva` DATE NOT NULL';
        }

        if (! $this->existeCheck('recepciones_provisorias', 'chk_recepciones_provisorias_plazo_conservacion_positivo')) {
            $alteraciones[] = 'ADD CONSTRAINT `chk_recepciones_provisorias_plazo_conservacion_positivo` '
                . 'CHECK (`plazo_conservacion_dias` > 0)';
        }

        if (! $this->existeCheck('recepciones_provisorias', 'chk_recepciones_provisorias_fecha_prevista')) {
            $alteraciones[] = 'ADD CONSTRAINT `chk_recepciones_provisorias_fecha_prevista` '
                . 'CHECK (`fecha_prevista_definitiva` >= `fecha_recepcion`)';
        }

        if ($alteraciones !== []) {
            $this->db->query(
                'ALTER TABLE ' . $this->db->escapeIdentifiers($this->db->prefixTable('recepciones_provisorias'))
                . ' ' . implode(', ', $alteraciones)
            );
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('recepciones_provisorias')) {
            return;
        }

        $tabla = $this->db->escapeIdentifiers($this->db->prefixTable('recepciones_provisorias'));

        if ($this->existeCheck('recepciones_provisorias', 'chk_recepciones_provisorias_fecha_prevista')) {
            $this->db->query(
                'ALTER TABLE ' . $tabla
                . ' DROP CHECK `chk_recepciones_provisorias_fecha_prevista`'
            );
        }

        if ($this->existeCheck('recepciones_provisorias', 'chk_recepciones_provisorias_plazo_conservacion_positivo')) {
            $this->db->query(
                'ALTER TABLE ' . $tabla
                . ' DROP CHECK `chk_recepciones_provisorias_plazo_conservacion_positivo`'
            );
        }

        if ($this->db->fieldExists('fecha_prevista_definitiva', 'recepciones_provisorias')) {
            $this->db->query(
                'ALTER TABLE ' . $tabla
                . ' DROP COLUMN `fecha_prevista_definitiva`'
            );
        }

        if ($this->db->fieldExists('plazo_conservacion_dias', 'recepciones_provisorias')) {
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
