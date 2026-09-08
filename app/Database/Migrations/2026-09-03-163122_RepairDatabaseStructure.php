<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class RepairDatabaseStructure extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        /*
        |--------------------------------------------------------------------------
        | 1. Crear tabla usuarios
        |--------------------------------------------------------------------------
        |
        | La migración CreateUsuarios figura como ejecutada, pero la tabla
        | actualmente no existe. Por eso la creamos directamente aquí.
        |
        */

        $db->query("
            CREATE TABLE IF NOT EXISTS usuarios (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                nombre VARCHAR(100) NOT NULL,
                apellido VARCHAR(100) NOT NULL,
                usuario VARCHAR(100) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                email VARCHAR(150) NULL,
                activo BOOLEAN NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY usuario (usuario)
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_general_ci
        ");


        /*
        |--------------------------------------------------------------------------
        | 2. Corregir datos existentes antes de imponer NOT NULL
        |--------------------------------------------------------------------------
        |
        | Algunas tablas tienen created_at / updated_at NULL.
        | El documento definitivo establece que esos campos deben ser
        | obligatorios.
        |
        | Si existen registros antiguos sin fecha, los completamos con NOW().
        |
        */

        $tablasConCreatedUpdated = [
            'estados_obra',
            'barrios',
            'empresas',
        ];

        foreach ($tablasConCreatedUpdated as $tabla) {
            $db->query("
                UPDATE {$tabla}
                SET created_at = NOW()
                WHERE created_at IS NULL
            ");

            $db->query("
                UPDATE {$tabla}
                SET updated_at = NOW()
                WHERE updated_at IS NULL
            ");
        }


        /*
        |--------------------------------------------------------------------------
        | 3. Corregir usuarios_roles.created_at
        |--------------------------------------------------------------------------
        */

        $db->query("
            UPDATE usuarios_roles
            SET created_at = NOW()
            WHERE created_at IS NULL
        ");


        /*
        |--------------------------------------------------------------------------
        | 4. Corregir estado PREVIO INICIO
        |--------------------------------------------------------------------------
        |
        | La especificación definitiva reemplazó:
        |
        | EN PREPARACIÓN
        |
        | por:
        |
        | PREVIO INICIO
        |
        */

        $db->query("
            UPDATE estados_obra
            SET estado = 'PREVIO INICIO'
            WHERE estado = 'EN PREPARACIÓN'
        ");


        /*
        |--------------------------------------------------------------------------
        | 5. Convertir tablas a InnoDB
        |--------------------------------------------------------------------------
        |
        | Actualmente las tablas son MyISAM.
        | MyISAM no soporta claves foráneas reales.
        |
        | Primero convertimos todas las tablas de aplicación.
        | La tabla interna "migrations" NO se toca.
        |
        */

        $tablas = [
            'estados_obra',
            'barrios',
            'tipos_licitacion',
            'tipos_resolucion',
            'tipos_documento',
            'roles',
            'empresas',
            'usuarios',
            'usuarios_roles',
            'obras',
            'resoluciones',
            'inspectores_obras',
            'inspecciones',
            'fotografias',
            'documentos',
            'neutralizaciones',
            'recepciones_provisorias',
            'recepciones_definitivas',
            'certificados',
            'elementos_entrega',
            'auditoria',
            'operaciones_sincronizacion',
        ];

        foreach ($tablas as $tabla) {
            $db->query("
                ALTER TABLE {$tabla}
                ENGINE = InnoDB
            ");
        }


        /*
        |--------------------------------------------------------------------------
        | 6. Corregir roles.rol -> roles.nombre
        |--------------------------------------------------------------------------
        */

        $db->query("
            ALTER TABLE roles
            DROP INDEX rol
        ");

        $db->query("
            ALTER TABLE roles
            CHANGE COLUMN rol nombre VARCHAR(50) NOT NULL
        ");

        $db->query("
            ALTER TABLE roles
            ADD UNIQUE KEY nombre (nombre)
        ");


        /*
        |--------------------------------------------------------------------------
        | 7. Corregir longitud de tipos_licitacion
        |--------------------------------------------------------------------------
        |
        | Documento: VARCHAR(80)
        | Actualmente: VARCHAR(100)
        |
        | Los valores existentes del catálogo caben dentro de 80 caracteres.
        |
        */

        $db->query("
            ALTER TABLE tipos_licitacion
            MODIFY COLUMN tipo_licitacion VARCHAR(80) NOT NULL
        ");


        /*
        |--------------------------------------------------------------------------
        | 8. Corregir estructura de obras
        |--------------------------------------------------------------------------
        |
        | codigo:               30 -> 20
        | nombre:               200 -> 255
        | expediente_contable:  30 -> 50
        |
        */

        /*
        | Antes de reducir codigo de 30 a 20 verificamos que no existan
        | valores demasiado largos.
        */

        $resultado = $db->query("
            SELECT COUNT(*) AS cantidad
            FROM obras
            WHERE CHAR_LENGTH(codigo) > 20
        ")->getRow();

        if ($resultado->cantidad > 0) {
            throw new \RuntimeException(
                'No se puede reducir obras.codigo a VARCHAR(20): existen registros con más de 20 caracteres.'
            );
        }

        $db->query("
            ALTER TABLE obras
            MODIFY COLUMN codigo VARCHAR(20) NOT NULL
        ");

        $db->query("
            ALTER TABLE obras
            MODIFY COLUMN nombre VARCHAR(255) NOT NULL
        ");

        $db->query("
            ALTER TABLE obras
            MODIFY COLUMN expediente_contable VARCHAR(50) NULL
        ");


        /*
        |--------------------------------------------------------------------------
        | 9. UNIQUE para expediente_municipal
        |--------------------------------------------------------------------------
        |
        | Un expediente municipal corresponde a una única obra.
        |
        */

        $resultado = $db->query("
            SELECT COUNT(*) AS cantidad
            FROM obras
            GROUP BY expediente_municipal
            HAVING COUNT(*) > 1
            LIMIT 1
        ")->getRow();

        if ($resultado) {
            throw new \RuntimeException(
                'No se puede crear UNIQUE sobre obras.expediente_municipal: existen expedientes municipales duplicados.'
            );
        }

        $db->query("
            ALTER TABLE obras
            ADD UNIQUE KEY expediente_municipal (expediente_municipal)
        ");


        /*
        |--------------------------------------------------------------------------
        | 10. Corregir timestamps de estados_obra, barrios y empresas
        |--------------------------------------------------------------------------
        */

        $db->query("
            ALTER TABLE estados_obra
            MODIFY COLUMN created_at DATETIME NOT NULL,
            MODIFY COLUMN updated_at DATETIME NOT NULL
        ");

        $db->query("
            ALTER TABLE barrios
            MODIFY COLUMN created_at DATETIME NOT NULL,
            MODIFY COLUMN updated_at DATETIME NOT NULL
        ");

        $db->query("
            ALTER TABLE empresas
            MODIFY COLUMN created_at DATETIME NOT NULL,
            MODIFY COLUMN updated_at DATETIME NOT NULL
        ");


        /*
        |--------------------------------------------------------------------------
        | 11. Corregir usuarios_roles.created_at
        |--------------------------------------------------------------------------
        */

        $db->query("
            ALTER TABLE usuarios_roles
            MODIFY COLUMN created_at DATETIME NOT NULL
        ");


        /*
        |--------------------------------------------------------------------------
        | 12. Agregar claves foráneas
        |--------------------------------------------------------------------------
        |
        | Todas las tablas ya están en InnoDB.
        | Ahora podemos crear las relaciones reales.
        |
        */


        /*
        | usuarios_roles
        */

        $db->query("
            ALTER TABLE usuarios_roles
            ADD CONSTRAINT fk_usuarios_roles_usuario
                FOREIGN KEY (usuario_id)
                REFERENCES usuarios(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ");

        $db->query("
            ALTER TABLE usuarios_roles
            ADD CONSTRAINT fk_usuarios_roles_rol
                FOREIGN KEY (rol_id)
                REFERENCES roles(id)
                ON DELETE RESTRICT
                ON UPDATE CASCADE
        ");


        /*
        | obras
        */

        $db->query("
            ALTER TABLE obras
            ADD CONSTRAINT fk_obras_estado
                FOREIGN KEY (estado_obra_id)
                REFERENCES estados_obra(id)
                ON DELETE RESTRICT
                ON UPDATE CASCADE
        ");

        $db->query("
            ALTER TABLE obras
            ADD CONSTRAINT fk_obras_barrio
                FOREIGN KEY (barrio_id)
                REFERENCES barrios(id)
                ON DELETE RESTRICT
                ON UPDATE CASCADE
        ");

        $db->query("
            ALTER TABLE obras
            ADD CONSTRAINT fk_obras_tipo_licitacion
                FOREIGN KEY (tipo_licitacion_id)
                REFERENCES tipos_licitacion(id)
                ON DELETE RESTRICT
                ON UPDATE CASCADE
        ");

        $db->query("
            ALTER TABLE obras
            ADD CONSTRAINT fk_obras_empresa
                FOREIGN KEY (empresa_id)
                REFERENCES empresas(id)
                ON DELETE RESTRICT
                ON UPDATE CASCADE
        ");


        /*
        | resoluciones
        */

        $db->query("
            ALTER TABLE resoluciones
            ADD CONSTRAINT fk_resoluciones_obra
                FOREIGN KEY (obra_id)
                REFERENCES obras(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ");

        $db->query("
            ALTER TABLE resoluciones
            ADD CONSTRAINT fk_resoluciones_tipo
                FOREIGN KEY (tipo_resolucion_id)
                REFERENCES tipos_resolucion(id)
                ON DELETE RESTRICT
                ON UPDATE CASCADE
        ");


        /*
        | inspecciones
        */

        $db->query("
            ALTER TABLE inspecciones
            ADD CONSTRAINT fk_inspecciones_obra
                FOREIGN KEY (obra_id)
                REFERENCES obras(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ");

        $db->query("
            ALTER TABLE inspecciones
            ADD CONSTRAINT fk_inspecciones_inspector
                FOREIGN KEY (inspector_id)
                REFERENCES usuarios(id)
                ON DELETE RESTRICT
                ON UPDATE CASCADE
        ");


        /*
        | fotografias
        */

        $db->query("
            ALTER TABLE fotografias
            ADD CONSTRAINT fk_fotografias_inspeccion
                FOREIGN KEY (inspeccion_id)
                REFERENCES inspecciones(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ");


        /*
        | inspectores_obras
        */

        $db->query("
            ALTER TABLE inspectores_obras
            ADD CONSTRAINT fk_inspectores_obras_obra
                FOREIGN KEY (obra_id)
                REFERENCES obras(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ");

        $db->query("
            ALTER TABLE inspectores_obras
            ADD CONSTRAINT fk_inspectores_obras_usuario
                FOREIGN KEY (usuario_id)
                REFERENCES usuarios(id)
                ON DELETE RESTRICT
                ON UPDATE CASCADE
        ");

        $db->query("
            ALTER TABLE inspectores_obras
            ADD CONSTRAINT fk_inspectores_obras_documento
                FOREIGN KEY (documento_id)
                REFERENCES documentos(id)
                ON DELETE SET NULL
                ON UPDATE CASCADE
        ");


        /*
        | documentos
        */

        $db->query("
            ALTER TABLE documentos
            ADD CONSTRAINT fk_documentos_obra
                FOREIGN KEY (obra_id)
                REFERENCES obras(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ");

        $db->query("
            ALTER TABLE documentos
            ADD CONSTRAINT fk_documentos_tipo
                FOREIGN KEY (tipo_documento_id)
                REFERENCES tipos_documento(id)
                ON DELETE RESTRICT
                ON UPDATE CASCADE
        ");


        /*
        | neutralizaciones
        */

        $db->query("
            ALTER TABLE neutralizaciones
            ADD CONSTRAINT fk_neutralizaciones_obra
                FOREIGN KEY (obra_id)
                REFERENCES obras(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ");

        $db->query("
            ALTER TABLE neutralizaciones
            ADD CONSTRAINT fk_neutralizaciones_documento
                FOREIGN KEY (documento_id)
                REFERENCES documentos(id)
                ON DELETE SET NULL
                ON UPDATE CASCADE
        ");


        /*
        | recepciones_provisorias
        */

        $db->query("
            ALTER TABLE recepciones_provisorias
            ADD CONSTRAINT fk_recepciones_provisorias_obra
                FOREIGN KEY (obra_id)
                REFERENCES obras(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ");

        $db->query("
            ALTER TABLE recepciones_provisorias
            ADD CONSTRAINT fk_recepciones_provisorias_documento
                FOREIGN KEY (documento_id)
                REFERENCES documentos(id)
                ON DELETE SET NULL
                ON UPDATE CASCADE
        ");


        /*
        | recepciones_definitivas
        */

        $db->query("
            ALTER TABLE recepciones_definitivas
            ADD CONSTRAINT fk_recepciones_definitivas_obra
                FOREIGN KEY (obra_id)
                REFERENCES obras(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ");

        $db->query("
            ALTER TABLE recepciones_definitivas
            ADD CONSTRAINT fk_recepciones_definitivas_documento
                FOREIGN KEY (documento_id)
                REFERENCES documentos(id)
                ON DELETE SET NULL
                ON UPDATE CASCADE
        ");


        /*
        | certificados
        */

        $db->query("
            ALTER TABLE certificados
            ADD CONSTRAINT fk_certificados_obra
                FOREIGN KEY (obra_id)
                REFERENCES obras(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ");

        $db->query("
            ALTER TABLE certificados
            ADD CONSTRAINT fk_certificados_documento
                FOREIGN KEY (documento_id)
                REFERENCES documentos(id)
                ON DELETE SET NULL
                ON UPDATE CASCADE
        ");


        /*
        | elementos_entrega
        */

        $db->query("
            ALTER TABLE elementos_entrega
            ADD CONSTRAINT fk_elementos_entrega_obra
                FOREIGN KEY (obra_id)
                REFERENCES obras(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ");


        /*
        | auditoria
        */

        $db->query("
            ALTER TABLE auditoria
            ADD CONSTRAINT fk_auditoria_usuario
                FOREIGN KEY (usuario_id)
                REFERENCES usuarios(id)
                ON DELETE SET NULL
                ON UPDATE CASCADE
        ");


        /*
        | operaciones_sincronizacion
        */

        $db->query("
            ALTER TABLE operaciones_sincronizacion
            ADD CONSTRAINT fk_operaciones_sincronizacion_usuario
                FOREIGN KEY (usuario_id)
                REFERENCES usuarios(id)
                ON DELETE RESTRICT
                ON UPDATE CASCADE
        ");
    }


    public function down()
    {
        /*
        |--------------------------------------------------------------------------
        | Esta migración es una reparación estructural.
        |--------------------------------------------------------------------------
        |
        | No implementamos un rollback automático porque implicaría:
        |
        | - volver las tablas a MyISAM,
        | - eliminar claves foráneas,
        | - eliminar la tabla usuarios,
        | - revertir cambios estructurales,
        |
        | y podría provocar pérdida o corrupción de datos.
        |
        | Si alguna vez fuera necesario revertir esta reparación,
        | se hará de forma manual y controlada.
        |
        */

        throw new \RuntimeException(
            'RepairDatabaseStructure no admite rollback automático.'
        );
    }
}