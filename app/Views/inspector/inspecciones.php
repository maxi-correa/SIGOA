<?php

use App\Libraries\HoraInspeccion;
use App\Libraries\PlazoObra;

/**
 * Navegación histórica de las inspecciones de una obra (Fases E.3–E.6).
 *
 * Esta es la **única** pantalla de historial de inspecciones del sistema: la
 * ven el inspector desde *Mis obras* o desde *Inspecciones*, y también
 * CONSULTA, ADMINISTRADOR y SUPERADMINISTRADOR. Por eso vive aquí y no se
 * duplica por rol.
 *
 * La vista es deliberadamente agnóstica: no conoce roles ni decide a dónde
 * enlazar. El controlador declara el prefijo de las URL (`$base`) y el destino
 * del botón "volver" (`$volver_url`, `$volver_texto`), que es lo único que
 * cambia entre puntos de entrada. El valor por defecto es el del inspector, que
 * es el flujo existente desde E.3.
 */
$base         = $base ?? '/inspector/inspecciones';
$volverUrl    = $volver_url ?? '/inspector/obras/ver/' . (int) $obra->id;
$volverTexto  = $volver_texto ?? 'Volver a la obra';
$mensajeVacio = $mensaje_vacio ?? 'Se recomienda generar una nueva inspección para comenzar a registrar el seguimiento de la obra.';
?>
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
                <?= esc($mensajeVacio) ?>
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
                               href="<?= site_url($base . '/detalle/' . (int) $inspeccion->id) ?>">
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
