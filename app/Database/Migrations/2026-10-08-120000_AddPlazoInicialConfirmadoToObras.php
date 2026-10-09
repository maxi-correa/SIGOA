<?php

namespace App\Database\Migrations;

use App\Libraries\PlazoObra;
use CodeIgniter\Database\Migration;

/**
 * Agrega a `obras` la marca de confirmación de los datos iniciales de
 * plazo (fecha de inicio y plazo original).
 *
 * Backfill: quedan confirmadas las obras que ya tienen fecha de inicio
 * y plazo original completos y consistentes con la regla vigente
 * (`días = valor × factor de la unidad`). El resto queda en 0 y deberá
 * confirmarse desde la ficha de la obra.
 *
 * No se modifican fechas ni plazos: la migración solo marca la columna.
 */
class AddPlazoInicialConfirmadoToObras extends Migration
{
    public function up()
    {
        $this->forge->addColumn('obras', [
            'plazo_inicial_confirmado' => [
                'type'    => 'BOOLEAN',
                'null'    => false,
                'default' => false,
                'after'   => 'plazo_original_dias',
            ],
        ]);

        $filas = $this->db->table('obras')
            ->select('id, fecha_inicio, plazo_original_valor, plazo_original_unidad, plazo_original_dias')
            ->where('fecha_inicio IS NOT NULL', null, false)
            ->where('plazo_original_valor IS NOT NULL', null, false)
            ->where('plazo_original_unidad IS NOT NULL', null, false)
            ->where('plazo_original_dias IS NOT NULL', null, false)
            ->get()
            ->getResult();

        $idsConfirmables = [];

        foreach ($filas as $fila) {
            $valor  = (int) $fila->plazo_original_valor;
            $unidad = (string) $fila->plazo_original_unidad;
            $dias   = (int) $fila->plazo_original_dias;

            if ($valor < 1) {
                continue;
            }

            if (! PlazoObra::esUnidadValida($unidad)) {
                continue;
            }

            if (PlazoObra::diasDesdeUnidad($valor, $unidad) !== $dias) {
                continue;
            }

            $idsConfirmables[] = (int) $fila->id;
        }

        if ($idsConfirmables !== []) {
            $this->db->table('obras')
                ->whereIn('id', $idsConfirmables)
                ->set('plazo_inicial_confirmado', 1)
                ->update();
        }
    }

    public function down()
    {
        $this->forge->dropColumn('obras', 'plazo_inicial_confirmado');
    }
}
