<?php

namespace App\Filters;

use CodeIgniter\Filters\CSRF as BaseCsrf;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\Security\Exceptions\SecurityException;

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

    private function esPeticionApi(RequestInterface $request): bool
    {
        if ($request->isAJAX()) {
            return true;
        }

        return str_contains((string) $request->getHeaderLine('Accept'), 'application/json');
    }
}
