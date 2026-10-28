<?php
$rolesNav       = session()->get('roles') ?? [];
$jerarquiaRoles = ['SUPERADMINISTRADOR', 'ADMINISTRADOR', 'INSPECTOR', 'CONSULTA'];
$rolPrincipal   = '';

foreach ($jerarquiaRoles as $rolJerarquia) {
    if (in_array($rolJerarquia, $rolesNav, true)) {
        $rolPrincipal = $rolJerarquia;
        break;
    }
}

if ($rolPrincipal === '') {
    $rolPrincipal = (string) ($rolesNav[0] ?? '');
}

$rolBadgeClase       = $rolPrincipal !== ''
    ? 'badge-rol-' . strtolower(str_replace(' ', '-', $rolPrincipal))
    : '';
$rolBadgeOcultoMovil = $rolPrincipal === 'INSPECTOR';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= csrf_meta() ?>
    <title><?= esc($titulo) ?> — SIGOA</title>

    <!-- PWA (Fase D.1) -->
    <link rel="manifest" href="<?= base_url('manifest.json') ?>">
    <meta name="theme-color" content="#24344C">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="SIGOA">

    <!-- Identidad de la sesión (solo metadatos: ningún dato sensible) -->
    <meta name="sigOA-usuario-id" content="<?= esc((string) session()->get('user_id')) ?>">
    <meta name="sigOA-usuario-nombre" content="<?= esc((string) session()->get('user_name')) ?>">

    <!-- Tipografía Inter (local) -->
    <link rel="stylesheet" href="<?= base_url('assets/fonts/inter/fonts.css') ?>">

    <!-- Bootstrap Icons 1.11.3 (local) -->
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">

    <!-- Estilos SIGOA -->
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/components/forms.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/components/buttons.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/components/alerts.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/components/badges.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/components/estados.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/components/neutralizacion-estado.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/components/connectivity.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/components/navbar.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/components/sidebar.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/components/tables.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/components/modal.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/components/footer.css') ?>">

    <?= $this->renderSection('styles') ?>
</head>
<body class="layout-auth">
    <header class="topbar">
        <div class="topbar-left">
            <button type="button"
                    class="sidebar-toggle"
                    id="sidebarToggle"
                    aria-label="Abrir menú de navegación"
                    aria-controls="sidebar"
                    aria-expanded="false">
                <i class="bi bi-list" aria-hidden="true"></i>
            </button>
            <span class="brand">SIGOA</span>
        </div>
        <div class="topbar-user">
            <span class="connectivity-indicator"
                  id="sigaConnectividad"
                  role="status">
                <i class="bi bi-wifi connectivity-icon" aria-hidden="true"></i>
                <span class="connectivity-text">En línea</span>
            </span>
            <span class="user-info">
                Sesión: <strong><?= esc($user_name) ?></strong> (<?= esc($username) ?>)
            </span>
            <?php if ($rolPrincipal !== ''): ?>
                <span class="badge badge-rol topbar-role-badge <?= esc($rolBadgeClase, 'attr') ?><?= $rolBadgeOcultoMovil ? ' topbar-role-badge--hide-mobile' : '' ?>"><?= esc($rolPrincipal) ?></span>
            <?php endif; ?>
        </div>
    </header>

    <div class="app-layout">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <span class="sidebar-brand">SIGOA</span>
                <button type="button"
                        class="sidebar-close"
                        id="sidebarClose"
                        aria-label="Cerrar menú de navegación">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </div>
            <?= $this->include('layouts/partials/sidebar') ?>
        </aside>

        <main class="content">
            <?= $this->renderSection('content') ?>
        </main>
    </div>

    <footer class="app-footer" role="contentinfo">
        <div class="app-footer-inner">
            <div class="app-footer-line app-footer-line--primary">
                <strong>SIGOA</strong>
            </div>
            <div class="app-footer-line">
                Desarrollado por Maximiliano Correa
            </div>
            <div class="app-footer-line">
                Todos los derechos reservados · © <?= date('Y') ?>
            </div>
        </div>
    </footer>

    <div class="sidebar-backdrop" id="sidebarBackdrop" aria-hidden="true"></div>

    <script src="<?= base_url('assets/js/components/sidebar.js') ?>"></script>
    <script src="<?= base_url('assets/js/components/uuid.js') ?>"></script>
    <script src="<?= base_url('assets/js/components/csrf.js') ?>"></script>
    <script src="<?= base_url('assets/js/components/indexeddb.js') ?>"></script>
    <script src="<?= base_url('assets/js/components/connectivity.js') ?>"></script>
    <script src="<?= base_url('assets/js/components/sincronizacion.js') ?>"></script>
    <script src="<?= base_url('assets/js/app.js') ?>"></script>
    <?= $this->renderSection('scripts') ?>
</body>
</html>