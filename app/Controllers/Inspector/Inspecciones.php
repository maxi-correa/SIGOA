<?php

namespace App\Controllers\Inspector;

use App\Controllers\Inspecciones as InspeccionesConsulta;
use App\Models\EstadoObraModel;
use App\Models\InspectoresObrasModel;
use App\Models\ObraModel;
use App\Services\AccesoInspecciones;

/**
 * Inspecciones del inspector.
 *
 * Divide las responsabilidades en dos caminos que no se mezclan:
 *
 * 1. **Alta** (`nueva()`, Fase D.2). Es la única acción que crea algo y
 *    exige, además de rol INSPECTOR (filtro de ruta), **asignación vigente**
 *    sobre la obra y un estado de obra que permita nuevas inspecciones (F.7).
 *    El servidor solo autoriza e inicializa la operación: la inspección se
 *    crea en el dispositivo (IndexedDB) y queda pendiente de la
 *    sincronización; este flujo NO inserta nada en `inspecciones` (§52.15).
 *
 * 2. **Consulta histórica** (`index()`, `ver()`, `detalle()`, heredadas de
 *    `App\Controllers\Inspecciones`). Es de **solo lectura**, es la misma
 *    pantalla que ven CONSULTA, ADMINISTRADOR y SUPERADMINISTRADOR, y no
 *    exige asignación vigente: el objetivo es poder consultar el historial
 *    incluso cuando el inspector ya no tiene esa obra a cargo.
 *
 * Esta clase solo declara lo que es propio del inspector —el alta y los
 * prefijos de URL de su punto de entrada— y **no** reimplementa el agrupamiento
 * por fecha, el detalle ni la galería: eso vive una sola vez, en el
 * controlador compartido.
 *
 * La autorización histórica del inspector (§52.4) y la validación de la fecha
 * local corresponden a la sincronización, no a la creación local.
 */
class Inspecciones extends InspeccionesConsulta
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

        if (! (new AccesoInspecciones())->puedeInspeccionar($obraId, $usuarioId, $session->get('roles') ?? [])) {
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
     * Prefijo de la navegación histórica del inspector.
     *
     * El inspector entra por `/inspector/inspecciones` (listado, ruta propia del
     * grupo `inspector`) y por `/inspector/inspecciones/ver/(:num)` desde
     * *Mis obras*. Ambas rutas siguen existiendo tal como estaban en E.3/E.4.
     */
    protected function baseHistorico(): string
    {
        return '/inspector/inspecciones';
    }

    /**
     * El servicio de fotografías del inspector es el de E.5, sin cambios de URL.
     */
    protected function baseFotografias(): string
    {
        return '/inspector/fotografias';
    }

    /**
     * El listado de obras consultables del inspector vive en su propio grupo.
     */
    protected function baseListado(array $roles): string
    {
        return '/inspector/inspecciones';
    }

    /**
     * Destino del botón "volver" del histórico del inspector.
     *
     * Si la obra sigue a su cargo, se vuelve a la vista operativa —de donde se
     * llega normalmente—. Si ya no está asignado, volver a ella sería un
     * rebote con aviso, así que se vuelve al listado de obras consultables,
     * que es el punto de entrada real en ese caso.
     *
     * @param list<string> $roles
     */
    protected function urlVolver(array $roles, int $obraId): string
    {
        if ((new InspectoresObrasModel())->esVigente($obraId, (int) session('user_id'))) {
            return '/inspector/obras/ver/' . $obraId;
        }

        return parent::urlVolver($roles, $obraId);
    }
}
