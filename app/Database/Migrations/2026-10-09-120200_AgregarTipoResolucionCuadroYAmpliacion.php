<?php

namespace App\Database\Migrations;

use App\Libraries\ResolucionObra;
use CodeIgniter\Database\Migration;

/**
 * Incorpora al catálogo el tipo de resolución que combina la aprobación
 * del cuadro comparativo con una ampliación de plazo.
 *
 * La inserción es idempotente: si el tipo ya existe (por ejemplo, porque
 * el seeder se ejecutó antes) no se duplica.
 */
class AgregarTipoResolucionCuadroYAmpliacion extends Migration
{
    public function up()
    {
        $existe = $this->db->table('tipos_resolucion')
            ->where('tipo_resolucion', ResolucionObra::TIPO_CUADRO_Y_AMPLIACION)
            ->countAllResults();

        if ($existe === 0) {
            $this->db->table('tipos_resolucion')->insert([
                'tipo_resolucion' => ResolucionObra::TIPO_CUADRO_Y_AMPLIACION,
                'activo'          => true,
            ]);
        }
    }

    public function down()
    {
        $tabla = $this->db->table('tipos_resolucion');

        $fila = $tabla
            ->select('id')
            ->where('tipo_resolucion', ResolucionObra::TIPO_CUADRO_Y_AMPLIACION)
            ->get()
            ->getRow();

        if ($fila === null) {
            return;
        }

        $enUso = $this->db->table('resoluciones')
            ->where('tipo_resolucion_id', (int) $fila->id)
            ->countAllResults();

        if ($enUso === 0) {
            $tabla->where('id', (int) $fila->id)->delete();
        }
    }
}
