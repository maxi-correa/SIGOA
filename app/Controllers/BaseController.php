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
}
