<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// =============================================
// RUTAS PÚBLICAS
// =============================================
$routes->get('/', 'Home::index');

$routes->get('/login', 'Auth::login');
$routes->post('/login', 'Auth::attemptLogin');
$routes->get('/logout', 'Auth::logout');

// =============================================
// REDIRECT GENÉRICO — usuario autenticado → su dashboard
// =============================================
$routes->get('/dashboard', 'Auth::redirectToDashboard', ['filter' => 'auth']);

// =============================================
// ÁREA SUPERADMINISTRADOR
// =============================================
$routes->group('superadmin', [
    'filter' => ['auth', 'role:SUPERADMINISTRADOR'],
], static function ($routes) {
    $routes->get('dashboard', 'Superadmin\Dashboard::index');
});

// =============================================
// ÁREA ADMINISTRADOR
// =============================================
$routes->group('admin', [
    'filter' => ['auth', 'role:ADMINISTRADOR'],
], static function ($routes) {
    $routes->get('dashboard', 'Admin\Dashboard::index');
});

// =============================================
// ÁREA INSPECTOR
// =============================================
$routes->group('inspector', [
    'filter' => ['auth', 'role:INSPECTOR'],
], static function ($routes) {
    $routes->get('dashboard', 'Inspector\Dashboard::index');
});

// =============================================
// ÁREA CONSULTA
// =============================================
$routes->group('consulta', [
    'filter' => ['auth', 'role:CONSULTA'],
], static function ($routes) {
    $routes->get('dashboard', 'Consulta\Dashboard::index');
});
