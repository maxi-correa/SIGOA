<?php

namespace App\Libraries;

use DateTime;

/**
 * Cálculos de plazo y fechas de obra.
 *
 * Centraliza la conversión del plazo original a días corridos y el
 * cálculo de la fecha de finalización, evitando duplicar la regla de
 * negocio en controladores y vistas.
 *
 * Regla vigente: 1 mes = 30 días corridos.
 */
class PlazoObra
{
    /** Unidad de plazo expresada en días corridos. */
    public const UNIDAD_DIAS = 'DIAS';

    /** Unidad de plazo expresada en meses. */
    public const UNIDAD_MES = 'MES';

    /** Días corridos equivalentes a un mes. */
    public const DIAS_POR_MES = 30;

    /**
     * Unidades admitidas para el plazo de obra.
     *
     * @return array<string, string> Código almacenado => etiqueta visible
     */
    public static function unidades(): array
    {
        return [
            self::UNIDAD_DIAS => 'Días corridos',
            self::UNIDAD_MES  => 'Mes',
        ];
    }

    /**
     * Indica si el código de unidad es válido.
     */
    public static function esUnidadValida(string $unidad): bool
    {
        return array_key_exists($unidad, self::unidades());
    }

    /**
     * Convierte un plazo expresado en valor + unidad a días corridos.
     */
    public static function diasDesdeUnidad(int $valor, string $unidad): int
    {
        $factor = ($unidad === self::UNIDAD_MES) ? self::DIAS_POR_MES : 1;

        return $valor * $factor;
    }

    /**
     * Convierte una fecha dd/mm/yyyy a formato de almacenamiento Y-m-d.
     *
     * Devuelve null si el valor está vacío o no es una fecha válida.
     * La comparación estricta con el formato generado rechaza fechas
     * como 31/02/2026, que PHP desplazaría automáticamente.
     */
    public static function parseFecha(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        if ($valor === '') {
            return null;
        }

        $fecha = DateTime::createFromFormat('d/m/Y', $valor);

        if ($fecha === false || $fecha->format('d/m/Y') !== $valor) {
            return null;
        }

        return $fecha->format('Y-m-d');
    }

    /**
     * Convierte una fecha almacenada Y-m-d al formato visual dd/mm/yyyy.
     *
     * Devuelve null si el valor es vacío o inválido.
     */
    public static function formatearFecha(?string $fecha): ?string
    {
        $fecha = trim((string) $fecha);

        if ($fecha === '') {
            return null;
        }

        $objeto = DateTime::createFromFormat('Y-m-d', $fecha);

        if ($objeto === false) {
            return null;
        }

        return $objeto->format('d/m/Y');
    }

    /**
     * Suma días corridos a una fecha almacenada y devuelve Y-m-d.
     *
     * No utiliza zona horaria para evitar desplazamientos de un día.
     */
    public static function sumarDias(string $fechaIso, int $dias): string
    {
        $fecha = DateTime::createFromFormat('Y-m-d', $fechaIso);

        if ($fecha === false) {
            return $fechaIso;
        }

        $fecha->modify('+' . $dias . ' days');

        return $fecha->format('Y-m-d');
    }
}
