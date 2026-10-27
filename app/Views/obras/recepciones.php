<?php

use App\Libraries\PlazoObra;

$puedeEditar              = $puede_editar ?? false;
$permiteProvisoria        = $permite_provisoria ?? false;
$permiteCorregirProv      = $permite_corregir_provisoria ?? false;
$permiteCorregirDef       = $permite_corregir_definitiva ?? false;
$permiteCorregirConjunta  = $permite_corregir_conjunta ?? false;
$permiteDefinitiva        = $permite_definitiva ?? false;
$definitivaHabilitada     = $definitiva_habilitada ?? false;
$fechaPrevista            = $fecha_prevista_definitiva ?? null;
$fechaHoy                 = $fecha_hoy ?? date('Y-m-d');
$plazoDefault             = (int) ($plazo_conservacion_default ?? 180);
$actasProvisoria          = $actas_provisoria ?? [];
$actasDefinitiva          = $actas_definitiva ?? [];
$historial                = $historial ?? [];

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

$nombreActa = static function (?int $documentoId, array $actas): string {
    foreach ($actas as $acta) {
        if ((int) $acta->id === (int) $documentoId) {
            return (string) ($acta->nombre_archivo_original ?? ('Acta #' . (int) $acta->id));
        }
    }

    return $documentoId === null ? 'Sin acta vinculada' : 'Acta #' . $documentoId . ' (no vigente)';
};

$selectActa = static function (string $id, string $nombre, array $actas, ?string $seleccionado, string $vacio): string {
    $html = '<select id="' . esc($id, 'attr') . '" name="' . esc($nombre, 'attr') . '" class="form-control">';
    $html .= '<option value="">' . esc($vacio) . '</option>';

    foreach ($actas as $acta) {
        $valor = (string) (int) $acta->id;
        $texto = (string) ($acta->nombre_archivo_original ?? ('Acta #' . $valor));
        $marca = $seleccionado === $valor ? ' selected' : '';
        $html .= '<option value="' . esc($valor, 'attr') . '"' . $marca . '>' . esc($texto) . '</option>';
    }

    return $html . '</select>';
};

$etiquetasEntidad = [
    'recepciones_provisorias' => 'Recepción provisoria',
    'recepciones_definitivas' => 'Recepción definitiva',
    'obras'                   => 'Obra',
];

$etiquetasCampo = [
    'fecha_recepcion'           => 'Fecha de recepción',
    'plazo_conservacion_dias'   => 'Plazo de conservación (días)',
    'fecha_prevista_definitiva' => 'Fecha prevista de la definitiva',
    'observaciones'             => 'Observaciones',
    'documento_id'              => 'Acta vinculada',
    'estado_obra_id'            => 'Estado de la obra',
];

$valorHistorial = static function (string $campo, ?string $valor) use ($estados_nombres): string {
    if ($valor === null || $valor === '') {
        return '—';
    }

    if ($campo === 'estado_obra_id') {
        return $estados_nombres[$valor] ?? ("Estado #" . $valor);
    }

    if ($campo === 'documento_id') {
        return 'Acta #' . $valor;
    }

    if (in_array($campo, ['fecha_recepcion', 'fecha_prevista_definitiva'], true)) {
        return PlazoObra::formatearFecha($valor) ?? $valor;
    }

    return $valor;
};

$fechaProvisoriaDefault = $provisoria !== null ? (string) $provisoria->fecha_recepcion : '';
$plazoProvisorioDefault = $provisoria !== null
    ? (string) (int) $provisoria->plazo_conservacion_dias
    : (string) $plazoDefault;
$observacionesProvisoriasDefault = $provisoria !== null ? (string) ($provisoria->observaciones ?? '') : '';
$actaProvisoriaDefault = $provisoria !== null && $provisoria->documento_id !== null ? (string) (int) $provisoria->documento_id : '';

$fechaDefinitivaDefault = ($definitivaHabilitada && $fechaPrevista !== null && $fechaPrevista <= $fechaHoy)
    ? $fechaPrevista
    : '';
$observacionesDefinitivasDefault = $definitiva !== null ? (string) ($definitiva->observaciones ?? '') : '';
$actaDefinitivaDefault = $definitiva !== null && $definitiva->documento_id !== null ? (string) (int) $definitiva->documento_id : '';
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
                    <p class="recepciones-bloque-nota">Día 0 del plazo de conservación.</p>
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
                        Si el acta ya fue cargada como documento, selecciónela y confirme la fecha de recepción.
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
                            <label for="documento_provisoria">Acta de recepción provisoria (opcional)</label>
                            <?= $selectActa(
                                'documento_provisoria',
                                'documento_id',
                                $actasProvisoria,
                                $valorFormulario('provisoria', 'documento_id'),
                                'Sin acta vinculada'
                            ) ?>
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
                    <div>
                        <dt>Acta vinculada</dt>
                        <dd><?= esc($nombreActa($provisoria->documento_id !== null ? (int) $provisoria->documento_id : null, $actasProvisoria)) ?></dd>
                    </div>
                    <div class="recepciones-detalle-observaciones">
                        <dt>Observaciones</dt>
                        <dd><?= esc($provisoria->observaciones ?? '—') ?></dd>
                    </div>
                </dl>

                <?php if ($puedeEditar && $permiteCorregirProv): ?>
                    <details class="recepciones-editar">
                        <summary>Corregir recepción provisoria</summary>
                        <p class="recepciones-nota-estado">
                            La fecha prevista de la definitiva se recalcula con la fecha y el plazo guardados.
                            Cada cambio queda registrado en el historial.
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
                                <label for="documento_provisoria_editar">Acta de recepción provisoria (opcional)</label>
                                <?= $selectActa(
                                    'documento_provisoria_editar',
                                    'documento_id',
                                    $actasProvisoria,
                                    $valorFormulario('actualizar_provisoria', 'documento_id', $actaProvisoriaDefault),
                                    'Sin acta vinculada'
                                ) ?>
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
                    <p class="recepciones-bloque-nota">Cierra el plazo de conservación y finaliza la obra.</p>
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
                    <div>
                        <dt>Acta vinculada</dt>
                        <dd><?= esc($nombreActa($definitiva->documento_id !== null ? (int) $definitiva->documento_id : null, $actasDefinitiva)) ?></dd>
                    </div>
                    <div class="recepciones-detalle-observaciones">
                        <dt>Observaciones</dt>
                        <dd><?= esc($definitiva->observaciones ?? '—') ?></dd>
                    </div>
                </dl>

                <?php if ($puedeEditar && $permiteCorregirDef): ?>
                    <details class="recepciones-editar">
                        <summary>Corregir recepción definitiva</summary>
                        <p class="recepciones-nota-estado">
                            La fecha no puede ser anterior a la fecha prevista calculada con la recepción provisoria.
                            Cada cambio queda registrado en el historial.
                        </p>

                        <form method="post"
                              action="<?= site_url('/obras/recepciones/definitiva/actualizar') ?>"
                              class="recepciones-form"
                              novalidate>
                            <?= csrf_field() ?>
                            <input type="hidden" name="obra_id" value="<?= (int) $obra->id ?>">
                            <input type="hidden" name="tipo_recepcion" value="actualizar_definitiva">

                            <div class="form-group">
                                <label for="fecha_recepcion_definitiva_editar">
                                    Fecha de recepción
                                    <span class="recepciones-requerido" aria-hidden="true">*</span>
                                </label>
                                <input type="date"
                                       id="fecha_recepcion_definitiva_editar"
                                       name="fecha_recepcion"
                                       class="form-control"
                                       max="<?= esc($fechaHoy, 'attr') ?>"
                                       value="<?= esc($valorFormulario('actualizar_definitiva', 'fecha_recepcion', (string) $definitiva->fecha_recepcion), 'attr') ?>">
                            </div>

                            <div class="form-group">
                                <label for="documento_definitiva_editar">Acta de recepción definitiva (opcional)</label>
                                <?= $selectActa(
                                    'documento_definitiva_editar',
                                    'documento_id',
                                    $actasDefinitiva,
                                    $valorFormulario('actualizar_definitiva', 'documento_id', $actaDefinitivaDefault),
                                    'Sin acta vinculada'
                                ) ?>
                            </div>

                            <div class="form-group">
                                <label for="observaciones_definitiva_editar">Observaciones</label>
                                <textarea id="observaciones_definitiva_editar"
                                          name="observaciones"
                                          class="form-control"
                                          rows="3"><?= esc($valorFormulario('actualizar_definitiva', 'observaciones', $observacionesDefinitivasDefault)) ?></textarea>
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
                    Al registrar la recepción definitiva, la obra pasará a <strong>FINALIZADA</strong>.
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
                        <label for="documento_definitiva">Acta de recepción definitiva (opcional)</label>
                        <?= $selectActa(
                            'documento_definitiva',
                            'documento_id',
                            $actasDefinitiva,
                            $valorFormulario('definitiva', 'documento_id'),
                            'Sin acta vinculada'
                        ) ?>
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

        <?php if ($puedeEditar && $permiteCorregirConjunta): ?>
            <div class="recepciones-bloque">
                <details class="recepciones-editar">
                    <summary>Corregir recepción provisoria y definitiva juntas</summary>
                    <p class="recepciones-nota-estado">
                        Ambas recepciones se validan con los valores resultantes. Si una corrección es incompatible
                        con la otra, no se guarda ningún cambio.
                    </p>

                    <form method="post"
                          action="<?= site_url('/obras/recepciones/correccion') ?>"
                          class="recepciones-form"
                          novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="obra_id" value="<?= (int) $obra->id ?>">

                        <h4 class="recepciones-subtitulo">Recepción provisoria</h4>
                        <div class="recepciones-form-grid">
                            <div class="form-group">
                                <label for="conjunta_rp_fecha">Fecha de recepción</label>
                                <input type="date" id="conjunta_rp_fecha" name="rp[fecha_recepcion]" class="form-control"
                                       max="<?= esc($fechaHoy, 'attr') ?>"
                                       value="<?= esc($fechaProvisoriaDefault, 'attr') ?>">
                            </div>
                            <div class="form-group">
                                <label for="conjunta_rp_plazo">Plazo de conservación (días)</label>
                                <input type="number" id="conjunta_rp_plazo" name="rp[plazo_conservacion_dias]" class="form-control"
                                       min="1" step="1" value="<?= esc($plazoProvisorioDefault, 'attr') ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="conjunta_rp_acta">Acta de recepción provisoria</label>
                            <?= $selectActa('conjunta_rp_acta', 'rp[documento_id]', $actasProvisoria, $actaProvisoriaDefault, 'Sin acta vinculada') ?>
                        </div>
                        <div class="form-group">
                            <label for="conjunta_rp_obs">Observaciones</label>
                            <textarea id="conjunta_rp_obs" name="rp[observaciones]" class="form-control" rows="2"><?= esc($observacionesProvisoriasDefault) ?></textarea>
                        </div>

                        <h4 class="recepciones-subtitulo">Recepción definitiva</h4>
                        <div class="form-group">
                            <label for="conjunta_rd_fecha">Fecha de recepción</label>
                            <input type="date" id="conjunta_rd_fecha" name="rd[fecha_recepcion]" class="form-control"
                                   max="<?= esc($fechaHoy, 'attr') ?>"
                                   value="<?= esc((string) $definitiva->fecha_recepcion, 'attr') ?>">
                        </div>
                        <div class="form-group">
                            <label for="conjunta_rd_acta">Acta de recepción definitiva</label>
                            <?= $selectActa('conjunta_rd_acta', 'rd[documento_id]', $actasDefinitiva, $actaDefinitivaDefault, 'Sin acta vinculada') ?>
                        </div>
                        <div class="form-group">
                            <label for="conjunta_rd_obs">Observaciones</label>
                            <textarea id="conjunta_rd_obs" name="rd[observaciones]" class="form-control" rows="2"><?= esc($observacionesDefinitivasDefault) ?></textarea>
                        </div>

                        <div class="recepciones-form-acciones">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-lg" aria-hidden="true"></i>
                                Guardar correcciones
                            </button>
                        </div>
                    </form>
                </details>
            </div>
        <?php endif; ?>

        <div class="recepciones-bloque">
            <div class="recepciones-bloque-encabezado">
                <div>
                    <h3 class="recepciones-subtitulo">Historial de recepciones</h3>
                    <p class="recepciones-bloque-nota">Altas, correcciones y cambios de plazo o estado vinculados a las recepciones.</p>
                </div>
            </div>

            <?php if ($historial === []): ?>
                <p class="recepciones-aviso">Todavía no hay cambios registrados.</p>
            <?php else: ?>
                <div class="sigoa-table-wrap">
                    <table class="sigoa-table recepciones-historial">
                        <thead>
                            <tr>
                                <th scope="col">Fecha y hora</th>
                                <th scope="col">Entidad</th>
                                <th scope="col">Acción</th>
                                <th scope="col">Campo</th>
                                <th scope="col">Valor anterior</th>
                                <th scope="col">Valor nuevo</th>
                                <th scope="col">Usuario</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historial as $fila): ?>
                                <?php
                                    $campo = (string) ($fila->campo ?? '');
                                    $usuario = trim((string) ($fila->usuario_nombre ?? '') . ' ' . (string) ($fila->usuario_apellido ?? ''));
                                ?>
                                <tr>
                                    <td><?= esc(date('d/m/Y H:i', strtotime((string) $fila->fecha_hora))) ?></td>
                                    <td><?= esc($etiquetasEntidad[$fila->entidad] ?? $fila->entidad) ?></td>
                                    <td><?= esc($fila->accion) ?></td>
                                    <td><?= esc($etiquetasCampo[$campo] ?? ($campo !== '' ? $campo : '—')) ?></td>
                                    <td><?= esc($campo !== '' ? $valorHistorial($campo, $fila->valor_anterior) : '—') ?></td>
                                    <td><?= esc($campo !== '' ? $valorHistorial($campo, $fila->valor_nuevo) : (string) ($fila->valor_nuevo ?? '—')) ?></td>
                                    <td><?= esc($usuario !== '' ? $usuario : '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<?= $this->endSection() ?>
