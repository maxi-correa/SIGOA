/* ===================================================================
   SIGOA — Documentación disponible del inspector (online + offline)
   ===================================================================
   La misma pantalla sirve para dos situaciones:

   * **Con conexión**: el listado de documentos vigentes lo entrega el
     servidor y los PDF se abren desde él.
   * **Sin conexión**: el listado y los PDF proceden de las copias
     descargadas en el dispositivo (IndexedDB, store `documentos`).

   El render es único: la vista en línea (`inspector/documentacion.php`)
   y el shell sin conexión (`assets/offline/documentacion.html`) entregan
   los mismos elementos y este módulo los pinta igual.

   Modos de trabajo:

   * `obraId > 0` — pantalla de una obra: se combina el listado vigente
     del servidor con las copias locales de esa obra;
   * `obraId = 0` — pantalla general sin conexión: se listan las copias
     descargadas de todas las obras del inspector, agrupadas por obra.

   Reglas de la copia local:

   * cada copia pertenece a un usuario y a una obra
     (`clave = usuario_id::obra_id::documento_logico_id`), de modo que dos
     cuentas que usen el mismo dispositivo no ven la documentación de la
     otra;
   * el emparejamiento con el servidor se hace por **documento lógico**
     (`documento_padre_id` o el propio `id`), no por el identificador
     físico del PDF: al reemplazarse una versión cambia el archivo y el
     documento, pero no la identidad lógica;
   * la actualización de una copia nunca se hace "en sitio": se descarga la
     versión nueva a una clave temporal, se verifica y solo entonces se
     sustituye la anterior (transacción atómica). Si algo falla, la copia
     que ya funcionaba se conserva.

   La capa de almacenamiento es `SIGOA.almacenamiento` (IndexedDB v3).
   =================================================================== */

(function () {
    'use strict';

    var ALMACEN = (window.SIGOA && window.SIGOA.almacenamiento) ? window.SIGOA.almacenamiento : null;
    var CONECTIVIDAD = (window.SIGOA && window.SIGOA.conectividad) ? window.SIGOA.conectividad : null;

    var ESTADOS = {
        GUARDADO: 'GUARDADO',
        ACTUALIZACION: 'ACTUALIZACION',
        PENDIENTE: 'PENDIENTE',
        NO_VIGENTE: 'NO_VIGENTE'
    };

    var FILTROS = {
        TODOS: 'todos',
        OFFLINE: 'offline',
        PENDIENTES: 'pendientes'
    };

    var ESTADO = {
        obraId: 0,
        obra: null,
        usuarioId: 0,
        urlApi: '',
        documentos: [],
        locales: {},
        filtro: FILTROS.TODOS,
        enLinea: false,
        sinServidor: false,
        ocupado: {}
    };

    var elementos = {};

    /* ================================================================
       Funciones puras (verificables de forma aislada)
       ================================================================ */

    /**
     * Clave de una copia local: usuario, obra y documento lógico.
     */
    function claveDocumento(usuarioId, obraId, logicoId) {
        return usuarioId + '::' + obraId + '::' + logicoId;
    }

    /**
     * Clave del conjunto de copias de un usuario sobre una obra.
     */
    function claveUsuarioObra(usuarioId, obraId) {
        return usuarioId + '::' + obraId;
    }

    /**
     * Clave de emparejamiento entre el servidor y la copia local.
     */
    function claveLogico(obraId, logicoId) {
        return obraId + '::' + logicoId;
    }

    /**
     * Estado de un documento frente a su copia local.
     *
     * * `PENDIENTE`     — no hay copia local: hay que descargarlo;
     * * `GUARDADO`      — la copia local corresponde a la versión vigente;
     * * `ACTUALIZACION` — hay copia local, pero el servidor ofrece una
     *   versión más reciente;
     * * `NO_VIGENTE`    — hay copia local, pero el documento ya no figura
     *   entre los vigentes de la obra.
     */
    function estadoDocumento(documento, local) {
        if (!local) {
            return ESTADOS.PENDIENTE;
        }

        if (!documento) {
            return ESTADOS.NO_VIGENTE;
        }

        if (Number(local.version) !== Number(documento.version)) {
            return ESTADOS.ACTUALIZACION;
        }

        return ESTADOS.GUARDADO;
    }

    /**
     * Empareja los documentos vigentes del servidor con las copias locales.
     *
     * Las copias locales cuyo documento ya no figura entre los vigentes se
     * conservan en el listado: son del inspector y puede interestarle
     * saber que dejó de estar vigente, pero se señalan como tal.
     *
     * @param {Array}  documentos Documentos vigentes del servidor.
     * @param {Object} locales     Copias locales indexadas por
     *                             `obra_id::documento_logico_id`.
     * @param {Object} opciones    `{ sinServidor: true }` cuando no hay
     *                             listado del servidor (modo general sin
     *                             conexión): las copias no pueden estar
     *                             "caducadas" porque no hay con qué
     *                             compararlas.
     */
    function combinar(documentos, locales, opciones) {
        var config = opciones || {};
        var mapa = locales || {};
        var resultado = [];
        var vistos = {};

        (documentos || []).forEach(function (documento) {
            var clave = claveLogico(
                Number(documento.obra_id || 0),
                Number(documento.logico_id)
            );

            vistos[clave] = true;

            var local = mapa[clave] || null;

            resultado.push({
                documento: documento,
                local: local,
                estado: estadoDocumento(documento, local)
            });
        });

        Object.keys(mapa).forEach(function (clave) {
            if (vistos[clave]) {
                return;
            }

            resultado.push({
                documento: null,
                local: mapa[clave],
                estado: config.sinServidor ? ESTADOS.GUARDADO : ESTADOS.NO_VIGENTE
            });
        });

        return resultado;
    }

    /**
     * Aplica el filtro activo sobre las entradas combinadas.
     *
     * * `todos`      — todo lo que la obra tiene;
     * * `offline`    — lo que puede abrirse sin conexión (tiene copia);
     * * `pendientes` — lo que requiere una descarga (no tiene copia, o la
     *   que tiene está desactualizada).
     */
    function filtrar(entradas, filtro) {
        var lista = entradas || [];

        if (filtro === FILTROS.OFFLINE) {
            return lista.filter(function (entrada) {
                return entrada.local !== null;
            });
        }

        if (filtro === FILTROS.PENDIENTES) {
            return lista.filter(function (entrada) {
                return entrada.estado === ESTADOS.PENDIENTE
                    || entrada.estado === ESTADOS.ACTUALIZACION;
            });
        }

        return lista.slice();
    }

    function formatearTamano(bytes) {
        var total = Number(bytes);

        if (!isFinite(total) || total <= 0) {
            return '';
        }

        if (total < 1024) {
            return total + ' B';
        }

        if (total < 1024 * 1024) {
            return Math.round(total / 1024) + ' KB';
        }

        return (Math.round((total / (1024 * 1024)) * 10) / 10) + ' MB';
    }

    function formatearFecha(iso) {
        if (!iso) {
            return '';
        }

        var partes = String(iso).slice(0, 10).split('-');

        if (partes.length !== 3) {
            return String(iso);
        }

        return partes[2] + '/' + partes[1] + '/' + partes[0];
    }

    /* ================================================================
       Utilidades de DOM
       ================================================================ */

    function vaciar(elemento) {
        if (!elemento) {
            return;
        }

        while (elemento.firstChild) {
            elemento.removeChild(elemento.firstChild);
        }
    }

    function crear(tag, clase, texto) {
        var nodo = document.createElement(tag);

        if (clase) {
            nodo.className = clase;
        }

        if (texto !== undefined && texto !== null) {
            nodo.textContent = texto;
        }

        return nodo;
    }

    function icono(clase) {
        var nodo = document.createElement('i');
        nodo.className = clase;
        nodo.setAttribute('aria-hidden', 'true');

        return nodo;
    }

    function boton(texto, clase, claseIcono, accion) {
        var nodo = crear('button', 'btn ' + clase, null);
        nodo.type = 'button';

        if (claseIcono) {
            nodo.appendChild(icono(claseIcono));
        }

        nodo.appendChild(crear('span', null, texto));

        nodo.addEventListener('click', function () {
            accion(nodo);
        });

        return nodo;
    }

    function mostrarAviso(clase, texto) {
        if (!elementos.aviso || !elementos.avisoTexto) {
            return;
        }

        elementos.aviso.className = 'alert ' + clase + ' did-aviso';
        elementos.avisoTexto.textContent = texto;
        elementos.aviso.removeAttribute('hidden');
    }

    function ocultarAviso() {
        if (!elementos.aviso || !elementos.avisoTexto) {
            return;
        }

        elementos.aviso.setAttribute('hidden', '');
        elementos.avisoTexto.textContent = '';
    }

    /* ================================================================
       Confirmación de acciones destructivas (RNF §36)
       ================================================================ */

    function confirmar(titulo, mensaje, textoAccion, alConfirmar) {
        var overlay = crear('div', 'modal-overlay');
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');

        var modal = crear('div', 'modal');

        var cabecera = crear('div', 'modal-title');
        cabecera.appendChild(icono('bi bi-exclamation-triangle'));
        cabecera.appendChild(crear('span', null, titulo));
        modal.appendChild(cabecera);

        modal.appendChild(crear('p', 'modal-message', mensaje));

        var acciones = crear('div', 'modal-actions');

        var cancelar = crear('button', 'btn btn-cancelar', 'Cancelar');
        cancelar.type = 'button';
        cancelar.addEventListener('click', cerrar);

        var confirmarBoton = crear('button', 'btn btn-danger', textoAccion);
        confirmarBoton.type = 'button';
        confirmarBoton.addEventListener('click', function () {
            cerrar();
            alConfirmar();
        });

        acciones.appendChild(cancelar);
        acciones.appendChild(confirmarBoton);
        modal.appendChild(acciones);

        overlay.appendChild(modal);

        function cerrar() {
            if (overlay.parentNode) {
                overlay.parentNode.removeChild(overlay);
            }

            document.removeEventListener('keydown', alPulsarEscape);
        }

        function alPulsarEscape(evento) {
            if (evento.key === 'Escape' || evento.keyCode === 27) {
                cerrar();
            }
        }

        overlay.addEventListener('click', function (evento) {
            if (evento.target === overlay) {
                cerrar();
            }
        });

        document.addEventListener('keydown', alPulsarEscape);
        document.body.appendChild(overlay);

        if (typeof cancelar.focus === 'function') {
            cancelar.focus();
        }
    }

    /* ================================================================
       Carga de datos
       ================================================================ */

    function leerPayload() {
        var nodo = document.getElementById('didDatos');

        if (!nodo) {
            return null;
        }

        try {
            return JSON.parse(nodo.textContent || nodo.innerHTML || 'null');
        } catch (error) {
            return null;
        }
    }

    /**
     * Identificador de la obra deducido de la URL.
     *
     * Valen las dos rutas del área del inspector:
     * `/inspector/documentacion/<id>` y `/inspector/obras/ver/<id>`.
     */
    function obraIdDesdeUrl() {
        var partes = String(window.location.pathname || '').split('/');

        for (var i = 0; i < partes.length - 1; i++) {
            if (partes[i] === 'documentacion' || partes[i] === 'ver') {
                var id = parseInt(partes[i + 1], 10);

                if (!isNaN(id)) {
                    return id;
                }
            }
        }

        return 0;
    }

    function usuarioIdDesdeMeta() {
        if (typeof document.querySelector !== 'function') {
            return 0;
        }

        var meta = document.querySelector('meta[name="sigOA-usuario-id"]');

        if (!meta) {
            return 0;
        }

        var id = parseInt(meta.getAttribute('content'), 10);

        return isNaN(id) ? 0 : id;
    }

    /**
     * Perfil local activo: a qué cuenta pertenecen las copias descargadas.
     *
     * Se escribe al entrar a una pantalla autenticada y se borra al cerrar
     * sesión. El shell sin conexión no puede mostrar nada si no existe un
     * perfil vigente.
     */
    function leerPerfil() {
        if (!ALMACEN) {
            return Promise.resolve(null);
        }

        return ALMACEN.obtener(ALMACEN.ALMACENES.perfiles, 'activo')
            .then(function (perfil) {
                return perfil || null;
            })
            .catch(function () {
                return null;
            });
    }

    function cargarLocales() {
        if (!ALMACEN || !ESTADO.usuarioId) {
            ESTADO.locales = {};
            return Promise.resolve();
        }

        var promesa;

        if (ESTADO.obraId) {
            promesa = ALMACEN.buscarPorIndice(
                ALMACEN.ALMACENES.documentos,
                'por_usuario_obra',
                claveUsuarioObra(ESTADO.usuarioId, ESTADO.obraId)
            );
        } else {
            promesa = ALMACEN.buscarPorIndice(
                ALMACEN.ALMACENES.documentos,
                'por_usuario',
                ESTADO.usuarioId
            );
        }

        return promesa.then(function (registros) {
            var mapa = {};

            (registros || []).forEach(function (registro) {
                /* Las copias temporales son restos de una actualización
                   interrumpida: no se muestran y se descartan. */
                if (registro.temporal) {
                    ALMACEN.eliminar(ALMACEN.ALMACENES.documentos, registro.clave);
                    return;
                }

                mapa[claveLogico(Number(registro.obra_id), Number(registro.documento_logico_id))] = registro;
            });

            ESTADO.locales = mapa;
        }).catch(function () {
            ESTADO.locales = {};
        });
    }

    /* ================================================================
       Conectividad
       ================================================================ */

    /**
     * Consulta al servidor: confirma que hay conexión **y** que la sesión
     * sigue vigente. `navigator.onLine` no basta (docs/SIGOA.md §53).
     */
    function comprobarServidor() {
        if (typeof window.fetch !== 'function' || !ESTADO.urlApi) {
            return Promise.resolve({ enLinea: false, payload: null, mensaje: null });
        }

        return window.fetch(ESTADO.urlApi, {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (respuesta) {
            if (respuesta.status === 401) {
                return {
                    enLinea: true,
                    payload: null,
                    mensaje: 'Su sesión no está activa. Inicie sesión nuevamente para ver la documentación actualizada.'
                };
            }

            if (!respuesta.ok) {
                return {
                    enLinea: true,
                    payload: null,
                    mensaje: 'El servidor no pudo entregar el listado de la documentación.'
                };
            }

            return respuesta.json().then(function (datos) {
                return {
                    enLinea: true,
                    payload: (datos && datos.payload) ? datos.payload : null,
                    mensaje: null
                };
            });
        }).catch(function () {
            return { enLinea: false, payload: null, mensaje: null };
        });
    }

    function pintarConexion() {
        if (!elementos.conexion || !elementos.conexionTexto) {
            return;
        }

        elementos.conexion.className = 'did-conexion ' + (ESTADO.enLinea ? 'is-online' : 'is-offline');

        var iconoConexion = elementos.conexion.querySelector('.did-conexion-icono i');
        if (iconoConexion) {
            iconoConexion.className = ESTADO.enLinea ? 'bi bi-cloud-check' : 'bi bi-cloud-slash';
        }

        elementos.conexionTexto.textContent = ESTADO.enLinea
            ? 'Conectado con el servidor. La documentación está actualizada.'
            : 'Sin conexión con el servidor. Se muestran los documentos descargados en este dispositivo.';
    }

    /* ================================================================
       Render
       ================================================================ */

    function distintivo(estado) {
        var nodo;

        if (estado === ESTADOS.GUARDADO) {
            nodo = crear('span', 'badge did-estado-guardado', 'Disponible offline');
        } else if (estado === ESTADOS.ACTUALIZACION) {
            nodo = crear('span', 'badge did-estado-actualizable', 'Versión nueva disponible');
        } else if (estado === ESTADOS.NO_VIGENTE) {
            nodo = crear('span', 'badge did-estado-pendiente', 'Ya no vigente');
        } else {
            nodo = crear('span', 'badge did-estado-pendiente', 'Pendiente de descargar');
        }

        return nodo;
    }

    function crearTarjeta(entrada) {
        var documento = entrada.documento;
        var local = entrada.local;

        var item = crear('li', 'did-doc');

        var cuerpo = crear('div', 'did-doc-cuerpo');

        var cabecera = crear('div', 'did-doc-cabecera');

        var titulo = crear('div', 'did-doc-titulo');
        titulo.appendChild(icono('bi bi-file-earmark-pdf'));
        titulo.appendChild(crear('span', null, (documento && documento.tipo_documento)
            ? documento.tipo_documento
            : (local && local.tipo_documento) ? local.tipo_documento : 'Documento'));
        cabecera.appendChild(titulo);
        cabecera.appendChild(distintivo(entrada.estado));

        cuerpo.appendChild(cabecera);

        /* En el modo general sin conexión cada tarjeta indica a qué obra
           pertenece: el inspector puede tener documentos de varias. */
        if (!ESTADO.obraId && local) {
            cuerpo.appendChild(crear('div', 'did-doc-obra', local.obra_nombre || local.obra_codigo || ''));
        }

        var nombre = (documento && documento.nombre) ? documento.nombre : (local ? local.nombre : '');
        if (nombre) {
            cuerpo.appendChild(crear('div', 'did-doc-nombre', nombre));
        }

        var meta = crear('div', 'did-doc-meta');

        function agregarMeta(texto) {
            if (!texto) {
                return;
            }

            if (meta.childNodes.length > 0) {
                meta.appendChild(crear('span', 'did-doc-meta-sep', '·'));
            }

            meta.appendChild(crear('span', null, texto));
        }

        var version = documento ? documento.version : (local ? local.version : 0);
        agregarMeta('Versión ' + version);

        var fecha = documento ? documento.fecha_documento : (local ? local.fecha_documento : '');
        agregarMeta(formatearFecha(fecha));

        if (local) {
            agregarMeta('Descargado el ' + formatearFecha(local.fecha_descarga));
            agregarMeta(formatearTamano(local.tamano_bytes));
        } else if (documento) {
            agregarMeta(formatearTamano(documento.tamano_bytes));
        }

        if (meta.childNodes.length > 0) {
            cuerpo.appendChild(meta);
        }

        item.appendChild(cuerpo);

        /* --- Acciones --- */
        var acciones = crear('div', 'did-doc-acciones');

        var puedeVer = local !== null || (documento && ESTADO.enLinea);
        var ver = boton('Ver', 'btn-primary', 'bi bi-eye', function () {
            verDocumento(entrada);
        });
        ver.disabled = !puedeVer;
        acciones.appendChild(ver);

        if (entrada.estado === ESTADOS.ACTUALIZACION) {
            acciones.appendChild(boton('Actualizar', 'btn-accion-secundaria', 'bi bi-arrow-repeat', function (nodo) {
                descargar(entrada, nodo);
            }));
        } else if (entrada.estado === ESTADOS.PENDIENTE) {
            acciones.appendChild(boton('Descargar', 'btn-accion-secundaria', 'bi bi-download', function (nodo) {
                descargar(entrada, nodo);
            }));
        }

        if (local) {
            acciones.appendChild(boton('Quitar descarga', 'btn-danger', 'bi bi-trash', function () {
                var nombreDocumento = (documento && documento.tipo_documento)
                    ? documento.tipo_documento
                    : (local.tipo_documento || 'documento');

                confirmar(
                    'Quitar la descarga',
                    'Se eliminará del dispositivo la copia de "' + nombreDocumento + '". '
                    + 'El documento del servidor no se modifica.',
                    'Quitar',
                    function () {
                        quitarCopia(entrada);
                    }
                );
            }));
        }

        item.appendChild(acciones);

        return item;
    }

    /**
     * Datos de la obra en el shell sin conexión: se toman de las propias
     * copias locales, que ya guardan el código y el nombre de la obra.
     */
    function pintarObra() {
        var nombre = ESTADO.obra ? (ESTADO.obra.nombre || '') : '';
        var codigo = ESTADO.obra ? (ESTADO.obra.codigo || '') : '';

        if (!nombre) {
            var claves = Object.keys(ESTADO.locales);

            for (var i = 0; i < claves.length; i++) {
                var local = ESTADO.locales[claves[i]];

                if (local && local.obra_nombre) {
                    nombre = local.obra_nombre;
                    codigo = local.obra_codigo || '';
                    break;
                }
            }
        }

        if (elementos.obra) {
            elementos.obra.textContent = nombre || 'Documentación de la obra';
        }

        if (codigo && elementos.obra) {
            elementos.obra.setAttribute('title', codigo);
        }

        if (elementos.volver) {
            elementos.volver.setAttribute(
                'href',
                ESTADO.obraId ? '/inspector/obras/ver/' + ESTADO.obraId : '/inspector/dashboard'
            );
        }
    }

    function render() {
        if (!elementos.lista) {
            return;
        }

        var entradas = combinar(ESTADO.documentos, ESTADO.locales, { sinServidor: ESTADO.sinServidor });
        var visibles = filtrar(entradas, ESTADO.filtro);

        vaciar(elementos.lista);

        visibles.forEach(function (entrada) {
            elementos.lista.appendChild(crearTarjeta(entrada));
        });

        if (elementos.vacio) {
            elementos.vacio.hidden = visibles.length > 0;
        }

        if (elementos.vacioTitulo && elementos.vacioTexto && visibles.length === 0) {
            if (ESTADO.filtro === FILTROS.OFFLINE) {
                elementos.vacioTitulo.textContent = 'No hay documentos descargados';
                elementos.vacioTexto.textContent = 'Descargue los documentos que necesite consultar sin conexión.';
            } else if (ESTADO.filtro === FILTROS.PENDIENTES) {
                elementos.vacioTitulo.textContent = 'No hay descargas pendientes';
                elementos.vacioTexto.textContent = 'Todos los documentos vigentes están descargados y al día en este dispositivo.';
            } else if (ESTADO.enLinea) {
                elementos.vacioTitulo.textContent = 'La obra no tiene documentación vigente';
                elementos.vacioTexto.textContent = 'Todavía no se ha cargado ningún documento para esta obra.';
            } else {
                elementos.vacioTitulo.textContent = 'No hay información disponible sin conexión';
                elementos.vacioTexto.textContent = 'Conéctese al servidor para consultar el listado de la documentación de la obra.';
            }
        }

        if (elementos.resumen) {
            var guardados = entradas.filter(function (entrada) {
                return entrada.estado === ESTADOS.GUARDADO;
            }).length;

            var desactualizados = entradas.filter(function (entrada) {
                return entrada.estado === ESTADOS.ACTUALIZACION;
            }).length;

            var texto = entradas.length === 1
                ? '1 documento'
                : entradas.length + ' documentos';

            texto += ' · ' + guardados + (guardados === 1 ? ' disponible offline' : ' disponibles offline');

            if (desactualizados > 0) {
                texto += ' · ' + desactualizados + (desactualizados === 1
                    ? ' con versión nueva disponible'
                    : ' con versión nueva disponible');
            }

            elementos.resumen.textContent = texto;
        }

        pintarConexion();
        pintarObra();
    }

    /* ================================================================
       Acciones sobre los documentos
       ================================================================ */

    var urlsActivas = [];

    function revocarUrls() {
        urlsActivas.forEach(function (url) {
            URL.revokeObjectURL(url);
        });

        urlsActivas = [];
    }

    function abrir(url) {
        var enlace = document.createElement('a');
        enlace.href = url;
        enlace.target = '_blank';
        enlace.rel = 'noopener';

        document.body.appendChild(enlace);
        enlace.click();
        document.body.removeChild(enlace);
    }

    function verDocumento(entrada) {
        if (entrada.local && entrada.local.archivo) {
            var url = URL.createObjectURL(entrada.local.archivo);
            urlsActivas.push(url);
            abrir(url);
            return;
        }

        if (entrada.documento && entrada.documento.url) {
            abrir(entrada.documento.url);
        }
    }

    function leerBuffer(blob) {
        return new Promise(function (resolver, rechazar) {
            if (typeof FileReader === 'undefined') {
                if (typeof blob.arrayBuffer === 'function') {
                    blob.arrayBuffer().then(resolver, rechazar);
                    return;
                }

                rechazar(new Error('Este navegador no permite leer el archivo descargado.'));
                return;
            }

            var lector = new FileReader();

            lector.onload = function () {
                resolver(lector.result);
            };

            lector.onerror = function () {
                rechazar(lector.error || new Error('No se pudo leer el archivo descargado.'));
            };

            lector.readAsArrayBuffer(blob);
        });
    }

    /**
     * Comprueba que el contenido descargado es realmente un PDF.
     *
     * La firma `%PDF-` debe aparecer al principio del archivo. Si el
     * servidor devolvió una página de error o un archivo truncado, la
     * descarga se descarta en lugar de guardarse como si fuera válida.
     */
    function esPdf(buffer) {
        var bytes = new Uint8Array(buffer, 0, Math.min(1024, buffer.byteLength));
        var texto = '';

        for (var i = 0; i < bytes.length; i++) {
            texto += String.fromCharCode(bytes[i]);
        }

        return texto.indexOf('%PDF-') !== -1;
    }

    function hashArchivo(buffer) {
        if (!window.crypto || !window.crypto.subtle || typeof window.crypto.subtle.digest !== 'function') {
            return Promise.resolve(null);
        }

        return window.crypto.subtle.digest('SHA-256', buffer).then(function (digest) {
            var bytes = new Uint8Array(digest);
            var hex = '';

            for (var i = 0; i < bytes.length; i++) {
                hex += ('0' + bytes[i].toString(16)).slice(-2);
            }

            return hex;
        }).catch(function () {
            return null;
        });
    }

    function construirRegistro(documento, blob, hash) {
        return {
            clave: claveDocumento(ESTADO.usuarioId, ESTADO.obraId, documento.logico_id),
            clave_usuario_obra: claveUsuarioObra(ESTADO.usuarioId, ESTADO.obraId),
            usuario_id: ESTADO.usuarioId,
            obra_id: ESTADO.obraId,
            obra_codigo: ESTADO.obra ? (ESTADO.obra.codigo || '') : '',
            obra_nombre: ESTADO.obra ? (ESTADO.obra.nombre || '') : '',
            documento_id: documento.id,
            documento_logico_id: documento.logico_id,
            tipo_documento: documento.tipo_documento,
            nombre: documento.nombre,
            version: documento.version,
            version_servidor: documento.version,
            fecha_documento: documento.fecha_documento,
            tamano_bytes: blob.size,
            mime_type: blob.type || 'application/pdf',
            hash: hash,
            fecha_descarga: new Date().toISOString(),
            archivo: blob
        };
    }

    /**
     * Guarda la copia local de forma segura.
     *
     * 1. se escribe en una clave temporal;
     * 2. se recupera y se comprueba que la copia es utilizable;
     * 3. solo entonces se sustituye la copia anterior (transacción
     *    atómica) y se borra la temporal.
     *
     * Si cualquier paso falla, la copia anterior permanece intacta.
     */
    function guardarCopia(registro) {
        if (!ALMACEN) {
            return Promise.reject(new Error('El almacenamiento local no está disponible.'));
        }

        var temporal = {
            clave: registro.clave + '::tmp',
            clave_usuario_obra: registro.clave_usuario_obra,
            usuario_id: registro.usuario_id,
            obra_id: registro.obra_id,
            obra_codigo: registro.obra_codigo,
            obra_nombre: registro.obra_nombre,
            documento_id: registro.documento_id,
            documento_logico_id: registro.documento_logico_id,
            tipo_documento: registro.tipo_documento,
            nombre: registro.nombre,
            version: registro.version,
            version_servidor: registro.version_servidor,
            fecha_documento: registro.fecha_documento,
            tamano_bytes: registro.tamano_bytes,
            mime_type: registro.mime_type,
            hash: registro.hash,
            fecha_descarga: registro.fecha_descarga,
            temporal: 1,
            archivo: registro.archivo
        };

        return ALMACEN.guardar(ALMACEN.ALMACENES.documentos, temporal)
            .then(function () {
                return ALMACEN.obtener(ALMACEN.ALMACENES.documentos, temporal.clave);
            })
            .then(function (verificado) {
                if (!verificado || !verificado.archivo
                    || Number(verificado.tamano_bytes) !== Number(registro.tamano_bytes)) {
                    throw new Error('No se pudo verificar la copia descargada.');
                }

                return ALMACEN.reemplazar(ALMACEN.ALMACENES.documentos, registro.clave, registro);
            })
            .then(function () {
                return ALMACEN.eliminar(ALMACEN.ALMACENES.documentos, temporal.clave);
            })
            .then(function () {
                return registro;
            })
            .catch(function (error) {
                return ALMACEN.eliminar(ALMACEN.ALMACENES.documentos, temporal.clave)
                    .catch(function () {
                        return null;
                    })
                    .then(function () {
                        throw error;
                    });
            });
    }

    function descargar(entrada, nodoBoton) {
        var documento = entrada.documento;

        if (!documento) {
            return;
        }

        if (ESTADO.ocupado[documento.logico_id]) {
            return;
        }

        ESTADO.ocupado[documento.logico_id] = true;

        if (nodoBoton) {
            nodoBoton.disabled = true;
        }

        mostrarAviso('alert-info', 'Descargando "' + documento.tipo_documento + '…');

        window.fetch(documento.url, {
            credentials: 'same-origin',
            cache: 'no-store'
        }).then(function (respuesta) {
            if (!respuesta.ok) {
                throw new Error('El servidor no pudo entregar el documento (código ' + respuesta.status + ').');
            }

            return respuesta.blob();
        }).then(function (blob) {
            if (!blob || blob.size === 0) {
                throw new Error('El archivo recibido está vacío.');
            }

            if (documento.tamano_bytes && Number(documento.tamano_bytes) !== Number(blob.size)) {
                throw new Error('El archivo recibido no coincide con el tamaño registrado en el servidor.');
            }

            return leerBuffer(blob).then(function (buffer) {
                if (!esPdf(buffer)) {
                    throw new Error('El archivo recibido no es un PDF válido.');
                }

                return hashArchivo(buffer).then(function (hash) {
                    return construirRegistro(documento, blob, hash);
                });
            });
        }).then(function (registro) {
            return guardarCopia(registro);
        }).then(function (registro) {
            ESTADO.locales[claveLogico(Number(registro.obra_id), Number(registro.documento_logico_id))] = registro;
            ocultarAviso();
            render();
            mostrarAviso('alert-success', '"' + registro.tipo_documento + '" quedó disponible sin conexión.');
        }).catch(function (error) {
            render();
            mostrarAviso('alert-danger', 'No se pudo descargar el documento: '
                + (error && error.message ? error.message : 'error desconocido.'));
        }).then(function () {
            delete ESTADO.ocupado[documento.logico_id];
        });
    }

    function quitarCopia(entrada) {
        var local = entrada.local;

        if (!local || !ALMACEN) {
            return;
        }

        ALMACEN.eliminar(ALMACEN.ALMACENES.documentos, local.clave)
            .then(function () {
                delete ESTADO.locales[claveLogico(Number(local.obra_id), Number(local.documento_logico_id))];
                render();
                mostrarAviso('alert-info', 'Se quitó la copia del documento de este dispositivo.');
            })
            .catch(function (error) {
                mostrarAviso('alert-danger', 'No se pudo quitar la copia: '
                    + (error && error.message ? error.message : 'error desconocido.'));
            });
    }

    /**
     * Actualiza automáticamente las copias locales desactualizadas.
     *
     * Se hace una por una y con el mismo procedimiento seguro de descarga:
     * si una falla, la copia anterior de ese documento se conserva.
     */
    function actualizarDesactualizados() {
        if (!ESTADO.enLinea || !ALMACEN) {
            return Promise.resolve(0);
        }

        var entradas = combinar(ESTADO.documentos, ESTADO.locales, { sinServidor: ESTADO.sinServidor })
            .filter(function (entrada) {
                return entrada.estado === ESTADOS.ACTUALIZACION;
            });

        var actualizados = 0;

        return entradas.reduce(function (cadena, entrada) {
            return cadena.then(function () {
                return new Promise(function (resolver) {
                    var antes = entrada.local;

                    descargar(entrada, null);

                    /* `descargar` es asíncrono: se espera a que libere el
                       bloqueo del documento para seguir con el siguiente. */
                    var espera = function () {
                        if (!ESTADO.ocupado[entrada.documento.logico_id]) {
                            var clave = claveLogico(
                                Number(entrada.documento.obra_id),
                                Number(entrada.documento.logico_id)
                            );

                            var ahora = ESTADO.locales[clave];

                            if (ahora && (!antes || ahora.version !== antes.version)) {
                                actualizados++;
                            }

                            resolver();
                            return;
                        }

                        setTimeout(espera, 120);
                    };

                    espera();
                });
            });
        }, Promise.resolve()).then(function () {
            return actualizados;
        });
    }

    /* ================================================================
       Ciclo de vida
       ================================================================ */

    function registrarEventos() {
        if (elementos.filtros) {
            elementos.filtros.addEventListener('click', function (evento) {
                var nodo = evento.target.closest ? evento.target.closest('.did-filtro') : null;

                if (!nodo) {
                    return;
                }

                ESTADO.filtro = nodo.getAttribute('data-filtro') || FILTROS.TODOS;

                var botones = elementos.filtros.querySelectorAll('.did-filtro');
                Array.prototype.forEach.call(botones, function (boton) {
                    var activo = boton === nodo;
                    boton.className = activo ? 'did-filtro is-activo' : 'did-filtro';
                    boton.setAttribute('aria-pressed', activo ? 'true' : 'false');
                });

                render();
            });
        }

        if (elementos.refrescar) {
            elementos.refrescar.addEventListener('click', function () {
                refrescar();
            });
        }

        if (CONECTIVIDAD && typeof CONECTIVIDAD.alCambiar === 'function') {
            CONECTIVIDAD.alCambiar(function () {
                refrescar();
            });
        }

        window.addEventListener('beforeunload', revocarUrls);
    }

    function refrescar() {
        if (!ESTADO.urlApi) {
            return Promise.resolve(0);
        }

        return comprobarServidor().then(function (resultado) {
            ESTADO.enLinea = resultado.enLinea;

            if (resultado.payload) {
                ESTADO.documentos = resultado.payload.documentos || [];
                ESTADO.obra = resultado.payload.obra || ESTADO.obra;
                ESTADO.obraId = resultado.payload.obra ? Number(resultado.payload.obra.id) : ESTADO.obraId;
                ESTADO.usuarioId = resultado.payload.usuario ? Number(resultado.payload.usuario.id) : ESTADO.usuarioId;
                ESTADO.sinServidor = false;
            } else if (!resultado.enLinea) {
                ESTADO.sinServidor = true;
            }

            render();

            if (resultado.mensaje) {
                mostrarAviso('alert-warning', resultado.mensaje);
            }

            if (resultado.enLinea) {
                return actualizarDesactualizados().then(function (cuantas) {
                    if (cuantas > 0) {
                        render();
                        mostrarAviso('alert-success', 'Se actualizaron ' + cuantas
                            + (cuantas === 1 ? ' documento' : ' documentos') + ' con la versión vigente.');
                    }

                    return cuantas;
                });
            }

            return 0;
        });
    }

    function iniciar() {
        if (!document.getElementById('documentacionDisponible')) {
            return;
        }

        elementos = {
            lista: document.getElementById('didLista'),
            vacio: document.getElementById('didVacio'),
            vacioTitulo: document.getElementById('didVacioTitulo'),
            vacioTexto: document.getElementById('didVacioTexto'),
            aviso: document.getElementById('didAviso'),
            avisoTexto: document.getElementById('didAvisoTexto'),
            conexion: document.getElementById('didConexion'),
            conexionTexto: document.getElementById('didConexionTexto'),
            filtros: document.getElementById('didFiltros'),
            refrescar: document.getElementById('didRefrescar'),
            resumen: document.getElementById('didResumen'),
            obra: document.getElementById('didObra'),
            volver: document.getElementById('didVolver')
        };

        var payload = leerPayload();

        if (payload) {
            ESTADO.obraId = payload.obra ? Number(payload.obra.id) : 0;
            ESTADO.obra = payload.obra || null;
            ESTADO.usuarioId = payload.usuario ? Number(payload.usuario.id) : 0;
            ESTADO.documentos = payload.documentos || [];
            ESTADO.urlApi = payload.api || '';
            ESTADO.enLinea = true;
        } else {
            /* Shell sin conexión: la obra se deduce de la URL y el usuario
               del perfil local vigente. */
            ESTADO.obraId = obraIdDesdeUrl();
            ESTADO.sinServidor = true;

            if (ESTADO.obraId) {
                ESTADO.urlApi = '/inspector/documentacion/api/' + ESTADO.obraId;
            }
        }

        if (!ESTADO.usuarioId) {
            ESTADO.usuarioId = usuarioIdDesdeMeta();
        }

        registrarEventos();

        leerPerfil().then(function (perfil) {
            if (!ESTADO.usuarioId && perfil) {
                ESTADO.usuarioId = Number(perfil.usuario_id) || 0;
            }

            if (!ESTADO.usuarioId) {
                render();
                mostrarAviso('alert-warning', 'No se pudo identificar al usuario de esta sesión en el dispositivo. '
                    + 'Conéctese al servidor para habilitar la consulta de la documentación.');

                return null;
            }

            return cargarLocales().then(function () {
                render();
            });
        }).then(function () {
            if (payload) {
                return null;
            }

            return comprobarServidor().then(function (resultado) {
                ESTADO.enLinea = resultado.enLinea;

                if (resultado.payload) {
                    ESTADO.documentos = resultado.payload.documentos || [];
                    ESTADO.obra = resultado.payload.obra || ESTADO.obra;
                    ESTADO.sinServidor = false;
                }

                render();

                if (resultado.mensaje) {
                    mostrarAviso('alert-warning', resultado.mensaje);
                }

                return null;
            });
        }).then(function () {
            if (ESTADO.enLinea) {
                return actualizarDesactualizados();
            }

            return null;
        }).catch(function (error) {
            if (window.console && console.error) {
                console.error('SIGOA: no se pudo iniciar la pantalla de documentación.', error);
            }
        });
    }

    window.SIGOA = window.SIGOA || {};
    window.SIGOA.documentacion = {
        ESTADOS: ESTADOS,
        FILTROS: FILTROS,
        claveDocumento: claveDocumento,
        claveUsuarioObra: claveUsuarioObra,
        claveLogico: claveLogico,
        estadoDocumento: estadoDocumento,
        combinar: combinar,
        filtrar: filtrar,
        formatearTamano: formatearTamano,
        formatearFecha: formatearFecha
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();
