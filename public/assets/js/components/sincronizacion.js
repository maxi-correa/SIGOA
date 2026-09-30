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

    Agotados los intentos, la operación sale de la cola automática. Ningún
    dato se elimina y la entidad conserva sus blobs. La única salida es una
    acción manual: el botón "Sincronizar" (`revivirAgotadas`) o el botón de
    reintento por ítem (`reintentar`). El agotamiento en estado
    `SINCRONIZANDO` —la aplicación se cerró a mitad del último intento— se
    trata igual que en `ERROR`: no era recuperable por sí solo.


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
   * No se almacena ninguna credencial ni token CSRF en IndexedDB. El token
     no forma parte de la operación persistida: lo resuelve `SIGOA.csrf` en
     cada envío y lo renueva con cada respuesta (§52.7, §61).

   No se usa Background Sync: los disparadores son la apertura de la
   aplicación, la recuperación de conectividad, la visibilidad de la
   pestaña, la acción manual "Sincronizar" y el reingreso tras el login
   (Android + Chrome e iPhone + Safari).
   =================================================================== */

(function () {
    'use strict';

    var ALMACEN = (window.SIGOA && window.SIGOA.almacenamiento) ? window.SIGOA.almacenamiento : null;
    var CONECTIVIDAD = (window.SIGOA && window.SIGOA.conectividad) ? window.SIGOA.conectividad : null;

    /* Token CSRF vigente. No se resuelve aquí: lo resuelve `SIGOA.csrf`, que
       es el único origen del token en el cliente y conoce su renovación tras
       cada respuesta (§61). Este componente no almacena tokens ni de uno ni
       de otro (§52.7). */
    var CSRF = (window.SIGOA && window.SIGOA.csrf) ? window.SIGOA.csrf : null;

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
       apertura de la aplicación.

       `cicloPendiente` no es un segundo procesador: es la petición que llegó
       mientras había un ciclo en curso. No se ejecuta en paralelo —eso sí
       sería consumir la misma cola dos veces— sino como continuación del
       drenaje, de modo que ningún disparador se pierde y siempre hay un
       único consumidor. */
    var enCurso = false;
    var cicloPendiente = null;
    var disparadoresRegistrados = false;
    var temporizador = null;

    /* Tope de vueltas del drenaje. Una vuelta solo continúa si la anterior
       consumió intentos, de modo que el número de vueltas está acotado por la
       cola; este tope protege frente a un ciclo que "avanzara" sin resolver
       nada. */
    var MAX_VUELTAS_DRENADO = 100;

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

    function tokenCsrf() {
        return CSRF ? CSRF.token() : '';
    }

    /**
     * Registra el token que deja vigente una respuesta.
     *
     * `SIGOA.csrf` lo resuelve al leer la cabecera `X-CSRF-TOKEN`, que
     * `App\Filters\CsrfApi::after()` publica en toda respuesta API con el
     * token vigente tras la regeneración. Sin esto, la segunda petición del
     * drenaje iría con el token de la primera y el servidor la rechazaría
     * (§61).
     */
    function renovarTokenCsrf(respuesta) {
        if (CSRF) {
            CSRF.actualizar(respuesta);
        }
    }

    function cabecerasAjson() {
        return {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': tokenCsrf()
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
     * Devuelve una operación a `PENDIENTE` sin consumir el intento.
     *
     * Se usa ante 401 y ante 403 por token de seguridad inválido: la
     * petición no llegó a la lógica de negocio, así que el fallo es de la
     * sesión, no de la operación. El intento ya registrado se devuelve para
     * que un token vencido no agote la cola ni castigue al dispositivo con
     * un ciclo infinito de peticiones rechazadas.
     */
    function marcarOperacionPausada(operacion, mensaje) {
        var copia = Object.assign({}, operacion);
        var intentos = Number(operacion.intentos) || 0;

        copia.estado          = OP_PENDIENTE;
        copia.error           = mensaje || null;
        copia.intentos        = intentos > 0 ? intentos - 1 : 0;
        copia.ultimo_intento  = copia.intentos > 0 ? copia.ultimo_intento : null;

        return guardarOperacion(copia);
    }

    /**
     * Indica si una operación agotó sus intentos y quedó fuera de la cola
     * automática.
     *
     * Importa el estado y no solo el contador: una operación que quedó
     * `SINCRONIZANDO` porque la aplicación se cerró a mitad del sexto
     * intento tiene los mismos `MAX_INTENTOS` consumidos que una que pasó a
     * `ERROR`, y en ambos casos ningún disparador automático —ni
     * `programarDiferido`— la vuelve a intentar. Sin esta comprobación, la
     * única salida es la acción manual del usuario.
     */
    function estaAgotada(operacion) {
        if (!operacion || operacion.estado === OP_SINCRONIZADA) {
            return false;
        }

        return (Number(operacion.intentos) || 0) >= MAX_INTENTOS;
    }

    /**
     * Indica si la operación necesita una acción manual para volver a la
     * cola: un error permanente (`ERROR`) o el agotamiento de reintentos.
     *
     * La interfaz usa esta comprobación para ofrecer el botón de reintento
     * también en el caso de agotamiento, que antes quedaba sin ninguna
     * salida visible.
     */
    function requiereReintentoManual(operacion) {
        return !!operacion
            && operacion.estado !== OP_SINCRONIZADA
            && (operacion.estado === OP_ERROR || estaAgotada(operacion));
    }

    /**
     * Reinicia el contador de intentos de una operación y la devuelve a la
     * cola. No borra datos ni reconstruye la entidad: solo deshace el
     * agotamiento.
     */
    function revivirOperacion(operacion) {
        var copia = Object.assign({}, operacion);

        copia.estado         = OP_PENDIENTE;
        copia.intentos       = 0;
        copia.ultimo_intento = null;
        copia.error          = null;

        return guardarOperacion(copia);
    }

    /**
     * Devuelve la entidad local a `PENDIENTE_SYNC` conservando todos sus
     * datos (incluidos los blobs de una fotografía).
     */
    function revivirEntidad(tipo, entidadUuid) {
        var store = tipo === TIPO_FOTOGRAFIA
            ? ALMACEN.ALMACENES.fotografias
            : ALMACEN.ALMACENES.inspecciones;

        return ALMACEN.obtener(store, entidadUuid).then(function (entidad) {
            if (!entidad) {
                return null;
            }

            var copia = Object.assign({}, entidad);

            copia.estado_local     = ESTADO_PENDIENTE_SYNC;
            copia.error_local      = null;
            copia.updated_at_local = ahoraISO();

            return ALMACEN.guardar(store, copia);
        });
    }

    /**
     * Obra a la que pertenece una operación, según la entidad que representa
     * y, para una fotografía, su inspección padre.
     */
    function obraDeOperacion(operacion, inspecciones, fotografias) {
        var entidad = operacion.tipo === TIPO_FOTOGRAFIA
            ? (fotografias || []).filter(function (foto) {
                return foto.uuid === operacion.entidad_uuid;
            })[0]
            : (inspecciones || []).filter(function (inspeccion) {
                return inspeccion.uuid === operacion.entidad_uuid;
            })[0];

        if (!entidad) {
            return null;
        }

        if (operacion.tipo !== TIPO_FOTOGRAFIA) {
            return entidad.obra_id;
        }

        var padre = (inspecciones || []).filter(function (inspeccion) {
            return inspeccion.uuid === entidad.inspeccion_uuid;
        })[0];

        return padre ? padre.obra_id : null;
    }

    /**
     * Revive las operaciones agotadas (§56).
     *
     * Es la contrapartida de la acción manual "Sincronizar": tras agotar los
     * reintentos, una operación queda fuera de la cola automática y el
     * dispositivo ya no la envía por sí solo. Esta función reinicia su
     * contador para que el ciclo que está a punto de ejecutarse la incluya,
     * sin tocar ningún otro dato local.
     *
     * Solo actúa sobre operaciones **agotadas**: un error permanente con
     * intentos disponibles sigue requiriendo el reintento explícito por
     * ítem, de modo que un rechazo que se repetiría no se reintenta en cada
     * pulsación del botón.
     *
     * @param {number} [obraId] Limita la revive a una obra.
     * @returns {Promise<object[]>} Operaciones revividas.
     */
    function revivirAgotadas(obraId) {
        return Promise.all([
            obtenerOperaciones(),
            ALMACEN.obtenerTodos(ALMACEN.ALMACENES.inspecciones),
            ALMACEN.obtenerTodos(ALMACEN.ALMACENES.fotografias)
        ]).then(function (datos) {
            var operaciones    = datos[0] || [];
            var inspecciones   = datos[1] || [];
            var fotografias    = datos[2] || [];
            var acotarAUnaObra = obraId !== undefined && obraId !== null && isFinite(obraId);

            var objetivo = operaciones.filter(function (operacion) {
                if (!estaAgotada(operacion)) {
                    return false;
                }

                if (!acotarAUnaObra) {
                    return true;
                }

                return obraDeOperacion(operacion, inspecciones, fotografias) === obraId;
            });

            return Promise.all(objetivo.map(function (operacion) {
                return revivirOperacion(operacion).then(function (revivida) {
                    return revivirEntidad(operacion.tipo, operacion.entidad_uuid)
                        .then(function () {
                            return revivida;
                        });
                });
            }));
        });
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

            return revivirOperacion(operacion).then(function (operacionReprogramada) {
                return revivirEntidad(tipo, entidadUuid).then(function () {
                    return operacionReprogramada;
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

    function leerError(resp) {
        if (!resp || typeof resp.json !== 'function') {
            return Promise.resolve(null);
        }

        return resp.json().catch(function () {
            return null;
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
                            /* El servidor rota el token en toda petición
                               aceptada: se registra antes de mirar el estado
                               o el cuerpo, para que la siguiente operación
                               salga ya con el vigente (§61). */
                            renovarTokenCsrf(resp);

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
                                return leerError(resp)
                                    .then(function (data) {
                                        if (data && data.error === 'CSRF_INVALID') {
                                            return pausarTodas(marcadas, 'El token de seguridad venció. Se reanudará al recargar la página.')
                                                .then(function () {
                                                    return { csrfInvalido: true };
                                                });
                                        }

                                        return pausarTodas(marcadas, 'Sin permisos para sincronizar.')
                                            .then(function () {
                                                return { prohibido: true };
                                            });
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
                        })
                        .then(function (resultado) {
                            /* Todo el lote salió: cada operación consumió un
                               intento, aunque alguna acabara en error. Lo usa
                               el drenaje para saber que esta vuelta avanzó. */
                            resultado.procesadas = marcadas.length;

                            return resultado;
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
                'X-CSRF-TOKEN': tokenCsrf()
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
                        /* Se renueva el token antes de cualquier otra
                           consideración: aunque esta fotografía falle o se
                           pause, la siguiente petición del mismo drenaje debe
                           salir con el token que el servidor acaba de emitir
                           (§61). */
                        renovarTokenCsrf(resp);

                        if (resp.status === 401) {
                            return pausarTodas([actual], 'Sesión expirada. Se reanudará al iniciar sesión.')
                                .then(function () {
                                    return { authRequerida: true };
                                });
                        }

                        if (resp.status === 403) {
                            return leerError(resp)
                                .then(function (data) {
                                    if (data && data.error === 'CSRF_INVALID') {
                                        return pausarTodas([actual], 'El token de seguridad venció. Se reanudará al recargar la página.')
                                            .then(function () {
                                                return { csrfInvalido: true };
                                            });
                                    }

                                    return pausarTodas([actual], 'Sin permisos para sincronizar.')
                                        .then(function () {
                                            return { prohibido: true };
                                        });
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
                    })
                    .then(function (parcial) {
                        /* La petición salió: el intento ya está consumido y la
                           operación esperará su backoff aunque el resultado
                           sea un error. Lo usa el drenaje para saber que esta
                           vuelta avanzó. */
                        parcial.procesada = true;

                        return parcial;
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
            prohibido: false,
            csrfInvalido: false,
            /* Operaciones que el ciclo puso en `SINCRONIZANDO`, es decir las
               que consumieron un intento. Es lo que permite decidir si el
               drenaje puede continuar (§ drenado). */
            procesadas: 0
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

                    if (parcial.csrfInvalido) {
                        resumen.csrfInvalido = true;
                        detener = true;
                        return;
                    }

                    resumen.sincronizadas += parcial.sincronizadas || 0;
                    resumen.errores += parcial.errores || 0;

                    if (parcial.procesada) {
                        resumen.procesadas++;
                    }
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
     * Fase que pide una petición: `null` es el ciclo completo.
     */
    function faseDe(config) {
        if (config && config.soloInspecciones === true) {
            return 'inspecciones';
        }

        if (config && config.soloFotografias === true) {
            return 'fotografias';
        }

        return null;
    }

    /**
     * Obra que acota una petición: `null` es un ciclo global.
     */
    function alcanceDe(config) {
        if (!config || config.obraId === undefined || config.obraId === null) {
            return null;
        }

        return config.obraId;
    }

    /**
     * Combina dos peticiones de ciclo sin perder trabajo.
     *
     * Se usa cuando una segunda petición llega mientras ya hay un ciclo en
     * curso. La combinación es deliberadamente más amplia que cada petición
     * por separado, porque su único propósito es que la unión de lo pedido se
     * ejecute alguna vez:
     *
     * * `revivirAgotadas` se acumula: la acción manual del inspector no puede
     *   perderse porque otra petición fuera automática;
     * * el alcance solo se acota si ambas peticiones apuntan a la misma obra.
     *   Si una es global, o si discrepan, la combinación es global;
     * * la fase solo se restringe si ambas piden la misma. Si una pide el
     *   ciclo completo, la combinación también es completa.
     */
    function combinarOpciones(primera, segunda) {
        var a = primera || {};
        var b = segunda || {};
        var resultado = Object.assign({}, a, b);

        resultado.revivirAgotadas = a.revivirAgotadas === true || b.revivirAgotadas === true;

        var alcanceA = alcanceDe(a);
        var alcanceB = alcanceDe(b);

        if (alcanceA === null || alcanceB === null || alcanceA !== alcanceB) {
            delete resultado.obraId;
        }

        if (faseDe(a) !== faseDe(b)) {
            delete resultado.soloInspecciones;
            delete resultado.soloFotografias;
        }

        return resultado;
    }

    /**
     * Suma dos resúmenes de ciclo.
     *
     * El drenaje puede comprise varias vueltas y quien lo pidió (la vista de
     * obra, el botón "Sincronizar") necesita el resultado de **todo** el
     * drenaje: si solo devolviera la última vuelta, una tanda de fotografías
     * se informaría como "sin cambios" aunque se hubieran enviado todas.
     *
     * Los contadores se suman y los indicadores de pausa se propagan; el
     * estado de la cola (`pendientes`, `pendientesElegibles`, `bloqueadas`,
     * `vacio`) es una foto del final y lo decide la última vuelta.
     */
    function fusionarResumenes(acumulado, nuevo) {
        var previo = acumulado || {};
        var inspeccionesPrevias = previo.inspecciones || resumenInspeccionesVacio();
        var fotografiasPrevias = previo.fotografias || resumenFotografiasVacio();
        var inspeccionesNuevas = nuevo.inspecciones || resumenInspeccionesVacio();
        var fotografiasNuevas = nuevo.fotografias || resumenFotografiasVacio();
        var total = Object.assign({}, previo);

        total.revividas = (previo.revividas || 0) + (nuevo.revividas || 0);
        total.intentos  = (previo.intentos || 0) + (nuevo.intentos || 0);

        total.inspecciones = {
            sincronizadas:   (inspeccionesPrevias.sincronizadas || 0) + (inspeccionesNuevas.sincronizadas || 0),
            yaSincronizadas: (inspeccionesPrevias.yaSincronizadas || 0) + (inspeccionesNuevas.yaSincronizadas || 0),
            rechazadas:      (inspeccionesPrevias.rechazadas || 0) + (inspeccionesNuevas.rechazadas || 0),
            errores:         (inspeccionesPrevias.errores || 0) + (inspeccionesNuevas.errores || 0)
        };

        total.fotografias = {
            sincronizadas:   (fotografiasPrevias.sincronizadas || 0) + (fotografiasNuevas.sincronizadas || 0),
            yaSincronizadas: (fotografiasPrevias.yaSincronizadas || 0) + (fotografiasNuevas.yaSincronizadas || 0),
            errores:         (fotografiasPrevias.errores || 0) + (fotografiasNuevas.errores || 0),
            /* `bloqueadas` describe la cola al terminar, no un total. */
            bloqueadas:      fotografiasNuevas.bloqueadas || 0,
            authRequerida:   nuevo.authRequerida === true || previo.authRequerida === true,
            prohibido:       nuevo.prohibido === true || previo.prohibido === true,
            csrfInvalido:    nuevo.csrfInvalido === true || previo.csrfInvalido === true
        };

        total.authRequerida = nuevo.authRequerida === true || previo.authRequerida === true;
        total.prohibido     = nuevo.prohibido === true || previo.prohibido === true;
        total.csrfInvalido  = nuevo.csrfInvalido === true || previo.csrfInvalido === true;
        total.sinConexion   = nuevo.sinConexion === true || previo.sinConexion === true;

        if (!total.error && nuevo.error) {
            total.error = nuevo.error;
        }

        total.pendientes          = nuevo.pendientes;
        total.pendientesElegibles = nuevo.pendientesElegibles;
        total.vacio               = nuevo.vacio;

        return total;
    }

    /**
     * Un ciclo puede seguir drenando solo si no se detuvo por una condición
     * real. Una pausa por sesión, un rechazo de permisos, un token de
     * seguridad vencido, un fallo de red o la pérdida de conectividad son
     * motivos para dejar la cola como está y esperar al siguiente disparador
     * (§56.6): seguir insistiendo aquí agotaría los reintentos.
     */
    function puedeSeguirDrenando(resumen) {
        return hayConectividad()
            && resumen.authRequerida !== true
            && resumen.prohibido !== true
            && resumen.csrfInvalido !== true
            && resumen.sinConexion !== true
            && !resumen.error;
    }

    /**
     * Sincroniza inspecciones y fotografías respetando el orden obligatorio.
     *
     * Es la entrada pública. Si ya hay un ciclo consumiendo la cola no abre
     * un segundo procesador: registra la petición (`cicloPendiente`) y la
     * ejecuta el propio ciclo, al final, como continuación del drenaje.
     *
     * @param {object}  [opciones]
     * @param {number}  [opciones.obraId] Limita el ciclo a una obra.
     * @param {string}  [opciones.motivo] Solo informativo.
     * @param {boolean} [opciones.soloInspecciones] Omite la fase de fotos.
     * @param {boolean} [opciones.soloFotografias] Omite la fase de inspecciones.
     * @param {boolean} [opciones.revivirAgotadas] Revive las operaciones que
     *        agotaron sus reintentos. Lo usa la acción manual "Sincronizar":
     *        ningún disparador automático lo hace, porque reintentar para
     *        siempre una operación sin red no terminaría nunca.
     * @returns {Promise<object>}
     */
    function sincronizarTodo(opciones) {
        var config = opciones || {};

        if (!supported()) {
            return Promise.resolve({ error: 'NO_LOCAL_STORAGE' });
        }

        if (enCurso) {
            cicloPendiente = combinarOpciones(cicloPendiente, config);

            return Promise.resolve({ enCurso: true });
        }

        if (!hayConectividad()) {
            return Promise.resolve({ sinConexion: true });
        }

        return drenar(config);
    }

    /**
     * Una pasada completa sobre la cola: inspecciones y después fotografías.
     *
     * Trabaja con la cola tal como la encuentra al empezar. Lo que se
     * capture mientras dura la pasada lo recoge la vuelta siguiente del
     * drenaje, no esta.
     */
    function ejecutarCiclo(config) {
        var soloInspecciones = config.soloInspecciones === true;
        var soloFotografias  = config.soloFotografias === true;
        var revivir          = config.revivirAgotadas === true;

        var resumen = {
            vacio:         true,
            revividas:     0,
            intentos:      0,
            inspecciones:  resumenInspeccionesVacio(),
            fotografias:   resumenFotografiasVacio()
        };

        return asegurarCola()
            .then(function () {
                if (!revivir) {
                    return;
                }

                return revivirAgotadas(config.obraId).then(function (revividas) {
                    resumen.revividas = revividas.length;
                });
            })
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

                        if (resultado.csrfInvalido) {
                            resumen.csrfInvalido = true;
                            return resumen;
                        }

                        resumen.inspecciones = resultado.resumen;

                        if (resultado.procesadas) {
                            resumen.intentos += resultado.procesadas;
                        }

                        return resumen;
                    });
            })
            .then(function () {
                if (resumen.authRequerida || resumen.prohibido || resumen.csrfInvalido || soloInspecciones) {
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
                                resumen.csrfInvalido = parcial.csrfInvalido;
                                resumen.intentos += parcial.procesadas || 0;

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
                return obtenerOperaciones()
                    .then(function (operaciones) {
                        var ahora = ahoraMilisegundos();

                        resultado.pendientes = resumir(operaciones);

                        /* Cuántas operaciones podría tomar la cola automática
                           ahora mismo. Es el criterio del drenaje: si queda
                           algo elegible, un ciclo que advanced debe seguir. */
                        resultado.pendientesElegibles = operaciones.filter(function (operacion) {
                            return puedeReintentar(operacion, ahora);
                        }).length;

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
     * Mantiene un único ciclo de procesamiento activo y drena la cola.
     *
     * El bloqueo (`enCurso`) se toma una sola vez, al entrar, y se suelta
     * al terminar: mientras dura el drenaje no hay un segundo procesador
     * capaz de consumir las mismas operaciones. Lo que llega durante el
     * drenaje (la apertura de la aplicación, `online`, `visibilitychange`, el
     * botón "Sincronizar", el temporizador del backoff o una fotografía
     * capturada en este instante) no se ejecuta en paralelo ni se descarta:
     * se encadena como vuelta siguiente.
     *
     * El drenaje continúa cuando la vuelta anterior **consumió intentos** y
     * todavía queda cola elegible. Exige avance a propósito: una cola que
     * solo tiene fotografías bloqueadas por su padre —o operaciones en
     * espera de su backoff, o en `ERROR`— no debe reiniciar el ciclo nunca
     * sola. Esas esperan su propio disparador (§56.6).
     */
    function drenar(config) {
        var opciones = config || {};
        var acumulado = null;
        var vueltas = 0;

        enCurso = true;

        function paso() {
            return ejecutarCiclo(opciones).then(function (resumen) {
                acumulado = fusionarResumenes(acumulado, resumen);

                var pendiente = cicloPendiente;

                cicloPendiente = null;

                /* Una operación completada dispara la siguiente: si esta vuelta
                   envió algo y queda cola elegible, se encadena otra vuelta sin
                   esperar a ningún disparador externo. */
                if (puedeSeguirDrenando(acumulado)
                    && resumen.intentos > 0
                    && resumen.pendientesElegibles > 0
                    && vueltas < MAX_VUELTAS_DRENADO
                ) {
                    vueltas++;

                    if (pendiente) {
                        opciones = combinarOpciones(opciones, pendiente);
                    }

                    return paso();
                }

                /* Petición recibida durante el drenaje: se ejecuta ahora, en el
                   mismo consumidor y sin dejar el lock en el camino. Si el
                   ciclo se detuvo por una pausa real, la petición se abandona
                   con él: la retomará el siguiente disparador (login, `online`,
                   visibilidad o el propio botón). */
                if (pendiente
                    && puedeSeguirDrenando(acumulado)
                    && vueltas < MAX_VUELTAS_DRENADO
                ) {
                    vueltas++;
                    opciones = combinarOpciones(opciones, pendiente);

                    return paso();
                }

                enCurso = false;

                return acumulado;
            }, function (error) {
                enCurso = false;

                throw error;
            });
        }

        return paso();
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
       Diagnóstico de la cola (Fase D.6.2)
       ================================================================== */

    /**
     * Estado completo de la cola local, en forma de informe.
     *
     * Es **de solo lectura**: no escribe en IndexedDB, no encola, no
     * programa reintentos y no realiza ninguna petición al servidor. Existe
     * porque el estado real de un dispositivo no es observable desde el
     * servidor (la cola vive solo en el navegador) y sin él no hay forma de
     * distinguir una operación agotada de una que simplemente espera su
     * backoff.
     *
     * Para cada operación informa por qué puede o no volver a intentarse:
     *
     *   * `eligible`    — la cola automática la reintentará sola;
     *   * `esperando`   — dentro de su backoff, con los ms restantes;
     *   * `agotada`     — consumió los `MAX_INTENTOS` y requiere la acción
     *                    manual "Sincronizar" o el botón de reintento;
     *   * `error`       — rechazo permanente;
     *   * `sincronizada`— confirmada por el servidor.
     *
     * @returns {Promise<object>}
     */
    function diagnostico() {
        if (!supported()) {
            return Promise.resolve({ soportado: false, operaciones: [] });
        }

        return Promise.all([
            obtenerOperaciones(),
            ALMACEN.obtenerTodos(ALMACEN.ALMACENES.inspecciones),
            ALMACEN.obtenerTodos(ALMACEN.ALMACENES.fotografias)
        ]).then(function (datos) {
            var operaciones  = datos[0] || [];
            var inspecciones = datos[1] || [];
            var fotografias  = datos[2] || [];
            var ahora        = ahoraMilisegundos();

            var informe = {
                soportado:     true,
                maxIntentos:   MAX_INTENTOS,
                resumen:       resumir(operaciones),
                inspecciones:  inspecciones.map(resumenEntidad),
                fotografias:   fotografias.map(resumenEntidad),
                operaciones:   operaciones.map(function (operacion) {
                    return {
                        id:              operacion.id,
                        tipo:            operacion.tipo,
                        entidad_uuid:    operacion.entidad_uuid,
                        dependencia_uuid: operacion.dependencia_uuid || null,
                        estado:          operacion.estado,
                        intentos:        Number(operacion.intentos) || 0,
                        ultimo_intento:  operacion.ultimo_intento || null,
                        error:           operacion.error || null,
                        created_at:      operacion.created_at || null,
                        obra_id:         obraDeOperacion(operacion, inspecciones, fotografias),
                        elegible:        puedeReintentar(operacion, ahora),
                        espera_restante: esperaRestante(operacion, ahora),
                        agotada:         estaAgotada(operacion),
                        requiere_reintento_manual: requiereReintentoManual(operacion)
                    };
                })
            };

            /* Las operaciones sin entidad local no pueden adscribirse a
               ninguna obra: se informan aparte en lugar de omitirse. */
            informe.operaciones_huerfanas = informe.operaciones
                .filter(function (operacion) {
                    return operacion.obra_id === null;
                })
                .length;

            return informe;
        });
    }

    function resumenEntidad(entidad) {
        return {
            uuid:             entidad.uuid,
            obra_id:          entidad.obra_id,
            inspeccion_uuid:  entidad.inspeccion_uuid || null,
            estado_local:     entidad.estado_local,
            servidor_id:      entidad.servidor_id === undefined ? null : entidad.servidor_id,
            error_local:      entidad.error_local || null,
            created_at_local: entidad.created_at_local || null,
            updated_at_local: entidad.updated_at_local || null,
            ruta_relativa:    entidad.ruta_relativa || null,
            tiene_blob:       !!entidad.blob,
            tiene_thumbnail:  !!entidad.thumbnail
        };
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
                return;
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
        revivirAgotadas:            revivirAgotadas,
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
        retrasoPara:               retrasoPara,
        puedeReintentar:           puedeReintentar,
        esperaRestante:            esperaRestante,
        estaAgotada:               estaAgotada,
        requiereReintentoManual:   requiereReintentoManual,

        /* Diagnóstico (solo lectura) */
        diagnostico: diagnostico,

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
