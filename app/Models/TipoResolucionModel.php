<?php

namespace App\Models;

use App\Libraries\ResolucionObra;
use CodeIgniter\Model;

/**
 * Catálogo de tipos de resolución.
 *
 * Los valores provienen del seed/migración y son la fuente de las reglas
 * de monto y días que aplica ResolucionObra.
 */
class TipoResolucionModel extends Model
{
    /**
     * Orden de presentación de los tipos.
     *
     * No depende del id: en bases migradas el tipo combinado se agregó
     * después de OTRO. OTRO cierra siempre la lista.
     */
    private const ORDEN = [
        ResolucionObra::TIPO_ADJUDICACION    => 10,
        ResolucionObra::TIPO_CUADRO_COMPARATIVO => 20,
        ResolucionObra::TIPO_AMPLIACION_PLAZO => 30,
        ResolucionObra::TIPO_CUADRO_Y_AMPLIACION => 40,
        ResolucionObra::TIPO_OTRO            => 999,
    ];

    /** Prioridad de un tipo no contemplado: antes de OTRO. */
    private const ORDEN_DESCONOCIDO = 35;

    protected $table = 'tipos_resolucion';

    protected $primaryKey = 'id';

    protected $returnType = 'object';

    protected $useTimestamps = false;

    protected $useSoftDeletes = false;

    /**
     * Tipos activos en el orden de presentación del selector.
     *
     * @return list<object>
     */
    public function findAllActivos(): array
    {
        $tipos = $this->where('activo', 1)->findAll();

        usort($tipos, static function (object $a, object $b): int {
            $prioridad = self::ordenDe($a->tipo_resolucion) <=> self::ordenDe($b->tipo_resolucion);

            return $prioridad !== 0 ? $prioridad : ((int) $a->id <=> (int) $b->id);
        });

        return $tipos;
    }

    private static function ordenDe(string $tipo): int
    {
        return self::ORDEN[mb_strtoupper(trim($tipo))] ?? self::ORDEN_DESCONOCIDO;
    }

    /**
     * Devuelve un tipo activo por id, o null si no existe o está inactivo.
     */
    public function findActivo(int $id): ?object
    {
        return $this
            ->where('id', $id)
            ->where('activo', 1)
            ->first();
    }
}
