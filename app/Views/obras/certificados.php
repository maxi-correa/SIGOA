<?php

use App\Libraries\CertificacionObra;
use App\Libraries\PlazoObra;

$puedeEditar   = $puede_editar ?? false;
$confirmada    = $configuracion_confirmada ?? false;
$bloqueada     = $configuracion_bloqueada ?? false;
$editable      = $puedeEditar && ! $bloqueada;
$anioActual    = (int) date('Y');

$erroresConfig      = session()->getFlashdata('errores_configuracion');
$erroresCertificado = session()->getFlashdata('errores_certificado');
$modalCertificadoAbierto = is_array($erroresCertificado) && $erroresCertificado !== [];

$valorImporte = static function (string $campo, $almacenado): string {
    $old = old($campo);

    if ($old !== null && $old !== '') {
        return (string) $old;
    }

    if ($almacenado === null || $almacenado === '') {
        return '';
    }

    return number_format((float) $almacenado, 3, ',', '');
};

$valorRadio = static function (string $campo, $almacenado): string {
    $old = old($campo);

    if ($old !== null && $old !== '') {
        return (string) $old;
    }

    if ($almacenado === true || $almacenado === 1 || $almacenado === '1') {
        return '1';
    }

    if ($almacenado === false || $almacenado === 0 || $almacenado === '0') {
        return '0';
    }

    return '';
};

$presupuestoValor = $valorImporte('presupuesto_oficial', $obra->presupuesto_oficial ?? null);
$contratoValor    = $valorImporte('monto_contrato', $obra->monto_contrato ?? null);
$anticipoSiNo     = $valorRadio('tiene_anticipo_financiero', $obra->tiene_anticipo_financiero ?? 0);
$porcAnticipo     = $valorImporte('porcentaje_anticipo_financiero', $obra->porcentaje_anticipo_financiero ?? null);
$fondoSiNo        = $valorRadio('tiene_fondo_reparo', $obra->tiene_fondo_reparo ?? 0);
$porcFondo        = $valorImporte('porcentaje_fondo_reparo', $obra->porcentaje_fondo_reparo ?? null);
$polizaSiNo       = $valorRadio('fondo_reparo_con_poliza', $obra->fondo_reparo_con_poliza ?? null);

$mostrarAnticipoPct = $anticipoSiNo === '1';
$mostrarFondoCampos = $fondoSiNo === '1';

$valorMes  = old('mes', '');
$valorAnio = old('anio', (string) $anioActual);
$valorBruto = old('monto_bruto', '');

$fechaInicioTexto = PlazoObra::formatearFecha($obra->fecha_inicio ?? null);
$plazoEtiqueta    = null;

if (($obra->plazo_original_valor ?? null) !== null && ($obra->plazo_original_unidad ?? null) !== null) {
    if ($obra->plazo_original_unidad === PlazoObra::UNIDAD_MES) {
        $plazoEtiqueta = ((int) $obra->plazo_original_valor === 1) ? 'mes' : 'meses';
    } else {
        $plazoEtiqueta = 'días corridos';
    }
}

$celdaImporte = static function (mixed $valor, bool $deduccion = false): string {
    $simbolo = $deduccion ? '-$' : '$';
    $numero  = CertificacionObra::formatearNumeroImporte($valor ?? 0);

    return '<span class="cert-importe-contable">'
        . '<span class="cert-importe-simbolo">' . esc($simbolo) . '</span>'
        . '<span class="cert-importe-numero">' . esc($numero) . '</span>'
        . '</span>';
};
?>
<?= $this->extend('layouts/auth') ?>

<?= $this->section('styles') ?>
    <link rel="stylesheet" href="<?= base_url('assets/css/pages/ficha-obra.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/pages/obra-certificados.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="ficha-obra-page cert-page"
     data-config-bloqueada="<?= $bloqueada ? '1' : '0' ?>"
     data-puede-editar="<?= $editable ? '1' : '0' ?>">

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

    <?php if (is_array($erroresConfig) && $erroresConfig !== []): ?>
        <div class="alert alert-danger" role="alert">
            <span class="alert-icon"><i class="bi bi-exclamation-circle"></i></span>
            <span>
                <?php foreach ($erroresConfig as $errorConfig): ?>
                    <div><?= esc($errorConfig) ?></div>
                <?php endforeach; ?>
            </span>
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
                <dt>Fecha de inicio</dt>
                <dd><?= esc($fechaInicioTexto ?? '—') ?></dd>
            </div>
            <div class="ficha-dato">
                <dt>Plazo de obra</dt>
                <dd>
                    <?php if ($plazoEtiqueta !== null): ?>
                        <?= esc($obra->plazo_original_valor) ?> <?= esc($plazoEtiqueta) ?>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </dd>
            </div>
            <div class="ficha-dato">
                <dt>N° de expediente</dt>
                <dd><?= esc($obra->expediente_municipal) ?></dd>
            </div>
        </dl>
    </section>

    <?php if ($editable): ?>
        <form method="post"
              action="<?= site_url('/obras/certificados/configurar') ?>"
              id="formConfigEconomica"
              novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="obra_id" value="<?= (int) $obra->id ?>">
    <?php endif; ?>

    <section class="ficha-datos">
        <h2 class="ficha-seccion-titulo">Datos de la obra</h2>

        <div class="ficha-campos-grid">
            <div class="form-group ficha-campo">
                <label for="presupuesto_oficial">Presupuesto oficial</label>
                <?php if ($editable): ?>
                    <div class="cert-input-grupo">
                        <span class="cert-input-affijo" aria-hidden="true">$</span>
                        <input type="text"
                               id="presupuesto_oficial"
                               name="presupuesto_oficial"
                               class="form-control"
                               inputmode="decimal"
                               autocomplete="off"
                               placeholder="$ 100.000.000"
                               value="<?= esc($presupuestoValor) ?>">
                    </div>
                <?php else: ?>
                    <p class="ficha-valor ficha-valor-calculado"><?= esc(CertificacionObra::formatearImporte($obra->presupuesto_oficial ?? null)) ?></p>
                <?php endif; ?>
            </div>

            <div class="form-group ficha-campo">
                <label for="monto_contrato">Monto de contrato original</label>
                <?php if ($editable): ?>
                    <div class="cert-input-grupo">
                        <span class="cert-input-affijo" aria-hidden="true">$</span>
                        <input type="text"
                               id="monto_contrato"
                               name="monto_contrato"
                               class="form-control"
                               inputmode="decimal"
                               autocomplete="off"
                               placeholder="$ 95.000.000"
                               value="<?= esc($contratoValor) ?>">
                    </div>
                <?php else: ?>
                    <p class="ficha-valor ficha-valor-calculado"><?= esc(CertificacionObra::formatearImporte($obra->monto_contrato ?? null)) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="ficha-datos">
        <h2 class="ficha-seccion-titulo">Configuración económica para certificar</h2>

        <?php if ($bloqueada): ?>
            <p class="cert-aviso">La configuración económica quedó bloqueada al emitirse el primer certificado.</p>
        <?php elseif ($confirmada): ?>
            <p class="cert-aviso cert-aviso-ok">Configuración confirmada. Puede comenzar a certificar. Los datos podrán modificarse hasta emitir el primer certificado.</p>
        <?php else: ?>
            <p class="cert-aviso">Complete y confirme la configuración económica para habilitar la carga de certificados.</p>
        <?php endif; ?>

        <fieldset class="cert-grupo" <?= $editable ? '' : 'disabled' ?>>
            <legend>¿Habrá anticipo financiero?</legend>
            <div class="cert-opciones">
                <label class="form-check">
                    <input type="radio"
                           name="tiene_anticipo_financiero"
                           value="1"
                           <?= $anticipoSiNo === '1' ? 'checked' : '' ?>
                           <?= $editable ? '' : 'disabled' ?>>
                    <span>Sí</span>
                </label>
                <label class="form-check">
                    <input type="radio"
                           name="tiene_anticipo_financiero"
                           value="0"
                           <?= $anticipoSiNo !== '1' ? 'checked' : '' ?>
                           <?= $editable ? '' : 'disabled' ?>>
                    <span>No</span>
                </label>
            </div>
        </fieldset>

        <div class="ficha-campos-grid cert-anticipo-campos" <?= $mostrarAnticipoPct ? '' : 'hidden' ?>>
            <div class="form-group ficha-campo">
                <label for="porcentaje_anticipo_financiero">Porcentaje de anticipo financiero</label>
                <?php if ($editable): ?>
                    <div class="cert-input-grupo">
                        <input type="text"
                               id="porcentaje_anticipo_financiero"
                               name="porcentaje_anticipo_financiero"
                               class="form-control"
                               inputmode="decimal"
                               autocomplete="off"
                               placeholder="20,000 %"
                               value="<?= esc($porcAnticipo) ?>">
                        <span class="cert-input-affijo" aria-hidden="true">%</span>
                    </div>
                <?php else: ?>
                    <p class="ficha-valor"><?= esc(CertificacionObra::formatearPorcentaje($obra->porcentaje_anticipo_financiero ?? null)) ?></p>
                <?php endif; ?>
            </div>

            <div class="form-group ficha-campo ficha-campo-calculado">
                <label>Monto total de anticipo</label>
                <p class="ficha-valor ficha-valor-calculado" id="anticipoTotalTexto">
                    <?= $anticipo_total !== null
                        ? esc(CertificacionObra::formatearImporte($anticipo_total))
                        : '—' ?>
                </p>
            </div>
        </div>

        <fieldset class="cert-grupo" <?= $editable ? '' : 'disabled' ?>>
            <legend>¿Existe fondo de reparo?</legend>
            <div class="cert-opciones">
                <label class="form-check">
                    <input type="radio"
                           name="tiene_fondo_reparo"
                           value="1"
                           <?= $fondoSiNo === '1' ? 'checked' : '' ?>
                           <?= $editable ? '' : 'disabled' ?>>
                    <span>Sí</span>
                </label>
                <label class="form-check">
                    <input type="radio"
                           name="tiene_fondo_reparo"
                           value="0"
                           <?= $fondoSiNo !== '1' ? 'checked' : '' ?>
                           <?= $editable ? '' : 'disabled' ?>>
                    <span>No</span>
                </label>
            </div>
        </fieldset>

        <div class="ficha-campos-grid cert-fondo-campos" <?= $mostrarFondoCampos ? '' : 'hidden' ?>>
            <div class="form-group ficha-campo">
                <label for="porcentaje_fondo_reparo">Porcentaje de fondo de reparo</label>
                <?php if ($editable): ?>
                    <div class="cert-input-grupo">
                        <input type="text"
                               id="porcentaje_fondo_reparo"
                               name="porcentaje_fondo_reparo"
                               class="form-control"
                               inputmode="decimal"
                               autocomplete="off"
                               placeholder="5,000 %"
                               value="<?= esc($porcFondo) ?>">
                        <span class="cert-input-affijo" aria-hidden="true">%</span>
                    </div>
                <?php else: ?>
                    <p class="ficha-valor"><?= esc(CertificacionObra::formatearPorcentaje($obra->porcentaje_fondo_reparo ?? null)) ?></p>
                <?php endif; ?>
            </div>

            <fieldset class="form-group ficha-campo cert-grupo cert-grupo-interno" <?= $editable ? '' : 'disabled' ?>>
                <legend>¿El fondo de reparo se cubre con póliza?</legend>
                <div class="cert-opciones">
                    <label class="form-check">
                        <input type="radio"
                               name="fondo_reparo_con_poliza"
                               value="1"
                               <?= $polizaSiNo === '1' ? 'checked' : '' ?>
                               <?= $editable ? '' : 'disabled' ?>>
                        <span>Sí</span>
                    </label>
                    <label class="form-check">
                        <input type="radio"
                               name="fondo_reparo_con_poliza"
                               value="0"
                               <?= $polizaSiNo === '0' ? 'checked' : '' ?>
                               <?= $editable ? '' : 'disabled' ?>>
                        <span>No</span>
                    </label>
                </div>
            </fieldset>
        </div>

        <?php if ($editable): ?>
            <div class="ficha-form-acciones">
                <button type="button"
                        class="btn btn-success"
                        id="btnAbrirConfirmacionConfig"
                        aria-haspopup="dialog"
                        aria-controls="modalConfirmacionConfig">
                    <i class="bi bi-check-lg" aria-hidden="true"></i>
                    Confirmar configuración
                </button>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($editable): ?>
        </form>
    <?php endif; ?>

    <?php if ($confirmada): ?>
        <section class="ficha-datos cert-tabla-seccion">
            <div class="cert-tabla-encabezado">
                <h2 class="ficha-seccion-titulo">Certificados</h2>
                <?php if ($puedeEditar): ?>
                    <button type="button"
                            class="btn btn-success"
                            id="btnCargarCertificado"
                            aria-haspopup="dialog"
                            aria-controls="modalCertificado">
                        <i class="bi bi-plus-lg" aria-hidden="true"></i>
                        Cargar Certificado
                    </button>
                <?php endif; ?>
            </div>

            <div class="sigoa-table-wrap">
                <table class="sigoa-table cert-tabla">
                    <thead>
                        <tr>
                            <th>Certificado N°</th>
                            <th>Mes</th>
                            <th>Año</th>
                            <th>Monto bruto</th>
                            <th>Estado Anticipo</th>
                            <th>Monto deducción anticipo</th>
                            <th>Fondo de reparo</th>
                            <th>Suma a pagar al contratista</th>
                            <th>Avance mensual</th>
                            <th>Avance acumulado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($certificados === []): ?>
                            <tr>
                                <td colspan="10" class="cert-tabla-vacia">No hay certificados emitidos.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($certificados as $certificado): ?>
                                <tr>
                                    <td><?= esc((string) $certificado->numero) ?></td>
                                    <td><?= esc(CertificacionObra::nombreMes((int) $certificado->mes)) ?></td>
                                    <td><?= esc((string) $certificado->anio) ?></td>
                                    <td class="cert-col-importe"><?= $celdaImporte($certificado->monto_bruto) ?></td>
                                    <td><?= esc(CertificacionObra::estadoAnticipoUi($certificado->estado_anticipo ?? null)) ?></td>
                                    <td class="cert-col-importe"><?= $celdaImporte($certificado->descuento_anticipo ?? 0, true) ?></td>
                                    <td class="cert-col-importe"><?= $celdaImporte($certificado->retencion_fondo_reparo ?? 0, true) ?></td>
                                    <td class="cert-col-importe"><?= $celdaImporte($certificado->monto_neto) ?></td>
                                    <td class="cert-col-porcentaje"><?= esc(CertificacionObra::formatearPorcentaje($certificado->avance_mensual ?? null)) ?></td>
                                    <td class="cert-col-porcentaje"><?= esc(CertificacionObra::formatearPorcentaje($certificado->avance_acumulado ?? null)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

</div>

<?php if ($editable): ?>
<div class="modal-overlay"
     id="modalConfirmacionConfig"
     hidden
     aria-hidden="true">
    <div class="modal modal-cert-resumen" role="dialog" aria-modal="true" aria-labelledby="modalConfirmacionConfigTitulo">
        <h2 class="modal-title" id="modalConfirmacionConfigTitulo">
            <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
            ¿Confirmar configuración económica?
        </h2>
        <p class="modal-message">
            Una vez confirmada y emitido el primer certificado, esta configuración no podrá modificarse.
        </p>
        <dl class="cert-resumen" id="resumenConfigEconomica"></dl>
        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" id="btnCancelarConfirmacionConfig">
                Cancelar
            </button>
            <button type="submit" class="btn btn-success" form="formConfigEconomica">
                <i class="bi bi-check-lg" aria-hidden="true"></i>
                Confirmar
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($puedeEditar && $confirmada): ?>
<div class="modal-overlay"
     id="modalCertificado"
     <?= $modalCertificadoAbierto ? '' : 'hidden' ?>
     aria-hidden="<?= $modalCertificadoAbierto ? 'false' : 'true' ?>">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalCertificadoTitulo">
        <h2 class="modal-title" id="modalCertificadoTitulo">
            <i class="bi bi-plus-lg" aria-hidden="true"></i>
            Cargar certificado
        </h2>
        <p class="modal-message">
            Indique el período y el monto bruto. El sistema calculará descuentos, retenciones, suma a pagar y avances.
        </p>

        <?php if ($modalCertificadoAbierto): ?>
            <div class="modal-error" role="alert" aria-live="polite">
                <?php foreach ($erroresCertificado as $errorCertificado): ?>
                    <div><?= esc($errorCertificado) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= site_url('/obras/certificados/crear') ?>" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="obra_id" value="<?= (int) $obra->id ?>">

            <div class="form-group">
                <label for="mes">
                    Mes
                    <span class="obras-requerido" aria-hidden="true">*</span>
                </label>
                <select id="mes" name="mes" class="form-control">
                    <option value="">Seleccione el mes</option>
                    <?php foreach ($meses as $numeroMes => $nombreMes): ?>
                        <option value="<?= (int) $numeroMes ?>"
                            <?= (string) $valorMes === (string) $numeroMes ? 'selected' : '' ?>>
                            <?= esc($nombreMes) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="anio">
                    Año
                    <span class="obras-requerido" aria-hidden="true">*</span>
                </label>
                <input type="text"
                       id="anio"
                       name="anio"
                       class="form-control"
                       inputmode="numeric"
                       maxlength="4"
                       autocomplete="off"
                       value="<?= esc($valorAnio) ?>">
            </div>

            <div class="form-group">
                <label for="monto_bruto">
                    Monto bruto
                    <span class="obras-requerido" aria-hidden="true">*</span>
                </label>
                <div class="cert-input-grupo">
                    <span class="cert-input-affijo" aria-hidden="true">$</span>
                    <input type="text"
                           id="monto_bruto"
                           name="monto_bruto"
                           class="form-control"
                           inputmode="decimal"
                           autocomplete="off"
                           placeholder="$ 10.000.000"
                           value="<?= esc($valorBruto) ?>">
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" id="btnCancelarCertificado">
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
    <script src="<?= base_url('assets/js/pages/obra-certificados.js') ?>"></script>
<?= $this->endSection() ?>
