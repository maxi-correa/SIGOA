/**
 * SIGOA — Gestión de usuarios
 *
 * Modal de edición (nombre, apellido, email, estado), modal de alta
 * (solo SUPERADMINISTRADOR), toggle de contraseñas y validación frontend.
 * El backend valida de forma independiente todas las operaciones.
 */
(function () {
    'use strict';

    /* ================================================================
       Token CSRF
       ================================================================
       `SIGOA.csrf` es el único origen del token vigente: con
       `Security::$regenerate = true` el servidor rota el token en cada
       petición aceptada, así que no puede leerse una sola vez al cargar
       la página. */
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

    /* ================================================================
       Elementos del DOM
       ================================================================ */
    var estadoEl = document.getElementById('estadoUsuarios');
    var estado   = estadoEl ? estadoEl.dataset : {};

    var modalEditar = document.getElementById('modalEditarUsuario');
    var formEditar  = document.getElementById('formEditarUsuario');
    var btnCancelarEditar = document.getElementById('btnCancelarEditarUsuario');
    var inputEditarId     = document.getElementById('editar_usuario_id');
    var inputEditarNombre = document.getElementById('editar_nombre');
    var inputEditarApellido = document.getElementById('editar_apellido');
    var inputEditarEmail  = document.getElementById('editar_email');
    var selectEditarActivo = document.getElementById('editar_activo');
    var mensajeEditar     = document.getElementById('modalEditarMensaje');

    var modalAlta = document.getElementById('modalAltaUsuario');
    var formAlta  = document.getElementById('formAltaUsuario');
    var btnAgregar = document.getElementById('btnAgregarUsuario');
    var btnCancelarAlta = document.getElementById('btnCancelarAltaUsuario');

    /* ================================================================
       Modales — Abrir / Cerrar
       ================================================================ */
    function mostrarOverlay(modal) {
        if (!modal) return;

        modal.removeAttribute('hidden');
        modal.removeAttribute('aria-hidden');
        document.body.style.overflow = 'hidden';
    }

    function ocultarOverlay(modal) {
        if (!modal) return;

        modal.setAttribute('hidden', '');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function limpiarErrores(modal) {
        if (!modal) return;

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

    function primerInputVisible(modal) {
        return modal ? modal.querySelector('input:not([type="hidden"]):not([disabled])') : null;
    }

    /* ================================================================
       Modal de edición
       ================================================================ */
    function abrirModalEditar(btn) {
        if (!modalEditar) return;

        limpiarErrores(modalEditar);

        inputEditarId.value = btn.getAttribute('data-id') || '';
        inputEditarNombre.value = btn.getAttribute('data-nombre') || '';
        inputEditarApellido.value = btn.getAttribute('data-apellido') || '';
        inputEditarEmail.value = btn.getAttribute('data-email') || '';
        selectEditarActivo.value = btn.getAttribute('data-activo') === '0' ? '0' : '1';

        if (mensajeEditar) {
            mensajeEditar.textContent =
                'Modifique los datos personales de ' +
                (btn.getAttribute('data-nombre') || '') + ' ' +
                (btn.getAttribute('data-apellido') || '') +
                '. El usuario, el rol y la contraseña no se modifican desde aquí.';
        }

        mostrarOverlay(modalEditar);
        inputEditarNombre.focus();
    }

    /* ================================================================
       Modal de alta
       ================================================================ */
    function limpiarCamposAlta() {
        formAlta.reset();

        var fieldErrors = formAlta.querySelectorAll('.field-error');
        for (var i = 0; i < fieldErrors.length; i++) {
            fieldErrors[i].textContent = '';
        }
    }

    function abrirModalAlta() {
        if (!modalAlta) return;

        limpiarErrores(modalAlta);
        limpiarCamposAlta();

        mostrarOverlay(modalAlta);

        var primerInput = primerInputVisible(modalAlta);
        if (primerInput) primerInput.focus();
    }

    /* ================================================================
       Apertura desde la tabla
       ================================================================ */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('[data-accion="editar"]') : null;
        if (btn) {
            abrirModalEditar(btn);
        }
    });

    if (btnAgregar) {
        btnAgregar.addEventListener('click', abrirModalAlta);
    }

    if (btnCancelarEditar) {
        btnCancelarEditar.addEventListener('click', function () {
            ocultarOverlay(modalEditar);
        });
    }

    if (btnCancelarAlta) {
        btnCancelarAlta.addEventListener('click', function () {
            ocultarOverlay(modalAlta);
        });
    }

    /* Cerrar con tecla Escape */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
            if (modalEditar && !modalEditar.hasAttribute('hidden')) {
                ocultarOverlay(modalEditar);
            }
            if (modalAlta && !modalAlta.hasAttribute('hidden')) {
                ocultarOverlay(modalAlta);
            }
        }
    });

    /* Cerrar al hacer clic fuera del modal */
    if (modalEditar) {
        modalEditar.addEventListener('click', function (e) {
            if (e.target === modalEditar) {
                ocultarOverlay(modalEditar);
            }
        });
    }

    if (modalAlta) {
        modalAlta.addEventListener('click', function (e) {
            if (e.target === modalAlta) {
                ocultarOverlay(modalAlta);
            }
        });
    }

    /* ================================================================
       Toggle mostrar/ocultar contraseña
       ================================================================ */
    function initPasswordToggles() {
        var toggleButtons = document.querySelectorAll('.toggle-usuario-password');

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
       Validación frontend
       ================================================================ */
    function mostrarErrorCampo(input, mensaje) {
        var grupo = input.closest('.form-group');
        if (!grupo) return;
        var errorSpan = grupo.querySelector('.field-error');
        if (errorSpan) errorSpan.textContent = mensaje;
    }

    function limpiarErroresFormulario(form) {
        var fieldErrors = form.querySelectorAll('.field-error');
        for (var i = 0; i < fieldErrors.length; i++) {
            fieldErrors[i].textContent = '';
        }
    }

    function esEmailValido(valor) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(valor);
    }

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

    function validarFormularioEdicion() {
        limpiarErroresFormulario(formEditar);

        var errores = [];

        if (inputEditarNombre.value.trim() === '') {
            errores.push({ el: inputEditarNombre, msg: 'El nombre es obligatorio.' });
        }
        if (inputEditarApellido.value.trim() === '') {
            errores.push({ el: inputEditarApellido, msg: 'El apellido es obligatorio.' });
        }

        var email = inputEditarEmail.value.trim();
        if (email !== '' && !esEmailValido(email)) {
            errores.push({ el: inputEditarEmail, msg: 'El correo electrónico ingresado no es válido.' });
        }

        if (errores.length > 0) {
            for (var i = 0; i < errores.length; i++) {
                mostrarErrorCampo(errores[i].el, errores[i].msg);
            }
            errores[0].el.focus();

            return false;
        }

        return true;
    }

    function validarFormularioAlta() {
        limpiarErroresFormulario(formAlta);

        var nombre      = document.getElementById('alta_nombre');
        var apellido    = document.getElementById('alta_apellido');
        var usuario     = document.getElementById('alta_usuario');
        var email       = document.getElementById('alta_email');
        var rol         = document.getElementById('alta_rol_id');
        var password    = document.getElementById('alta_password');
        var confirmar   = document.getElementById('alta_confirmar_password');

        var errores = [];

        if (nombre.value.trim() === '') {
            errores.push({ el: nombre, msg: 'El nombre es obligatorio.' });
        }
        if (apellido.value.trim() === '') {
            errores.push({ el: apellido, msg: 'El apellido es obligatorio.' });
        }

        var usuarioValor = usuario.value.trim();
        if (usuarioValor === '') {
            errores.push({ el: usuario, msg: 'El usuario es obligatorio.' });
        } else if (usuarioValor.length < 3) {
            errores.push({ el: usuario, msg: 'El usuario debe tener al menos 3 caracteres.' });
        }

        if (email.value.trim() !== '' && !esEmailValido(email.value.trim())) {
            errores.push({ el: email, msg: 'El correo electrónico ingresado no es válido.' });
        }

        if (rol.value === '') {
            errores.push({ el: rol, msg: 'Debe seleccionar un rol válido.' });
        }

        if (password.value === '') {
            errores.push({ el: password, msg: 'La contraseña es obligatoria.' });
        } else {
            var reglas = validarReglasContrasena(password.value);
            if (reglas) {
                errores.push({ el: password, msg: reglas });
            }
        }

        if (confirmar.value === '') {
            errores.push({ el: confirmar, msg: 'Debe confirmar la contraseña.' });
        } else if (password.value !== '' && password.value !== confirmar.value) {
            errores.push({ el: confirmar, msg: 'La confirmación de la contraseña no coincide.' });
        }

        if (errores.length > 0) {
            for (var i = 0; i < errores.length; i++) {
                mostrarErrorCampo(errores[i].el, errores[i].msg);
            }
            errores[0].el.focus();

            return false;
        }

        return true;
    }

    if (formEditar) {
        formEditar.addEventListener('submit', function (e) {
            if (!validarFormularioEdicion()) {
                e.preventDefault();
                return;
            }

            actualizarTokenCsrfFormulario(formEditar, 'csrf_editar_usuario');
        });
    }

    if (formAlta) {
        formAlta.addEventListener('submit', function (e) {
            if (!validarFormularioAlta()) {
                e.preventDefault();
                return;
            }

            actualizarTokenCsrfFormulario(formAlta, 'csrf_alta_usuario');
        });
    }

    /* ================================================================
       Reapertura de modales tras un error de validación del backend
       ================================================================ */
    function revisarEstadoInicial() {
        if (!estado || !estado.reabrir) return;

        /* Los campos conservan los valores enviados (old() renderizado por
           la vista); no se recargan desde los atributos data-* para no
           pisarlos. */
        if (estado.reabrir === 'edicion' && modalEditar) {
            mostrarOverlay(modalEditar);
        } else if (estado.reabrir === 'alta' && modalAlta) {
            mostrarOverlay(modalAlta);
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
