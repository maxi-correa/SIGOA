<?php

namespace App\Libraries;

use App\Models\CertificadoModel;
use App\Models\ObraModel;

/**
 * Cálculos y reglas de certificación de obra.
 *
 * Los importes derivados (anticipo total, saldo, avances) no se persisten:
 * se obtienen de la configuración de `obras` y de los certificados emitidos.
 */
class CertificacionObra
{
    public const DECIMALES_IMPORTE = 3;

    public const DECIMALES_PORCENTAJE = 3;

    public const DECIMALES_PRESENTACION = 2;

    /**
     * Meses del certificado (número almacenado => etiqueta).
     *
     * @return array<int, string>
     */
    public static function meses(): array
    {
        return [
            1  => 'Enero',
            2  => 'Febrero',
            3  => 'Marzo',
            4  => 'Abril',
            5  => 'Mayo',
            6  => 'Junio',
            7  => 'Julio',
            8  => 'Agosto',
            9  => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre',
        ];
    }

    public static function nombreMes(int $mes): string
    {
        return self::meses()[$mes] ?? (string) $mes;
    }

    public static function tieneAnticipo(object $obra): bool
    {
        return self::esVerdadero($obra->tiene_anticipo_financiero ?? 0);
    }

    public static function tieneFondo(object $obra): bool
    {
        return self::esVerdadero($obra->tiene_fondo_reparo ?? 0);
    }

    public static function fondoConPoliza(object $obra): bool
    {
        return self::esVerdadero($obra->fondo_reparo_con_poliza ?? null);
    }

    /**
     * La configuración está confirmada cuando los importes obligatorios
     * están cargados y la regla de anticipo/fondo es válida.
     *
     * No se agrega un campo de confirmación: se deriva de `obras`.
     */
    public static function estaConfirmada(object $obra): bool
    {
        if (! self::importePositivo($obra->presupuesto_oficial ?? null)) {
            return false;
        }

        if (! self::importePositivo($obra->monto_contrato ?? null)) {
            return false;
        }

        $errores = (new ObraModel())->validarConfiguracionEconomica([
            'tiene_anticipo_financiero'      => $obra->tiene_anticipo_financiero ?? 0,
            'porcentaje_anticipo_financiero' => $obra->porcentaje_anticipo_financiero ?? null,
            'tiene_fondo_reparo'             => $obra->tiene_fondo_reparo ?? 0,
            'porcentaje_fondo_reparo'        => $obra->porcentaje_fondo_reparo ?? null,
            'fondo_reparo_con_poliza'        => $obra->fondo_reparo_con_poliza ?? null,
        ]);

        return $errores === [];
    }

    public static function estaBloqueada(int $cantidadCertificados): bool
    {
        return $cantidadCertificados > 0;
    }

    /**
     * Anticipo total = presupuesto oficial × porcentaje / 100.
     *
     * Devuelve null cuando la obra no tiene anticipo financiero.
     */
    public static function anticipoTotal(object $obra): ?float
    {
        if (! self::tieneAnticipo($obra)) {
            return null;
        }

        if (! self::importePositivo($obra->presupuesto_oficial ?? null)) {
            return null;
        }

        $porcentaje = $obra->porcentaje_anticipo_financiero ?? null;

        if (! is_numeric($porcentaje)) {
            return null;
        }

        return self::redondearImporte(
            (float) $obra->presupuesto_oficial * (float) $porcentaje / 100
        );
    }

    /**
     * @param list<object> $certificados
     */
    public static function descuentoAnticipoAcumulado(array $certificados): float
    {
        $suma = 0.0;

        foreach ($certificados as $certificado) {
            $suma += (float) ($certificado->descuento_anticipo ?? 0);
        }

        return self::redondearImporte($suma);
    }

    /**
     * Calcula los importes persistibles de un certificado.
     *
     * El descuento teórico de anticipo es el porcentaje configurado sobre
     * el monto bruto del certificado. El tope es el anticipo total derivado
     * del presupuesto oficial, descontando lo ya aplicado en certificados
     * anteriores. El cliente no puede imponer estos valores.
     *
     * @param list<object> $anteriores
     *
     * @return array{
     *     descuento_anticipo: float,
     *     estado_anticipo: ?string,
     *     retencion_fondo_reparo: float,
     *     monto_neto: float,
     *     avance_mensual: ?float
     * }
     */
    public static function calcularValoresCertificado(object $obra, array $anteriores, float $montoBruto): array
    {
        $montoBruto = self::redondearImporte($montoBruto);
        $descuento  = 0.0;
        $estado     = null;

        if (self::tieneAnticipo($obra)) {
            $total      = self::anticipoTotal($obra) ?? 0.0;
            $acumulado  = self::descuentoAnticipoAcumulado($anteriores);
            $saldo      = self::redondearImporte($total - $acumulado);
            $porcentaje = (float) ($obra->porcentaje_anticipo_financiero ?? 0);
            $teorico    = self::redondearImporte($montoBruto * $porcentaje / 100);

            if ($saldo <= 0) {
                $descuento = 0.0;
                $estado    = CertificadoModel::ESTADO_ANTICIPO_YA_LIQUIDADO;
            } elseif ($teorico > $saldo) {
                $descuento = $saldo;
                $estado    = CertificadoModel::ESTADO_ANTICIPO_REMANENTE;
            } else {
                $descuento = $teorico;
                $estado    = CertificadoModel::ESTADO_ANTICIPO_NORMAL;
            }
        }

        $retencion = 0.0;

        if (self::tieneFondo($obra) && ! self::fondoConPoliza($obra)) {
            $retencion = self::redondearImporte(
                $montoBruto * (float) ($obra->porcentaje_fondo_reparo ?? 0) / 100
            );
        }

        return [
            'descuento_anticipo'     => $descuento,
            'estado_anticipo'        => $estado,
            'retencion_fondo_reparo' => $retencion,
            'monto_neto'             => self::redondearImporte($montoBruto - $descuento - $retencion),
            'avance_mensual'         => self::avanceMensual($obra, $montoBruto),
        ];
    }

    public static function avanceMensual(object $obra, float $montoBruto): ?float
    {
        if (! self::importePositivo($obra->monto_contractual_vigente ?? null)) {
            return null;
        }

        return self::redondearPorcentaje(
            $montoBruto / (float) $obra->monto_contractual_vigente * 100
        );
    }

    /**
     * Agrega avance mensual y acumulado derivados a cada certificado.
     *
     * @param list<object> $certificados
     *
     * @return list<object>
     */
    public static function conAvances(object $obra, array $certificados): array
    {
        $acumulado = 0.0;
        $filas     = [];

        foreach ($certificados as $certificado) {
            $mensual = self::avanceMensual($obra, (float) $certificado->monto_bruto);
            $fila    = clone $certificado;

            $fila->avance_mensual   = $mensual;
            $fila->avance_acumulado = null;

            if ($mensual !== null) {
                $acumulado              = self::redondearPorcentaje($acumulado + $mensual);
                $fila->avance_acumulado = $acumulado;
            }

            $filas[] = $fila;
        }

        return $filas;
    }

    /**
     * @param array<string, mixed> $datos
     *
     * @return list<string>
     */
    public static function validarConfiguracionParaCertificar(array $datos): array
    {
        $errores = [];

        if (! self::importePositivo($datos['presupuesto_oficial'] ?? null)) {
            $errores[] = 'El presupuesto oficial es obligatorio y debe ser mayor a cero.';
        }

        if (! self::importePositivo($datos['monto_contrato'] ?? null)) {
            $errores[] = 'El monto de contrato original es obligatorio y debe ser mayor a cero.';
        }

        return array_merge($errores, (new ObraModel())->validarConfiguracionEconomica($datos));
    }

    /**
     * @return list<string>
     */
    public static function validarDatosCertificado(mixed $mes, mixed $anio, mixed $montoBruto): array
    {
        $errores = [];

        if (! is_numeric($mes) || (int) $mes < 1 || (int) $mes > 12) {
            $errores[] = 'El mes del certificado no es válido.';
        }

        $anioActual = (int) date('Y');

        if (! is_numeric($anio) || (int) $anio < 2000 || (int) $anio > ($anioActual + 1)) {
            $errores[] = 'El año del certificado no es válido.';
        }

        if (! self::importePositivo($montoBruto)) {
            $errores[] = 'El monto bruto es obligatorio y debe ser mayor a cero.';
        }

        return $errores;
    }

    /**
     * El período (año + mes) debe ser estrictamente posterior al del último certificado.
     * El primer certificado no tiene restricción cronológica previa.
     *
     * @return list<string>
     */
    public static function validarPeriodoPosterior(mixed $mes, mixed $anio, ?object $ultimo): array
    {
        if ($ultimo === null) {
            return [];
        }

        if (! is_numeric($mes) || (int) $mes < 1 || (int) $mes > 12) {
            return [];
        }

        if (! is_numeric($anio)) {
            return [];
        }

        $mes  = (int) $mes;
        $anio = (int) $anio;

        $nuevo  = ($anio * 12) + $mes;
        $previo = ((int) $ultimo->anio * 12) + (int) $ultimo->mes;

        if ($nuevo > $previo) {
            return [];
        }

        $periodo = self::nombreMes((int) $ultimo->mes) . ' ' . (int) $ultimo->anio;

        return [
            'El período del certificado debe ser posterior al último certificado registrado (' . $periodo . ').',
        ];
    }

    public static function parseImporte(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $texto = trim((string) $valor);
        $texto = str_replace(['$', ' '], '', $texto);

        if ($texto === '') {
            return null;
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

        if ($numero < 0) {
            return null;
        }

        return number_format($numero, self::DECIMALES_IMPORTE, '.', '');
    }

    public static function parsePorcentaje(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $texto = str_replace('%', '', (string) $valor);

        return self::parseImporte($texto);
    }

    public static function formatearImporte(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }

        return '$ ' . number_format((float) $valor, self::DECIMALES_PRESENTACION, ',', '.');
    }

    public static function formatearDeduccion(mixed $valor): string
    {
        $importe = ($valor === null || $valor === '') ? 0.0 : (float) $valor;

        return '-$ ' . number_format($importe, self::DECIMALES_PRESENTACION, ',', '.');
    }

    public static function formatearPorcentaje(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }

        return number_format((float) $valor, self::DECIMALES_PORCENTAJE, ',', '.') . ' %';
    }

    public static function formatearNumeroImporte(mixed $valor): string
    {
        $importe = ($valor === null || $valor === '') ? 0.0 : (float) $valor;

        return number_format($importe, self::DECIMALES_PRESENTACION, ',', '.');
    }

    public static function estadoAnticipoUi(?string $estado): string
    {
        if ($estado === null || $estado === '') {
            return '---';
        }

        return match ($estado) {
            CertificadoModel::ESTADO_ANTICIPO_NORMAL       => 'NORMAL',
            CertificadoModel::ESTADO_ANTICIPO_REMANENTE    => 'REMANENTE',
            CertificadoModel::ESTADO_ANTICIPO_YA_LIQUIDADO => 'YA LIQUIDADO',
            default                                        => str_replace('_', ' ', $estado),
        };
    }

    public static function redondearImporte(float $valor): float
    {
        return round($valor, self::DECIMALES_IMPORTE);
    }

    public static function redondearPorcentaje(float $valor): float
    {
        return round($valor, self::DECIMALES_PORCENTAJE);
    }

    public static function importePositivo(mixed $valor): bool
    {
        if ($valor === null || $valor === '') {
            return false;
        }

        if (! is_numeric($valor)) {
            return false;
        }

        return (float) $valor > 0;
    }

    private static function esVerdadero(mixed $valor): bool
    {
        return $valor === true || $valor === 1 || $valor === '1';
    }
}
