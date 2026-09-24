<?php

namespace App\Libraries;

/**
 * Utilidades para UUID (identidad estable de inspecciones y fotografías).
 *
 * En la arquitectura offline (Fase A, §52.3) el UUID es generado por el
 * cliente (`crypto.randomUUID()`); el servidor lo valida y lo utiliza como
 * clave de idempotencia. No se incorpora una librería externa: la validación
 * usa un patrón RFC 4122 y la generación usa la fuente de aleatoriedad
 * segura de PHP (`random_bytes`), solo para backfills y pruebas de servidor.
 */
final class Uuid
{
    /**
     * Patrón RFC 4122: 8-4-4-4-12 dígitos hexadecimales.
     * Acepta cualquier versión (v1..v5); la unicidad la garantiza la BD.
     */
    private const PATRON = '/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/i';

    public static function isValid(string $valor): bool
    {
        return preg_match(self::PATRON, $valor) === 1;
    }

    /**
     * Genera un UUID v4 (aleatorio).
     *
     * Devuelve la forma canónica en minúsculas, igual que
     * `crypto.randomUUID()` del navegador.
     */
    public static function v4(): string
    {
        $bytes = random_bytes(16);

        // Versión 4 y variante RFC 4122.
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}