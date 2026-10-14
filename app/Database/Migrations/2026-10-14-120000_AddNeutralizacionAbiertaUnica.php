<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/**
 * Garantiza a nivel de base de datos que una obra no tenga más de una
 * neutralización abierta simultáneamente (BLOQUE 5).
 *
 * Agrega una columna generada `abierta` que vale 1 mientras la fila no
 * tenga `fecha_reinicio` y queda NULL cuando el período está cerrado, más
 * un índice único `(obra_id, abierta)`. Como los valores NULL no colisionan
 * en el índice, solo se impide la convivencia de dos filas abiertas para la
 * misma obra; cualquier cantidad de períodos cerrados es válida.
 *
 * La migración es idempotente y no modifica ninguna migración histórica.
 * Si ya existieran obras con más de una neutralización abierta, se detiene
 * y las informa: la restricción no debe aplicarse debilitando datos.
 */
class AddNeutralizacionAbiertaUnica extends Migration
{
    public function up()
    {
        if ($this->db->fieldExists('abierta', 'neutralizaciones')) {
            return;
        }

        $tabla = $this->db->prefixTable('neutralizaciones');

        $duplicadas = $this->db
            ->query(
                "SELECT obra_id FROM {$tabla} WHERE fecha_reinicio IS NULL "
                . 'GROUP BY obra_id HAVING COUNT(*) > 1'
            )
            ->getResult();

        if ($duplicadas !== []) {
            $ids = implode(', ', array_map(static fn ($fila) => (string) $fila->obra_id, $duplicadas));

            throw new RuntimeException(
                'Existen obras con más de una neutralización abierta (obra_id: ' . $ids
                . '). Regularícelas antes de aplicar esta migración.'
            );
        }

        $this->db->query(
            "ALTER TABLE {$tabla} "
            . 'ADD COLUMN `abierta` TINYINT '
            . 'GENERATED ALWAYS AS (IF(`fecha_reinicio` IS NULL, 1, NULL)) STORED, '
            . 'ADD UNIQUE KEY `uq_neutralizaciones_abierta` (`obra_id`, `abierta`)'
        );
    }

    public function down()
    {
        if (! $this->db->fieldExists('abierta', 'neutralizaciones')) {
            return;
        }

        $tabla = $this->db->prefixTable('neutralizaciones');

        $this->db->query(
            "ALTER TABLE {$tabla} "
            . 'DROP INDEX `uq_neutralizaciones_abierta`, '
            . 'DROP COLUMN `abierta`'
        );
    }
}
