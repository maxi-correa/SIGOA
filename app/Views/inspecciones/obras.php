<?php

/**
 * Listado de obras cuyo historial de inspecciones se puede consultar
 * (Fase E.6).
 *
 * Es el punto de entrada a la navegación `Obra → Fechas → Inspecciones →
 * Fotografías` para INSPECTOR y CONSULTA. Los roles administrativos no llegan
 * aquí: entran desde *Ver obra*, en el panel de una obra ya seleccionada.
 *
 * La pantalla es de **solo consulta**: cada tarjeta abre el histórico de su
 * obra y no hay ninguna acción de alta. Crear inspecciones sigue siendo
 * exclusivo del inspector y únicamente desde su vista de obra.
 *
 * Como el resto de la navegación histórica, la vista no conoce roles: el
 * controlador declara el prefijo de las URL (`$base`) y el destino del botón
 * "volver" (`$volver_url`, `$volver_texto`).
 */
$base        = $base ?? '/inspecciones';
$volverUrl   = $volver_url ?? '/dashboard';
$volverTexto = $volver_texto ?? 'Volver';
$esInspector = (bool) ($es_inspector ?? false);
$obras       = is_array($obras ?? null) ? $obras : [];
?>
<?= $this->extend('layouts/auth') ?>

<?= $this->section('styles') ?>
    <link rel="stylesheet" href="<?= base_url('assets/css/pages/inspecciones-obras.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ico-page">

    <div class="ico-barra-acciones">
        <a href="<?= site_url($volverUrl) ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            <?= esc($volverTexto) ?>
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

    <header class="ico-encabezado">
        <h1 class="ico-titulo">Inspecciones</h1>

        <p class="ico-subtitulo">
            <?= $esInspector
                ? 'Consulte el historial de inspecciones de las obras que tuvo asignadas.'
                : 'Consulte el historial de inspecciones de las obras.' ?>
        </p>
    </header>

    <?php if ($obras === []): ?>

        <section class="ico-vacio">
            <div class="ico-vacio-icono" aria-hidden="true">
                <i class="bi bi-clipboard-check"></i>
            </div>

            <h2 class="ico-vacio-titulo">No hay obras para consultar</h2>

            <p class="ico-vacio-texto">
                <?= $esInspector
                    ? 'No tiene obras asignadas ni inspecciones registradas que consultar.'
                    : 'No hay obras registradas en el sistema.' ?>
            </p>
        </section>

    <?php else: ?>

        <ul class="ico-lista">
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

                /* Solo se muestra lo que existe: si no hay inspector o
                   representante vigente, la tarjeta lo dice en vez de callar. */
                $inspectorNombre = trim(
                    ($obra->inspector_apellido ?? '') . ', ' . ($obra->inspector_nombre ?? ''),
                    ' ,'
                );

                $representanteNombre = trim(
                    ($obra->representante_apellido ?? '') . ', ' . ($obra->representante_nombre ?? ''),
                    ' ,'
                );
                ?>
                <li class="ico-item">
                    <a class="ico-card" href="<?= site_url($base . '/ver/' . (int) $obra->id) ?>">
                        <div class="ico-card-superior">
                            <h2 class="ico-card-nombre"><?= esc($obra->nombre) ?></h2>
                            <span class="estado-badge <?= $estadoClase ?>"><?= esc($estadoNombre) ?></span>
                        </div>

                        <dl class="ico-card-datos">
                            <div class="ico-card-dato">
                                <dt>N° de expediente</dt>
                                <dd><?= esc($obra->expediente_municipal ?? '—') ?></dd>
                            </div>

                            <div class="ico-card-dato">
                                <dt>Inspector vigente</dt>
                                <dd>
                                    <?php if ($inspectorNombre !== ''): ?>
                                        <?= esc($inspectorNombre) ?>
                                    <?php else: ?>
                                        <span class="ico-sd">Sin inspector asignado</span>
                                    <?php endif; ?>
                                </dd>
                            </div>

                            <div class="ico-card-dato">
                                <dt>Representante técnico</dt>
                                <dd>
                                    <?php if ($representanteNombre !== ''): ?>
                                        <?= esc($representanteNombre) ?>
                                    <?php else: ?>
                                        <span class="ico-sd">Sin representante asignado</span>
                                    <?php endif; ?>
                                </dd>
                            </div>
                        </dl>

                        <span class="ico-card-apertura" aria-hidden="true">
                            <i class="bi bi-chevron-right"></i>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

    <?php endif; ?>

</div>

<?= $this->endSection() ?>
