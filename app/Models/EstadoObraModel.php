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

    /**
     * Estados de obra en los que se permite registrar resoluciones.
     *
     * Solo durante la ejecución de la obra (o su neutralización
     * temporaria) tiene sentido aprobar cuadros comparativos o
     * ampliaciones de plazo.
     */
    public const PERMITEN_REGISTRAR_RESOLUCIONES = [
        'EN EJECUCIÓN',
        'NEUTRALIZADA',
    ];

    /** Estado inicial de toda obra nueva. */
    public const ESTADO_PREVIO_INICIO = 'PREVIO INICIO';

    /** Estado desde el que puede iniciarse una neutralización (BLOQUE 5). */
    public const ESTADO_EN_EJECUCION = 'EN EJECUCIÓN';

    /** Estado en el que puede registrarse el reinicio de una obra (BLOQUE 5). */
    public const ESTADO_NEUTRALIZADA = 'NEUTRALIZADA';

    /** Estado posterior a la recepción provisoria. */
    public const ESTADO_EN_PLAZO_CONSERVACION = 'EN PLAZO DE CONSERVACIÓN';

    /** Estado posterior a la recepción definitiva. */
    public const ESTADO_FINALIZADA = 'FINALIZADA';

    /**
     * Estados que admiten registrar la recepción provisoria.
     *
     * EN PLAZO y FINALIZADA permiten completar obras históricas que ya
     * tenían un estado posterior sin la recepción registrada.
     */
    public const PERMITEN_RECEPCION_PROVISORIA = [
        'EN EJECUCIÓN',
        'EN PLAZO DE CONSERVACIÓN',
        'FINALIZADA',
    ];

    /**
     * Estados que admiten registrar la recepción definitiva.
     */
    public const PERMITEN_RECEPCION_DEFINITIVA = [
        'EN PLAZO DE CONSERVACIÓN',
        'FINALIZADA',
    ];

    /**
     * Estados que admiten corregir la recepción provisoria mientras no
     * exista la recepción definitiva.
     */
    public const PERMITEN_CORREGIR_RECEPCION_PROVISORIA = [
        'EN PLAZO DE CONSERVACIÓN',
        'FINALIZADA',
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
        return $this->findByEstado(self::ESTADO_PREVIO_INICIO);
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

    /**
     * Indica si un estado de obra permite registrar resoluciones.
     *
     * La comparación es por nombre normalizado (mayúsculas), sin depender
     * de los ids numéricos del catálogo.
     */
    public function permiteRegistrarResoluciones(string $estadoNombre): bool
    {
        $estado = mb_strtoupper(trim($estadoNombre));

        foreach (self::PERMITEN_REGISTRAR_RESOLUCIONES as $permitido) {
            if (mb_strtoupper($permitido) === $estado) {
                return true;
            }
        }

        return false;
    }

    /**
     * Indica si un estado de obra permite iniciar una neutralización.
     *
     * Solo una obra en ejecución puede neutralizarse (BLOQUE 5). La
     * comparación es por nombre normalizado, sin depender de los ids.
     */
    public function permiteIniciarNeutralizacion(string $estadoNombre): bool
    {
        return mb_strtoupper(trim($estadoNombre)) === mb_strtoupper(self::ESTADO_EN_EJECUCION);
    }

    /**
     * Indica si un estado de obra permite registrar su reinicio.
     *
     * Solo una obra neutralizada puede reanudar su ejecución (BLOQUE 5).
     */
    public function permiteReiniciarNeutralizacion(string $estadoNombre): bool
    {
        return mb_strtoupper(trim($estadoNombre)) === mb_strtoupper(self::ESTADO_NEUTRALIZADA);
    }

    /**
     * Indica si el estado permite registrar la recepción provisoria.
     */
    public function permiteRegistrarRecepcionProvisoria(string $estadoNombre): bool
    {
        return $this->estadoEnLista($estadoNombre, self::PERMITEN_RECEPCION_PROVISORIA);
    }

    /**
     * Indica si el estado permite registrar la recepción definitiva.
     */
    public function permiteRegistrarRecepcionDefinitiva(string $estadoNombre): bool
    {
        return $this->estadoEnLista($estadoNombre, self::PERMITEN_RECEPCION_DEFINITIVA);
    }

    /**
     * Indica si el estado permite corregir los datos de la recepción
     * provisoria existente.
     */
    public function permiteCorregirRecepcionProvisoria(string $estadoNombre): bool
    {
        return $this->estadoEnLista($estadoNombre, self::PERMITEN_CORREGIR_RECEPCION_PROVISORIA);
    }

    /**
     * @param list<string> $permitidos
     */
    private function estadoEnLista(string $estadoNombre, array $permitidos): bool
    {
        $estado = mb_strtoupper(trim($estadoNombre));

        foreach ($permitidos as $permitido) {
            if (mb_strtoupper($permitido) === $estado) {
                return true;
            }
        }

        return false;
    }
}