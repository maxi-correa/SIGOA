<?php

use CodeIgniter\HTTP\SiteURI;

$rolesNav      = $roles ?? session()->get('roles') ?? [];
$gestionaUsuarios = array_intersect(['SUPERADMINISTRADOR', 'ADMINISTRADOR'], $rolesNav) !== [];
$esInspector      = in_array('INSPECTOR', $rolesNav, true);
$esConsulta       = in_array('CONSULTA', $rolesNav, true);
/* `SiteURI::getPath()` devuelve la ruta con `index.php` delante
   (`Config\App::$indexPage`), que no corresponde a ninguna entrada del menú.
   Para el estado activo hace falta la ruta real, sin el archivo índice. */
$uriActual    = service('request')->getUri();
$currentPath   = trim(
    (string) ($uriActual instanceof SiteURI ? $uriActual->getRoutePath() : $uriActual->getPath()),
    '/'
);
$primerSegmento = explode('/', $currentPath)[0] ?? '';

/* `Inspecciones` cubre todo el historial con cualquiera de sus prefijos
   (`/inspector/inspecciones`, `/consulta/inspecciones` y `/inspecciones`),
   incluido el detalle y la galería. */
$esInspecciones    = $primerSegmento === 'inspecciones'
    || in_array($currentPath, ['inspector/inspecciones', 'consulta/inspecciones'], true)
    || str_starts_with($currentPath, 'inspector/inspecciones/')
    || str_starts_with($currentPath, 'consulta/inspecciones/');
/* `Mis obras` es la vista operativa del inspector, no su historial: son
   entradas distintas y no pueden quedar activas a la vez. */
$esMisObras        = $primerSegmento === 'inspector' && ! $esInspecciones;
/* `Inicio` sigue siendo el dashboard de cada rol. Para consulta solo cuenta
   su propio dashboard: el resto de `/consulta/...` pertenece a `Inspecciones`. */
$esInicio          = $esConsulta
    ? $currentPath === 'consulta/dashboard'
    : in_array($primerSegmento, ['', 'dashboard', 'superadmin', 'admin', 'obras'], true);
$esUsuarios        = $currentPath === 'usuarios';
$esEmpresas        = $primerSegmento === 'empresas';
$esRepresentantes  = $primerSegmento === 'representantes';
$esMisDatos        = $currentPath === 'mis-datos';
?>

<nav class="sidebar-nav" aria-label="Navegación principal">
    <ul class="sidebar-menu">

        <?php if ($esInspector): ?>

            <li class="sidebar-item">
                <a class="sidebar-link<?= $esMisObras ? ' is-active' : '' ?>" href="<?= site_url('/inspector/dashboard') ?>">
                    <i class="bi bi-clipboard-check" aria-hidden="true"></i>
                    <span>Mis obras</span>
                </a>
            </li>

        <?php else: ?>

            <li class="sidebar-item">
                <a class="sidebar-link<?= $esInicio ? ' is-active' : '' ?>" href="<?= site_url('/dashboard') ?>">
                    <i class="bi bi-house-door" aria-hidden="true"></i>
                    <span>Inicio</span>
                </a>
            </li>

        <?php endif; ?>

        <?php /* Listado de obras consultables (Fase E.6).

                 Lo ofrecen los roles que navegan por rol: el inspector (historial
                 de las obras que tuvo asignadas) y consulta (historial de las
                 obras). Los roles administrativos entran desde *Ver obra* y por
                 eso no tienen este ítem: no existe listado global para ellos. */ ?>
        <?php if ($esInspector || $esConsulta): ?>
            <li class="sidebar-item">
                <a class="sidebar-link<?= $esInspecciones ? ' is-active' : '' ?>"
                   href="<?= site_url($esInspector ? '/inspector/inspecciones' : '/consulta/inspecciones') ?>">
                    <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
                    <span>Inspecciones</span>
                </a>
            </li>
        <?php endif; ?>

        <?php if ($gestionaUsuarios): ?>
            <li class="sidebar-item">
                <a class="sidebar-link<?= $esUsuarios ? ' is-active' : '' ?>" href="<?= site_url('/usuarios') ?>">
                    <i class="bi bi-person-gear" aria-hidden="true"></i>
                    <span>Gestión de usuarios</span>
                </a>
            </li>
        <?php endif; ?>

        <?php if ($gestionaUsuarios): ?>
            <li class="sidebar-item">
                <a class="sidebar-link<?= $esEmpresas ? ' is-active' : '' ?>" href="<?= site_url('/empresas') ?>">
                    <i class="bi bi-building" aria-hidden="true"></i>
                    <span>Empresas</span>
                </a>
            </li>
        <?php endif; ?>

        <?php if ($gestionaUsuarios): ?>
            <li class="sidebar-item">
                <a class="sidebar-link<?= $esRepresentantes ? ' is-active' : '' ?>" href="<?= site_url('/representantes') ?>">
                    <i class="bi bi-person-vcard" aria-hidden="true"></i>
                    <span>Representantes técnicos</span>
                </a>
            </li>
        <?php endif; ?>
    </ul>

    <div class="sidebar-divider" role="presentation"></div>

    <ul class="sidebar-menu">
        <li class="sidebar-item">
            <a class="sidebar-link<?= $esMisDatos ? ' is-active' : '' ?>" href="<?= site_url('/mis-datos') ?>">
                <i class="bi bi-person" aria-hidden="true"></i>
                <span>Mis datos</span>
            </a>
        </li>

        <li class="sidebar-item">
            <a class="sidebar-link sidebar-link-logout" href="<?= site_url('/logout') ?>">
                <i class="bi bi-box-arrow-left" aria-hidden="true"></i>
                <span>Cerrar sesión</span>
            </a>
        </li>
    </ul>
</nav>
