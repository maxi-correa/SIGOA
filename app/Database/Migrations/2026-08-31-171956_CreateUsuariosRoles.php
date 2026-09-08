<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUsuariosRoles extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'usuario_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'rol_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);

        $this->forge->addKey('usuario_id');
        $this->forge->addKey('rol_id');

        $this->forge->addUniqueKey(['usuario_id', 'rol_id']);

        $this->forge->addForeignKey(
            'usuario_id',
            'usuarios',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->forge->addForeignKey(
            'rol_id',
            'roles',
            'id',
            'RESTRICT',
            'CASCADE'
        );

        $this->forge->createTable('usuarios_roles');
    }

    public function down()
    {
        $this->forge->dropTable('usuarios_roles', true);
    }
}