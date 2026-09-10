/**
 * SIGOA — Login
 * Toggle contraseña, recordar usuario y validación frontend
 */
(function () {
    'use strict';

    /* ----------------------------------------------------------------
       Toggle Mostrar / Ocultar contraseña
       ---------------------------------------------------------------- */
    function initPasswordToggle() {
        var wrapper = document.querySelector('.input-icon-wrapper');
        if (!wrapper) return;

        var input = wrapper.querySelector('input');
        var btn = wrapper.querySelector('.input-icon-action');
        if (!input || !btn) return;

        var iconShow = btn.querySelector('.icon-show');
        var iconHide = btn.querySelector('.icon-hide');

        btn.addEventListener('click', function () {
            var isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';

            if (iconShow) iconShow.style.display = isPassword ? 'none' : '';
            if (iconHide) iconHide.style.display = isPassword ? '' : 'none';

            btn.setAttribute('aria-label',
                isPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'
            );

            input.focus();
        });
    }

    /* ----------------------------------------------------------------
       Recordar usuario — cookie simple
       ---------------------------------------------------------------- */
    var COOKIE_NAME = 'sigoa_remember_user';
    var COOKIE_DAYS = 30;

    function getCookie(name) {
        var match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : '';
    }

    function setCookie(name, value, days) {
        var d = new Date();
        d.setTime(d.getTime() + days * 86400000);
        document.cookie = name + '=' + encodeURIComponent(value) +
            '; expires=' + d.toUTCString() + '; path=/; SameSite=Lax';
    }

    function deleteCookie(name) {
        document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; SameSite=Lax';
    }

    function initRememberUser() {
        var usuarioInput = document.getElementById('usuario');
        var rememberCheck = document.getElementById('remember_user');
        if (!usuarioInput || !rememberCheck) return;

        /* Al cargar: si hay cookie, prellenar usuario y marcar checkbox */
        var saved = getCookie(COOKIE_NAME);
        if (saved) {
            usuarioInput.value = saved;
            rememberCheck.checked = true;
        }

        /* Al enviar el formulario */
        var form = usuarioInput.closest('form');
        if (!form) return;

        form.addEventListener('submit', function () {
            if (rememberCheck.checked) {
                setCookie(COOKIE_NAME, usuarioInput.value, COOKIE_DAYS);
            } else {
                deleteCookie(COOKIE_NAME);
            }
        });
    }

    /* ----------------------------------------------------------------
       Validación frontend
       ---------------------------------------------------------------- */
    function initFormValidation() {
        var form = document.getElementById('loginForm');
        var usuarioInput = document.getElementById('usuario');
        var passwordInput = document.getElementById('password');
        var errorUsuario = document.getElementById('error-usuario');
        var errorPassword = document.getElementById('error-password');
        if (!form || !usuarioInput || !passwordInput || !errorUsuario || !errorPassword) return;

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
    }

    /* ----------------------------------------------------------------
       Inicialización
       ---------------------------------------------------------------- */
    document.addEventListener('DOMContentLoaded', function () {
        initPasswordToggle();
        initRememberUser();
        initFormValidation();
    });
})();
