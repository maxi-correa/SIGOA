/**
 * SIGOA — Inspecciones locales de una obra (Fase D.2)
 *
 * Recupera de IndexedDB (base `SIGOA`, store `inspecciones` por índice
 * `por_obra`) las inspecciones creadas en este dispositivo para la obra
 * actual, y permite ver sus fotografías (store `fotografias` por índice
 * `por_inspeccion`).
 *
 * Es una lectura puramente local: no consulta ni notifica al servidor.
 * Si el navegador no soporta IndexedDB, la sección permanece oculta.
 */
(function () {
    'use strict';

    var ALMACEN = (window.SIGOA && window.SIGOA.almacenamiento) ? window.SIGOA.almacenamiento : null;

    var seccion = document.getElementById('inspeccionesLocales');
    var lista   = document.getElementById('inspeccionesLocalesLista');

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
            'BORRADOR':       { texto: 'Borrador',                   clase: 'io-badge-borrador' }
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

        var fotosContenedor = document.createElement('div');
        fotosContenedor.className = 'io-locales-fotos';
        fotosContenedor.setAttribute('hidden', '');

        var boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'io-locales-fotos-toggle';
        boton.setAttribute('aria-expanded', 'false');
        boton.innerHTML = '<i class="bi bi-images" aria-hidden="true"></i> Ver fotografías';

        boton.addEventListener('click', function () {
            var oculto = fotosContenedor.hasAttribute('hidden');

            if (oculto) {
                cargarFotografias(inspeccion.uuid, fotosContenedor, boton);
            } else {
                fotosContenedor.setAttribute('hidden', '');
                boton.setAttribute('aria-expanded', 'false');
            }
        });

        item.appendChild(encabezado);
        item.appendChild(observacion);
        item.appendChild(boton);
        item.appendChild(fotosContenedor);

        lista.appendChild(item);
    }

    function inicializar() {
        if (!seccion || !lista || !ALMACEN || !ALMACEN.soportado()) {
            return;
        }

        var obraId = parseInt(seccion.dataset.obraId, 10);

        if (!Number.isFinite(obraId)) {
            return;
        }

        ALMACEN.buscarPorIndice(ALMACEN.ALMACENES.inspecciones, 'por_obra', obraId)
            .then(function (inspecciones) {
                seccion.removeAttribute('hidden');

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
            })
            .catch(function (error) {
                if (window.console && console.error) {
                    console.error('SIGOA: no se pudieron recuperar las inspecciones locales.', error);
                }
            });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', inicializar);
    } else {
        inicializar();
    }
})();