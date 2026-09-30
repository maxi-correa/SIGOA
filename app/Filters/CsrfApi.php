<?php

namespace App\Filters;

use CodeIgniter\Filters\CSRF as BaseCsrf;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Security\SecurityInterface;

class CsrfApi extends BaseCsrf
{
    /**
     * @param list<string>|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        try {
            return parent::before($request, $arguments);
        } catch (SecurityException $e) {
            if (! $this->esPeticionApi($request)) {
                throw $e;
            }

            return service('response')
                ->setStatusCode(403)
                ->setJSON([
                    'ok'    => false,
                    'error' => 'CSRF_INVALID',
                ]);
        }
    }

    /**
     * Publica en la respuesta el token CSRF vigente.
     *
     * Con `Config\Security::$regenerate = true`, cada petición que supera la
     * verificación genera un token nuevo: la cookie `csrf_cookie_name` se
     * reescribe y el token que ya tenía el cliente queda obsoleto. El `<meta>`
     * que emite `layouts/auth.php` es una foto del token en el momento de
     * cargar la página y el navegador no lo actualiza, de modo que un cliente
     * que solo lo consultara quedaría clavado en el primer 403.
     *
     * Devolver el token vigente en la cabecera que el propio cliente ya envía
     * (`Security::getHeaderName()`) convierte la renovación en un contrato
     * explícito: cada respuesta indica con qué token puede hacerse la
     * siguiente petición, sin recargar, sin leer cookies y sin persistir
     * nada (docs/SIGOA.md §52.7, §61).
     *
     * Solo se publica en peticiones API/AJAX, las mismas que este filtro
     * distingue en `before()`: las respuestas HTML siguen llevando el token
     * únicamente en la cookie y en el `<meta>` del layout.
     *
     * No se publica nada cuando el token fue rechazado: en ese camino
     * `verify()` lanza antes de regenerar y, además, `before()` devuelve
     * directamente la respuesta 403, por lo que los filtros `after` no llegan
     * a ejecutarse. El cliente trata ese caso como una pausa real y espera a
     * su siguiente disparador (§56.6).
     *
     * @param list<string>|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        if (! $this->esPeticionApi($request)) {
            return $response;
        }

        $security = service('security');

        if (! $security instanceof SecurityInterface) {
            return $response;
        }

        $hash = $security->getHash();

        if ($hash === null || $hash === '') {
            return $response;
        }

        $response->setHeader($security->getHeaderName(), $hash);

        return $response;
    }

    private function esPeticionApi(RequestInterface $request): bool
    {
        if ($request->isAJAX()) {
            return true;
        }

        return str_contains((string) $request->getHeaderLine('Accept'), 'application/json');
    }
}