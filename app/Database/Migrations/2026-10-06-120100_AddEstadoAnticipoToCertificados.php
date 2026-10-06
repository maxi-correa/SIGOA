<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Agrega el estado conceptual del anticipo en cada certificado.
 *
 * Valores previstos (constantes de CertificadoModel): NORMAL, REMANENTE,
 * YA_LIQUIDADO. NULL cuando la obra no tiene anticipo financiero.
 */
class AddEstadoAnticipoToCertificados extends Migration
{
    public function up()
    {
        $this->forge->addColumn('certificados', [
            'estado_anticipo' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'null'       => true,
                'after'      => 'descuento_anticipo',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('certificados', 'estado_anticipo');
    }
}
