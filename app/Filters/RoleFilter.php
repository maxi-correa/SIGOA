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
            if ($this->esPeticionApi($request)) {
                return $this->respuestaApi(403, 'FORBIDDEN');
            }

            return redirect()->to('/dashboard')
                ->with('warning', 'No tiene autorización para acceder a esa sección.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }

    /**
     * Determina si la petición espera una respuesta de API (JSON).
     *
     * En contexto API no se aplica la redirección HTML a /dashboard: se responde
     * con el código HTTP y el cuerpo JSON correspondientes.
     */
    private function esPeticionApi(RequestInterface $request): bool
    {
        if ($request->isAJAX()) {
            return true;
        }

        return strpos($request->getHeaderLine('Accept'), 'application/json') !== false;
    }

    private function respuestaApi(int $status, string $error): ResponseInterface
    {
        return service('response')
            ->setStatusCode($status)
            ->setJSON(['ok' => false, 'error' => $error]);
    }
}
