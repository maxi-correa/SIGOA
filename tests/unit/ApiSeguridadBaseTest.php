<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Base de seguridad para APIs (Fase B).
 *
 * Verifica el comportamiento de AuthFilter y RoleFilter por separado para la
 * autenticación y la autorización, de modo que las futuras APIs de
 * sincronización respondan 401/403 en JSON sin tocar la redirección HTML que
 * mantiene la web actual.
 *
 * Contrato de API:
 *   sin sesión válida        → 401 {"ok":false,"error":"AUTH_REQUIRED"}
 *   sin rol requerido        → 403 {"ok":false,"error":"FORBIDDEN"}
 *   contexto web (HTML)      → redirección intacta (/login o /dashboard)
 *
 * @internal
 */
final class ApiSeguridadBaseTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    public function testApiSinAutenticacionDevuelve401Json(): void
    {
        $resultado = $this->withHeaders([
            'Accept'          => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->get('/mis-datos');

        $resultado->assertStatus(401);
        $resultado->assertHeader('Content-Type', 'application/json; charset=UTF-8');
        $resultado->assertJSONFragment(['ok' => false, 'error' => 'AUTH_REQUIRED']);
    }

    public function testWebSinAutenticacionRedirigeAlLogin(): void
    {
        $resultado = $this->get('/mis-datos');

        $resultado->assertRedirectTo('/login');
    }

    public function testApiConUsuarioInactivoDevuelve401Json(): void
    {
        $resultado = $this->withSession([
            'logged_in' => true,
            'activo'    => false,
        ])->withHeaders([
            'Accept'          => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->get('/mis-datos');

        $resultado->assertStatus(401);
        $resultado->assertJSONFragment(['ok' => false, 'error' => 'AUTH_REQUIRED']);
    }

    public function testApiSinRolRequeridoDevuelve403Json(): void
    {
        $resultado = $this->withSession([
            'logged_in' => true,
            'activo'    => true,
            'roles'     => ['CONSULTA'],
        ])->withHeaders([
            'Accept'          => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->get('/usuarios');

        $resultado->assertStatus(403);
        $resultado->assertHeader('Content-Type', 'application/json; charset=UTF-8');
        $resultado->assertJSONFragment(['ok' => false, 'error' => 'FORBIDDEN']);
    }

    public function testWebSinRolRequeridoRedirigeAlDashboard(): void
    {
        $resultado = $this->withSession([
            'logged_in' => true,
            'activo'    => true,
            'roles'     => ['CONSULTA'],
        ])->get('/usuarios');

        $resultado->assertRedirectTo('/dashboard');
    }

    public function testInspectorConRolCorrectoAtraviesaElFiltroDeRol(): void
    {
        $resultado = $this->withSession([
            'logged_in' => true,
            'activo'    => true,
            'roles'     => ['INSPECTOR'],
        ])->get('/inspector/dashboard');

        $resultado->assertStatus(200);
    }

    public function testDashboardResuelveElDestinoSegunElRol(): void
    {
        $resultado = $this->withSession([
            'logged_in' => true,
            'activo'    => true,
            'roles'     => ['CONSULTA'],
        ])->get('/dashboard');

        $resultado->assertRedirectTo('/consulta/dashboard');
    }
}