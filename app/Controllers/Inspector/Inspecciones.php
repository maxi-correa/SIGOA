<?php

namespace App\Controllers\Inspector;

use App\Controllers\BaseController;
use App\Models\EstadoObraModel;
use App\Models\InspectoresObrasModel;
use App\Models\ObraModel;

/**
 * Nueva inspección (flujo LOCAL de Fase D.2).
 *
 * El servidor solo autoriza e inicializa la operación: verifica usuario
 * autenticado con rol INSPECTOR (filtros de ruta), asignación vigente a la
 * obra y estado de obra que permita nuevas inspecciones (F.7). La inspección
 * en sí se crea en el dispositivo (IndexedDB) y queda pendiente de la
 * sincronización futura: NO se inserta en `inspecciones` ni existe endpoint
 * de sincronización en esta fase.
 *
 * La autorización histórica del inspector (§52.4) y la validación de la
 * fecha local corresponden a la sincronización, no a la creación local.
 */
class Inspecciones extends BaseController
{
    /**
     * Formulario de nueva inspección para una obra.
     *
     * Accesible solo con asignación vigente y con estado de obra que permita
     * nuevas inspecciones. De lo contrario redirige a la vista de la obra o
     * al dashboard con el aviso correspondiente.
     */
    public function nueva(int $obraId)
    {
        $session   = session();
        $usuarioId = (int) $session->get('user_id');

        $obra = (new ObraModel())->findDetalle($obraId);

        if ($obra === null) {
            return redirect()->to('/inspector/dashboard')
                ->with('error', 'La obra seleccionada no existe.');
        }

        if (! (new InspectoresObrasModel())->esVigente($obraId, $usuarioId)) {
            return redirect()->to('/inspector/obras/ver/' . $obraId)
                ->with('warning', 'No puede iniciar una inspección en esa obra. Solo está habilitado para las obras que tiene asignadas como inspector.');
        }

        $estadoNombre = (string) ($obra->estado_nombre ?? '');

        if (! (new EstadoObraModel())->permiteInspeccionar($estadoNombre)) {
            return redirect()->to('/inspector/obras/ver/' . $obraId)
                ->with('warning', 'No se puede iniciar una nueva inspección mientras la obra se encuentra en estado ' . mb_strtoupper($estadoNombre) . '.');
        }

        return view('inspector/inspeccion_nueva', [
            'titulo'       => 'Nueva inspección',
            'user_name'    => $session->get('user_name'),
            'username'     => $session->get('username'),
            'roles'        => $session->get('roles') ?? [],
            'obra_id'      => $obraId,
            'inspector_id' => $usuarioId,
            'obra'         => $obra,
        ]);
    }
}