<?php use App\Libraries\HoraInspeccion; use App\Libraries\PlazoObra; ?>
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

/* El orden y la agrupación por fecha los resuelve la base de datos
   (InspeccionModel::listarPorObraAgrupado): la vista solo los presenta. */
$grupos = is_array($grupos ?? null) ? $grupos : [];
$total  = (int) ($total ?? 0);
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

        <?php if ($total > 0): ?>
            <p class="iis-resumen">
                <?= $total === 1
                    ? '1 inspección registrada'
                    : $total . ' inspecciones registradas' ?>
            </p>
        <?php endif; ?>
    </header>

    <!-- ============================================================
         Estado vacío (E.3): la obra todavía no tiene inspecciones
         registradas en el servidor.
         ============================================================ -->
    <?php if ($total === 0): ?>

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

    <?php else: ?>

        <!-- ============================================================
             Histórico agrupado por fecha (E.4)

             La base entrega los grupos ya ordenados: fechas descendentes y,
             dentro de cada fecha, inspecciones de la hora más reciente a la
             más antigua. Las que no tienen hora se muestran al final del día
             y se identifican como tales; `00:00` es una hora válida.
             ============================================================ -->
        <?php foreach ($grupos as $grupo): ?>
            <?php $fechaTexto = PlazoObra::formatearFecha($grupo['fecha']) ?? $grupo['fecha']; ?>

            <section class="iis-grupo">
                <h2 class="iis-grupo-fecha">
                    <span class="iis-grupo-fecha-texto"><?= esc($fechaTexto) ?></span>
                    <span class="iis-grupo-total">
                        <?= $grupo['total'] === 1
                            ? '1 inspección'
                            : $grupo['total'] . ' inspecciones' ?>
                    </span>
                </h2>

                <ul class="iis-lista">
                    <?php foreach ($grupo['inspecciones'] as $inspeccion): ?>
                        <?php $horaTexto = HoraInspeccion::texto(
                            $inspeccion->hora_inspeccion === null ? null : (string) $inspeccion->hora_inspeccion
                        ); ?>

                        <li class="iis-item">
                            <a class="iis-item-enlace"
                               href="<?= site_url('/inspector/inspecciones/detalle/' . (int) $inspeccion->id) ?>">
                                <span class="iis-item-hora<?= HoraInspeccion::esSinHora(
                                    $inspeccion->hora_inspeccion === null ? null : (string) $inspeccion->hora_inspeccion
                                ) ? ' iis-item-hora-sin' : '' ?>">
                                    <?= esc($horaTexto) ?>
                                </span>

                                <span class="iis-item-cuerpo">
                                    <?php $observacion = trim((string) ($inspeccion->observacion ?? '')); ?>

                                    <?php if ($observacion !== ''): ?>
                                        <span class="iis-item-observacion"><?= esc($observacion) ?></span>
                                    <?php else: ?>
                                        <span class="iis-item-observacion iis-item-observacion-vacia">Sin observaciones</span>
                                    <?php endif; ?>

                                    <span class="iis-item-ver">
                                        Ver detalle
                                        <i class="bi bi-chevron-right" aria-hidden="true"></i>
                                    </span>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>

    <?php endif; ?>

</div>

<?= $this->endSection() ?>
