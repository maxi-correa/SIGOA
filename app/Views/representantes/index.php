<?= $this->extend('layouts/auth') ?>

<?= $this->section('styles') ?>
    <link rel="stylesheet" href="<?= base_url('assets/css/pages/representantes.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="representantes-page">

    <header class="representantes-header">
        <div class="representantes-header-texto">
            <h1>Representantes técnicos</h1>
            <p class="representantes-subtitulo">
                Gestioná los representantes técnicos registrados en SIGOA.
            </p>
        </div>

        <a href="<?= site_url('/representantes/nuevo') ?>" class="btn btn-success representantes-btn-alta">
            <i class="bi bi-plus-lg" aria-hidden="true"></i>
            Agregar representante técnico
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

    <?php if (empty($representantes)): ?>

        <div class="representantes-vacio">
            <p class="representantes-vacio-titulo">No hay representantes técnicos registrados.</p>
            <p class="representantes-vacio-texto">
                Actualmente no existen representantes técnicos cargados en el sistema.
                Utilice el botón <strong>“Agregar representante técnico”</strong> para
                registrar el primero.
            </p>
            <p class="representantes-vacio-accion">
                <a href="<?= site_url('/representantes/nuevo') ?>" class="btn btn-success">
                    <i class="bi bi-plus-lg" aria-hidden="true"></i>
                    Agregar representante técnico
                </a>
            </p>
        </div>

    <?php else: ?>

        <div class="sigoa-table-wrap">
            <table class="sigoa-table representantes-tabla">
                <thead>
                    <tr>
                        <th scope="col">Apellido y nombre</th>
                        <th scope="col">Título profesional</th>
                        <th scope="col">Matrícula Provincial</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($representantes as $representante): ?>
                        <tr>
                            <td class="representantes-nombre">
                                <?= esc($representante->apellido . ', ' . $representante->nombre) ?>
                            </td>
                            <td>
                                <span class="representantes-titulo"><?= esc($representante->titulo_profesional_nombre) ?></span>
                            </td>
                            <td>
                                <?php if (! empty($representante->matricula)): ?>
                                    <?= esc($representante->matricula) ?>
                                <?php else: ?>
                                    <span class="representantes-sd">Sin matrícula</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((int) $representante->activo === 1): ?>
                                    <span class="representantes-estado representantes-estado-activo">
                                        <span class="representantes-estado-punto" aria-hidden="true"></span>
                                        Activo
                                    </span>
                                <?php else: ?>
                                    <span class="representantes-estado representantes-estado-inactivo">
                                        <span class="representantes-estado-punto" aria-hidden="true"></span>
                                        Inactivo
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="representantes-acciones-cell">
                                <a href="<?= site_url('/representantes/editar/' . (int) $representante->id) ?>"
                                   class="representantes-btn-accion"
                                   title="Editar representante técnico">
                                    <i class="bi bi-pencil" aria-hidden="true"></i>
                                    Editar
                                </a>
                                <?php if ((int) $representante->activo === 1): ?>
                                    <button type="button"
                                            class="representantes-btn-accion representantes-btn-desactivar"
                                            data-representante-id="<?= (int) $representante->id ?>"
                                            data-representante-nombre="<?= esc($representante->apellido . ', ' . $representante->nombre, 'attr') ?>"
                                            data-representante-activo="0"
                                            title="Desactivar representante técnico">
                                        <i class="bi bi-toggle-off" aria-hidden="true"></i>
                                        Desactivar
                                    </button>
                                <?php else: ?>
                                    <button type="button"
                                            class="representantes-btn-accion representantes-btn-activar"
                                            data-representante-id="<?= (int) $representante->id ?>"
                                            data-representante-nombre="<?= esc($representante->apellido . ', ' . $representante->nombre, 'attr') ?>"
                                            data-representante-activo="1"
                                            title="Reactivar representante técnico">
                                        <i class="bi bi-toggle-on" aria-hidden="true"></i>
                                        Reactivar
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

</div>

<!-- Modal: confirmación de activación / desactivación -->
<div class="modal-overlay" id="modalEstadoRepresentante" hidden aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalEstadoTitulo">
        <h2 class="modal-title" id="modalEstadoTitulo">
            <i class="bi bi-toggle-off" aria-hidden="true" id="modalEstadoIcono"></i>
            <span id="modalEstadoTituloTexto">Desactivar representante técnico</span>
        </h2>
        <p class="modal-message" id="modalEstadoMensaje"></p>
        <form method="post" action="<?= site_url('/representantes/estado') ?>" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="representante_id" id="estado_representante_id" value="">
            <input type="hidden" name="activo" id="estado_representante_activo" value="">
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" id="btnCancelarEstadoRepresentante">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-danger" id="btnConfirmarEstadoRepresentante">
                    <i class="bi bi-toggle-off" aria-hidden="true" id="btnConfirmarEstadoIcono"></i>
                    <span id="btnConfirmarEstadoTexto">Desactivar</span>
                </button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script src="<?= base_url('assets/js/pages/representantes.js') ?>"></script>
<?= $this->endSection() ?>
