<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($titulo) ?> — SIGOA</title>

    <!-- Tipografía Inter (local) -->
    <link rel="stylesheet" href="<?= base_url('assets/fonts/inter/fonts.css') ?>">

    <!-- Bootstrap Icons 1.11.3 (local) -->
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">

    <!-- Estilos SIGOA -->
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/components/forms.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/components/buttons.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/components/alerts.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/pages/login.css') ?>">
</head>
<body>
    <main class="login-page">
        <div class="login-card">

            <!-- Encabezado -->
            <div class="login-header">
                <h1 class="login-brand">SIGOA</h1>
                <p class="login-subtitle">Sistema de Inspección de Obras de Arquitectura</p>
            </div>

            <!-- Error de autenticación (credenciales incorrectas) -->
            <?php if (session()->getFlashdata('error')): ?>
                <div class="alert alert-danger" role="alert">
                    <span class="alert-icon"><i class="bi bi-exclamation-circle"></i></span>
                    <span><?= esc(session()->getFlashdata('error')) ?></span>
                </div>
            <?php endif; ?>

            <!-- Formulario -->
            <form method="post" action="<?= site_url('/login') ?>" class="login-form" novalidate id="loginForm">

                <!-- Usuario -->
                <div class="form-group">
                    <label for="usuario">Usuario</label>
                    <input
                        type="text"
                        id="usuario"
                        name="usuario"
                        class="form-control"
                        value="<?= esc(old('usuario')) ?>"
                        placeholder="Ingrese su usuario"
                        autocomplete="username"
                        autofocus
                    >
                    <span class="field-error" id="error-usuario" role="alert" aria-live="polite"></span>
                </div>

                <!-- Contraseña -->
                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <div class="input-icon-wrapper">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-control"
                            placeholder="Ingrese su contraseña"
                            autocomplete="current-password"
                        >
                        <button
                            type="button"
                            class="input-icon-action"
                            aria-label="Mostrar contraseña"
                            id="togglePassword"
                        >
                            <i class="bi bi-eye icon-show"></i>
                            <i class="bi bi-eye-slash icon-hide" style="display:none"></i>
                        </button>
                    </div>
                    <span class="field-error" id="error-password" role="alert" aria-live="polite"></span>
                </div>

                <!-- Recordar usuario -->
                <div class="form-group login-remember">
                    <label class="form-check">
                        <input type="checkbox" id="remember_user" name="remember_user" value="1">
                        <span class="form-check-label">Recordar usuario</span>
                    </label>
                </div>

                <!-- Botón ingresar -->
                <button type="submit" class="btn btn-success" id="btnLogin">
                    <i class="bi bi-box-arrow-in-right"></i>
                    Ingresar
                </button>

            </form>

        </div>
    </main>

    <!-- JavaScript login -->
    <script src="<?= base_url('assets/js/pages/login.js') ?>"></script>
</body>
</html>
