<?php

namespace App\Controllers\Inspector;

use App\Controllers\BaseController;
use App\Models\EstadoObraModel;
use App\Models\InspeccionModel;
use App\Models\InspectoresObrasModel;
use App\Models\ObraModel;

/**
 * Inspecciones del inspector sobre una obra.
 *
 * Dos responsabilidades separadas (Fase E.3):
 *
 * 1. `nueva()` — alta de una inspección. El servidor solo autoriza e
 *    inicializa la operación: verifica usuario autenticado con rol INSPECTOR
 *    (filtros de ruta), asignación vigente a la obra y estado de obra que
 *    permita nuevas inspecciones (F.7). La inspección en sí se crea en el
 *    dispositivo (IndexedDB) y queda pendiente de la sincronización: este flujo
 *    NO inserta nada en `inspecciones`; el envío al servidor lo realiza el
 *    sincronizador (§52.15).
 *
 * 2. `ver()` — punto de entrada a la consulta de las inspecciones de la obra.
 *    Es de **solo lectura** y no depende del estado de la obra: consultar el
 *    historial de una obra finalizada tiene que ser posible. En E.3 la vista
 *    únicamente informa si la obra tiene inspecciones registradas; la
 *    navegación Obra → Fecha → Inspección es E.4.
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

    /**
     * Consulta de las inspecciones registradas para una obra (Fase E.3).
     *
     * Verifica lo mismo que la vista de la obra: la obra debe existir y el
     * inspector debe tener asignación vigente. A diferencia de `nueva()`, NO
     * exige que el estado de la obra permita inspeccionar: el historial de una
     * obra neutralizada o finalizada también se consulta, y restringuirlo en
     * esta fase dejaría al inspector sin acceso a su propio registro.
     *
     * Entrega únicamente la cantidad de inspecciones del servidor, para poder
     * mostrar el estado vacío sin adelantar el historial de E.4.
     */
    public function ver(int $obraId)
    {
        $session   = session();
        $usuarioId = (int) $session->get('user_id');

        $obra = (new ObraModel())->findDetalle($obraId);

        if ($obra === null) {
            return redirect()->to('/inspector/dashboard')
                ->with('error', 'La obra seleccionada no existe.');
        }

        if (! (new InspectoresObrasModel())->esVigente($obraId, $usuarioId)) {
            return redirect()->to('/inspector/dashboard')
                ->with('warning', 'No puede consultar las inspecciones de esa obra. Solo está habilitado para las obras que tiene asignadas como inspector.');
        }

        return view('inspector/inspecciones', [
            'titulo'             => 'Inspecciones',
            'user_name'          => $session->get('user_name'),
            'username'           => $session->get('username'),
            'roles'              => $session->get('roles') ?? [],
            'obra'               => $obra,
            'total_inspecciones' => (new InspeccionModel())->contarParaObra($obraId),
        ]);
    }
}