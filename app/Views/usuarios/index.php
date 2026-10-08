<?= $this->extend('layouts/auth') ?>

<?= $this->section('styles') ?>
    <link rel="stylesheet" href="<?= base_url('assets/css/pages/usuarios.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
$erroresModal  = session()->getFlashdata('errores_usuario');
$reabrirModal  = (string) (session()->getFlashdata('reabrir_modal_usuario') ?? '');

/* En reapertura por error, identifica al usuario que se estaba editando */
$usuarioEdicion = null;

if ($reabrirModal === 'edicion') {
    $usuarioEdicionId = (int) old('usuario_id', 0);

    foreach ($usuarios as $usuarioLista) {
        if ((int) $usuarioLista->id === $usuarioEdicionId) {
            $usuarioEdicion = $usuarioLista;
            break;
        }
    }
}

$mensajeEdicion = $usuarioEdicion !== null
    ? 'Modifique los datos personales de ' . $usuarioEdicion->nombre . ' ' . $usuarioEdicion->apellido . '. El usuario, el rol y la contraseña no se modifican desde aquí.'
    : 'Modifique los datos personales del usuario seleccionado. El usuario, el rol y la contraseña no se modifican desde aquí.';
?>

<div class="usuarios-page">

    <header class="usuarios-header">
        <div class="usuarios-header-texto">
            <h1><?= esc($titulo) ?></h1>
            <p class="usuarios-subtitulo">
                Usuarios registrados en SIGOA y sus roles asignados.
            </p>
        </div>

        <?php if ($puede_agregar): ?>
            <button type="button"
                    class="btn btn-success usuarios-btn-alta"
                    id="btnAgregarUsuario"
                    aria-haspopup="dialog"
                    aria-controls="modalAltaUsuario">
                <i class="bi bi-plus-lg" aria-hidden="true"></i>
                Agregar usuario
            </button>
        <?php endif; ?>
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

    <?php if (empty($usuarios)): ?>
        <p class="usuarios-vacio">No hay usuarios registrados.</p>
    <?php else: ?>

        <div class="sigoa-table-wrap">
            <table class="sigoa-table usuarios-tabla">
                <thead>
                    <tr>
                        <th scope="col">Nombre</th>
                        <th scope="col">Apellido</th>
                        <th scope="col">Usuario</th>
                        <th scope="col">Email</th>
                        <th scope="col">Rol</th>
                        <th scope="col">Estado</th>
                        <th scope="col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $usuario): ?>
                        <tr>
                            <td><?= esc($usuario->nombre) ?></td>
                            <td><?= esc($usuario->apellido) ?></td>
                            <td><?= esc($usuario->usuario) ?></td>
                            <td>
                                <?php if ($usuario->email): ?>
                                    <?= esc($usuario->email) ?>
                                <?php else: ?>
                                    <span class="usuarios-email-vacio">No ingresado</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="usuarios-rol-cell">
                                    <?php foreach ($usuario->roles as $rol): ?>
                                        <span class="badge badge-rol"><?= esc($rol) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td>
                                <span class="usuarios-estado <?= $usuario->activo ? 'usuarios-estado-activo' : 'usuarios-estado-inactivo' ?>">
                                    <span class="usuarios-estado-dot" aria-hidden="true"></span>
                                    <?= $usuario->activo ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </td>
                            <td class="usuarios-acciones-cell">
                                <?php if ((int) $usuario->id === (int) $user_id): ?>
                                    <a href="<?= site_url('/mis-datos') ?>"
                                       class="usuarios-btn-accion"
                                       title="Modificar sus datos personales">
                                        <i class="bi bi-person" aria-hidden="true"></i>
                                        Mis Datos
                                    </a>
                                <?php else: ?>
                                    <button type="button"
                                            class="usuarios-btn-accion"
                                            data-accion="editar"
                                            data-id="<?= (int) $usuario->id ?>"
                                            data-nombre="<?= esc($usuario->nombre, 'attr') ?>"
                                            data-apellido="<?= esc($usuario->apellido, 'attr') ?>"
                                            data-email="<?= esc((string) ($usuario->email ?? ''), 'attr') ?>"
                                            data-activo="<?= $usuario->activo ? '1' : '0' ?>"
                                            aria-haspopup="dialog"
                                            aria-controls="modalEditarUsuario"
                                            title="Editar usuario">
                                        <i class="bi bi-pencil" aria-hidden="true"></i>
                                        Editar
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

<!-- ================================================================
     MODAL — Editar usuario (nombre, apellido, email, estado)
     ================================================================ -->
<div class="modal-overlay" id="modalEditarUsuario" hidden aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalEditarTitulo">
        <h2 class="modal-title" id="modalEditarTitulo">
            <i class="bi bi-pencil" aria-hidden="true"></i> Editar usuario
        </h2>
        <p class="modal-message" id="modalEditarMensaje"><?= esc($mensajeEdicion) ?></p>

        <div class="modal-error" role="alert" aria-live="polite" <?= $erroresModal && $reabrirModal === 'edicion' ? '' : 'hidden' ?>>
            <?php if (is_array($erroresModal) && $reabrirModal === 'edicion'): ?>
                <?php foreach ($erroresModal as $errorUsuario): ?>
                    <div><?= esc($errorUsuario) ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <form id="formEditarUsuario"
              method="post"
              action="<?= site_url('/usuarios/actualizar') ?>"
              novalidate>
            <?= csrf_field('csrf_editar_usuario') ?>

            <input type="hidden" name="usuario_id" id="editar_usuario_id" value="<?= esc(old('usuario_id', '')) ?>">

            <div class="form-group">
                <label for="editar_nombre">
                    Nombre <span class="usuarios-requerido" aria-hidden="true">*</span>
                </label>
                <input type="text"
                       id="editar_nombre"
                       name="nombre"
                       class="form-control"
                       maxlength="100"
                       autocomplete="off"
                       value="<?= esc(old('nombre', '')) ?>">
                <span class="field-error" role="alert" aria-live="polite"></span>
            </div>

            <div class="form-group">
                <label for="editar_apellido">
                    Apellido <span class="usuarios-requerido" aria-hidden="true">*</span>
                </label>
                <input type="text"
                       id="editar_apellido"
                       name="apellido"
                       class="form-control"
                       maxlength="100"
                       autocomplete="off"
                       value="<?= esc(old('apellido', '')) ?>">
                <span class="field-error" role="alert" aria-live="polite"></span>
            </div>

            <div class="form-group">
                <label for="editar_email">Correo electrónico</label>
                <input type="email"
                       id="editar_email"
                       name="email"
                       class="form-control"
                       maxlength="150"
                       autocomplete="off"
                       placeholder="correo@ejemplo.com (opcional)"
                       value="<?= esc(old('email', '')) ?>">
                <span class="field-error" role="alert" aria-live="polite"></span>
            </div>

            <div class="form-group">
                <label for="editar_activo">Estado</label>
                <select id="editar_activo" name="activo" class="form-control">
                    <option value="1" <?= (string) old('activo', '1') === '1' ? 'selected' : '' ?>>Activo</option>
                    <option value="0" <?= (string) old('activo', '1') === '0' ? 'selected' : '' ?>>Inactivo</option>
                </select>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" id="btnCancelarEditarUsuario">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-lg" aria-hidden="true"></i> Guardar cambios
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================================================================
     MODAL — Agregar usuario (SUPERADMINISTRADOR y ADMINISTRADOR)
     ================================================================ -->
<?php if ($puede_agregar): ?>
<div class="modal-overlay" id="modalAltaUsuario" hidden aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalAltaTitulo">
        <h2 class="modal-title" id="modalAltaTitulo">
            <i class="bi bi-person-plus" aria-hidden="true"></i> Agregar usuario
        </h2>
        <p class="modal-message">
            Registre un nuevo usuario y asígále un rol. El usuario nace
            activo y su contraseña se almacena cifrada.
        </p>

        <div class="modal-error" role="alert" aria-live="polite" <?= $erroresModal && $reabrirModal === 'alta' ? '' : 'hidden' ?>>
            <?php if (is_array($erroresModal) && $reabrirModal === 'alta'): ?>
                <?php foreach ($erroresModal as $errorUsuario): ?>
                    <div><?= esc($errorUsuario) ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <form id="formAltaUsuario"
              method="post"
              action="<?= site_url('/usuarios/crear') ?>"
              novalidate>
            <?= csrf_field('csrf_alta_usuario') ?>

            <div class="form-group">
                <label for="alta_nombre">
                    Nombre <span class="usuarios-requerido" aria-hidden="true">*</span>
                </label>
                <input type="text"
                       id="alta_nombre"
                       name="nombre"
                       class="form-control"
                       maxlength="100"
                       autocomplete="off"
                       value="<?= esc(old('nombre', '')) ?>">
                <span class="field-error" role="alert" aria-live="polite"></span>
            </div>

            <div class="form-group">
                <label for="alta_apellido">
                    Apellido <span class="usuarios-requerido" aria-hidden="true">*</span>
                </label>
                <input type="text"
                       id="alta_apellido"
                       name="apellido"
                       class="form-control"
                       maxlength="100"
                       autocomplete="off"
                       value="<?= esc(old('apellido', '')) ?>">
                <span class="field-error" role="alert" aria-live="polite"></span>
            </div>

            <div class="form-group">
                <label for="alta_usuario">
                    Usuario <span class="usuarios-requerido" aria-hidden="true">*</span>
                </label>
                <input type="text"
                       id="alta_usuario"
                       name="usuario"
                       class="form-control"
                       maxlength="100"
                       autocomplete="off"
                       placeholder="Ej.: juan.perez"
                       value="<?= esc(old('usuario', '')) ?>">
                <span class="field-error" role="alert" aria-live="polite"></span>
            </div>

            <div class="form-group">
                <label for="alta_email">Correo electrónico</label>
                <input type="email"
                       id="alta_email"
                       name="email"
                       class="form-control"
                       maxlength="150"
                       autocomplete="off"
                       placeholder="correo@ejemplo.com (opcional)"
                       value="<?= esc(old('email', '')) ?>">
                <span class="field-error" role="alert" aria-live="polite"></span>
            </div>

            <div class="form-group">
                <label for="alta_rol_id">
                    Rol <span class="usuarios-requerido" aria-hidden="true">*</span>
                </label>
                <select id="alta_rol_id" name="rol_id" class="form-control">
                    <option value="">Seleccione un rol</option>
                    <?php foreach ($roles_disponibles as $rolDisponible): ?>
                        <option value="<?= (int) $rolDisponible->id ?>"
                            <?= (string) old('rol_id', '') !== '' && (int) old('rol_id') === (int) $rolDisponible->id ? 'selected' : '' ?>>
                            <?= esc($rolDisponible->nombre) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="field-error" role="alert" aria-live="polite"></span>
            </div>

            <div class="form-group">
                <label for="alta_password">
                    Contraseña <span class="usuarios-requerido" aria-hidden="true">*</span>
                </label>
                <div class="input-icon-wrapper">
                    <input type="password"
                           id="alta_password"
                           name="password"
                           class="form-control"
                           autocomplete="new-password"
                           placeholder="Mínimo 9 caracteres">
                    <button type="button"
                            class="input-icon-action toggle-usuario-password"
                            aria-label="Mostrar contraseña">
                        <i class="bi bi-eye icon-show"></i>
                        <i class="bi bi-eye-slash icon-hide" style="display:none"></i>
                    </button>
                </div>
                <span class="usuarios-nota-contrasena">
                    Mínimo 9 caracteres, una letra mayúscula y un número.
                </span>
                <span class="field-error" role="alert" aria-live="polite"></span>
            </div>

            <div class="form-group">
                <label for="alta_confirmar_password">
                    Repetir contraseña <span class="usuarios-requerido" aria-hidden="true">*</span>
                </label>
                <div class="input-icon-wrapper">
                    <input type="password"
                           id="alta_confirmar_password"
                           name="confirmar_password"
                           class="form-control"
                           autocomplete="new-password"
                           placeholder="Repita la contraseña">
                    <button type="button"
                            class="input-icon-action toggle-usuario-password"
                            aria-label="Mostrar contraseña">
                        <i class="bi bi-eye icon-show"></i>
                        <i class="bi bi-eye-slash icon-hide" style="display:none"></i>
                    </button>
                </div>
                <span class="field-error" role="alert" aria-live="polite"></span>
            </div>

            <!-- Estado — impuesto por el sistema en el alta -->
            <div class="form-group">
                <label for="alta_estado">Estado</label>
                <input type="text"
                       id="alta_estado"
                       class="form-control usuarios-estado-preview"
                       value="Activo"
                       disabled>
                <span class="usuarios-nota-contrasena">
                    Valor asignado automáticamente al crear el usuario.
                </span>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" id="btnCancelarAltaUsuario">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-lg" aria-hidden="true"></i> Crear usuario
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Dato de estado para JS: reabrir modales en caso de error POST -->
<div id="estadoUsuarios"
     data-reabrir="<?= esc($reabrirModal) ?>"
     style="display:none"></div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script src="<?= base_url('assets/js/pages/usuarios.js') ?>"></script>
<?= $this->endSection() ?>
