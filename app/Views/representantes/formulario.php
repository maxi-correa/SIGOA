<?= $this->extend('layouts/auth') ?>

<?= $this->section('styles') ?>
    <link rel="stylesheet" href="<?= base_url('assets/css/pages/representantes.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
$representante = $representante ?? null;
$esEdicion     = $representante !== null;

$erroresFormulario = session()->getFlashdata('errores_representante');

$tituloFormulario = $esEdicion ? 'Editar representante técnico' : 'Nuevo representante técnico';
$descripcion      = $esEdicion
    ? 'Modifique los datos del representante técnico.'
    : 'Registre un nuevo representante técnico. La matrícula es el único campo opcional.';

$valorNombre    = old('nombre', $representante->nombre ?? '');
$valorApellido  = old('apellido', $representante->apellido ?? '');
$valorTitulo    = old('titulo_profesional_id', $representante->titulo_profesional_id ?? '');
$valorMatricula = old('matricula', $representante->matricula ?? '');

$accionFormulario = $esEdicion
    ? site_url('/representantes/actualizar/' . (int) $representante->id)
    : site_url('/representantes/crear');
?>

<div class="representantes-page">

    <header class="representantes-header">
        <div class="representantes-header-texto">
            <h1><?= esc($tituloFormulario) ?></h1>
            <p class="representantes-subtitulo"><?= esc($descripcion) ?></p>
        </div>

        <a href="<?= site_url('/representantes') ?>" class="btn btn-secondary representantes-btn-volver">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            Volver al listado
        </a>
    </header>

    <?php if ($erroresFormulario): ?>
        <div class="alert alert-danger" role="alert">
            <span class="alert-icon"><i class="bi bi-exclamation-circle"></i></span>
            <span>
                <?php foreach ($erroresFormulario as $errorRepresentante): ?>
                    <div><?= esc($errorRepresentante) ?></div>
                <?php endforeach; ?>
            </span>
        </div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger" role="alert">
            <span class="alert-icon"><i class="bi bi-exclamation-circle"></i></span>
            <span><?= esc(session()->getFlashdata('error')) ?></span>
        </div>
    <?php endif; ?>

    <div class="representantes-card">
        <form method="post" action="<?= esc($accionFormulario) ?>" novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="nombre">
                    Nombre <span class="representantes-requerido" aria-hidden="true">*</span>
                </label>
                <input type="text"
                       id="nombre"
                       name="nombre"
                       class="form-control"
                       maxlength="100"
                       autocomplete="off"
                       placeholder="Ej.: JUAN CARLOS"
                       value="<?= esc($valorNombre) ?>">
            </div>

            <div class="form-group">
                <label for="apellido">
                    Apellido <span class="representantes-requerido" aria-hidden="true">*</span>
                </label>
                <input type="text"
                       id="apellido"
                       name="apellido"
                       class="form-control"
                       maxlength="100"
                       autocomplete="off"
                       placeholder="Ej.: PÉREZ"
                       value="<?= esc($valorApellido) ?>">
            </div>

            <div class="form-group">
                <label for="titulo_profesional_id">
                    Título profesional <span class="representantes-requerido" aria-hidden="true">*</span>
                </label>
                <select id="titulo_profesional_id"
                        name="titulo_profesional_id"
                        class="form-control">
                    <option value="">Seleccione un título</option>
                    <?php foreach ($titulos as $titulo): ?>
                        <option value="<?= (int) $titulo->id ?>"
                            <?= (string) $valorTitulo === (string) $titulo->id ? 'selected' : '' ?>>
                            <?= esc($titulo->titulo) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="matricula">Matrícula</label>
                <input type="text"
                       id="matricula"
                       name="matricula"
                       class="form-control"
                       maxlength="30"
                       autocomplete="off"
                       placeholder="Ej.: 1234"
                       value="<?= esc($valorMatricula) ?>">
                <span class="representantes-ayuda">
                    Campo opcional. Si se completa, no puede repetirse en otro representante técnico.
                </span>
            </div>

            <div class="representantes-card-acciones">
                <a href="<?= site_url('/representantes') ?>" class="btn btn-secondary">
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
