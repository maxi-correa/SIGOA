<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Agrega la marca de confirmación de una resolución.
 *
 * Un trámite nace sin confirmar (0) y puede editarse. La confirmación es
 * una acción explícita del usuario: una vez confirmado, el trámite queda
 * inmutable y no puede editarse ni eliminarse.
 */
class AddConfirmacionAResoluciones extends Migration
{
    public function up()
    {
        $this->forge->addColumn('resoluciones', [
            'confirmada' => [
                'type'    => 'BOOLEAN',
                'null'    => false,
                'default' => false,
                'after'   => 'observaciones',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('resoluciones', 'confirmada');
    }
}
