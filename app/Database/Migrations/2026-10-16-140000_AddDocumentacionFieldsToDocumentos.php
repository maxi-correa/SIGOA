<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDocumentacionFieldsToDocumentos extends Migration
{
    public function up()
    {
        $this->forge->addColumn('documentos', [
            'nombre_archivo_original' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'nombre_archivo',
            ],
            'usuario_id_carga' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
                'after'    => 'fecha_documento',
            ],
            'neutralizacion_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
                'after'    => 'usuario_id_carga',
            ],
            'reinicio_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
                'after'    => 'neutralizacion_id',
            ],
            'version' => [
                'type'     => 'INT',
                'unsigned' => true,
                'default'  => 1,
                'null'     => false,
                'after'    => 'observaciones',
            ],
            'es_version_actual' => [
                'type'    => 'TINYINT',
                'constraint' => 1,
                'default' => 1,
                'null'    => false,
                'after'   => 'version',
            ],
            'documento_padre_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
                'after'    => 'es_version_actual',
            ],
        ]);

        $this->forge->addKey('usuario_id_carga');
        $this->forge->addKey('neutralizacion_id');
        $this->forge->addKey('reinicio_id');
        $this->forge->addKey('documento_padre_id');
    }

    public function down()
    {
        $this->forge->dropColumn('documentos', [
            'nombre_archivo_original',
            'usuario_id_carga',
            'neutralizacion_id',
            'reinicio_id',
            'version',
            'es_version_actual',
            'documento_padre_id',
        ]);
    }
}
