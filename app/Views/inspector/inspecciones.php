<?= $this->extend('layouts/auth') ?>

<?= $this->section('styles') ?>
    <link rel="stylesheet" href="<?= base_url('assets/css/pages/inspector-inspecciones.css') ?>">
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

$totalInspecciones = (int) ($total_inspecciones ?? 0);
?>

<div class="iis-page">

    <div class="iis-barra-acciones">
        <a href="<?= site_url('/inspector/obras/ver/' . (int) $obra->id) ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            Volver a la obra
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
         Identidad de la obra y de la sección consultada
         ============================================================ -->
    <header class="iis-encabezado">
        <div class="iis-contexto">
            <p class="iis-obra"><?= esc($obra->nombre) ?></p>
            <span class="estado-badge <?= $estadoClase ?>"><?= esc($estadoNombre) ?></span>
        </div>

        <h1 class="iis-titulo">Inspecciones</h1>
    </header>

    <!-- ============================================================
         Estado de la consulta

         En E.3 la navegación por fecha, inspección y fotografías es E.4:
         la vista solo informa si la obra tiene inspecciones registradas.
         ============================================================ -->
    <?php if ($totalInspecciones > 0): ?>

        <section class="iis-estado">
            <div class="iis-estado-icono" aria-hidden="true">
                <i class="bi bi-journal-text"></i>
            </div>

            <h2 class="iis-estado-titulo">
                <?= $totalInspecciones === 1
                    ? '1 inspección registrada'
                    : $totalInspecciones . ' inspecciones registradas' ?>
            </h2>

            <p class="iis-estado-texto">
                La consulta de inspecciones por fecha se habilitará en una etapa
                posterior. Por el momento puede registrar una nueva inspección
                desde la obra.
            </p>
        </section>

    <?php else: ?>

        <section class="iis-vacio">
            <div class="iis-vacio-icono" aria-hidden="true">
                <i class="bi bi-clipboard-check"></i>
            </div>

            <h2 class="iis-vacio-titulo">No existen inspecciones aún</h2>

            <p class="iis-vacio-texto">
                Se recomienda generar una nueva inspección para comenzar a
                registrar el seguimiento de la obra.
            </p>
        </section>

    <?php endif; ?>

</div>

<?= $this->endSection() ?>
