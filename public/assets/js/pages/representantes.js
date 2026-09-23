/**
 * SIGOA — Representantes técnicos
 *
 * Modal de confirmación para activar o desactivar un representante técnico.
 * La operación envía un formulario POST con el ID del representante y el
 * estado destino (1 = activo, 0 = inactivo).
 */
(function () {
    'use strict';

    var modal            = document.getElementById('modalEstadoRepresentante');
    var inputId          = document.getElementById('estado_representante_id');
    var inputActivo      = document.getElementById('estado_representante_activo');
    var spanMensaje      = document.getElementById('modalEstadoMensaje');
    var tituloTexto      = document.getElementById('modalEstadoTituloTexto');
    var modalIcono       = document.getElementById('modalEstadoIcono');
    var btnCancelar      = document.getElementById('btnCancelarEstadoRepresentante');
    var btnConfirmar     = document.getElementById('btnConfirmarEstadoRepresentante');
    var btnConfirmarIcono = document.getElementById('btnConfirmarEstadoIcono');
    var btnConfirmarTexto = document.getElementById('btnConfirmarEstadoTexto');

    function abrirModal(id, nombre, activar) {
        if (!modal) return;

        if (inputId) {
            inputId.value = String(id);
        }

        if (inputActivo) {
            inputActivo.value = activar ? '1' : '0';
        }

        if (tituloTexto) {
            tituloTexto.textContent = activar
                ? 'Reactivar representante técnico'
                : 'Desactivar representante técnico';
        }

        if (modalIcono) {
            modalIcono.className = activar ? 'bi bi-toggle-on' : 'bi bi-toggle-off';
        }

        if (spanMensaje) {
            spanMensaje.textContent = activar
                ? '¿Reactivar a ' + (nombre || '') + '? Volverá a estar disponible para nuevas asignaciones en obras.'
                : '¿Desactivar a ' + (nombre || '') + '? No se ofrecerá para nuevas asignaciones, pero conserva su historial en las obras.';
        }

        if (btnConfirmar) {
            btnConfirmar.className = activar ? 'btn btn-success' : 'btn btn-danger';
        }

        if (btnConfirmarIcono) {
            btnConfirmarIcono.className = activar ? 'bi bi-toggle-on' : 'bi bi-toggle-off';
        }

        if (btnConfirmarTexto) {
            btnConfirmarTexto.textContent = activar ? 'Reactivar' : 'Desactivar';
        }

        modal.removeAttribute('hidden');
        modal.removeAttribute('aria-hidden');
        document.body.style.overflow = 'hidden';

        if (btnConfirmar) {
            btnConfirmar.focus();
        }
    }

    function cerrarModal() {
        if (!modal) return;

        modal.setAttribute('hidden', '');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    if (btnCancelar) {
        btnCancelar.addEventListener('click', cerrarModal);
    }

    document.addEventListener('keydown', function (e) {
        if ((e.key === 'Escape' || e.keyCode === 27) && modal && !modal.hasAttribute('hidden')) {
            cerrarModal();
        }
    });

    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                cerrarModal();
            }
        });
    }

    /* Abrir el modal desde el botón de estado de la fila */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest
            ? e.target.closest('.representantes-btn-activar, .representantes-btn-desactivar')
            : null;

        if (btn) {
            var id     = parseInt(btn.getAttribute('data-representante-id'), 10);
            var nombre = btn.getAttribute('data-representante-nombre') || '';
            var activar = btn.getAttribute('data-representante-activo') === '1';

            if (!isNaN(id)) {
                abrirModal(id, nombre, activar);
            }
        }
    });

})();
