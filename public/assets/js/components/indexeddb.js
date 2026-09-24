/* ===================================================================
   SIGOA — Capa de acceso a IndexedDB (Fase D.1 / §52.10)
   ===================================================================
   Implementación vanilla JavaScript, sin librerías externas.

   Base local versionada: `SIGOA` (v1) con stores:

   * inspecciones  — PK `uuid`     (identidad local de sincronización)
   * fotografias   — PK `uuid`     (relación por `inspeccion_uuid`;
                                    blobs/thumbnail se dejan previstos
                                    y se usarán desde Fase D.2)
   * operaciones   — PK `id` auto  (cola local futura; en D.1 no
                                    realiza ninguna request al servidor)

   Esta capa NO almacena contraseñas, cookies de sesión ni tokens CSRF
   (decisión de seguridad Fase B / §52.7 / §52.6).

   Los datos locales solo se eliminan mediante operaciones explícitas
   (`eliminar`, `limpiar` o el autotest): nada se borra automáticamente.
   =================================================================== */

(function () {
    'use strict';

    var NOMBRE_BASE = 'SIGOA';
    var VERSION_BASE = 1;

    var STORES = {
        inspecciones: {
            keyPath: 'uuid',
            autoIncrement: false,
            indices: []
        },
        fotografias: {
            keyPath: 'uuid',
            autoIncrement: false,
            indices: [
                { nombre: 'por_inspeccion', keyPath: 'inspeccion_uuid' }
            ]
        },
        operaciones: {
            keyPath: 'id',
            autoIncrement: true,
            indices: [
                { nombre: 'por_entidad', keyPath: 'entidad_uuid' },
                { nombre: 'por_estado', keyPath: 'estado' }
            ]
        }
    };

    var ALMACENES = {
        inspecciones: 'inspecciones',
        fotografias: 'fotografias',
        operaciones: 'operaciones'
    };

    function soportado() {
        return typeof window !== 'undefined'
            && typeof window.indexedDB !== 'undefined'
            && window.indexedDB !== null;
    }

    function crearStores(db) {
        Object.keys(STORES).forEach(function (nombre) {
            if (db.objectStoreNames.contains(nombre)) {
                return;
            }

            var config = STORES[nombre];
            var store = db.createObjectStore(nombre, {
                keyPath: config.keyPath,
                autoIncrement: config.autoIncrement
            });

            config.indices.forEach(function (indice) {
                store.createIndex(indice.nombre, indice.keyPath, { unique: false });
            });
        });
    }

    function abrir() {
        return new Promise(function (resolver, rechazar) {
            if (!soportado()) {
                rechazar(new Error('SIGOA: IndexedDB no está disponible en este navegador.'));
                return;
            }

            var peticion = window.indexedDB.open(NOMBRE_BASE, VERSION_BASE);

            peticion.onupgradeneeded = function (evento) {
                crearStores(evento.target.result);
            };

            peticion.onsuccess = function (evento) {
                resolver(evento.target.result);
            };

            peticion.onerror = function () {
                rechazar(peticion.error || new Error('SIGOA: no se pudo abrir la base IndexedDB.'));
            };

            peticion.onblocked = function () {
                rechazar(new Error('SIGOA: la base IndexedDB está bloqueada por otra pestaña.'));
            };
        });
    }

    function conStore(nombreStore, modo, accion) {
        return abrir().then(function (db) {
            return new Promise(function (resolver, rechazar) {
                var transaccion;
                var peticion = null;

                try {
                    transaccion = db.transaction(nombreStore, modo);
                } catch (error) {
                    db.close();
                    rechazar(error);
                    return;
                }

                transaccion.oncomplete = function () {
                    db.close();
                    resolver(peticion ? peticion.result : undefined);
                };

                transaccion.onerror = function () {
                    db.close();
                    rechazar(transaccion.error || new Error('SIGOA: falló la transacción IndexedDB.'));
                };

                transaccion.onabort = function () {
                    db.close();
                    rechazar(transaccion.error || new Error('SIGOA: la transacción IndexedDB fue abortada.'));
                };

                try {
                    peticion = accion(transaccion.objectStore(nombreStore));
                } catch (error) {
                    db.close();
                    rechazar(error);
                }
            });
        });
    }

    function obtener(nombreStore, clave) {
        return conStore(nombreStore, 'readonly', function (store) {
            return store.get(clave);
        });
    }

    function obtenerTodos(nombreStore) {
        return conStore(nombreStore, 'readonly', function (store) {
            return store.getAll();
        });
    }

    function guardar(nombreStore, valor) {
        return conStore(nombreStore, 'readwrite', function (store) {
            return store.put(valor);
        });
    }

    function agregar(nombreStore, valor) {
        return conStore(nombreStore, 'readwrite', function (store) {
            return store.add(valor);
        });
    }

    function eliminar(nombreStore, clave) {
        return conStore(nombreStore, 'readwrite', function (store) {
            return store.delete(clave);
        });
    }

    function limpiar(nombreStore) {
        return conStore(nombreStore, 'readwrite', function (store) {
            return store.clear();
        });
    }

    function contar(nombreStore) {
        return conStore(nombreStore, 'readonly', function (store) {
            return store.count();
        });
    }

    /* ------------------------------------------------------------------
       Inicialización: crea/abre la base (y sus stores) y solicita
       almacenamiento persistente cuando el navegador lo soporta.
       No escribe datos: solo prepara la capa local.
       ------------------------------------------------------------------ */
    function iniciar() {
        if (!soportado()) {
            if (window.console && console.warn) {
                console.warn('SIGOA: IndexedDB no disponible; el modo offline no podrá operar.');
            }
            return Promise.resolve(false);
        }

        return abrir().then(function (db) {
            db.close();

            if (navigator.storage && typeof navigator.storage.persist === 'function') {
                navigator.storage.persist().then(function (otorgado) {
                    if (window.console && console.info) {
                        console.info('SIGOA: almacenamiento persistente ' + (otorgado ? 'otorgado' : 'no otorgado') + '.');
                    }
                }).catch(function () {
                    /* La persistencia es mejor esfuerzo: no bloquea D.1. */
                });
            }

            return true;
        }).catch(function (error) {
            if (window.console && console.error) {
                console.error('SIGOA: no se pudo inicializar IndexedDB.', error);
            }
            return false;
        });
    }

    /* ------------------------------------------------------------------
       Autotest explícito de persistencia (Fase D.1, punto 8).
       Se ejecuta SOLO cuando se lo invoca manualmente (consola/chequeo):
       guarda un registro de prueba en `inspecciones`, lo recupera y lo
       elimina mediante una operación explícita. No borra datos ajenos.
       ------------------------------------------------------------------ */
    function autotest() {
        if (!window.SIGOA || !window.SIGOA.uuid) {
            return Promise.reject(new Error('SIGOA: componente uuid no cargado.'));
        }

        var uuid = window.SIGOA.uuid.v4();
        var ahora = new Date().toISOString();

        var registroPrueba = {
            uuid: uuid,
            obra_id: 0,
            inspector_id: 0,
            fecha_inspeccion: '1970-01-01',
            hora_inspeccion: '00:00:00',
            observacion: 'Registro de prueba D.1 (borrado automático al finalizar el autotest).',
            estado_local: 'PRUEBA',
            created_at_local: ahora,
            updated_at_local: ahora
        };

        return guardar(ALMACENES.inspecciones, registroPrueba)
            .then(function () {
                return obtener(ALMACENES.inspecciones, uuid);
            })
            .then(function (recuperado) {
                var guardadoOk = !!recuperado && recuperado.uuid === uuid
                    && recuperado.estado_local === 'PRUEBA';

                return eliminar(ALMACENES.inspecciones, uuid).then(function () {
                    return obtener(ALMACENES.inspecciones, uuid);
                }).then(function (eliminado) {
                    var ok = guardadoOk && eliminado === undefined;

                    if (window.console) {
                        (ok ? console.log : console.error)(
                            ok
                                ? 'SIGOA autotest OK — guardado/recuperado/eliminado (uuid ' + uuid + ').'
                                : 'SIGOA autotest FALLÓ — revisar la capa IndexedDB.'
                        );
                    }

                    return { ok: ok, uuid: uuid, guardado: guardadoOk, eliminado: eliminado === undefined };
                });
            });
    }

    window.SIGOA = window.SIGOA || {};
    window.SIGOA.almacenamiento = {
        NOMBRE_BASE: NOMBRE_BASE,
        VERSION_BASE: VERSION_BASE,
        ALMACENES: ALMACENES,
        STORES: STORES,
        soportado: soportado,
        abrir: abrir,
        obtener: obtener,
        obtenerTodos: obtenerTodos,
        guardar: guardar,
        agregar: agregar,
        eliminar: eliminar,
        limpiar: limpiar,
        contar: contar,
        iniciar: iniciar,
        autotest: autotest
    };
})();