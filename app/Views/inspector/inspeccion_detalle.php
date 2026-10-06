<?php

use App\Libraries\HoraInspeccion;
use App\Libraries\PlazoObra;

/**
 * Detalle de una inspección registrada (Fases E.4, E.5 y E.6).
 *
 * Pantalla compartida por todos los roles que consultan el historial: no
 * conoce roles ni rutas fijas. El controlador declara el prefijo de la
 * navegación (`$base`) y el del servicio de fotografías (`$base_fotos`), que
 * es lo único que cambia entre puntos de entrada. Los valores por defecto
 * corresponden al flujo del inspector, que es el existente desde E.4/E.5.
 */
$base      = $base ?? '/inspector/inspecciones';
$baseFotos = $base_fotos ?? '/inspector/fotografias';
?>
<?= $this->extend('layouts/auth') ?>

<?= $this->section('styles') ?>
    <link rel="stylesheet" href="<?= base_url('assets/css/pages/inspector-inspeccion-detalle.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
/* Se muestran los datos tal como quedaron registrados: no se recalcula ni
   se completa nada (Fase E.4). Todo ello vive en el encabezado: el bloque de
   datos que lo repetía debajo se eliminó, y con él la necesidad de mostrar el
   estado de la obra, que es un dato de la obra y no de la inspección. */
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

/* Fotografías no anuladas de la inspección, ya ordenadas por la base de datos
   (Fase E.5). La View solo las presenta: no conoce rutas del almacenamiento. */
$fotografias = is_array($fotografias ?? null) ? $fotografias : [];
$totalFotos  = count($fotografias);
?>

<div class="iid-page">

    <div class="iid-barra-acciones">
        <a href="<?= site_url($base . '/ver/' . (int) $obra->id) ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            Volver a inspecciones
        </a>
    </div>

    <!-- ============================================================
         Datos registrados de la inspección: obra, fecha, hora, inspector
         y observación.

         La fecha es el título porque identifica la inspección dentro del
         historial de la obra, y hora, inspector y observación quedan como
         datos de apoyo. Cada dato aparece una sola vez en la pantalla.
         ============================================================ -->
    <header class="iid-encabezado">
        <p class="iid-obra"><?= esc($obra->nombre) ?></p>

        <h1 class="iid-titulo"><?= esc($fechaTexto) ?></h1>

        <div class="iid-meta">
            <p class="iid-meta-linea">
                <span class="iid-meta-etiqueta">Hora:</span>
                <span class="iid-meta-valor<?= $sinHora ? ' iid-meta-valor-sin' : '' ?>">
                    <?= esc($horaTexto) ?>
                </span>
            </p>

            <p class="iid-meta-linea">
                <span class="iid-meta-etiqueta">Inspector:</span>
                <span class="iid-meta-valor"><?= esc($inspectorNombre !== '' ? $inspectorNombre : '—') ?></span>
            </p>
        </div>

        <div class="iid-observacion">
            <p class="iid-observacion-etiqueta">Observación:</p>
            <p class="iid-observacion-texto">
                <?= esc($observacion !== '' ? $observacion : 'Sin observaciones') ?>
            </p>
        </div>
    </header>

    <!-- ============================================================
         Fotografías

         Galería de las fotografías no anuladas de esta inspección
         (Fase E.5). Miniatura y original se piden por identidad técnica
         (`uuid`) a `Inspector\Fotografias`; la View nunca recibe ni
         compone rutas del almacenamiento.

         La galería es en línea: el servidor responde con `no-store` y no
         hay caché histórica de fotografías en esta fase. Sin conexión
         no se ven, y tanto el aviso de esta sección como el indicador
         de la barra (D.1) lo dicen con texto e iconografía, nunca solo
         con color (RNF §44).
         ============================================================ -->
    <section class="iid-fotografias">
        <h2 class="iid-seccion-titulo">Fotografías</h2>

        <?php if ($totalFotos === 0): ?>

            <div class="iid-fotografias-aviso">
                <i class="bi bi-camera" aria-hidden="true"></i>
                <p class="iid-fotografias-texto">
                    Esta inspección no tiene fotografías registradas.
                </p>
            </div>

        <?php else: ?>

            <p class="iid-fotografias-conteo">
                <?= $totalFotos === 1 ? '1 fotografía' : $totalFotos . ' fotografías' ?>
            </p>

            <div class="iid-fotografias-sin-conexion" id="iidGaleriaSinConexion" hidden>
                <i class="bi bi-wifi-off" aria-hidden="true"></i>
                <p class="iid-fotografias-texto">
                    Sin conexión no se pueden ver las fotografías de esta
                    inspección: se sirven desde el servidor.
                </p>
            </div>

            <div class="iid-galeria" id="iidGaleria">

                <?php foreach ($fotografias as $indice => $fotografia): ?>
                    <?php
                    /* Solo presentación de lo registrado: si un dato no está, no
                       se inventa nada y simplemente no se muestra. */
                    $medida = '';

                    if ($fotografia->ancho !== null && $fotografia->alto !== null) {
                        $medida = (int) $fotografia->ancho . ' × ' . (int) $fotografia->alto . ' px';
                    }

                    $tamano = $fotografia->tamano_bytes === null ? null : (int) $fotografia->tamano_bytes;
                    $peso   = '';

                    if ($tamano !== null) {
                        if ($tamano < 1024) {
                            $peso = $tamano . ' B';
                        } elseif ($tamano < 1048576) {
                            $peso = number_format($tamano / 1024, 0, ',', '.') . ' KB';
                        } else {
                            $peso = number_format($tamano / 1048576, 1, ',', '.') . ' MB';
                        }
                    }

                    $detalle = implode(' · ', array_filter([$medida, $peso]));

                    /* `fecha_hora_captura` es el momento de la toma en el
                       dispositivo, con los mismos formateadores que el resto de
                       la página. */
                    $marca        = trim((string) ($fotografia->fecha_hora_captura ?? ''));
                    $capturaIso   = '';
                    $capturaTexto = '';

                    if ($marca !== '') {
                        $partes       = preg_split('/[ T]/', $marca) ?: [];
                        $capturaIso   = implode('T', array_slice($partes, 0, 2));
                        $capturaTexto = trim(
                            (PlazoObra::formatearFecha($partes[0] ?? '') ?? '')
                            . (isset($partes[1]) ? ' ' . HoraInspeccion::texto($partes[1]) : '')
                        );
                    }

                    $uuidFoto = (string) $fotografia->uuid;
                    ?>

                    <figure class="iid-foto">
                        <a class="iid-foto-enlace"
                           href="<?= site_url($baseFotos . '/ver/' . rawurlencode($uuidFoto)) ?>">
                            <img class="iid-foto-imagen"
                                 src="<?= site_url($baseFotos . '/mini/' . rawurlencode($uuidFoto)) ?>"
                                 alt="Fotografía <?= (int) $indice + 1 ?> de la inspección del <?= esc($fechaTexto) ?>"
                                 loading="lazy"
                                 decoding="async">
                            <span class="iid-foto-accion">
                                <i class="bi bi-arrows-fullscreen" aria-hidden="true"></i>
                                Ver original
                            </span>
                        </a>

                        <figcaption class="iid-foto-cap">
                            <?php if ($detalle !== ''): ?>
                                <span class="iid-foto-dato"><?= esc($detalle) ?></span>
                            <?php endif; ?>

                            <?php if ($capturaTexto !== ''): ?>
                                <time class="iid-foto-dato" datetime="<?= esc($capturaIso) ?>"><?= esc($capturaTexto) ?></time>
                            <?php endif; ?>
                        </figcaption>
                    </figure>

                <?php endforeach; ?>

            </div>

            <p class="iid-fotografias-nota">
                <i class="bi bi-info-circle" aria-hidden="true"></i>
                Las fotografías se sirven desde el servidor y requieren conexión.
            </p>

        <?php endif; ?>
    </section>

</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script src="<?= base_url('assets/js/pages/inspeccion-detalle.js') ?>"></script>
<?= $this->endSection() ?>
