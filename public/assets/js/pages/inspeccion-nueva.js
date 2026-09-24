/**
 * SIGOA — Nueva inspección (Fase D.2)
 *
 * Flujo 100 % local contra IndexedDB (base `SIGOA`, stores `inspecciones`
 * y `fotografias`):
 *
 *   1. inicializa fecha/hora con el reloj del dispositivo;
 *   2. al guardar: genera UUID, crea la inspección con `estado_local =
 *      PENDIENTE_SYNC` y la persiste en el store `inspecciones`;
 *   3. habilita la captura de fotografías; cada una se optimiza en el
 *      cliente (SIGOA.imagenes), se persiste junto con su thumbnail en el
 *      store `fotografias` y queda pendiente de sincronización;
 *   4. todo permanece disponible aunque el dispositivo quede offline.
 *
 * No se realiza ninguna request al servidor: la sincronización es futura.
 */
(function () {
    'use strict';

    var ALMACEN = (window.SIGOA && window.SIGOA.almacenamiento) ? window.SIGOA.almacenamiento : null;
    var UUID    = (window.SIGOA && window.SIGOA.uuid) ? window.SIGOA.uuid : null;
    var IMAGENES = (window.SIGOA && window.SIGOA.imagenes) ? window.SIGOA.imagenes : null;

    var ESTADO_PENDIENTE_SYNC = 'PENDIENTE_SYNC';

    var contenedor    = document.getElementById('datosLocal');
    var form          = document.getElementById('formNuevaInspeccion');
    var inputFecha    = document.getElementById('fecha_inspeccion');
    var inputHora     = document.getElementById('hora_inspeccion');
    var inputObserv   = document.getElementById('observacion');
    var btnGuardar    = document.getElementById('btnGuardarInspeccion');
    var alertaGuardada = document.getElementById('alertaGuardada');
    var alertaError   = document.getElementById('alertaError');
    var alertaErrorTexto = document.getElementById('alertaErrorTexto');
    var seccionFotos  = document.getElementById('seccionFotos');
    var inputFoto     = document.getElementById('inputFoto');
    var procesandoFoto = document.getElementById('procesandoFoto');
    var listaFotos    = document.getElementById('listaFotos');
    var pie           = document.getElementById('tituloPie');

    var inspeccionActual = null;
    var urlsActivas = [];

    /* ================================================================
       Utilidades
       ================================================================ */
    function dosDigitos(numero) {
        return numero < 10 ? '0' + numero : String(numero);
    }

    function fechaDispositivo() {
        var ahora = new Date();

        return [
            ahora.getFullYear(),
            dosDigitos(ahora.getMonth() + 1),
            dosDigitos(ahora.getDate())
        ].join('-');
    }

    function horaDispositivo() {
        var ahora = new Date();

        return dosDigitos(ahora.getHours()) + ':' + dosDigitos(ahora.getMinutes());
    }

    function ocultarError() {
        if (alertaError) {
            alertaError.setAttribute('hidden', '');
        }
    }

    function mostrarError(mensaje) {
        if (alertaError && alertaErrorTexto) {
            alertaErrorTexto.textContent = mensaje;
            alertaError.removeAttribute('hidden');
        }
    }

    function errorCampo(campo, bloque, mensaje) {
        if (bloque) {
            bloque.textContent = mensaje;
            bloque.removeAttribute('hidden');
        }
        if (campo) {
            campo.classList.add('is-invalid');
        }
    }

    function limpiarErrorCampo(campo, bloque) {
        if (bloque) {
            bloque.textContent = '';
            bloque.setAttribute('hidden', '');
        }
        if (campo) {
            campo.classList.remove('is-invalid');
        }
    }

    function formatearBytes(bytes) {
        if (bytes < 1024) {
            return bytes + ' B';
        }
        if (bytes < 1024 * 1024) {
            return (bytes / 1024).toFixed(0) + ' KB';
        }
        return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
    }

    function revocarUrls() {
        for (var i = 0; i < urlsActivas.length; i++) {
            URL.revokeObjectURL(urlsActivas[i]);
        }
        urlsActivas = [];
    }

    function ahoraISO() {
        return new Date().toISOString();
    }

    /* ================================================================
       Guardar la inspección (SÓLO local)
       ================================================================ */
    function validarFormulario() {
        var ok = true;

        if (!inputFecha.value) {
            errorCampo(inputFecha, document.getElementById('errorFecha'), 'Debe indicar la fecha de la inspección.');
            ok = false;
        } else {
            limpiarErrorCampo(inputFecha, document.getElementById('errorFecha'));
        }

        if (!inputHora.value) {
            errorCampo(inputHora, document.getElementById('errorHora'), 'Debe indicar la hora de la inspección.');
            ok = false;
        } else {
            limpiarErrorCampo(inputHora, document.getElementById('errorHora'));
        }

        return ok;
    }

    function notificarGuardado() {
        if (alertaGuardada) {
            alertaGuardada.removeAttribute('hidden');
        }

        if (seccionFotos) {
            seccionFotos.removeAttribute('hidden');
        }

        if (pie) {
            pie.removeAttribute('hidden');
        }
    }

    function guardarInspeccion() {
        ocultarError();

        if (!ALMACEN || !UUID) {
            mostrarError('El almacenamiento local no está disponible en este navegador.');
            return;
        }

        if (!ALMACEN.soportado()) {
            mostrarError('Este dispositivo no soporta almacenamiento local; no es posible guardar la inspección.');
            return;
        }

        if (!validarFormulario()) {
            return;
        }

        var hora = inputHora.value + ':00';
        var observacion = inputObserv.value.trim();
        var ahora = ahoraISO();

        if (inspeccionActual) {
            inspeccionActual.fecha_inspeccion = inputFecha.value;
            inspeccionActual.hora_inspeccion  = hora;
            inspeccionActual.observacion      = observacion;
            inspeccionActual.updated_at_local = ahora;
        } else {
            inspeccionActual = {
                uuid:             UUID.v4(),
                obra_id:          parseInt(contenedor.dataset.obraId, 10),
                inspector_id:     parseInt(contenedor.dataset.inspectorId, 10),
                fecha_inspeccion: inputFecha.value,
                hora_inspeccion:  hora,
                observacion:      observacion,
                estado_local:     ESTADO_PENDIENTE_SYNC,
                created_at_local: ahora,
                updated_at_local: ahora
            };
        }

        return ALMACEN.guardar(ALMACEN.ALMACENES.inspecciones, inspeccionActual)
            .then(function () {
                notificarGuardado();
            })
            .catch(function (error) {
                showErrorAlmacenar(error, 'No fue posible guardar la inspección en este dispositivo.');
            });
    }

    /* ================================================================
       Fotografías (SÓLO local)
       ================================================================ */
    function procesarFotografia(archivo) {
        if (!ALMACEN || !UUID || !IMAGENES) {
            mostrarError('El procesamiento de imágenes no está disponible en este navegador.');
            return;
        }

        if (!inspeccionActual) {
            mostrarError('Primero debe guardar la inspección.');
            return;
        }

        procesandoFoto.removeAttribute('hidden');
        inputFoto.setAttribute('disabled', 'disabled');

        IMAGENES.optimizar(archivo)
            .then(function (resultado) {
                var fotografia = {
                    uuid:                 UUID.v4(),
                    inspeccion_uuid:      inspeccionActual.uuid,
                    nombre_archivo:       null,
                    extension:            resultado.extension,
                    mime_type:            resultado.mime_type,
                    tamano_bytes:         resultado.tamano_bytes,
                    ancho:                resultado.ancho,
                    alto:                 resultado.alto,
                    fecha_hora_captura:   ahoraISO(),
                    latitud:              null,
                    longitud:             null,
                    dispositivo:          null,
                    estado_local:         ESTADO_PENDIENTE_SYNC,
                    blob:                 resultado.blob,
                    thumbnail:            resultado.thumbnail,
                    created_at_local:     ahoraISO(),
                    updated_at_local:     ahoraISO()
                };

                return ALMACEN.guardar(ALMACEN.ALMACENES.fotografias, fotografia)
                    .then(function () {
                        return fotografia;
                    });
            })
            .then(function (fotografia) {
                renderizarFotografia(fotografia);
            })
            .catch(function (error) {
                showErrorAlmacenar(error, 'No fue posible procesar y guardar la fotografía.');
            })
            .finally(function () {
                procesandoFoto.setAttribute('hidden', '');
                inputFoto.removeAttribute('disabled');
                inputFoto.value = '';
            });
    }

    function renderizarFotografia(fotografia) {
        var url = URL.createObjectURL(fotografia.thumbnail);
        urlsActivas.push(url);

        var item   = document.createElement('li');
        item.className = 'nin-foto-item';
        item.dataset.uuid = fotografia.uuid;

        var img = document.createElement('img');
        img.className = 'nin-foto-thumb';
        img.src = url;
        img.alt = 'Fotografía de la inspección';
        img.loading = 'lazy';

        var datos = document.createElement('div');
        datos.className = 'nin-foto-datos';

        var dimensiones = document.createElement('span');
        dimensiones.className = 'nin-foto-metadata';
        dimensiones.textContent = fotografia.ancho + ' × ' + fotografia.alto + ' px';

        var peso = document.createElement('span');
        peso.className = 'nin-foto-metadata';
        peso.textContent = formatearBytes(fotografia.tamano_bytes);

        datos.appendChild(dimensiones);
        datos.appendChild(peso);

        var quitar = document.createElement('button');
        quitar.type = 'button';
        quitar.className = 'btn btn-danger nin-foto-quitar';
        quitar.setAttribute('aria-label', 'Quitar fotografía');
        quitar.innerHTML = '<i class="bi bi-trash" aria-hidden="true"></i>';

        quitar.addEventListener('click', function () {
            var uuid = item.dataset.uuid;

            ALMACEN.eliminar(ALMACEN.ALMACENES.fotografias, uuid).then(function () {
                item.parentNode.removeChild(item);
            }).catch(function () {
                mostrarError('No fue posible quitar la fotografía.');
            });
        });

        item.appendChild(img);
        item.appendChild(datos);
        item.appendChild(quitar);

        listaFotos.appendChild(item);
        listaFotos.removeAttribute('hidden');
    }

    function showErrorAlmacenar(error, mensaje) {
        if (window.console && console.error) {
            console.error('SIGOA: operación local fallida.', error);
        }
        mostrarError(mensaje);
    }

    /* ================================================================
       Inicialización
       ================================================================ */
    function inicializar() {
        if (!contenedor) {
            return;
        }

        if (!ALMACEN || !ALMACEN.soportado()) {
            inputFecha.setAttribute('disabled', 'disabled');
            inputHora.setAttribute('disabled', 'disabled');
            inputObserv.setAttribute('disabled', 'disabled');
            btnGuardar.setAttribute('disabled', 'disabled');
            mostrarError('El almacenamiento local no está disponible; no se puede guardar la inspección en este dispositivo.');
            return;
        }

        inputFecha.value = inputFecha.value || fechaDispositivo();
        inputHora.value  = inputHora.value || horaDispositivo();
    }

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            guardarInspeccion();
        });
    }

    if (inputFoto) {
        inputFoto.addEventListener('change', function () {
            var archivo = inputFoto.files && inputFoto.files.length > 0 ? inputFoto.files[0] : null;

            if (archivo) {
                procesarFotografia(archivo);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', inicializar);
    } else {
        inicializar();
    }
})();