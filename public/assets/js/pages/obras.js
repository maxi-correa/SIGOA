/**
 * SIGOA — Dashboard administrativo / Obras
 *
 * Modal reutilizable de obra en dos modos:
 *   - Alta:   acción POST /obras/crear, estado impuesto por el sistema.
 *   - Edición: acción POST /obras/actualizar, con datos precargados.
 */
(function () {
    'use strict';

    var overlay = document.getElementById('modalObra');
    var formObra  = document.getElementById('formObra');

    var btnAbrirAlta = document.getElementById('btnAgregarObra');
    var btnCancelar  = document.getElementById('btnCancelarObra');

    var inputObraId     = document.getElementById('obra_id');
    var inputExpediente = document.getElementById('expediente_municipal');
    var inputNombre     = document.getElementById('nombre');
    var selectBarrio    = document.getElementById('barrio_id');
    var selectEmpresa   = document.getElementById('empresa_id');
    var selectTipo      = document.getElementById('tipo_licitacion_id');
    var inputNumeroLic  = document.getElementById('numero_licitacion');
    var selectEstado    = document.getElementById('estado_obra_id');

    var tituloIcono     = document.getElementById('modalObraIcono');
    var tituloTexto     = document.getElementById('modalObraTituloTexto');
    var mensaje         = document.getElementById('modalObraMensaje');
    var btnGuardarTexto = document.getElementById('btnGuardarObraTexto');
    var bloqueCodigo    = document.getElementById('bloqueCodigoObra');
    var textoCodigo     = document.getElementById('codigoObra');
    var bloqueEstadoAlta   = document.getElementById('bloqueEstadoAlta');
    var bloqueEstadoEdicion = document.getElementById('bloqueEstadoEdicion');

    var textoAltaMensaje =
        'Registre el expediente inicial de la obra. La información de ' +
        'adjudicación podrá completarse posteriormente.';
    var textoEdicionMensaje =
        'Modifique los datos básicos de la obra. El código se genera ' +
        'automáticamente y no es editable.';

    function mostrarOverlay() {
        if (!overlay) return;

        overlay.removeAttribute('hidden');
        overlay.removeAttribute('aria-hidden');
        document.body.style.overflow = 'hidden';

        if (inputExpediente) {
            inputExpediente.focus();
        }
    }

    function ocultarOverlay() {
        if (!overlay) return;

        overlay.setAttribute('hidden', '');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function configurarModoAlta() {
        if (overlay && formObra) {
            formObra.action = overlay.getAttribute('data-accion-alta') || formObra.action;
        }
        tituloIcono.className = 'bi bi-plus-square';
        tituloTexto.textContent = 'Agregar obra';
        mensaje.textContent = textoAltaMensaje;
        btnGuardarTexto.textContent = 'Guardar';
        bloqueCodigo.hidden = true;
        bloqueEstadoAlta.hidden = false;
        bloqueEstadoEdicion.hidden = true;
    }

    /* Solo al abrir el alta desde el botón (+ Agregar obra): campos en blanco */
    function limpiarCamposObra() {
        inputObraId.value = '';
        inputExpediente.value = '';
        inputNombre.value = '';
        selectBarrio.value = '';
        selectEmpresa.value = '';
        selectTipo.value = '';
        inputNumeroLic.value = '';
        selectEstado.value = '';
    }

    function configurarModoEdicion() {
        if (overlay && formObra) {
            formObra.action = overlay.getAttribute('data-accion-edicion') || formObra.action;
        }
        tituloIcono.className = 'bi bi-pencil';
        tituloTexto.textContent = 'Editar obra';
        mensaje.textContent = textoEdicionMensaje;
        btnGuardarTexto.textContent = 'Guardar cambios';
        bloqueCodigo.hidden = false;
        bloqueEstadoAlta.hidden = true;
        bloqueEstadoEdicion.hidden = false;
    }

    function abrirModalAlta() {
        limpiarCamposObra();
        configurarModoAlta();
        mostrarOverlay();
    }

    function abrirModalEdicion(obra) {
        configurarModoEdicion();

        inputObraId.value = obra.id;
        inputExpediente.value = obra.expediente;
        inputNombre.value = obra.nombre;
        selectBarrio.value = obra.barrio > 0 ? String(obra.barrio) : '';
        selectEmpresa.value = obra.empresa > 0 ? String(obra.empresa) : '';
        selectTipo.value = obra.tipo > 0 ? String(obra.tipo) : '';
        inputNumeroLic.value = obra.licitacion;
        selectEstado.value = obra.estado > 0 ? String(obra.estado) : '';
        textoCodigo.textContent = obra.codigo;

        mostrarOverlay();
    }

    function leerObraDeBoton(btn) {
        var numerico = function (valor) {
            var n = parseInt(valor, 10);
            return isNaN(n) ? 0 : n;
        };

        return {
            id:         numerico(btn.getAttribute('data-id')),
            codigo:     btn.getAttribute('data-codigo') || '',
            expediente: btn.getAttribute('data-expediente') || '',
            nombre:     btn.getAttribute('data-nombre') || '',
            barrio:     numerico(btn.getAttribute('data-barrio')),
            empresa:    numerico(btn.getAttribute('data-empresa')),
            tipo:       numerico(btn.getAttribute('data-tipo')),
            licitacion: btn.getAttribute('data-licitacion') || '',
            estado:     numerico(btn.getAttribute('data-estado'))
        };
    }

    /* Abrir en modo alta */
    if (btnAbrirAlta) {
        btnAbrirAlta.addEventListener('click', abrirModalAlta);
    }

    /* Abrir en modo edición desde cualquier botón Editar de la tabla */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('[data-accion="editar"]') : null;
        if (btn) {
            abrirModalEdicion(leerObraDeBoton(btn));
        }
    });

    if (btnCancelar) {
        btnCancelar.addEventListener('click', ocultarOverlay);
    }

    /* Cerrar con tecla Escape */
    document.addEventListener('keydown', function (e) {
        if ((e.key === 'Escape' || e.keyCode === 27) && overlay && !overlay.hasAttribute('hidden')) {
            ocultarOverlay();
        }
    });

    /* Cerrar al hacer clic fuera del modal */
    if (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) {
                ocultarOverlay();
            }
        });
    }

    /* Reapertura del modal tras un error de validación del backend */
    document.addEventListener('DOMContentLoaded', function () {
        if (!overlay) return;

        var modo = overlay.getAttribute('data-reabrir');

        if (modo === 'alta') {
            /* Los campos conservan los valores enviados (old) para rellenar
               el formulario tras el error. */
            configurarModoAlta();
            mostrarOverlay();
        } else if (modo === 'edicion') {
            /* Los campos conservan los valores enviados (old) y el código
               fue precargado por la vista a partir del flashdata. */
            configurarModoEdicion();
            mostrarOverlay();
        }
    });

})();