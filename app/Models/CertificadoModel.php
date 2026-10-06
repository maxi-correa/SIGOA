<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Certificados de obra.
 *
 * Conserva los importes efectivamente aplicados en cada certificado.
 * La configuración permanente (presupuesto, anticipo, fondo de reparo)
 * pertenece a `obras`. El cálculo de descuentos, estados y neto se
 * implementará en una fase posterior.
 */
class CertificadoModel extends Model
{
    public const ESTADO_ANTICIPO_NORMAL = 'NORMAL';

    public const ESTADO_ANTICIPO_REMANENTE = 'REMANENTE';

    public const ESTADO_ANTICIPO_YA_LIQUIDADO = 'YA_LIQUIDADO';

    protected $table = 'certificados';

    protected $primaryKey = 'id';

    protected $returnType = 'object';

    protected $allowedFields = [
        'obra_id',
        'numero',
        'mes',
        'anio',
        'fecha_emision',
        'monto_bruto',
        'descuento_anticipo',
        'estado_anticipo',
        'retencion_fondo_reparo',
        'monto_neto',
        'documento_id',
        'observaciones',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = false;

    protected $useSoftDeletes = false;

    /**
     * Estados de anticipo admitidos en un certificado.
     *
     * @return list<string>
     */
    public static function estadosAnticipo(): array
    {
        return [
            self::ESTADO_ANTICIPO_NORMAL,
            self::ESTADO_ANTICIPO_REMANENTE,
            self::ESTADO_ANTICIPO_YA_LIQUIDADO,
        ];
    }

    public static function esEstadoAnticipoValido(?string $estado): bool
    {
        if ($estado === null || $estado === '') {
            return true;
        }

        return in_array($estado, self::estadosAnticipo(), true);
    }
}
