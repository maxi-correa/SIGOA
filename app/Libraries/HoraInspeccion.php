<?php

namespace App\Libraries;

/**
 * Presentación de la hora de una inspección.
 *
 * Centraliza la única regla de presentación de `inspecciones.hora_inspeccion`:
 *
 * * `NULL` significa que la inspección **no tiene hora** y se informa como tal;
 * * `00:00:00` es una **hora válida** y se muestra como `00:00`, nunca como
 *   "sin hora" (§52.8: en el nombre de la carpeta física ocurre lo mismo).
 *
 * No puede dejarse a la interpretación de cada vista: una inspección a medianoche
 * y una inspección sin hora son casos válidos y distintos, y confundirlos daría
 * una lectura equivocada del histórico del día.
 *
 * Es solo presentación: no altera, normaliza ni recalcula el dato almacenado. La
 * forma canónica en la base es `HH:MM:SS` y las fracciones de segundo, si las
 * hubiera, no se muestran en el histórico.
 */
final class HoraInspeccion
{
    /** Etiqueta de una inspección sin hora. */
    public const SIN_HORA = 'Sin hora';

    /**
     * Texto de la hora de una inspección: `HH:MM`, o la etiqueta de "sin hora".
     *
     * Acepta las variantes que puede devolver el motor (`HH:MM`,
     * `HH:MM:SS`, `HH:MM:SS.ffffff`). Un valor ilegible se informa como "sin
     * hora" en lugar de mostrar un dato corrupto.
     */
    public static function texto(?string $hora): string
    {
        $partes = self::partes($hora);

        if ($partes === null) {
            return self::SIN_HORA;
        }

        return $partes[0] . ':' . $partes[1];
    }

    /**
     * Indica si la inspección no tiene una hora informable.
     *
     * Es true para `NULL` y también para un valor ilegible: en el histórico no
     * se distingue entre "no se registró" y "no se puede leer", porque en ambos
     * casos no hay una hora que mostrar.
     */
    public static function esSinHora(?string $hora): bool
    {
        return self::partes($hora) === null;
    }

    /**
     * Hora y minuto de una hora almacenada, o null si no es una hora válida.
     *
     * @return array{0: string, 1: string}|null
     */
    private static function partes(?string $hora): ?array
    {
        if ($hora === null) {
            return null;
        }

        if (preg_match('/\A(\d{2}):(\d{2})(?::\d{2})?(?:\.\d+)?\z/', trim($hora), $coincidencias) !== 1) {
            return null;
        }

        if ((int) $coincidencias[1] > 23 || (int) $coincidencias[2] > 59) {
            return null;
        }

        return [$coincidencias[1], $coincidencias[2]];
    }
}
