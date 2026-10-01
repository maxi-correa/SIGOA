<?php

namespace App\Models;

use App\Services\ObraAlmacenamiento;
use CodeIgniter\Model;

/**
 * Inspecciones de obra.
 *
 * El `id` es la identidad interna/autoincremental del servidor; el `uuid` es la
 * identidad estable de la entidad para operaciones offline y sincronización
 * futuras (F.2 / §52.3). El `uuid` no reemplaza al `id`.
 *
 * Una obra puede tener múltiples inspecciones en la misma fecha: la identidad
 * de cada inspección es su `uuid` (F.5). La unicidad del `uuid` queda delegada
 * al índice UNIQUE de la base de datos.
 *
 * Desde la Fase E.2 el `id` cumple además una segunda función: desempata de
 * forma determinista el **nombre físico** de la carpeta de la inspección, que
 * se deriva de `fecha_inspeccion` + `hora_inspeccion` y, cuando coinciden
 * varias inspecciones, del orden `id ASC` dentro de esa misma combinación
 * (§52.8). El `uuid` sigue siendo exclusivamente la identidad técnica del
 * protocolo de sincronización y nunca forma parte de una ruta en disco.
 *
 * No incluye todavía campos de sincronización (estado, remote_id, hash, cola):
 * esa información pertenece al almacenamiento local futuro.
 */
class InspeccionModel extends Model
{
    protected $table = 'inspecciones';

    protected $primaryKey = 'id';

    protected $returnType = 'object';

    protected $allowedFields = [
        'uuid',
        'obra_id',
        'inspector_id',
        'fecha_inspeccion',
        'hora_inspeccion',
        'observacion',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = false;

    protected $useSoftDeletes = false;

    protected $validationRules = [
        'uuid' => 'required|max_length[36]|uuid_valid',
    ];

    protected $validationMessages = [
        'uuid' => [
            'required'   => 'El campo uuid es obligatorio.',
            'max_length' => 'El campo uuid excede el largo permitido.',
            'uuid_valid' => 'El campo uuid no es un UUID válido.',
        ],
    ];

    public function findByUuid(string $uuid): ?object
    {
        return $this->where('uuid', $uuid)->first();
    }

    /**
     * Sufijo ordinal de la carpeta física de una inspección (Fase E.2, §52.8).
     *
     * Devuelve `1 + COUNT` de inspecciones de la **misma obra**, **misma
     * fecha** y **misma hora** cuyo `id` es menor que el de la inspección
     * indicada. Es decir: dentro de cada combinación `obra_id +
     * fecha_inspeccion + hora_inspeccion`, la inspección con menor `id` recibe
     * el nombre base (`1`, sin sufijo) y las siguientes `-2`, `-3`, etc.
     *
     * Es determinista e independiente del orden en que llegan las fotografías:
     * dos inspecciones de una misma obra, fecha y hora nunca comparten carpeta,
     * sin importar cuál se sincronizó primero.
     *
     * La comparación se hace con la hora canónica `HH:MM:SS`: `hora_inspeccion`
     * es `TIME` en MariaDB y puede traer fracciones de segundo. `NULL` es un
     * valor distinto de `00:00:00` y por eso se compara explícitamente como nula.
     *
     * @param int         $obraId       Obra de la inspección.
     * @param string      $fecha        `fecha_inspeccion` (`YYYY-MM-DD`).
     * @param string|null $hora         `hora_inspeccion` en forma canónica
     *                                  `HH:MM:SS` (la que devuelve
     *                                  `normalizarHora()`) o null.
     * @param int         $inspeccionId `inspecciones.id` de la inspección.
     *
     * @return int Sufijo >= 1 (1 = nombre base).
     */
    public function sufijoCarpeta(int $obraId, string $fecha, ?string $hora, int $inspeccionId): int
    {
        $builder = $this->builder()
            ->where('obra_id', $obraId)
            ->where('fecha_inspeccion', $fecha)
            ->where('id <', $inspeccionId);

        if ($hora === null) {
            $builder->where('hora_inspeccion', null);
        } else {
            $builder->where('hora_inspeccion', $hora);
        }

        return 1 + (int) $builder->countAllResults();
    }

    /**
     * Nombre físico completo de la carpeta de una inspección
     * (`HH-mm-ss`, `HH-mm-ss-2`, `SIN-HORA`, …).
     *
     * Es el punto de entrada de la composición: normaliza la hora, resuelve el
     * desempate por `id ASC` —que requiere consultar `inspecciones`— y delega
     * la forma del nombre en `ObraAlmacenamiento`.
     *
     * Una hora presente pero ilegible devuelve null en lugar de degradarse a
     * `SIN-HORA`: aceptarla mezclaría la carpeta de una inspección con hora
     * inválida con la de una inspección sin hora, que es un caso distinto.
     *
     * @return string|null Null si los datos no permiten componer un nombre válido.
     */
    public function nombreCarpetaInspeccion(int $obraId, string $fecha, ?string $hora, int $inspeccionId): ?string
    {
        $horaNormalizada = $this->normalizarHora($hora);

        if ($hora !== null && $horaNormalizada === null) {
            return null;
        }

        $sufijo = $this->sufijoCarpeta($obraId, $fecha, $horaNormalizada, $inspeccionId);

        return $this->almacenamiento()->nombreCarpetaInspeccion($horaNormalizada, $sufijo);
    }

    /**
     * Normaliza una hora a la forma canónica `HH:MM:SS` con la que se compara
     * en la base y se nombra la carpeta, descartando fracciones de segundo.
     *
     * Devuelve null si la hora no es una hora válida. Una hora ausente
     * (null o vacía) también devuelve null: es un valor legítimo y distinto de
     * un dato ilegible, y quien llama lo distingue por su propio argumento.
     */
    public function normalizarHora(?string $hora): ?string
    {
        if ($hora === null || trim($hora) === '') {
            return null;
        }

        $hora = trim($hora);

        /* Se acepta la forma canónica `HH:MM:SS` y, defensivamente, las
           variantes que puede devolver el motor (`HH:MM`, fracciones de
           segundo), porque la columna es TIME y no VARCHAR. */
        if (preg_match('/\A(\d{2}):(\d{2})(?::(\d{2}))?(?:\.\d+)?\z/', $hora, $partes) !== 1) {
            return null;
        }

        $h = (int) $partes[1];
        $m = (int) $partes[2];
        $s = (int) ($partes[3] ?? '0');

        if ($h > 23 || $m > 59 || $s > 59) {
            return null;
        }

        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }

    /**
     * Servicio de almacenamiento físico. Se resuelve de forma diferida para no
     * acoplar el modelo al contenedor de servicios al construirse.
     */
    private function almacenamiento(): ObraAlmacenamiento
    {
        return new ObraAlmacenamiento();
    }
}