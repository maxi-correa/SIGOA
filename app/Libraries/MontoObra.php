<?php

namespace App\Libraries;

/**
 * Cálculo del monto contractual vigente de la obra.
 *
 * Monto vigente = monto contractual original + suma de los ajustes
 * económicos de los cuadros comparativos confirmados.
 *
 * Los importes se suman en milésimas enteras (escala 1000, equivalente a
 * DECIMAL(15,3)) para evitar errores de coma flotante. No persiste nada:
 * el resultado es un dato derivado que se recalcula desde las fuentes
 * (monto de contrato original y resoluciones confirmadas), nunca sumando
 * incrementalmente sobre un valor vigente previo.
 */
class MontoObra
{
    /** Cantidad de decimales de los importes monetarios. */
    public const DECIMALES = 3;

    /** Cantidad de decimales del porcentaje de aumento o disminución. */
    public const DECIMALES_PORCENTAJE = 2;

    /** Factor de escala interna (milésimas). */
    private const ESCALA = 1000;

    /**
     * Calcula la composición del monto contractual vigente.
     *
     * Solo aportan las resoluciones cuyo tipo ajusta el monto
     * (`ResolucionObra::ajustaMonto`), que estén confirmadas y con un monto
     * distinto de cero; las pendientes se devuelven separadas para poder
     * mostrarlas sin confundirlas con ajustes aprobados. Una resolución
     * combinada (cuadro + ampliación) aporta su monto una sola vez.
     *
     * @param string|null  $montoContrato Monto contractual original (DECIMAL(15,3)) o null.
     * @param list<object> $resoluciones  Resoluciones de la obra (tipo_nombre, monto, confirmada).
     *
     * @return array{
     *     contrato: string|null,
     *     ajustes: list<object>,
     *     pendientes: list<object>,
     *     porcentajes: list<string|null>,
     *     total_ajuste: string,
     *     vigente: string|null,
     *     tiene_ajustes: bool
     * }
     */
    public static function calcularVigente(?string $montoContrato, array $resoluciones): array
    {
        $contratoMilesimas = self::aMilesimas($montoContrato);

        $ajustes     = [];
        $pendientes  = [];
        $porcentajes = [];
        $total       = 0;

        foreach ($resoluciones as $resolucion) {
            if (! ResolucionObra::ajustaMonto($resolucion->tipo_nombre ?? null)) {
                continue;
            }

            $montoMilesimas = self::aMilesimas($resolucion->monto ?? null);

            if ((int) ($resolucion->confirmada ?? 0) !== 1) {
                $pendientes[] = $resolucion;

                continue;
            }

            if ($montoMilesimas === null || $montoMilesimas === 0) {
                continue;
            }

            $ajustes[] = $resolucion;
            $total    += $montoMilesimas;

            /* El porcentaje de cada ajuste se calcula siempre sobre el monto
               de contrato original, nunca sobre el vigente parcial previo. */
            $porcentajes[] = $contratoMilesimas === null
                ? null
                : self::formatearVariacionPorcentual($contratoMilesimas, $contratoMilesimas + $total);
        }

        return [
            'contrato'      => $contratoMilesimas === null ? null : self::aDecimal($contratoMilesimas),
            'ajustes'       => $ajustes,
            'pendientes'    => $pendientes,
            'porcentajes'   => $porcentajes,
            'total_ajuste'  => self::aDecimal($total),
            'vigente'       => $contratoMilesimas === null ? null : self::aDecimal($contratoMilesimas + $total),
            'tiene_ajustes' => $ajustes !== [],
        ];
    }

    /**
     * Porcentaje de aumento o disminución del monto vigente respecto del
     * monto de contrato original.
     *
     * Es la variación relativa: `(vigente - original) / original × 100`.
     * Devuelve null si falta el monto original, si no es numérico o si es
     * cero (no hay base de cálculo).
     */
    public static function porcentajeVariacion(?string $montoContrato, ?string $montoVigente): ?string
    {
        $original = self::aMilesimas($montoContrato);
        $vigente  = self::aMilesimas($montoVigente);

        if ($original === null || $vigente === null || $original === 0) {
            return null;
        }

        return self::formatearVariacionPorcentual($original, $vigente);
    }

    /**
     * Convierte un importe a milésimas enteras. Devuelve null si falta o
     * no es numérico.
     */
    private static function aMilesimas(mixed $valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (! is_numeric($valor)) {
            return null;
        }

        return (int) round((float) $valor * self::ESCALA);
    }

    /**
     * Formatea la variación porcentual entre el monto original y el vigente
     * (ambos en milésimas), con dos decimales y coma decimal.
     *
     * Se calcula en centésimas de porcentaje (enteras) para evitar errores de
     * coma flotante. Devuelve null si el monto original es cero: sin base de
     * cálculo el porcentaje es indeterminado.
     */
    private static function formatearVariacionPorcentual(int $originalMilesimas, int $vigenteMilesimas): ?string
    {
        if ($originalMilesimas === 0) {
            return null;
        }

        $centesimas = (int) round(
            (($vigenteMilesimas - $originalMilesimas) / $originalMilesimas) * 100 * 100
        );

        $negativo = $centesimas < 0;
        $absoluto = abs($centesimas);
        $entero   = intdiv($absoluto, 100);
        $decimal  = str_pad((string) ($absoluto % 100), 2, '0', STR_PAD_LEFT);

        return ($negativo ? '-' : '') . $entero . ',' . $decimal . ' %';
    }

    /**
     * Formatea milésimas enteras como DECIMAL con signo.
     */
    private static function aDecimal(int $milesimas): string
    {
        $negativo = $milesimas < 0;
        $absoluto = abs($milesimas);
        $entero   = intdiv($absoluto, self::ESCALA);
        $decimal  = str_pad((string) ($absoluto % self::ESCALA), self::DECIMALES, '0', STR_PAD_LEFT);

        return ($negativo ? '-' : '') . $entero . '.' . $decimal;
    }
}
