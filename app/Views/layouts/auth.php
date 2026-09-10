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
    <link rel="stylesheet" href="<?= base_url('assets/css/components/buttons.css') ?>">

    <?= $this->renderSection('styles') ?>
</head>
<body>
    <div class="topbar">
        <span class="brand">SIGOA</span>
        <div>
            <span class="user-info">
                Sesión: <strong><?= esc($user_name) ?></strong> (<?= esc($username) ?>)
            </span>
            <a href="<?= site_url('/logout') ?>">Cerrar sesión</a>
        </div>
    </div>

    <div class="content">
        <?= $this->renderSection('content') ?>
    </div>

    <?= $this->renderSection('scripts') ?>
</body>
</html>
