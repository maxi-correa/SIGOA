/**
 * SIGOA — Inspecciones locales de una obra (Fase D.2 / D.3 / D.4)
 *
 * Recupera de IndexedDB (base `SIGOA`, store `inspecciones` por índice
 * `por_obra`) las inspecciones creadas en este dispositivo para la obra
 * actual, y permite ver sus fotografías (store `fotografias` por índice
 * `por_inspeccion`).
 *
 * Desde Fase D.3 coordina la **sincronización manual** con el servidor
 * (botón "Sincronizar", SIGOA.sincronizacion, docs/SIGOA.md §55/§56):
 * informa el resultado, refresca los estados de cada inspección y fotografía,
 * y ante 401 avisa que hay que volver a iniciar sesión.
 *
 * Desde Fase D.4 muestra además el estado de la operación de cola
 * (`PENDIENTE`, `SINCRONIZANDO`, `SINCRONIZADA`, `ERROR`) y ofrece el
 * reintento manual de las operaciones en `ERROR`. Los datos locales nunca
 * se eliminan.
 */
(function () {
    'use strict';

    var ALMACEN = (window.SIGOA && window.SIGOA.almacenamiento) ? window.SIGOA.almacenamiento : null;
    var SINCRONIZACION = (window.SIGOA && window.SIGOA.sincronizacion) ? window.SIGOA.sincronizacion : null;

    var seccion = document.getElementById('inspeccionesLocales');
    var lista   = document.getElementById('inspeccionesLocalesLista');
    var btnSincronizar = document.getElementById('btnSincronizar');
    var alertaEstado   = document.getElementById('sincronizacionEstado');
    var obraId         = seccion ? parseInt(seccion.dataset.obraId, 10) : NaN;

    function formatoFecha(iso) {
        if (!iso) {
            return '';
        }

        var partes = String(iso).split('-');
        if (partes.length !== 3) {
            return iso;
        }

        return partes[2] + '/' + partes[1] + '/' + partes[0];
    }

    function formatoHora(hora) {
        if (!hora) {
            return '';
        }

        var partes = String(hora).split(':');
        return partes.length >= 2 ? partes[0] + ':' + partes[1] : hora;
    }

    function estadoEtiqueta(estado) {
        return {
            'PENDIENTE_SYNC': { texto: 'Pendiente de sincronización', clase: 'io-badge-pendiente' },
            'SINCRONIZADA':   { texto: 'Sincronizada',                clase: 'io-badge-sincronizada' },
            'ERROR':          { texto: 'Error de sincronización',     clase: 'io-badge-error' },
            'BORRADOR':       { texto: 'Borrador',                    clase: 'io-badge-borrador' }
        }[estado] || { texto: estado || 'Local', clase: 'io-badge-local' };
    }

    function renderVacio() {
        var item = document.createElement('li');
        item.className = 'io-locales-vacio';

        item.textContent = 'No hay inspecciones guardadas en este dispositivo para esta obra.';

        lista.appendChild(item);
    }

    function etiquetaOperacion(operacion) {
        if (!operacion) {
            return null;
        }

        if (operacion.estado === 'SINCRONIZANDO') {
            return { texto: 'Sincronizando…', clase: 'io-badge-pendiente' };
        }

        if (operacion.estado === 'SINCRONIZADA') {
            return { texto: 'Enviada al servidor', clase: 'io-badge-sincronizada' };
        }

        if (operacion.estado === 'ERROR') {
            return { texto: 'Reintento manual requerido', clase: 'io-badge-error' };
        }

        return { texto: 'En cola', clase: 'io-badge-local' };
    }

    /**
     * Botón de reintento manual de una operación en `ERROR`.
     *
     * El reintento reinicia el contador de intentos y devuelve la entidad a
     * `PENDIENTE_SYNC`; no reconstruye ni borra la entidad.
     */
    function botonReintentar(tipo, entidadUuid) {
        var boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'io-locales-reintentar';
        boton.innerHTML = '<i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Reintentar sincronización';

        boton.addEventListener('click', function () {
            boton.setAttribute('disabled', 'disabled');

            SINCRONIZACION.reintentar(tipo, entidadUuid)
                .then(function () {
                    return renderInspecciones();
                })
                .then(function () {
                    /* El reintento manual intenta el envío de inmediato: la
                       operación vuelve a la cola y, a continuación, se
                       informa el resultado real del ciclo. */
                    return sincronizar();
                })
                .catch(function (error) {
                    if (window.console && console.error) {
                        console.error('SIGOA: no se pudo reintentar la operación.', error);
                    }

                    boton.removeAttribute('disabled');
                });
        });

        return boton;
    }

    function renderFotografia(fotografia, operaciones) {
        var caja = document.createElement('figure');
        caja.className = 'io-locales-foto';

        var operacion = SINCRONIZACION.buscarOperacion(
            operaciones,
            SINCRONIZACION.TIPO_FOTOGRAFIA,
            fotografia.uuid
        );

        if (fotografia.thumbnail) {
            var url = URL.createObjectURL(fotografia.thumbnail);
            var img = document.createElement('img');
            img.src = url;
            img.alt = 'Fotografía de la inspección';
            img.loading = 'lazy';
            caja.appendChild(img);
        } else {
            var aviso = document.createElement('p');
            aviso.className = 'io-locales-fotos-vacio';
            /* Tras la confirmación los blobs se liberan: la miniatura ya no
               existe en el dispositivo, y eso es lo esperado (§56). */
            aviso.textContent = fotografia.ruta_thumbnail
                ? 'Archivo liberado en el dispositivo tras la sincronización.'
                : 'La miniatura local ya no está disponible.';
            caja.appendChild(aviso);
        }

        var pie = document.createElement('figcaption');
        pie.className = 'io-locales-foto-cap';
        pie.textContent = (fotografia.ancho ? fotografia.ancho + ' × ' + fotografia.alto + ' px · ' : '')
            + (fotografia.tamano_bytes ? fotografia.tamano_bytes + ' B' : '');

        caja.appendChild(pie);

        var etiquetaOp = etiquetaOperacion(operacion);

        if (etiquetaOp) {
            var estados = document.createElement('div');
            estados.className = 'io-locales-estados';

            var estado = estadoEtiqueta(fotografia.estado_local);
            var badge = document.createElement('span');
            badge.className = 'io-badge ' + estado.clase;
            badge.textContent = estado.texto;

            var spanOp = document.createElement('span');
            spanOp.className = 'io-badge ' + etiquetaOp.clase;
            spanOp.textContent = etiquetaOp.texto;

            estados.appendChild(badge);
            estados.appendChild(spanOp);
            caja.appendChild(estados);
        } else {
            var soloEstado = estadoEtiqueta(fotografia.estado_local);
            var badgeSolo = document.createElement('span');
            badgeSolo.className = 'io-badge ' + soloEstado.clase;
            badgeSolo.textContent = soloEstado.texto;
            caja.appendChild(badgeSolo);
        }

        if (fotografia.estado_local === 'ERROR' && fotografia.error_local) {
            var motivo = document.createElement('p');
            motivo.className = 'io-locales-item-error';
            motivo.textContent = fotografia.error_local;
            caja.appendChild(motivo);
        }

        if (operacion && operacion.estado === 'ERROR') {
            caja.appendChild(botonReintentar(SINCRONIZACION.TIPO_FOTOGRAFIA, fotografia.uuid));
        }

        return caja;
    }

    function cargarFotografias(inspeccionUuid, contenedor, boton) {
        ALMACEN.buscarPorIndice(ALMACEN.ALMACENES.fotografias, 'por_inspeccion', inspeccionUuid)
            .then(function (fotografias) {
                contenedor.textContent = '';

                if (!fotografias || fotografias.length === 0) {
                    var vacio = document.createElement('p');
                    vacio.className = 'io-locales-fotos-vacio';
                    vacio.textContent = 'Esta inspección no tiene fotografías.';
                    contenedor.appendChild(vacio);
                    return;
                }

                return SINCRONIZACION.obtenerOperaciones()
                    .then(function (operaciones) {
                        fotografias.forEach(function (fotografia) {
                            contenedor.appendChild(renderFotografia(fotografia, operaciones));
                        });

                        contenedor.removeAttribute('hidden');
                        boton.setAttribute('aria-expanded', 'true');
                    });
            })
            .catch(function (error) {
                if (window.console && console.error) {
                    console.error('SIGOA: no se pudieron recuperar las fotografías locales.', error);
                }
            });
    }

    function renderInspeccion(inspeccion, operaciones) {
        var item = document.createElement('li');
        item.className = 'io-locales-item';

        var operacion = SINCRONIZACION.buscarOperacion(
            operaciones,
            SINCRONIZACION.TIPO_INSPECCION,
            inspeccion.uuid
        );

        var encabezado = document.createElement('div');
        encabezado.className = 'io-locales-item-superior';

        var etiqueta = estadoEtiqueta(inspeccion.estado_local);
        var badge = document.createElement('span');
        badge.className = 'io-badge ' + etiqueta.clase;
        badge.textContent = etiqueta.texto;

        var titulo = document.createElement('h3');
        titulo.className = 'io-locales-item-titulo';

        var fechaTxt   = formatoFecha(inspeccion.fecha_inspeccion);
        var horaTxt    = formatoHora(inspeccion.hora_inspeccion);
        titulo.textContent = 'Inspección del ' + (fechaTxt || '—') + (horaTxt ? ' · ' + horaTxt : '') + ' hs';

        encabezado.appendChild(titulo);
        encabezado.appendChild(badge);

        var etiquetaOp = etiquetaOperacion(operacion);

        if (etiquetaOp) {
            var spanOp = document.createElement('span');
            spanOp.className = 'io-badge ' + etiquetaOp.clase;
            spanOp.textContent = etiquetaOp.texto;
            encabezado.appendChild(spanOp);
        }

        var observacion = document.createElement('p');
        observacion.className = 'io-locales-item-obs';
        observacion.textContent = inspeccion.observacion ? inspeccion.observacion : 'Sin observación.';

        item.appendChild(encabezado);
        item.appendChild(observacion);

        if (inspeccion.estado_local === 'ERROR' && inspeccion.error_local) {
            var motivo = document.createElement('p');
            motivo.className = 'io-locales-item-obs io-locales-item-error';
            motivo.textContent = inspeccion.error_local;

            item.appendChild(motivo);
        }

        if (operacion && operacion.estado === 'ERROR' && operacion.error) {
            var motivoOperacion = document.createElement('p');
            motivoOperacion.className = 'io-locales-item-obs io-locales-item-error';
            motivoOperacion.textContent = operacion.error;

            item.appendChild(motivoOperacion);
        }

        if (inspeccion.estado_local === 'SINCRONIZADA' && inspeccion.servidor_id) {
            var meta = document.createElement('p');
            meta.className = 'io-locales-item-obs io-locales-item-servidor';
            meta.textContent = 'ID en servidor: ' + inspeccion.servidor_id;

            item.appendChild(meta);
        }

        if (operacion && operacion.estado === 'ERROR') {
            item.appendChild(botonReintentar(SINCRONIZACION.TIPO_INSPECCION, inspeccion.uuid));
        }

        var boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'io-locales-fotos-toggle';
        boton.setAttribute('aria-expanded', 'false');
        boton.innerHTML = '<i class="bi bi-images" aria-hidden="true"></i> Ver fotografías';

        var fotosContenedor = document.createElement('div');
        fotosContenedor.className = 'io-locales-fotos';
        fotosContenedor.setAttribute('hidden', '');

        boton.addEventListener('click', function () {
            var oculto = fotosContenedor.hasAttribute('hidden');

            if (oculto) {
                cargarFotografias(inspeccion.uuid, fotosContenedor, boton);
            } else {
                fotosContenedor.setAttribute('hidden', '');
                boton.setAttribute('aria-expanded', 'false');
            }
        });

        item.appendChild(boton);
        item.appendChild(fotosContenedor);

        lista.appendChild(item);
    }

    function renderInspecciones() {
        if (!seccion || !lista) {
            return Promise.resolve();
        }

        lista.textContent = '';

        return Promise.all([
            ALMACEN.buscarPorIndice(ALMACEN.ALMACENES.inspecciones, 'por_obra', obraId),
            SINCRONIZACION.obtenerOperaciones()
        ]).then(function (resultados) {
            var inspecciones = resultados[0] || [];
            var operaciones = resultados[1] || [];

            if (inspecciones.length === 0) {
                renderVacio();
                return;
            }

            inspecciones
                .slice()
                .sort(function (a, b) {
                    var fa = a.fecha_inspeccion || '';
                    var fb = b.fecha_inspeccion || '';

                    if (fa === fb) {
                        return String(b.uuid).localeCompare(String(a.uuid));
                    }

                    return fa < fb ? 1 : -1;
                })
                .forEach(function (inspeccion) {
                    renderInspeccion(inspeccion, operaciones);
                });
        });
    }

    /* ================================================================
       Sincronización manual (Fase D.3)
       ================================================================ */

    function mostrarAlerta(tipo, icono, mensaje) {
        if (!alertaEstado) {
            return;
        }

        alertaEstado.textContent = '';

        var alerta = document.createElement('div');
        alerta.className = 'alert ' + tipo;
        alerta.setAttribute('role', 'alert');

        var iconoEl = document.createElement('span');
        iconoEl.className = 'alert-icon';
        iconoEl.innerHTML = icono;

        var texto = document.createElement('span');
        texto.textContent = mensaje;

        alerta.appendChild(iconoEl);
        alerta.appendChild(texto);

        alertaEstado.appendChild(alerta);
        alertaEstado.removeAttribute('hidden');
    }

    function plural(cantidad, singular, pluralForma) {
        return cantidad + ' ' + (cantidad === 1 ? singular : pluralForma);
    }

    function textoResumen(resumen) {
        if (resumen.enCurso) {
            return 'Ya hay una sincronización en curso.';
        }

        if (resumen.sinConexion) {
            return 'El dispositivo no tiene conexión. Las inspecciones y fotografías quedan en cola.';
        }

        if (resumen.error === 'NO_LOCAL_STORAGE') {
            return 'Este dispositivo no permite almacenamiento local, por lo que no se puede sincronizar.';
        }

        if (resumen.authRequerida || resumen.error === 'AUTH_REQUIRED') {
            return 'Tu sesión expiró. Volvé a iniciar sesión y la cola continuará sola. Las inspecciones y fotografías del dispositivo se conservan.';
        }

        if (resumen.prohibido || resumen.error === 'FORBIDDEN') {
            return 'No tenés permisos para sincronizar en esta sesión. Recargá la página e intentá nuevamente.';
        }

        if (resumen.error === 'RED') {
            return 'No fue posible conectarse con el servidor. Verificá tu conexión; la cola reintentará automáticamente.';
        }

        if (resumen.error) {
            return 'El servidor no pudo procesar la sincronización. Recargá la página e intentá nuevamente.';
        }

        if (resumen.vacio) {
            return 'No hay inspecciones ni fotografías pendientes de sincronización.';
        }

        var partes = [];
        var insp = resumen.inspecciones || {};
        var fotos = resumen.fotografias || {};

        if (insp.sincronizadas > 0) {
            partes.push(plural(insp.sincronizadas, 'inspección sincronizada', 'inspecciones sincronizadas'));
        }

        if (insp.yaSincronizadas > 0) {
            partes.push(plural(insp.yaSincronizadas, 'inspección ya estaba sincronizada', 'inspecciones ya estaban sincronizadas'));
        }

        if (fotos.sincronizadas > 0) {
            partes.push(plural(fotos.sincronizadas, 'fotografía sincronizada', 'fotografías sincronizadas'));
        }

        if (fotos.bloqueadas > 0) {
            partes.push(plural(fotos.bloqueadas, 'fotografía en espera de su inspección', 'fotografías en espera de su inspección'));
        }

        if (insp.rechazadas > 0) {
            partes.push(plural(insp.rechazadas, 'inspección rechazada', 'inspecciones rechazadas'));
        }

        if (insp.errores > 0 || fotos.errores > 0) {
            partes.push(plural((insp.errores || 0) + (fotos.errores || 0), 'operación con error', 'operaciones con error'));
        }

        return 'Sincronización finalizada: ' + (partes.join(', ') || 'sin cambios') + '.';
    }

    function nivelAlerta(resumen) {
        if (resumen.enCurso || resumen.sinConexion || resumen.vacio) {
            return 'alert-info';
        }

        if (resumen.authRequerida || resumen.prohibido || resumen.error) {
            return 'alert-danger';
        }

        var insp = resumen.inspecciones || {};
        var fotos = resumen.fotografias || {};

        if ((insp.rechazadas || 0) > 0 || (insp.errores || 0) > 0 || (fotos.errores || 0) > 0) {
            return 'alert-warning';
        }

        return 'alert-success';
    }

    function iconoAlerta(resumen) {
        if (nivelAlerta(resumen) === 'alert-success') {
            return '<i class="bi bi-check-circle" aria-hidden="true"></i>';
        }

        if (nivelAlerta(resumen) === 'alert-warning') {
            return '<i class="bi bi-exclamation-triangle" aria-hidden="true"></i>';
        }

        if (nivelAlerta(resumen) === 'alert-danger') {
            return '<i class="bi bi-exclamation-circle" aria-hidden="true"></i>';
        }

        return '<i class="bi bi-info-circle" aria-hidden="true"></i>';
    }

    function sincronizar() {
        if (!btnSincronizar || !SINCRONIZACION) {
            return;
        }

        btnSincronizar.setAttribute('disabled', 'disabled');

        /* El ciclo completo (inspecciones y después fotografías) porque la
           página muestra ambos estados y ambos pueden estar en cola. */
        SINCRONIZACION.sincronizarTodo({ obraId: obraId })
            .then(function (resumen) {
                mostrarAlerta(nivelAlerta(resumen), iconoAlerta(resumen), textoResumen(resumen));

                return renderInspecciones();
            })
            .catch(function (error) {
                if (window.console && console.error) {
                    console.error('SIGOA: falló la sincronización.', error);
                }

                mostrarAlerta('alert-danger', '<i class="bi bi-exclamation-circle" aria-hidden="true"></i>', 'No fue posible sincronizar. Verificá tu conexión e intentá nuevamente.');
            })
            .finally(function () {
                btnSincronizar.removeAttribute('disabled');
            });
    }

    function inicializar() {
        if (!seccion || !lista || !ALMACEN || !ALMACEN.soportado()) {
            return;
        }

        if (!Number.isFinite(obraId)) {
            return;
        }

        seccion.removeAttribute('hidden');

        if (btnSincronizar && SINCRONIZACION) {
            btnSincronizar.addEventListener('click', sincronizar);
        }

        renderInspecciones();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', inicializar);
    } else {
        inicializar();
    }
})();