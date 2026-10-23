<?php use App\Libraries\CertificacionObra; ?>
<?php use App\Libraries\MontoObra; ?>
<?php use App\Libraries\PlazoObra; ?>
<?= $this->extend('layouts/auth') ?>

<?= $this->section('styles') ?>
    <link rel="stylesheet" href="<?= base_url('assets/css/pages/obras.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/pages/ficha-obra.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
$puedeEditar = $puede_editar ?? false;
$plazoInicialConfirmado = $plazo_inicial_confirmado ?? false;

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

$plazoInicialDias   = $plazo_inicial_dias ?? null;
$plazoVigenteDias   = $plazo_vigente_dias ?? null;
$totalAmpliacionDias = (int) ($total_ampliacion_dias ?? 0);
$ampliaciones          = $ampliaciones ?? [];
$ampliacionesPendientes = $ampliaciones_pendientes ?? [];
$neutralizacionAbierta  = $neutralizacion_abierta ?? false;
$neutralizaciones       = $neutralizaciones ?? [];
$totalNeutralizadoDias  = (int) ($total_neutralizado_dias ?? 0);

$plazoInicialTexto = ($plazoInicialDias !== null) ? $plazoInicialDias . ' días' : null;
$plazoVigenteTexto = ($plazoVigenteDias !== null) ? $plazoVigenteDias . ' días' : null;

$montoContratoOriginal  = $monto_contrato_original ?? null;
$montoVigente           = $monto_vigente ?? null;
$montoAjustes           = $monto_ajustes ?? [];
$montoAjustesPendientes = $monto_ajustes_pendientes ?? [];
$montoAjustesPorcentajes = $monto_ajustes_porcentajes ?? [];
$totalAjusteMonto       = $total_ajuste_monto ?? '0.000';
$tieneAjustesMonto      = $tiene_ajustes_monto ?? false;
$totalAjusteTexto       = (((float) $totalAjusteMonto > 0) ? '+' : '')
    . CertificacionObra::formatearImporte($totalAjusteMonto);
$porcentajeVigenteMonto = MontoObra::porcentajeVariacion($montoContratoOriginal, $montoVigente);

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
        <a href="<?= site_url('/dashboard') ?>" class="btn btn-volver ficha-btn-volver">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            Volver a obras
        </a>

        <div class="ficha-barra-acciones-secundarias">
            <?php /* Entrada al historial de inspecciones de la obra (Fase E.6).
                    Misma navegación que usa el inspector y el rol de consulta,
                    con el mismo criterio de autorización resuelto en backend. */ ?>
            <?php if ($puede_consultar_inspecciones ?? false): ?>
                <a href="<?= site_url('/inspecciones/ver/' . (int) $obra->id) ?>"
                   class="btn btn-accion-secundaria ficha-btn-inspecciones">
                    <i class="bi bi-clipboard-check" aria-hidden="true"></i>
                    Ver Inspecciones
                </a>
            <?php endif; ?>

            <a href="<?= site_url('/obras/certificados/' . (int) $obra->id) ?>"
               class="btn btn-accion-secundaria ficha-btn-certificados">
                <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
                Ver Certificados
            </a>

            <a href="<?= site_url('/obras/resoluciones/' . (int) $obra->id) ?>"
               class="btn btn-accion-secundaria ficha-btn-resoluciones">
                <i class="bi bi-file-earmark-check" aria-hidden="true"></i>
                Ver Resoluciones
            </a>

            <a href="<?= site_url('/obras/neutralizaciones/' . (int) $obra->id) ?>"
               class="btn btn-accion-secundaria ficha-btn-neutralizaciones">
                <i class="bi bi-pause-circle" aria-hidden="true"></i>
                Ver Neutralizaciones
            </a>

            <a href="<?= site_url('/obras/documentacion/' . (int) $obra->id) ?>"
               class="btn btn-accion-secundaria ficha-btn-documentacion">
                <i class="bi bi-folder2-open" aria-hidden="true"></i>
                Documentación
            </a>
        </div>
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

    <!-- ============================================================
         BLOQUE 1 — Encabezado de identificación y Expte. contable
         Formulario de guardado independiente: solo actualiza el
         Expte. contable.
         ============================================================ -->
    <?php if ($puedeEditar): ?>
        <form method="post"
              action="<?= site_url('/obras/ficha/actualizar-expediente') ?>"
              id="formExpedienteContable"
              novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="obra_id" value="<?= (int) $obra->id ?>">
    <?php endif; ?>

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

    <!-- ============================================================
         BLOQUE 2 — Datos operativos de la obra (fecha de inicio y
         plazo original). Formulario de guardado independiente: se
         muestra solo mientras los datos iniciales sean editables.
         ============================================================ -->
    <?php if ($puedeEditar && ! $plazoInicialConfirmado): ?>
        <form method="post"
              action="<?= site_url('/obras/ficha/actualizar') ?>"
              id="formDatosObra"
              novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="obra_id" value="<?= (int) $obra->id ?>">
    <?php endif; ?>

    <section class="ficha-datos">
        <h2 class="ficha-seccion-titulo">Datos de la obra</h2>

        <?php if ($plazoInicialConfirmado): ?>
            <p class="ficha-aviso ficha-aviso-ok">
                <i class="bi bi-lock-fill" aria-hidden="true"></i>
                Fecha de inicio y plazo confirmados: no pueden modificarse.
            </p>
        <?php elseif ($puedeEditar): ?>
            <p class="ficha-aviso">
                <i class="bi bi-info-circle" aria-hidden="true"></i>
                Complete la fecha de inicio y el plazo y confirme los datos
                iniciales: quedarán definitivos y no podrán modificarse.
            </p>
        <?php endif; ?>

        <div class="ficha-campos-grid">

            <div class="form-group ficha-campo">
                <label for="fecha_inicio">Fecha de inicio</label>
                <?php if ($puedeEditar && ! $plazoInicialConfirmado): ?>
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
                <?php if ($puedeEditar && ! $plazoInicialConfirmado): ?>
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
                <label>Plazo inicial en días corridos</label>
                <p class="ficha-valor ficha-valor-calculado">
                    <?= $plazoInicialTexto !== null ? esc($plazoInicialTexto) : '—' ?>
                </p>
            </div>

            <div class="form-group ficha-campo ficha-campo-calculado">
                <label>Plazo contractual vigente</label>
                <p class="ficha-valor ficha-valor-calculado">
                    <?= $plazoVigenteTexto !== null ? esc($plazoVigenteTexto) : '—' ?>
                    <?php if ($plazoVigenteDias !== null && $totalAmpliacionDias > 0): ?>
                        <span class="ficha-valor-nota">(<?= (int) $plazoInicialDias ?> + <?= (int) $totalAmpliacionDias ?>)</span>
                    <?php endif; ?>
                </p>
            </div>

            <div class="form-group ficha-campo ficha-campo-calculado">
                <label>Fecha de finalización</label>
                <p class="ficha-valor ficha-valor-calculado">
                    <?= esc($fechaFinTexto ?? '—') ?>
                </p>
            </div>

            <div class="form-group ficha-campo ficha-campo-calculado">
                <label>Días neutralizados</label>
                <p class="ficha-valor ficha-valor-calculado">
                    <?= $totalNeutralizadoDias > 0 ? (int) $totalNeutralizadoDias . ' días' : '—' ?>
                    <?php if ($neutralizacionAbierta): ?>
                        <span class="ficha-valor-nota">(neutralización abierta)</span>
                    <?php endif; ?>
                </p>
            </div>

            <div class="form-group ficha-campo ficha-campo-calculado">
                <label>Monto contractual vigente</label>
                <p class="ficha-valor ficha-valor-calculado">
                    <?= esc(CertificacionObra::formatearImporte($montoVigente)) ?>
                    <?php if ($tieneAjustesMonto): ?>
                        <span class="ficha-valor-nota">(original + ajustes)</span>
                    <?php endif; ?>
                </p>
            </div>

        </div>

        <div class="plazo-desglose">
            <h3 class="ficha-seccion-titulo plazo-desglose-encabezado">Cálculo del plazo contractual vigente</h3>

            <?php if (! $plazoInicialConfirmado): ?>
                <p class="ficha-aviso">
                    <i class="bi bi-info-circle" aria-hidden="true"></i>
                    Los datos iniciales de plazo aún no fueron confirmados:
                    el plazo vigente y la fecha de finalización son provisorios.
                </p>
            <?php endif; ?>

            <?php if ($neutralizacionAbierta): ?>
                <p class="ficha-aviso ficha-aviso-alerta">
                    <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                    La obra tiene una neutralización abierta: la fecha de
                    finalización no puede presentarse como vigente hasta que se
                    registre el reinicio. Los períodos cerrados ya computados
                    siguen sumando.
                </p>
            <?php endif; ?>

            <dl class="ficha-resumen plazo-desglose-resumen">
                <dt>Fecha de inicio contractual</dt>
                <dd><?= esc($fechaInicioTexto ?? '—') ?></dd>

                <dt>Plazo inicial confirmado</dt>
                <dd>
                    <?= $plazoInicialTexto !== null ? esc($plazoInicialTexto) : '—' ?>
                    <?php if (! $plazoInicialConfirmado && $plazoInicialTexto !== null): ?>
                        <span class="plazo-desglose-provisorio">(provisorio)</span>
                    <?php endif; ?>
                </dd>

                <dt>Ampliaciones confirmadas</dt>
                <dd><?= $totalAmpliacionDias > 0 ? '+' . (int) $totalAmpliacionDias . ' días' : 'Sin ampliaciones' ?></dd>

                <dt>Días neutralizados computados</dt>
                <dd><?= $totalNeutralizadoDias > 0 ? '+' . (int) $totalNeutralizadoDias . ' días' : 'Sin neutralizaciones' ?></dd>

                <dt>Plazo contractual vigente</dt>
                <dd><?= $plazoVigenteTexto !== null ? esc($plazoVigenteTexto) : '—' ?></dd>

                <dt>Fecha de finalización</dt>
                <dd><?= esc($fechaFinTexto ?? '—') ?></dd>
            </dl>

            <?php if ($ampliaciones === []): ?>
                <p class="plazo-desglose-vacio">
                    No hay ampliaciones de plazo confirmadas: el plazo vigente
                    coincide con el plazo inicial.
                </p>
            <?php else: ?>
                <p class="ficha-etiqueta plazo-desglose-tabla-titulo">Ampliaciones de plazo confirmadas</p>
                <div class="sigoa-table-wrap">
                    <table class="sigoa-table plazo-ampliaciones-tabla">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>N°</th>
                                <th>Año</th>
                                <th>Fecha</th>
                                <th class="plazo-col-dias">Días</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ampliaciones as $ampliacion): ?>
                                <tr>
                                    <td><?= esc($ampliacion->tipo_nombre ?? '—') ?></td>
                                    <td><?= $ampliacion->numero === null ? '—' : (int) $ampliacion->numero ?></td>
                                    <td><?= $ampliacion->anio === null ? '—' : (int) $ampliacion->anio ?></td>
                                    <td><?= esc(PlazoObra::formatearFecha($ampliacion->fecha_resolucion) ?? '—') ?></td>
                                    <td class="plazo-col-dias">+<?= (int) $ampliacion->dias ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="plazo-ampliaciones-total">
                                <td colspan="4">Total de días de ampliación aprobados</td>
                                <td class="plazo-col-dias">+<?= (int) $totalAmpliacionDias ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($ampliacionesPendientes !== []): ?>
                <p class="ficha-etiqueta plazo-desglose-tabla-titulo">Ampliaciones pendientes de confirmación</p>
                <p class="plazo-desglose-nota">Trámites aún no confirmados: no se suman al plazo vigente.</p>
                <div class="sigoa-table-wrap">
                    <table class="sigoa-table plazo-ampliaciones-tabla">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>N°</th>
                                <th>Año</th>
                                <th>Fecha</th>
                                <th class="plazo-col-dias">Días</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ampliacionesPendientes as $pendiente): ?>
                                <tr>
                                    <td><?= esc($pendiente->tipo_nombre ?? '—') ?></td>
                                    <td><?= $pendiente->numero === null ? '—' : (int) $pendiente->numero ?></td>
                                    <td><?= $pendiente->anio === null ? '—' : (int) $pendiente->anio ?></td>
                                    <td><?= esc(PlazoObra::formatearFecha($pendiente->fecha_resolucion) ?? '—') ?></td>
                                    <td class="plazo-col-dias">
                                        <?= ($pendiente->dias === null || (int) $pendiente->dias < 1) ? '—' : (int) $pendiente->dias ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <p class="ficha-etiqueta plazo-desglose-tabla-titulo">Neutralizaciones</p>
            <?php if ($neutralizaciones === []): ?>
                <p class="plazo-desglose-vacio">No hay neutralizaciones registradas.</p>
            <?php else: ?>
                <div class="sigoa-table-wrap">
                    <table class="sigoa-table plazo-neutralizaciones-tabla">
                        <thead>
                            <tr>
                                <th>N°</th>
                                <th>Fecha de neutralización</th>
                                <th>Fecha de reinicio</th>
                                <th class="plazo-col-dias">Días</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($neutralizaciones as $periodo): ?>
                                <?php
                                $periodoAbierto = $periodo->fecha_reinicio === null;
                                $periodoDias    = PlazoObra::diasNeutralizados($periodo->fecha_inicio, $periodo->fecha_reinicio);
                                ?>
                                <tr>
                                    <td><?= (int) $periodo->numero ?></td>
                                    <td><?= esc(PlazoObra::formatearFecha($periodo->fecha_inicio) ?? '—') ?></td>
                                    <td><?= esc(PlazoObra::formatearFecha($periodo->fecha_reinicio) ?? '—') ?></td>
                                    <td class="plazo-col-dias"><?= $periodoDias === null ? '—' : '+' . (int) $periodoDias ?></td>
                                    <td>
                                        <span class="neutralizacion-estado <?= $periodoAbierto ? 'neutralizacion-estado-abierta' : 'neutralizacion-estado-cerrada' ?>">
                                            <?= $periodoAbierto ? 'Abierta' : 'Cerrada' ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="plazo-ampliaciones-total">
                                <td colspan="3">Total de días neutralizados</td>
                                <td class="plazo-col-dias">+<?= (int) $totalNeutralizadoDias ?></td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="plazo-desglose">
            <h3 class="ficha-seccion-titulo plazo-desglose-encabezado">Cálculo del monto contractual vigente</h3>

            <dl class="ficha-resumen plazo-desglose-resumen">
                <dt>Monto de contrato original</dt>
                <dd><?= esc(CertificacionObra::formatearImporte($montoContratoOriginal)) ?></dd>

                <dt>Ajustes económicos confirmados</dt>
                <dd><?= $tieneAjustesMonto ? esc($totalAjusteTexto) : 'Sin ajustes' ?></dd>

                <dt>Monto contractual vigente</dt>
                <dd><?= esc(CertificacionObra::formatearImporte($montoVigente)) ?></dd>
            </dl>

            <?php if ($montoAjustes === []): ?>
                <p class="plazo-desglose-vacio">
                    No hay ajustes económicos confirmados: el monto vigente
                    coincide con el monto de contrato original.
                </p>
            <?php else: ?>
                <p class="ficha-etiqueta plazo-desglose-tabla-titulo">Ajustes económicos confirmados</p>
                <div class="sigoa-table-wrap">
                    <table class="sigoa-table monto-ajustes-tabla">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>N°</th>
                                <th>Año</th>
                                <th>Fecha</th>
                                <th class="monto-col-porcentaje">Porcentaje</th>
                                <th class="monto-col-importe">Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($montoAjustes as $indice => $ajuste): ?>
                                <tr>
                                    <td><?= esc($ajuste->tipo_nombre ?? '—') ?></td>
                                    <td><?= $ajuste->numero === null ? '—' : (int) $ajuste->numero ?></td>
                                    <td><?= $ajuste->anio === null ? '—' : (int) $ajuste->anio ?></td>
                                    <td><?= esc(PlazoObra::formatearFecha($ajuste->fecha_resolucion) ?? '—') ?></td>
                                    <td class="monto-col-porcentaje"><?= esc($montoAjustesPorcentajes[$indice] ?? '—') ?></td>
                                    <td class="monto-col-importe"><?= esc(CertificacionObra::formatearImporte($ajuste->monto)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="monto-ajustes-total">
                                <td colspan="4">Total de ajustes económicos aprobados</td>
                                <td class="monto-col-porcentaje"><?= esc($porcentajeVigenteMonto ?? '—') ?></td>
                                <td class="monto-col-importe"><?= esc($totalAjusteTexto) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($montoAjustesPendientes !== []): ?>
                <p class="ficha-etiqueta plazo-desglose-tabla-titulo">Ajustes económicos pendientes de confirmación</p>
                <p class="plazo-desglose-nota">Trámites aún no confirmados: no se suman al monto vigente.</p>
                <div class="sigoa-table-wrap">
                    <table class="sigoa-table monto-ajustes-tabla">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>N°</th>
                                <th>Año</th>
                                <th>Fecha</th>
                                <th class="monto-col-importe">Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($montoAjustesPendientes as $pendiente): ?>
                                <tr>
                                    <td><?= esc($pendiente->tipo_nombre ?? '—') ?></td>
                                    <td><?= $pendiente->numero === null ? '—' : (int) $pendiente->numero ?></td>
                                    <td><?= $pendiente->anio === null ? '—' : (int) $pendiente->anio ?></td>
                                    <td><?= esc(PlazoObra::formatearFecha($pendiente->fecha_resolucion) ?? '—') ?></td>
                                    <td class="monto-col-importe"><?= esc(CertificacionObra::formatearImporte($pendiente->monto)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($puedeEditar && ! $plazoInicialConfirmado): ?>
            <div class="ficha-form-acciones">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-lg" aria-hidden="true"></i>
                    Guardar cambios
                </button>

                <button type="button"
                        class="btn btn-secondary"
                        id="btnConfirmarPlazoInicial"
                        aria-haspopup="dialog"
                        aria-controls="modalConfirmarPlazo">
                    <i class="bi bi-check-circle" aria-hidden="true"></i>
                    Confirmar datos iniciales
                </button>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($puedeEditar && ! $plazoInicialConfirmado): ?>
        </form>
    <?php endif; ?>

    <div class="ficha-personal">

    <!-- ============================================================
         Inspector vigente
         ============================================================ -->
    <section class="ficha-inspector">
        <h2 class="ficha-seccion-titulo">Inspector</h2>

        <div class="ficha-inspector-vigente">
            <p class="ficha-etiqueta">Inspector vigente</p>

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
                    class="btn btn-accion-secundaria ficha-btn-cambio-inspector"
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
            <p class="ficha-etiqueta">Representante técnico vigente</p>

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
                    class="btn btn-accion-secundaria ficha-btn-cambio-inspector"
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
                <button type="button" class="btn btn-cancelar" id="btnCancelarInspector">
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
                <button type="button" class="btn btn-cancelar" id="btnCancelarRepresentante">
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

<?php if ($puedeEditar && ! $plazoInicialConfirmado): ?>
<!-- ================================================================
     MODAL — Confirmación de los datos iniciales de plazo
     ================================================================ -->
<div class="modal-overlay"
     id="modalConfirmarPlazo"
     hidden
     aria-hidden="true">
    <div class="modal modal-ficha-resumen" role="dialog" aria-modal="true" aria-labelledby="modalConfirmarPlazoTitulo">
        <h2 class="modal-title" id="modalConfirmarPlazoTitulo">
            <i class="bi bi-check-circle" aria-hidden="true"></i>
            Confirmar datos iniciales
        </h2>
        <p class="modal-message">
            La fecha de inicio y el plazo de la obra quedarán confirmados y
            ya no podrán modificarse. Revise los datos antes de confirmar.
        </p>

        <dl class="ficha-resumen">
            <dt>Fecha de inicio</dt>
            <dd id="resumenFechaInicio">—</dd>
            <dt>Plazo de obra</dt>
            <dd id="resumenPlazoObra">—</dd>
        </dl>

        <div class="modal-actions">
            <button type="button" class="btn btn-cancelar" id="btnCancelarConfirmarPlazo">
                Cancelar
            </button>
            <button type="submit"
                    class="btn btn-success"
                    form="formDatosObra"
                    formaction="<?= site_url('/obras/ficha/confirmar-plazo') ?>">
                <i class="bi bi-check-lg" aria-hidden="true"></i>
                Confirmar
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script src="<?= base_url('assets/js/pages/ficha-obra.js') ?>"></script>
<?= $this->endSection() ?>
