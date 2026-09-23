<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();

        if (! $session->get('logged_in')) {
            if ($this->esPeticionApi($request)) {
                return $this->respuestaApi(401, 'AUTH_REQUIRED');
            }

            return redirect()->to('/login');
        }

        if ($session->get('activo') !== true) {
            $session->destroy();

            if ($this->esPeticionApi($request)) {
                return $this->respuestaApi(401, 'AUTH_REQUIRED');
            }

            return redirect()->to('/login');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }

    /**
     * Determina si la petición espera una respuesta de API (JSON).
     *
     * En contexto API no se aplica la redirección HTML a /login: se responde
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
