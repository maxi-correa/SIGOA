<?php

namespace App\Libraries;

/**
 * Reglas de negocio de las resoluciones (actos administrativos) de obra.
 *
 * Centraliza qué campos admite cada tipo de resolución y cómo se
 * interpretan el monto y los días. La misma tabla de reglas se usa en el
 * backend (validación y normalización) y se expone a la vista para
 * mostrar u ocultar los campos del formulario, evitando duplicar la
 * decisión en el JavaScript.
 *
 * El plazo original y el monto contractual original no se modifican aquí:
 * los trámites solo se conservan. La aplicación de ampliaciones al plazo
 * o al monto contractual vigente es una etapa posterior.
 */
class ResolucionObra
{
    /** Tipos de resolución contemplados explícitamente por las reglas. */
    public const TIPO_ADJUDICACION = 'ADJUDICACIÓN';

    public const TIPO_CUADRO_COMPARATIVO = 'APROBACIÓN DE CUADRO COMPARATIVO';

    public const TIPO_AMPLIACION_PLAZO = 'APROBACIÓN DE AMPLIACIÓN DE PLAZO';

    public const TIPO_CUADRO_Y_AMPLIACION = 'APROBACIÓN DE CUADRO COMPARATIVO Y AMPLIACIÓN DE PLAZO';

    public const TIPO_OTRO = 'OTRO';

    /** Estados de un campo del formulario según el tipo de resolución. */
    public const CAMPO_OCULTO = 'oculto';

    public const CAMPO_OPCIONAL = 'opcional';

    public const CAMPO_REQUERIDO = 'requerido';

    private const DECIMALES_IMPORTE = 3;

    /**
     * Reglas de monto y días de un tipo de resolución.
     *
     * @return array{monto: string, dias: string}
     */
    public static function reglas(?string $tipoNombre): array
    {
        $tipo = self::normalizarTipo($tipoNombre);

        return match ($tipo) {
            self::TIPO_CUADRO_COMPARATIVO => [
                'monto' => self::CAMPO_REQUERIDO,
                'dias'  => self::CAMPO_OCULTO,
            ],
            self::TIPO_AMPLIACION_PLAZO => [
                'monto' => self::CAMPO_OCULTO,
                'dias'  => self::CAMPO_REQUERIDO,
            ],
            self::TIPO_CUADRO_Y_AMPLIACION => [
                'monto' => self::CAMPO_REQUERIDO,
                'dias'  => self::CAMPO_REQUERIDO,
            ],
            /* ADJUDICACIÓN y OTRO son antecedentes administrativos
               informativos: no intervienen en cálculos económicos ni de
               plazo, por lo que no admiten monto ni días. */
            self::TIPO_ADJUDICACION,
            self::TIPO_OTRO => [
                'monto' => self::CAMPO_OCULTO,
                'dias'  => self::CAMPO_OCULTO,
            ],
            /* Cualquier tipo futuro no contemplado: admite monto y/o días,
               pero no puede quedar completamente vacío. */
            default => [
                'monto' => self::CAMPO_OPCIONAL,
                'dias'  => self::CAMPO_OPCIONAL,
            ],
        };
    }

    /**
     * Valida la combinación de monto y días para el tipo indicado.
     *
     * Un monto igual a cero y una cantidad de días igual a cero se
     * consideran "no informados": los valores ausentes se persisten como
     * NULL, no como cero.
     *
     * @return list<string>
     */
    public static function validar(mixed $monto, ?int $dias, ?string $tipoNombre): array
    {
        $reglas = self::reglas($tipoNombre);
        $errores = [];

        $montoInformado = $monto !== null && (float) $monto !== 0.0;
        $diasInformados = $dias !== null && $dias !== 0;

        if ($reglas['monto'] === self::CAMPO_OCULTO && $montoInformado) {
            $errores[] = 'El tipo de resolución seleccionado no admite un monto.';
        }

        if ($reglas['dias'] === self::CAMPO_OCULTO && $diasInformados) {
            $errores[] = 'El tipo de resolución seleccionado no admite una cantidad de días.';
        }

        if ($reglas['monto'] === self::CAMPO_REQUERIDO && ! $montoInformado) {
            $errores[] = 'El tipo de resolución seleccionado requiere un monto distinto de cero.';
        }

        if ($reglas['dias'] === self::CAMPO_REQUERIDO && ! ($dias !== null && $dias > 0)) {
            $errores[] = 'El tipo de resolución seleccionado requiere una cantidad de días mayor a cero.';
        }

        if (
            $reglas['monto'] === self::CAMPO_OPCIONAL
            && $reglas['dias'] === self::CAMPO_OPCIONAL
            && ! $montoInformado
            && ! $diasInformados
        ) {
            $errores[] = 'Debe registrar al menos un monto o una cantidad de días.';
        }

        return $errores;
    }

    /**
     * Normaliza un monto ingresado a formato de almacenamiento DECIMAL(15,3).
     *
     * Admite separadores de miles y decimales con coma o punto, y montos
     * negativos (por ejemplo, una deductiva en un cuadro comparativo).
     * Devuelve null si el valor está vacío o no es numérico.
     */
    public static function parseMonto(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $texto = trim((string) $valor);
        $texto = str_replace(['$', ' '], '', $texto);

        if ($texto === '') {
            return null;
        }

        $negativo = false;

        if (str_starts_with($texto, '-')) {
            $negativo = true;
            $texto    = substr($texto, 1);
        }

        if (str_contains($texto, ',') && str_contains($texto, '.')) {
            $texto = str_replace('.', '', $texto);
            $texto = str_replace(',', '.', $texto);
        } elseif (str_contains($texto, ',')) {
            $texto = str_replace(',', '.', $texto);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $texto) === 1) {
            $texto = str_replace('.', '', $texto);
        }

        if (! is_numeric($texto)) {
            return null;
        }

        $numero = (float) $texto;

        if ($negativo) {
            $numero = -$numero;
        }

        return number_format($numero, self::DECIMALES_IMPORTE, '.', '');
    }

    /**
     * Normaliza una cantidad de días a entero no negativo.
     *
     * Devuelve null si está vacía o no es un entero válido (se rechazan
     * decimales y signos).
     */
    public static function parseDias(mixed $valor): ?int
    {
        if ($valor === null) {
            return null;
        }

        $texto = trim((string) $valor);

        if ($texto === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $texto) !== 1) {
            return null;
        }

        return (int) $texto;
    }

    /**
     * Normaliza un número de resolución a entero positivo.
     */
    public static function parseNumero(mixed $valor): ?int
    {
        $entero = self::parseDias($valor);

        if ($entero === null || $entero < 1) {
            return null;
        }

        return $entero;
    }

    /**
     * Normaliza un año a entero de cuatro dígitos plausible.
     */
    public static function parseAnio(mixed $valor): ?int
    {
        $entero = self::parseDias($valor);

        if ($entero === null || $entero < 1000 || $entero > 9999) {
            return null;
        }

        return $entero;
    }

    /**
     * Formatea una cantidad de días para presentación.
     */
    public static function formatearDias(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }

        return (int) $valor . ' días';
    }

    /**
     * Indica si el trámite tiene todos los datos de identificación para
     * poder confirmarse.
     */
    public static function identificacionCompleta(?int $numero, ?int $anio, ?string $fechaResolucion): bool
    {
        return $numero !== null && $anio !== null && $fechaResolucion !== null;
    }

    /**
     * Indica si el tipo funciona como antecedente administrativo
     * informativo y, por lo tanto, exige observaciones al confirmarse.
     *
     * ADJUDICACIÓN y OTRO no intervienen en cálculos de monto ni de plazo;
     * su contenido relevante son las observaciones.
     */
    public static function requiereObservaciones(?string $tipoNombre): bool
    {
        return in_array(
            self::normalizarTipo($tipoNombre),
            [self::TIPO_ADJUDICACION, self::TIPO_OTRO],
            true
        );
    }

    /**
     * Indica si el trámite puede confirmarse: siempre exige número, año y
     * fecha; los tipos informativos requieren además observaciones.
     */
    public static function puedeConfirmarse(
        ?string $tipoNombre,
        ?int $numero,
        ?int $anio,
        ?string $fechaResolucion,
        ?string $observaciones
    ): bool {
        if (! self::identificacionCompleta($numero, $anio, $fechaResolucion)) {
            return false;
        }

        if (self::requiereObservaciones($tipoNombre)) {
            return trim((string) $observaciones) !== '';
        }

        return true;
    }

    private static function normalizarTipo(?string $tipo): string
    {
        return mb_strtoupper(trim((string) $tipo));
    }
}
