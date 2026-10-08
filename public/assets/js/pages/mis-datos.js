/**
 * SIGOA — Mis Datos
 * Verificar contraseña, cambiar contraseña, editar datos personales
 * (nombre, apellido, email) y gestión de modales.
 */
(function () {
    'use strict';

    /* ================================================================
       Token CSRF
       ================================================================
       `SIGOA.csrf` es el único origen del token vigente (§61): con
       `Security::$regenerate = true` el servidor rota el token en cada
       petición aceptada, así que no puede leerse una sola vez al cargar la
       página. */
    var CSRF = (window.SIGOA && window.SIGOA.csrf) ? window.SIGOA.csrf : null;

    function obtenerTokenCsrf() {
        return CSRF ? CSRF.token() : '';
    }

    function actualizarTokenCsrfFormulario(form, idCampoCsrf) {
        if (!form || !idCampoCsrf) return;
        var token = obtenerTokenCsrf();
        if (token === '') return;
        var campo = document.getElementById(idCampoCsrf);
        if (campo) {
            campo.value = token;
        }
    }

    /**
     * Registra el token que deja vigente una respuesta.
     *
     * Debe ejecutarse antes de leer el cuerpo: la cabecera y el JSON son
     * excluyentes en el mismo objeto `Response`.
     */
    function renovarTokenCsrf(respuesta) {
        if (CSRF) {
            CSRF.actualizar(respuesta);
        }
    }

    /* ================================================================
       Elementos del DOM
       ================================================================ */
    var datosLista      = document.getElementById('datosLista');
    var accionesCard    = document.getElementById('accionesCard');
    var botonesEditarDato = document.querySelectorAll('.btn-editar-dato');
    var formEditarDato  = document.getElementById('formEditarDato');
    var btnCancelarDato = document.getElementById('btnCancelarDato');
    var inputDatoEditado = document.getElementById('datoEditado');
    var labelDatoEditado = document.getElementById('labelDatoEditado');

    var btnVerContrasena       = document.getElementById('btnVerContrasena');
    var iconoVerificar         = document.getElementById('iconoVerificar');
    var passwordVerificado     = document.getElementById('passwordVerificado');
    var btnsCambiar            = document.querySelectorAll('.btn-cambiar-contrasena');
    var notaContrasenaBottom   = document.getElementById('notaContrasenaBottom');

    var modalVerificar   = document.getElementById('modalVerificar');
    var formVerificar    = document.getElementById('formVerificar');
    var inputVerificar   = document.getElementById('verificar_password');
    var errorVerificar   = document.getElementById('errorVerificar');
    var btnCancelarVerificar = document.getElementById('btnCancelarVerificar');

    var modalCambiar        = document.getElementById('modalCambiar');
    var formCambiar         = document.getElementById('formCambiarContrasena');
    var errorCambiar        = document.getElementById('errorCambiar');
    var btnCancelarCambiar  = document.getElementById('btnCancelarCambiar');

    var estadoEl = document.getElementById('estadoMisDatos');
    var estado   = estadoEl ? estadoEl.dataset : {};

    /* ================================================================
       Modales — Abrir / Cerrar
       ================================================================ */
    function abrirModal(modal) {
        if (!modal) return;
        modal.removeAttribute('hidden');
        modal.removeAttribute('aria-hidden');
        document.body.style.overflow = 'hidden';

        var primerInput = modal.querySelector('input:not([type="hidden"])');
        if (primerInput) {
            primerInput.value = '';
            primerInput.focus();
        }
    }

    function cerrarModal(modal) {
        if (!modal) return;
        modal.setAttribute('hidden', '');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        limpiarErrorModal(modal);
    }

    function limpiarErrorModal(modal) {
        var errorContainer = modal.querySelector('.modal-error');
        if (errorContainer) {
            errorContainer.textContent = '';
            errorContainer.setAttribute('hidden', '');
        }
        var fieldErrors = modal.querySelectorAll('.field-error');
        for (var i = 0; i < fieldErrors.length; i++) {
            fieldErrors[i].textContent = '';
        }
    }

    function mostrarErrorModal(modal, mensaje) {
        var errorContainer = modal.querySelector('.modal-error');
        if (errorContainer) {
            errorContainer.textContent = mensaje;
            errorContainer.removeAttribute('hidden');
        }
    }

    /* Cerrar con Escape */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
            if (modalVerificar && !modalVerificar.hasAttribute('hidden')) {
                cerrarModal(modalVerificar);
                btnVerContrasena.focus();
            }
            if (modalCambiar && !modalCambiar.hasAttribute('hidden')) {
                cerrarModal(modalCambiar);
            }
        }
    });

    /* Cerrar al hacer click fuera del modal */
    if (modalVerificar) {
        modalVerificar.addEventListener('click', function (e) {
            if (e.target === modalVerificar) {
                cerrarModal(modalVerificar);
                btnVerContrasena.focus();
            }
        });
    }

    if (modalCambiar) {
        modalCambiar.addEventListener('click', function (e) {
            if (e.target === modalCambiar) {
                cerrarModal(modalCambiar);
            }
        });
    }

    /* ================================================================
       Toggle mostrar/ocultar contraseña (dentro de modales)
       ================================================================ */
    function initPasswordToggles() {
        var toggleButtons = document.querySelectorAll('.toggle-modal-password');
        for (var i = 0; i < toggleButtons.length; i++) {
            (function (btn) {
                btn.addEventListener('click', function () {
                    var wrapper = btn.closest('.input-icon-wrapper');
                    if (!wrapper) return;

                    var input = wrapper.querySelector('input');
                    if (!input) return;

                    var iconShow = btn.querySelector('.icon-show');
                    var iconHide = btn.querySelector('.icon-hide');
                    var isPassword = input.type === 'password';

                    input.type = isPassword ? 'text' : 'password';

                    if (iconShow) iconShow.style.display = isPassword ? 'none' : '';
                    if (iconHide) iconHide.style.display = isPassword ? '' : 'none';

                    btn.setAttribute('aria-label',
                        isPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'
                    );

                    input.focus();
                });
            })(toggleButtons[i]);
        }
    }

    /* ================================================================
       Edición de datos personales — Toggle inline (nombre, apellido, email)
       ================================================================ */
    var configDatos = {
        nombre: {
            label: 'Nuevo nombre',
            type: 'text',
            maxlength: 100,
            autocomplete: 'off',
            vacio: 'El nombre es obligatorio.'
        },
        apellido: {
            label: 'Nuevo apellido',
            type: 'text',
            maxlength: 100,
            autocomplete: 'off',
            vacio: 'El apellido es obligatorio.'
        },
        email: {
            label: 'Nuevo correo electrónico',
            type: 'email',
            maxlength: 150,
            autocomplete: 'email',
            vacio: 'Debe ingresar un correo electrónico.'
        }
    };

    function mostrarFormularioDato(campo, valor) {
        var config = configDatos[campo];
        if (!datosLista || !formEditarDato || !accionesCard || !config) return;

        formEditarDato.action = '/mis-datos/' + campo;
        formEditarDato.dataset.campo = campo;

        if (labelDatoEditado) {
            labelDatoEditado.textContent = config.label;
        }

        if (inputDatoEditado) {
            inputDatoEditado.type = config.type;
            inputDatoEditado.maxLength = config.maxlength;
            inputDatoEditado.autocomplete = config.autocomplete;
            inputDatoEditado.value = valor || '';
        }

        var errorSpan = formEditarDato.querySelector('.field-error');
        if (errorSpan) errorSpan.textContent = '';

        datosLista.style.display = 'none';
        accionesCard.style.display = 'none';
        formEditarDato.removeAttribute('hidden');

        if (inputDatoEditado) inputDatoEditado.focus();
    }

    function ocultarFormularioDato() {
        if (!datosLista || !formEditarDato || !accionesCard) return;
        formEditarDato.setAttribute('hidden', '');
        datosLista.style.display = '';
        accionesCard.style.display = '';
    }

    for (var d = 0; d < botonesEditarDato.length; d++) {
        (function (btn) {
            btn.addEventListener('click', function () {
                mostrarFormularioDato(btn.getAttribute('data-campo'), btn.getAttribute('data-valor'));
            });
        })(botonesEditarDato[d]);
    }

    if (btnCancelarDato) {
        btnCancelarDato.addEventListener('click', ocultarFormularioDato);
    }

    if (formEditarDato) {
        formEditarDato.addEventListener('submit', function (e) {
            var campo = formEditarDato.dataset.campo;
            var config = configDatos[campo];
            var errorSpan = formEditarDato.querySelector('.field-error');
            var valor = inputDatoEditado ? inputDatoEditado.value.trim() : '';

            if (errorSpan) errorSpan.textContent = '';

            /* Validación frontend (el backend valida de forma independiente) */
            if (config && valor === '') {
                if (errorSpan) errorSpan.textContent = config.vacio;
                if (inputDatoEditado) inputDatoEditado.focus();
                e.preventDefault();
                return;
            }

            if (campo === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(valor)) {
                if (errorSpan) errorSpan.textContent = 'El correo electrónico ingresado no es válido.';
                if (inputDatoEditado) inputDatoEditado.focus();
                e.preventDefault();
                return;
            }

            actualizarTokenCsrfFormulario(formEditarDato, 'csrf_editar_dato');
        });
    }

    /* ================================================================
       Verificar contraseña (fetch AJAX)
       ================================================================ */
    function abrirVerificar() {
        errorVerificar.textContent = '';
        errorVerificar.setAttribute('hidden', '');
        abrirModal(modalVerificar);
    }

    function onVerificarSuccess() {
        cerrarModal(modalVerificar);

        /* Cambiar ícono de ojo a check de verificación */
        if (iconoVerificar) {
            iconoVerificar.className = 'bi bi-check-circle-fill';
            iconoVerificar.parentElement.setAttribute('aria-label', 'Contraseña verificada');
        }

        /* Mostrar estado verificado */
        if (passwordVerificado) {
            passwordVerificado.removeAttribute('hidden');
        }

        /* Habilitar botones de cambio de contraseña */
        for (var i = 0; i < btnsCambiar.length; i++) {
            btnsCambiar[i].removeAttribute('disabled');
        }

        /* Ocultar nota informativa */
        if (notaContrasenaBottom) {
            notaContrasenaBottom.style.display = 'none';
        }
    }

    if (btnVerContrasena) {
        btnVerContrasena.addEventListener('click', function () {
            abrirVerificar();
        });
    }

    if (btnCancelarVerificar) {
        btnCancelarVerificar.addEventListener('click', function () {
            cerrarModal(modalVerificar);
        });
    }

    if (formVerificar) {
        formVerificar.addEventListener('submit', function (e) {
            e.preventDefault();

            var password = inputVerificar.value;
            if (password === '') {
                mostrarErrorModal(modalVerificar, 'Debe ingresar su contraseña actual.');
                return;
            }

            var formData = new FormData(formVerificar);

            fetch(formVerificar.action || '/mis-datos/verificar-password', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': obtenerTokenCsrf()
                }
            })
            .then(function (resp) {
                renovarTokenCsrf(resp);

                return resp.json();
            })
            .then(function (data) {
                if (data.success) {
                    onVerificarSuccess();
                } else {
                    mostrarErrorModal(modalVerificar, data.error || 'No fue posible verificar la contraseña.');
                    inputVerificar.value = '';
                    inputVerificar.focus();
                }
            })
            .catch(function () {
                mostrarErrorModal(modalVerificar, 'Error de conexión. Intente nuevamente.');
            });
        });
    }

    /* ================================================================
       Cambiar contraseña — Modal
       ================================================================ */
    function abrirCambiar() {
        errorCambiar.textContent = '';
        errorCambiar.setAttribute('hidden', '');
        abrirModal(modalCambiar);
    }

    function onCambiarClose() {
        cerrarModal(modalCambiar);
        if (btnsCambiar.length > 0) {
            btnsCambiar[0].focus();
        }
    }

    for (var j = 0; j < btnsCambiar.length; j++) {
        (function (btn) {
            btn.addEventListener('click', function () {
                if (!btn.disabled) {
                    abrirCambiar();
                }
            });
        })(btnsCambiar[j]);
    }

    if (btnCancelarCambiar) {
        btnCancelarCambiar.addEventListener('click', function () {
            onCambiarClose();
        });
    }

    if (formCambiar) {
        formCambiar.addEventListener('submit', function (e) {
            e.preventDefault();

            /* Validación frontend */
            var nueva    = document.getElementById('nueva_contrasena');
            var confirmar = document.getElementById('confirmar_contrasena');

            var campos = formCambiar.querySelectorAll('.field-error');
            for (var k = 0; k < campos.length; k++) {
                campos[k].textContent = '';
            }

            var errores = [];

            if (!nueva.value) {
                errores.push({ el: nueva, msg: 'Debe ingresar la nueva contraseña.' });
            }
            if (!confirmar.value) {
                errores.push({ el: confirmar, msg: 'Debe confirmar la nueva contraseña.' });
            }

            if (errores.length === 0) {
                var reglas = validarReglasContrasena(nueva.value);
                if (reglas) {
                    errores.push({ el: nueva, msg: reglas });
                } else if (nueva.value !== confirmar.value) {
                    errores.push({ el: confirmar, msg: 'Las contraseñas no coinciden.' });
                }
            }

            if (errores.length > 0) {
                for (var m = 0; m < errores.length; m++) {
                    var campo = errores[m].el.closest('.form-group');
                    if (campo) {
                        var errorSpan = campo.querySelector('.field-error');
                        if (errorSpan) errorSpan.textContent = errores[m].msg;
                    }
                    if (m === 0) errores[m].el.focus();
                }
                return;
            }

            /* Envío normal (full POST) con token CSRF vigente */
            actualizarTokenCsrfFormulario(formCambiar, 'csrf_cambiar_contrasena');
            formCambiar.submit();
        });
    }

    /**
     * Replica las reglas de contraseña del servidor / login.
     */
    function validarReglasContrasena(pw) {
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

    /* ================================================================
       Reabrir formularios/modales cuando hay errores POST
       ================================================================ */
    function revisarEstadoInicial() {
        if (!estado) return;

        if (estado.reabrirDato && configDatos[estado.reabrirDato]) {
            mostrarFormularioDato(estado.reabrirDato, estado.valorOld || '');
        }
        if (estado.reabrirCambiar === '1') {
            abrirCambiar();
        }
    }

    /* ================================================================
       Inicialización
       ================================================================ */
    document.addEventListener('DOMContentLoaded', function () {
        initPasswordToggles();
        revisarEstadoInicial();
    });

})();
