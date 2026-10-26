<?php

use App\Libraries\PlazoObra;

$puedeEditar                 = $puede_editar ?? false;
$permiteProvisoria           = $permite_provisoria ?? false;
$permiteCorregirProvisoria   = $permite_corregir_provisoria ?? false;
$permiteDefinitiva           = $permite_definitiva ?? false;
$definitivaHabilitada        = $definitiva_habilitada ?? false;
$fechaPrevista               = $fecha_prevista_definitiva ?? null;
$fechaHoy                   = $fecha_hoy ?? date('Y-m-d');
$plazoDefault               = (int) ($plazo_conservacion_default ?? 180);

$errores    = session()->getFlashdata('errores_recepcion');
$hayErrores = is_array($errores) && $errores !== [];
$tipoError  = $hayErrores ? (string) old('tipo_recepcion') : '';

$valorFormulario = static function (string $tipo, string $campo, string $defecto = '') use ($tipoError): string {
    if ($tipoError !== $tipo) {
        return $defecto;
    }

    $old = old($campo);

    return $old !== null ? (string) $old : $defecto;
};

$fechaTexto = static fn($fecha): string => PlazoObra::formatearFecha($fecha) ?? '—';
$editandoProvisoria = $tipoError === 'actualizar_provisoria';

$fechaProvisoriaDefault = $provisoria !== null
    ? (string) $provisoria->fecha_recepcion
    : '';
$plazoProvisorioDefault = $provisoria !== null
    ? (string) (int) $provisoria->plazo_conservacion_dias
    : (string) $plazoDefault;
$observacionesProvisoriasDefault = $provisoria !== null
    ? (string) ($provisoria->observaciones ?? '')
    : '';

$fechaDefinitivaDefault = ($definitivaHabilitada && $fechaPrevista !== null && $fechaPrevista <= $fechaHoy)
    ? $fechaPrevista
    : '';
?>
<?= $this->extend('layouts/auth') ?>

<?= $this->section('styles') ?>
    <link rel="stylesheet" href="<?= base_url('assets/css/pages/ficha-obra.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/pages/obra-recepciones.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ficha-obra-page recepciones-page">
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

    <?php if ($hayErrores): ?>
        <div class="alert alert-danger" role="alert">
            <span class="alert-icon"><i class="bi bi-exclamation-circle"></i></span>
            <div>
                <?php foreach ($errores as $errorRecepcion): ?>
                    <div><?= esc($errorRecepcion) ?></div>
                <?php endforeach; ?>
            </div>
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

    <section class="ficha-datos recepciones-seccion" aria-labelledby="recepcionesTitulo">
        <h2 class="ficha-seccion-titulo" id="recepcionesTitulo">Recepciones de obra</h2>

        <p class="recepciones-aviso">
            <i class="bi bi-info-circle" aria-hidden="true"></i>
            La recepción provisoria inicia el plazo de conservación. La recepción
            definitiva se habilita desde la fecha prevista y finaliza la obra.
        </p>

        <dl class="recepciones-resumen">
            <div class="recepciones-resumen-dato">
                <dt>Recepción provisoria</dt>
                <dd><?= $provisoria !== null ? 'Registrada' : 'Pendiente' ?></dd>
            </div>
            <div class="recepciones-resumen-dato">
                <dt>Fecha provisoria</dt>
                <dd><?= esc($fechaTexto($provisoria->fecha_recepcion ?? null)) ?></dd>
            </div>
            <div class="recepciones-resumen-dato">
                <dt>Plazo de conservación</dt>
                <dd>
                    <?= $provisoria !== null
                        ? (int) $provisoria->plazo_conservacion_dias . ' días'
                        : $plazoDefault . ' días (previsto)' ?>
                </dd>
            </div>
            <div class="recepciones-resumen-dato">
                <dt>Definitiva prevista</dt>
                <dd><?= esc($fechaTexto($fechaPrevista)) ?></dd>
            </div>
            <div class="recepciones-resumen-dato">
                <dt>Recepción definitiva</dt>
                <dd><?= $definitiva !== null ? 'Registrada' : 'Pendiente' ?></dd>
            </div>
        </dl>

        <div class="recepciones-bloque">
            <div class="recepciones-bloque-encabezado">
                <div>
                    <h3 class="recepciones-subtitulo">Recepción provisoria</h3>
                    <p class="recepciones-bloque-nota">
                        Día 0 del plazo de conservación.
                    </p>
                </div>
                <?php if ($provisoria !== null): ?>
                    <span class="recepcion-estado recepcion-estado-registrada">
                        <i class="bi bi-check-circle" aria-hidden="true"></i>
                        Registrada
                    </span>
                <?php else: ?>
                    <span class="recepcion-estado recepcion-estado-pendiente">
                        <i class="bi bi-clock" aria-hidden="true"></i>
                        Pendiente
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($provisoria === null): ?>
                <?php if ($puedeEditar && $permiteProvisoria): ?>
                    <p class="recepciones-nota-estado">
                        Al registrar la recepción provisoria, la obra pasará a
                        <strong>EN PLAZO DE CONSERVACIÓN</strong> si aún está en ejecución.
                    </p>

                    <form method="post"
                          action="<?= site_url('/obras/recepciones/provisoria') ?>"
                          class="recepciones-form"
                          novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="obra_id" value="<?= (int) $obra->id ?>">
                        <input type="hidden" name="tipo_recepcion" value="provisoria">

                        <div class="recepciones-form-grid">
                            <div class="form-group">
                                <label for="fecha_recepcion_provisoria">
                                    Fecha de recepción
                                    <span class="recepciones-requerido" aria-hidden="true">*</span>
                                </label>
                                <input type="date"
                                       id="fecha_recepcion_provisoria"
                                       name="fecha_recepcion"
                                       class="form-control"
                                       max="<?= esc($fechaHoy, 'attr') ?>"
                                       value="<?= esc($valorFormulario('provisoria', 'fecha_recepcion'), 'attr') ?>">
                            </div>

                            <div class="form-group">
                                <label for="plazo_conservacion_dias">
                                    Plazo de conservación (días)
                                    <span class="recepciones-requerido" aria-hidden="true">*</span>
                                </label>
                                <input type="number"
                                       id="plazo_conservacion_dias"
                                       name="plazo_conservacion_dias"
                                       class="form-control"
                                       min="1"
                                       step="1"
                                       value="<?= esc($valorFormulario('provisoria', 'plazo_conservacion_dias', (string) $plazoDefault), 'attr') ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="observaciones_provisoria">Observaciones</label>
                            <textarea id="observaciones_provisoria"
                                      name="observaciones"
                                      class="form-control"
                                      rows="3"><?= esc($valorFormulario('provisoria', 'observaciones')) ?></textarea>
                        </div>

                        <div class="recepciones-form-acciones">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-lg" aria-hidden="true"></i>
                                Registrar recepción provisoria
                            </button>
                        </div>
                    </form>
                <?php elseif ($puedeEditar): ?>
                    <p class="recepciones-aviso recepciones-aviso-error">
                        <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                        El estado actual de la obra no permite registrar la recepción provisoria.
                    </p>
                <?php else: ?>
                    <p class="recepciones-aviso">
                        <i class="bi bi-lock" aria-hidden="true"></i>
                        La recepción provisoria aún no fue registrada.
                    </p>
                <?php endif; ?>
            <?php else: ?>
                <dl class="recepciones-detalle">
                    <div>
                        <dt>Fecha de recepción</dt>
                        <dd><?= esc($fechaTexto($provisoria->fecha_recepcion)) ?></dd>
                    </div>
                    <div>
                        <dt>Plazo aplicado</dt>
                        <dd><?= (int) $provisoria->plazo_conservacion_dias ?> días</dd>
                    </div>
                    <div>
                        <dt>Fecha prevista de la definitiva</dt>
                        <dd><?= esc($fechaTexto($provisoria->fecha_prevista_definitiva)) ?></dd>
                    </div>
                    <div class="recepciones-detalle-observaciones">
                        <dt>Observaciones</dt>
                        <dd><?= esc($provisoria->observaciones ?? '—') ?></dd>
                    </div>
                </dl>

                <?php if ($definitiva !== null): ?>
                    <p class="recepciones-aviso">
                        <i class="bi bi-lock" aria-hidden="true"></i>
                        La recepción definitiva ya fue registrada: los datos de la
                        recepción provisoria no pueden modificarse.
                    </p>
                <?php elseif ($puedeEditar && $permiteCorregirProvisoria): ?>
                    <details class="recepciones-editar" <?= $editandoProvisoria ? 'open' : '' ?>>
                        <summary>Corregir datos de la recepción provisoria</summary>
                        <p class="recepciones-nota-estado">
                            La fecha prevista de la definitiva se recalcula con la
                            fecha y el plazo guardados.
                        </p>

                        <form method="post"
                              action="<?= site_url('/obras/recepciones/provisoria/actualizar') ?>"
                              class="recepciones-form"
                              novalidate>
                            <?= csrf_field() ?>
                            <input type="hidden" name="obra_id" value="<?= (int) $obra->id ?>">
                            <input type="hidden" name="tipo_recepcion" value="actualizar_provisoria">

                            <div class="recepciones-form-grid">
                                <div class="form-group">
                                    <label for="fecha_recepcion_provisoria_editar">
                                        Fecha de recepción
                                        <span class="recepciones-requerido" aria-hidden="true">*</span>
                                    </label>
                                    <input type="date"
                                           id="fecha_recepcion_provisoria_editar"
                                           name="fecha_recepcion"
                                           class="form-control"
                                           max="<?= esc($fechaHoy, 'attr') ?>"
                                           value="<?= esc($valorFormulario('actualizar_provisoria', 'fecha_recepcion', $fechaProvisoriaDefault), 'attr') ?>">
                                </div>

                                <div class="form-group">
                                    <label for="plazo_conservacion_dias_editar">
                                        Plazo de conservación (días)
                                        <span class="recepciones-requerido" aria-hidden="true">*</span>
                                    </label>
                                    <input type="number"
                                           id="plazo_conservacion_dias_editar"
                                           name="plazo_conservacion_dias"
                                           class="form-control"
                                           min="1"
                                           step="1"
                                           value="<?= esc($valorFormulario('actualizar_provisoria', 'plazo_conservacion_dias', $plazoProvisorioDefault), 'attr') ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="observaciones_provisoria_editar">Observaciones</label>
                                <textarea id="observaciones_provisoria_editar"
                                          name="observaciones"
                                          class="form-control"
                                          rows="3"><?= esc($valorFormulario('actualizar_provisoria', 'observaciones', $observacionesProvisoriasDefault)) ?></textarea>
                            </div>

                            <div class="recepciones-form-acciones">
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-check-lg" aria-hidden="true"></i>
                                    Guardar cambios
                                </button>
                            </div>
                        </form>
                    </details>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="recepciones-bloque">
            <div class="recepciones-bloque-encabezado">
                <div>
                    <h3 class="recepciones-subtitulo">Recepción definitiva</h3>
                    <p class="recepciones-bloque-nota">
                        Cierra el plazo de conservación y finaliza la obra.
                    </p>
                </div>
                <?php if ($definitiva !== null): ?>
                    <span class="recepcion-estado recepcion-estado-registrada">
                        <i class="bi bi-check-circle" aria-hidden="true"></i>
                        Registrada
                    </span>
                <?php else: ?>
                    <span class="recepcion-estado recepcion-estado-pendiente">
                        <i class="bi bi-clock" aria-hidden="true"></i>
                        Pendiente
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($definitiva !== null): ?>
                <dl class="recepciones-detalle">
                    <div>
                        <dt>Fecha de recepción</dt>
                        <dd><?= esc($fechaTexto($definitiva->fecha_recepcion)) ?></dd>
                    </div>
                    <div class="recepciones-detalle-observaciones">
                        <dt>Observaciones</dt>
                        <dd><?= esc($definitiva->observaciones ?? '—') ?></dd>
                    </div>
                </dl>

                <p class="recepciones-aviso">
                    <i class="bi bi-lock" aria-hidden="true"></i>
                    La recepción definitiva es inmutable y no puede modificarse ni eliminarse.
                </p>
            <?php elseif ($provisoria === null): ?>
                <p class="recepciones-aviso">
                    <i class="bi bi-info-circle" aria-hidden="true"></i>
                    Registre primero la recepción provisoria para habilitar la definitiva.
                </p>
            <?php elseif (! $definitivaHabilitada): ?>
                <p class="recepciones-aviso recepciones-aviso-alerta">
                    <i class="bi bi-calendar-event" aria-hidden="true"></i>
                    La recepción definitiva se habilitará desde el
                    <strong><?= esc($fechaTexto($fechaPrevista)) ?></strong>.
                </p>
            <?php elseif ($puedeEditar && $permiteDefinitiva): ?>
                <p class="recepciones-nota-estado">
                    Al registrar la recepción definitiva, la obra pasará a
                    <strong>FINALIZADA</strong>.
                </p>

                <form method="post"
                      action="<?= site_url('/obras/recepciones/definitiva') ?>"
                      class="recepciones-form"
                      novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="obra_id" value="<?= (int) $obra->id ?>">
                    <input type="hidden" name="tipo_recepcion" value="definitiva">

                    <div class="recepciones-form-grid">
                        <div class="form-group">
                            <label for="fecha_recepcion_definitiva">
                                Fecha de recepción
                                <span class="recepciones-requerido" aria-hidden="true">*</span>
                            </label>
                            <input type="date"
                                   id="fecha_recepcion_definitiva"
                                   name="fecha_recepcion"
                                   class="form-control"
                                   min="<?= esc((string) $fechaPrevista, 'attr') ?>"
                                   max="<?= esc($fechaHoy, 'attr') ?>"
                                   value="<?= esc($valorFormulario('definitiva', 'fecha_recepcion', $fechaDefinitivaDefault), 'attr') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="observaciones_definitiva">Observaciones</label>
                        <textarea id="observaciones_definitiva"
                                  name="observaciones"
                                  class="form-control"
                                  rows="3"><?= esc($valorFormulario('definitiva', 'observaciones')) ?></textarea>
                    </div>

                    <div class="recepciones-form-acciones">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-lg" aria-hidden="true"></i>
                            Registrar recepción definitiva
                        </button>
                    </div>
                </form>
            <?php elseif ($puedeEditar): ?>
                <p class="recepciones-aviso recepciones-aviso-error">
                    <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                    El estado actual de la obra no permite registrar la recepción definitiva.
                </p>
            <?php else: ?>
                <p class="recepciones-aviso">
                    <i class="bi bi-lock" aria-hidden="true"></i>
                    La recepción definitiva aún no fue registrada.
                </p>
            <?php endif; ?>
        </div>
    </section>
</div>

<?= $this->endSection() ?>
