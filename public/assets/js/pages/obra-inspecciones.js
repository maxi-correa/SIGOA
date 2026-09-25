/**
 * SIGOA — Inspecciones locales de una obra (Fase D.2 / D.3)
 *
 * Recupera de IndexedDB (base `SIGOA`, store `inspecciones` por índice
 * `por_obra`) las inspecciones creadas en este dispositivo para la obra
 * actual, y permite ver sus fotografías (store `fotografias` por índice
 * `por_inspeccion`).
 *
 * Desde Fase D.3 también coordina la **sincronización manual** de las
 * inspecciones pendientes con el servidor (botón "Sincronizar",
 * SIGOA.sincronizacion, docs/SIGOA.md §55): informa el resultado, refresca
 * los estados de cada inspección (SINCRONIZADA / ERROR) y ante 401 avisa
 * que hay que volver a iniciar sesión. Los datos locales nunca se eliminan.
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

                fotografias.forEach(function (fotografia) {
                    if (!fotografia.thumbnail) {
                        return;
                    }

                    var caja = document.createElement('figure');
                    caja.className = 'io-locales-foto';

                    var url  = URL.createObjectURL(fotografia.thumbnail);
                    var img  = document.createElement('img');
                    img.src = url;
                    img.alt = 'Fotografía de la inspección';
                    img.loading = 'lazy';

                    var pie = document.createElement('figcaption');
                    pie.className = 'io-locales-foto-cap';
                    pie.textContent = (fotografia.ancho ? fotografia.ancho + ' × ' + fotografia.alto + ' px · ' : '')
                        + (fotografia.tamano_bytes ? fotografia.tamano_bytes + ' B' : '');

                    caja.appendChild(img);
                    caja.appendChild(pie);

                    contenedor.appendChild(caja);
                });

                contenedor.removeAttribute('hidden');
                boton.setAttribute('aria-expanded', 'true');
            })
            .catch(function (error) {
                if (window.console && console.error) {
                    console.error('SIGOA: no se pudieron recuperar las fotografías locales.', error);
                }
            });
    }

    function renderInspeccion(inspeccion) {
        var item = document.createElement('li');
        item.className = 'io-locales-item';

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

        if (inspeccion.estado_local === 'SINCRONIZADA' && inspeccion.servidor_id) {
            var meta = document.createElement('p');
            meta.className = 'io-locales-item-obs io-locales-item-servidor';
            meta.textContent = 'ID en servidor: ' + inspeccion.servidor_id;

            item.appendChild(meta);
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

        return ALMACEN.buscarPorIndice(ALMACEN.ALMACENES.inspecciones, 'por_obra', obraId)
            .then(function (inspecciones) {
                if (!inspecciones || inspecciones.length === 0) {
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
                    .forEach(renderInspeccion);
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

    function textoResumen(resumen) {
        if (resumen.vacio) {
            return 'No hay inspecciones pendientes de sincronización.';
        }

        if (resumen.error === 'AUTH_REQUIRED') {
            return 'Tu sesión expiró. Volvé a iniciar sesión y volvé a sincronizar. Las inspecciones del dispositivo se conservan.';
        }

        if (resumen.error === 'RED') {
            return 'No fue posible conectarse con el servidor. Verificá tu conexión e intentá nuevamente.';
        }

        if (resumen.error === 'FORBIDDEN') {
            return 'No tenés permisos para sincronizar en esta sesión. Recargá la página e intentá nuevamente.';
        }

        if (resumen.error) {
            return 'El servidor no pudo procesar la sincronización. Recargá la página e intentá nuevamente.';
        }

        var partes = [];

        if (resumen.sincronizadas > 0) {
            partes.push(resumen.sincronizadas + ' sincronizada' + (resumen.sincronizadas === 1 ? '' : 's'));
        }

        if (resumen.yaSincronizadas > 0) {
            partes.push(resumen.yaSincronizadas + ' ya sincronizada' + (resumen.yaSincronizadas === 1 ? '' : 's'));
        }

        if (resumen.rechazadas > 0) {
            partes.push(resumen.rechazadas + ' rechazada' + (resumen.rechazadas === 1 ? '' : 's') + ' (ver motivo en cada inspección)');
        }

        return 'Sincronización finalizada: ' + (partes.join(', ') || 'sin cambios') + '.';
    }

    function sincronizar() {
        if (!btnSincronizar || !SINCRONIZACION) {
            return;
        }

        btnSincronizar.setAttribute('disabled', 'disabled');

        SINCRONIZACION.sincronizarInspecciones(obraId)
            .then(function (resumen) {
                if (resumen.vacio) {
                    mostrarAlerta('alert-info', '<i class="bi bi-info-circle" aria-hidden="true"></i>', textoResumen(resumen));
                } else if ((resumen.error === 'AUTH_REQUIRED') || resumen.error === 'RED' || resumen.error === 'FORBIDDEN' || resumen.error) {
                    mostrarAlerta('alert-danger', '<i class="bi bi-exclamation-circle" aria-hidden="true"></i>', textoResumen(resumen));
                } else if (resumen.rechazadas > 0 || resumen.errores > 0) {
                    mostrarAlerta('alert-warning', '<i class="bi bi-exclamation-triangle" aria-hidden="true"></i>', textoResumen(resumen));
                } else {
                    mostrarAlerta('alert-success', '<i class="bi bi-check-circle" aria-hidden="true"></i>', textoResumen(resumen));
                }

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