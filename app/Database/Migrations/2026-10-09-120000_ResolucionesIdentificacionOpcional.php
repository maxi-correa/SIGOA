<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Permite registrar trámites incompletos: el número, el año y la fecha de
 * la resolución pasan a admitir nulos.
 *
 * Un trámite puede crearse sin estos datos y completarse antes de
 * confirmarse. El índice único (obra_id, numero, anio) se conserva: en
 * MySQL los nulos no colisionan, por lo que una misma obra puede tener
 * varios trámites sin identificar.
 */
class ResolucionesIdentificacionOpcional extends Migration
{
    public function up()
    {
        $this->forge->modifyColumn('resoluciones', [
            'numero' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'anio' => [
                'type'     => 'SMALLINT',
                'unsigned' => true,
                'null'     => true,
            ],
            'fecha_resolucion' => [
                'type' => 'DATE',
                'null' => true,
            ],
        ]);
    }

    public function down()
    {
        /* No se revierte a NOT NULL: puede haber trámites incompletos
           (identificación nula) que impedirían la conversión. */
    }
}
