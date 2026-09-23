/**
 * SIGOA — Ficha de obra
 *
 * Máscara de fecha (dd/mm/aaaa), restricción numérica del plazo y
 * apertura/cierre de los modales de cambio de inspector y de
 * representante técnico.
 */
(function () {
    'use strict';

    /* --- Máscara de fecha dd/mm/aaaa --- */
    function aplicarMascaraFecha(input) {
        if (!input) return;

        input.addEventListener('input', function () {
            var digitos = input.value.replace(/\D/g, '').slice(0, 8);
            var partes = [];

            if (digitos.length > 0) {
                partes.push(digitos.slice(0, 2));
            }
            if (digitos.length > 2) {
                partes.push(digitos.slice(2, 4));
            }
            if (digitos.length > 4) {
                partes.push(digitos.slice(4, 8));
            }

            input.value = partes.join('/');
        });
    }

    aplicarMascaraFecha(document.getElementById('fecha_inicio'));
    aplicarMascaraFecha(document.getElementById('fecha_cambio'));
    aplicarMascaraFecha(document.getElementById('fecha_cambio_representante'));

    /* --- Solo dígitos en el valor del plazo --- */
    var inputPlazo = document.getElementById('plazo_valor');

    if (inputPlazo) {
        inputPlazo.addEventListener('input', function () {
            inputPlazo.value = inputPlazo.value.replace(/\D/g, '').slice(0, 6);
        });
    }

    /* --- Modales de cambio de inspector y de representante técnico --- */
    function configurarModal(opciones) {
        var overlay = document.getElementById(opciones.overlay);
        if (!overlay) return;

        var btnAbrir    = opciones.abrir ? document.getElementById(opciones.abrir) : null;
        var btnCancelar = opciones.cancelar ? document.getElementById(opciones.cancelar) : null;
        var campoFoco   = opciones.foco ? document.getElementById(opciones.foco) : null;

        function abrir() {
            overlay.removeAttribute('hidden');
            overlay.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';

            if (campoFoco) {
                campoFoco.focus();
            }
        }

        function cerrar() {
            overlay.setAttribute('hidden', '');
            overlay.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }

        if (btnAbrir) {
            btnAbrir.addEventListener('click', abrir);
        }

        if (btnCancelar) {
            btnCancelar.addEventListener('click', cerrar);
        }

        document.addEventListener('keydown', function (e) {
            if ((e.key === 'Escape' || e.keyCode === 27) && !overlay.hasAttribute('hidden')) {
                cerrar();
            }
        });

        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) {
                cerrar();
            }
        });

        /* El modal pudo renderizarse abierto por un error del backend */
        if (!overlay.hasAttribute('hidden')) {
            document.body.style.overflow = 'hidden';
        }
    }

    configurarModal({
        overlay:  'modalInspector',
        abrir:    'btnCambioInspector',
        cancelar: 'btnCancelarInspector',
        foco:     'fecha_cambio'
    });

    configurarModal({
        overlay:  'modalRepresentante',
        abrir:    'btnCambioRepresentante',
        cancelar: 'btnCancelarRepresentante',
        foco:     'fecha_cambio_representante'
    });

})();
