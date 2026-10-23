<?php

use App\Libraries\PlazoObra;

$puedeEditar      = $puede_editar ?? false;
$permiteIniciar   = $permite_iniciar ?? false;
$permiteReiniciar = $permite_reiniciar ?? false;
$plazoConfirmado  = $plazo_confirmado ?? false;
$editable         = $puedeEditar && ($permiteIniciar || $permiteReiniciar);

$errores         = session()->getFlashdata('errores_neutralizacion');
$hayErrores      = is_array($errores) && $errores !== [];
$erroresReinicio = $hayErrores && old('fecha_reinicio') !== null;
$erroresCrear    = $hayErrores && old('fecha_reinicio') === null;

$abierta = $abierta ?? null;
$totalNeutralizado = (int) ($total_neutralizado_dias ?? 0);

$valorCampo = static function (string $campo, string $defecto = ''): string {
    $old = old($campo);

    return $old !== null ? (string) $old : $defecto;
};

$fechaTexto = static fn($fecha): string => PlazoObra::formatearFecha($fecha) ?? '—';
?>
<?= $this->extend('layouts/auth') ?>

<?= $this->section('styles') ?>
    <link rel="stylesheet" href="<?= base_url('assets/css/pages/ficha-obra.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/pages/obra-neutralizaciones.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ficha-obra-page neutralizaciones-page"
     data-puede-editar="<?= $editable ? '1' : '0' ?>"
     data-permite-iniciar="<?= $permiteIniciar ? '1' : '0' ?>"
     data-permite-reiniciar="<?= $permiteReiniciar ? '1' : '0' ?>"
     data-url-crear="<?= site_url('/obras/neutralizaciones/crear') ?>"
     data-url-reiniciar="<?= site_url('/obras/neutralizaciones/reiniciar') ?>"
     data-obra-fecha-inicio="<?= esc((string) ($obra->fecha_inicio ?? ''), 'attr') ?>"
     data-abierta-inicio="<?= esc((string) ($abierta->fecha_inicio ?? ''), 'attr') ?>">

    <div class="ficha-barra-acciones">
        <a href="<?= site_url('/obras/ver/' . (int) $obra->id) ?>" class="btn btn-volver ficha-btn-volver">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            Volver
        </a>
    </div>

    <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success" role="alert">
            <span class="alert-icon"><i class="bi bi-check-circle"></i></span>
            <span><?= esc(session()->getFlashdata('success')) ?></span>
        </div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger" role="alert">
            <span class="alert-icon"><i class="bi bi-exclamation-circle"></i></span>
            <span><?= esc(session()->getFlashdata('error')) ?></span>
        </div>
    <?php endif; ?>

    <section class="ficha-identidad" aria-label="Identificación de la obra">
        <div class="ficha-identidad-principal">
            <div class="ficha-titular">
                <h1 class="ficha-nombre"><?= esc($obra->nombre) ?></h1>
                <?php if (! empty($obra->codigo)): ?>
                    <span class="ficha-codigo"><?= esc($obra->codigo) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <dl class="ficha-grid">
            <div class="ficha-dato">
                <dt>N° de expediente</dt>
                <dd><?= esc($obra->expediente_municipal) ?></dd>
            </div>
            <div class="ficha-dato">
                <dt>Estado</dt>
                <dd><?= esc($obra->estado_nombre ?? '—') ?></dd>
            </div>
        </dl>
    </section>

    <section class="ficha-datos neutralizaciones-tabla-seccion">
        <h2 class="ficha-seccion-titulo">Neutralizaciones</h2>

        <dl class="neutralizaciones-resumen">
            <div class="neutralizaciones-resumen-dato">
                <dt>Períodos registrados</dt>
                <dd><?= count($neutralizaciones) ?></dd>
            </div>
            <div class="neutralizaciones-resumen-dato">
                <dt>Días neutralizados</dt>
                <dd><?= $totalNeutralizado ?></dd>
            </div>
            <div class="neutralizaciones-resumen-dato">
                <dt>Neutralización abierta</dt>
                <dd><?= $abierta !== null ? 'Sí' : 'No' ?></dd>
            </div>
        </dl>

        <p class="neutralizaciones-aviso">
            <i class="bi bi-info-circle" aria-hidden="true"></i>
            Los días neutralizados se computan desde la fecha de neutralización
            hasta el día anterior al reinicio. Una obra solo puede tener una
            neutralización abierta a la vez; mientras exista, la fecha de
            finalización queda suspendida.
        </p>

        <div class="neutralizaciones-tabla-encabezado">
            <p class="ficha-etiqueta">Historial de períodos</p>
            <?php if ($editable && $permiteIniciar): ?>
                <button type="button"
                        class="btn btn-success"
                        id="btnNuevaNeutralizacion"
                        aria-haspopup="dialog"
                        aria-controls="modalNeutralizacion">
                    <i class="bi bi-pause-circle" aria-hidden="true"></i>
                    Registrar neutralización
                </button>
            <?php endif; ?>
        </div>

        <?php if ($puedeEditar && ! $plazoConfirmado): ?>
            <p class="neutralizaciones-aviso">
                <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                El plazo inicial de la obra debe estar confirmado antes de
                registrar una neutralización.
            </p>
        <?php elseif ($puedeEditar && ! $permiteIniciar && ! $permiteReiniciar): ?>
            <p class="neutralizaciones-aviso">
                <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                El estado actual de la obra no permite neutralizarla ni
                registrar un reinicio.
            </p>
        <?php endif; ?>

        <div class="sigoa-table-wrap">
            <table class="sigoa-table neutralizaciones-tabla">
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Fecha de neutralización</th>
                        <th>Fecha de reinicio</th>
                        <th class="neutralizaciones-col-dias">Días</th>
                        <th>Estado</th>
                        <th>Observaciones</th>
                        <?php if ($editable): ?>
                            <th class="neutralizaciones-col-acciones">Acciones</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($neutralizaciones === []): ?>
                        <tr>
                            <td colspan="<?= $editable ? 7 : 6 ?>" class="neutralizaciones-tabla-vacia">
                                No hay neutralizaciones registradas.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($neutralizaciones as $periodo): ?>
                            <?php
                            $periodoAbierto = $periodo->fecha_reinicio === null;
                            $periodoDias    = PlazoObra::diasNeutralizados($periodo->fecha_inicio, $periodo->fecha_reinicio);
                            ?>
                            <tr>
                                <td><?= (int) $periodo->numero ?></td>
                                <td><?= esc($fechaTexto($periodo->fecha_inicio)) ?></td>
                                <td><?= esc($fechaTexto($periodo->fecha_reinicio)) ?></td>
                                <td class="neutralizaciones-col-dias">
                                    <?= $periodoDias === null ? '—' : (int) $periodoDias ?>
                                </td>
                                <td>
                                    <span class="neutralizacion-estado <?= $periodoAbierto ? 'neutralizacion-estado-abierta' : 'neutralizacion-estado-cerrada' ?>">
                                        <?= $periodoAbierto ? 'Abierta' : 'Cerrada' ?>
                                    </span>
                                </td>
                                <td class="neutralizaciones-observaciones"><?= esc($periodo->observaciones ?? '—') ?></td>

                                <?php if ($editable): ?>
                                    <td class="neutralizaciones-col-acciones">
                                        <?php if ($periodoAbierto && $permiteReiniciar): ?>
                                            <div class="neutralizaciones-acciones">
                                                <button type="button"
                                                        class="btn-accion-secundaria btn-icon"
                                                        data-accion="reiniciar"
                                                        title="Registrar reinicio"
                                                        aria-label="Registrar reinicio"
                                                        data-id="<?= (int) $periodo->id ?>">
                                                    <i class="bi bi-play-circle" aria-hidden="true"></i>
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <span class="neutralizaciones-inmutable" title="El período confirmado no puede modificarse">
                                                <i class="bi bi-lock" aria-hidden="true"></i>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php if ($editable && $permiteIniciar): ?>
<div class="modal-overlay"
     id="modalNeutralizacion"
     <?= $erroresCrear ? '' : 'hidden' ?>
     aria-hidden="<?= $erroresCrear ? 'false' : 'true' ?>">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalNeutralizacionTitulo">
        <h2 class="modal-title" id="modalNeutralizacionTitulo">
            <i class="bi bi-pause-circle" aria-hidden="true"></i>
            Registrar neutralización
        </h2>
        <p class="modal-message">
            La obra pasará al estado NEUTRALIZADA y dejará de contar días hasta
            que se registre el reinicio. La neutralización queda confirmada y no
            podrá modificarse.
        </p>

        <dl class="ficha-resumen">
            <dt>N° de neutralización</dt>
            <dd><?= (int) $proximo_numero ?></dd>
        </dl>

        <?php if ($erroresCrear): ?>
            <div class="modal-error" role="alert" aria-live="polite">
                <?php foreach ($errores as $error): ?>
                    <div><?= esc($error) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= site_url('/obras/neutralizaciones/crear') ?>" id="formNeutralizacion" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="obra_id" value="<?= (int) $obra->id ?>">

            <div class="form-group">
                <label for="fecha_inicio">
                    Fecha de neutralización
                    <span class="neutralizaciones-requerido" aria-hidden="true">*</span>
                </label>
                <input type="date"
                       id="fecha_inicio"
                       name="fecha_inicio"
                       class="form-control"
                       value="<?= esc($valorCampo('fecha_inicio'), 'attr') ?>">
                <p class="neutralizaciones-error" id="error-fecha-inicio" hidden></p>
            </div>

            <div class="form-group">
                <label for="observaciones">Observaciones</label>
                <textarea id="observaciones"
                          name="observaciones"
                          class="form-control"
                          rows="3"><?= esc($valorCampo('observaciones')) ?></textarea>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-cancelar" id="btnCancelarNeutralizacion">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-lg" aria-hidden="true"></i>
                    Registrar
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($editable && $permiteReiniciar): ?>
<div class="modal-overlay"
     id="modalReiniciar"
     <?= $erroresReinicio ? '' : 'hidden' ?>
     aria-hidden="<?= $erroresReinicio ? 'false' : 'true' ?>">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalReiniciarTitulo">
        <h2 class="modal-title" id="modalReiniciarTitulo">
            <i class="bi bi-play-circle" aria-hidden="true"></i>
            Registrar reinicio
        </h2>
        <p class="modal-message">
            La obra volverá al estado EN EJECUCIÓN. El día del reinicio no se
            cuenta como neutralizado.
        </p>

        <?php if ($erroresReinicio): ?>
            <div class="modal-error" role="alert" aria-live="polite">
                <?php foreach ($errores as $error): ?>
                    <div><?= esc($error) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= site_url('/obras/neutralizaciones/reiniciar') ?>" id="formReiniciar" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="obra_id" value="<?= (int) $obra->id ?>">

            <div class="form-group">
                <label for="fecha_reinicio">
                    Fecha de reinicio
                    <span class="neutralizaciones-requerido" aria-hidden="true">*</span>
                </label>
                <input type="date"
                       id="fecha_reinicio"
                       name="fecha_reinicio"
                       class="form-control"
                       min="<?= esc((string) ($abierta->fecha_inicio ?? ''), 'attr') ?>"
                       value="<?= esc($valorCampo('fecha_reinicio'), 'attr') ?>">
                <p class="neutralizaciones-error" id="error-fecha-reinicio" hidden></p>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-cancelar" id="btnCancelarReiniciar">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-lg" aria-hidden="true"></i>
                    Registrar reinicio
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script src="<?= base_url('assets/js/pages/obra-neutralizaciones.js') ?>"></script>
<?= $this->endSection() ?>
