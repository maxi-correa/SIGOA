<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Agrega a `obras` la configuración económica permanente necesaria para
 * la certificación: presupuesto oficial, anticipo financiero, fondo de
 * reparo y póliza de fondo de reparo.
 *
 * No se realiza backfill de importes ni porcentajes. Las obras existentes
 * quedan sin configurar (flags en 0, porcentajes y póliza en NULL).
 */
class AddConfiguracionEconomicaCertificacionToObras extends Migration
{
    public function up()
    {
        $this->forge->addColumn('obras', [
            'presupuesto_oficial' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,3',
                'null'       => true,
                'after'      => 'empresa_id',
            ],
            'tiene_anticipo_financiero' => [
                'type'    => 'BOOLEAN',
                'null'    => false,
                'default' => false,
                'after'   => 'monto_contractual_vigente',
            ],
            'porcentaje_anticipo_financiero' => [
                'type'       => 'DECIMAL',
                'constraint' => '6,3',
                'null'       => true,
                'after'      => 'tiene_anticipo_financiero',
            ],
            'tiene_fondo_reparo' => [
                'type'    => 'BOOLEAN',
                'null'    => false,
                'default' => false,
                'after'   => 'porcentaje_anticipo_financiero',
            ],
            'porcentaje_fondo_reparo' => [
                'type'       => 'DECIMAL',
                'constraint' => '6,3',
                'null'       => true,
                'after'      => 'tiene_fondo_reparo',
            ],
            'fondo_reparo_con_poliza' => [
                'type'    => 'BOOLEAN',
                'null'    => true,
                'after'   => 'porcentaje_fondo_reparo',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('obras', [
            'presupuesto_oficial',
            'tiene_anticipo_financiero',
            'porcentaje_anticipo_financiero',
            'tiene_fondo_reparo',
            'porcentaje_fondo_reparo',
            'fondo_reparo_con_poliza',
        ]);
    }
}
