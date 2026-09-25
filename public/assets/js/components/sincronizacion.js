/* ===================================================================
   SIGOA — Cola de sincronización, reintentos y fotografías
   (Fase D.4 / docs/SIGOA.md §56, sobre §52.13)
   ===================================================================
   Exposición: `SIGOA.sincronizacion`.

   Este componente es el ÚNICO origen de `fetch()` hacia el servidor de
   sincronización y el mecanismo local de coordinación de la cola
   `operaciones` de IndexedDB.

   -------------------------------------------------------------------
   Estados locales de entidad (`inspecciones` / `fotografias`)
   -------------------------------------------------------------------
   Son los de D.2/D.3 y no se modifican:

   * `PENDIENTE_SYNC` — capturada en el dispositivo, sin confirmación.
   * `SINCRONIZADA`   — el servidor confirmó la operación.
   * `ERROR`          — error funcional permanente o reintentos agotados.

   -------------------------------------------------------------------
   Estados de operación (store `operaciones`)
   -------------------------------------------------------------------
   * `PENDIENTE`     — en cola, esperando su turno o el fin del backoff.
   * `SINCRONIZANDO` — en curso en este momento.
   * `SINCRONIZADA`  — confirmada por el servidor; la fila se conserva
                      como trazabilidad y nunca se elimina sola.
   * `ERROR`         — error permanente o reintentos agotados; requiere
                      una acción manual de reintento.

   -------------------------------------------------------------------
   Backoff progresivo (§52.13)
   -------------------------------------------------------------------
   5 s → 15 s → 30 s → 60 s → 5 min, y después `ERROR`.

   La espera se calcula SIEMPRE a partir de `intentos` + `ultimo_intento`
   persistidos en IndexedDB, nunca desde un `setTimeout()` en memoria: al
   cerrar y reabrir la aplicación, la decisión se retoma desde el disco.
   El temporizador del navegador es solo una comodidad de uso; si se
   descarta (pestaña en segundo plano, cierre), el siguiente disparo
   reevalúa el mismo cálculo.

   -------------------------------------------------------------------
   Orden obligatorio (§52.13)
   -------------------------------------------------------------------
   1. inspecciones pendientes → 2. confirmación del servidor →
   3. fotografías de esas inspecciones → 4. liberación de blobs.

   Una fotografía cuyo padre todavía no está `SINCRONIZADA` **no se
   intenta**: queda `PENDIENTE` sin consumir intentos y sin pasar a
   `ERROR`.

   -------------------------------------------------------------------
   Seguridad de los datos locales
   -------------------------------------------------------------------
   * Ningún `fetch()` exitoso elimina nada por sí solo.
   * Los blobs de una fotografía (optimizado y thumbnail) se liberan solo
     después de que el servidor confirmó el alta, y en la misma escritura
     que marca la fotografía como `SINCRONIZADA`.
   * Ante 401 (`AUTH_REQUIRED`) el ciclo se detiene sin tocar un solo
     registro de entidad: al volver a iniciar sesión, la cola se reanuda
     sola.
   * No se almacena ninguna credencial ni token CSRF en IndexedDB.

   No se usa Background Sync: los disparadores son la apertura de la
   aplicación, la recuperación de conectividad, la visibilidad de la
   pestaña, la acción manual "Sincronizar" y el reingreso tras el login
   (Android + Chrome e iPhone + Safari).
   =================================================================== */

(function () {
    'use strict';

    var ALMACEN = (window.SIGOA && window.SIGOA.almacenamiento) ? window.SIGOA.almacenamiento : null;
    var CONECTIVIDAD = (window.SIGOA && window.SIGOA.conectividad) ? window.SIGOA.conectividad : null;

    var ENDPOINT_INSPECCIONES = '/inspector/sincronizar/inspecciones';
    var ENDPOINT_FOTOGRAFIAS  = '/inspector/sincronizar/fotografias';

    /* ------------------------------------------------------------------
       Estados locales de entidad (D.2/D.3, sin cambios)
       ------------------------------------------------------------------ */
    var ESTADO_PENDIENTE_SYNC = 'PENDIENTE_SYNC';
    var ESTADO_SINCRONIZADA   = 'SINCRONIZADA';
    var ESTADO_ERROR          = 'ERROR';

    /* ------------------------------------------------------------------
       Estados de operación (cola local)
       ------------------------------------------------------------------ */
    var OP_PENDIENTE     = 'PENDIENTE';
    var OP_SINCRONIZANDO = 'SINCRONIZANDO';
    var OP_SINCRONIZADA  = 'SINCRONIZADA';
    var OP_ERROR         = 'ERROR';

    /* ------------------------------------------------------------------
       Tipos de operación
       ------------------------------------------------------------------ */
    var TIPO_INSPECCION = 'INSPECCION';
    var TIPO_FOTOGRAFIA = 'FOTOGRAFIA';

    /* ------------------------------------------------------------------
       Backoff progresivo, en milisegundos.

       El índice es la cantidad de intentos ya consumidos:
         0 → inmediato (primer intento)
         1 → 5 s   (1.º reintento)
         2 → 15 s  (2.º)
         3 → 30 s  (3.º)
         4 → 60 s  (4.º)
         5 → 5 min (5.º)

       Superado el último escalón la operación queda en `ERROR` y espera
       una acción manual. Ningún dato se elimina.
       ------------------------------------------------------------------ */
    var RETRASOS_MS = [0, 5000, 15000, 30000, 60000, 300000];

    var MAX_INTENTOS = RETRASOS_MS.length;

    /* Lock en memoria: impide ejecuciones simultáneas provocadas por el
       botón "Sincronizar", el evento `online`, `visibilitychange` y la
       apertura de la aplicación. */
    var enCurso = false;
    var disparadoresRegistrados = false;
    var temporizador = null;

    /* ==================================================================
       Utilidades
       ================================================================== */

    function ahoraISO() {
        return new Date().toISOString();
    }

    function ahoraMilisegundos() {
        return Date.now();
    }

    function supported() {
        return !!ALMACEN && typeof ALMACEN.soportado === 'function' && ALMACEN.soportado();
    }

    function hayConectividad() {
        if (CONECTIVIDAD && typeof CONECTIVIDAD.esOnline === 'function') {
            return CONECTIVIDAD.esOnline();
        }

        return typeof navigator === 'undefined' || navigator.onLine !== false;
    }

    function obtenerTokenCsrf() {
        var prefijo = 'csrf_cookie_name=';
        var cookies = document.cookie.split(';');

        for (var i = 0; i < cookies.length; i++) {
            var par = cookies[i].trim();

            if (par.indexOf(prefijo) === 0) {
                return par.slice(prefijo.length);
            }
        }

        return '';
    }

    function cabecerasAjson() {
        return {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': obtenerTokenCsrf()
        };
    }

    function registrarError(mensaje, error) {
        if (window.console && console.error) {
            console.error('SIGOA: ' + mensaje, error || '');
        }
    }

    /* ==================================================================
       Backoff y elegibilidad
       ================================================================== */

    /**
     * Espera, en milisegundos, que corresponde a una cantidad dada de
     * intentos ya consumidos. 0 significa "sin espera".
     */
    function retrasoPara(intentos) {
        var n = Number(intentos);

        if (!isFinite(n) || n < 0) {
            return 0;
        }

        if (n >= RETRASOS_MS.length) {
            return RETRASOS_MS[RETRASOS_MS.length - 1];
        }

        return RETRASOS_MS[Math.floor(n)];
    }

    /**
     * Indica si una operación puede volver a intentarse ahora.
     *
     * Considera estado, intentos y `ultimo_intento`, de modo que la
     * decisión sobrevive al cierre de la aplicación.
     *
     * @param {object} operacion Registro del store `operaciones`.
     * @param {number} [ahora]   Marca de tiempo en ms (inyectable en pruebas).
     */
    function puedeReintentar(operacion, ahora) {
        if (!operacion) {
            return false;
        }

        if (operacion.estado === OP_SINCRONIZADA || operacion.estado === OP_ERROR) {
            return false;
        }

        var intentos = Number(operacion.intentos) || 0;

        if (intentos >= MAX_INTENTOS) {
            return false;
        }

        if (intentos === 0 || !operacion.ultimo_intento) {
            return true;
        }

        var momento = ahora === undefined ? ahoraMilisegundos() : ahora;
        var ultimo = Date.parse(operacion.ultimo_intento);

        if (isNaN(ultimo)) {
            return true;
        }

        return (momento - ultimo) >= retrasoPara(intentos);
    }

    /**
     * Milisegundos que faltan para que la operación vuelva a ser elegible.
     *
     * Devuelve 0 para las operaciones que ya no deben reintentarse
     * (`SINCRONIZADA`, `ERROR` o con los intentos agotados), porque en
     * esos casos no existe un próximo reintento automático que programar.
     */
    function esperaRestante(operacion, ahora) {
        if (!operacion) {
            return 0;
        }

        if (puedeReintentar(operacion, ahora)) {
            return 0;
        }

        if (operacion.estado === OP_SINCRONIZADA || operacion.estado === OP_ERROR) {
            return 0;
        }

        var intentos = Number(operacion.intentos) || 0;

        if (intentos >= MAX_INTENTOS) {
            return 0;
        }

        var momento = ahora === undefined ? ahoraMilisegundos() : ahora;
        var ultimo = Date.parse(operacion.ultimo_intento);

        if (isNaN(ultimo)) {
            return 0;
        }

        return Math.max(0, retrasoPara(intentos) - (momento - ultimo));
    }

    /* ==================================================================
       Cola local (`operaciones`)
       ================================================================== */

    function obtenerOperaciones() {
        return ALMACEN.obtenerTodos(ALMACEN.ALMACENES.operaciones)
            .then(function (operaciones) {
                return operaciones || [];
            });
    }

    /**
     * Busca la operación de una entidad concreta. La clave es el par
     * (`tipo`, `entidad_uuid`): una inspección y una fotografía nunca
     * comparten operación aunque el uuid coincidiera.
     */
    function buscarOperacion(operaciones, tipo, entidadUuid) {
        for (var i = 0; i < operaciones.length; i++) {
            if (operaciones[i].tipo === tipo && operaciones[i].entidad_uuid === entidadUuid) {
                return operaciones[i];
            }
        }

        return null;
    }

    /**
     * Registra una operación pendiente. Es idempotente: si ya existe una
     * operación para (tipo, entidad) se devuelve la existente sin crear un
     * duplicado, y no se altera su estado ni su historial de intentos.
     *
     * Una operación ya `SINCRONIZADA` no se vuelve a encolar: en D.4 el
     * servidor no expone operaciones de actualización (§56).
     *
     * En ambos casos resuelve con la operación completa, nunca con la
     * clave del store, para que el valor de retorno sea uniforme.
     */
    function encolar(tipo, entidadUuid, dependenciaUuid) {
        return obtenerOperaciones().then(function (operaciones) {
            var existente = buscarOperacion(operaciones, tipo, entidadUuid);

            if (existente) {
                return existente;
            }

            var nueva = {
                tipo:             tipo,
                entidad_uuid:     entidadUuid,
                dependencia_uuid: dependenciaUuid || null,
                estado:           OP_PENDIENTE,
                intentos:         0,
                ultimo_intento:   null,
                error:            null,
                created_at:       ahoraISO()
            };

            return ALMACEN.agregar(ALMACEN.ALMACENES.operaciones, nueva)
                .then(function () {
                    return nueva;
                });
        });
    }

    function guardarOperacion(operacion) {
        var copia = Object.assign({}, operacion);

        copia.updated_at = ahoraISO();

        return ALMACEN.guardar(ALMACEN.ALMACENES.operaciones, copia).then(function () {
            return copia;
        });
    }

    /**
     * Marca la operación como en curso: consume un intento y anota la hora.
     *
     * El estado `SINCRONIZANDO` es transitorio. Si la aplicación se cierra a
     * mitad de camino, la operación queda con `intentos` y `ultimo_intento`
     * persistidos y vuelve a evaluarse desde el backoff al reabrir.
     */
    function marcarIntentando(operacion) {
        var copia = Object.assign({}, operacion);

        copia.estado         = OP_SINCRONIZANDO;
        copia.intentos       = (Number(operacion.intentos) || 0) + 1;
        copia.ultimo_intento = ahoraISO();
        copia.error          = null;

        return guardarOperacion(copia);
    }

    function marcarOperacionSincronizada(operacion) {
        var copia = Object.assign({}, operacion);

        copia.estado = OP_SINCRONIZADA;
        copia.error  = null;

        return guardarOperacion(copia);
    }

    /**
     * Devuelve una operación a `PENDIENTE` sin consumir otro intento.
     *
     * Se usa ante 401: la sesión expiró, no la operación. El reintento se
     *rograma con el backoff ya consumido, de modo que no se castiga al
     * dispositivo con un ciclo infinito de requests sin sesión.
     */
    function marcarOperacionPausada(operacion, mensaje) {
        var copia = Object.assign({}, operacion);

        copia.estado = OP_PENDIENTE;
        copia.error  = mensaje || null;

        return guardarOperacion(copia);
    }

    /**
     * Registra un fallo de una operación.
     *
     * - Fallo transitorio: vuelve a `PENDIENTE` y espera el backoff.
     * - Fallo permanente, o intentos agotados: pasa a `ERROR`.
     *
     * En ambos casos la operación y la entidad se conservan íntegras.
     */
    function marcarOperacionError(operacion, mensaje, permanente) {
        var copia = Object.assign({}, operacion);
        var intentos = (Number(operacion.intentos) || 0);

        copia.error = mensaje || 'Error desconocido de sincronización.';

        var agotada = intentos >= MAX_INTENTOS;

        copia.estado = (permanente || agotada) ? OP_ERROR : OP_PENDIENTE;

        return guardarOperacion(copia);
    }

    /**
     * Reintento manual de una operación en `ERROR` (§56).
     *
     * Devuelve la operación reprogramada a `PENDIENTE` con `intentos = 0`,
     * `ultimo_intento = null` y `error = null`, y devuelve la entidad local a
     * `PENDIENTE_SYNC`. No se borra ningún dato: es un reinicio del contador
     * de intentos, no una reconstrucción de la entidad.
     */
    function reintentar(tipo, entidadUuid) {
        return obtenerOperaciones().then(function (operaciones) {
            var operacion = buscarOperacion(operaciones, tipo, entidadUuid);

            if (!operacion) {
                return null;
            }

            var copia = Object.assign({}, operacion);

            copia.estado         = OP_PENDIENTE;
            copia.intentos       = 0;
            copia.ultimo_intento = null;
            copia.error          = null;

            return guardarOperacion(copia).then(function (operacionReprogramada) {
                var store = tipo === TIPO_FOTOGRAFIA
                    ? ALMACEN.ALMACENES.fotografias
                    : ALMACEN.ALMACENES.inspecciones;

                return ALMACEN.obtener(store, entidadUuid).then(function (entidad) {
                    if (!entidad) {
                        return operacionReprogramada;
                    }

                    var copiaEntidad = Object.assign({}, entidad);

                    copiaEntidad.estado_local     = ESTADO_PENDIENTE_SYNC;
                    copiaEntidad.error_local      = null;
                    copiaEntidad.updated_at_local = ahoraISO();

                    return ALMACEN.guardar(store, copiaEntidad).then(function () {
                        return operacionReprogramada;
                    });
                });
            });
        });
    }

    /**
     * Operaciones de un tipo que pueden intentarse ahora mismo.
     */
    function operacionesElegibles(operaciones, tipo, ahora) {
        return operaciones.filter(function (operacion) {
            return operacion.tipo === tipo && puedeReintentar(operacion, ahora);
        });
    }

    /**
     * Recuento de operaciones por estado, para la interfaz.
     */
    function resumir(operaciones) {
        var resumen = {
            inspecciones: { pendientes: 0, errores: 0, sincronizadas: 0 },
            fotografias:  { pendientes: 0, errores: 0, sincronizadas: 0 }
        };

        operaciones.forEach(function (operacion) {
            var destino = operacion.tipo === TIPO_FOTOGRAFIA
                ? resumen.fotografias
                : resumen.inspecciones;

            if (operacion.estado === OP_SINCRONIZADA) {
                destino.sincronizadas++;
            } else if (operacion.estado === OP_ERROR) {
                destino.errores++;
            } else {
                destino.pendientes++;
            }
        });

        resumen.total = resumen.inspecciones.pendientes
            + resumen.inspecciones.errores
            + resumen.fotografias.pendientes
            + resumen.fotografias.errores;

        return resumen;
    }

    /* ==================================================================
       Recuperación de la cola tras cerrar la aplicación
       ================================================================== */

    /**
     * Reconstruye las operaciones que faltan.
     *
     * Las inspecciones y fotografías capturadas antes de que existiera la
     * cola (o las que quedaron con `PENDIENTE_SYNC` / `ERROR` sin operación
     * asociada) se encolan aquí. Es idempotente: no duplica operaciones.
     *
     * La entidad en `ERROR` con su operación ya presente conserva el estado
     * `ERROR` de esa operación: solo secreates la que falta.
     */
    function asegurarCola() {
        return Promise.all([
            ALMACEN.obtenerTodos(ALMACEN.ALMACENES.inspecciones),
            ALMACEN.obtenerTodos(ALMACEN.ALMACENES.fotografias),
            obtenerOperaciones()
        ]).then(function (resultados) {
            var inspecciones = resultados[0] || [];
            var fotografias  = resultados[1] || [];
            var operaciones  = resultados[2] || [];

            var altas = [];

            inspecciones.forEach(function (inspeccion) {
                if (inspeccion.estado_local !== ESTADO_PENDIENTE_SYNC
                    && inspeccion.estado_local !== ESTADO_ERROR) {
                    return;
                }

                if (buscarOperacion(operaciones, TIPO_INSPECCION, inspeccion.uuid)) {
                    return;
                }

                altas.push(ALMACEN.agregar(ALMACEN.ALMACENES.operaciones, {
                    tipo:             TIPO_INSPECCION,
                    entidad_uuid:     inspeccion.uuid,
                    dependencia_uuid: null,
                    estado:           inspeccion.estado_local === ESTADO_ERROR ? OP_ERROR : OP_PENDIENTE,
                    intentos:         0,
                    ultimo_intento:   null,
                    error:            inspeccion.error_local || null,
                    created_at:       ahoraISO()
                }));
            });

            fotografias.forEach(function (fotografia) {
                if (fotografia.estado_local !== ESTADO_PENDIENTE_SYNC
                    && fotografia.estado_local !== ESTADO_ERROR) {
                    return;
                }

                if (buscarOperacion(operaciones, TIPO_FOTOGRAFIA, fotografia.uuid)) {
                    return;
                }

                altas.push(ALMACEN.agregar(ALMACEN.ALMACENES.operaciones, {
                    tipo:             TIPO_FOTOGRAFIA,
                    entidad_uuid:     fotografia.uuid,
                    dependencia_uuid: fotografia.inspeccion_uuid || null,
                    estado:           fotografia.estado_local === ESTADO_ERROR ? OP_ERROR : OP_PENDIENTE,
                    intentos:         0,
                    ultimo_intento:   null,
                    error:            fotografia.error_local || null,
                    created_at:       ahoraISO()
                }));
            });

            return Promise.all(altas);
        });
    }

    /* ==================================================================
       Sincronización de inspecciones (reutiliza el endpoint de D.3)
       ================================================================== */

    function cuerpoInspeccion(inspeccion) {
        return {
            uuid:             inspeccion.uuid,
            obra_id:          inspeccion.obra_id,
            fecha_inspeccion: inspeccion.fecha_inspeccion,
            hora_inspeccion:  inspeccion.hora_inspeccion || null,
            observacion:      inspeccion.observacion ? inspeccion.observacion : null
        };
    }

    function enviarLoteInspecciones(items) {
        return fetch(ENDPOINT_INSPECCIONES, {
            method: 'POST',
            headers: cabecerasAjson(),
            body: JSON.stringify({
                inspecciones: items.map(function (item) {
                    return cuerpoInspeccion(item.inspeccion);
                })
            })
        });
    }

    /**
     * Traduce la respuesta por ítem del servidor a estado local.
     * Coincide con el contrato de D.3 y no altera sus códigos.
     */
    function aplicarResultadosInspecciones(items, data) {
        var porUuid = {};
        var decisiones = {};
        var resumen = { sincronizadas: 0, yaSincronizadas: 0, rechazadas: 0, errores: 0 };

        items.forEach(function (item) {
            porUuid[item.inspeccion.uuid] = item;
        });

        (data && Array.isArray(data.results) ? data.results : []).forEach(function (resultado) {
            var item = porUuid[resultado.uuid];

            if (!item) {
                return;
            }

            var inspeccion = Object.assign({}, item.inspeccion);

            if (resultado.estado === 'SYNCED' || resultado.estado === 'ALREADY_SYNCED') {
                inspeccion.estado_local     = ESTADO_SINCRONIZADA;
                inspeccion.servidor_id      = resultado.id;
                inspeccion.error_local      = null;
                inspeccion.updated_at_local = ahoraISO();

                if (resultado.estado === 'SYNCED') {
                    resumen.sincronizadas++;
                } else {
                    resumen.yaSincronizadas++;
                }

                decisiones[item.operacion.id] = { sincronizada: true };
            } else if (resultado.estado === 'REJECTED') {
                /* Rechazo funcional permanente: ERROR, conservando los datos. */
                inspeccion.estado_local     = ESTADO_ERROR;
                inspeccion.error_local      = resultado.mensaje
                    ? resultado.mensaje
                    : 'El servidor rechazó la inspección (' + (resultado.error || '') + ').';
                inspeccion.updated_at_local = ahoraISO();

                resumen.rechazadas++;

                decisiones[item.operacion.id] = {
                    permanente: true,
                    mensaje: inspeccion.error_local
                };
            } else {
                /* ERROR del servidor (transitorio): la inspección conserva
                   `PENDIENTE_SYNC` y espera el backoff. */
                resumen.errores++;

                decisiones[item.operacion.id] = {
                    permanente: false,
                    mensaje: resultado.mensaje || 'Error temporal del servidor.'
                };
            }

            item.promiseGuardado = ALMACEN.guardar(ALMACEN.ALMACENES.inspecciones, inspeccion);
        });

        /* Cualquier operación sin resultado del servidor no debe quedar
           atrapada en `SINCRONIZANDO`: se trata como fallo transitorio. */
        items.forEach(function (item) {
            if (decisiones[item.operacion.id] || item.promiseGuardado) {
                return;
            }

            decisiones[item.operacion.id] = {
                permanente: false,
                mensaje: 'El servidor no devolvió resultado para esta inspección.'
            };
        });

        return Promise.all(items.map(function (item) {
            return item.promiseGuardado;
        })).then(function () {
            return aplicarDecisiones(items, decisiones, resumen);
        });
    }

    function aplicarDecisiones(items, decisiones, resumen) {
        return Promise.all(items.map(function (item) {
            var decision = decisiones[item.operacion.id];

            if (!decision) {
                return Promise.resolve();
            }

            if (decision.sincronizada) {
                return marcarOperacionSincronizada(item.operacion);
            }

            return marcarOperacionError(item.operacion, decision.mensaje, decision.permanente);
        })).then(function () {
            return resumen;
        });
    }

    function mensajeDeError(data, status) {
        if (data && data.details && data.details.mensaje) {
            return data.details.mensaje;
        }

        if (data && data.mensaje) {
            return data.mensaje;
        }

        if (status === 0 || status === undefined) {
            return 'No fue posible conectarse con el servidor.';
        }

        if (status >= 500) {
            return 'El servidor no pudo procesar la sincronización.';
        }

        return 'El servidor rechazó la operación.';
    }

    /**
     * Un fallo de lote se aplica a cada operación como transitorio: una
     * inspección pendiente no se marca `ERROR` por un problema de red.
     */
    function marcarFalloLote(items, mensaje) {
        return Promise.all(items.map(function (item) {
            return marcarOperacionError(item.operacion, mensaje, false);
        })).then(function () {
            return {
                sincronizadas: 0,
                yaSincronizadas: 0,
                rechazadas: 0,
                errores: items.length
            };
        });
    }

    /**
     * Carga las entidades de las operaciones candidatas y descarta las que
     * ya no existen en el dispositivo (su operación se elimina, porque
     * reintentarla indefinidamente no la haría aparecer de nuevo).
     */
    function cargarInspecciones(operaciones, obraId) {
        return Promise.all(operaciones.map(function (operacion) {
            return ALMACEN.obtener(ALMACEN.ALMACENES.inspecciones, operacion.entidad_uuid)
                .then(function (inspeccion) {
                    return { operacion: operacion, inspeccion: inspeccion };
                });
        })).then(function (pares) {
            var items = [];
            var huerfanas = [];

            pares.forEach(function (par) {
                if (!par.inspeccion) {
                    huerfanas.push(par.operacion);
                    return;
                }

                if (obraId && par.inspeccion.obra_id !== obraId) {
                    return;
                }

                items.push(par);
            });

            return Promise.all(huerfanas.map(function (operacion) {
                return ALMACEN.eliminar(ALMACEN.ALMACENES.operaciones, operacion.id);
            })).then(function () {
                return items;
            });
        });
    }

    /**
     * Sincroniza las inspecciones de un conjunto de operaciones elegibles.
     *
     * @returns {Promise<object>} `{resumen, authRequerida, prohibido}`
     */
    function procesarInspecciones(operaciones, obraId) {
        return cargarInspecciones(operaciones, obraId)
            .then(function (items) {
                if (items.length === 0) {
                    return { resumen: { sincronizadas: 0, yaSincronizadas: 0, rechazadas: 0, errores: 0 } };
                }

                return Promise.all(items.map(function (item) {
                    return marcarIntentando(item.operacion).then(function (marcada) {
                        return { operacion: marcada, inspeccion: item.inspeccion };
                    });
                })).then(function (marcadas) {
                    return enviarLoteInspecciones(marcadas)
                        .then(function (resp) {
                            if (resp.status === 401) {
                                /* Sesión expirada: NO se toca ninguna entidad.
                                   Los intentos ya consumidos quedan
                                   registrados y el backoff se reanuda tras
                                   el login. */
                                return pausarTodas(marcadas, 'Sesión expirada. Se reanudará al iniciar sesión.')
                                    .then(function () {
                                        return { authRequerida: true };
                                    });
                            }

                            if (resp.status === 403) {
                                return pausarTodas(marcadas, 'Sin permisos para sincronizar.')
                                    .then(function () {
                                        return { prohibido: true };
                                    });
                            }

                            return resp.json()
                                .then(function (data) {
                                    if (!resp.ok || !data || !Array.isArray(data.results)) {
                                        return marcarFalloLote(marcadas, mensajeDeError(data, resp.status))
                                            .then(function (resumen) {
                                                return { resumen: resumen };
                                            });
                                    }

                                    return aplicarResultadosInspecciones(marcadas, data)
                                        .then(function (resumen) {
                                            return { resumen: resumen };
                                        });
                                });
                        })
                        .catch(function (error) {
                            registrarError('falló la sincronización de inspecciones', error);

                            return marcarFalloLote(marcadas, 'No fue posible conectarse con el servidor.')
                                .then(function (resumen) {
                                    return { resumen: resumen };
                                });
                        });
                });
            });
    }

    function pausarTodas(items, mensaje) {
        return Promise.all(items.map(function (item) {
            return marcarOperacionPausada(item.operacion, mensaje);
        }));
    }

    /* ==================================================================
       Sincronización de fotografías
       ================================================================== */

    /**
     * Selecciona las fotografías habilitadas para este ciclo.
     *
     * Solo las que cumplen las dos condiciones:
     *   1. su operación es elegible (estado y backoff);
     *   2. su inspección padre está `SINCRONIZADA` y tiene `servidor_id`.
     *
     * Las bloqueadas se informan aparte: **no** se intenta subirlas, no
     * consumen intentos y no pasan a `ERROR`.
     */
    function seleccionarFotografias(operaciones, obraId) {
        return Promise.all(operaciones.map(function (operacion) {
            return ALMACEN.obtener(ALMACEN.ALMACENES.fotografias, operacion.entidad_uuid)
                .then(function (fotografia) {
                    if (!fotografia) {
                        return { operacion: operacion, fotografia: null, inspeccion: null };
                    }

                    return ALMACEN.obtener(ALMACEN.ALMACENES.inspecciones, fotografia.inspeccion_uuid)
                        .then(function (inspeccion) {
                            return { operacion: operacion, fotografia: fotografia, inspeccion: inspeccion };
                        });
                });
        })).then(function (pares) {
            var habilitadas = [];
            var bloqueadas  = [];
            var huerfanas   = [];

            pares.forEach(function (par) {
                if (!par.fotografia || !par.inspeccion) {
                    huerfanas.push(par.operacion);
                    return;
                }

                if (obraId && par.inspeccion.obra_id !== obraId) {
                    return;
                }

                if (par.inspeccion.estado_local !== ESTADO_SINCRONIZADA || !par.inspeccion.servidor_id) {
                    /* Padre aún no confirmado: no se intenta, no se cuenta
                       como intento y no se marca `ERROR`. */
                    bloqueadas.push(par);
                    return;
                }

                habilitadas.push(par);
            });

            return Promise.all(huerfanas.map(function (operacion) {
                return ALMACEN.eliminar(ALMACEN.ALMACENES.operaciones, operacion.id);
            })).then(function () {
                return { habilitadas: habilitadas, bloqueadas: bloqueadas };
            });
        });
    }

    function construirFormulario(fotografia, inspeccion) {
        var formulario = new FormData();

        formulario.append('uuid', fotografia.uuid);
        formulario.append('inspeccion_uuid', inspeccion.uuid);
        /* El nombre del campo lo define el servidor; el del cliente es solo
           una etiqueta y no determina la ruta ni el nombre físico. */
        formulario.append('archivo', fotografia.blob, 'foto-' + fotografia.uuid + '.jpg');

        if (fotografia.fecha_hora_captura) {
            formulario.append('fecha_hora_captura', fotografia.fecha_hora_captura);
        }

        if (fotografia.latitud !== null && fotografia.latitud !== undefined) {
            formulario.append('latitud', String(fotografia.latitud));
        }

        if (fotografia.longitud !== null && fotografia.longitud !== undefined) {
            formulario.append('longitud', String(fotografia.longitud));
        }

        if (fotografia.dispositivo) {
            formulario.append('dispositivo', fotografia.dispositivo);
        }

        return formulario;
    }

    function enviarFotografia(fotografia, inspeccion) {
        return fetch(ENDPOINT_FOTOGRAFIAS, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': obtenerTokenCsrf()
            },
            /* Sin `Content-Type`: el navegador agrega el límite multipart. */
            body: construirFormulario(fotografia, inspeccion)
        });
    }

    /**
     * Confirma una fotografía: marca la entidad `SINCRONIZADA` y libera los
     * blobs en la MISMA escritura, que ocurre únicamente después de que el
     * servidor confirmó el alta. Si esa escritura fallara, los blobs
     * permanecerían y la fotografía volvería a la cola.
     */
    function confirmarFotografia(item, resultado) {
        var fotografia = Object.assign({}, item.fotografia);

        fotografia.estado_local       = ESTADO_SINCRONIZADA;
        fotografia.servidor_id        = resultado.id;
        fotografia.error_local        = null;
        fotografia.ruta_relativa      = resultado.ruta_relativa || null;
        fotografia.ruta_thumbnail     = resultado.ruta_thumbnail || null;
        fotografia.nombre_archivo     = resultado.nombre_archivo || null;
        fotografia.blob               = null;
        fotografia.thumbnail          = null;
        fotografia.blobs_liberados_at = ahoraISO();
        fotografia.updated_at_local   = ahoraISO();

        return ALMACEN.guardar(ALMACEN.ALMACENES.fotografias, fotografia)
            .then(function () {
                return marcarOperacionSincronizada(item.operacion);
            });
    }

    function marcarFotografiaError(item, mensaje, permanente) {
        var fotografia = Object.assign({}, item.fotografia);

        fotografia.estado_local     = permanente ? ESTADO_ERROR : ESTADO_PENDIENTE_SYNC;
        fotografia.error_local      = permanente ? mensaje : null;
        fotografia.updated_at_local = ahoraISO();

        return ALMACEN.guardar(ALMACEN.ALMACENES.fotografias, fotografia)
            .then(function () {
                return marcarOperacionError(item.operacion, mensaje, permanente);
            });
    }

    function procesarFotografia(item) {
        if (!item.fotografia.blob) {
            /* Sin blob no hay nada que subir: es un problema local
               permanente, no de red. No consume intento porque el
               servidor nunca llegó a recibir nada. */
            return marcarFotografiaError(
                item,
                'La fotografía ya no tiene archivo local. No se puede sincronizar.',
                true
            ).then(function () {
                return { errores: 1 };
            });
        }

        return marcarIntentando(item.operacion)
            .then(function (operacionMarcada) {
                var actual = {
                    operacion: operacionMarcada,
                    fotografia: item.fotografia,
                    inspeccion: item.inspeccion
                };

                return enviarFotografia(item.fotografia, item.inspeccion)
                    .then(function (resp) {
                        if (resp.status === 401) {
                            return pausarTodas([actual], 'Sesión expirada. Se reanudará al iniciar sesión.')
                                .then(function () {
                                    return { authRequerida: true };
                                });
                        }

                        if (resp.status === 403) {
                            return pausarTodas([actual], 'Sin permisos para sincronizar.')
                                .then(function () {
                                    return { prohibido: true };
                                });
                        }

                        return resp.json().then(function (data) {
                            var resultado = data && Array.isArray(data.results) && data.results.length > 0
                                ? data.results[0]
                                : null;

                            if (!resp.ok || !resultado) {
                                return marcarFotografiaError(
                                    actual,
                                    mensajeDeError(data, resp.status),
                                    esErrorPermanente(data && data.error)
                                ).then(function () {
                                    return { errores: 1 };
                                });
                            }

                            /* El resultado debe corresponder a la fotografía
                               enviada. Ante un desajuste no se aplica nada: la
                               integridad de los datos prima sobre confiar en
                               una respuesta incoherente. */
                            if (resultado.uuid && resultado.uuid !== item.fotografia.uuid) {
                                return marcarFotografiaError(
                                    actual,
                                    'El servidor respondió con una fotografía distinta a la enviada.',
                                    true
                                ).then(function () {
                                    return { errores: 1 };
                                });
                            }

                            if (resultado.estado === 'SYNCED' || resultado.estado === 'ALREADY_SYNCED') {
                                return confirmarFotografia(actual, resultado)
                                    .then(function () {
                                        return { sincronizadas: 1 };
                                    });
                            }

                            /* REJECTED es permanente; ERROR es transitorio. */
                            return marcarFotografiaError(
                                actual,
                                resultado.mensaje || 'El servidor rechazó la fotografía.',
                                resultado.estado === 'REJECTED' || esErrorPermanente(resultado.error)
                            ).then(function () {
                                return { errores: 1 };
                            });
                        });
                    })
                    .catch(function (error) {
                        registrarError('falló la subida de una fotografía', error);

                        return marcarFotografiaError(
                            actual,
                            'No fue posible enviar la fotografía al servidor.',
                            false
                        ).then(function () {
                            return { errores: 1 };
                        });
                    });
            });
    }

    /**
     * Códigos del servidor que no tienen sentido reintentar: volver a
     * enviarlos produciría exactamente el mismo rechazo.
     */
    function esErrorPermanente(codigo) {
        return [
            'VALIDATION_ERROR',
            'INVALID_UUID',
            'UUID_EN_CONFLICTO',
            'ARCHIVO_INVALIDO',
            'ARCHIVO_CORRUPTO',
            'ARCHIVO_DEMASIADO_GRANDE',
            'MIME_NO_PERMITIDO',
            'HISTORICAL_AUTHORIZATION_FAILED',
            'OBRA_NOT_FOUND'
        ].indexOf(codigo) !== -1;
    }

    /**
     * Sube las fotografías habilitadas de forma secuencial: en un teléfono
     * una carga paralela de varios JPEG agotaría memoria y ancho de banda
     * sin ganar nada.
     */
    function procesarFotografias(seleccion) {
        var resumen = {
            sincronizadas: 0,
            yaSincronizadas: 0,
            errores: 0,
            bloqueadas: seleccion.bloqueadas.length,
            authRequerida: false,
            prohibido: false
        };
        var detener = false;

        var cadena = Promise.resolve();

        seleccion.habilitadas.forEach(function (item) {
            cadena = cadena.then(function () {
                if (detener) {
                    return;
                }

                return procesarFotografia(item).then(function (parcial) {
                    if (!parcial) {
                        return;
                    }

                    if (parcial.authRequerida) {
                        resumen.authRequerida = true;
                        detener = true;
                        return;
                    }

                    if (parcial.prohibido) {
                        resumen.prohibido = true;
                        detener = true;
                        return;
                    }

                    resumen.sincronizadas += parcial.sincronizadas || 0;
                    resumen.errores += parcial.errores || 0;
                });
            });
        });

        return cadena.then(function () {
            return resumen;
        });
    }

    /* ==================================================================
       Orquestación
       ================================================================== */

    function resumenInspeccionesVacio() {
        return { sincronizadas: 0, yaSincronizadas: 0, rechazadas: 0, errores: 0 };
    }

    function resumenFotografiasVacio() {
        return { sincronizadas: 0, yaSincronizadas: 0, errores: 0, bloqueadas: 0 };
    }

    /**
     * Sincroniza inspecciones y fotografías respetando el orden obligatorio.
     *
     * @param {object}  [opciones]
     * @param {number}  [opciones.obraId] Limita el ciclo a una obra.
     * @param {string}  [opciones.motivo] Solo informativo.
     * @param {boolean} [opciones.soloInspecciones] Omite la fase de fotos.
     * @param {boolean} [opciones.soloFotografias] Omite la fase de inspecciones.
     * @returns {Promise<object>}
     */
    function sincronizarTodo(opciones) {
        var config = opciones || {};
        var soloInspecciones = config.soloInspecciones === true;
        var soloFotografias  = config.soloFotografias === true;

        if (!supported()) {
            return Promise.resolve({ error: 'NO_LOCAL_STORAGE' });
        }

        if (enCurso) {
            return Promise.resolve({ enCurso: true });
        }

        if (!hayConectividad()) {
            return Promise.resolve({ sinConexion: true });
        }

        enCurso = true;

        var resumen = {
            vacio:         true,
            inspecciones:  resumenInspeccionesVacio(),
            fotografias:   resumenFotografiasVacio()
        };

        return asegurarCola()
            .then(function () {
                return obtenerOperaciones();
            })
            .then(function (operaciones) {
                var ahora = ahoraMilisegundos();
                var inspecciones = soloFotografias
                    ? []
                    : operacionesElegibles(operaciones, TIPO_INSPECCION, ahora);

                if (inspecciones.length === 0) {
                    return null;
                }

                return procesarInspecciones(inspecciones, config.obraId)
                    .then(function (resultado) {
                        if (resultado.authRequerida) {
                            resumen.authRequerida = true;
                            return resumen;
                        }

                        if (resultado.prohibido) {
                            resumen.prohibido = true;
                            return resumen;
                        }

                        resumen.inspecciones = resultado.resumen;

                        return resumen;
                    });
            })
            .then(function () {
                if (resumen.authRequerida || resumen.prohibido || soloInspecciones) {
                    return resumen;
                }

                return obtenerOperaciones().then(function (operaciones) {
                    var ahora = ahoraMilisegundos();
                    var fotografias = operacionesElegibles(operaciones, TIPO_FOTOGRAFIA, ahora);

                    if (fotografias.length === 0) {
                        return resumen;
                    }

                    return seleccionarFotografias(fotografias, config.obraId)
                        .then(function (seleccion) {
                            resumen.vacio = false;

                            return procesarFotografias(seleccion).then(function (parcial) {
                                resumen.fotografias = parcial;
                                resumen.authRequerida = parcial.authRequerida;
                                resumen.prohibido = parcial.prohibido;

                                if (parcial.sincronizadas > 0
                                    || parcial.errores > 0
                                    || parcial.bloqueadas > 0
                                ) {
                                    resumen.vacio = false;
                                }

                                return resumen;
                            });
                        });
                });
            })
            .catch(function (error) {
                registrarError('falló el ciclo de sincronización', error);

                resumen.error = 'RED';

                return resumen;
            })
            .then(function (resultado) {
                enCurso = false;

                return obtenerOperaciones()
                    .then(function (operaciones) {
                        resultado.pendientes = resumir(operaciones);

                        if (!resultado.vacio) {
                            resultado.vacio = resultado.pendientes.total === 0;
                        }

                        programarDiferido(operaciones);

                        return resultado;
                    })
                    .catch(function () {
                        return resultado;
                    });
            });
    }

    /**
     * Sincroniza solo las inspecciones de una obra.
     *
     * Se conserva la firma de D.3 (`obraId` opcional) y ahora es un caso
     * particular de `sincronizarTodo()`, de modo que hereda la cola, el
     * backoff y el orden de D.4.
     */
    function sincronizarInspecciones(obraId) {
        return sincronizarTodo({ obraId: obraId, soloInspecciones: true });
    }

    /**
     * Solo fotografías: útil para depurar y para el reintento manual
     * acotado a una obra desde la interfaz.
     */
    function sincronizarFotografias(obraId) {
        return sincronizarTodo({ obraId: obraId, soloFotografias: true });
    }

    /* ==================================================================
       Disparadores
       ================================================================== */

    function cancelarDiferido() {
        if (temporizador !== null) {
            window.clearTimeout(temporizador);
            temporizador = null;
        }
    }

    /**
     * Programa un nuevo ciclo para cuando expire el backoff más próximo.
     *
     * El temporizador es una comodidad: si el navegador lo descarta
     * (pestaña en segundo plano, cierre), los disparadores principales
     * reevalúan el mismo cálculo desde `intentos` + `ultimo_intento`.
     */
    function programarDiferido(operaciones) {
        cancelarDiferido();

        if (!hayConectividad()) {
            return;
        }

        var momento = ahoraMilisegundos();
        var espera = null;

        operaciones.forEach(function (operacion) {
            if (operacion.estado === OP_SINCRONIZADA || operacion.estado === OP_ERROR) {
                return;
            }

            var restante = esperaRestante(operacion, momento);

            if (restante <= 0) {
                restante = 0;
            }

            if (espera === null || restante < espera) {
                espera = restante;
            }
        });

        if (espera === null || espera <= 0) {
            return;
        }

        temporizador = window.setTimeout(function () {
            temporizador = null;
            sincronizarTodo({ motivo: 'programado' });
        }, espera);
    }

    /**
     * Registra los disparadores de sincronización.
     *
     * Se lo llama desde `app.js` (apertura de la aplicación autenticada),
     * desde el evento `online` (recuperación de conectividad) y desde
     * `visibilitychange` (la aplicación vuelve al primer plano).
     *
     * NO se usa Background Sync: no existe en iOS/Safari y no es necesario
     * para cubrir el comportamiento requerido.
     */
    function iniciar() {
        if (disparadoresRegistrados || !supported()) {
            return Promise.resolve(false);
        }

        disparadoresRegistrados = true;

        if (CONECTIVIDAD && typeof CONECTIVIDAD.alCambiar === 'function') {
            CONECTIVIDAD.alCambiar(function (online) {
                if (online) {
                    sincronizarTodo({ motivo: 'conectividad' });
                } else {
                    cancelarDiferido();
                }
            });
        }

        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible') {
                sincronizarTodo({ motivo: 'visible' });
            }
        });

        /* Primer paso al abrir la aplicación (o al reingresar tras el login,
           porque el destino es una vista autenticada que carga app.js). */
        return sincronizarTodo({ motivo: 'apertura' }).then(function () {
            return true;
        });
    }

    /* ==================================================================
       API pública
       ================================================================== */

    window.SIGOA = window.SIGOA || {};
    window.SIGOA.sincronizacion = {
        /* Orquestación */
        sincronizarTodo:         sincronizarTodo,
        sincronizarInspecciones: sincronizarInspecciones,
        sincronizarFotografias:  sincronizarFotografias,
        iniciar:                 iniciar,

        /* Cola */
        encolar:                    encolar,
        reintentar:                 reintentar,
        asegurarCola:               asegurarCola,
        obtenerOperaciones:         obtenerOperaciones,
        marcarIntentando:           marcarIntentando,
        marcarOperacionSincronizada: marcarOperacionSincronizada,
        marcarOperacionError:       marcarOperacionError,
        marcarOperacionPausada:     marcarOperacionPausada,
        operacionesElegibles:       operacionesElegibles,
        resumir:                    resumir,
        buscarOperacion:            buscarOperacion,

        /* Reintentos */
        retrasoPara:     retrasoPara,
        puedeReintentar: puedeReintentar,
        esperaRestante:  esperaRestante,

        /* Códigos del servidor */
        esErrorPermanente: esErrorPermanente,

        /* Constantes */
        ESTADO_PENDIENTE_SYNC: ESTADO_PENDIENTE_SYNC,
        ESTADO_SINCRONIZADA:   ESTADO_SINCRONIZADA,
        ESTADO_ERROR:          ESTADO_ERROR,
        OP_PENDIENTE:          OP_PENDIENTE,
        OP_SINCRONIZANDO:      OP_SINCRONIZANDO,
        OP_SINCRONIZADA:       OP_SINCRONIZADA,
        OP_ERROR:              OP_ERROR,
        TIPO_INSPECCION:       TIPO_INSPECCION,
        TIPO_FOTOGRAFIA:       TIPO_FOTOGRAFIA,
        RETRASOS_MS:           RETRASOS_MS,
        MAX_INTENTOS:          MAX_INTENTOS,
        ENDPOINT_INSPECCIONES: ENDPOINT_INSPECCIONES,
        ENDPOINT_FOTOGRAFIAS:  ENDPOINT_FOTOGRAFIAS
    };
})();
