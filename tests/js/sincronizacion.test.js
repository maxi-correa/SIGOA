/* ===================================================================
   Pruebas de la lógica de la cola de sincronización (Fase D.4)
   ===================================================================
   Ejecuta `public/assets/js/components/sincronizacion.js` en un
   entorno Node simulado (sin navegador y sin IndexedDB real) mediante el
   módulo `vm`, y verifica el comportamiento que no puede observarse con
   pruebas estructurales:

     * tabla de backoff y cálculo de elegibilidad;
     * idempotencia del encolado;
     * orden obligatorio inspecciones → fotografías;
     * fotografías bloqueadas mientras su padre no esté sincronizado;
     * liberación de blobs solo tras la confirmación del servidor;
      * 401 no toca ninguna entidad;
      * agotamiento de reintentos → ERROR;
      * reintento manual;
      * (D.6.2) una operación agotada sale de la cola automática y solo la
      *   acción manual "Sincronizar" la revive, sin tocar los datos;
      * (D.6.2) `ALREADY_SYNCED` como éxito lógico e idempotencia;
      * (D.6.2) el diagnóstico de la cola es de solo lectura.


   Uso:  node tests/js/sincronizacion.test.js
   =================================================================== */

'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

/* ------------------------------------------------------------------ */
/* Micro-assert                                                        */
/* ------------------------------------------------------------------ */

let pruebas = 0;
let fallos = 0;

function ok(condicion, mensaje) {
    pruebas++;

    if (condicion) {
        return;
    }

    fallos++;
    console.error('  FALLA: ' + mensaje);
}

function igual(esperado, obtenido, mensaje) {
    ok(
        esperado === obtenido,
        mensaje + ' (esperado: ' + JSON.stringify(esperado) + ', obtenido: ' + JSON.stringify(obtenido) + ')'
    );
}

function titulo(nombre) {
    console.log('· ' + nombre);
}

/* ------------------------------------------------------------------ */
/* Almacenamiento simulado (IndexedDB en memoria)                        */
/* ------------------------------------------------------------------ */

function crearAlmacenamiento() {
    const datos = {
        inspecciones: new Map(),
        fotografias: new Map(),
        operaciones: new Map()
    };
    let siguienteId = 1;

    function clave(store, valor) {
        if (store === 'operaciones') {
            return valor.id;
        }

        return valor.uuid;
    }

    function clonar(valor) {
        return valor === null || valor === undefined ? valor : Object.assign({}, valor);
    }

    return {
        ALMACENES: {
            inspecciones: 'inspecciones',
            fotografias: 'fotografias',
            operaciones: 'operaciones'
        },
        soportado: function () {
            return true;
        },
        obtenerTodos: function (store) {
            return Promise.resolve(Array.from(datos[store].values()).map(clonar));
        },
        obtener: function (store, id) {
            return Promise.resolve(clonar(datos[store].get(id)));
        },
        guardar: function (store, valor) {
            const copia = clonar(valor);
            datos[store].set(clave(store, copia), copia);
            return Promise.resolve(clave(store, copia));
        },
        agregar: function (store, valor) {
            const copia = clonar(valor);

            if (store === 'operaciones') {
                copia.id = siguienteId++;
            }

            datos[store].set(clave(store, copia), copia);

            return Promise.resolve(clave(store, copia));
        },
        eliminar: function (store, id) {
            datos[store].delete(id);
            return Promise.resolve();
        },
        contar: function (store) {
            return Promise.resolve(datos[store].size);
        },
        buscarPorIndice: function (store, indice, valor) {
            const claveIndice = indice === 'por_obra' ? 'obra_id' : 'inspeccion_uuid';

            return Promise.resolve(
                Array.from(datos[store].values())
                    .filter(function (item) {
                        return item[claveIndice] === valor;
                    })
                    .map(clonar)
            );
        },
        /* Utilidades de aserción para las pruebas. */
        _datos: datos
    };
}

/* ------------------------------------------------------------------ */
/* Entorno simulado                                                    */
/* ------------------------------------------------------------------ */

const RUTA_COMPONENTE = path.join(__dirname, '..', '..', 'public', 'assets', 'js', 'components', 'sincronizacion.js');

function crearEntorno(opciones) {
    const config = opciones || {};
    const almacen = crearAlmacenamiento();
    const llamadas = [];

    function respuesta(estado, cuerpo) {
        return Promise.resolve({
            ok: estado >= 200 && estado < 300,
            status: estado,
            json: function () {
                return Promise.resolve(cuerpo);
            }
        });
    }

    const api = {
        respuesta: respuesta,
        llamadas: llamadas
    };

    const contexto = {
        console: console,
        Promise: Promise,
        Object: Object,
        Array: Array,
        Math: Math,
        Date: Date,
        JSON: JSON,
        String: String,
        Number: Number,
        Boolean: Boolean,
        isFinite: isFinite,
        isNaN: isNaN,
        parseInt: parseInt,
        parseFloat: parseFloat,
        setTimeout: setTimeout,
        clearTimeout: clearTimeout,
        FormData: function () {
            this.campos = [];
            this.append = function (nombre, valor) {
                this.campos.push([nombre, valor]);
            };
        },
        fetch: function (url, init) {
            llamadas.push({ url: url, init: init, cuerpo: (init && init.body) || null });

            if (typeof config.fetch === 'function') {
                return config.fetch.call(api, url, init, llamadas.length - 1);
            }

            return respuesta(200, { ok: true, results: [] });
        }
    };

    contexto.window = contexto;
    contexto.globalThis = contexto;

    contexto.navigator = { onLine: config.online !== false };

    contexto.document = {
        cookie: 'csrf_cookie_name=token-de-prueba',
        visibilityState: 'visible',
        addEventListener: function () {},
        getElementById: function () {
            return null;
        },
        querySelector: function (selector) {
            if (selector === 'meta[name="X-CSRF-TOKEN"]' && config.csrfMeta) {
                return {
                    getAttribute: function (name) {
                        return name === 'content' ? config.csrfMeta : null;
                    }
                };
            }

            return null;
        }
    };

    contexto.SIGOA = {
        almacenamiento: almacen,
        conectividad: {
            esOnline: function () {
                return contexto.navigator.onLine;
            },
            alCambiar: function () {
                return function () {};
            }
        }
    };

    vm.createContext(contexto);
    vm.runInContext(fs.readFileSync(RUTA_COMPONENTE, 'utf8'), contexto, { filename: 'sincronizacion.js' });

    return {
        sync: contexto.SIGOA.sincronizacion,
        almacen: almacen,
        llamadas: llamadas,
        respuesta: respuesta,
        contexto: contexto
    };
}

/* ------------------------------------------------------------------ */
/* Fixtures                                                            */
/* ------------------------------------------------------------------ */

const UUID_INSP = '11111111-1111-4111-8111-111111111111';
const UUID_FOTO = '22222222-2222-4222-8222-222222222222';
const ENDPOINT_INSPECCIONES = '/inspector/sincronizar/inspecciones';
const ENDPOINT_FOTOGRAFIAS = '/inspector/sincronizar/fotografias';

function crearInspeccion(extra) {
    return Object.assign({
        uuid: UUID_INSP,
        obra_id: 1,
        inspector_id: 7,
        fecha_inspeccion: '2026-03-15',
        hora_inspeccion: '10:30:00',
        observacion: 'Prueba',
        estado_local: 'PENDIENTE_SYNC',
        created_at_local: '2026-03-15T10:30:00.000Z',
        updated_at_local: '2026-03-15T10:30:00.000Z'
    }, extra || {});
}

function crearFotografia(extra) {
    return Object.assign({
        uuid: UUID_FOTO,
        inspeccion_uuid: UUID_INSP,
        ancho: 800,
        alto: 600,
        tamano_bytes: 1024,
        fecha_hora_captura: '2026-03-15T10:31:00.000Z',
        estado_local: 'PENDIENTE_SYNC',
        blob: { id: 'blob-optimizado' },
        thumbnail: { id: 'blob-miniatura' }
    }, extra || {});
}

function sembrar(entorno, inspeccion, fotografias) {
    const promesas = [];

    if (inspeccion) {
        promesas.push(entorno.almacen.guardar('inspecciones', inspeccion));
    }

    (fotografias || []).forEach(function (foto) {
        promesas.push(entorno.almacen.guardar('fotografias', foto));
    });

    return Promise.all(promesas);
}

/**
 * Inserta una operación directamente en el store, con el estado exacto que
 * se quiere reproducir. Se usa para los casos en los que la operación nunca
 * se crearía sola (agotamiento, cierre a mitad de un intento).
 */
function sembrarOperacion(entorno, operacion) {
    return entorno.almacen.guardar('operaciones', operacion);
}

    function respuestaServidor(estado) {
        return function (url) {
            if (estado === 'ok') {
                /* El endpoint de inspecciones solo responde por inspecciones
                   y el de fotografías solo por la fotografía enviada: el
                   cliente nunca debe confundir un resultado con otro. */
                if (url === ENDPOINT_INSPECCIONES) {
                    return this.respuesta(200, {
                        ok: true,
                        results: [{ uuid: UUID_INSP, estado: 'SYNCED', id: 42 }]
                    });
                }

                return this.respuesta(200, {
                    ok: true,
                    results: [{
                        uuid: UUID_FOTO,
                        estado: 'SYNCED',
                        id: 99,
                        nombre_archivo: 'INS-00042-20260315-103000-a1b2c3.jpg',
                        ruta_relativa: 'OBR-000001/2026-03-15/' + UUID_INSP + '/IMAGENES/INS-00042-20260315-103000-a1b2c3.jpg',
                        ruta_thumbnail: 'OBR-000001/2026-03-15/' + UUID_INSP + '/THUMBNAILS/THB-00042-20260315-103000-d4e5f6.jpg'
                    }]
                });
            }

            if (estado === '401') {
                return this.respuesta(401, { ok: false, error: 'AUTH_REQUIRED' });
            }

            return this.respuesta(200, { ok: true, results: [] });
        };
    }

/* ------------------------------------------------------------------ */
/* Pruebas                                                             */
/* ------------------------------------------------------------------ */

function probarBackoff() {
    titulo('Backoff progresivo');

    const entorno = crearEntorno({});
    const s = entorno.sync;

    igual(0, s.retrasoPara(0), 'El primer intento es inmediato');
    igual(5000, s.retrasoPara(1), 'Primer reintento a 5 s');
    igual(15000, s.retrasoPara(2), 'Segundo reintento a 15 s');
    igual(30000, s.retrasoPara(3), 'Tercer reintento a 30 s');
    igual(60000, s.retrasoPara(4), 'Cuarto reintento a 60 s');
    igual(300000, s.retrasoPara(5), 'Quinto reintento a 5 min');
    igual(300000, s.retrasoPara(6), 'Sin sixth escalón: se mantiene el último');
    igual(6, s.MAX_INTENTOS, 'Se permiten 6 intentos en total');
}

function probarElegibilidad() {
    titulo('Elegibilidad y espera restante');

    const entorno = crearEntorno({});
    const s = entorno.sync;
    const ahora = Date.parse('2026-03-15T12:00:00.000Z');

    ok(s.puedeReintentar({ estado: 'PENDIENTE', intentos: 0 }, ahora), 'Sin intentos es elegible');

    const conUnIntento = {
        estado: 'PENDIENTE',
        intentos: 1,
        ultimo_intento: '2026-03-15T11:59:58.000Z'
    };

    ok(!s.puedeReintentar(conUnIntento, ahora), 'Dentro del backoff no es elegible');
    igual(3000, s.esperaRestante(conUnIntento, ahora), 'Faltan 3 s de los 5 s de espera');

    ok(
        s.puedeReintentar(conUnIntento, Date.parse('2026-03-15T12:00:03.000Z')),
        'Pasado el backoff vuelve a ser elegible'
    );

    ok(!s.puedeReintentar({ estado: 'SINCRONIZADA', intentos: 0 }, ahora), 'Una operación sincronizada no se reintenta');
    ok(!s.puedeReintentar({ estado: 'ERROR', intentos: 1 }, ahora), 'Una operación en ERROR no se reintenta sola');

    const agotada = {
        estado: 'PENDIENTE',
        intentos: s.MAX_INTENTOS,
        ultimo_intento: '2026-03-15T11:00:00.000Z'
    };

    ok(!s.puedeReintentar(agotada, ahora), 'Agotados los intentos no es elegible');
    igual(0, s.esperaRestante(agotada, now(agotada)), 'Sin intentos restantes no hay espera que programar');
}

function now(operacion) {
    return Date.parse(operacion.ultimo_intento) + 3600000;
}

function probarEncolado() {
    titulo('Encolado idempotente');

    const entorno = crearEntorno({});
    const s = entorno.sync;
    let creada = null;

    return s.encolar(s.TIPO_INSPECCION, UUID_INSP, null)
        .then(function (primera) {
            creada = primera;
            igual('PENDIENTE', primera.estado, 'La operación nace PENDIENTE');
            igual(0, primera.intentos, 'La operación nace sin intentos');
            igual('INSPECCION', primera.tipo, 'La operación conserva su tipo');

            return s.encolar(s.TIPO_INSPECCION, UUID_INSP, null);
        })
        .then(function (segunda) {
            igual(1, entorno.almacen._datos.operaciones.size, 'Encolar dos veces no duplica');
            igual(creada.created_at, segunda.created_at, 'Devuelve la operación existente, no una nueva');

            return s.obtenerOperaciones();
        })
        .then(function (operaciones) {
            /* Una operación ya en ERROR conserva su estado: reencolar no es
               un reintento manual. */
            operaciones[0].estado = 'ERROR';
            operaciones[0].intentos = 6;
            return entorno.almacen.guardar('operaciones', operaciones[0]);
        })
        .then(function () {
            return s.encolar(s.TIPO_INSPECCION, UUID_INSP, null);
        })
        .then(function (devuelta) {
            igual('ERROR', devuelta.estado, 'Encolar no revive una operación en ERROR');
            igual(6, devuelta.intentos, 'Encolar no reinicia el contador de intentos');
        });
}

function probarAsegurarCola() {
    titulo('Recuperación de la cola al reabrir');

    const entorno = crearEntorno({});
    const s = entorno.sync;

    return sembrar(
        entorno,
        crearInspeccion(),
        [crearFotografia()]
    )
        .then(function () {
            return s.asegurarCola();
        })
        .then(function () {
            igual(2, entorno.almacen._datos.operaciones.size, 'Se crean las operaciones que faltaban');
        })
        .then(function () {
            return s.asegurarCola();
        })
        .then(function () {
            igual(2, entorno.almacen._datos.operaciones.size, 'Asegurar la cola dos veces no duplica');
        })
        .then(function () {
            return entorno.almacen.obtenerTodos('operaciones');
        })
        .then(function (operaciones) {
            const foto = operaciones.filter(function (o) {
                return o.tipo === 'FOTOGRAFIA';
            })[0];

            igual(UUID_INSP, foto.dependencia_uuid, 'La fotografía depende de su inspección');
        });
}

function probarOrdenObligatorio() {
    titulo('Orden inspecciones → fotografías');

    const entorno = crearEntorno({ fetch: respuestaServidor('ok') });
    const s = entorno.sync;

    return sembrar(entorno, crearInspeccion(), [crearFotografia()])
        .then(function () {
            return s.sincronizarTodo();
        })
        .then(function () {
            igual(2, entorno.llamadas.length, 'Se realizan dos peticiones');

            const primera = entorno.llamadas[0];
            const segunda = entorno.llamadas[1];

            igual('/inspector/sincronizar/inspecciones', primera.url, 'Primero se sincronizan las inspecciones');
            igual('/inspector/sincronizar/fotografias', segunda.url, 'Después, las fotografías');
            ok(segunda.cuerpo instanceof Object && Array.isArray(segunda.cuerpo.campos), 'La fotografía se envía como multipart');
        });
}

function probarFotografiaBloqueada() {
    titulo('Fotografía bloqueada mientras su padre no está sincronizado');

    const entorno = crearEntorno({ fetch: respuestaServidor('ok') });
    const s = entorno.sync;

    return sembrar(entorno, crearInspeccion(), [crearFotografia()])
        .then(function () {
            return s.encolar(s.TIPO_FOTOGRAFIA, UUID_FOTO, UUID_INSP);
        })
        .then(function () {
            /* Solo la fotografía está encolada: la inspección aún no se
               sincronizó, así que no puede enviarse. */
            return s.sincronizarFotografias();
        })
        .then(function (resumen) {
            igual(0, entorno.llamadas.length, 'No se intenta subir la fotografía');

            return entorno.almacen.obtenerTodos('operaciones');
        })
        .then(function (operaciones) {
            const foto = operaciones.filter(function (o) {
                return o.tipo === 'FOTOGRAFIA';
            })[0];

            igual('PENDIENTE', foto.estado, 'La fotografía sigue PENDIENTE');
            igual(0, foto.intentos, 'La fotografía bloqueada no consume intentos');
            igual(null, foto.error, 'La fotografía bloqueada no pasa a ERROR');
        });
}

function probarLiberacionDeBlobs() {
    titulo('Los blobs se liberan solo tras la confirmación');

    const entorno = crearEntorno({ fetch: respuestaServidor('ok') });
    const s = entorno.sync;

    return sembrar(
        entorno,
        crearInspeccion({ estado_local: 'SINCRONIZADA', servidor_id: 42 }),
        [crearFotografia()]
    )
        .then(function () {
            return s.encolar(s.TIPO_FOTOGRAFIA, UUID_FOTO, UUID_INSP);
        })
        .then(function () {
            return s.sincronizarFotografias();
        })
        .then(function () {
            return entorno.almacen.obtener('fotografias', UUID_FOTO);
        })
        .then(function (foto) {
            igual('SINCRONIZADA', foto.estado_local, 'La fotografía queda sincronizada');
            igual(99, foto.servidor_id, 'Se guarda el id del servidor');
            igual(null, foto.blob, 'El blob optimizado se libera');
            igual(null, foto.thumbnail, 'El thumbnail se libera');
            ok(typeof foto.blobs_liberados_at === 'string', 'Se registra cuándo se liberaron los blobs');
            ok(typeof foto.ruta_thumbnail === 'string', 'Se guarda la ruta del thumbnail del servidor');
        })
        .then(function () {
            return entorno.almacen.obtenerTodos('operaciones');
        })
        .then(function (operaciones) {
            igual('SINCRONIZADA', operaciones[0].estado, 'La operación queda confirmada');
        });
}

function probarBlobsSeConservanAnteFallo() {
    titulo('Ante un fallo los blobs se conservan');

    const entorno = crearEntorno({
        fetch: function () {
            return entorno.respuesta(500, { ok: false, error: 'SERVER_ERROR' });
        }
    });
    const s = entorno.sync;

    return sembrar(
        entorno,
        crearInspeccion({ estado_local: 'SINCRONIZADA', servidor_id: 42 }),
        [crearFotografia()]
    )
        .then(function () {
            return s.encolar(s.TIPO_FOTOGRAFIA, UUID_FOTO, UUID_INSP);
        })
        .then(function () {
            return s.sincronizarFotografias();
        })
        .then(function () {
            return entorno.almacen.obtener('fotografias', UUID_FOTO);
        })
        .then(function (foto) {
            ok(foto.blob !== null && foto.blob !== undefined, 'El blob se conserva para reintentar');
            igual('PENDIENTE_SYNC', foto.estado_local, 'La fotografía sigue pendiente');
        });
}

function probarSesionExpirada() {
    titulo('401 detiene el ciclo sin tocar los datos');

    const entorno = crearEntorno({ fetch: respuestaServidor('401') });
    const s = entorno.sync;

    return sembrar(entorno, crearInspeccion(), [crearFotografia()])
        .then(function () {
            return s.sincronizarTodo();
        })
        .then(function (resumen) {
            ok(resumen.authRequerida === true, 'Se informa que la sesión expiró');
            igual(1, entorno.llamadas.length, 'No se intentan las fotografías tras el 401');

            return Promise.all([
                entorno.almacen.obtener('inspecciones', UUID_INSP),
                entorno.almacen.obtener('fotografias', UUID_FOTO),
                entorno.almacen.obtenerTodos('operaciones')
            ]);
        })
        .then(function (resultados) {
            igual('PENDIENTE_SYNC', resultados[0].estado_local, 'La inspección no cambia de estado');
            igual(undefined, resultados[0].servidor_id, 'No se inventa un id de servidor');
            ok(resultados[1].blob !== null, 'El blob de la fotografía no se toca');

            const inspeccion = resultados[2].filter(function (o) {
                return o.tipo === 'INSPECCION';
            })[0];

            igual('PENDIENTE', inspeccion.estado, 'La operación vuelve a PENDIENTE');
            igual(0, inspeccion.intentos, 'El 401 no consume intento: la cola no se agota sin sesión');
            igual(null, inspeccion.ultimo_intento, 'Sin intentos registrados tampoco queda fecha de último intento');
        });
}

function probarColaNoSeCongelaSinSesion() {
    titulo('La cola no se congela tras varios 401');

    const entorno = crearEntorno({ fetch: respuestaServidor('401') });
    const s = entorno.sync;

    return sembrar(entorno, crearInspeccion(), [])
        .then(function () {
            return s.sincronizarTodo();
        })
        .then(function () {
            return s.sincronizarTodo();
        })
        .then(function () {
            return s.sincronizarTodo();
        })
        .then(function () {
            return s.sincronizarTodo();
        })
        .then(function () {
            return s.sincronizarTodo();
        })
        .then(function () {
            return s.sincronizarTodo();
        })
        .then(function () {
            return s.sincronizarTodo();
        })
        .then(function () {
            return entorno.almacen.obtenerTodos('operaciones');
        })
        .then(function (operaciones) {
            igual(1, operaciones.length, 'La operación sigue en la cola');
            igual('PENDIENTE', operaciones[0].estado, 'Nunca pasa a ERROR por falta de sesión');
            igual(0, operaciones[0].intentos, 'Ninguno de los ciclos consumió un intento');

            return s.obtenerOperaciones();
        })
        .then(function (operaciones) {
            ok(s.puedeReintentar(operaciones[0]), 'La cola queda disponible para reanudarse al iniciar sesión');
        });
}

function probarTokenCsrfInvalido() {
    titulo('403 por token de seguridad inválido');

    const entorno = crearEntorno({
        fetch: function () {
            return this.respuesta(403, { ok: false, error: 'CSRF_INVALID' });
        }
    });
    const s = entorno.sync;

    return sembrar(entorno, crearInspeccion(), [crearFotografia()])
        .then(function () {
            return s.sincronizarTodo();
        })
        .then(function (resumen) {
            ok(resumen.csrfInvalido === true, 'Se informa que el token de seguridad venció');
            igual(1, entorno.llamadas.length, 'No se intentan las fotografías tras el 403 de CSRF');

            return Promise.all([
                entorno.almacen.obtener('inspecciones', UUID_INSP),
                entorno.almacen.obtenerTodos('operaciones')
            ]);
        })
        .then(function (resultados) {
            igual('PENDIENTE_SYNC', resultados[0].estado_local, 'La inspección conserva su estado local');

            const inspeccion = resultados[1].filter(function (o) {
                return o.tipo === 'INSPECCION';
            })[0];

            igual('PENDIENTE', inspeccion.estado, 'La operación vuelve a PENDIENTE');
            igual(0, inspeccion.intentos, 'El token vencido no consume intentos');
        });
}

function probarTokenCsrfDesdeMeta() {
    titulo('El token CSRF se toma del meta de la página');

    const entorno = crearEntorno({
        csrfMeta: 'token-del-meta',
        fetch: respuestaServidor('ok')
    });
    const s = entorno.sync;

    return sembrar(entorno, crearInspeccion(), [])
        .then(function () {
            return s.sincronizarTodo();
        })
        .then(function () {
            igual(1, entorno.llamadas.length, 'Se realizó una sola petición');
            igual(
                'token-del-meta',
                entorno.llamadas[0].init.headers['X-CSRF-TOKEN'],
                'La cabecera toma el token emitido por el layout, no la cookie'
            );
        });
}

function probarRechazoPermanente() {
    titulo('Rechazo permanente pasa la entidad a ERROR');

    const entorno = crearEntorno({
        fetch: function () {
            return entorno.respuesta(200, {
                ok: true,
                results: [{
                    uuid: UUID_INSP,
                    estado: 'REJECTED',
                    error: 'HISTORICAL_AUTHORIZATION_FAILED',
                    mensaje: 'El inspector no estaba asignado a la obra.'
                }]
            });
        }
    });
    const s = entorno.sync;

    return sembrar(entorno, crearInspeccion(), [])
        .then(function () {
            return s.sincronizarTodo();
        })
        .then(function (resumen) {
            igual(1, resumen.inspecciones.rechazadas, 'El resumen cuenta el rechazo');

            return Promise.all([
                entorno.almacen.obtener('inspecciones', UUID_INSP),
                entorno.almacen.obtenerTodos('operaciones')
            ]);
        })
        .then(function (resultados) {
            igual('ERROR', resultados[0].estado_local, 'La inspección pasa a ERROR');
            ok(typeof resultados[0].error_local === 'string', 'Se guarda el motivo del rechazo');
            igual('ERROR', resultados[1][0].estado, 'La operación pasa a ERROR');
        });
}

function probarAgotamientoDeIntentos() {
    titulo('Agotamiento de reintentos');

    const entorno = crearEntorno({
        fetch: function () {
            return entorno.respuesta(503, { ok: false, error: 'SERVER_ERROR' });
        }
    });
    const s = entorno.sync;

    return sembrar(entorno, crearInspeccion(), [])
        .then(function () {
            return s.encolar(s.TIPO_INSPECCION, UUID_INSP, null);
        })
        .then(function () {
            /* Se simulan cinco fallos previos con el backoff ya transcurrido. */
            return entorno.almacen.obtenerTodos('operaciones').then(function (operaciones) {
                operaciones[0].intentos = s.MAX_INTENTOS - 1;
                operaciones[0].ultimo_intento = '2020-01-01T00:00:00.000Z';
                return entorno.almacen.guardar('operaciones', operaciones[0]);
            });
        })
        .then(function () {
            return s.sincronizarTodo();
        })
        .then(function () {
            return entorno.almacen.obtenerTodos('operaciones');
        })
        .then(function (operaciones) {
            igual('ERROR', operaciones[0].estado, 'Al agotarse los intentos la operación queda en ERROR');
            igual(s.MAX_INTENTOS, operaciones[0].intentos, 'Se consumieron todos los intentos');
        });
}

function probarReintentoManual() {
    titulo('Reintento manual');

    const entorno = crearEntorno({});
    const s = entorno.sync;

    return sembrar(entorno, crearInspeccion({ estado_local: 'ERROR', error_local: 'fallo previo' }), [])
        .then(function () {
            return s.encolar(s.TIPO_INSPECCION, UUID_INSP, null);
        })
        .then(function () {
            return entorno.almacen.obtenerTodos('operaciones');
        })
        .then(function (operaciones) {
            operaciones[0].estado = 'ERROR';
            operaciones[0].intentos = 6;
            operaciones[0].error = 'agotada';
            return entorno.almacen.guardar('operaciones', operaciones[0]);
        })
        .then(function () {
            return s.reintentar(s.TIPO_INSPECCION, UUID_INSP);
        })
        .then(function (operacion) {
            igual('PENDIENTE', operacion.estado, 'La operación vuelve a la cola');
            igual(0, operacion.intentos, 'El contador de intentos se reinicia');
            igual(null, operacion.ultimo_intento, 'Se limpia la marca del último intento');
            igual(null, operacion.error, 'Se limpia el error');

            return entorno.almacen.obtener('inspecciones', UUID_INSP);
        })
        .then(function (inspeccion) {
            igual('PENDIENTE_SYNC', inspeccion.estado_local, 'La entidad vuelve a PENDIENTE_SYNC');
            igual(null, inspeccion.error_local, 'Se limpia el error de la entidad');
        });
}

function probarRespuestaAjenaNoSeAplica() {
    titulo('Un resultado ajeno no se aplica a la fotografía');

    const entorno = crearEntorno({
        fetch: function (url) {
            if (url === ENDPOINT_INSPECCIONES) {
                return this.respuesta(200, {
                    ok: true,
                    results: [{ uuid: UUID_INSP, estado: 'SYNCED', id: 42 }]
                });
            }

            return this.respuesta(200, {
                ok: true,
                results: [{ uuid: '33333333-3333-4333-8333-333333333333', estado: 'SYNCED', id: 77 }]
            });
        }
    });
    const s = entorno.sync;

    return sembrar(
        entorno,
        crearInspeccion({ estado_local: 'SINCRONIZADA', servidor_id: 42 }),
        [crearFotografia()]
    )
        .then(function () {
            return s.encolar(s.TIPO_FOTOGRAFIA, UUID_FOTO, UUID_INSP);
        })
        .then(function () {
            return s.sincronizarFotografias();
        })
        .then(function () {
            return entorno.almacen.obtener('fotografias', UUID_FOTO);
        })
        .then(function (foto) {
            igual('ERROR', foto.estado_local, 'La fotografía pasa a ERROR');
            ok(!foto.servidor_id, 'No se adopta el id de otra fotografía');
            ok(foto.blob !== null, 'Los blobs se conservan: el reintento manual es posible');
        });
}

function probarSinConexion() {
    titulo('Dispositivo sin conexión');

    const entorno = crearEntorno({ online: false });
    const s = entorno.sync;

    return s.sincronizarTodo().then(function (resumen) {
        ok(resumen.sinConexion === true, 'No se intenta nada sin conexión');
        igual(0, entorno.llamadas.length, 'No se realiza ninguna petición');
    });
}

function probarSoloInspecciones() {
    titulo('El ciclo acotado no toca fotografías');

    const entorno = crearEntorno({ fetch: respuestaServidor('ok') });
    const s = entorno.sync;

    return sembrar(entorno, crearInspeccion(), [crearFotografia()])
        .then(function () {
            return s.sincronizarInspecciones(1);
        })
        .then(function (resumen) {
            igual(1, entorno.llamadas.length, 'Solo se hace una petición');
            igual('/inspector/sincronizar/inspecciones', entorno.llamadas[0].url, 'Solo se piden inspecciones');

            return entorno.almacen.obtener('fotografias', UUID_FOTO);
        })
        .then(function (foto) {
            igual('PENDIENTE_SYNC', foto.estado_local, 'La fotografía permanece pendiente');
        });
}

function probarFiltroPorObra() {
    titulo('Filtro por obra');

    const entorno = crearEntorno({ fetch: respuestaServidor('ok') });
    const s = entorno.sync;

    return sembrar(entorno, crearInspeccion({ obra_id: 99 }), [])
        .then(function () {
            return s.sincronizarTodo({ obraId: 1 });
        })
        .then(function () {
            igual(0, entorno.llamadas.length, 'No se sincroniza la inspección de otra obra');
        });
}

function probarCodigosPermanentes() {
    titulo('Códigos no reintentables');

    const s = crearEntorno({}).sync;

    ok(s.esErrorPermanente('MIME_NO_PERMITIDO'), 'MIME_NO_PERMITIDO es permanente');
    ok(s.esErrorPermanente('HISTORICAL_AUTHORIZATION_FAILED'), 'HISTORICAL_AUTHORIZATION_FAILED es permanente');
    ok(s.esErrorPermanente('ARCHIVO_DEMASIADO_GRANDE'), 'ARCHIVO_DEMASIADO_GRANDE es permanente');
    ok(!s.esErrorPermanente('SERVER_ERROR'), 'SERVER_ERROR sí se reintenta');
    ok(!s.esErrorPermanente('STORAGE_ERROR'), 'STORAGE_ERROR sí se reintenta');
    ok(!s.esErrorPermanente(undefined), 'Un código ausente se reintenta');
}

/* ------------------------------------------------------------------ */
/* Fase D.6.2 — una operación agotada se revive con "Sincronizar"       */
/* ------------------------------------------------------------------ */

/**
 * Regresión del caso observado en la prueba manual: una operación con los
 * `MAX_INTENTOS` consumidos queda fuera de `operacionesElegibles()`, y
 * `programarDiferido()` no programa temporizador para ella, así que ningún
 * disparador automático la vuelve a intentar.
 *
 * Se reproducen los dos estados en los que eso ocurre:
 *   - `ERROR`: el ciclo falló y consumió el último intento;
 *   - `SINCRONIZANDO` con los intentos agotados: la aplicación se cerró
 *     (o la pestaña fue descartada) mientras el sexto intento estaba en
 *     vuelo, y la etiqueta mostrada era "Sincronizando…".
 */
function sembrarColaAgotada(entorno) {
    const s = entorno.sync;

    return sembrar(entorno, crearInspeccion(), [crearFotografia()])
        .then(function () {
            return sembrarOperacion(entorno, {
                id: 1,
                tipo: 'INSPECCION',
                entidad_uuid: UUID_INSP,
                dependencia_uuid: null,
                estado: 'SINCRONIZANDO',
                intentos: s.MAX_INTENTOS,
                ultimo_intento: '2026-03-15T10:00:00.000Z',
                error: null,
                created_at: '2026-03-15T09:00:00.000Z'
            });
        })
        .then(function () {
            return sembrarOperacion(entorno, {
                id: 2,
                tipo: 'FOTOGRAFIA',
                entidad_uuid: UUID_FOTO,
                dependencia_uuid: UUID_INSP,
                estado: 'ERROR',
                intentos: s.MAX_INTENTOS,
                ultimo_intento: '2026-03-15T10:00:00.000Z',
                error: 'No fue posible conectarse con el servidor.',
                created_at: '2026-03-15T09:00:01.000Z'
            });
        });
}

function probarAgotadaNoSeReintentaSola() {
    titulo('La cola automática no reintenta una operación agotada');

    const entorno = crearEntorno({ fetch: respuestaServidor('ok') });
    const s = entorno.sync;

    return sembrarColaAgotada(entorno)
        .then(function () {
            /* Disparo automático: sin `revivirAgotadas`. */
            return s.sincronizarTodo();
        })
        .then(function (resumen) {
            igual(0, entorno.llamadas.length, 'Ningún disparador automático envía la operación agotada');

            return entorno.almacen.obtenerTodos('operaciones');
        })
        .then(function (operaciones) {
            const inspeccion = operaciones.filter(function (o) {
                return o.tipo === 'INSPECCION';
            })[0];

            igual('SINCRONIZANDO', inspeccion.estado, 'La operación agotada conserva su estado');
            igual(s.MAX_INTENTOS, inspeccion.intentos, 'El contador de intentos no se reinicia solo');
            ok(!s.puedeReintentar(inspeccion), 'Una operación agotada no es elegible');
            igual(0, s.esperaRestante(inspeccion), 'No hay espera que programar: quedaría congelada');
        });
}

function probarSincronizarReviveAgotadas() {
    titulo('"Sincronizar" revive la operación agotada y completa el ciclo');

    const entorno = crearEntorno({ fetch: respuestaServidor('ok') });
    const s = entorno.sync;

    return sembrarColaAgotada(entorno)
        .then(function () {
            return s.sincronizarTodo({ obraId: 1, motivo: 'manual', revivirAgotadas: true });
        })
        .then(function (resumen) {
            igual(2, resumen.revividas, 'Se reviven las dos operaciones agotadas');
            igual(2, entorno.llamadas.length, 'La inspección se envía y después su fotografía');

            return Promise.all([
                entorno.almacen.obtener('inspecciones', UUID_INSP),
                entorno.almacen.obtener('fotografias', UUID_FOTO),
                entorno.almacen.obtenerTodos('operaciones')
            ]);
        })
        .then(function (resultados) {
            /* 1. La inspección se sincroniza. */
            igual('SINCRONIZADA', resultados[0].estado_local, 'La inspección queda sincronizada');
            igual(42, resultados[0].servidor_id, 'Se guarda el id del servidor');

            /* 2. La fotografía dependiente puede continuar. */
            igual('SINCRONIZADA', resultados[1].estado_local, 'La fotografía dependiente se sincroniza');
            igual(99, resultados[1].servidor_id, 'La fotografía guarda su id de servidor');
            igual(null, resultados[1].blob, 'El blob se libera tras la confirmación');

            /* 3. Ambas operaciones quedan confirmadas. El contador se
               reinició a cero al revivir y el ciclo consumió un intento:
               de haber seguido agotado, habrían carries 6. */
            igual(2, resultados[2].length, 'No se crean operaciones nuevas');

            resultados[2].forEach(function (operacion) {
                igual('SINCRONIZADA', operacion.estado, 'La operación queda confirmada: ' + operacion.tipo);
                igual(1, operacion.intentos, 'El contador se reinició y el ciclo consumió un intento: ' + operacion.tipo);
            });
        });
}

function probarRevivirNoAfectaOtrasObras() {
    titulo('Revivir no reinicia la cola de otra obra');

    const entorno = crearEntorno({ fetch: respuestaServidor('ok') });
    const s = entorno.sync;

    const UUID_AJENA = '44444444-4444-4444-8444-444444444444';

    const inspeccionAjena = crearInspeccion({
        uuid: UUID_AJENA,
        obra_id: 99
    });

    return sembrar(entorno, crearInspeccion(), [])
        .then(function () {
            return entorno.almacen.guardar('inspecciones', inspeccionAjena);
        })
        .then(function () {
            return sembrarOperacion(entorno, {
                id: 1,
                tipo: 'INSPECCION',
                entidad_uuid: UUID_INSP,
                dependencia_uuid: null,
                estado: 'ERROR',
                intentos: s.MAX_INTENTOS,
                ultimo_intento: '2026-03-15T10:00:00.000Z',
                error: 'agotada',
                created_at: '2026-03-15T09:00:00.000Z'
            });
        })
        .then(function () {
            return sembrarOperacion(entorno, {
                id: 2,
                tipo: 'INSPECCION',
                entidad_uuid: UUID_AJENA,
                dependencia_uuid: null,
                estado: 'ERROR',
                intentos: s.MAX_INTENTOS,
                ultimo_intento: '2026-03-15T10:00:00.000Z',
                error: 'agotada',
                created_at: '2026-03-15T09:00:01.000Z'
            });
        })
        .then(function () {
            return s.sincronizarTodo({ obraId: 1, revivirAgotadas: true });
        })
        .then(function (resumen) {
            igual(1, resumen.revividas, 'Solo se revive la operación de la obra abierta');

            return entorno.almacen.obtenerTodos('operaciones');
        })
        .then(function (operaciones) {
            const ajena = operaciones.filter(function (o) {
                return o.entidad_uuid === UUID_AJENA;
            })[0];

            igual('ERROR', ajena.estado, 'La cola de otra obra no se toca');
            igual(s.MAX_INTENTOS, ajena.intentos, 'Sus intentos siguen consumidos');
        });
}

function probarIdempotenciaTrasRevivir() {
    titulo('Una segunda sincronización no duplica');

    const entorno = crearEntorno({
        fetch: function (url) {
            /* El servidor ya tiene ambas entidades: responde
               ALREADY_SYNCED, que debe tratarse como éxito lógico. */
            if (url === ENDPOINT_INSPECCIONES) {
                return this.respuesta(200, {
                    ok: true,
                    results: [{ uuid: UUID_INSP, estado: 'ALREADY_SYNCED', id: 42 }]
                });
            }

            return this.respuesta(200, {
                ok: true,
                results: [{
                    uuid: UUID_FOTO,
                    estado: 'ALREADY_SYNCED',
                    id: 99,
                    ruta_relativa: 'OBR-000001/2026-03-15/' + UUID_INSP + '/IMAGENES/ya.jpg',
                    ruta_thumbnail: 'OBR-000001/2026-03-15/' + UUID_INSP + '/THUMBNAILS/ya.jpg'
                }]
            });
        }
    });
    const s = entorno.sync;

    return sembrarColaAgotada(entorno)
        .then(function () {
            return s.sincronizarTodo({ obraId: 1, revivirAgotadas: true });
        })
        .then(function (resumen) {
            igual(1, resumen.inspecciones.yaSincronizadas, 'La inspección ya estaba sincronizada: es un éxito');
            igual(1, resumen.fotografias.sincronizadas, 'La fotografía se confirma igualmente');
            igual(2, entorno.llamadas.length, 'Solo se enviaron las dos peticiones del ciclo');

            return s.sincronizarTodo({ obraId: 1, revivirAgotadas: true });
        })
        .then(function (resumen) {
            igual(2, entorno.llamadas.length, 'La segunda pulsación no vuelve a enviar nada');
            igual(0, resumen.revividas, 'No hay nada que revivir');
            ok(resumen.vacio, 'La cola queda vacía');
            igual(0, resumen.pendientes.total, 'No quedan elementos pendientes');

            return entorno.almacen.obtenerTodos('operaciones');
        })
        .then(function (operaciones) {
            igual(2, operaciones.length, 'No se crean operaciones nuevas en la segunda vuelta');
        });
}

function probarReintentoManualVisible() {
    titulo('El botón de reintento aparece también con los intentos agotados');

    const s = crearEntorno({}).sync;

    ok(
        s.requiereReintentoManual({ estado: 'ERROR', intentos: 2 }),
        'Un error permanente requiere reintento manual'
    );

    ok(
        s.requiereReintentoManual({ estado: 'SINCRONIZANDO', intentos: s.MAX_INTENTOS }),
        'Una operación agotada a mitad de intento también: antes no tenía salida'
    );

    ok(
        s.requiereReintentoManual({ estado: 'PENDIENTE', intentos: s.MAX_INTENTOS }),
        'Una operación agotada en PENDIENTE también'
    );

    ok(
        !s.requiereReintentoManual({ estado: 'PENDIENTE', intentos: 1 }),
        'Una operación sana no ofrece reintento: lacola automática la atiende'
    );

    ok(
        !s.requiereReintentoManual({ estado: 'SINCRONIZADA', intentos: s.MAX_INTENTOS }),
        'Una operación ya confirmada no ofrece reintento'
    );
}

function probarDiagnosticoNoEscribe() {
    titulo('El diagnóstico informa el estado sin modificarlo');

    const entorno = crearEntorno({});
    const s = entorno.sync;

    function instantanea() {
        return Promise.all([
            entorno.almacen.obtenerTodos('inspecciones'),
            entorno.almacen.obtenerTodos('fotografias'),
            entorno.almacen.obtenerTodos('operaciones')
        ]).then(function (datos) {
            return JSON.stringify(datos);
        });
    }

    return sembrarColaAgotada(entorno)
        .then(instantanea)
        .then(function (antes) {
            return s.diagnostico().then(function (informe) {
                return instantanea().then(function (despues) {
                    igual(antes, despues, 'El diagnóstico no escribe en la base local');
                    igual(2, informe.operaciones.length, 'Informa de las dos operaciones');
                    igual(0, informe.operaciones_huerfanas, 'Ambas operaciones tienen entidad local');
                    igual(1, informe.inspecciones.length, 'Informa de la inspección local');
                    igual(1, informe.fotografias.length, 'Informa de la fotografía local');
                    igual(
                        1,
                        informe.resumen.inspecciones.pendientes,
                        'La operación agotada en SINCRONIZANDO se contaba como pendiente: el resumen la hacía pasar por sana'
                    );
                    igual(1, informe.resumen.fotografias.errores, 'La agotada en ERROR sí se contaba como error');
                    igual(s.MAX_INTENTOS, informe.maxIntentos, 'Informa del límite de intentos');

                    const agotadas = informe.operaciones.filter(function (operacion) {
                        return operacion.agotada;
                    });

                    igual(2, agotadas.length, 'Detecta que las dos operaciones están agotadas');

                    agotadas.forEach(function (operacion) {
                        ok(!operacion.elegible, 'Una operación agotada no es elegible: ' + operacion.tipo);
                        ok(operacion.requiere_reintento_manual, 'Requiere reintento manual: ' + operacion.tipo);
                        igual(1, operacion.obra_id, 'Informa la obra de la operación: ' + operacion.tipo);
                    });

                    igual('PENDIENTE_SYNC', informe.inspecciones[0].estado_local, 'La inspección sigue pendiente localmente');
                    igual(null, informe.inspecciones[0].servidor_id, 'Todavía no tiene id de servidor');
                    igual(true, informe.fotografias[0].tiene_blob, 'La fotografía conserva su blob local');
                });
            });
        });
}

/* ------------------------------------------------------------------ */
/* Ejecución                                                           */
/* ------------------------------------------------------------------ */

const pasos = [
    probarBackoff,
    probarElegibilidad,
    probarEncolado,
    probarAsegurarCola,
    probarOrdenObligatorio,
    probarFotografiaBloqueada,
    probarLiberacionDeBlobs,
    probarBlobsSeConservanAnteFallo,
    probarSesionExpirada,
    probarColaNoSeCongelaSinSesion,
    probarTokenCsrfInvalido,
    probarTokenCsrfDesdeMeta,
    probarRechazoPermanente,
    probarAgotamientoDeIntentos,
    probarReintentoManual,
    probarRespuestaAjenaNoSeAplica,
    probarSinConexion,
    probarSoloInspecciones,
    probarFiltroPorObra,
    probarCodigosPermanentes,
    probarAgotadaNoSeReintentaSola,
    probarSincronizarReviveAgotadas,
    probarRevivirNoAfectaOtrasObras,
    probarIdempotenciaTrasRevivir,
    probarReintentoManualVisible,
    probarDiagnosticoNoEscribe
];

pasos.reduce(function (cadena, paso) {
    return cadena.then(function () {
        return paso();
    });
}, Promise.resolve()).then(function () {
    console.log('');
    console.log(pruebas + ' aserciones, ' + fallos + ' fallos.');

    process.exit(fallos === 0 ? 0 : 1);
}).catch(function (error) {
    console.error('Error inesperado:', error);
    process.exit(1);
});
