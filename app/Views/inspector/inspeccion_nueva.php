<?= $this->extend('layouts/auth') ?>

<?= $this->section('styles') ?>
    <link rel="stylesheet" href="<?= base_url('assets/css/pages/inspeccion-nueva.css') ?>">
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

<div class="nin-page"
     id="datosLocal"
     data-obra-id="<?= (int) $obra_id ?>"
     data-inspector-id="<?= (int) $inspector_id ?>">

    <div class="nin-barra-acciones">
        <a href="<?= site_url('/inspector/obras/ver/' . (int) $obra_id) ?>" class="btn btn-secondary nin-btn-volver">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            Volver a la obra
        </a>
    </div>

    <section class="nin-identidad">
        <div class="nin-identidad-principal">
            <h1 class="nin-nombre"><?= esc($obra->nombre) ?></h1>
            <span class="estado-badge <?= $estadoClase ?>"><?= esc($estadoNombre) ?></span>
        </div>
    </section>

    <?php if (session()->getFlashdata('warning')): ?>
        <div class="alert alert-warning" role="alert">
            <span class="alert-icon"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i></span>
            <span><?= esc(session()->getFlashdata('warning')) ?></span>
        </div>
    <?php endif; ?>

    <div class="alert alert-info" role="alert">
        <span class="alert-icon"><i class="bi bi-info-circle" aria-hidden="true"></i></span>
        <span>
            Esta inspección se guarda <strong>en este dispositivo</strong> y queda pendiente de
            la sincronización con el servidor en una etapa posterior.
        </span>
    </div>

    <div id="alertaError" class="alert alert-danger" role="alert" hidden>
        <span class="alert-icon"><i class="bi bi-exclamation-circle" aria-hidden="true"></i></span>
        <span id="alertaErrorTexto"></span>
    </div>

    <!-- ============================================================
         Formulario de datos de la inspección
         ============================================================ -->
    <section class="nin-card" id="seccionFormulario">
        <h2 class="nin-card-titulo">Datos de la inspección</h2>

        <div id="alertaGuardada" class="alert alert-success" role="alert" hidden>
            <span class="alert-icon"><i class="bi bi-check-circle" aria-hidden="true"></i></span>
            <span>Inspección guardada en este dispositivo.</span>
        </div>

        <form id="formNuevaInspeccion" novalidate>
            <div class="form-group">
                <label for="fecha_inspeccion">Fecha de la inspección</label>
                <input type="date"
                       id="fecha_inspeccion"
                       name="fecha_inspeccion"
                       class="form-control"
                       required>
                <span class="field-error" id="errorFecha" hidden></span>
            </div>

            <div class="form-group">
                <label for="hora_inspeccion">Hora de la inspección</label>
                <input type="time"
                       id="hora_inspeccion"
                       name="hora_inspeccion"
                       class="form-control"
                       required>
                <span class="field-error" id="errorHora" hidden></span>
            </div>

            <div class="form-group">
                <label for="observacion">Observación</label>
                <textarea id="observacion"
                          name="observacion"
                          class="form-control nin-observacion"
                          rows="4"
                          placeholder="Comentarios de la inspección (opcional)"></textarea>
            </div>

            <div class="nin-acciones">
                <button type="submit" class="btn btn-success" id="btnGuardarInspeccion">
                    <i class="bi bi-save" aria-hidden="true"></i>
                    Guardar inspección
                </button>
            </div>
        </form>
    </section>

    <!-- ============================================================
         Fotografías (se habilita después de guardar la inspección)
         ============================================================ -->
    <section class="nin-card" id="seccionFotos" hidden>
        <h2 class="nin-card-titulo">Fotografías de la inspección</h2>
        <p class="nin-card-descripcion">
            Tome una o varias fotografías. Cada fotografía se optimiza en este
            dispositivo y se guarda junto con la inspección.
        </p>

        <label class="btn btn-primary nin-btn-foto" for="inputFoto">
            <i class="bi bi-camera" aria-hidden="true"></i>
            Tomar fotografía
        </label>
        <input type="file"
               id="inputFoto"
               accept="image/*"
               capture="environment"
               class="nin-input-foto"
               aria-label="Tomar o seleccionar fotografía">

        <p class="nin-procesando" id="procesandoFoto" hidden>
            <i class="bi bi-arrow-repeat bi-spin" aria-hidden="true"></i>
            Optimizando fotografía…
        </p>

        <ul class="nin-fotos-lista" id="listaFotos"></ul>
    </section>

    <div class="nin-pie" id="tituloPie" hidden>
        <a href="<?= site_url('/inspector/obras/ver/' . (int) $obra_id) ?>" class="btn btn-secondary nin-btn-volver">
            <i class="bi bi-check-circle" aria-hidden="true"></i>
            Finalizar y volver a la obra
        </a>
    </div>

</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script src="<?= base_url('assets/js/components/camera-resize.js') ?>"></script>
    <script src="<?= base_url('assets/js/pages/inspeccion-nueva.js') ?>"></script>
<?= $this->endSection() ?>