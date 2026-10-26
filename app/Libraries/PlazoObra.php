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

        $objeto = DateTime::createFromFormat('!Y-m-d', $fecha);

        if ($objeto === false || $objeto->format('Y-m-d') !== $fecha) {
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

    /**
     * Calcula la composición del plazo contractual vigente.
     *
     * Plazo vigente = plazo inicial en días + suma de días de las
     * ampliaciones confirmadas. Solo aportan las resoluciones cuyo tipo
     * amplía el plazo (ResolucionObra::ampliaPlazo) y cuya cantidad de
     * días es mayor a cero; las pendientes se devuelven separadas para
     * poder mostrarlas sin confundirlas con ampliaciones aprobadas.
     *
     * No persiste nada: el resultado es un dato derivado.
     *
     * @param int|null     $plazoInicialDias Plazo original en días corridos, o null si falta.
     * @param list<object> $resoluciones     Resoluciones de la obra (tipo_nombre, dias, confirmada).
     *
     * @return array{
     *     inicial_dias: int|null,
     *     ampliaciones: list<object>,
     *     pendientes: list<object>,
     *     total_ampliacion_dias: int,
     *     vigente_dias: int|null,
     *     tiene_ampliaciones: bool
     * }
     */
    public static function calcularVigente(?int $plazoInicialDias, array $resoluciones): array
    {
        $ampliaciones = [];
        $pendientes   = [];
        $total        = 0;

        foreach ($resoluciones as $resolucion) {
            if (! ResolucionObra::ampliaPlazo($resolucion->tipo_nombre ?? null)) {
                continue;
            }

            $dias = ($resolucion->dias === null || (int) $resolucion->dias < 1)
                ? 0
                : (int) $resolucion->dias;

            if ((int) ($resolucion->confirmada ?? 0) !== 1) {
                $pendientes[] = $resolucion;

                continue;
            }

            if ($dias > 0) {
                $ampliaciones[] = $resolucion;
                $total         += $dias;
            }
        }

        return [
            'inicial_dias'          => $plazoInicialDias,
            'ampliaciones'          => $ampliaciones,
            'pendientes'            => $pendientes,
            'total_ampliacion_dias' => $total,
            'vigente_dias'          => $plazoInicialDias === null ? null : $plazoInicialDias + $total,
            'tiene_ampliaciones'    => $total > 0,
        ];
    }

    /**
     * Fecha de finalización contractual: inicio + plazo vigente + días
     * neutralizados cerrados.
     *
     * Devuelve null cuando faltan datos iniciales válidos o cuando la
     * obra tiene una neutralización abierta: en ese caso la fecha no
     * puede presentarse como vigente mientras no se calcule el reinicio.
     *
     * Los días neutralizados corresponden a los períodos ya cerrados
     * (BLOQUE 5) y se suman a la misma convención de días corridos que
     * el plazo: fecha de inicio + N días, sin restar uno.
     */
    public static function fechaFinalizacion(
        ?string $fechaInicio,
        ?int $plazoVigenteDias,
        bool $neutralizacionAbierta = false,
        int $diasNeutralizados = 0
    ): ?string {
        if ($neutralizacionAbierta || $fechaInicio === null || $plazoVigenteDias === null) {
            return null;
        }

        if (self::formatearFecha($fechaInicio) === null) {
            return null;
        }

        return self::sumarDias($fechaInicio, $plazoVigenteDias + $diasNeutralizados);
    }

    /**
     * Fecha prevista para la recepción definitiva.
     *
     * La recepción provisoria es el día 0 del plazo de conservación: la
     * fecha prevista se obtiene sumando los días corridos del plazo a la
     * fecha de recepción provisoria. Por ejemplo, una recepción provisoria
     * del 23/10/2026 con 180 días de conservación prevé la definitiva para
     * el 21/04/2027.
     *
     * Devuelve null si la fecha es inválida o si el plazo no es positivo.
     */
    public static function fechaPrevistaDefinitiva(string $fechaProvisoria, int $plazoConservacionDias): ?string
    {
        if ($plazoConservacionDias <= 0 || self::formatearFecha($fechaProvisoria) === null) {
            return null;
        }

        return self::sumarDias($fechaProvisoria, $plazoConservacionDias);
    }

    /**
     * Indica si una recepción definitiva puede registrarse en la fecha dada.
     *
     * La definitiva se habilita desde la fecha prevista inclusive; antes de
     * ese día la recepción no corresponde. Devuelve false si alguna fecha es
     * inválida o si el plazo de conservación no es positivo.
     */
    public static function permiteRecepcionDefinitiva(
        string $fechaProvisoria,
        int $plazoConservacionDias,
        string $fechaDefinitiva
    ): bool {
        $fechaPrevista = self::fechaPrevistaDefinitiva($fechaProvisoria, $plazoConservacionDias);

        if ($fechaPrevista === null || self::formatearFecha($fechaDefinitiva) === null) {
            return false;
        }

        return strcmp($fechaDefinitiva, $fechaPrevista) >= 0;
    }

    /**
     * Diferencia en días corridos entre dos fechas almacenadas (Y-m-d).
     *
     * Devuelve la cantidad de días entre `desde` y `hasta` sin incluir
     * ningún día extra: del 01/03/2026 al 11/03/2026 son 10 días. Puede
     * ser negativa si `hasta` es anterior a `desde`; los consumidores
     * deciden cómo tratarla. Devuelve null si alguna fecha es inválida.
     */
    public static function diasEntre(?string $desde, ?string $hasta): ?int
    {
        $desde = trim((string) $desde);
        $hasta = trim((string) $hasta);

        if ($desde === '' || $hasta === '') {
            return null;
        }

        $inicio = DateTime::createFromFormat('Y-m-d', $desde);
        $fin    = DateTime::createFromFormat('Y-m-d', $hasta);

        if ($inicio === false || $fin === false) {
            return null;
        }

        /* Comparación estricta: rechaza fechas como 2026-02-31, que PHP
           desplazaría automáticamente. */
        if ($inicio->format('Y-m-d') !== $desde || $fin->format('Y-m-d') !== $hasta) {
            return null;
        }

        $diferencia = $inicio->diff($fin);

        return $diferencia->invert === 1
            ? -(int) $diferencia->days
            : (int) $diferencia->days;
    }

    /**
     * Días neutralizados de un período.
     *
     * Un período abierto (`fecha_reinicio` nula) no aporta todavía una
     * cantidad definitiva y devuelve null. El día del reinicio no se
     * cuenta: se computa la diferencia entre la fecha de inicio de la
     * neutralización y la de reinicio. Devuelve null si el período tiene
     * una duración negativa o fechas inválidas.
     */
    public static function diasNeutralizados(?string $fechaInicio, ?string $fechaReinicio): ?int
    {
        if ($fechaReinicio === null || trim((string) $fechaReinicio) === '') {
            return null;
        }

        $dias = self::diasEntre($fechaInicio, $fechaReinicio);

        if ($dias === null || $dias < 0) {
            return null;
        }

        return $dias;
    }

    /**
     * Total de días neutralizados acumulados en los períodos cerrados.
     *
     * Los períodos abiertos no suman. Es un dato derivado: se recalcula
     * desde las filas almacenadas y nunca se persiste.
     *
     * @param list<object> $periodos Filas con `fecha_inicio` y `fecha_reinicio`.
     */
    public static function totalNeutralizado(array $periodos): int
    {
        $total = 0;

        foreach ($periodos as $periodo) {
            $dias = self::diasNeutralizados(
                $periodo->fecha_inicio ?? null,
                $periodo->fecha_reinicio ?? null
            );

            if ($dias !== null) {
                $total += $dias;
            }
        }

        return $total;
    }
}
