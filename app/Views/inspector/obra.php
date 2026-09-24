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

$puedeInspeccionar = (bool) ($puede_inspeccionar ?? false);
?>

<div class="io-page">

    <div class="io-barra-acciones">
        <a href="<?= site_url('/inspector/dashboard') ?>" class="btn btn-secondary io-btn-volver">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            Mis obras
        </a>

        <?php if ($puedeInspeccionar): ?>
            <a href="<?= site_url('/inspector/inspecciones/nueva/' . (int) $obra->id) ?>"
               class="btn btn-primary io-btn-nueva">
                <i class="bi bi-plus-circle" aria-hidden="true"></i>
                Nueva inspección
            </a>
        <?php endif; ?>
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

    <?php if ($puedeInspeccionar): ?>

        <!-- ============================================================
             Herramienta de inspección: nueva inspección local
             ============================================================ -->
        <section class="io-aviso">
            <div class="io-aviso-icono" aria-hidden="true">
                <i class="bi bi-camera"></i>
            </div>
            <div class="io-aviso-texto">
                <h2 class="io-aviso-titulo">Nueva inspección</h2>
                <p>
                    Puede iniciar una inspección para esta obra. La inspección y las
                    fotografías se guardan en este dispositivo y quedan pendientes de
                    la futura sincronización con el servidor.
                </p>
            </div>
        </section>

    <?php else: ?>

        <!-- ============================================================
             Aviso: estado que no permite nuevas inspecciones
             ============================================================ -->
        <section class="io-aviso io-aviso-bloqueado">
            <div class="io-aviso-icono" aria-hidden="true">
                <i class="bi bi-pause-circle"></i>
            </div>
            <div class="io-aviso-texto">
                <h2 class="io-aviso-titulo">No se pueden iniciar inspecciones</h2>
                <p>
                    Esta obra se encuentra en estado <?= esc($estadoNombre) ?>. Solo se
                    pueden iniciar nuevas inspecciones en obras en ejecución,
                    neutralizadas o en plazo de conservación.
                </p>
            </div>
        </section>

    <?php endif; ?>

    <!-- ============================================================
         Inspecciones guardadas en este dispositivo
         El contenido se completa con JavaScript desde IndexedDB
         (public/assets/js/pages/obra-inspecciones.js).
         ============================================================ -->
    <section class="io-locales"
             id="inspeccionesLocales"
             data-obra-id="<?= (int) $obra->id ?>"
             hidden>
        <div class="io-locales-encabezado">
            <h2 class="io-locales-titulo">
                <i class="bi bi-phone" aria-hidden="true"></i>
                Inspecciones en este dispositivo
            </h2>
            <p class="io-locales-subtitulo">
                Inspecciones guardadas localmente para esta obra, pendientes de sincronización.
            </p>
        </div>

        <ul class="io-locales-lista" id="inspeccionesLocalesLista"></ul>
    </section>

</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script src="<?= base_url('assets/js/pages/obra-inspecciones.js') ?>"></script>
<?= $this->endSection() ?>