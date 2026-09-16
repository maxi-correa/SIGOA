<?= $this->extend('layouts/auth') ?>

<?= $this->section('styles') ?>
    <link rel="stylesheet" href="<?= base_url('assets/css/pages/empresas.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
$empresa    = $empresa ?? null;
$esEdicion  = $empresa !== null;

$erroresFormulario = session()->getFlashdata('errores_empresa');
$huboEnvio         = $erroresFormulario !== null;

$tituloFormulario = $esEdicion ? 'Editar empresa' : 'Nueva empresa';
$descripcion      = $esEdicion
    ? 'Modifique los datos de la empresa contratista.'
    : 'Registre una nueva empresa contratista. La razón social es el único campo obligatorio.';

$valorRazonSocial = old('razon_social', $empresa->razon_social ?? '');
$valorCuit        = old('cuit', $empresa->cuit ?? '');
$valorDomicilio   = old('domicilio', $empresa->domicilio ?? '');
$valorTelefono    = old('telefono', $empresa->telefono ?? '');
$valorEmail       = old('email', $empresa->email ?? '');

$activa = $esEdicion ? (bool) $empresa->activo : true;

if ($huboEnvio) {
    $activa = old('activo', '') === '1';
}

$accionFormulario = $esEdicion
    ? site_url('/empresas/actualizar/' . (int) $empresa->id)
    : site_url('/empresas/crear');
?>

<div class="empresas-page">

    <header class="empresas-header">
        <div class="empresas-header-texto">
            <h1><?= esc($tituloFormulario) ?></h1>
            <p class="empresas-subtitulo"><?= esc($descripcion) ?></p>
        </div>

        <a href="<?= site_url('/empresas') ?>" class="btn btn-secondary empresas-btn-volver">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            Volver al listado
        </a>
    </header>

    <?php if ($erroresFormulario): ?>
        <div class="alert alert-danger" role="alert">
            <span class="alert-icon"><i class="bi bi-exclamation-circle"></i></span>
            <span>
                <?php foreach ($erroresFormulario as $errorEmpresa): ?>
                    <div><?= esc($errorEmpresa) ?></div>
                <?php endforeach; ?>
            </span>
        </div>
    <?php endif; ?>

    <div class="empresas-card">
        <form method="post" action="<?= esc($accionFormulario) ?>" novalidate>

            <div class="form-group">
                <label for="razon_social">
                    Razón social <span class="empresas-requerido" aria-hidden="true">*</span>
                </label>
                <input type="text"
                       id="razon_social"
                       name="razon_social"
                       class="form-control"
                       maxlength="200"
                       autocomplete="off"
                       placeholder="Ej.: CONSTRUCTORA DEL SUR S.A."
                       value="<?= esc($valorRazonSocial) ?>">
            </div>

            <div class="form-group">
                <label for="cuit">CUIT</label>
                <input type="text"
                       id="cuit"
                       name="cuit"
                       class="form-control"
                       maxlength="13"
                       autocomplete="off"
                       placeholder="Ej.: 30-12345678-9"
                       value="<?= esc($valorCuit) ?>">
            </div>

            <div class="form-group">
                <label for="domicilio">Domicilio</label>
                <input type="text"
                       id="domicilio"
                       name="domicilio"
                       class="form-control"
                       maxlength="255"
                       autocomplete="off"
                       placeholder="Ej.: Av. Roca 1234"
                       value="<?= esc($valorDomicilio) ?>">
            </div>

            <div class="form-group">
                <label for="telefono">Teléfono</label>
                <input type="text"
                       id="telefono"
                       name="telefono"
                       class="form-control"
                       maxlength="50"
                       autocomplete="off"
                       placeholder="Ej.: 0291 4567890"
                       value="<?= esc($valorTelefono) ?>">
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email"
                       id="email"
                       name="email"
                       class="form-control"
                       maxlength="150"
                       autocomplete="off"
                       placeholder="Ej.: contacto@empresa.com.ar"
                       value="<?= esc($valorEmail) ?>">
            </div>

            <div class="form-group">
                <label class="form-check">
                    <input type="checkbox"
                           id="activo"
                           name="activo"
                           value="1"
                           <?= $activa ? 'checked' : '' ?>>
                    <span class="form-check-label">Empresa activa</span>
                </label>
                <span class="empresas-ayuda">
                    Solo las empresas activas están disponibles al asignarlas a obras.
                </span>
            </div>

            <div class="empresas-card-acciones">
                <a href="<?= site_url('/empresas') ?>" class="btn btn-secondary">
                    Cancelar
                </a>
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-lg" aria-hidden="true"></i>
                    Guardar
                </button>
            </div>

        </form>
    </div>

</div>

<?= $this->endSection() ?>