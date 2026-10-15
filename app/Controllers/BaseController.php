<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 *
 * Extend this class in any new controllers:
 * ```
 *     class Home extends BaseController
 * ```
 *
 * For security, be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */

    // protected $session;

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Load here all helpers you want to be available in your controllers that extend BaseController.
        // Caution: Do not put the this below the parent::initController() call below.
        // $this->helpers = ['form', 'url'];

        // Caution: Do not edit this line.
        parent::initController($request, $response, $logger);

        // Preload any models, libraries, etc, here.
        // $this->session = service('session');
    }

    /**
     * Devuelve el rol principal del usuario según la jerarquía de SIGOA.
     *
     * La sesión almacena los roles como array. Este método resuelve el rol
     * de mayor jerarquía siguiendo el orden del sistema:
     * SUPERADMINISTRADOR → ADMINISTRADOR → INSPECTOR → CONSULTA.
     *
     * @param list<string> $roles
     */
    protected function getRolPrincipal(array $roles): string
    {
        $jerarquia = ['SUPERADMINISTRADOR', 'ADMINISTRADOR', 'INSPECTOR', 'CONSULTA'];

        foreach ($jerarquia as $rol) {
            if (in_array($rol, $roles, true)) {
                return $rol;
            }
        }

        return $roles[0] ?? '';
    }

    /**
     * Determina el dashboard correspondiente según la jerarquía de roles.
     *
     * @param list<string> $roles
     */
    protected function getDashboardPath(array $roles): string
    {
        return match ($this->getRolPrincipal($roles)) {
            'SUPERADMINISTRADOR' => '/superadmin/dashboard',
            'ADMINISTRADOR'      => '/admin/dashboard',
            'INSPECTOR'          => '/inspector/dashboard',
            'CONSULTA'           => '/consulta/dashboard',
            default              => '/login',
        };
    }

    /**
     * Datos del dashboard administrativo (sección Obras).
     *
     * Compartido por SUPERADMINISTRADOR, ADMINISTRADOR y CONSULTA en esta
     * fase. Las obras se listan paginadas (10 por página) y ordenadas por
     * created_at DESC. Incluye los catálogos necesarios para el modal
     * de alta inicial.
     *
     * Lee del query string los filtros del buscador del listado:
     *
     * - `buscar`: término de texto (nombre, barrio, expediente, empresa o
     *   número de licitación). Se recorta y limita; vacío = sin filtro.
     * - `estado`: id del catálogo `estados_obra`. Se valida contra el
     *   catálogo real; un valor desconocido o no numérico se ignora.
     *
     * El filtrado ocurre en el servidor, en ObraModel::listarPaginado(),
     * para que la paginación opere sobre el conjunto completo de
     * coincidencias. En la vista se exponen 'buscar', 'estado_filtro' y
     * 'hay_filtros' para rellenar el formulario y diferenciar el estado
     * vacío con/sin filtros activos.
     *
     * @return array<string, mixed>
     */
    protected function datosDashboardObras(): array
    {
        $session = session();

        $estados      = (new \App\Models\EstadoObraModel())->findAllActivos();
        $buscar       = $this->normalizarTerminoBusqueda($this->request->getGet('buscar'));
        $estadoFiltro = $this->validarEstadoFiltro($this->request->getGet('estado'), $estados);

        $obraModel = new \App\Models\ObraModel();
        $obras     = $obraModel->listarPaginado(10, $buscar, $estadoFiltro);

        return [
            'titulo'               => 'Obras',
            'user_name'            => $session->get('user_name'),
            'username'             => $session->get('username'),
            'roles'                => $session->get('roles') ?? [],
            'obras'                => $obras,
            'pager'                => $obraModel->pager,
            'per_page'             => 10,
            'barrios'              => (new \App\Models\BarrioModel())->findAllActivos(),
            'empresas'             => (new \App\Models\EmpresaModel())->findAllActivas(),
            'tipos_licitacion'     => (new \App\Models\TipoLicitacionModel())->findAllActivos(),
            'estados'              => $estados,
            'estado_previo_inicio' => (new \App\Models\EstadoObraModel())->findPrevioInicio(),
            'buscar'               => $buscar,
            'estado_filtro'        => $estadoFiltro,
            'hay_filtros'          => $buscar !== '' || $estadoFiltro !== null,
        ];
    }

    /**
     * Normaliza el término de búsqueda recibido por query string.
     *
     * Devuelve '' si el valor no es una cadena o queda vacío tras recortar
     * espacios. Elimina caracteres de control y limita el largo a 100
     * caracteres para evitar consultas innecesariamente pesadas.
     */
    private function normalizarTerminoBusqueda(mixed $valor): string
    {
        if (! is_string($valor)) {
            return '';
        }

        $valor = preg_replace('/[\x00-\x1F\x7F]/u', '', $valor) ?? '';

        return mb_substr(trim($valor), 0, 100);
    }

    /**
     * Valida el id de estado recibido por query string contra el catálogo
     * real de `estados_obra`.
     *
     * @param array<int, object> $estados Catálogo de EstadoObraModel::findAllActivos()
     * @return int|null id válido o null cuando no hay filtro de estado aplicable
     */
    private function validarEstadoFiltro(mixed $valor, array $estados): ?int
    {
        if ($valor === null || $valor === '' || ! is_numeric($valor)) {
            return null;
        }

        $id = (int) $valor;

        foreach ($estados as $estado) {
            if ((int) $estado->id === $id) {
                return $id;
            }
        }

        return null;
    }
}
