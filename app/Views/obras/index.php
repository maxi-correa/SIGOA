<?= $this->extend('layouts/auth') ?>

<?= $this->section('styles') ?>
    <link rel="stylesheet" href="<?= base_url('assets/css/pages/obras.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
$obrasTotal     = $pager->getTotal();
$paginaActual   = $pager->getCurrentPage();
$paginasTotales = $pager->getPageCount();
$perPageReal    = (int) ($per_page ?? 10);
$desde          = $obrasTotal === 0 ? 0 : (($paginaActual - 1) * $perPageReal) + 1;
$hasta          = min($paginaActual * $perPageReal, $obrasTotal);

$oldBarrio  = old('barrio_id') ?? '';
$oldEmpresa = old('empresa_id') ?? '';
$oldTipo    = old('tipo_licitacion_id') ?? '';
$oldEstado  = old('estado_obra_id') ?? '';

$rolesNav          = $roles ?? [];
$puedeEditarObras  = array_intersect(['SUPERADMINISTRADOR', 'ADMINISTRADOR'], $rolesNav) !== [];
?>

<div class="obras-page">

    <header class="obras-header">
        <div class="obras-header-texto">
            <h1>Dirección General de Ejecución de Obras de Arquitectura</h1>
            <p class="obras-subtitulo">Obras</p>
        </div>

        <button type="button"
                class="btn btn-primary obras-btn-agregar"
                id="btnAgregarObra"
                aria-haspopup="dialog"
                aria-controls="modalObra">
            <i class="bi bi-plus-lg" aria-hidden="true"></i>
            Agregar obra
        </button>
    </header>

    <!-- Mensajes del sistema -->
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

    <!-- Zona reservada para buscador y filtros futuros -->
    <div class="obras-filtros" aria-hidden="true">
        <div class="obras-filtros-item obras-filtros-buscador">
            <label class="sr-only" for="buscarObra">Buscar obra</label>
            <input type="search"
                   id="buscarObra"
                   name="buscar"
                   class="form-control"
                   placeholder="Buscar obra..."
                   disabled
                   title="Disponible próximamente">
        </div>
        <div class="obras-filtros-item">
            <label class="sr-only" for="filtroEstado">Filtro por estado</label>
            <select id="filtroEstado"
                    name="estado"
                    class="form-control"
                    disabled
                    title="Disponible próximamente">
                <option value="">Estado</option>
            </select>
        </div>
    </div>

    <?php if (empty($obras)): ?>

        <div class="obras-vacio">
            <p class="obras-vacio-titulo">Aún no hay obras cargadas.</p>
            <p class="obras-vacio-texto">
                Utilice <strong>“Agregar obra”</strong> para registrar la primera obra.
            </p>
        </div>

    <?php else: ?>

        <div class="sigoa-table-wrap">
            <table class="sigoa-table obras-tabla" id="obrasTabla">
                <thead>
                    <tr>
                        <th scope="col">N° Expte.</th>
                        <th scope="col">Nombre de obra</th>
                        <th scope="col">Barrio</th>
                        <th scope="col">Empresa</th>
                        <th scope="col">Tipo Licitación</th>
                        <th scope="col">N° de Licitación</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($obras as $obra): ?>
                        <?php
                        $estadoNombre = strtoupper((string) ($obra->estado_nombre ?? ''));

                        $estadoClase = match ($estadoNombre) {
                            'EN EJECUCIÓN'             => 'obras-estado-ejecucion',
                            'NEUTRALIZADA'             => 'obras-estado-neutralizada',
                            'EN PLAZO DE CONSERVACIÓN' => 'obras-estado-conservacion',
                            'FINALIZADA'               => 'obras-estado-finalizada',
                            default                    => 'obras-estado-previo',
                        };
                        ?>
                        <tr>
                            <td><?= esc($obra->expediente_municipal) ?></td>
                            <td class="obras-nombre"><?= esc($obra->nombre) ?></td>
                            <td>
                                <?php if ($obra->barrio_nombre): ?>
                                    <?= esc($obra->barrio_nombre) ?>
                                <?php else: ?>
                                    <span class="obras-sd">S/D</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($obra->empresa_razon_social): ?>
                                    <?= esc($obra->empresa_razon_social) ?>
                                <?php else: ?>
                                    <span class="obras-sd">S/D</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($obra->tipo_licitacion_nombre): ?>
                                    <?= esc($obra->tipo_licitacion_nombre) ?>
                                <?php else: ?>
                                    <span class="obras-sd">S/D</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($obra->numero_licitacion): ?>
                                    <?= esc($obra->numero_licitacion) ?>
                                <?php else: ?>
                                    <span class="obras-sd">S/D</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="obras-estado <?= $estadoClase ?>">
                                    <?= esc($estadoNombre) ?>
                                </span>
                            </td>
                            <td class="obras-acciones-cell">
                                <?php if ($puedeEditarObras): ?>
                                    <div class="obras-acciones">
                                        <button type="button"
                                                class="obras-btn-accion"
                                                data-accion="editar"
                                                data-id="<?= (int) $obra->id ?>"
                                                data-codigo="<?= esc($obra->codigo) ?>"
                                                data-expediente="<?= esc($obra->expediente_municipal) ?>"
                                                data-nombre="<?= esc($obra->nombre) ?>"
                                                data-barrio="<?= (int) $obra->barrio_id ?>"
                                                data-empresa="<?= (int) $obra->empresa_id ?>"
                                                data-tipo="<?= (int) $obra->tipo_licitacion_id ?>"
                                                data-licitacion="<?= esc($obra->numero_licitacion ?? '') ?>"
                                                data-estado="<?= (int) $obra->estado_obra_id ?>"
                                                aria-haspopup="dialog"
                                                aria-controls="modalObra"
                                                title="Editar obra">
                                            <i class="bi bi-pencil" aria-hidden="true"></i>
                                            Editar
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <nav class="obras-paginacion" aria-label="Paginación de obras">
            <p class="obras-paginacion-info">
                Mostrando <?= $desde ?>–<?= $hasta ?> de <?= $obrasTotal ?> obras
            </p>

            <ul class="obras-paginacion-lista">
                <li>
                    <?php if ($paginaActual > 1): ?>
                        <a class="obras-paginacion-link" href="?page=<?= $paginaActual - 1 ?>">
                            <i class="bi bi-chevron-left" aria-hidden="true"></i>
                            Anterior
                        </a>
                    <?php else: ?>
                        <span class="obras-paginacion-link is-disabled">Anterior</span>
                    <?php endif; ?>
                </li>

                <?php for ($p = 1; $p <= $paginasTotales; $p++): ?>
                    <li>
                        <?php if ($p === $paginaActual): ?>
                            <span class="obras-paginacion-link is-current" aria-current="page"><?= $p ?></span>
                        <?php else: ?>
                            <a class="obras-paginacion-link" href="?page=<?= $p ?>"><?= $p ?></a>
                        <?php endif; ?>
                    </li>
                <?php endfor; ?>

                <li>
                    <?php if ($paginaActual < $paginasTotales): ?>
                        <a class="obras-paginacion-link" href="?page=<?= $paginaActual + 1 ?>">
                            Siguiente
                            <i class="bi bi-chevron-right" aria-hidden="true"></i>
                        </a>
                    <?php else: ?>
                        <span class="obras-paginacion-link is-disabled">Siguiente</span>
                    <?php endif; ?>
                </li>
            </ul>
        </nav>

    <?php endif; ?>

</div>

<!-- ================================================================
     MODAL — Agregar / Editar obra (estructura reutilizable)
     ================================================================ -->
<?php
$erroresModal  = session()->getFlashdata('errores_obra');
$reabrirModal  = (string) (session()->getFlashdata('reabrir_modal_obra') ?? '0');
$codigoEdicion = (string) (session()->getFlashdata('obra_edicion_codigo') ?? '');
?>
<div class="modal-overlay"
     id="modalObra"
     hidden
     aria-hidden="true"
     data-reabrir="<?= esc($reabrirModal) ?>"
     data-accion-alta="<?= site_url('/obras/crear') ?>"
     data-accion-edicion="<?= site_url('/obras/actualizar') ?>">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalObraTitulo">
        <h2 class="modal-title" id="modalObraTitulo">
            <i class="bi bi-plus-square" id="modalObraIcono" aria-hidden="true"></i>
            <span id="modalObraTituloTexto">Agregar obra</span>
        </h2>
        <p class="modal-message" id="modalObraMensaje">
            Registre el expediente inicial de la obra. La información de
            adjudicación podrá completarse posteriormente.
        </p>

        <div class="modal-error" id="errorModalObra" role="alert" aria-live="polite"
             <?= $erroresModal ? '' : 'hidden' ?>>
            <?php if (is_array($erroresModal)): ?>
                <?php foreach ($erroresModal as $errorObra): ?>
                    <div><?= esc($errorObra) ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <form id="formObra"
              method="post"
              action="<?= site_url('/obras/crear') ?>"
              novalidate>

            <input type="hidden" name="obra_id" id="obra_id" value="<?= esc(old('obra_id', '')) ?>">

            <div class="obras-codigo-info" id="bloqueCodigoObra" hidden>
                <span class="obras-codigo-etiqueta">Código de obra:</span>
                <strong id="codigoObra"><?= esc($codigoEdicion) ?></strong>
            </div>

            <div class="form-group">
                <label for="expediente_municipal">
                    N° de Expte. <span class="obras-requerido" aria-hidden="true">*</span>
                </label>
                <input type="text"
                       id="expediente_municipal"
                       name="expediente_municipal"
                       class="form-control"
                       maxlength="30"
                       autocomplete="off"
                       placeholder="Ej.: 1234-M-2026"
                       value="<?= esc(old('expediente_municipal', '')) ?>">
                <span class="field-error" role="alert" aria-live="polite"></span>
            </div>

            <div class="form-group">
                <label for="nombre">
                    Nombre de obra <span class="obras-requerido" aria-hidden="true">*</span>
                </label>
                <input type="text"
                       id="nombre"
                       name="nombre"
                       class="form-control"
                       maxlength="255"
                       autocomplete="off"
                       placeholder="Ej.: Ampliación Escuela N.º 12"
                       value="<?= esc(old('nombre', '')) ?>">
                <span class="field-error" role="alert" aria-live="polite"></span>
            </div>

            <div class="form-group">
                <label for="barrio_id">Barrio</label>
                <select id="barrio_id" name="barrio_id" class="form-control">
                    <option value="">Sin barrio</option>
                    <?php foreach ($barrios as $barrio): ?>
                        <option value="<?= (int) $barrio->id ?>"
                            <?= $oldBarrio !== '' && (int) $oldBarrio === (int) $barrio->id ? 'selected' : '' ?>>
                            <?= esc($barrio->nombre) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="empresa_id">Empresa</label>
                <select id="empresa_id" name="empresa_id" class="form-control">
                    <option value="">Sin empresa</option>
                    <?php foreach ($empresas as $empresa): ?>
                        <option value="<?= (int) $empresa->id ?>"
                            <?= $oldEmpresa !== '' && (int) $oldEmpresa === (int) $empresa->id ? 'selected' : '' ?>>
                            <?= esc($empresa->razon_social) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="tipo_licitacion_id">Tipo de Licitación</label>
                <select id="tipo_licitacion_id" name="tipo_licitacion_id" class="form-control">
                    <option value="">Sin tipo de licitación</option>
                    <?php foreach ($tipos_licitacion as $tipo): ?>
                        <option value="<?= (int) $tipo->id ?>"
                            <?= $oldTipo !== '' && (int) $oldTipo === (int) $tipo->id ? 'selected' : '' ?>>
                            <?= esc($tipo->tipo_licitacion) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="numero_licitacion">N° de Licitación</label>
                <input type="text"
                       id="numero_licitacion"
                       name="numero_licitacion"
                       class="form-control"
                       maxlength="30"
                       autocomplete="off"
                       placeholder="Ej.: 34/2026"
                       value="<?= esc(old('numero_licitacion', '')) ?>">
                <span class="field-error" role="alert" aria-live="polite"></span>
            </div>

            <!-- Estado — modo alta: impuesto por el sistema -->
            <div class="form-group" id="bloqueEstadoAlta">
                <label for="estado_obra_preview">Estado</label>
                <input type="text"
                       id="estado_obra_preview"
                       class="form-control obras-estado-preview"
                       value="<?= esc($estado_previo_inicio?->estado ?? 'PREVIO INICIO') ?>"
                       disabled>
                <span class="obras-estado-nota">
                    Valor asignado automáticamente por el sistema al crear la obra.
                </span>
            </div>

            <!-- Estado — modo edición: selección libre del catálogo -->
            <div class="form-group" id="bloqueEstadoEdicion" hidden>
                <label for="estado_obra_id">Estado</label>
                <select id="estado_obra_id" name="estado_obra_id" class="form-control">
                    <?php foreach ($estados as $estado): ?>
                        <option value="<?= (int) $estado->id ?>"
                            <?= $oldEstado !== '' && (int) $oldEstado === (int) $estado->id ? 'selected' : '' ?>>
                            <?= esc($estado->estado) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="obras-estado-nota">
                    Puede modificarse libremente en esta etapa para registrar obras existentes.
                </span>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" id="btnCancelarObra">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-success" id="btnGuardarObra">
                    <i class="bi bi-check-lg" aria-hidden="true"></i>
                    <span id="btnGuardarObraTexto">Guardar</span>
                </button>
            </div>

        </form>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script src="<?= base_url('assets/js/pages/obras.js') ?>"></script>
<?= $this->endSection() ?>