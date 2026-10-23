/**
 * SIGOA — Documentación de obra
 *
 *  - Selector de tipo documental: muestra los campos de evento solo para
 *    los tipos que los requieren (según `data-tipo`, sin depender del
 *    texto de la opción) y expone el motivo de los tipos no disponibles.
 *  - Modal «Ubicación del archivo»: consulta la ruta por `data-url`,
 *    permite copiar ruta o carpeta y se cierra con X, botón, Escape o
 *    clic en el fondo, devolviendo el foco al botón de origen.
 *  - Modal «Reemplazar documento»: precarga el documento y sus
 *    observaciones en el formulario.
 *
 * No usa Bootstrap JS: el proyecto carga Bootstrap Icons pero no el
 * bundle de Bootstrap, por lo que los modales se gestionan con el
 * componente propio (`.modal-overlay[hidden]`).
 */
(function () {
    'use strict';

    var TIPO_NEUTRALIZACION = 'ACTA DE NEUTRALIZACIÓN';
    var TIPO_REINICIO = 'ACTA DE REINICIO';

    /* ================================================================
       Selector de tipo documental
       ================================================================ */
    var selectTipo = document.getElementById('tipo_documento_id');
    var campoNeutralizacion = document.getElementById('campo_neutralizacion');
    var campoReinicio = document.getElementById('campo_reinicio');
    var tipoMotivo = document.getElementById('tipo_motivo');

    function alternarCampo(campo, visible) {
        if (!campo) return;
        if (visible) {
            campo.removeAttribute('hidden');
        } else {
            campo.setAttribute('hidden', '');
        }
    }

    if (selectTipo) {
        selectTipo.addEventListener('change', function () {
            var selected = this.options[this.selectedIndex];
            if (!selected) return;

            var tipo = selected.getAttribute('data-tipo') || '';
            var habilitado = selected.getAttribute('data-habilitado') === '1';
            var motivo = selected.getAttribute('data-motivo') || '';

            if (tipoMotivo) {
                tipoMotivo.textContent = (!habilitado && motivo) ? motivo : '';
            }

            alternarCampo(campoNeutralizacion, tipo === TIPO_NEUTRALIZACION);
            alternarCampo(campoReinicio, tipo === TIPO_REINICIO);
        });
    }

    /* ================================================================
       Selector de archivos personalizado
       Mantiene el <input type="file"> nativo operativo (teclado y
       tecnologías de asistencia) y refleja el nombre elegido en texto.
       ================================================================ */
    var SIN_ARCHIVO = 'Ningún archivo seleccionado';

    function inicializarSelectorArchivo(selector) {
        var input = selector.querySelector('[data-archivo-input]');
        var nombre = selector.querySelector('[data-archivo-nombre]');
        if (!input || !nombre) return;

        input.addEventListener('change', function () {
            if (input.files && input.files.length > 0) {
                nombre.textContent = input.files[0].name;
            } else {
                nombre.textContent = SIN_ARCHIVO;
            }
        });
    }

    document.querySelectorAll('[data-archivo-selector]').forEach(inicializarSelectorArchivo);

    /* ================================================================
       Utilidades de modal
       ================================================================ */
    var bodyOverflow = '';

    function abrirModal(modal) {
        if (!modal) return;
        bodyOverflow = document.body.style.overflow;
        modal.removeAttribute('hidden');
        modal.removeAttribute('aria-hidden');
        document.body.style.overflow = 'hidden';
    }

    function cerrarModal(modal) {
        if (!modal) return;
        modal.setAttribute('hidden', '');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = bodyOverflow;
    }

    function enlaceEscape(modal, cerrar) {
        document.addEventListener('keydown', function (e) {
            if ((e.key === 'Escape' || e.keyCode === 27) && modal && !modal.hasAttribute('hidden')) {
                cerrar();
            }
        });
    }

    /* ================================================================
       Copiar al portapapeles con fallback
       ================================================================ */
    function copiarTexto(texto, aviso) {
        function exito() {
            if (aviso) aviso.textContent = 'Copiado al portapapeles.';
        }

        function fallback() {
            var temp = document.createElement('textarea');
            temp.value = texto;
            temp.setAttribute('readonly', '');
            temp.style.position = 'absolute';
            temp.style.left = '-9999px';
            document.body.appendChild(temp);
            temp.select();

            try {
                document.execCommand('copy');
                exito();
            } catch (err) {
                if (aviso) aviso.textContent = 'No se pudo copiar automáticamente.';
            }

            document.body.removeChild(temp);
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(texto).then(exito, fallback);
        } else {
            fallback();
        }
    }

    /* ================================================================
       Modal «Agregar documento»
       Se abre desde el botón verde o desde el estado vacío. Mantiene
       el formulario de carga existente y su procesamiento (POST al
       mismo endpoint): no duplica validaciones.
       ================================================================ */
    var modalAgregar = document.getElementById('modalAgregarDocumento');

    if (modalAgregar) {
        var btnAbrirAgregar = document.getElementById('btnAgregarDocumento');
        var btnCancelarAgregar = document.getElementById('btnCancelarAgregar');
        var focoInicial = function () {
            var primera = document.getElementById('tipo_documento_id');
            if (primera && typeof primera.focus === 'function') {
                primera.focus();
            }
        };

        var abrirAgregar = function () {
            abrirModal(modalAgregar);
            focoInicial();
        };

        if (btnAbrirAgregar) {
            btnAbrirAgregar.addEventListener('click', abrirAgregar);
        }

        document.querySelectorAll('[data-ir-agregar]').forEach(function (btn) {
            btn.addEventListener('click', abrirAgregar);
        });

        if (btnCancelarAgregar) {
            btnCancelarAgregar.addEventListener('click', function () {
                cerrarModal(modalAgregar);
                if (btnAbrirAgregar && typeof btnAbrirAgregar.focus === 'function') {
                    btnAbrirAgregar.focus();
                }
            });
        }

        enlaceEscape(modalAgregar, function () {
            cerrarModal(modalAgregar);
        });

        modalAgregar.addEventListener('click', function (e) {
            if (e.target === modalAgregar) {
                cerrarModal(modalAgregar);
            }
        });

        /* Reapertura tras un error de validación del backend: el modal
           vuelve a abrirse con los valores enviados (old) y el mensaje
           de error visible, para corregir sin abandonar el formulario. */
        if (modalAgregar.getAttribute('data-reabrir') === 'alta') {
            abrirAgregar();
        }
    }

    /* ================================================================
       Modal «Ubicación del archivo»
       ================================================================ */
    var modalUbicacion = document.getElementById('modalUbicacion');

    if (modalUbicacion) {
        var rutaCompletaEl = document.getElementById('rutaCompleta');
        var rutaDirectorioEl = document.getElementById('rutaDirectorio');
        var ubicacionError = document.getElementById('ubicacionError');
        var avisoCopiado = document.getElementById('avisoCopiado');
        var btnCopiarRuta = document.getElementById('btnCopiarRuta');
        var btnCopiarDir = document.getElementById('btnCopiarDir');
        var btnCerrarUbicacion = document.getElementById('btnCerrarUbicacion');
        var botonOrigen = null;

        var cerrarUbicacion = function () {
            cerrarModal(modalUbicacion);
            if (botonOrigen && typeof botonOrigen.focus === 'function') {
                botonOrigen.focus();
            }
        };

        btnCerrarUbicacion.addEventListener('click', cerrarUbicacion);
        enlaceEscape(modalUbicacion, cerrarUbicacion);
        modalUbicacion.addEventListener('click', function (e) {
            if (e.target === modalUbicacion) {
                cerrarUbicacion();
            }
        });

        if (btnCopiarRuta) {
            btnCopiarRuta.addEventListener('click', function () {
                copiarTexto(rutaCompletaEl.textContent, avisoCopiado);
            });
        }

        if (btnCopiarDir) {
            btnCopiarDir.addEventListener('click', function () {
                copiarTexto(rutaDirectorioEl.textContent, avisoCopiado);
            });
        }

        document.querySelectorAll('[data-accion="ubicacion"]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var url = btn.getAttribute('data-url');
                if (!url) return;

                botonOrigen = btn;
                ubicacionError.textContent = '';
                ubicacionError.setAttribute('hidden', '');
                avisoCopiado.textContent = '';
                rutaCompletaEl.textContent = '';
                rutaDirectorioEl.textContent = '';
                btnCopiarRuta.disabled = true;
                btnCopiarDir.disabled = true;

                fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                })
                    .then(function (response) {
                        return response.json().then(function (data) {
                            return { ok: response.ok, data: data };
                        });
                    })
                    .then(function (resultado) {
                        if (resultado.ok && resultado.data.ok) {
                            rutaCompletaEl.textContent = resultado.data.ruta_completa;
                            rutaDirectorioEl.textContent = resultado.data.directorio;
                            btnCopiarRuta.disabled = false;
                            btnCopiarDir.disabled = false;
                        } else {
                            ubicacionError.textContent = (resultado.data && resultado.data.error) || 'No se pudo obtener la ubicación del archivo.';
                            ubicacionError.removeAttribute('hidden');
                        }

                        abrirModal(modalUbicacion);
                        btnCerrarUbicacion.focus();
                    })
                    .catch(function () {
                        ubicacionError.textContent = 'No se pudo obtener la ubicación del archivo.';
                        ubicacionError.removeAttribute('hidden');
                        abrirModal(modalUbicacion);
                        btnCerrarUbicacion.focus();
                    });
            });
        });
    }

    /* ================================================================
       Modal «Reemplazar documento»
       ================================================================ */
    var modalReemplazar = document.getElementById('modalReemplazar');

    if (modalReemplazar) {
        var reemplazarNombre = document.getElementById('reemplazarNombre');
        var reemplazarDocumentoId = document.getElementById('reemplazar_documento_id');
        var reemplazarObservaciones = document.getElementById('reemplazar_observaciones');
        var btnCancelarReemplazar = document.getElementById('btnCancelarReemplazar');
        var botonOrigenReemplazar = null;

        var cerrarReemplazar = function () {
            cerrarModal(modalReemplazar);
            if (botonOrigenReemplazar && typeof botonOrigenReemplazar.focus === 'function') {
                botonOrigenReemplazar.focus();
            }
        };

        btnCancelarReemplazar.addEventListener('click', cerrarReemplazar);
        enlaceEscape(modalReemplazar, cerrarReemplazar);
        modalReemplazar.addEventListener('click', function (e) {
            if (e.target === modalReemplazar) {
                cerrarReemplazar();
            }
        });

        document.querySelectorAll('[data-accion="reemplazar"]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                botonOrigenReemplazar = btn;
                reemplazarDocumentoId.value = btn.getAttribute('data-doc-id') || '';
                reemplazarNombre.textContent = btn.getAttribute('data-nombre') || '';
                reemplazarObservaciones.value = btn.getAttribute('data-observaciones') || '';

                abrirModal(modalReemplazar);

                var inputArchivo = document.getElementById('reemplazar_archivo');
                if (inputArchivo) {
                    inputArchivo.focus();
                }
            });
        });
    }
})();
