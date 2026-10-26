<?php

namespace App\Models;

use CodeIgniter\Model;

class DocumentoModel extends Model
{
    protected $table = 'documentos';

    protected $primaryKey = 'id';

    protected $returnType = 'object';

    protected $useTimestamps = true;

    protected $dateFormat = 'datetime';

    protected $createdField = 'created_at';

    protected $updatedField = 'updated_at';

    protected $allowedFields = [
        'obra_id',
        'tipo_documento_id',
        'nombre_archivo_original',
        'nombre_archivo',
        'ruta_relativa',
        'extension',
        'mime_type',
        'tamano_bytes',
        'fecha_documento',
        'observaciones',
        'usuario_id_carga',
        'neutralizacion_id',
        'reinicio_id',
        'version',
        'es_version_actual',
        'documento_padre_id',
    ];

    /**
     * Lista documentos de una obra con su tipo, usuario de carga y el
     * evento vinculado.
     *
     * La neutralización y el reinicio viven en la misma tabla
     * (`neutralizaciones`): `fecha_inicio` es la fecha de neutralización y
     * `fecha_reinicio` la fecha del reinicio del período. Los alias
     * `n` y `r` distinguen cada vínculo (`neutralizacion_id` y
     * `reinicio_id`).
     */
    public function listarPorObra(int $obraId): array
    {
        return $this->builderPorObra($obraId)
            ->orderBy('td.tipo_documento', 'ASC')
            ->orderBy('d.created_at', 'ASC')
            ->get()
            ->getResultObject();
    }

    /**
     * Lista únicamente las versiones vigentes de los documentos de una obra.
     *
     * Es la consulta que usa el inspector: solo debe ver la versión actual
     * de cada documento lógico, no el historial de versiones reemplazadas.
     */
    public function listarVigentesPorObra(int $obraId): array
    {
        return $this->builderPorObra($obraId)
            ->where('d.es_version_actual', 1)
            ->orderBy('td.tipo_documento', 'ASC')
            ->orderBy('d.created_at', 'ASC')
            ->get()
            ->getResultObject();
    }

    /**
     * Versiones vigentes de un tipo de documento dentro de una obra.
     *
     * Se usa para ofrecer como vínculo las actas de recepción vigentes.
     *
     * @return list<object>
     */
    public function listarVigentesPorTipo(int $obraId, int $tipoDocumentoId): array
    {
        return $this->where('obra_id', $obraId)
            ->where('tipo_documento_id', $tipoDocumentoId)
            ->where('es_version_actual', 1)
            ->orderBy('created_at', 'ASC')
            ->findAll();
    }

    /**
     * Consulta base de los documentos de una obra, con los datos de tipo,
     * usuario de carga y evento vinculado ya resueltos.
     */
    private function builderPorObra(int $obraId)
    {
        return $this->db
            ->table('documentos d')
            ->select('d.*, td.tipo_documento, u.nombre as usuario_nombre, u.apellido as usuario_apellido, '
                      . 'n.numero as neutralizacion_numero, n.fecha_inicio as fecha_neutralizacion, '
                      . 'r.numero as reinicio_numero, r.fecha_reinicio as fecha_reinicio')
            ->join('tipos_documento td', 'td.id = d.tipo_documento_id', 'left')
            ->join('usuarios u', 'u.id = d.usuario_id_carga', 'left')
            ->join('neutralizaciones n', 'n.id = d.neutralizacion_id', 'left')
            ->join('neutralizaciones r', 'r.id = d.reinicio_id', 'left')
            ->where('d.obra_id', $obraId);
    }

    /**
     * Obtiene la versión actual de un documento dado un tipo y obra.
     */
    public function obtenerVersionActual(int $obraId, int $tipoDocumentoId, ?int $neutralizacionId = null, ?int $reinicioId = null): ?object
    {
        $builder = $this->db->table('documentos')
            ->where('obra_id', $obraId)
            ->where('tipo_documento_id', $tipoDocumentoId)
            ->where('es_version_actual', 1);

        if ($neutralizacionId !== null) {
            $builder->where('neutralizacion_id', $neutralizacionId);
        } else {
            $builder->where('neutralizacion_id IS NULL', null, false);
        }

        if ($reinicioId !== null) {
            $builder->where('reinicio_id', $reinicioId);
        } else {
            $builder->where('reinicio_id IS NULL', null, false);
        }

        $builder->limit(1);

        return $builder->get()->getRow();
    }

    /**
     * Registra una nueva versión vigente de un documento.
     *
     * La versión reemplazada se conserva con `es_version_actual = 0` (nunca
     * se elimina) y la nueva hereda obra, tipo y eventos del documento
     * reemplazado. Todo ocurre en una transacción: si falla el registro, la
     * versión anterior vuelve a quedar vigente.
     *
     * @param array<string, mixed> $datos Campos propios de la nueva versión
     *                                      (archivo, observaciones, etc.).
     *
     * @return int|null Id de la nueva versión o `null` si no se pudo crear.
     */
    public function crearVersionDe(int $documentoActualId, array $datos): ?int
    {
        $actual = $this->find($documentoActualId);

        if ($actual === null || (int) $actual->es_version_actual !== 1) {
            return null;
        }

        $padreId = $actual->documento_padre_id !== null
            ? (int) $actual->documento_padre_id
            : (int) $actual->id;

        $nueva = array_merge($datos, [
            'obra_id'            => $actual->obra_id,
            'tipo_documento_id'  => $actual->tipo_documento_id,
            'neutralizacion_id'  => $actual->neutralizacion_id,
            'reinicio_id'        => $actual->reinicio_id,
            'version'            => (int) $actual->version + 1,
            'es_version_actual'  => 1,
            'documento_padre_id' => $padreId,
        ]);

        $db = $this->db;
        $db->transBegin();

        try {
            $db->table($this->table)
                ->where('id', (int) $actual->id)
                ->where('es_version_actual', 1)
                ->update([
                    'es_version_actual' => 0,
                    'updated_at'        => date('Y-m-d H:i:s'),
                ]);

            if ($db->affectedRows() !== 1) {
                throw new \RuntimeException('La versión vigente cambió durante el reemplazo.');
            }

            $nuevoId = $this->insert($nueva);

            if ($nuevoId === false) {
                throw new \RuntimeException('No se pudo registrar la nueva versión.');
            }

            $db->transCommit();

            return (int) $nuevoId;
        } catch (\Throwable $e) {
            $db->transRollback();

            return null;
        }
    }

    /**
     * Número de versión que le corresponderá al próximo documento de un
     * documento lógico: `1` si aún no existe, o la versión vigente + 1.
     *
     * Se usa para componer el nombre físico del PDF antes de escribir el
     * archivo, tanto en la carga inicial como en el reemplazo, sin depender
     * de nombres libres en el directorio.
     */
    public function siguienteVersion(int $obraId, int $tipoDocumentoId, ?int $neutralizacionId = null, ?int $reinicioId = null): int
    {
        $actual = $this->obtenerVersionActual($obraId, $tipoDocumentoId, $neutralizacionId, $reinicioId);

        return $actual === null ? 1 : (int) $actual->version + 1;
    }

    /**
     * Lista todas las versiones de un documento agrupadas por documento_padre_id.
     */
    public function listarVersiones(int $documentoId): array
    {
        $doc = $this->find($documentoId);
        if ($doc === null) {
            return [];
        }

        $padreId = $doc->documento_padre_id ?? $doc->id;

        return $this->db
            ->table('documentos d')
            ->select('d.*, u.nombre as usuario_nombre, u.apellido as usuario_apellido')
            ->join('usuarios u', 'u.id = d.usuario_id_carga', 'left')
            ->where('d.documento_padre_id', $padreId)
            ->orWhere('d.id', $padreId)
            ->orderBy('d.version', 'ASC')
            ->get()
            ->getResultObject();
    }
}
