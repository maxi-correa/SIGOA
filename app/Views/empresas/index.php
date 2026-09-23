<?= $this->extend('layouts/auth') ?>

<?= $this->section('styles') ?>
    <link rel="stylesheet" href="<?= base_url('assets/css/pages/empresas.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="empresas-page">

    <header class="empresas-header">
        <div class="empresas-header-texto">
            <h1>Empresas</h1>
            <p class="empresas-subtitulo">
                Gestioná las empresas contratistas registradas en SIGOA.
            </p>
        </div>

        <a href="<?= site_url('/empresas/nueva') ?>" class="btn btn-success empresas-btn-alta">
            <i class="bi bi-plus-lg" aria-hidden="true"></i>
            Alta empresa
        </a>
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

    <?php if (empty($empresas)): ?>

        <div class="empresas-vacio">
            <p class="empresas-vacio-titulo">No hay empresas registradas.</p>
            <p class="empresas-vacio-texto">
                Actualmente no existen empresas cargadas en el sistema. Utilice
                el botón <strong>“Alta empresa”</strong> para registrar la primera.
            </p>
            <p class="empresas-vacio-accion">
                <a href="<?= site_url('/empresas/nueva') ?>" class="btn btn-success">
                    <i class="bi bi-plus-lg" aria-hidden="true"></i>
                    Alta empresa
                </a>
            </p>
        </div>

    <?php else: ?>

        <div class="sigoa-table-wrap">
            <table class="sigoa-table empresas-tabla">
                <thead>
                    <tr>
                        <th scope="col" class="empresas-logo-th">
                            <span class="sr-only">Logo</span>
                        </th>
                        <th scope="col">Razón social</th>
                        <th scope="col">CUIT</th>
                        <th scope="col">Domicilio</th>
                        <th scope="col">Teléfono</th>
                        <th scope="col">Email</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($empresas as $empresa): ?>
                        <tr>
                            <td class="empresas-logo-cell">
                                <div class="empresa-logo">
                                    <form method="post"
                                          action="<?= site_url('/empresas/logo/actualizar') ?>"
                                          enctype="multipart/form-data"
                                          class="empresa-logo-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="empresa_id" value="<?= (int) $empresa->id ?>">
                                        <input type="file"
                                               id="logo_<?= (int) $empresa->id ?>"
                                               name="logo"
                                               class="empresa-logo-input"
                                               accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                               tabindex="-1">
                                        <label for="logo_<?= (int) $empresa->id ?>"
                                               class="empresa-logo-etiqueta"
                                               title="<?= ! empty($empresa->ruta_logo) ? 'Cambiar logo' : 'Subir logo' ?>">
                                            <?php if (! empty($empresa->ruta_logo)): ?>
                                                <img src="<?= site_url('/empresas/logo/' . (int) $empresa->id) ?>"
                                                     alt="Logo de <?= esc($empresa->razon_social) ?>">
                                            <?php else: ?>
                                                <span class="empresa-logo-vacio" aria-hidden="true">
                                                    <i class="bi bi-plus-lg"></i>
                                                </span>
                                            <?php endif; ?>
                                        </label>
                                        <?php if (! empty($empresa->ruta_logo)): ?>
                                            <button type="button"
                                                    class="empresa-logo-eliminar"
                                                    data-empresa-id="<?= (int) $empresa->id ?>"
                                                    title="Eliminar logo"
                                                    aria-label="Eliminar logo de <?= esc($empresa->razon_social) ?>">
                                                <i class="bi bi-trash" aria-hidden="true"></i>
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </td>
                            <td class="empresas-nombre"><?= esc($empresa->razon_social) ?></td>
                            <td>
                                <?php if ($empresa->cuit): ?>
                                    <?= esc($empresa->cuit) ?>
                                <?php else: ?>
                                    <span class="empresas-sd">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($empresa->domicilio): ?>
                                    <?= esc($empresa->domicilio) ?>
                                <?php else: ?>
                                    <span class="empresas-sd">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($empresa->telefono): ?>
                                    <?= esc($empresa->telefono) ?>
                                <?php else: ?>
                                    <span class="empresas-sd">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($empresa->email): ?>
                                    <?= esc($empresa->email) ?>
                                <?php else: ?>
                                    <span class="empresas-sd">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="empresas-estado <?= $empresa->activo ? 'empresas-estado-activa' : 'empresas-estado-inactiva' ?>">
                                    <span class="empresas-estado-dot" aria-hidden="true"></span>
                                    <?= $empresa->activo ? 'Activa' : 'Inactiva' ?>
                                </span>
                            </td>
                            <td class="empresas-acciones-cell">
                                <a href="<?= site_url('/empresas/editar/' . (int) $empresa->id) ?>"
                                   class="empresas-btn-accion"
                                   title="Editar empresa">
                                    <i class="bi bi-pencil" aria-hidden="true"></i>
                                    Editar
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

</div>

<!-- Modal: confirmación de eliminación de logo -->
<div class="modal-overlay" id="modalEliminarLogo" hidden aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalEliminarTitulo">
        <h2 class="modal-title" id="modalEliminarTitulo">
            <i class="bi bi-trash" aria-hidden="true"></i>
            Eliminar logo
        </h2>
        <p class="modal-message">
            ¿Eliminar logo?
        </p>
        <p class="modal-message">
            El logo de esta empresa será eliminado de SIGOA. Esta acción no afecta los datos de la empresa.
        </p>
        <form method="post" action="<?= site_url('/empresas/logo/eliminar') ?>" id="formEliminarLogo" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="empresa_id" id="eliminar_empresa_id" value="">
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" id="btnCancelarEliminarLogo">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-danger">
                    <i class="bi bi-trash" aria-hidden="true"></i>
                    Eliminar logo
                </button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script src="<?= base_url('assets/js/pages/empresas.js') ?>"></script>
<?= $this->endSection() ?>