<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Asignaciones de representantes técnicos a obras.
 *
 * Conserva el historial de asignaciones: cada fila representa un período
 * con `fecha_inicio` y `fecha_fin`. La asignación vigente es aquella cuya
 * `fecha_fin` es NULL y debe existir una única por obra.
 *
 * Los períodos son inclusivos (la fecha de fin es el último día efectivo),
 * por lo que no deben superponerse ni dejar huecos.
 */
class ObrasRepresentantesTecnicosModel extends Model
{
    protected $table = 'obras_representantes_tecnicos';

    protected $primaryKey = 'id';

    protected $returnType = 'object';

    protected $allowedFields = [
        'obra_id',
        'representante_tecnico_id',
        'fecha_inicio',
        'fecha_fin',
        'created_at',
    ];

    protected $useTimestamps = false;

    protected $useSoftDeletes = false;

    /**
     * Representante técnico vigente de una obra (fecha_fin NULL).
     *
     * Si existieran varias filas abiertas por datos heredados, se toma la
     * de fecha_inicio más reciente.
     */
    public function findVigentePorObra(int $obraId): ?object
    {
        return $this
            ->where('obra_id', $obraId)
            ->where('fecha_fin', null)
            ->orderBy('fecha_inicio', 'DESC')
            ->orderBy('id', 'DESC')
            ->first();
    }

    /**
     * Representante técnico vigente con los datos del representante y su título.
     */
    public function findVigenteConRepresentante(int $obraId): ?object
    {
        return $this
            ->select('obras_representantes_tecnicos.*')
            ->select('representantes_tecnicos.nombre AS representante_nombre, representantes_tecnicos.apellido AS representante_apellido, representantes_tecnicos.matricula AS representante_matricula')
            ->select('tipos_titulo_profesional.titulo AS titulo_profesional_nombre')
            ->join('representantes_tecnicos', 'representantes_tecnicos.id = obras_representantes_tecnicos.representante_tecnico_id', 'inner')
            ->join('tipos_titulo_profesional', 'tipos_titulo_profesional.id = representantes_tecnicos.titulo_profesional_id', 'inner')
            ->where('obras_representantes_tecnicos.obra_id', $obraId)
            ->where('obras_representantes_tecnicos.fecha_fin', null)
            ->orderBy('obras_representantes_tecnicos.fecha_inicio', 'DESC')
            ->orderBy('obras_representantes_tecnicos.id', 'DESC')
            ->first();
    }

    /**
     * Historial de asignaciones de una obra con los datos del representante
     * y su título, ordenado de la asignación más reciente a la más antigua.
     *
     * @return list<object>
     */
    public function listarHistorial(int $obraId): array
    {
        return $this
            ->select('obras_representantes_tecnicos.*')
            ->select('representantes_tecnicos.nombre AS representante_nombre, representantes_tecnicos.apellido AS representante_apellido, representantes_tecnicos.matricula AS representante_matricula')
            ->select('tipos_titulo_profesional.titulo AS titulo_profesional_nombre')
            ->join('representantes_tecnicos', 'representantes_tecnicos.id = obras_representantes_tecnicos.representante_tecnico_id', 'inner')
            ->join('tipos_titulo_profesional', 'tipos_titulo_profesional.id = representantes_tecnicos.titulo_profesional_id', 'inner')
            ->where('obras_representantes_tecnicos.obra_id', $obraId)
            ->orderBy('obras_representantes_tecnicos.fecha_inicio', 'DESC')
            ->orderBy('obras_representantes_tecnicos.id', 'DESC')
            ->findAll();
    }

    /**
     * Indica si un representante técnico tiene una asignación vigente.
     *
     * Una asignación vigente es la que no tiene fecha de fin (la obra lo
     * tiene actualmente como representante). Las asignaciones históricas
     * no impiden desactivarlo: el historial se conserva.
     *
     * Solo lectura; no modifica la asignación.
     */
    public function tieneAsignacionVigente(int $representanteTecnicoId): bool
    {
        return $this
            ->where('representante_tecnico_id', $representanteTecnicoId)
            ->where('fecha_fin', null)
            ->countAllResults() > 0;
    }

    /**
     * Cierra una asignación estableciendo su fecha de fin.
     */
    public function cerrarVigente(int $id, string $fechaFin): bool
    {
        return $this->update($id, ['fecha_fin' => $fechaFin]);
    }

    /**
     * Registra una nueva asignación vigente para una obra.
     */
    public function crearAsignacion(int $obraId, int $representanteTecnicoId, string $fechaInicio): int
    {
        return (int) $this->insert([
            'obra_id'                   => $obraId,
            'representante_tecnico_id'  => $representanteTecnicoId,
            'fecha_inicio'              => $fechaInicio,
            'created_at'                => date('Y-m-d H:i:s'),
        ], true);
    }

    /**
     * Reemplaza el representante técnico vigente de una obra de forma atómica.
     *
     * Cierra la asignación abierta y crea la nueva dentro de una misma
     * transacción, para no dejar la obra sin representante técnico si una
     * operación falla. Al completar correctamente, actualiza la obra
     * mediante ObraModel::touch().
     *
     * Reglas:
     *
     * - La fecha debe ser válida y no posterior a la fecha actual.
     * - Si existe una asignación vigente, la nueva fecha debe ser
     *   estrictamente posterior a la fecha de inicio de la vigente.
     * - La asignación anterior se cierra con `fecha_inicio - 1 día`,
     *   conservando períodos inclusivos sin superposición ni huecos.
     *
     * Para una primera asignación (sin vigente) solo aplican las
     * validaciones de fecha válida y no futura.
     */
    public function cambiarAsignacion(int $obraId, int $representanteTecnicoId, string $fechaCambio): bool
    {
        if (! $this->esFechaValida($fechaCambio) || $this->esFechaFutura($fechaCambio)) {
            return false;
        }

        $this->db->transStart();

        $vigente = $this->findVigentePorObra($obraId);

        if ($vigente !== null) {
            if (strcmp($fechaCambio, $vigente->fecha_inicio) <= 0) {
                $this->db->transComplete();

                return false;
            }

            $this->cerrarVigente((int) $vigente->id, $this->diaAnterior($fechaCambio));
        }

        $this->crearAsignacion($obraId, $representanteTecnicoId, $fechaCambio);

        (new ObraModel())->touch($obraId);

        $this->db->transComplete();

        return $this->db->transStatus();
    }

    /**
     * Valida que la fecha sea una fecha real en formato Y-m-d.
     */
    private function esFechaValida(string $fecha): bool
    {
        $fechaObj = \DateTime::createFromFormat('Y-m-d', $fecha);

        return $fechaObj !== false && $fechaObj->format('Y-m-d') === $fecha;
    }

    /**
     * Indica si la fecha es posterior a la fecha actual.
     */
    private function esFechaFutura(string $fecha): bool
    {
        return strcmp($fecha, date('Y-m-d')) > 0;
    }

    /**
     * Devuelve el día anterior a la fecha indicada (formato Y-m-d).
     */
    private function diaAnterior(string $fecha): string
    {
        $fechaObj = \DateTime::createFromFormat('Y-m-d', $fecha);

        return $fechaObj->modify('-1 day')->format('Y-m-d');
    }
}