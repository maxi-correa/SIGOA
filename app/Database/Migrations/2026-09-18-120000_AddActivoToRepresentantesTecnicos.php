<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Agrega el estado Activo/Inactivo al padrón de representantes técnicos.
 *
 * Los representantes existentes quedan activos. El estado reemplaza a la
 * eliminación física: un representante inactivo conserva sus datos y su
 * historial de asignaciones en las obras.
 */
class AddActivoToRepresentantesTecnicos extends Migration
{
    public function up()
    {
        $this->forge->addColumn('representantes_tecnicos', [
            'activo' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 1,
                'after'      => 'matricula',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('representantes_tecnicos', 'activo');
    }
}
