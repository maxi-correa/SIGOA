<?php

use App\Libraries\CertificacionObra;
use App\Libraries\PlazoObra;
use App\Libraries\ResolucionObra;

$puedeEditar      = $puede_editar ?? false;
$permiteRegistrar = $permite_registrar ?? false;
$editable         = $puedeEditar && $permiteRegistrar;

$erroresResolucion = session()->getFlashdata('errores_resolucion');
$modalAbierto      = is_array($erroresResolucion) && $erroresResolucion !== [];
$editando          = $modalAbierto && (string) old('resolucion_id') !== '';
$accionFormulario  = $editando
    ? '/obras/resoluciones/actualizar'
    : '/obras/resoluciones/crear';

$valorCampo = static function (string $campo, string $defecto = ''): string {
    $old = old($campo);

    if ($old !== null) {
        return (string) $old;
    }

    return $defecto;
};

$fechaTexto  = static fn($fecha): string => PlazoObra::formatearFecha($fecha) ?? '—';
$montoTexto  = static fn($monto): string => ($monto === null || $monto === '')
    ? '—'
    : CertificacionObra::formatearImporte($monto);
$numeroTexto = static fn($numero): string => $numero === null ? '—' : (string) $numero;
$anioTexto   = static fn($anio): string => $anio === null ? '—' : (string) $anio;
?>
<?= $this->extend('layouts/auth') ?>

<?= $this->section('styles') ?>
    <link rel="stylesheet" href="<?= base_url('assets/css/pages/ficha-obra.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/pages/obra-resoluciones.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ficha-obra-page resoluciones-page"
     data-puede-editar="<?= $editable ? '1' : '0' ?>"
     data-url-crear="<?= site_url('/obras/resoluciones/crear') ?>"
     data-url-actualizar="<?= site_url('/obras/resoluciones/actualizar') ?>">

    <div class="ficha-barra-acciones">
        <a href="<?= site_url('/obras/ver/' . (int) $obra->id) ?>" class="btn btn-secondary ficha-btn-volver">
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

    <section class="ficha-datos resoluciones-tabla-seccion">
        <div class="resoluciones-tabla-encabezado">
            <h2 class="ficha-seccion-titulo">Resoluciones</h2>
            <?php if ($editable): ?>
                <button type="button"
                        class="btn btn-success"
                        id="btnNuevaResolucion"
                        aria-haspopup="dialog"
                        aria-controls="modalResolucion">
                    <i class="bi bi-plus-lg" aria-hidden="true"></i>
                    Registrar resolución
                </button>
            <?php endif; ?>
        </div>

        <?php if ($puedeEditar && ! $permiteRegistrar): ?>
            <p class="resoluciones-aviso">
                El estado actual de la obra no permite registrar ni modificar resoluciones.
            </p>
        <?php endif; ?>

        <div class="sigoa-table-wrap">
            <table class="sigoa-table resoluciones-tabla">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>N°</th>
                        <th>Año</th>
                        <th>Fecha</th>
                        <th class="resoluciones-col-importe">Monto</th>
                        <th class="resoluciones-col-importe">Días</th>
                        <th>Estado</th>
                        <th>Observaciones</th>
                        <?php if ($editable): ?>
                            <th class="resoluciones-col-acciones">Acciones</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($resoluciones === []): ?>
                        <tr>
                            <td colspan="<?= $editable ? 9 : 8 ?>" class="resoluciones-tabla-vacia">
                                No hay resoluciones registradas.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($resoluciones as $resolucion): ?>
                            <?php
                            $confirmada     = (int) $resolucion->confirmada === 1;
                            $requiereObserv = ResolucionObra::requiereObservaciones($resolucion->tipo_nombre ?? null);
                            $completa       = ResolucionObra::puedeConfirmarse(
                                $resolucion->tipo_nombre ?? null,
                                $resolucion->numero === null ? null : (int) $resolucion->numero,
                                $resolucion->anio === null ? null : (int) $resolucion->anio,
                                $resolucion->fecha_resolucion,
                                $resolucion->observaciones
                            );
                            $motivoIncompleta = $requiereObserv
                                ? 'Complete número, año, fecha y observaciones para confirmar'
                                : 'Complete número, año y fecha para confirmar';
                            ?>
                            <tr>
                                <td><?= esc($resolucion->tipo_nombre ?? '—') ?></td>
                                <td><?= esc($numeroTexto($resolucion->numero)) ?></td>
                                <td><?= esc($anioTexto($resolucion->anio)) ?></td>
                                <td><?= esc($fechaTexto($resolucion->fecha_resolucion)) ?></td>
                                <td class="resoluciones-col-importe"><?= esc($montoTexto($resolucion->monto)) ?></td>
                                <td class="resoluciones-col-importe"><?= esc(ResolucionObra::formatearDias($resolucion->dias)) ?></td>
                                <td>
                                    <span class="resolucion-estado <?= $confirmada ? 'resolucion-estado-confirmada' : 'resolucion-estado-pendiente' ?>">
                                        <?= $confirmada ? 'Confirmada' : 'Pendiente' ?>
                                    </span>
                                </td>
                                <td class="resoluciones-observaciones"><?= esc($resolucion->observaciones ?? '—') ?></td>

                                <?php if ($editable): ?>
                                    <td class="resoluciones-col-acciones">
                                        <?php if (! $confirmada): ?>
                                            <div class="resoluciones-acciones">
                                                <button type="button"
                                                        class="btn-icon"
                                                        data-accion="editar"
                                                        title="Modificar"
                                                        aria-label="Modificar resolución"
                                                        data-id="<?= (int) $resolucion->id ?>"
                                                        data-tipo="<?= (int) $resolucion->tipo_resolucion_id ?>"
                                                        data-numero="<?= esc((string) ($resolucion->numero ?? ''), 'attr') ?>"
                                                        data-anio="<?= esc((string) ($resolucion->anio ?? ''), 'attr') ?>"
                                                        data-fecha="<?= esc((string) ($resolucion->fecha_resolucion ?? ''), 'attr') ?>"
                                                        data-monto="<?= esc((string) ($resolucion->monto ?? ''), 'attr') ?>"
                                                        data-dias="<?= esc((string) ($resolucion->dias ?? ''), 'attr') ?>"
                                                        data-observaciones="<?= esc((string) ($resolucion->observaciones ?? ''), 'attr') ?>">
                                                    <i class="bi bi-pencil" aria-hidden="true"></i>
                                                </button>
                                                <?php if ($completa): ?>
                                                    <button type="button"
                                                            class="btn-icon"
                                                            data-accion="confirmar"
                                                            title="Confirmar"
                                                            aria-label="Confirmar resolución"
                                                            data-id="<?= (int) $resolucion->id ?>">
                                                        <i class="bi bi-check-lg" aria-hidden="true"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button"
                                                            class="btn-icon"
                                                            disabled
                                                            title="<?= esc($motivoIncompleta, 'attr') ?>">
                                                        <i class="bi bi-check-lg" aria-hidden="true"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button type="button"
                                                        class="btn-icon btn-icon-danger"
                                                        data-accion="eliminar"
                                                        title="Eliminar"
                                                        aria-label="Eliminar resolución"
                                                        data-id="<?= (int) $resolucion->id ?>">
                                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <span class="resoluciones-bloqueada" title="La resolución confirmada no puede modificarse">
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

<?php if ($editable): ?>
<div class="modal-overlay"
     id="modalResolucion"
     <?= $modalAbierto ? '' : 'hidden' ?>
     aria-hidden="<?= $modalAbierto ? 'false' : 'true' ?>">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalResolucionTitulo">
        <h2 class="modal-title" id="modalResolucionTitulo">
            <i class="bi bi-file-earmark-check" aria-hidden="true"></i>
            <span id="modalResolucionTituloTexto"><?= $editando ? 'Modificar resolución' : 'Registrar resolución' ?></span>
        </h2>

        <?php if ($modalAbierto): ?>
            <div class="modal-error" role="alert" aria-live="polite">
                <?php foreach ($erroresResolucion as $errorResolucion): ?>
                    <div><?= esc($errorResolucion) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= site_url($accionFormulario) ?>" id="formResolucion" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="obra_id" value="<?= (int) $obra->id ?>">
            <input type="hidden" name="resolucion_id" id="resolucion_id" value="<?= esc($valorCampo('resolucion_id'), 'attr') ?>">

            <div class="form-group">
                <label for="tipo_resolucion_id">
                    Tipo de resolución
                    <span class="resoluciones-requerido" aria-hidden="true">*</span>
                </label>
                <select id="tipo_resolucion_id" name="tipo_resolucion_id" class="form-control">
                    <option value="">Seleccione el tipo</option>
                    <?php foreach ($tipos as $tipo): ?>
                        <option value="<?= (int) $tipo['id'] ?>"
                                data-monto="<?= esc($tipo['reglas']['monto'], 'attr') ?>"
                                data-dias="<?= esc($tipo['reglas']['dias'], 'attr') ?>"
                                data-observaciones="<?= esc($tipo['reglas']['observaciones'], 'attr') ?>"
                                <?= $valorCampo('tipo_resolucion_id') === (string) $tipo['id'] ? 'selected' : '' ?>>
                            <?= esc($tipo['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="resoluciones-form-grid">
                <div class="form-group">
                    <label for="numero">Número</label>
                    <input type="text"
                           id="numero"
                           name="numero"
                           class="form-control"
                           inputmode="numeric"
                           autocomplete="off"
                           value="<?= esc($valorCampo('numero'), 'attr') ?>">
                </div>

                <div class="form-group">
                    <label for="anio">Año</label>
                    <input type="text"
                           id="anio"
                           name="anio"
                           class="form-control"
                           inputmode="numeric"
                           maxlength="4"
                           autocomplete="off"
                           value="<?= esc($valorCampo('anio'), 'attr') ?>">
                </div>

                <div class="form-group">
                    <label for="fecha_resolucion">Fecha</label>
                    <input type="date"
                           id="fecha_resolucion"
                           name="fecha_resolucion"
                           class="form-control"
                           value="<?= esc($valorCampo('fecha_resolucion'), 'attr') ?>">
                </div>
            </div>

            <div class="form-group" id="grupo-monto">
                <label for="monto">Monto</label>
                <div class="cert-input-grupo">
                    <span class="cert-input-affijo" aria-hidden="true">$</span>
                    <input type="text"
                           id="monto"
                           name="monto"
                           class="form-control"
                           inputmode="decimal"
                           autocomplete="off"
                           placeholder="$ 10.000.000"
                           value="<?= esc($valorCampo('monto'), 'attr') ?>">
                </div>
            </div>

            <div class="form-group" id="grupo-dias">
                <label for="dias">Días</label>
                <input type="text"
                       id="dias"
                       name="dias"
                       class="form-control"
                       inputmode="numeric"
                       autocomplete="off"
                       value="<?= esc($valorCampo('dias'), 'attr') ?>">
            </div>

            <div class="form-group">
                <label for="observaciones">
                    Observaciones
                    <span class="resoluciones-requerido" id="observaciones-requerido" aria-hidden="true" hidden>*</span>
                </label>
                <textarea id="observaciones"
                          name="observaciones"
                          class="form-control"
                          rows="3"><?= esc($valorCampo('observaciones')) ?></textarea>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" id="btnCancelarResolucion">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-lg" aria-hidden="true"></i>
                    Guardar
                </button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modalConfirmarResolucion" hidden aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalConfirmarResolucionTitulo">
        <h2 class="modal-title" id="modalConfirmarResolucionTitulo">
            <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
            ¿Confirmar resolución?
        </h2>
        <p class="modal-message">
            Una vez confirmada, la resolución no podrá modificarse ni eliminarse.
        </p>
        <form method="post" action="<?= site_url('/obras/resoluciones/confirmar') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="obra_id" value="<?= (int) $obra->id ?>">
            <input type="hidden" name="resolucion_id" id="resolucion_id_confirmar" value="">
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" id="btnCancelarConfirmarResolucion">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-lg" aria-hidden="true"></i>
                    Confirmar
                </button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="modalEliminarResolucion" hidden aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalEliminarResolucionTitulo">
        <h2 class="modal-title" id="modalEliminarResolucionTitulo">
            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
            ¿Eliminar resolución?
        </h2>
        <p class="modal-message">
            Esta acción no puede deshacerse.
        </p>
        <form method="post" action="<?= site_url('/obras/resoluciones/eliminar') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="obra_id" value="<?= (int) $obra->id ?>">
            <input type="hidden" name="resolucion_id" id="resolucion_id_eliminar" value="">
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" id="btnCancelarEliminarResolucion">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-danger">
                    <i class="bi bi-trash" aria-hidden="true"></i>
                    Eliminar
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script src="<?= base_url('assets/js/pages/obra-resoluciones.js') ?>"></script>
<?= $this->endSection() ?>
