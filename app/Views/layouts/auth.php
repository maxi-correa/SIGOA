<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($titulo) ?> — SIGOA</title>

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
    <link rel="stylesheet" href="<?= base_url('assets/css/components/navbar.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/components/sidebar.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/components/tables.css') ?>">

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
            <span class="user-info">
                Sesión: <strong><?= esc($user_name) ?></strong> (<?= esc($username) ?>)
            </span>
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

    <div class="sidebar-backdrop" id="sidebarBackdrop" aria-hidden="true"></div>

    <script src="<?= base_url('assets/js/components/sidebar.js') ?>"></script>
    <?= $this->renderSection('scripts') ?>
</body>
</html>