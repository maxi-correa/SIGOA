/**
 * SIGOA — Dashboard administrativo / Obras
 * Apertura y cierre del modal "Agregar obra" (alta inicial).
 */
(function () {
    'use strict';

    var overlay     = document.getElementById('modalAgregarObra');
    var btnAbrir    = document.getElementById('btnAgregarObra');
    var btnCancelar = document.getElementById('btnCancelarAgregarObra');

    function abrirModal() {
        if (!overlay) return;

        overlay.removeAttribute('hidden');
        overlay.removeAttribute('aria-hidden');
        document.body.style.overflow = 'hidden';

        var primerCampo = overlay.querySelector('#expediente_municipal');
        if (primerCampo) {
            primerCampo.focus();
        }
    }

    function cerrarModal() {
        if (!overlay) return;

        overlay.setAttribute('hidden', '');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    if (btnAbrir) {
        btnAbrir.addEventListener('click', abrirModal);
    }

    if (btnCancelar) {
        btnCancelar.addEventListener('click', cerrarModal);
    }

    /* Cerrar con tecla Escape */
    document.addEventListener('keydown', function (e) {
        if ((e.key === 'Escape' || e.keyCode === 27) && overlay && !overlay.hasAttribute('hidden')) {
            cerrarModal();
        }
    });

    /* Cerrar al hacer clic fuera del modal */
    if (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) {
                cerrarModal();
            }
        });
    }

    /* Reabrir si el backend lo indica tras un error de validación */
    document.addEventListener('DOMContentLoaded', function () {
        if (overlay && overlay.dataset.reabrir === '1') {
            abrirModal();
        }
    });

})();