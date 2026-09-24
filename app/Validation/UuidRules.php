<?php

namespace App\Validation;

use App\Libraries\Uuid;

/**
 * Reglas de validación propias de SIGOA.
 *
 * `uuid_valid`: valida el formato RFC 4122 de un UUID. La unicidad no se
 * valida aquí: la garantiza el índice UNIQUE de la base de datos.
 */
final class UuidRules
{
    public function uuid_valid($str = null): bool
    {
        if ($str === null || trim($str) === '') {
            return true;
        }

        return Uuid::isValid((string) $str);
    }
}