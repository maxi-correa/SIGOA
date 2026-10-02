<?php use App\Libraries\HoraInspeccion; use App\Libraries\PlazoObra; ?>
<?= $this->extend('layouts/auth') ?>

<?= $this->section('styles') ?>
    <link rel="stylesheet" href="<?= base_url('assets/css/pages/inspector-inspeccion-detalle.css') ?>">
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

/* Se muestran los datos tal como quedaron registrados: no se recalcula ni
   se completa nada (Fase E.4). */
$fechaTexto    = PlazoObra::formatearFecha($inspeccion->fecha_inspeccion) ?? (string) $inspeccion->fecha_inspeccion;
$horaBruta     = $inspeccion->hora_inspeccion === null ? null : (string) $inspeccion->hora_inspeccion;
$horaTexto     = HoraInspeccion::texto($horaBruta);
$sinHora       = HoraInspeccion::esSinHora($horaBruta);
$observacion   = trim((string) ($inspeccion->observacion ?? ''));

$inspectorNombre = trim(implode(' ', array_filter([
    (string) ($inspeccion->inspector_nombre ?? ''),
    (string) ($inspeccion->inspector_apellido ?? ''),
])));

if ($inspectorNombre === '') {
    $inspectorNombre = trim((string) ($inspeccion->inspector_usuario ?? ''));
}
?>

<div class="iid-page">

    <div class="iid-barra-acciones">
        <a href="<?= site_url('/inspector/inspecciones/ver/' . (int) $obra->id) ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            Volver a inspecciones
        </a>
    </div>

    <!-- ============================================================
         Identificación de la inspección: obra, fecha y hora registradas.
         ============================================================ -->
    <header class="iid-encabezado">
        <div class="iid-contexto">
            <p class="iid-obra"><?= esc($obra->nombre) ?></p>
            <span class="estado-badge <?= $estadoClase ?>"><?= esc($estadoNombre) ?></span>
        </div>

        <h1 class="iid-titulo">Inspección del <?= esc($fechaTexto) ?></h1>

        <p class="iid-hora<?= $sinHora ? ' iid-hora-sin' : '' ?>">
            <?= esc($horaTexto) ?>
        </p>
    </header>

    <!-- ============================================================
         Datos registrados de la inspección
         ============================================================ -->
    <section class="iid-datos">
        <h2 class="iid-seccion-titulo">Datos de la inspección</h2>

        <dl class="iid-grid">
            <div class="iid-dato">
                <dt>Fecha</dt>
                <dd><?= esc($fechaTexto) ?></dd>
            </div>

            <div class="iid-dato">
                <dt>Hora</dt>
                <dd class="<?= $sinHora ? 'iid-dato-sin-hora' : '' ?>"><?= esc($horaTexto) ?></dd>
            </div>

            <div class="iid-dato">
                <dt>Inspector</dt>
                <dd><?= esc($inspectorNombre !== '' ? $inspectorNombre : '—') ?></dd>
            </div>

            <div class="iid-dato iid-dato-ancho">
                <dt>Observaciones</dt>
                <dd><?= esc($observacion !== '' ? $observacion : 'Sin observaciones') ?></dd>
            </div>
        </dl>
    </section>

    <!-- ============================================================
         Fotografías

         La zona queda señalada pero vacía a propósito (E.5): la galería,
         las miniaturas y la descarga de originales no se implementan en
         esta fase, y anticipar markup o rutas de archivo sería mostrar
         algo que el servidor todavía no entrega.
         ============================================================ -->
    <section class="iid-fotografias">
        <h2 class="iid-seccion-titulo">Fotografías</h2>

        <div class="iid-fotografias-aviso">
            <i class="bi bi-camera" aria-hidden="true"></i>
            <p class="iid-fotografias-texto">
                Las fotografías de esta inspección se incorporarán en una etapa
                posterior.
            </p>
        </div>
    </section>

</div>

<?= $this->endSection() ?>
