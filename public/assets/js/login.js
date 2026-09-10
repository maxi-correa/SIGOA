/**
 * SIGOA — Login
 * Toggle contraseña y gestión de "Recordar usuario"
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
       Inicialización
       ---------------------------------------------------------------- */
    document.addEventListener('DOMContentLoaded', function () {
        initPasswordToggle();
        initRememberUser();
    });
})();
