<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class RoleFilter implements FilterInterface
{
    /**
     * Verifica que el usuario autenticado tenga al menos uno de los roles
     * requeridos para acceder a la ruta.
     *
     * Los roles permitidos se reciben como argumentos del filtro:
     *   role:ADMINISTRADOR
     *   role:ADMINISTRADOR,INSPECTOR
     *
     * Si no existe coincidencia, se redirige a /dashboard para que
     * Auth::redirectToDashboard() resuelva el destino según getDashboardPath().
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        if ($arguments === null || $arguments === []) {
            return;
        }

        $session   = session();
        $userRoles = $session->get('roles') ?? [];

        if (array_intersect($arguments, $userRoles) === []) {
            return redirect()->to('/dashboard')
                ->with('warning', 'No tiene autorización para acceder a esa sección.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
