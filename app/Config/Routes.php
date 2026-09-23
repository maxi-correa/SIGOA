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
// MIS DATOS — datos personales del usuario autenticado
// =============================================
$routes->get('/mis-datos', 'MisDatos::index', ['filter' => 'auth']);
$routes->post('/mis-datos/email', 'MisDatos::updateEmail', [
    'filter' => ['auth', 'role:ADMINISTRADOR,SUPERADMINISTRADOR'],
]);
$routes->post('/mis-datos/password', 'MisDatos::changePassword', ['filter' => 'auth']);
$routes->post('/mis-datos/verificar-password', 'MisDatos::verifyPassword', ['filter' => 'auth']);

// =============================================
// GESTIÓN DE USUARIOS — consulta/listado (ADMIN y SUPERADMIN)
// =============================================
$routes->get('/usuarios', 'Usuarios::index', [
    'filter' => ['auth', 'role:SUPERADMINISTRADOR,ADMINISTRADOR'],
]);

// =============================================
// GESTIÓN DE EMPRESAS — CRUD (ADMIN y SUPERADMIN)
// =============================================
$routes->get('/empresas', 'Empresas::index', [
    'filter' => ['auth', 'role:SUPERADMINISTRADOR,ADMINISTRADOR'],
]);
$routes->get('/empresas/nueva', 'Empresas::nueva', [
    'filter' => ['auth', 'role:SUPERADMINISTRADOR,ADMINISTRADOR'],
]);
$routes->post('/empresas/crear', 'Empresas::crear', [
    'filter' => ['auth', 'role:SUPERADMINISTRADOR,ADMINISTRADOR'],
]);
$routes->get('/empresas/editar/(:num)', 'Empresas::editar/$1', [
    'filter' => ['auth', 'role:SUPERADMINISTRADOR,ADMINISTRADOR'],
]);
$routes->post('/empresas/actualizar/(:num)', 'Empresas::actualizar/$1', [
    'filter' => ['auth', 'role:SUPERADMINISTRADOR,ADMINISTRADOR'],
]);

// LOGO DE EMPRESA — subir/reemplazar, eliminar y servir la imagen
$routes->post('/empresas/logo/actualizar', 'Empresas::subirLogo', [
    'filter' => ['auth', 'role:SUPERADMINISTRADOR,ADMINISTRADOR'],
]);
$routes->post('/empresas/logo/eliminar', 'Empresas::eliminarLogo', [
    'filter' => ['auth', 'role:SUPERADMINISTRADOR,ADMINISTRADOR'],
]);
$routes->get('/empresas/logo/(:num)', 'Empresas::verLogo/$1', [
    'filter' => ['auth', 'role:SUPERADMINISTRADOR,ADMINISTRADOR'],
]);

// =============================================
// REPRESENTANTES TÉCNICOS — CRUD (ADMIN y SUPERADMIN)
// =============================================
$routes->get('/representantes', 'RepresentantesTecnicos::index', [
    'filter' => ['auth', 'role:SUPERADMINISTRADOR,ADMINISTRADOR'],
]);
$routes->get('/representantes/nuevo', 'RepresentantesTecnicos::nuevo', [
    'filter' => ['auth', 'role:SUPERADMINISTRADOR,ADMINISTRADOR'],
]);
$routes->post('/representantes/crear', 'RepresentantesTecnicos::crear', [
    'filter' => ['auth', 'role:SUPERADMINISTRADOR,ADMINISTRADOR'],
]);
$routes->get('/representantes/editar/(:num)', 'RepresentantesTecnicos::editar/$1', [
    'filter' => ['auth', 'role:SUPERADMINISTRADOR,ADMINISTRADOR'],
]);
$routes->post('/representantes/actualizar/(:num)', 'RepresentantesTecnicos::actualizar/$1', [
    'filter' => ['auth', 'role:SUPERADMINISTRADOR,ADMINISTRADOR'],
]);
$routes->post('/representantes/estado', 'RepresentantesTecnicos::cambiarEstado', [
    'filter' => ['auth', 'role:SUPERADMINISTRADOR,ADMINISTRADOR'],
]);

// =============================================
// OBRAS — alta inicial y edición de datos básicos (ADMIN y SUPERADMIN)
// =============================================
$routes->post('/obras/crear', 'Obras::crear', [
    'filter' => ['auth', 'role:SUPERADMINISTRADOR,ADMINISTRADOR'],
]);

$routes->post('/obras/actualizar', 'Obras::actualizar', [
    'filter' => ['auth', 'role:SUPERADMINISTRADOR,ADMINISTRADOR'],
]);

// FICHA DE OBRA — visualización (ADMIN, SUPERADMIN y CONSULTA)
$routes->get('/obras/ver/(:num)', 'Obras::ver/$1', [
    'filter' => ['auth', 'role:SUPERADMINISTRADOR,ADMINISTRADOR,CONSULTA'],
]);

// FICHA DE OBRA — edición de datos operativos (ADMIN y SUPERADMIN)
$routes->post('/obras/ficha/actualizar', 'Obras::actualizarFicha', [
    'filter' => ['auth', 'role:SUPERADMINISTRADOR,ADMINISTRADOR'],
]);

// FICHA DE OBRA — cambio de inspector vigente (ADMIN y SUPERADMIN)
$routes->post('/obras/inspector/actualizar', 'Obras::actualizarInspector', [
    'filter' => ['auth', 'role:SUPERADMINISTRADOR,ADMINISTRADOR'],
]);

// FICHA DE OBRA — cambio de representante técnico vigente (ADMIN y SUPERADMIN)
$routes->post('/obras/representante/actualizar', 'Obras::actualizarRepresentante', [
    'filter' => ['auth', 'role:SUPERADMINISTRADOR,ADMINISTRADOR'],
]);

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

    // VISTA OPERATIVA DE LA OBRA (preparada) — accesible solo con asignación vigente
    $routes->get('obras/ver/(:num)', 'Inspector\Obras::ver/$1');
});

// =============================================
// ÁREA CONSULTA
// =============================================
$routes->group('consulta', [
    'filter' => ['auth', 'role:CONSULTA'],
], static function ($routes) {
    $routes->get('dashboard', 'Consulta\Dashboard::index');
});
