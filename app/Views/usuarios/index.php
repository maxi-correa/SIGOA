<?= $this->extend('layouts/auth') ?>

<?= $this->section('styles') ?>
    <link rel="stylesheet" href="<?= base_url('assets/css/pages/usuarios.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

    <h1><?= esc($titulo) ?></h1>

    <p class="usuarios-intro">
        Usuarios registrados en SIGOA y sus roles asignados.
    </p>

    <?php if (empty($usuarios)): ?>
        <p class="usuarios-email-vacio">No hay usuarios registrados.</p>
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
                                    <span class="usuarios-chip-propio" aria-label="Es su propio usuario">—</span>
                                <?php else: ?>
                                    <span class="usuarios-chip-pendiente"
                                          title="Las acciones estarán disponibles próximamente">
                                        <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                                        En preparación
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

<?= $this->endSection() ?>
