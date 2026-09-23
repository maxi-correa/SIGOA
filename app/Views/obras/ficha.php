<?php use App\Libraries\PlazoObra; ?>
<?= $this->extend('layouts/auth') ?>

<?= $this->section('styles') ?>
    <link rel="stylesheet" href="<?= base_url('assets/css/pages/obras.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/pages/ficha-obra.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
$puedeEditar = $puede_editar ?? false;

$erroresFicha     = session()->getFlashdata('errores_ficha');
$erroresInspector = session()->getFlashdata('errores_inspector');
$modalInspectorAbierto = is_array($erroresInspector) && $erroresInspector !== [];

$erroresRepresentante     = session()->getFlashdata('errores_representante');
$modalRepresentanteAbierto = is_array($erroresRepresentante) && $erroresRepresentante !== [];

$estadoNombre = strtoupper((string) ($obra->estado_nombre ?? ''));

$estadoClase = match ($estadoNombre) {
    'EN EJECUCIÓN'             => 'estado-ejecucion',
    'NEUTRALIZADA'             => 'estado-neutralizada',
    'EN PLAZO DE CONSERVACIÓN' => 'estado-conservacion',
    'FINALIZADA'               => 'estado-finalizada',
    default                    => 'estado-previo',
};

$valorExpteContable = old('expediente_contable', $obra->expediente_contable ?? '');

$fechaInicioTexto   = PlazoObra::formatearFecha($obra->fecha_inicio);
$valorFechaInicio   = old('fecha_inicio', $fechaInicioTexto ?? '');
$valorPlazoValor    = old('plazo_valor', $obra->plazo_original_valor ?? '');
$valorPlazoUnidad   = old('plazo_unidad', $obra->plazo_original_unidad ?? PlazoObra::UNIDAD_DIAS);

$fechaFinTexto = PlazoObra::formatearFecha($fecha_fin);
$plazoDiasTexto = ($plazo_dias !== null) ? $plazo_dias . ' días' : null;

$plazoEtiqueta = null;

if ($obra->plazo_original_valor !== null && $obra->plazo_original_unidad !== null) {
    if ($obra->plazo_original_unidad === PlazoObra::UNIDAD_MES) {
        $plazoEtiqueta = ((int) $obra->plazo_original_valor === 1) ? 'mes' : 'meses';
    } else {
        $plazoEtiqueta = 'días corridos';
    }
}

$valorInspectorCambio = old('inspector_id', '');
$valorFechaCambio     = old('fecha_cambio', '');

$valorRepresentanteCambio = old('representante_tecnico_id', '');
$valorFechaCambioRep      = old('fecha_cambio', '');
?>

<div class="ficha-obra-page">

    <div class="ficha-barra-acciones">
        <a href="<?= site_url('/dashboard') ?>" class="btn btn-secondary ficha-btn-volver">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            Volver a obras
        </a>

        <button type="button"
                class="btn btn-secondary ficha-btn-certificados"
                disabled
                title="Disponible próximamente">
            <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
            Ver Certificados
        </button>
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

    <?php if (is_array($erroresFicha) && $erroresFicha !== []): ?>
        <div class="alert alert-danger" role="alert">
            <span class="alert-icon"><i class="bi bi-exclamation-circle"></i></span>
            <span>
                <?php foreach ($erroresFicha as $errorFicha): ?>
                    <div><?= esc($errorFicha) ?></div>
                <?php endforeach; ?>
            </span>
        </div>
    <?php endif; ?>

    <?php if ($puedeEditar): ?>
        <form method="post" action="<?= site_url('/obras/ficha/actualizar') ?>" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="obra_id" value="<?= (int) $obra->id ?>">
    <?php endif; ?>

    <!-- ============================================================
         Encabezado de identificación de la obra
         ============================================================ -->
    <section class="ficha-identidad">
        <div class="ficha-identidad-principal">
            <div class="ficha-titular">
                <h1 class="ficha-nombre"><?= esc($obra->nombre) ?></h1>
                <span class="ficha-codigo"><?= esc($obra->codigo) ?></span>
            </div>
            <span class="estado-badge <?= $estadoClase ?>"><?= esc($estadoNombre) ?></span>
        </div>

        <dl class="ficha-grid">
            <div class="ficha-dato">
                <dt>N° de Expte. municipal</dt>
                <dd><?= esc($obra->expediente_municipal) ?></dd>
            </div>

            <div class="ficha-dato">
                <dt>Barrio</dt>
                <dd>
                    <?php if ($obra->barrio_nombre): ?>
                        <?= esc($obra->barrio_nombre) ?>
                    <?php else: ?>
                        <span class="obras-sd">S/D</span>
                    <?php endif; ?>
                </dd>
            </div>

            <div class="ficha-dato">
                <dt>Empresa</dt>
                <dd>
                    <?php if ($obra->empresa_razon_social): ?>
                        <?= esc($obra->empresa_razon_social) ?>
                    <?php else: ?>
                        <span class="obras-sd">S/D</span>
                    <?php endif; ?>
                </dd>
            </div>

            <div class="ficha-dato">
                <dt>Tipo de Licitación</dt>
                <dd>
                    <?php if ($obra->tipo_licitacion_nombre): ?>
                        <?= esc($obra->tipo_licitacion_nombre) ?>
                    <?php else: ?>
                        <span class="obras-sd">S/D</span>
                    <?php endif; ?>
                </dd>
            </div>

            <div class="ficha-dato">
                <dt>N° de Licitación</dt>
                <dd>
                    <?php if ($obra->numero_licitacion): ?>
                        <?= esc($obra->numero_licitacion) ?>
                    <?php else: ?>
                        <span class="obras-sd">S/D</span>
                    <?php endif; ?>
                </dd>
            </div>

            <div class="ficha-dato">
                <dt><label for="expediente_contable">Expte. contable</label></dt>
                <dd>
                    <?php if ($puedeEditar): ?>
                        <input type="text"
                               id="expediente_contable"
                               name="expediente_contable"
                               class="form-control"
                               maxlength="50"
                               autocomplete="off"
                               placeholder="Ej.: 456-2026"
                               value="<?= esc($valorExpteContable) ?>">
                    <?php elseif ($obra->expediente_contable): ?>
                        <?= esc($obra->expediente_contable) ?>
                    <?php else: ?>
                        <span class="obras-sd">S/D</span>
                    <?php endif; ?>
                </dd>
            </div>
        </dl>
    </section>

    <!-- ============================================================
         Datos operativos de la obra
         ============================================================ -->
    <section class="ficha-datos">
        <h2 class="ficha-seccion-titulo">Datos de la obra</h2>

        <div class="ficha-campos-grid">

            <div class="form-group ficha-campo">
                <label for="fecha_inicio">Fecha de inicio</label>
                <?php if ($puedeEditar): ?>
                    <input type="text"
                           id="fecha_inicio"
                           name="fecha_inicio"
                           class="form-control"
                           inputmode="numeric"
                           maxlength="10"
                           autocomplete="off"
                           placeholder="dd/mm/aaaa"
                           value="<?= esc($valorFechaInicio) ?>">
                <?php else: ?>
                    <p class="ficha-valor"><?= esc($fechaInicioTexto ?? '—') ?></p>
                <?php endif; ?>
            </div>

            <div class="form-group ficha-campo">
                <label for="plazo_valor">Plazo de obra</label>
                <?php if ($puedeEditar): ?>
                    <div class="ficha-plazo">
                        <input type="text"
                               id="plazo_valor"
                               name="plazo_valor"
                               class="form-control"
                               inputmode="numeric"
                               maxlength="6"
                               autocomplete="off"
                               placeholder="Ej.: 120"
                               value="<?= esc($valorPlazoValor) ?>">
                        <label class="sr-only" for="plazo_unidad">Unidad del plazo</label>
                        <select id="plazo_unidad" name="plazo_unidad" class="form-control">
                            <?php foreach ($unidades as $codigoUnidad => $etiquetaUnidad): ?>
                                <option value="<?= esc($codigoUnidad) ?>"
                                    <?= $valorPlazoUnidad === $codigoUnidad ? 'selected' : '' ?>>
                                    <?= esc($etiquetaUnidad) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php elseif ($plazoEtiqueta !== null): ?>
                    <p class="ficha-valor">
                        <?= esc($obra->plazo_original_valor) ?> <?= esc($plazoEtiqueta) ?>
                    </p>
                <?php else: ?>
                    <p class="ficha-valor">—</p>
                <?php endif; ?>
            </div>

            <div class="form-group ficha-campo ficha-campo-calculado">
                <label>Plazo en días corridos</label>
                <p class="ficha-valor ficha-valor-calculado">
                    <?= $plazoDiasTexto !== null ? esc($plazoDiasTexto) : '—' ?>
                </p>
            </div>

            <div class="form-group ficha-campo ficha-campo-calculado">
                <label>Fecha de finalización</label>
                <p class="ficha-valor ficha-valor-calculado">
                    <?= esc($fechaFinTexto ?? '—') ?>
                </p>
            </div>

        </div>

        <?php if ($puedeEditar): ?>
            <div class="ficha-form-acciones">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-lg" aria-hidden="true"></i>
                    Guardar cambios
                </button>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($puedeEditar): ?>
        </form>
    <?php endif; ?>

    <div class="ficha-personal">

    <!-- ============================================================
         Inspector vigente
         ============================================================ -->
    <section class="ficha-inspector">
        <h2 class="ficha-seccion-titulo">Inspector</h2>

        <div class="ficha-inspector-vigente">
            <p class="ficha-inspector-etiqueta">Inspector vigente</p>

            <?php if ($inspector_vigente): ?>
                <p class="ficha-valor ficha-valor-calculado">
                    <?= esc($inspector_vigente->inspector_apellido . ', ' . $inspector_vigente->inspector_nombre) ?>
                </p>
                <p class="ficha-inspector-desde">
                    Desde el <?= esc(PlazoObra::formatearFecha($inspector_vigente->fecha_inicio) ?? '—') ?>
                </p>
            <?php else: ?>
                <p class="ficha-valor ficha-inspector-sin">Sin inspector asignado</p>
            <?php endif; ?>
        </div>

        <?php if ($puedeEditar): ?>
            <button type="button"
                    class="btn btn-secondary ficha-btn-cambio-inspector"
                    id="btnCambioInspector"
                    aria-haspopup="dialog"
                    aria-controls="modalInspector">
                <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
                ¿Existe un cambio de inspector?
            </button>
        <?php endif; ?>
    </section>

    <!-- ============================================================
         Representante técnico vigente
         ============================================================ -->
    <section class="ficha-representante">
        <h2 class="ficha-seccion-titulo">Representante técnico</h2>

        <div class="ficha-inspector-vigente">
            <p class="ficha-inspector-etiqueta">Representante técnico vigente</p>

            <?php if ($representante_vigente): ?>
                <p class="ficha-valor ficha-valor-calculado">
                    <?= esc($representante_vigente->representante_apellido . ', ' . $representante_vigente->representante_nombre) ?>
                </p>
                <p class="ficha-inspector-desde">
                    <?= esc($representante_vigente->titulo_profesional_nombre) ?>
                    <?php if ($representante_vigente->representante_matricula): ?>
                        · Mat. <?= esc($representante_vigente->representante_matricula) ?>
                    <?php endif; ?>
                </p>
                <p class="ficha-inspector-desde">
                    Desde el <?= esc(PlazoObra::formatearFecha($representante_vigente->fecha_inicio) ?? '—') ?>
                </p>
            <?php else: ?>
                <p class="ficha-valor ficha-inspector-sin">Sin representante técnico asignado</p>
            <?php endif; ?>
        </div>

        <?php if ($puedeEditar): ?>
            <button type="button"
                    class="btn btn-secondary ficha-btn-cambio-inspector"
                    id="btnCambioRepresentante"
                    aria-haspopup="dialog"
                    aria-controls="modalRepresentante">
                <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
                ¿Existe un cambio de representante técnico?
            </button>
        <?php endif; ?>
    </section>

    </div>

</div>

<?php if ($puedeEditar): ?>
<!-- ================================================================
     MODAL — Cambio de inspector vigente
     ================================================================ -->
<div class="modal-overlay"
     id="modalInspector"
     <?= $modalInspectorAbierto ? '' : 'hidden' ?>
     aria-hidden="<?= $modalInspectorAbierto ? 'false' : 'true' ?>">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalInspectorTitulo">
        <h2 class="modal-title" id="modalInspectorTitulo">
            <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
            Cambio de inspector
        </h2>
        <p class="modal-message">
            Registre desde qué fecha rige el cambio y seleccione el nuevo
            inspector. La asignación anterior se conserva en el historial.
        </p>

        <?php if ($modalInspectorAbierto): ?>
            <div class="modal-error" role="alert" aria-live="polite">
                <?php foreach ($erroresInspector as $errorInspector): ?>
                    <div><?= esc($errorInspector) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= site_url('/obras/inspector/actualizar') ?>" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="obra_id" value="<?= (int) $obra->id ?>">

            <div class="form-group">
                <label for="fecha_cambio">
                    Fecha desde la que rige el cambio
                    <span class="obras-requerido" aria-hidden="true">*</span>
                </label>
                <input type="text"
                       id="fecha_cambio"
                       name="fecha_cambio"
                       class="form-control"
                       inputmode="numeric"
                       maxlength="10"
                       autocomplete="off"
                       placeholder="dd/mm/aaaa"
                       value="<?= esc($valorFechaCambio) ?>">
            </div>

            <div class="form-group">
                <label for="inspector_id">
                    Nuevo inspector
                    <span class="obras-requerido" aria-hidden="true">*</span>
                </label>
                <select id="inspector_id" name="inspector_id" class="form-control">
                    <option value="">Seleccione un inspector</option>
                    <?php foreach ($inspectores as $inspector): ?>
                        <?php
                        $esVigente = $inspector_vigente !== null
                            && (int) $inspector_vigente->usuario_id === (int) $inspector->id;
                        ?>
                        <?php if ($esVigente): ?>
                            <option value="<?= (int) $inspector->id ?>" disabled>
                                <?= esc($inspector->apellido . ', ' . $inspector->nombre) ?> (vigente)
                            </option>
                        <?php else: ?>
                            <option value="<?= (int) $inspector->id ?>"
                                <?= (string) $valorInspectorCambio === (string) $inspector->id ? 'selected' : '' ?>>
                                <?= esc($inspector->apellido . ', ' . $inspector->nombre) ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" id="btnCancelarInspector">
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
<?php endif; ?>

<?php if ($puedeEditar): ?>
<!-- ================================================================
     MODAL — Cambio de representante técnico vigente
     ================================================================ -->
<div class="modal-overlay"
     id="modalRepresentante"
     <?= $modalRepresentanteAbierto ? '' : 'hidden' ?>
     aria-hidden="<?= $modalRepresentanteAbierto ? 'false' : 'true' ?>">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalRepresentanteTitulo">
        <h2 class="modal-title" id="modalRepresentanteTitulo">
            <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
            Cambio de representante técnico
        </h2>
        <p class="modal-message">
            Registre desde qué fecha rige el cambio y seleccione el nuevo
            representante técnico. La asignación anterior se conserva en el
            historial.
        </p>

        <?php if ($modalRepresentanteAbierto): ?>
            <div class="modal-error" role="alert" aria-live="polite">
                <?php foreach ($erroresRepresentante as $errorRepresentante): ?>
                    <div><?= esc($errorRepresentante) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= site_url('/obras/representante/actualizar') ?>" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="obra_id" value="<?= (int) $obra->id ?>">

            <div class="form-group">
                <label for="fecha_cambio_representante">
                    Fecha desde la que rige el cambio
                    <span class="obras-requerido" aria-hidden="true">*</span>
                </label>
                <input type="text"
                       id="fecha_cambio_representante"
                       name="fecha_cambio"
                       class="form-control"
                       inputmode="numeric"
                       maxlength="10"
                       autocomplete="off"
                       placeholder="dd/mm/aaaa"
                       value="<?= esc($valorFechaCambioRep) ?>">
            </div>

            <div class="form-group">
                <label for="representante_tecnico_id">
                    Nuevo representante técnico
                    <span class="obras-requerido" aria-hidden="true">*</span>
                </label>
                <select id="representante_tecnico_id" name="representante_tecnico_id" class="form-control">
                    <option value="">Seleccione un representante técnico</option>
                    <?php foreach ($representantes as $representante): ?>
                        <?php
                        $esVigente = $representante_vigente !== null
                            && (int) $representante_vigente->representante_tecnico_id === (int) $representante->id;
                        ?>
                        <?php if ($esVigente): ?>
                            <option value="<?= (int) $representante->id ?>" disabled>
                                <?= esc($representante->apellido . ', ' . $representante->nombre) ?> (vigente)
                            </option>
                        <?php else: ?>
                            <option value="<?= (int) $representante->id ?>"
                                <?= (string) $valorRepresentanteCambio === (string) $representante->id ? 'selected' : '' ?>>
                                <?= esc($representante->apellido . ', ' . $representante->nombre) ?><?php if ($representante->matricula): ?> — Mat. <?= esc($representante->matricula) ?><?php endif; ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" id="btnCancelarRepresentante">
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
<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script src="<?= base_url('assets/js/pages/ficha-obra.js') ?>"></script>
<?= $this->endSection() ?>
