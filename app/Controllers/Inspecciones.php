<?php

namespace App\Controllers;

use App\Models\FotografiaModel;
use App\Models\InspeccionModel;
use App\Models\ObraModel;
use App\Services\AccesoInspecciones;

/**
 * Consulta del historial de inspecciones de una obra (Fases E.3, E.4, E.5 y
 * E.6).
 *
 * Es la **única** implementación de la navegación
 * `Obra → Fechas → Inspecciones → Fotografías`. No importa desde dónde se
 * llegue —Inspector → Mis obras, Inspector → Inspecciones, Consulta →
 * Inspecciones o Administrador → Ver obra → Inspecciones—: el agrupamiento,
 * el detalle y la galería son los mismos, y solo cambia el punto de entrada y
 * la autorización.
 *
 * ## Qué NO hace este controlador
 *
 * No crea inspecciones, no sincroniza y no escribe datos. El alta vive en
 * `Inspector\Inspecciones::nueva()` y requiere asignación vigente; el servicio
 * `AccesoInspecciones` mantiene esa diferencia explícita entre **inspeccionar**
 * y **consultar**.
 *
 * ## Autorización
 *
 * Toda la autorización sale de `AccesoInspecciones::puedeConsultarObra()` y se
 * resuelve **antes** de leer ninguna inspección: una obra ajena no devuelve ni
 * el nombre ni el total. La consulta no depende del estado de la obra, de modo
 * que el historial de una obra FINALIZADA o NEUTRALIZADA también se consulta.
 *
 * ## Puntos de entrada
 *
 * Las rutas se registran en dos grupos (ver `Config/Routes.php`):
 *
 * * `/inspector/inspecciones/{ver,detalle}` — el flujo ya existente de
 *   Inspector → Mis obras, que se conserva tal cual;
 * * `/inspecciones/{ver,detalle,fotografias}` — navegación compartida para
 *   INSPECTOR, CONSULTA, ADMINISTRADOR y SUPERADMINISTRADOR.
 *
 * La única diferencia entre ambos es el prefijo de las URL que la vista compone,
 * que cada entrada declara con `baseHistorico()` y `baseFotografias()`: las
 * vistas no conocen roles.
 */
class Inspecciones extends BaseController
{
    /**
     * Listado de obras cuyo historial puede consultar el usuario (Fase E.6).
     *
     * Para INSPECTOR son las obras que tuvo asignadas, vigentes o ya cerradas:
     * el objetivo es poder consultar su historial incluso cuando no tiene
     * ninguna obra a cargo. Para CONSULTA son todas las obras, que es lo que
     * el rol ya puede ver en su dashboard.
     *
     * Las tarjetas enlazan a la navegación histórica y **no** a ninguna acción
     * de alta: desde este listado no se crea nada.
     */
    public function index()
    {
        $session   = session();
        $roles     = $session->get('roles') ?? [];
        $usuarioId = (int) $session->get('user_id');

        $esInspector = in_array('INSPECTOR', $roles, true);

        $obras = (new ObraModel())->listarParaConsulta($esInspector ? $usuarioId : null);

        return view('inspecciones/obras', [
            'titulo'       => 'Inspecciones',
            'user_name'    => $session->get('user_name'),
            'username'     => $session->get('username'),
            'roles'        => $roles,
            'obras'        => $obras,
            'base'         => $this->baseHistorico(),
            'es_inspector' => $esInspector,
        ]);
    }

    /**
     * Histórico de las inspecciones de una obra agrupado por fecha (E.3/E.4).
     *
     * Entrega las inspecciones agrupadas por fecha y ordenadas por la base de
     * datos (`InspeccionModel::listarPorObraAgrupado`); la vista solo las
     * presenta.
     */
    public function ver(int $obraId)
    {
        $session   = session();
        $roles     = $session->get('roles') ?? [];
        $usuarioId = (int) $session->get('user_id');

        $obra = (new ObraModel())->findDetalle($obraId);

        if ($obra === null) {
            return redirect()->to($this->getDashboardPath($roles))
                ->with('error', 'La obra seleccionada no existe.');
        }

        if (! (new AccesoInspecciones())->puedeConsultarObra($obraId, $usuarioId, $roles)) {
            return redirect()->to($this->getDashboardPath($roles))
                ->with('warning', 'No puede consultar las inspecciones de esa obra.');
        }

        $grupos = (new InspeccionModel())->listarPorObraAgrupado($obraId);

        return view('inspector/inspecciones', [
            'titulo'       => 'Inspecciones',
            'user_name'    => $session->get('user_name'),
            'username'     => $session->get('username'),
            'roles'        => $roles,
            'obra'         => $obra,
            'grupos'       => $grupos,
            'total'        => $this->totalDe($grupos),
            'base'         => $this->baseHistorico(),
            'volver_url'   => $this->urlVolver($roles, $obraId),
            'volver_texto' => $this->textoVolver($roles, $obraId),
            'mensaje_vacio' => $this->mensajeVacio($roles),
        ]);
    }

    /**
     * Detalle de una inspección registrada, con la galería de sus fotografías
     * (Fases E.4 y E.5).
     *
     * La autorización se resuelve sobre la obra **de la inspección**, nunca
     * sobre un identificador recibido del cliente: pedir el detalle de una
     * inspección de otra obra recibe el mismo rechazo que pedir el histórico
     * de una obra ajena.
     *
     * Entrega las fotografías no anuladas, en orden de registro. Los archivos
     * los sirve `Inspector\Fotografias`, que repite esta misma autorización
     * para cada imagen.
     */
    public function detalle(int $inspeccionId)
    {
        $session   = session();
        $roles     = $session->get('roles') ?? [];
        $usuarioId = (int) $session->get('user_id');

        $inspeccion = (new InspeccionModel())->findDetalle($inspeccionId);

        if ($inspeccion === null) {
            return redirect()->to($this->getDashboardPath($roles))
                ->with('error', 'La inspección solicitada no existe.');
        }

        $obraId = (int) $inspeccion->obra_id;
        $obra   = (new ObraModel())->findDetalle($obraId);

        if ($obra === null) {
            return redirect()->to($this->getDashboardPath($roles))
                ->with('error', 'La obra de la inspección no existe.');
        }

        if (! (new AccesoInspecciones())->puedeConsultarObra($obraId, $usuarioId, $roles)) {
            return redirect()->to($this->getDashboardPath($roles))
                ->with('warning', 'No puede consultar esa inspección.');
        }

        return view('inspector/inspeccion_detalle', [
            'titulo'        => 'Detalle de inspección',
            'user_name'     => $session->get('user_name'),
            'username'      => $session->get('username'),
            'roles'         => $roles,
            'obra'          => $obra,
            'inspeccion'    => $inspeccion,
            'fotografias'   => (new FotografiaModel())->listarPorInspeccion((int) $inspeccion->id),
            'base'          => $this->baseHistorico(),
            'base_fotos'    => $this->baseFotografias(),
        ]);
    }

    /**
     * Prefijo de la navegación histórica de este punto de entrada.
     *
     * Lo consumen las vistas para componer sus enlaces; ninguna vista conoce
     * roles ni decide por su cuenta a dónde enlazar.
     */
    protected function baseHistorico(): string
    {
        return '/inspecciones';
    }

    /**
     * Prefijo del servicio de fotografías de este punto de entrada.
     *
     * La galería sigue siendo la de E.5 (imágenes por `uuid`, servidas por el
     * servidor); solo cambia el prefijo de la ruta que la compone.
     */
    protected function baseFotografias(): string
    {
        return '/inspecciones/fotografias';
    }

    /**
     * Listado de obras de este punto de entrada, destino del botón "volver"
     * de la pantalla histórica.
     *
     * @param list<string> $roles
     */
    protected function baseListado(array $roles): string
    {
        return in_array('INSPECTOR', $roles, true)
            ? '/inspector/inspecciones'
            : '/consulta/inspecciones';
    }

    /**
     * Destino del botón "volver" de la pantalla histórica.
     *
     * Se vuelve al punto de entrada real: los roles administrativos entran desde
     * la ficha de la obra y vuelven a ella; INSPECTOR y CONSULTA entran por un
     * listado de obras y vuelven a ese listado.
     *
     * @param list<string> $roles
     */
    protected function urlVolver(array $roles, int $obraId): string
    {
        $administrativo = array_intersect(['SUPERADMINISTRADOR', 'ADMINISTRADOR'], $roles) !== [];

        return $administrativo
            ? '/obras/ver/' . $obraId
            : $this->baseListado($roles);
    }

    /**
     * Texto del botón "volver", coherente con su destino.
     *
     * Se deduce de la propia URL de destino para que las subclases que la
     * reescriban (el inspector vuelve a su vista de obra) no puedan dejar el
     * botón diciendo algo que no corresponde.
     *
     * @param list<string> $roles
     */
    protected function textoVolver(array $roles, int $obraId): string
    {
        return str_contains($this->urlVolver($roles, $obraId), '/obras/ver/')
            ? 'Volver a la obra'
            : 'Volver a inspecciones';
    }

    /**
     * Mensaje del estado vacío del histórico.
     *
     * Solo el inspector puede crear inspecciones, así que solo su entrada
     * sugiere hacerlo: en los accesos de consulta un texto que invitara a
     * generar una inspección sería falso.
     *
     * @param list<string> $roles
     */
    protected function mensajeVacio(array $roles): string
    {
        return in_array('INSPECTOR', $roles, true)
            ? 'Se recomienda generar una nueva inspección para comenzar a registrar el seguimiento de la obra.'
            : 'Esta obra no tiene inspecciones registradas.';
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
