/* ===================================================================
   SIGOA — Sincronización manual de inspecciones con el servidor
   (Fase D.3 / docs/SIGOA.md §55)
   ===================================================================
   Exposición: `SIGOA.sincronizacion.sincronizarInspecciones(obraId?)`.

   Flujo (solo inspecciones; fotografías quedan para D.4):

   1. recupera de IndexedDB las inspecciones locales en
      `PENDIENTE_SYNC` (de la obra indicada, o todas si no se pasa obra);
   2. arma el lote con la identidad *estable* (uuid). El `inspector_id`
      local NO se envía: el servidor toma la identidad de la sesión;
   3. obtiene un token CSRF **vigente** de la cookie `csrf_cookie_name`
      (rotación `regenerate = true`, §52.7) y lo envía como `X-CSRF-TOKEN`
      junto con `X-Requested-With: XMLHttpRequest`;
   4. procesa la respuesta por uuid:
      * SYNCED / ALREADY_SYNCED → `estado_local = SINCRONIZADA` y guarda
        `servidor_id` (id de servidor) para trazabilidad;
      * REJECTED (rechazo permanente) → `estado_local = ERROR` y conserva
        el motivo en `error_local`. **Los datos no se eliminan**;
      * ERROR del servidor (temporal) → el registro permanece
        `PENDIENTE_SYNC` para reintentar en una próxima sincronización
        (§52.13);
   5. ante **401 (AUTH_REQUIRED)** no se modifica ningún dato local y se
      devuelve `authRequerida: true` para que la interfaz solicite volver a
      autenticarse (§52.6). Los tokens CSRF y credenciales nunca se
      almacenan en IndexedDB (§52.7).

   En caso de falla de red la respuesta es `{ error: 'RED' }` y los estados
   locales quedan intactos.
   =================================================================== */

(function () {
    'use strict';

    var ALMACEN = (window.SIGOA && window.SIGOA.almacenamiento) ? window.SIGOA.almacenamiento : null;

    var ENDPOINT_INSPECCIONES = '/inspector/sincronizar/inspecciones';

    var ESTADO_PENDIENTE_SYNC = 'PENDIENTE_SYNC';
    var ESTADO_SINCRONIZADA   = 'SINCRONIZADA';
    var ESTADO_ERROR          = 'ERROR';

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

    function cuerpoInspeccion(inspeccion) {
        return {
            uuid:             inspeccion.uuid,
            obra_id:          inspeccion.obra_id,
            fecha_inspeccion: inspeccion.fecha_inspeccion,
            hora_inspeccion:  inspeccion.hora_inspeccion || null,
            observacion:      inspeccion.observacion ? inspeccion.observacion : null
        };
    }

    function obtenerPendientes(obraId) {
        var promesa = (obraId && ALMACEN.buscarPorIndice)
            ? ALMACEN.buscarPorIndice(ALMACEN.ALMACENES.inspecciones, 'por_obra', obraId)
            : ALMACEN.obtenerTodos(ALMACEN.ALMACENES.inspecciones);

        return promesa.then(function (inspecciones) {
            return (inspecciones || []).filter(function (inspeccion) {
                return inspeccion.estado_local === ESTADO_PENDIENTE_SYNC;
            });
        });
    }

    function enviarLote(pendientes) {
        return fetch(ENDPOINT_INSPECCIONES, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': obtenerTokenCsrf()
            },
            body: JSON.stringify({
                inspecciones: pendientes.map(cuerpoInspeccion)
            })
        });
    }

    function resumenVacio() {
        return {
            procesadas: 0,
            sincronizadas: 0,
            yaSincronizadas: 0,
            rechazadas: 0,
            errores: 0
        };
    }

    function aplicarResultados(pendientes, data) {
        var porUuid = {};
        var resumen = resumenVacio();
        var actualizaciones = [];
        var i;

        for (i = 0; i < pendientes.length; i++) {
            porUuid[pendientes[i].uuid] = pendientes[i];
        }

        (data && data.results ? data.results : []).forEach(function (resultado) {
            var local = porUuid[resultado.uuid];

            if (!local) {
                return;
            }

            var copia = Object.assign({}, local);

            if (resultado.estado === 'SYNCED' || resultado.estado === 'ALREADY_SYNCED') {
                copia.estado_local = ESTADO_SINCRONIZADA;
                copia.servidor_id  = resultado.id;
                copia.error_local  = null;
                copia.updated_at_local = new Date().toISOString();

                if (resultado.estado === 'SYNCED') {
                    resumen.sincronizadas++;
                } else {
                    resumen.yaSincronizadas++;
                }
            } else if (resultado.estado === 'REJECTED') {
                copia.estado_local = ESTADO_ERROR;
                copia.error_local  = resultado.mensaje
                    ? resultado.mensaje
                    : 'El servidor rechazó la inspección (' + (resultado.error || '') + ').';
                copia.updated_at_local = new Date().toISOString();

                resumen.rechazadas++;
            } else {
                /* ERROR del servidor (temporal): se conserva PENDIENTE_SYNC
                   para reintentar en la próxima sincronización (§52.13). */
                resumen.errores++;
            }

            actualizaciones.push(ALMACEN.guardar(ALMACEN.ALMACENES.inspecciones, copia));
        });

        return Promise.all(actualizaciones).then(function () {
            return resumen;
        });
    }

    function procesarRespuesta(pendientes, resp) {
        if (resp.status === 401) {
            /* Sesión expirada: no se toca ningún dato local (§52.6). */
            return Promise.resolve({ error: 'AUTH_REQUIRED', authRequerida: true });
        }

        if (resp.status === 403) {
            return Promise.resolve({ error: 'FORBIDDEN' });
        }

        return resp.json()
            .then(function (data) {
                if (resp.status === 422 || !resp.ok) {
                    return { error: 'VALIDATION_ERROR', details: data.details || null };
                }

                return aplicarResultados(pendientes, data);
            })
            .catch(function () {
                return { error: 'SERVER' };
            });
    }

    /**
     * Sincroniza las inspecciones locales pendientes con el servidor.
     *
     * @param {number|undefined} obraId - si se indica, solo sincroniza las
     *        inspecciones `PENDIENTE_SYNC` de esa obra; si no, todas.
     * @returns {Promise<object>} resumen `{procesadas, sincronizadas,
     *          yaSincronizadas, rechazadas, errores}` o `{error, authRequerida?}`.
     */
    function sincronizarInspecciones(obraId) {
        if (!ALMACEN || !ALMACEN.soportado()) {
            return Promise.resolve({ error: 'NO_LOCAL_STORAGE' });
        }

        return obtenerPendientes(obraId)
            .then(function (pendientes) {
                if (pendientes.length === 0) {
                    return { vacio: true };
                }

                return enviarLote(pendientes)
                    .then(function (resp) {
                        return procesarRespuesta(pendientes, resp);
                    })
                    .catch(function (error) {
                        if (window.console && console.error) {
                            console.error('SIGOA: falló la sincronización de inspecciones.', error);
                        }

                        return { error: 'RED' };
                    });
            })
            .catch(function (error) {
                if (window.console && console.error) {
                    console.error('SIGOA: no se pudieron recuperar las inspecciones pendientes.', error);
                }

                return { error: 'RED' };
            });
    }

    window.SIGOA = window.SIGOA || {};
    window.SIGOA.sincronizacion = {
        sincronizarInspecciones: sincronizarInspecciones,
        ESTADO_PENDIENTE_SYNC:   ESTADO_PENDIENTE_SYNC,
        ESTADO_SINCRONIZADA:     ESTADO_SINCRONIZADA,
        ESTADO_ERROR:            ESTADO_ERROR
    };
})();