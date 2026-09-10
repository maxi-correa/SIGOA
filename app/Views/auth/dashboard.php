<?= $this->extend('layouts/auth') ?>

<?= $this->section('styles') ?>
    <link rel="stylesheet" href="<?= base_url('assets/css/pages/dashboard.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <h1><?= esc($titulo) ?></h1>
    <div class="welcome-box">
        <p>
            Bienvenido/a, <strong><?= esc($user_name) ?></strong>.<br>
            Ha iniciado sesión correctamente en SIGOA.
        </p>
        <p class="welcome-box-secondary">
            Esta es una página de verificación para comprobar que el mecanismo de autenticación
            funciona correctamente. El acceso a esta página requiere sesión activa.
        </p>
    </div>
<?= $this->endSection() ?>
