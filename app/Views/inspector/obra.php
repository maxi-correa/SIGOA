<?= $this->extend('layouts/auth') ?>

<?= $this->section('styles') ?>
    <link rel="stylesheet" href="<?= base_url('assets/css/pages/inspector-obra.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
$estadoNombre = strtoupper((string) ($obra->estado_nombre ?? ''));

$estadoClase = match ($estadoNombre) {
    'EN EJECUCIÓN'             => 'estado-ejecucion',
    'NEUTRALIZADA'             => 'estado-neutralizada',
    'EN PLAZO DE CONSERVACIÓN' => 'estado-conservacion',
    'FINALIZADA'               => 'estado-finalizada',
    default                    => 'estado-previo',
};
?>

<div class="io-page">

    <div class="io-barra-acciones">
        <a href="<?= site_url('/inspector/dashboard') ?>" class="btn btn-secondary io-btn-volver">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            Mis obras
        </a>
    </div>

    <?php if (session()->getFlashdata('warning')): ?>
        <div class="alert alert-warning" role="alert">
            <span class="alert-icon"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i></span>
            <span><?= esc(session()->getFlashdata('warning')) ?></span>
        </div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger" role="alert">
            <span class="alert-icon"><i class="bi bi-exclamation-circle" aria-hidden="true"></i></span>
            <span><?= esc(session()->getFlashdata('error')) ?></span>
        </div>
    <?php endif; ?>

    <!-- ============================================================
         Encabezado de identificación de la obra
         ============================================================ -->
    <section class="io-identidad">
        <div class="io-identidad-principal">
            <h1 class="io-nombre"><?= esc($obra->nombre) ?></h1>
            <span class="estado-badge <?= $estadoClase ?>"><?= esc($estadoNombre) ?></span>
        </div>

        <dl class="io-grid">
            <div class="io-dato">
                <dt>N° de expediente</dt>
                <dd><?= esc($obra->expediente_municipal) ?></dd>
            </div>

            <div class="io-dato">
                <dt>Tipo de licitación</dt>
                <dd>
                    <?php if ($obra->tipo_licitacion_nombre): ?>
                        <?= esc($obra->tipo_licitacion_nombre) ?>
                    <?php else: ?>
                        <span class="io-sd">S/D</span>
                    <?php endif; ?>
                </dd>
            </div>

            <div class="io-dato">
                <dt>N° de licitación</dt>
                <dd>
                    <?php if ($obra->numero_licitacion): ?>
                        <?= esc($obra->numero_licitacion) ?>
                    <?php else: ?>
                        <span class="io-sd">S/D</span>
                    <?php endif; ?>
                </dd>
            </div>
        </dl>
    </section>

    <!-- ============================================================
         Aviso informativo: vista operativa en preparación
         ============================================================ -->
    <section class="io-aviso">
        <div class="io-aviso-icono" aria-hidden="true">
            <i class="bi bi-tools"></i>
        </div>
        <div class="io-aviso-texto">
            <h2 class="io-aviso-titulo">Vista operativa en preparación</h2>
            <p>
                Esta obra fue reconocida como una de sus asignaciones vigentes.
                Las herramientas de trabajo sobre la inspección se habilitarán
                próximamente.
            </p>
        </div>
    </section>

</div>

<?= $this->endSection() ?>