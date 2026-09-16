<?php
$rolesNav      = $roles ?? session()->get('roles') ?? [];
$gestionaUsuarios = array_intersect(['SUPERADMINISTRADOR', 'ADMINISTRADOR'], $rolesNav) !== [];
$currentPath   = trim((string) service('request')->getUri()->getPath(), '/');
$primerSegmento = explode('/', $currentPath)[0] ?? '';

$esInicio      = in_array($primerSegmento, ['', 'dashboard', 'superadmin', 'admin', 'inspector', 'consulta'], true);
$esUsuarios    = $currentPath === 'usuarios';
$esEmpresas    = $primerSegmento === 'empresas';
$esMisDatos    = $currentPath === 'mis-datos';
?>

<nav class="sidebar-nav" aria-label="Navegación principal">
    <ul class="sidebar-menu">
        <li class="sidebar-item">
            <a class="sidebar-link<?= $esInicio ? ' is-active' : '' ?>" href="<?= site_url('/dashboard') ?>">
                <i class="bi bi-house-door" aria-hidden="true"></i>
                <span>Inicio</span>
            </a>
        </li>

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
