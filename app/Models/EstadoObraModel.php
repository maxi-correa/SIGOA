<?php

namespace App\Models;

use CodeIgniter\Model;

class EstadoObraModel extends Model
{
    /**
     * Estados de obra en los que se permiten nuevas inspecciones (F.7 / §52.5).
     *
     * PREVIO INICIO y FINALIZADA quedan fuera: la restricción aplica a la
     * creación de inspecciones, no a la sincronización de las creadas
     * legítimamente mientras el estado lo permitía.
     */
    public const PERMITEN_INSPECCIONAR = [
        'EN EJECUCIÓN',
        'NEUTRALIZADA',
        'EN PLAZO DE CONSERVACIÓN',
    ];

    protected $table = 'estados_obra';

    protected $primaryKey = 'id';

    protected $returnType = 'object';

    protected $useTimestamps = false;

    protected $useSoftDeletes = false;

    /**
     * Estado de obra por nombre.
     */
    public function findByEstado(string $estado): ?object
    {
        return $this->where('estado', $estado)->first();
    }

    /**
     * Estados de obra activos en orden lógico del catálogo.
     *
     * @return list<object>
     */
    public function findAllActivos(): array
    {
        return $this
            ->where('activo', 1)
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    /**
     * Estado inicial obligatorio de toda obra nueva.
     */
    public function findPrevioInicio(): ?object
    {
        return $this->findByEstado('PREVIO INICIO');
    }

    /**
     * Indica si un estado de obra permite iniciar nuevas inspecciones (F.7).
     *
     * La comparación es por nombre normalizado (mayúsculas), sin depender de
     * los ids numéricos del catálogo. Se utiliza como precondición de
     * creación local y del alta en línea.
     */
    public function permiteInspeccionar(string $estadoNombre): bool
    {
        $estado = mb_strtoupper(trim($estadoNombre));

        foreach (self::PERMITEN_INSPECCIONAR as $permitido) {
            if (mb_strtoupper($permitido) === $estado) {
                return true;
            }
        }

        return false;
    }
}