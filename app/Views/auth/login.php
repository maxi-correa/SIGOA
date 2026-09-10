<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($titulo) ?> — SIGOA</title>

    <!-- Tipografía Inter (local sería ideal; CDN como respaldo) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap Icons (local sería ideal; CDN como respaldo) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

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
    <script src="<?= base_url('assets/js/login.js') ?>"></script>

    <!-- Validación frontend -->
    <script>
    (function () {
        'use strict';

        var form = document.getElementById('loginForm');
        var usuarioInput = document.getElementById('usuario');
        var passwordInput = document.getElementById('password');
        var errorUsuario = document.getElementById('error-usuario');
        var errorPassword = document.getElementById('error-password');

        function clearErrors() {
            errorUsuario.textContent = '';
            errorPassword.textContent = '';
            usuarioInput.classList.remove('is-invalid');
            passwordInput.classList.remove('is-invalid');
        }

        function showError(input, errorEl, message) {
            errorEl.textContent = message;
            input.classList.add('is-invalid');
        }

        function validatePasswordRules(pw) {
            if (pw.length < 9) {
                return 'La contraseña debe tener al menos 9 caracteres.';
            }
            if (!/[A-Z]/.test(pw)) {
                return 'La contraseña debe contener al menos una letra mayúscula.';
            }
            if (!/[0-9]/.test(pw)) {
                return 'La contraseña debe contener al menos un número.';
            }
            return '';
        }

        form.addEventListener('submit', function (e) {
            clearErrors();

            var usuario = usuarioInput.value.trim();
            var password = passwordInput.value;
            var hasError = false;

            /* 1. Campos obligatorios */
            if (usuario === '' && password === '') {
                showError(usuarioInput, errorUsuario, 'Debe completar el usuario y la contraseña.');
                hasError = true;
            } else if (usuario === '') {
                showError(usuarioInput, errorUsuario, 'Falta ingresar el usuario.');
                hasError = true;
            } else if (password === '') {
                showError(passwordInput, errorPassword, 'Falta completar la contraseña.');
                hasError = true;
            }

            if (hasError) {
                e.preventDefault();
                return;
            }

            /* 2. Reglas de contraseña */
            var ruleError = validatePasswordRules(password);
            if (ruleError) {
                showError(passwordInput, errorPassword, ruleError);
                e.preventDefault();
                return;
            }

            /* 3. Si pasa todo, el formulario se envía al servidor */
        });

        /* Limpiar error individual al escribir */
        usuarioInput.addEventListener('input', function () {
            if (errorUsuario.textContent) {
                errorUsuario.textContent = '';
                usuarioInput.classList.remove('is-invalid');
            }
        });

        passwordInput.addEventListener('input', function () {
            if (errorPassword.textContent) {
                errorPassword.textContent = '';
                passwordInput.classList.remove('is-invalid');
            }
        });
    })();
    </script>
</body>
</html>
