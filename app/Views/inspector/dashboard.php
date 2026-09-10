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
        <p>
            Rol activo: <strong><?= esc(implode(', ', $roles)) ?></strong>
        </p>
    </div>
<?php if (session()->getFlashdata('warning')): ?>
    <div class="alert alert-warning" role="alert">
        <span class="alert-icon"><i class="bi bi-exclamation-triangle"></i></span>
        <span><?= esc(session()->getFlashdata('warning')) ?></span>
    </div>
<?php endif; ?>
<?= $this->endSection() ?>
