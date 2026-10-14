<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Neutralizaciones de obra (períodos de suspensión de la ejecución).
 *
 * Una neutralización nace ya confirmada: se registra dentro de una única
 * transacción que crea la fila y cambia el estado de la obra a
 * NEUTRALIZADA (BLOQUE 5). El reinicio completa `fecha_reinicio` en la
 * fila abierta (`fecha_reinicio IS NULL`).
 *
 * Una vez registrada, la neutralización es inmutable: no puede editarse
 * ni eliminarse. El reinicio solo se aplica a la fila abierta, por lo que
 * tampoco puede alterar períodos ya cerrados. Estos bloqueos viven en el
 * modelo, no solo en la interfaz.
 *
 * El cómputo de días y la fecha de finalización son datos derivados que
 * se resuelven en `PlazoObra`; este modelo solo persiste y consulta.
 */
class NeutralizacionModel extends Model
{
    protected $table = 'neutralizaciones';

    protected $primaryKey = 'id';

    protected $returnType = 'object';

    protected $allowedFields = [
        'obra_id',
        'numero',
        'fecha_inicio',
        'fecha_reinicio',
        'documento_id',
        'observaciones',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = false;

    protected $useSoftDeletes = false;

    /**
     * Períodos de neutralización de una obra, ordenados por número.
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

    /**
     * Neutralización abierta de la obra, o null si no hay ninguna.
     *
     * Una neutralización abierta es la que aún no registra fecha de
     * reinicio (`fecha_reinicio IS NULL`). Nunca puede haber más de una
     * por obra (garantizado en el controlador/transacción y reforzado por
     * la restricción de base de datos).
     */
    public function abierta(int $obraId): ?object
    {
        return $this
            ->where('obra_id', $obraId)
            ->where('fecha_reinicio', null)
            ->orderBy('id', 'DESC')
            ->first();
    }

    /**
     * Indica si la obra tiene una neutralización abierta.
     */
    public function tieneAbierta(int $obraId): bool
    {
        return $this->abierta($obraId) !== null;
    }

    /**
     * Próximo número correlativo de neutralización para la obra.
     *
     * La numeración comienza nuevamente en cada obra.
     */
    public function proximoNumero(int $obraId): int
    {
        $fila = $this
            ->selectMax('numero', 'maximo')
            ->where('obra_id', $obraId)
            ->first();

        return (int) ($fila->maximo ?? 0) + 1;
    }

    /**
     * Fecha de reinicio del último período cerrado de la obra.
     *
     * Devuelve `null` si la obra no tiene ningún período cerrado. La
     * fecha se usa para impedir que una nueva neutralización se solape
     * con los períodos ya computados (BLOQUE 5). `MAX` ignora los
     * `NULL`, por lo que un período abierto no interfiere.
     */
    public function ultimaFechaReinicio(int $obraId): ?string
    {
        $fila = $this
            ->selectMax('fecha_reinicio', 'ultima')
            ->where('obra_id', $obraId)
            ->first();

        $ultima = $fila->ultima ?? null;

        return ($ultima === null || $ultima === '') ? null : (string) $ultima;
    }

    /**
     * Persiste una neutralización nueva (nace confirmada y abierta).
     *
     * @param array<string, mixed> $datos
     */
    public function crear(int $obraId, array $datos): bool
    {
        $ahora = date('Y-m-d H:i:s');

        return (bool) $this->insert([
            'obra_id'        => $obraId,
            'numero'         => (int) $datos['numero'],
            'fecha_inicio'   => $datos['fecha_inicio'],
            'fecha_reinicio' => null,
            'documento_id'   => $datos['documento_id'] ?? null,
            'observaciones'  => $datos['observaciones'] ?? null,
            'created_at'     => $ahora,
            'updated_at'     => $ahora,
        ], true);
    }

    /**
     * Completa la fecha de reinicio de una neutralización abierta.
     *
     * El `WHERE fecha_reinicio IS NULL` garantiza que no pueda reiniciarse
     * una fila ya cerrada ni registrarse dos reinicios para el mismo
     * período. Devuelve false si la fila no estaba abierta.
     */
    public function reiniciar(int $id, string $fechaReinicio): bool
    {
        $this->db->table($this->table)
            ->where('id', $id)
            ->where('fecha_reinicio', null)
            ->update([
                'fecha_reinicio' => $fechaReinicio,
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);

        return $this->db->affectedRows() === 1;
    }

    /**
     * Bloquea cualquier escritura directa sobre el modelo.
     *
     * Las neutralizaciones se crean y se reinician solo a través de
     * `crear()` / `reiniciar()`; no existe edición ni eliminación de
     * períodos confirmados.
     *
     * @param array<string, mixed>|object|null $row
     */
    public function update($id = null, $row = null): bool
    {
        return false;
    }

    /**
     * Impide eliminar neutralizaciones.
     */
    public function delete($id = null, bool $purge = false): bool
    {
        return false;
    }

    /**
     * Bloquea la inserción masiva.
     *
     * Las neutralizaciones solo se crean de a una a través de `crear()`,
     * dentro de la transacción que también cambia el estado de la obra.
     * La inserción masiva eludiría ese ciclo, por lo que se rechaza.
     *
     * @param list<object|array<string, mixed>>|null $set
     *
     * @return false
     */
    public function insertBatch(?array $set = null, ?bool $escape = null, int $batchSize = 100, bool $testing = false)
    {
        return false;
    }

    /**
     * Bloquea la edición masiva.
     *
     * Una neutralización registrada es inmutable; la edición masiva
     * permitiría alterar o cerrar períodos confirmados sin pasar por
     * `reiniciar()`. Se rechaza.
     *
     * @param list<object|array<string, mixed>>|null $set
     *
     * @return false
     */
    public function updateBatch(?array $set = null, ?string $index = null, int $batchSize = 100, bool $returnSQL = false)
    {
        return false;
    }
}
