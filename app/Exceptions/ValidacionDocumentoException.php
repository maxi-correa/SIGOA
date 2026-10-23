<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Error de validación de un documento durante su carga o reemplazo.
 *
 * Permite que la validación compartida del archivo PDF (extensión,
 * contenido real y tamaño) se aplique igual en ambas operaciones: el
 * controlador captura la excepción y la convierte en un mensaje de error
 * de la pantalla.
 */
class ValidacionDocumentoException extends RuntimeException
{
}
