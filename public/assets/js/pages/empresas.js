/**
 * SIGOA — Empresas
 *
 * Gestión del logo de empresa desde el listado:
 *   - Selección de archivo: envía el formulario automáticamente al elegir
 *     una imagen (carga inicial o reemplazo).
 *   - Eliminación: modal de confirmación que envía el formulario POST.
 */
(function () {
    'use strict';

    var modalEliminar = document.getElementById('modalEliminarLogo');
    var inputEmpresa  = document.getElementById('eliminar_empresa_id');
    var btnCancelar   = document.getElementById('btnCancelarEliminarLogo');

    /* ================================================================
       Subir / reemplazar logo — auto submit al elegir archivo
       ================================================================ */
    var inputsLogo = document.querySelectorAll('.empresa-logo-input');

    for (var i = 0; i < inputsLogo.length; i++) {
        (function (input) {
            input.addEventListener('change', function () {
                var form = input.closest('.empresa-logo-form');

                if (form && input.files && input.files.length > 0) {
                    form.submit();
                }
            });
        })(inputsLogo[i]);
    }

    /* ================================================================
       Eliminar logo — Modal de confirmación
       ================================================================ */
    function abrirModalEliminar(empresaId) {
        if (!modalEliminar) return;

        inputEmpresa.value = String(empresaId);
        modalEliminar.removeAttribute('hidden');
        modalEliminar.removeAttribute('aria-hidden');
        document.body.style.overflow = 'hidden';

        var btnConfirmar = modalEliminar.querySelector('.btn.btn-danger');
        if (btnConfirmar) {
            btnConfirmar.focus();
        }
    }

    function cerrarModalEliminar() {
        if (!modalEliminar) return;

        modalEliminar.setAttribute('hidden', '');
        modalEliminar.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    if (btnCancelar) {
        btnCancelar.addEventListener('click', cerrarModalEliminar);
    }

    document.addEventListener('keydown', function (e) {
        if ((e.key === 'Escape' || e.keyCode === 27) && modalEliminar && !modalEliminar.hasAttribute('hidden')) {
            cerrarModalEliminar();
        }
    });

    if (modalEliminar) {
        modalEliminar.addEventListener('click', function (e) {
            if (e.target === modalEliminar) {
                cerrarModalEliminar();
            }
        });
    }

    /* Abrir el modal desde el botón [papelera] de la fila */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('.empresa-logo-eliminar') : null;

        if (btn) {
            var empresaId = parseInt(btn.getAttribute('data-empresa-id'), 10);
            if (!isNaN(empresaId)) {
                abrirModalEliminar(empresaId);
            }
        }
    });

})();