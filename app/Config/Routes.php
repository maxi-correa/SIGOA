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

// CERTIFICADOS DE OBRA — visualización (ADMIN, SUPERADMIN y CONSULTA)
$routes->get('/obras/certificados/(:num)', 'Certificados::index/$1', [
    'filter' => ['auth', 'role:SUPERADMINISTRADOR,ADMINISTRADOR,CONSULTA'],
]);

// CERTIFICADOS DE OBRA — confirmación de configuración económica (ADMIN y SUPERADMIN)
$routes->post('/obras/certificados/configurar', 'Certificados::confirmarConfiguracion', [
    'filter' => ['auth', 'role:SUPERADMINISTRADOR,ADMINISTRADOR'],
]);

// CERTIFICADOS DE OBRA — alta de certificado (ADMIN y SUPERADMIN)
$routes->post('/obras/certificados/crear', 'Certificados::crear', [
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

    // LISTADO DE OBRAS CONSULTABLES (Fase E.6) — sin asignación vigente: también
    // sirve cuando el inspector no tiene ninguna obra a cargo
    $routes->get('inspecciones', 'Inspector\Inspecciones::index');

    // NUEVA INSPECCIÓN LOCAL (Fase D.2) — autoriza e inicializa; se guarda en IndexedDB
    $routes->get('inspecciones/nueva/(:num)', 'Inspector\Inspecciones::nueva/$1');

    // HISTÓRICO DE INSPECCIONES DE LA OBRA (Fases E.3/E.4) — solo lectura, agrupado por fecha
    $routes->get('inspecciones/ver/(:num)', 'Inspector\Inspecciones::ver/$1');

    // DETALLE DE UNA INSPECCIÓN (Fase E.4) — solo lectura; la autorización se resuelve sobre su obra
    $routes->get('inspecciones/detalle/(:num)', 'Inspector\Inspecciones::detalle/$1');

    // SINCRONIZACIÓN DE INSPECCIONES (Fase D.3) — alta confirmada por uuid, idempotente (§55)
    $routes->post('sincronizar/inspecciones', 'Inspector\Sincronizar::inspecciones');

    // SINCRONIZACIÓN DE FOTOGRAFÍAS (Fase D.4) — alta multipart por uuid, idempotente (§56)
    $routes->post('sincronizar/fotografias', 'Inspector\Sincronizar::fotografias');

    // FOTOGRAFÍAS DE UNA INSPECCIÓN (Fase E.5) — solo lectura, por identidad técnica (uuid)
    $routes->get('fotografias/ver/(:segment)', 'Inspector\Fotografias::ver/$1');
    $routes->get('fotografias/mini/(:segment)', 'Inspector\Fotografias::miniatura/$1');
});

// =============================================
// HISTÓRICO DE INSPECCIONES — navegación compartida (Fase E.6)
//
// Es la MISMA navegación que la del inspector (Obra → Fechas → Inspecciones →
// Fotografías), con la MISMA implementación: `App\Controllers\Inspecciones`.
// Solo cambia la autorización, que es la de consulta del historial, y el punto
// de entrada (Administrador → Ver obra → Inspecciones, Consulta → Inspecciones).
//
// El listado de obras vive en el grupo de cada rol (inspector/consulta) y
// deliberadamente no existe aquí: los roles administrativos entran desde una
// obra ya seleccionada en su dashboard.
//
// No hay ruta de alta ni de sincronización en este grupo: crear inspecciones
// sigue siendo exclusivo del inspector y únicamente por su flujo propio.
// =============================================
$routes->group('inspecciones', [
    'filter' => ['auth', 'role:SUPERADMINISTRADOR,ADMINISTRADOR,CONSULTA,INSPECTOR'],
], static function ($routes) {
    $routes->get('ver/(:num)', 'Inspecciones::ver/$1');
    $routes->get('detalle/(:num)', 'Inspecciones::detalle/$1');

    $routes->get('fotografias/ver/(:segment)', 'Inspector\Fotografias::ver/$1');
    $routes->get('fotografias/mini/(:segment)', 'Inspector\Fotografias::miniatura/$1');
});

// =============================================
// ÁREA CONSULTA
// =============================================
$routes->group('consulta', [
    'filter' => ['auth', 'role:CONSULTA'],
], static function ($routes) {
    $routes->get('dashboard', 'Consulta\Dashboard::index');

    // LISTADO DE OBRAS CONSULTABLES (Fase E.6) — solo lectura, sin creación
    $routes->get('inspecciones', 'Inspecciones::index');
});
