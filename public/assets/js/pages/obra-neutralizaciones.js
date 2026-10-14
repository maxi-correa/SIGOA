/* ===================================================================
   SIGOA — Página: Neutralizaciones de obra (BLOQUE 5)
   ===================================================================
   Gestiona el alta de una neutralización y el registro del reinicio.
   La validación de cliente es una ayuda: las mismas reglas se verifican
   en el servidor, que es la autoridad final.
   =================================================================== */

(function () {
    'use strict';

    var page = document.querySelector('.neutralizaciones-page');

    if (!page || page.dataset.puedeEditar !== '1') {
        return;
    }

    var obraFechaInicio = page.dataset.obraFechaInicio || '';
    var abiertaInicio   = page.dataset.abiertaInicio || '';

    function abrir(overlay) {
        overlay.hidden = false;
        overlay.setAttribute('aria-hidden', 'false');
    }

    function cerrar(overlay) {
        overlay.hidden = true;
        overlay.setAttribute('aria-hidden', 'true');
    }

    function mostrarError(input, contenedor, mensaje) {
        input.classList.add('is-invalid');
        contenedor.textContent = mensaje;
        contenedor.hidden = false;
    }

    function limpiarError(input, contenedor) {
        input.classList.remove('is-invalid');
        contenedor.textContent = '';
        contenedor.hidden = true;
    }

    /* --- Alta de neutralización --- */
    var modalNeutralizacion = document.getElementById('modalNeutralizacion');
    var formNeutralizacion  = document.getElementById('formNeutralizacion');
    var inputFechaInicio    = document.getElementById('fecha_inicio');
    var errorFechaInicio    = document.getElementById('error-fecha-inicio');
    var btnNueva            = document.getElementById('btnNuevaNeutralizacion');

    if (modalNeutralizacion) {
        if (btnNueva) {
            btnNueva.addEventListener('click', function () {
                formNeutralizacion.reset();
                limpiarError(inputFechaInicio, errorFechaInicio);
                abrir(modalNeutralizacion);
                inputFechaInicio.focus();
            });
        }

        document.getElementById('btnCancelarNeutralizacion').addEventListener('click', function () {
            cerrar(modalNeutralizacion);
        });

        formNeutralizacion.addEventListener('submit', function (evento) {
            limpiarError(inputFechaInicio, errorFechaInicio);

            if (inputFechaInicio.value === '') {
                evento.preventDefault();
                mostrarError(inputFechaInicio, errorFechaInicio, 'Ingrese la fecha de neutralización.');
                inputFechaInicio.focus();
                return;
            }

            if (obraFechaInicio && inputFechaInicio.value < obraFechaInicio) {
                evento.preventDefault();
                mostrarError(inputFechaInicio, errorFechaInicio, 'La fecha no puede ser anterior al inicio de la obra.');
                inputFechaInicio.focus();
            }
        });
    }

    /* --- Registro del reinicio --- */
    var modalReiniciar     = document.getElementById('modalReiniciar');
    var formReiniciar      = document.getElementById('formReiniciar');
    var inputFechaReinicio = document.getElementById('fecha_reinicio');
    var errorFechaReinicio = document.getElementById('error-fecha-reinicio');

    if (modalReiniciar) {
        page.querySelectorAll('[data-accion="reiniciar"]').forEach(function (boton) {
            boton.addEventListener('click', function () {
                formReiniciar.reset();
                limpiarError(inputFechaReinicio, errorFechaReinicio);
                inputFechaReinicio.min = abiertaInicio;
                abrir(modalReiniciar);
                inputFechaReinicio.focus();
            });
        });

        document.getElementById('btnCancelarReiniciar').addEventListener('click', function () {
            cerrar(modalReiniciar);
        });

        formReiniciar.addEventListener('submit', function (evento) {
            limpiarError(inputFechaReinicio, errorFechaReinicio);

            if (inputFechaReinicio.value === '') {
                evento.preventDefault();
                mostrarError(inputFechaReinicio, errorFechaReinicio, 'Ingrese la fecha de reinicio.');
                inputFechaReinicio.focus();
                return;
            }

            if (abiertaInicio && inputFechaReinicio.value < abiertaInicio) {
                evento.preventDefault();
                mostrarError(inputFechaReinicio, errorFechaReinicio, 'La fecha no puede ser anterior a la neutralización.');
                inputFechaReinicio.focus();
            }
        });
    }

    /* --- Cierre común de modales --- */
    var overlays = [modalNeutralizacion, modalReiniciar].filter(Boolean);

    overlays.forEach(function (overlay) {
        overlay.addEventListener('click', function (evento) {
            if (evento.target === overlay) {
                cerrar(overlay);
            }
        });
    });

    document.addEventListener('keydown', function (evento) {
        if (evento.key !== 'Escape') {
            return;
        }

        overlays.forEach(function (overlay) {
            if (! overlay.hidden) {
                cerrar(overlay);
            }
        });
    });

    /* Modal abierto por errores devueltos del servidor. */
    overlays.forEach(function (overlay) {
        if (! overlay.hidden) {
            abrir(overlay);
        }
    });
})();
