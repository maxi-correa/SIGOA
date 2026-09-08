<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDocumentoForeignKeyToInspectoresObras extends Migration
{
    public function up()
    {
        $this->forge->addForeignKey(
            'documento_id',
            'documentos',
            'id',
            'RESTRICT',
            'SET NULL',
            'inspectores_obras'
        );
    }

    public function down()
    {
        $this->forge->dropForeignKey(
            'inspectores_obras',
            'inspectores_obras_documento_id_foreign'
        );
    }
}