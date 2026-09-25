<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Registro de operaciones de sincronización en el servidor (§52.16).
 *
 * La cola local (IndexedDB) es el estado operativo del dispositivo;
 * `operaciones_sincronizacion` es el **registro en el servidor** de las
 * altas de sincronización (trazabilidad). `registro_id` contiene el UUID
 * de la entidad sincronizada.
 *
 * Estados (§52.13): PENDIENTE / PROCESADA / ERROR, con `intentos`,
 * `ultimo_intento` y `error` para reintentos/trazabilidad.
 */
class OperacionSincronizacionModel extends Model
{
    public const ESTADO_PENDIENTE = 'PENDIENTE';

    public const ESTADO_PROCESADA = 'PROCESADA';

    public const ESTADO_ERROR = 'ERROR';

    protected $table = 'operaciones_sincronizacion';

    protected $primaryKey = 'id';

    protected $returnType = 'object';

    protected $allowedFields = [
        'usuario_id',
        'tipo_operacion',
        'entidad',
        'registro_id',
        'estado',
        'intentos',
        'ultimo_intento',
        'error',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;

    protected $createdField = 'created_at';

    protected $updatedField = 'updated_at';

    protected $useSoftDeletes = false;

    /**
     * Registra una operación de sincronización para trazabilidad.
     *
     * Devuelve el `id` de la operación creada. `registro_id` es el UUID de la
     * entidad (`inspecciones`/`fotografias`); `error` se usa cuando la
     * operación queda en ERROR.
     */
    public function registrar(
        int $usuarioId,
        string $tipoOperacion,
        string $entidad,
        string $registroId,
        string $estado,
        ?string $error = null,
        int $intentos = 1
    ): int|false {
        return $this->insert([
            'usuario_id'     => $usuarioId,
            'tipo_operacion' => $tipoOperacion,
            'entidad'        => $entidad,
            'registro_id'    => $registroId,
            'estado'         => $estado,
            'intentos'       => $intentos,
            'ultimo_intento' => date('Y-m-d H:i:s'),
            'error'          => $error,
        ], true);
    }
}