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
 * Tres responsabilidades separadas:
 *
 * 1. `nueva()` — alta de una inspección. El servidor solo autoriza e
 *    inicializa la operación: verifica usuario autenticado con rol INSPECTOR
 *    (filtros de ruta), asignación vigente a la obra y estado de obra que
 *    permita nuevas inspecciones (F.7). La inspección en sí se crea en el
 *    dispositivo (IndexedDB) y queda pendiente de la sincronización: este flujo
 *    NO inserta nada en `inspecciones`; el envío al servidor lo realiza el
 *    sincronizador (§52.15).
 *
 * 2. `ver()` — histórico de la obra (E.3/E.4): inspecciones agrupadas por
 *    fecha, de más reciente a más antigua. Es de **solo lectura** y no depende
 *    del estado de la obra: consultar el historial de una obra finalizada tiene
 *    que ser posible.
 *
 * 3. `detalle()` — identificación de una inspección concreta (E.4): fecha, hora
 *    y observaciones, tal como quedaron registradas.
 *
 * El alta y la consulta histórica son caminos separados a propósito: el alta
 * restringe por estado de obra (F.7) y la consulta no, porque el registro ya
 * existente no depende de si hoy se puede inspeccionar.
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
     * Histórico de las inspecciones de una obra (Fases E.3 y E.4).
     *
     * Verifica lo mismo que la vista de la obra: la obra debe existir y el
     * inspector debe tener asignación vigente. A diferencia de `nueva()`, NO
     * exige que el estado de la obra permita inspeccionar: el historial de una
     * obra neutralizada o finalizada también se consulta, y restringirlo dejaría
     * al inspector sin acceso a su propio registro.
     *
     * Entrega las inspecciones agrupadas por fecha y ordenadas por la base de
     * datos. La autorización se resuelve **antes** de leer ninguna inspección:
     * una obra ajena no devuelve ni el nombre ni el total.
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

        $grupos = (new InspeccionModel())->listarPorObraAgrupado($obraId);

        return view('inspector/inspecciones', [
            'titulo'    => 'Inspecciones',
            'user_name' => $session->get('user_name'),
            'username'  => $session->get('username'),
            'roles'     => $session->get('roles') ?? [],
            'obra'      => $obra,
            'grupos'    => $grupos,
            'total'     => $this->totalDe($grupos),
        ]);
    }

    /**
     * Detalle de una inspección registrada (Fase E.4).
     *
     * La autorización es la misma que la del histórico y se resuelve sobre la
     * obra **de la inspección**, no sobre un identificador recibido del
     * cliente: pedir el detalle de una inspección de otra obra recibe el mismo
     * rechazo que pedir el histórico de una obra ajena.
     *
     * Es de solo lectura: no se modifica ni recalcula ningún dato histórico, y
     * las fotografías no se resuelven (corresponde a E.5).
     */
    public function detalle(int $inspeccionId)
    {
        $session   = session();
        $usuarioId = (int) $session->get('user_id');

        $inspeccion = (new InspeccionModel())->findDetalle($inspeccionId);

        if ($inspeccion === null) {
            return redirect()->to('/inspector/dashboard')
                ->with('error', 'La inspección solicitada no existe.');
        }

        $obraId = (int) $inspeccion->obra_id;
        $obra   = (new ObraModel())->findDetalle($obraId);

        if ($obra === null) {
            return redirect()->to('/inspector/dashboard')
                ->with('error', 'La obra de la inspección no existe.');
        }

        if (! (new InspectoresObrasModel())->esVigente($obraId, $usuarioId)) {
            return redirect()->to('/inspector/dashboard')
                ->with('warning', 'No puede consultar esa inspección. Solo está habilitado para las obras que tiene asignadas como inspector.');
        }

        return view('inspector/inspeccion_detalle', [
            'titulo'     => 'Detalle de inspección',
            'user_name'  => $session->get('user_name'),
            'username'   => $session->get('username'),
            'roles'      => $session->get('roles') ?? [],
            'obra'       => $obra,
            'inspeccion' => $inspeccion,
        ]);
    }

    /**
     * Total de inspecciones de los grupos ya consultados.
     *
     * No se cuenta en una segunda consulta: sale de la misma lista que la vista
     * muestra, de modo que el encabezado y el listado no pueden discrepar.
     *
     * @param list<array{fecha: string, total: int, inspecciones: list<object>}> $grupos
     */
    private function totalDe(array $grupos): int
    {
        $total = 0;

        foreach ($grupos as $grupo) {
            $total += $grupo['total'];
        }

        return $total;
    }
}