<?= $this->extend('layouts/auth') ?>

<?= $this->section('styles') ?>
    <link rel="stylesheet" href="<?= base_url('assets/css/pages/mis-datos.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
$usuarioEmail = $usuario->email ?? null;
$avatarInitial = mb_strtoupper(mb_substr($usuario->nombre ?? '', 0, 1));
$avatarLast    = mb_strtoupper(mb_substr($usuario->apellido ?? '', 0, 1));
?>

<div class="mis-datos-page">

    <h1><?= esc($titulo) ?></h1>

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

    <div class="card datos-card">

        <!-- Avatar + encabezado -->
        <div class="datos-encabezado">
            <div class="avatar">
                <span class="avatar-iniciales"><?= esc($avatarInitial . $avatarLast) ?></span>
            </div>
            <div class="datos-encabezado-texto">
                <h2 class="datos-nombre-completo"><?= esc($usuario->nombre . ' ' . $usuario->apellido) ?></h2>
                <span class="datos-usuario-texto"><?= esc($usuario->usuario) ?></span>
            </div>
        </div>

        <!-- Lista de datos -->
        <div class="datos-lista" id="datosLista">

            <div class="dato-fila">
                <dt>Nombre</dt>
                <dd><?= esc($usuario->nombre) ?></dd>
            </div>

            <div class="dato-fila">
                <dt>Apellido</dt>
                <dd><?= esc($usuario->apellido) ?></dd>
            </div>

            <div class="dato-fila">
                <dt>Usuario</dt>
                <dd><?= esc($usuario->usuario) ?></dd>
            </div>

            <div class="dato-fila">
                <dt>Rol</dt>
                <dd><span class="badge badge-rol"><?= esc($rol_principal) ?></span></dd>
            </div>

            <!-- Correo electrónico -->
            <div class="dato-fila">
                <dt>Correo electrónico</dt>
                <dd>
                    <div class="dato-email-fila">
                        <?php if ($usuarioEmail): ?>
                            <span class="dato-valor" id="emailValor"><?= esc($usuarioEmail) ?></span>
                        <?php else: ?>
                            <span class="dato-sin-valor" id="emailValor">No ingresado</span>
                        <?php endif; ?>

                        <?php if ($puede_modificar_email): ?>
                            <button type="button"
                                    class="btn-accion-inline"
                                    id="btnEditarEmail"
                                    aria-label="Modificar correo electrónico">
                                <i class="bi bi-pencil"></i> Modificar
                            </button>
                        <?php endif; ?>
                    </div>
                </dd>
            </div>

            <!-- Contraseña -->
            <div class="dato-fila">
                <dt>Contraseña</dt>
                <dd>
                    <div class="dato-password-fila">
                        <span class="password-mask" id="passwordMask">&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</span>

                        <button type="button"
                                class="btn-accion-inline"
                                id="btnVerContrasena"
                                aria-label="Ver contraseña">
                            <i class="bi bi-eye" id="iconoVerificar"></i>
                        </button>

                        <span class="contrasena-verificada" id="passwordVerificado" hidden>
                            <i class="bi bi-check-circle-fill"></i> Verificada
                        </span>

                        <?php if ($puede_modificar_email): ?>
                            <button type="button"
                                    class="btn-accion-inline btn-cambiar-contrasena"
                                    id="btnCambiarContrasena"
                                    disabled
                                    aria-label="Cambiar contraseña">
                                <i class="bi bi-key"></i> Cambiar contraseña
                            </button>
                        <?php endif; ?>
                    </div>

                    <?php if (! $puede_modificar_email): ?>
                        <p class="nota-contrasena" id="notaContrasenaBottom">
                            Presione el ícono del ojo para verificar su contraseña actual
                            y habilitar el cambio.
                        </p>
                    <?php endif; ?>
                </dd>
            </div>

        </div>
        <!-- /datos-lista -->

        <!-- Formulario de edición de email (oculto inicialmente) -->
        <form id="formEditarEmail"
              method="post"
              action="<?= site_url('/mis-datos/email') ?>"
              class="datos-form-editar"
              hidden
              novalidate>
            <div class="form-group">
                <label for="email">Nuevo correo electrónico</label>
                <input type="email"
                       id="email"
                       name="email"
                       class="form-control"
                       value="<?= esc((string) ($usuarioEmail ?? '')) ?>"
                       maxlength="150"
                       autocomplete="email"
                       placeholder="correo@ejemplo.com">
                <span class="field-error" role="alert" aria-live="polite"></span>
            </div>
            <div class="datos-form-acciones">
                <button type="button" class="btn btn-secondary" id="btnCancelarEmail">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-lg"></i> Guardar
                </button>
            </div>
        </form>

        <!-- Acciones del card -->
        <div class="card-footer-acciones" id="accionesCard">
            <?php if (! $puede_modificar_email): ?>
                <button type="button"
                        class="btn btn-secondary btn-cambiar-contrasena"
                        id="btnCambiarContrasenaBottom"
                        disabled
                        aria-label="Cambiar contraseña">
                    <i class="bi bi-key"></i> Cambiar contraseña
                </button>
            <?php endif; ?>

            <a href="<?= esc($dashboard_url) ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>

    </div>
    <!-- /datos-card -->

</div>
<!-- /mis-datos-page -->

<!-- ================================================================
     MODAL — Verificar contraseña (ícono ojo)
     ================================================================ -->
<div class="modal-overlay" id="modalVerificar" hidden aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="verificarTitulo">
        <h2 class="modal-title" id="verificarTitulo">
            <i class="bi bi-shield-lock"></i> Ver contraseña
        </h2>
        <p class="modal-message">
            Para continuar debe ingresar su contraseña actual. La contraseña
            original no puede mostrarse porque se almacena como hash
            irreversiblemente encriptado.
        </p>

        <div class="modal-error" id="errorVerificar" role="alert" aria-live="polite" hidden></div>

        <form id="formVerificar" action="<?= site_url('/mis-datos/verificar-password') ?>" novalidate>
            <div class="form-group">
                <label for="verificar_password">Contraseña actual</label>
                <div class="input-icon-wrapper">
                    <input type="password"
                           id="verificar_password"
                           name="password"
                           class="form-control"
                           autocomplete="current-password"
                           placeholder="Ingrese su contraseña actual">
                    <button type="button"
                            class="input-icon-action toggle-modal-password"
                            aria-label="Mostrar contraseña">
                        <i class="bi bi-eye icon-show"></i>
                        <i class="bi bi-eye-slash icon-hide" style="display:none"></i>
                    </button>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" id="btnCancelarVerificar">
                    Volver
                </button>
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-lg"></i> Confirmar
                </button>
            </div>
        </form>

    </div>
</div>

<!-- ================================================================
     MODAL — Cambiar contraseña
     ================================================================ -->
<div class="modal-overlay" id="modalCambiar" hidden aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="cambiarTitulo">
        <h2 class="modal-title" id="cambiarTitulo">
            <i class="bi bi-key"></i> Cambiar contraseña
        </h2>
        <p class="modal-message">
            La contraseña debe tener al menos 9 caracteres, una letra
            mayúscula y un número.
        </p>

        <div class="modal-error" id="errorCambiar" role="alert" aria-live="polite" hidden></div>

        <form id="formCambiarContrasena"
              method="post"
              action="<?= site_url('/mis-datos/password') ?>"
              novalidate>

            <div class="form-group">
                <label for="nueva_contrasena">Nueva contraseña</label>
                <div class="input-icon-wrapper">
                    <input type="password"
                           id="nueva_contrasena"
                           name="nueva_contrasena"
                           class="form-control"
                           autocomplete="new-password"
                           required>
                    <button type="button"
                            class="input-icon-action toggle-modal-password"
                            aria-label="Mostrar contraseña">
                        <i class="bi bi-eye icon-show"></i>
                        <i class="bi bi-eye-slash icon-hide" style="display:none"></i>
                    </button>
                </div>
                <span class="field-error" role="alert" aria-live="polite"></span>
            </div>

            <div class="form-group">
                <label for="confirmar_contrasena">Confirmar nueva contraseña</label>
                <div class="input-icon-wrapper">
                    <input type="password"
                           id="confirmar_contrasena"
                           name="confirmar_contrasena"
                           class="form-control"
                           autocomplete="new-password"
                           required>
                    <button type="button"
                            class="input-icon-action toggle-modal-password"
                            aria-label="Mostrar contraseña">
                        <i class="bi bi-eye icon-show"></i>
                        <i class="bi bi-eye-slash icon-hide" style="display:none"></i>
                    </button>
                </div>
                <span class="field-error" role="alert" aria-live="polite"></span>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" id="btnCancelarCambiar">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-lg"></i> Guardar contraseña
                </button>
            </div>

        </form>
    </div>
</div>

<!-- Dato de estado para JS: reabrir modales/formulario en caso de error POST -->
<?php
$reabrirEmail   = session()->getFlashdata('reabrir_email');
$reabrirCambiar = session()->getFlashdata('reabrir_cambiar');
?>
<div id="estadoMisDatos"
     data-reabrir-email="<?= $reabrirEmail ? '1' : '0' ?>"
     data-reabrir-cambiar="<?= $reabrirCambiar ? '1' : '0' ?>"
     style="display:none"></div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script src="<?= base_url('assets/js/pages/mis-datos.js') ?>"></script>
<?= $this->endSection() ?>
