<?= $this->extend('layouts/auth') ?>

<?= $this->section('styles') ?>
    <link rel="stylesheet" href="<?= base_url('assets/css/pages/inspector-dashboard.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="inspector-page">

    <header class="inspector-header">
        <h1>DGEOA</h1>
        <p class="inspector-subtitulo">Mis obras</p>
    </header>

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

    <?php if (empty($obras)): ?>

        <div class="inspector-vacio">
            <div class="inspector-vacio-icono" aria-hidden="true">
                <i class="bi bi-briefcase"></i>
            </div>
            <p class="inspector-vacio-titulo">No tiene obras asignadas</p>
            <p class="inspector-vacio-texto">
                Actualmente no existen obras asignadas a su usuario como inspector.
            </p>
            <p class="inspector-vacio-texto">
                Si considera que debería tener una obra asignada, comuníquese con
                un administrador para verificar y actualizar la información.
            </p>
        </div>

    <?php else: ?>

        <ul class="inspector-lista">
            <?php foreach ($obras as $obra): ?>
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
                <li class="inspector-item">
                    <a class="inspector-card"
                       href="<?= site_url('/inspector/obras/ver/' . (int) $obra->id) ?>">
                        <div class="inspector-card-superior">
                            <h2 class="inspector-card-nombre"><?= esc($obra->nombre) ?></h2>
                            <span class="estado-badge <?= $estadoClase ?>"><?= esc($estadoNombre) ?></span>
                        </div>

                        <dl class="inspector-card-datos">
                            <div class="inspector-card-dato">
                                <dt>N° de expediente</dt>
                                <dd><?= esc($obra->expediente_municipal) ?></dd>
                            </div>
                            <div class="inspector-card-dato">
                                <dt>Tipo de licitación</dt>
                                <dd>
                                    <?php if ($obra->tipo_licitacion_nombre): ?>
                                        <?= esc($obra->tipo_licitacion_nombre) ?>
                                    <?php else: ?>
                                        <span class="inspector-sd">S/D</span>
                                    <?php endif; ?>
                                </dd>
                            </div>
                            <div class="inspector-card-dato">
                                <dt>N° de licitación</dt>
                                <dd>
                                    <?php if ($obra->numero_licitacion): ?>
                                        <?= esc($obra->numero_licitacion) ?>
                                    <?php else: ?>
                                        <span class="inspector-sd">S/D</span>
                                    <?php endif; ?>
                                </dd>
                            </div>
                        </dl>

                        <span class="inspector-card-apertura" aria-hidden="true">
                            <i class="bi bi-chevron-right"></i>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

    <?php endif; ?>

</div>

<?= $this->endSection() ?>