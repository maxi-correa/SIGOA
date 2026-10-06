<?php

namespace App\Services;

use App\Models\InspectoresObrasModel;

/**
 * Autorización de las inspecciones de una obra (Fase E.6).
 *
 * Centraliza las **dos** preguntas que hasta E.5 vivían separadas dentro de
 * `Inspector\Inspecciones`, y que no son la misma:
 *
 * 1. **Inspeccionar** (`puedeInspeccionar`) — poder *crear* una inspección.
 *    Es un acto operativo sobre una obra que hoy se puede inspeccionar: exige
 *    rol INSPECTOR y **asignación vigente** (§52.4 / F.7). El estado de la
 *    obra lo exige el modelo de estados (`EstadoObraModel::permiteInspeccionar`),
 *    no este servicio, porque es una regla de negocio del ciclo de la obra y
 *    no de acceso.
 *
 * 2. **Consultar** (`puedeConsultarObra`) — poder *leer* el historial
 *    (Obra → Fechas → Inspecciones → Fotografías). No exige asignación
 *    vigente ni estado habilitable: el registro ya existente no depende de si
 *    hoy se puede inspeccionar. Una obra FINALIZADA, NEUTRALIZADA o una obra
 *    en la que el inspector ya no está asignado siguen siendo consultables,
 *    porque el objetivo es leer la historia, no intervenir en la obra.
 *
 * La diferencia es deliberada y no un detalle de implementación: si la
 * consulta exigiera asignación vigente, un inspector al que le cambian la
 * asignación perdería el acceso a su propio registro.
 *
 * La fotografía (E.5) usa **la misma** consulta que la pantalla que la
 * muestra: una imagen no puede estar visible para quien no puede ver la
 * inspección a la que pertenece.
 */
class AccesoInspecciones
{
    /**
     * Roles que consultan el historial de cualquier obra.
     *
     * Son exactamente los roles para los que la ficha de obra es visible
     * (`GET /obras/ver/(:num)`: SUPERADMINISTRADOR, ADMINISTRADOR y CONSULTA).
     * La regla es por tanto una sola: **se consulta el historial de una obra
     * que el usuario puede ver**. No hay un criterio de acceso al histórico
     * distinto del criterio de acceso a la obra.
     *
     * @var list<string>
     */
    public const ROLES_CONSULTA_OBRA = ['SUPERADMINISTRADOR', 'ADMINISTRADOR', 'CONSULTA'];

    /**
     * El usuario puede iniciar una inspección en la obra.
     *
     * Requiere rol INSPECTOR (el filtro de ruta también lo exige: son dos
     * capas distintas) y asignación vigente (§52.4).
     *
     * @param list<string> $roles
     */
    public function puedeInspeccionar(int $obraId, int $usuarioId, array $roles): bool
    {
        if (! in_array('INSPECTOR', $roles, true)) {
            return false;
        }

        return (new InspectoresObrasModel())->esVigente($obraId, $usuarioId);
    }

    /**
     * El usuario puede consultar el historial de inspecciones de la obra.
     *
     * Para INSPECTOR se acepta cualquier asignación —vigente o histórica—
     * porque el objetivo es leer su propio registro (§52.4 conserva el
     * historial de asignaciones). Para los roles que ven la ficha de cualquier
     * obra basta con que la obra exista.
     *
     * @param list<string> $roles
     */
    public function puedeConsultarObra(int $obraId, int $usuarioId, array $roles): bool
    {
        if (array_intersect(self::ROLES_CONSULTA_OBRA, $roles) !== []) {
            return true;
        }

        if (! in_array('INSPECTOR', $roles, true)) {
            return false;
        }

        $asignaciones = new InspectoresObrasModel();

        return $asignaciones->esVigente($obraId, $usuarioId)
            || $asignaciones->haTenidoAsignacion($obraId, $usuarioId);
    }
}
