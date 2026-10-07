<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Certificados de obra.
 *
 * Conserva los importes efectivamente aplicados en cada certificado.
 * La configuración permanente (presupuesto, anticipo, fondo de reparo)
 * pertenece a `obras`. Los descuentos, estados y el neto se calculan
 * en servidor al emitir cada certificado.
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

    /**
     * Certificados de una obra, en orden de emisión.
     *
     * @return list<object>
     */
    public function listarPorObra(int $obraId): array
    {
        return $this
            ->where('obra_id', $obraId)
            ->orderBy('numero', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    public function contarPorObra(int $obraId): int
    {
        return $this->where('obra_id', $obraId)->countAllResults();
    }

    public function proximoNumero(int $obraId): int
    {
        $fila = $this
            ->selectMax('numero')
            ->where('obra_id', $obraId)
            ->get()
            ->getRow();

        $maximo = ($fila !== null && $fila->numero !== null) ? (int) $fila->numero : 0;

        return $maximo + 1;
    }

    public function ultimoPorObra(int $obraId): ?object
    {
        return $this
            ->where('obra_id', $obraId)
            ->orderBy('numero', 'DESC')
            ->orderBy('id', 'DESC')
            ->first();
    }

    /**
     * Persiste un certificado ya calculado.
     *
     * @param array<string, mixed> $datos
     */
    public function crear(array $datos): int
    {
        $ahora = date('Y-m-d H:i:s');

        return (int) $this->insert([
            'obra_id'                => $datos['obra_id'],
            'numero'                 => $datos['numero'],
            'mes'                    => $datos['mes'],
            'anio'                   => $datos['anio'],
            'fecha_emision'          => $datos['fecha_emision'] ?? null,
            'monto_bruto'            => $datos['monto_bruto'],
            'descuento_anticipo'     => $datos['descuento_anticipo'],
            'estado_anticipo'        => $datos['estado_anticipo'],
            'retencion_fondo_reparo' => $datos['retencion_fondo_reparo'],
            'monto_neto'             => $datos['monto_neto'],
            'documento_id'           => $datos['documento_id'] ?? null,
            'observaciones'          => $datos['observaciones'] ?? null,
            'created_at'             => $ahora,
            'updated_at'             => $ahora,
        ], true);
    }
}
