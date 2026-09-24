<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Elimina la restricción `UNIQUE (obra_id, fecha_inspeccion)` de
 * `inspecciones` (F.5 / §52.3).
 *
 * Desde esta fase una obra puede tener múltiples inspecciones en la misma
 * fecha. Cada inspección se identifica inequívocamente por su `uuid`.
 *
 * Se conservan el índice simple `obra_id`, las foreign keys (`obra_id` satisface
 * a `fk_inspecciones_obra`; `inspector_id` a `fk_inspecciones_inspector`), los
 * timestamps y las demás columnas. No se crea ninguna restricción nueva que
 * vuelva a impedir múltiples inspecciones por día.
 */
class DropUniqueObraFechaInspeccion extends Migration
{
    public function up()
    {
        $this->db->query('ALTER TABLE inspecciones DROP INDEX obra_id_fecha_inspeccion');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE inspecciones ADD UNIQUE KEY obra_id_fecha_inspeccion (obra_id, fecha_inspeccion)');
    }
}