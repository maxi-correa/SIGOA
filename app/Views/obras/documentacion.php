<?php
use App\Libraries\PlazoObra;

$this->extend('layouts/auth');

$this->section('styles');
?>
    <link rel="stylesheet" href="<?= base_url('assets/css/pages/obras.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/pages/ficha-obra.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/pages/documentacion.css') ?>">
<?php
$this->endSection();

$this->section('content');
$rolesNav = $roles ?? [];
$puedeEditar = $puede_editar ?? false;
$sinTiposPendientes = $puedeEditar && $opcionesTipo === [];

$estadoNombre = strtoupper((string) ($obra->estado_nombre ?? ''));
$estadoClase = match ($estadoNombre) {
    'EN EJECUCIÓN'             => 'estado-ejecucion',
    'NEUTRALIZADA'             => 'estado-neutralizada',
    'EN PLAZO DE CONSERVACIÓN' => 'estado-conservacion',
    'FINALIZADA'               => 'estado-finalizada',
    default                    => 'estado-previo',
};
?>

<div class="ficha-obra-page documentacion-page">
    <div class="ficha-barra-acciones">
        <a href="<?= site_url('/dashboard') ?>" class="btn btn-secondary ficha-btn-volver">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            Volver al dashboard
        </a>
        <a href="<?= site_url('/obras/ver/' . (int) $obra->id) ?>" class="btn btn-secondary">
            <i class="bi bi-file-text" aria-hidden="true"></i>
            Volver a la ficha
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

    <?php
    /* Los errores de la carga se muestran dentro del modal reabierto; el
       resto de errores documentales (por ejemplo, al reemplazar) se
       informan como alerta en la parte superior. */
    $erroresDocumento    = session()->getFlashdata('errores_documento');
    $reabrirAgregarModal = (string) (session()->getFlashdata('reabrir_modal_documento') ?? '');
    $hayErroresDocumento = is_array($erroresDocumento) && $erroresDocumento !== [];

    if ($hayErroresDocumento && $reabrirAgregarModal === ''): ?>
        <div class="alert alert-danger" role="alert">
            <span class="alert-icon"><i class="bi bi-exclamation-circle"></i></span>
            <span>
                <?php foreach ($erroresDocumento as $error): ?>
                    <div><?= esc($error) ?></div>
                <?php endforeach; ?>
            </span>
        </div>
    <?php endif; ?>

    <section class="ficha-identidad">
        <div class="ficha-identidad-principal">
            <div class="ficha-titular">
                <h1 class="ficha-nombre"><?= esc($obra->nombre) ?></h1>
                <span class="documentacion-subtitulo">Documentación</span>
                <span class="ficha-codigo"><?= esc($obra->codigo) ?></span>
            </div>
            <span class="estado-badge <?= $estadoClase ?>"><?= esc($estadoNombre) ?></span>
        </div>
        <dl class="ficha-grid">
            <div class="ficha-dato">
                <dt>N° de Expte.</dt>
                <dd><?= esc($obra->expediente_municipal) ?></dd>
            </div>
            <div class="ficha-dato">
                <dt>Tipo de Licitación</dt>
                <dd><?= $obra->tipo_licitacion_nombre ? esc($obra->tipo_licitacion_nombre) : '<span class="obras-sd">S/D</span>' ?></dd>
            </div>
            <div class="ficha-dato">
                <dt>N° de Licitación</dt>
                <dd><?= $obra->numero_licitacion ? esc($obra->numero_licitacion) : '<span class="obras-sd">S/D</span>' ?></dd>
            </div>
            <div class="ficha-dato">
                <dt>Expte. contable</dt>
                <dd><?= !empty($obra->expediente_contable) ? esc($obra->expediente_contable) : '<span class="obras-sd">S/D</span>' ?></dd>
            </div>
        </dl>
    </section>

    <section class="card documentacion-card" id="seccionDocumentacionCargada">
        <div class="card-header documentacion-card-header">
            <h2>Documentación cargada</h2>
            <?php if ($puedeEditar && !$sinTiposPendientes): ?>
                <button type="button"
                        class="btn btn-success documentacion-btn-agregar"
                        id="btnAgregarDocumento"
                        aria-haspopup="dialog"
                        aria-controls="modalAgregarDocumento">
                    <i class="bi bi-plus-lg" aria-hidden="true"></i>
                    Agregar documento
                </button>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($sinTiposPendientes): ?>
                <p class="documentacion-nota">
                    Todos los tipos documentales previstos para el estado actual de la obra ya
                    tienen un documento vigente. Para actualizar uno existente utilice
                    «Reemplazar documento».
                </p>
            <?php endif; ?>
            <?php if (empty($documentosAgrupados)): ?>
                <div class="documentacion-vacio">
                    <p class="documentacion-vacio-texto">No hay documentación cargada para esta obra.</p>
                    <?php if ($puedeEditar && !$sinTiposPendientes): ?>
                        <p class="documentacion-vacio-accion">
                            <button type="button" class="btn btn-success documentacion-cta" data-ir-agregar>
                                <i class="bi bi-plus-lg" aria-hidden="true"></i>
                                Agregar documento
                            </button>
                        </p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($documentosAgrupados as $tipoNombre => $grupos): ?>
                    <h3 class="documentacion-tipo"><?= esc($tipoNombre) ?></h3>
                    <div class="documentacion-lista">
                        <?php foreach ($grupos as $grupo): ?>
                            <?php $doc = $grupo['principal']; ?>
                            <div class="documentacion-grupo">
                                <div class="documentacion-item <?= $doc && $doc->es_version_actual ? 'actual' : 'historico' ?>">
                                    <div class="documentacion-info">
                                        <div class="documentacion-nombre">
                                            <i class="bi bi-file-pdf-fill text-danger" aria-hidden="true"></i>
                                            <?= esc($doc->nombre_archivo) ?>
                                            <?php if ($doc->es_version_actual): ?>
                                                <span class="badge badge-success">Versión vigente</span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">v<?= (int) $doc->version ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="documentacion-meta">
                                            <span><i class="bi bi-calendar" aria-hidden="true"></i> <?= esc(PlazoObra::formatearFecha($doc->created_at)) ?></span>
                                            <?php if ($doc->usuario_nombre): ?>
                                                <span><i class="bi bi-person" aria-hidden="true"></i> <?= esc($doc->usuario_nombre . ' ' . $doc->usuario_apellido) ?></span>
                                            <?php endif; ?>
                                            <?php if ($doc->reinicio_id && $doc->fecha_reinicio): ?>
                                                <span>
                                                    <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
                                                    Reinicio N° <?= (int) $doc->reinicio_numero ?> - <?= esc(PlazoObra::formatearFecha($doc->fecha_reinicio)) ?>
                                                </span>
                                            <?php elseif ($doc->neutralizacion_id && $doc->fecha_neutralizacion): ?>
                                                <span>
                                                    <i class="bi bi-pause-circle" aria-hidden="true"></i>
                                                    Neutralización N° <?= (int) $doc->neutralizacion_numero ?> - <?= esc(PlazoObra::formatearFecha($doc->fecha_neutralizacion)) ?>
                                                </span>
                                            <?php elseif ($doc->neutralizacion_id || $doc->reinicio_id): ?>
                                                <span>
                                                    <?php if ($doc->reinicio_id): ?>
                                                        Reinicio N° <?= (int) ($doc->reinicio_numero ?? $doc->reinicio_id) ?>
                                                    <?php else: ?>
                                                        Neutralización N° <?= (int) ($doc->neutralizacion_numero ?? $doc->neutralizacion_id) ?>
                                                    <?php endif; ?>
                                                </span>
                                            <?php endif; ?>
                                            <span>v<?= (int) $doc->version ?></span>
                                        </div>
                                    </div>
                                    <div class="documentacion-acciones">
                                        <a href="<?= site_url('/obras/documentacion/ver/' . (int) $doc->id) ?>"
                                           target="_blank"
                                           rel="noopener"
                                           class="btn-accion-icon"
                                           title="Abrir o visualizar PDF"
                                           aria-label="Abrir o visualizar PDF">
                                            <i class="bi bi-file-pdf" aria-hidden="true"></i>
                                        </a>
                                        <?php if ($puedeEditar): ?>
                                            <button type="button"
                                                    class="btn-accion-icon"
                                                    data-accion="ubicacion"
                                                    data-doc-id="<?= (int) $doc->id ?>"
                                                    data-url="<?= site_url('/obras/documentacion/ubicacion/' . (int) $doc->id) ?>"
                                                    title="Ver ubicación del archivo"
                                                    aria-label="Ver ubicación del archivo">
                                                <i class="bi bi-folder2-open" aria-hidden="true"></i>
                                            </button>
                                            <button type="button"
                                                    class="btn-accion-icon"
                                                    data-accion="reemplazar"
                                                    data-doc-id="<?= (int) $doc->id ?>"
                                                    data-nombre="<?= esc($doc->nombre_archivo) ?>"
                                                    data-observaciones="<?= esc($doc->observaciones ?? '') ?>"
                                                    title="Reemplazar documento"
                                                    aria-label="Reemplazar documento">
                                                <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if ($grupo['historial'] !== []): ?>
                                    <details class="documentacion-historial">
                                        <summary>Versiones anteriores (<?= count($grupo['historial']) ?>)</summary>
                                        <div class="documentacion-lista">
                                            <?php foreach ($grupo['historial'] as $ant): ?>
                                                <div class="documentacion-item historico">
                                                    <div class="documentacion-info">
                                                        <div class="documentacion-nombre">
                                                            <i class="bi bi-file-pdf" aria-hidden="true"></i>
                                                            <?= esc($ant->nombre_archivo) ?>
                                                            <span class="badge badge-secondary">v<?= (int) $ant->version ?></span>
                                                        </div>
                                                        <div class="documentacion-meta">
                                                            <span><i class="bi bi-calendar" aria-hidden="true"></i> <?= esc(PlazoObra::formatearFecha($ant->created_at)) ?></span>
                                                            <?php if ($ant->usuario_nombre): ?>
                                                                <span><i class="bi bi-person" aria-hidden="true"></i> <?= esc($ant->usuario_nombre . ' ' . $ant->usuario_apellido) ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div class="documentacion-acciones">
                                                        <a href="<?= site_url('/obras/documentacion/ver/' . (int) $ant->id) ?>"
                                                           target="_blank"
                                                           rel="noopener"
                                                           class="btn-accion-icon"
                                                           title="Abrir o visualizar PDF"
                                                           aria-label="Abrir o visualizar PDF">
                                                            <i class="bi bi-file-pdf" aria-hidden="true"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </details>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php if ($puedeEditar && !$sinTiposPendientes): ?>
    <?php
    $reabrirAgregar    = $reabrirAgregarModal;
    $erroresAgregar    = $erroresDocumento;
    $oldTipo           = old('tipo_documento_id');
    $oldNeutralizacion = old('neutralizacion_id');
    $oldReinicio       = old('reinicio_id');
    $oldObservaciones  = old('observaciones');
    ?>
    <div class="modal-overlay"
         id="modalAgregarDocumento"
         hidden
         aria-hidden="true"
         data-reabrir="<?= esc($reabrirAgregar) ?>">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalAgregarTitulo">
            <h2 class="modal-title" id="modalAgregarTitulo">
                <i class="bi bi-plus-square" aria-hidden="true"></i>
                Agregar documento
            </h2>
            <p class="modal-message">
                Seleccione el tipo documental, adjunte el archivo PDF y complete las
                observaciones si corresponde.
            </p>

            <div class="modal-error" id="agregarDocumentoError" role="alert" aria-live="polite"
                 <?= is_array($erroresAgregar) && $erroresAgregar !== [] && $reabrirAgregar !== '' ? '' : 'hidden' ?>>
                <?php if (is_array($erroresAgregar) && $reabrirAgregar !== ''): ?>
                    <?php foreach ($erroresAgregar as $error): ?>
                        <div><?= esc($error) ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <form method="post" action="<?= site_url('/obras/documentacion/subir') ?>" enctype="multipart/form-data" id="formAgregarDocumento" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="obra_id" value="<?= (int) $obra->id ?>">
                <div class="documentacion-form">
                    <div class="form-group">
                        <label for="tipo_documento_id">Tipo de documento</label>
                        <select class="form-control" id="tipo_documento_id" name="tipo_documento_id" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach ($opcionesTipo as $opt): ?>
                                <option value="<?= (int) $opt['id'] ?>"
                                        data-tipo="<?= esc($opt['nombre']) ?>"
                                        data-habilitado="<?= $opt['habilitado'] ? '1' : '0' ?>"
                                        data-motivo="<?= esc($opt['motivo'] ?? '') ?>"
                                        <?= $oldTipo !== null && (int) $oldTipo === (int) $opt['id'] ? 'selected' : '' ?>>
                                    <?= esc($opt['nombre']) ?>
                                    <?php if (!$opt['habilitado']): ?> (No disponible)<?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="documentacion-ayuda" id="tipo_motivo"></small>
                    </div>
                    <div class="form-group" id="campo_neutralizacion" hidden>
                        <label for="neutralizacion_id">Neutralización</label>
                        <select class="form-control" name="neutralizacion_id" id="neutralizacion_id">
                            <option value="">Seleccionar...</option>
                            <?php foreach ($neutralizacionesPendientes as $n): ?>
                                <option value="<?= (int) $n->id ?>"
                                        <?= $oldNeutralizacion !== null && (int) $oldNeutralizacion === (int) $n->id ? 'selected' : '' ?>>
                                    N° <?= (int) $n->numero ?> - Neutralización <?= esc(PlazoObra::formatearFecha($n->fecha_inicio)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" id="campo_reinicio" hidden>
                        <label for="reinicio_id">Reinicio (neutralización con reinicio registrado)</label>
                        <select class="form-control" name="reinicio_id" id="reinicio_id">
                            <option value="">Seleccionar...</option>
                            <?php foreach ($reiniciosPendientes as $n): ?>
                                <option value="<?= (int) $n->id ?>"
                                        <?= $oldReinicio !== null && (int) $oldReinicio === (int) $n->id ? 'selected' : '' ?>>
                                    N° <?= (int) $n->numero ?> - Neutralización <?= esc(PlazoObra::formatearFecha($n->fecha_inicio)) ?> → Reinicio <?= esc(PlazoObra::formatearFecha($n->fecha_reinicio)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="archivo">Archivo PDF</label>
                        <div class="archivo-selector" data-archivo-selector>
                            <input type="file"
                                   class="form-control archivo-selector-input"
                                   id="archivo"
                                   name="archivo"
                                   accept="application/pdf"
                                   required
                                   data-archivo-input>
                            <label class="archivo-selector-boton" for="archivo">
                                <i class="bi bi-paperclip" aria-hidden="true"></i>
                                Seleccionar archivo
                            </label>
                            <span class="archivo-selector-nombre" data-archivo-nombre>Ningún archivo seleccionado</span>
                        </div>
                        <small class="documentacion-ayuda">Solo archivos PDF. Máx. 10MB.</small>
                    </div>
                    <div class="form-group">
                        <label for="observaciones">Observaciones</label>
                        <textarea class="form-control" id="observaciones" name="observaciones" rows="2"><?= esc($oldObservaciones ?? '') ?></textarea>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" id="btnCancelarAgregar">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-plus-circle" aria-hidden="true"></i>
                        Cargar documento
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if ($puedeEditar): ?>
    <div class="modal-overlay" id="modalUbicacion" hidden aria-hidden="true">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalUbicacionTitulo">
            <h2 class="modal-title" id="modalUbicacionTitulo">
                <i class="bi bi-folder2-open" aria-hidden="true"></i>
                Ubicación del archivo
            </h2>
            <div class="modal-error" id="ubicacionError" role="alert" hidden></div>
            <p class="modal-message"><strong>Ruta completa:</strong></p>
            <pre class="ruta-texto" id="rutaCompleta"></pre>
            <p class="modal-message"><strong>Carpeta contenedora:</strong></p>
            <pre class="ruta-texto" id="rutaDirectorio"></pre>
            <p class="ruta-copiado" id="avisoCopiado" role="status" aria-live="polite"></p>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" id="btnCopiarRuta" disabled>
                    <i class="bi bi-clipboard" aria-hidden="true"></i>
                    Copiar ruta
                </button>
                <button type="button" class="btn btn-secondary" id="btnCopiarDir" disabled>
                    <i class="bi bi-clipboard" aria-hidden="true"></i>
                    Copiar carpeta
                </button>
                <button type="button" class="btn btn-primary" id="btnCerrarUbicacion">
                    Cerrar
                </button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="modalReemplazar" hidden aria-hidden="true">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalReemplazarTitulo">
            <h2 class="modal-title" id="modalReemplazarTitulo">
                <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
                Reemplazar documento
            </h2>
            <p class="modal-message">
                Se cargará una nueva versión vigente de
                <strong id="reemplazarNombre"></strong>. La versión actual se conserva en el historial.
            </p>
            <form method="post" action="<?= site_url('/obras/documentacion/reemplazar') ?>" enctype="multipart/form-data" id="formReemplazar" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="documento_id" id="reemplazar_documento_id" value="">
                <input type="hidden" name="obra_id" value="<?= (int) $obra->id ?>">
                <div class="form-group">
                    <label for="reemplazar_archivo">Nuevo archivo PDF</label>
                    <div class="archivo-selector" data-archivo-selector>
                        <input type="file"
                               class="form-control archivo-selector-input"
                               id="reemplazar_archivo"
                               name="archivo"
                               accept="application/pdf"
                               required
                               data-archivo-input>
                        <label class="archivo-selector-boton" for="reemplazar_archivo">
                            <i class="bi bi-paperclip" aria-hidden="true"></i>
                            Seleccionar archivo
                        </label>
                        <span class="archivo-selector-nombre" data-archivo-nombre>Ningún archivo seleccionado</span>
                    </div>
                    <small class="documentacion-ayuda">Solo archivos PDF. Máx. 10MB.</small>
                </div>
                <div class="form-group">
                    <label for="reemplazar_observaciones">Observaciones</label>
                    <textarea class="form-control" id="reemplazar_observaciones" name="observaciones" rows="2"></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" id="btnCancelarReemplazar">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
                        Reemplazar
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/documentacion.js') ?>"></script>
<?= $this->endSection() ?>
