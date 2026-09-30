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
const RUTA_CSRF = path.join(__dirname, '..', '..', 'public', 'assets', 'js', 'components', 'csrf.js');

/** Nombre de la cabecera con la que el servidor publica el token vigente. */
const CABECERA_CSRF = 'X-CSRF-TOKEN';

function crearEntorno(opciones) {
    const config = opciones || {};
    const almacen = crearAlmacenamiento();
    const llamadas = [];

    /* Estado de la cookie CSRF del navegador. Es mutable y visible desde las
       pruebas porque el servidor simulado necesita rotarla: con
       `Security::$regenerate = true`, cada petición aceptada reescribe la
       cookie y deja el `<meta>` de la página con el valor anterior. */
    const cookie = {
        valor: config.cookieCsrf === undefined ? 'token-de-prueba' : config.cookieCsrf,
        contador: 0
    };

    /* El `<meta name="X-CSRF-TOKEN">` que emite el layout: una foto del token
       en el momento de cargar la página que el navegador nunca actualiza. */
    const meta = { valor: config.csrfMeta || '' };

    /* Token que el servidor toma como vigente en `Security::verify()`. En
       producción es el mismo valor que lleva la cookie, pero se modela
       aparte para poder simular una cookie que el navegador no puede leer
       (`httponly = true`) o que no llega a escribirse. */
    const servidorCsrf = {
        valor: config.tokenServidor === undefined ? cookie.valor : config.tokenServidor,
        contador: 0
    };

    function respuesta(estado, cuerpo, cabeceras) {
        const valores = cabeceras || {};

        return Promise.resolve({
            ok: estado >= 200 && estado < 300,
            status: estado,
            /* `Headers.get()` no distingue mayúsculas, igual que el navegador. */
            headers: {
                get: function (nombre) {
                    const objetivo = String(nombre).toLowerCase();
                    const claves = Object.keys(valores);

                    for (let i = 0; i < claves.length; i++) {
                        if (claves[i].toLowerCase() === objetivo) {
                            return valores[claves[i]] || null;
                        }
                    }

                    return null;
                }
            },
            json: function () {
                return Promise.resolve(cuerpo);
            }
        });
    }

    const api = {
        respuesta: respuesta,
        llamadas: llamadas,
        cookie: cookie,
        servidorCsrf: servidorCsrf
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
        /* El navegador mantiene la cookie al día con cada `Set-Cookie`, pero
           no toca el `<meta>` de la página ya renderizada. */
        get cookie() {
            return 'csrf_cookie_name=' + cookie.valor;
        },
        visibilityState: 'visible',
        addEventListener: function () {},
        getElementById: function () {
            return null;
        },
        querySelector: function (selector) {
            if (selector === 'meta[name="' + CABECERA_CSRF + '"]' && meta.valor) {
                return {
                    getAttribute: function (name) {
                        return name === 'content' ? meta.valor : null;
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
    /* `csrf.js` va antes que `sincronizacion.js`, igual que en el layout. */
    vm.runInContext(fs.readFileSync(RUTA_CSRF, 'utf8'), contexto, { filename: 'csrf.js' });
    vm.runInContext(fs.readFileSync(RUTA_COMPONENTE, 'utf8'), contexto, { filename: 'sincronizacion.js' });

    return {
        sync: contexto.SIGOA.sincronizacion,
        csrf: contexto.SIGOA.csrf,
        almacen: almacen,
        llamadas: llamadas,
        respuesta: respuesta,
        cookie: cookie,
        meta: meta,
        servidorCsrf: servidorCsrf,
        contexto: contexto,

        /**
         * Simula una recarga de la página: el layout vuelve a emitir el token
         * (`csrf_meta()`) y la cookie se renueva con él. No toca la cola local,
         * que sobrevive a la recarga, ni el resto del estado del dispositivo.
         *
         * @param {string} token Token emitido por la página recargada.
         */
        recargar: function (token) {
            meta.valor = token;
            cookie.valor = token;
            servidorCsrf.valor = token;
            servidorCsrf.contador = 0;
        }
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
/* Servidor simulado con rotación de CSRF                               */
/* ------------------------------------------------------------------ */

/**
 * Reproduce el servidor real: `Config\Security::$csrfProtection = 'cookie'`
 * y `$regenerate = true`.
 *
 * * El token vigente vive en la cookie, contra la que `Security::verify()`
 *   compara en cada POST.
 * * Una petición con el token vigente se acepta y **rota** el token: la
 *   cookie se reescribe y la respuesta trae `X-CSRF-TOKEN` con el nuevo
 *   valor, que es lo que publica `App\Filters\CsrfApi::after()`.
 * * Una petición con cualquier otro token recibe el **403 JSON
 *   `CSRF_INVALID`** de `CsrfApi` — y, como en CodeIgniter, ese camino
 *   devuelve la respuesta desde `before()` y los filtros `after` no llegan a
 *   ejecutarse, así que no llega token nuevo.
 *
 * @param {object}   [opciones]
 * @param {boolean}  [opciones.rotarCookie]   Rota la cookie (por defecto sí).
 * @param {boolean}  [opciones.publicarToken] Publica `X-CSRF-TOKEN` en la
 *        respuesta. Desactivarlo simula un navegador que no puede leer la
 *        cookie (`httponly = true`) o que la bloquea.
 * @param {function} [opciones.cuerpo]        Cuerpo de la respuesta correcta.
 */
function servidorQueRotaElTokenCsrf(opciones) {
    const config = opciones || {};
    const rotarCookie = config.rotarCookie !== false;
    const publicarToken = config.publicarToken !== false;

    return function (url, init) {
        const registro = this.llamadas[this.llamadas.length - 1];

        registro.tokenEnviado = registro.init.headers[CABECERA_CSRF];
        registro.tokenVigente = this.servidorCsrf.valor;

        if (registro.tokenEnviado !== registro.tokenVigente) {
            registro.motivo = 'CSRF_INVALID';

            return this.respuesta(403, { ok: false, error: 'CSRF_INVALID' });
        }

        /* `Security::verify()` regenera el token y `saveHashInCookie()`
           responde con un `Set-Cookie`. */
        this.servidorCsrf.contador++;
        this.servidorCsrf.valor = 'token-rotado-' + this.servidorCsrf.contador;

        if (rotarCookie) {
            this.cookie.valor = this.servidorCsrf.valor;
        }

        registro.tokenRecibido = publicarToken ? this.servidorCsrf.valor : null;

        const cuerpo = typeof config.cuerpo === 'function'
            ? config.cuerpo(url, init)
            : cuerpoExitoso(url);

        const cabeceras = {};

        if (publicarToken) {
            cabeceras[CABECERA_CSRF] = this.servidorCsrf.valor;
        }

        return this.respuesta(200, cuerpo, cabeceras);
    };
}

/** Respuesta correcta del endpoint que corresponda a `url`. */
function cuerpoExitoso(url) {
    if (url === ENDPOINT_INSPECCIONES) {
        return { ok: true, results: [{ uuid: UUID_INSP, estado: 'SYNCED', id: 42 }] };
    }

    return {
        ok: true,
        results: [{
            uuid: UUID_FOTO,
            estado: 'SYNCED',
            id: 99,
            nombre_archivo: 'INS-00042-20260315-103000-a1b2c3.jpg',
            ruta_relativa: 'OBR-000001/2026-03-15/' + UUID_INSP + '/IMAGENES/INS-00042-20260315-103000-a1b2c3.jpg',
            ruta_thumbnail: 'OBR-000001/2026-03-15/' + UUID_INSP + '/THUMBNAILS/THB-00042-20260315-103000-d4e5f6.jpg'
        }]
    };
}

/**
 * Confirma la fotografía que se acaba de enviar, leyendo su `uuid` del
 * `FormData`. Varias fotografías encoladas comparten el mismo servidor, así
 * que un cuerpo fijo haría que el cliente rechazara las que no coinciden
 * con el resultado recibido.
 */
function cuerpoExitosoDeLaFotoEnviada(url, init) {
    if (url === ENDPOINT_INSPECCIONES) {
        return cuerpoExitoso(url);
    }

    const campos = (init && init.body && init.body.campos) || [];
    let uuid = UUID_FOTO;

    for (let i = 0; i < campos.length; i++) {
        if (campos[i][0] === 'uuid') {
            uuid = campos[i][1];
        }
    }

    return {
        ok: true,
        results: [{
            uuid: uuid,
            estado: 'SYNCED',
            id: 99,
            nombre_archivo: 'foto.jpg',
            ruta_relativa: 'OBR-000001/2026-03-15/' + UUID_INSP + '/IMAGENES/foto.jpg',
            ruta_thumbnail: 'OBR-000001/2026-03-15/' + UUID_INSP + '/THUMBNAILS/foto-thb.jpg'
        }]
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

/**
 * El `<meta>` es la foto del token al cargar la página, no el origen
 * principal: solo se usa cuando no hay cookie legible. Preferirlo era
 * precisamente el defecto (§61).
 */
function probarTokenCsrfSoloDesdeMeta() {
    titulo('El token CSRF cae al meta cuando no hay cookie legible');

    const entorno = crearEntorno({
        csrfMeta: 'token-del-meta',
        /* `httponly = true`: el navegador no puede leer la cookie, así que el
           `<meta>` del layout es el único origen disponible. */
        cookieCsrf: '',
        fetch: function (url) {
            return this.respuesta(200, cuerpoExitoso(url), { 'X-CSRF-TOKEN': 'token-renovado' });
        }
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
                entorno.llamadas[0].init.headers[CABECERA_CSRF],
                'La cabecera toma el token emitido por el layout cuando no hay cookie'
            );
        });
}

/**
 * Caso 1 — Cola de varias fotografías con el servidor rotando el token en
 * cada respuesta, como en producción. Regresión directa del defecto
 * reportado en el dispositivo: subía una y las demás quedaban pendientes
 * hasta recargar la página.
 */
function probarVariasFotografiasConRotacionDeToken() {
    titulo('Varias fotografías se drenan con el token rotando en cada respuesta');

    const uuids = ['foto-1', 'foto-2', 'foto-3'];
    const entorno = crearEntorno({
        /* El `<meta>` conserva el token de la carga de la página: es la
           condición que hacía fallar al cliente anterior. */
        csrfMeta: 'token-de-la-pagina',
        fetch: servidorQueRotaElTokenCsrf({ cuerpo: cuerpoExitosoDeLaFotoEnviada })
    });
    const s = entorno.sync;
    const inspeccion = crearInspeccion({ estado_local: 'SINCRONIZADA', servidor_id: 42 });

    return sembrar(entorno, inspeccion, uuids.map(function (uuid) {
        return crearFotografia({ uuid: uuid });
    }))
        .then(function () {
            return conTiempoLimite(
                s.sincronizarTodo(),
                3000,
                'el drenaje con varias fotografías y rotación de token no terminó'
            );
        })
        .then(function (resumen) {
            igual(3, entorno.llamadas.length, 'Las tres fotografías salieron sin recargar');
            igual(3, resumen.fotografias.sincronizadas, 'Las tres se confirman');
            igual(0, resumen.fotografias.errores, 'Ninguna quedó en error');
            ok(!resumen.csrfInvalido, 'No hubo ningún 403 de CSRF');

            return Promise.all([
                entorno.almacen.obtenerTodos('operaciones'),
                s.diagnostico()
            ]);
        })
        .then(function (resultados) {
            const cola = resultados[0];
            const informe = resultados[1];

            igual(0, informe.resumen.fotografias.pendientes, 'La cola de fotografías queda vacía');
            igual(0, informe.resumen.total, 'No queda nada pendiente en absoluto');

            /* El orden obligatorio: la cola se consume en el orden en que se
               capturó y cada blobs se libera solo tras su confirmación. */
            const sincronizadas = cola.filter(function (operacion) {
                return operacion.estado === 'SINCRONIZADA';
            });

            igual(3, sincronizadas.length, 'Las tres operaciones quedan sincronizadas');
            igual(
                uuids.join(','),
                sincronizadas.map(function (operacion) {
                    return operacion.entidad_uuid;
                }).join(','),
                'Se conservan la trazabilidad y el orden de la cola'
            );
        });
}

/**
 * Caso 2 — Regeneración real: cada petición debe llevar el token que el
 * servidor emitió en la respuesta anterior, nunca el del `<meta>`.
 */
function probarCadaPeticionUsaElTokenRenovado() {
    titulo('Cada petición usa el token que devolvió la respuesta anterior');

    const uuids = ['foto-1', 'foto-2', 'foto-3'];
    const entorno = crearEntorno({
        csrfMeta: 'token-de-la-pagina',
        fetch: servidorQueRotaElTokenCsrf({ cuerpo: cuerpoExitosoDeLaFotoEnviada })
    });
    const s = entorno.sync;
    const inspeccion = crearInspeccion({ estado_local: 'SINCRONIZADA', servidor_id: 42 });

    return sembrar(entorno, inspeccion, uuids.map(function (uuid) {
        return crearFotografia({ uuid: uuid });
    }))
        .then(function () {
            return s.sincronizarTodo();
        })
        .then(function () {
            igual(3, entorno.llamadas.length, 'Se hicieron tres peticiones');

            entorno.llamadas.forEach(function (llamada, indice) {
                igual(
                    llamada.tokenVigente,
                    llamada.tokenEnviado,
                    'La petición ' + (indice + 1) + ' salió con el token vigente del servidor'
                );

                if (indice > 0) {
                    igual(
                        entorno.llamadas[indice - 1].tokenRecibido,
                        llamada.tokenEnviado,
                        'La petición ' + (indice + 1) + ' reutiliza el token recibido en la anterior'
                    );
                }

                ok(
                    llamada.tokenEnviado !== 'token-de-la-pagina',
                    'La petición ' + (indice + 1) + ' no usa el token congelado del meta'
                );
            });
        });
}

/**
 * El canal de renovación no depende de que el navegador pueda leer la
 * cookie: con `httponly = true` solo existe la cabecera que publica
 * `CsrfApi::after()`.
 */
function probarTokenSoloPorCabecera() {
    titulo('El token se renueva por cabecera cuando la cookie no es legible');

    const uuids = ['foto-1', 'foto-2'];
    const entorno = crearEntorno({
        csrfMeta: 'token-de-la-pagina',
        /* La cookie existe pero el navegador no la expone: `document.cookie`
           no la devuelve, así que el `<meta>` y la cabecera son los únicos
           canales. El `Set-Cookie` tampoco se refleja. */
        cookieCsrf: '',
        tokenServidor: 'token-de-la-pagina',
        fetch: servidorQueRotaElTokenCsrf({
            cuerpo: cuerpoExitosoDeLaFotoEnviada,
            rotarCookie: false
        })
    });
    const s = entorno.sync;
    const inspeccion = crearInspeccion({ estado_local: 'SINCRONIZADA', servidor_id: 42 });

    return sembrar(entorno, inspeccion, uuids.map(function (uuid) {
        return crearFotografia({ uuid: uuid });
    }))
        .then(function () {
            return s.sincronizarTodo();
        })
        .then(function () {
            igual(2, entorno.llamadas.length, 'Las dos fotografías salieron');
            ok(entorno.llamadas[0].motivo === undefined, 'La primera no fue rechazada');
            ok(entorno.llamadas[1].motivo === undefined, 'La segunda tampoco');
            igual(
                entorno.llamadas[0].tokenRecibido,
                entorno.llamadas[1].tokenEnviado,
                'La segunda salió con el token de la cabecera de la primera respuesta'
            );

            return entorno.csrf.token();
        })
        .then(function (token) {
            igual(entorno.llamadas[1].tokenRecibido, token, 'El token vigente es el de la última respuesta');
            ok(token !== 'token-de-la-pagina', 'No se quedó en el token congelado del meta');
            ok(token !== '', 'La cookie no legible no impide obtener un token');
        });
}

/**
 * Caso 3 — CSRF realmente inválido: un servidor que rechaza siempre. La
 * operación debe pausarse, no gastar los seis intentos, quedar disponible
 * y no generar un bucle.
 */
function probarTokenIrrenovablePausaSinBucle() {
    titulo('Un token que no puede renovarse pausa la cola sin gastar intentos');

    const entorno = crearEntorno({
        csrfMeta: 'token-de-la-pagina',
        /* El servidor nunca acepta el token que envía el cliente. */
        fetch: function (url, init) {
            const registro = this.llamadas[this.llamadas.length - 1];

            registro.tokenEnviado = init.headers[CABECERA_CSRF];
            registro.tokenVigente = 'token-que-el-servidor-nunca-emitio';
            registro.motivo = 'CSRF_INVALID';

            return this.respuesta(403, { ok: false, error: 'CSRF_INVALID' });
        }
    });
    const s = entorno.sync;
    const inspeccion = crearInspeccion({ estado_local: 'SINCRONIZADA', servidor_id: 42 });

    return sembrar(entorno, inspeccion, [crearFotografia({ uuid: 'foto-1' })])
        .then(function () {
            return conTiempoLimite(
                s.sincronizarTodo(),
                2000,
                'el ciclo con un 403 CSRF permanente no terminó (bucle)'
            );
        })
        .then(function (resumen) {
            ok(resumen.csrfInvalido === true, 'Se informa que el token de seguridad venció');
            igual(1, entorno.llamadas.length, 'Una sola petición: no se insiste en el mismo ciclo');

            return s.obtenerOperaciones();
        })
        .then(function (operaciones) {
            const foto = operaciones[0];

            igual('PENDIENTE', foto.estado, 'La operación vuelve a PENDIENTE');
            igual(0, foto.intentos, 'No consumió ninguno de los 6 intentos');
            ok(s.puedeReintentar(foto), 'Queda disponible para el siguiente disparador');
            ok(!s.requiereReintentoManual(foto), 'No necesita la acción manual de reintento');

            /* La pausa no congela la cola: otro disparador puede retomarla. */
            return conTiempoLimite(s.sincronizarTodo(), 2000, 'el segundo ciclo tampoco terminó')
                .then(function (segundo) {
                    igual(2, entorno.llamadas.length, 'El siguiente disparador vuelve a intentarlo una vez');
                    ok(segundo.csrfInvalido === true, 'Sigue informando la pausa, sin Consumir intentos');
                });
        })
        .then(function () {
            return s.obtenerOperaciones();
        })
        .then(function (operaciones) {
            igual(0, operaciones[0].intentos, 'Dos pausas no consumieron ningún intento');
        });
}

/**
 * Caso 4 — La recarga sigue siendo un mecanismo de recuperación válido: una
 * carga nueva de la página trae un token nuevo y la cola termina.
 */
function probarRecargaRecuperaLaCola() {
    titulo('La recarga recupera la cola cuando el token no puede renovarse');

    const entorno = crearEntorno({
        /* Cookie vencida (§57.1.1): el navegador no tiene ninguna legible y el
           `<meta>` guarda un token viejo. El servidor, al no encontrar
           cookie válida, generó una nueva que el cliente no puede conocer. */
        csrfMeta: 'token-vencido',
        cookieCsrf: '',
        tokenServidor: 'token-nuevo-que-el-cliente-desconoce',
        fetch: servidorQueRotaElTokenCsrf({ cuerpo: cuerpoExitosoDeLaFotoEnviada })
    });
    const s = entorno.sync;
    const inspeccion = crearInspeccion({ estado_local: 'SINCRONIZADA', servidor_id: 42 });

    return sembrar(entorno, inspeccion, [crearFotografia({ uuid: 'foto-1' })])
        .then(function () {
            return s.sincronizarTodo();
        })
        .then(function (resumen) {
            ok(resumen.csrfInvalido === true, 'El primer ciclo queda pausado');
            igual('CSRF_INVALID', entorno.llamadas[0].motivo, 'El token viejo fue rechazado');

            /* Recarga: el layout vuelve a emitir el token y la cookie se
               renueva con él. Solo cambia el estado de la página; la cola
               local sobrevive intacta. */
            entorno.recargar('token-nuevo-que-el-cliente-desconoce');

            return s.sincronizarTodo();
        })
        .then(function (resumen) {
            igual(2, entorno.llamadas.length, 'Tras la recarga sale la petición pendiente');
            igual(
                'token-nuevo-que-el-cliente-desconoce',
                entorno.llamadas[1].init.headers[CABECERA_CSRF],
                'La petición posterior usa el token de la página recién cargada'
            );
            igual(1, resumen.fotografias.sincronizadas, 'La fotografía pendiente se confirma');

            return s.diagnostico();
        })
        .then(function (informe) {
            igual(0, informe.resumen.total, 'La cola queda vacía tras la recuperación');
        });
}

/**
 * Caso 5 — La solución no está acoplada a fotografías: el endpoint de
 * inspecciones también renueva el token que consume el resto del ciclo.
 */
function probarInspeccionesRenuevanElToken() {
    titulo('Las inspecciones también renuevan el token de las siguientes peticiones');

    const entorno = crearEntorno({
        csrfMeta: 'token-de-la-pagina',
        fetch: servidorQueRotaElTokenCsrf({ cuerpo: cuerpoExitoso })
    });
    const s = entorno.sync;

    return sembrar(entorno, crearInspeccion(), [crearFotografia()])
        .then(function () {
            return s.sincronizarTodo();
        })
        .then(function (resumen) {
            igual(2, entorno.llamadas.length, 'Una petición de inspecciones y una de fotografía');
            igual(ENDPOINT_INSPECCIONES, entorno.llamadas[0].url, 'Primero las inspecciones');
            igual(ENDPOINT_FOTOGRAFIAS, entorno.llamadas[1].url, 'Después las fotografías');

            igual(
                entorno.llamadas[0].tokenRecibido,
                entorno.llamadas[1].tokenEnviado,
                'La fotografía sale con el token emitido al confirmar la inspección'
            );

            ok(entorno.llamadas[0].tokenEnviado !== entorno.llamadas[1].tokenEnviado, 'El token rotó entre ambas');
            igual(1, resumen.inspecciones.sincronizadas, 'La inspección se sincroniza');
            igual(1, resumen.fotografias.sincronizadas, 'La fotografía también, en el mismo ciclo');
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
/* Drenaje de la cola                                                  */
/* ------------------------------------------------------------------ */

/**
 * Fotografía `n` de una serie. Cada una es una operación independiente: el
 * padre ya está sincronizado, de modo que la única razón para que se queden
 * en la cola es que el ciclo no las recogió.
 */
function fotoDeSerie(n) {
    return crearFotografia({
        uuid: 'foto-' + n,
        blob: { id: 'blob-' + n },
        thumbnail: { id: 'miniatura-' + n }
    });
}

/** Lee el `uuid` que el componente envía en el formulario. */
function uuidEnviado(init) {
    const campos = init && init.body && init.body.campos ? init.body.campos : [];
    let valor = null;

    campos.forEach(function (campo) {
        if (campo[0] === 'uuid') {
            valor = campo[1];
        }
    });

    return valor;
}

/** Respuesta del servidor para la fotografía que se está subiendo. */
function respuestaDeFotografia(api, uuid, id) {
    return api.respuesta(200, {
        ok: true,
        results: [{
            uuid: uuid,
            estado: 'SYNCED',
            id: id,
            nombre_archivo: 'INS-00042-' + uuid + '.jpg',
            ruta_relativa: 'OBR-000001/2026-03-15/' + UUID_INSP + '/IMAGENES/INS-00042-' + uuid + '.jpg',
            ruta_thumbnail: 'OBR-000001/2026-03-15/' + UUID_INSP + '/THUMBNAILS/THB-00042-' + uuid + '.jpg'
        }]
    });
}

/** Respuesta del servidor para la inspección que se está subiendo. */
function respuestaDeInspeccion(api) {
    return api.respuesta(200, { ok: true, results: [{ uuid: UUID_INSP, estado: 'SYNCED', id: 42 }] });
}

/**
 * Adaptador para construir respuestas fuera de un `fetch`: los helpers
 * anteriores esperan el contexto del servidor simulado.
 */
function apiDePrueba(entorno) {
    return {
        respuesta: function (estado, cuerpo) {
            return entorno.respuesta(estado, cuerpo);
        }
    };
}

/**
 * Sustituye los temporizadores del entorno por registradores. Permite
 * comprobar si el ciclo dejó una espera real programada —o ninguna— sin
 * esperar a que venza.
 */
function espiarTemporizadores(entorno) {
    const esperas = [];

    entorno.contexto.setTimeout = function (funcion, ms) {
        esperas.push(ms);

        return -1;
    };

    entorno.contexto.clearTimeout = function () {};

    return esperas;
}

/** Falla la prueba si la promesa no se resuelve pronto (drenaje infinito). */
function conTiempoLimite(promesa, ms, mensaje) {
    return new Promise(function (resolver, rechazar) {
        const reloj = setTimeout(function () {
            rechazar(new Error(mensaje));
        }, ms);

        promesa.then(function (valor) {
            clearTimeout(reloj);

            resolver(valor);
        }, function (error) {
            clearTimeout(reloj);

            rechazar(error);
        });
    });
}

function probarVariasFotografiasEnUnCiclo() {
    titulo('Varias fotografías pendientes se envían todas en un solo ciclo');

    const entorno = crearEntorno({
        fetch: function (url, init) {
            if (url === ENDPOINT_INSPECCIONES) {
                return respuestaDeInspeccion(this);
            }

            return respuestaDeFotografia(this, uuidEnviado(init), 99);
        }
    });
    const s = entorno.sync;

    return sembrar(entorno, crearInspeccion({ estado_local: 'SINCRONIZADA', servidor_id: 42 }), [
        fotoDeSerie(1),
        fotoDeSerie(2),
        fotoDeSerie(3)
    ])
        .then(function () {
            return Promise.all([
                s.encolar('FOTOGRAFIA', 'foto-1', UUID_INSP),
                s.encolar('FOTOGRAFIA', 'foto-2', UUID_INSP),
                s.encolar('FOTOGRAFIA', 'foto-3', UUID_INSP)
            ]);
        })
        .then(function () {
            /* Una sola llamada: el propio ciclo debe vaciar la cola. */
            return conTiempoLimite(s.sincronizarTodo(), 2000, 'el ciclo no terminó');
        })
        .then(function (resumen) {
            igual(3, resumen.fotografias.sincronizadas, 'Las tres fotografías se sincronizaron');
            igual(3, resumen.intentos, 'El ciclo consumió tres intentos');
            igual(0, resumen.fotografias.bloqueadas, 'Ninguna quedó bloqueada');
            igual(0, resumen.pendientes.total, 'La cola quedó vacía');

            const subidas = entorno.llamadas
                .filter(function (llamada) {
                    return llamada.url === ENDPOINT_FOTOGRAFIAS;
                })
                .map(function (llamada) {
                    return uuidEnviado(llamada.init);
                });

            igual('foto-1,foto-2,foto-3', subidas.join(','), 'Se subieron las tres, en orden');

            return s.sincronizarTodo();
        })
        .then(function (resumen) {
            igual(3, entorno.llamadas.length, 'Un ciclo posterior con la cola vacía no repite peticiones');
            ok(resumen.vacio === true, 'El ciclo posterior informa cola vacía');
        });
}

function probarDrenajeTrasCadaExito() {
    titulo('El éxito de una fotografía dispara la siguiente sin recargar');

    /* Reproduce el defecto observado en el dispositivo: la siguiente
       fotografía se captura mientras la anterior se está subiendo, es decir
       después de que el ciclo tomara su foto de la cola. Antes del drenaje
       esa fotografía se quedaba para siempre en `PENDIENTE` hasta que el
       inspector recargaba la página. */
    let pendientesDeCapturar = [2, 3];
    let entorno;

    function capturarSiguiente() {
        const n = pendientesDeCapturar.shift();

        if (n === undefined) {
            return Promise.resolve();
        }

        return entorno.almacen.guardar('fotografias', fotoDeSerie(n)).then(function () {
            return entorno.sync.encolar('FOTOGRAFIA', 'foto-' + n, UUID_INSP);
        });
    }

    const servidor = function (url, init) {
        if (url === ENDPOINT_INSPECCIONES) {
            return respuestaDeInspeccion(this);
        }

        const uuid = uuidEnviado(init);

        /* La captura ocurre una vez el servidor confirmó la anterior. */
        return capturarSiguiente().then(function () {
            return respuestaDeFotografia(apiDePrueba(entorno), uuid, 99);
        });
    };

    entorno = crearEntorno({ fetch: servidor });
    const s = entorno.sync;

    return sembrar(entorno, crearInspeccion({ estado_local: 'SINCRONIZADA', servidor_id: 42 }), [fotoDeSerie(1)])
        .then(function () {
            return s.encolar('FOTOGRAFIA', 'foto-1', UUID_INSP);
        })
        .then(function () {
            return conTiempoLimite(s.sincronizarTodo(), 2000, 'el drenaje no terminó');
        })
        .then(function (resumen) {
            const subidas = entorno.llamadas
                .filter(function (llamada) {
                    return llamada.url === ENDPOINT_FOTOGRAFIAS;
                })
                .map(function (llamada) {
                    return uuidEnviado(llamada.init);
                });

            igual('foto-1,foto-2,foto-3', subidas.join(','), 'La cola se drenó sola, sin nuevo disparador');
            igual(3, resumen.fotografias.sincronizadas, 'Las tres fotografías acabaron sincronizadas');
            igual(0, resumen.pendientes.total, 'No queda ninguna fotografía pendiente');

            return s.diagnostico();
        })
        .then(function (informe) {
            igual(0, informe.resumen.total, 'El diagnóstico confirma la cola vacía');
            igual(3, informe.resumen.fotografias.sincronizadas, 'El diagnóstico cuenta las tres sincronizadas');
            igual(
                3,
                informe.operaciones.length,
                'Las operaciones completadas se conservan como historial, no se borran'
            );
        });
}

function probarDrenajeNoAbreSegundoProcesador() {
    titulo('El drenaje no abre un segundo procesador');

    /* La primera subida queda retenida. Mientras está en vuelo llegan dos
       cosas: otro disparador de sincronización y una fotografía nueva. Ninguna
       puede consumirse en paralelo —sería consumir la misma cola dos veces— y
       ninguna puede perderse. */
    let liberar = null;
    let ciclo = null;
    let enVuelo = 0;
    let maximoEnVuelo = 0;
    let entorno;

    function retenida() {
        return new Promise(function (resolver) {
            liberar = resolver;
        });
    }

    function servidor(url, init) {
        if (url === ENDPOINT_INSPECCIONES) {
            return respuestaDeInspeccion(this);
        }

        const uuid = uuidEnviado(init);

        enVuelo++;
        maximoEnVuelo = Math.max(maximoEnVuelo, enVuelo);

        const responder = function () {
            enVuelo--;

            return respuestaDeFotografia(apiDePrueba(entorno), uuid, 99);
        };

        if (uuid === 'foto-1') {
            return retenida().then(responder);
        }

        return Promise.resolve().then(responder);
    }

    entorno = crearEntorno({ fetch: servidor });
    const s = entorno.sync;

    return sembrar(entorno, crearInspeccion({ estado_local: 'SINCRONIZADA', servidor_id: 42 }), [fotoDeSerie(1)])
        .then(function () {
            return s.encolar('FOTOGRAFIA', 'foto-1', UUID_INSP);
        })
        .then(function () {
            /* El ciclo queda retenido en la subida: no se espera aquí. */
            ciclo = conTiempoLimite(s.sincronizarTodo(), 2000, 'el ciclo retenido no terminó');
        })
        .then(function () {
            return new Promise(function (resolver) {
                setTimeout(resolver, 0);
            });
        })
        .then(function () {
            /* El ciclo sigue en vuelo: llega otro disparador y otra captura. */
            return entorno.almacen.guardar('fotografias', fotoDeSerie(2))
                .then(function () {
                    return s.encolar('FOTOGRAFIA', 'foto-2', UUID_INSP);
                })
                .then(function () {
                    return s.sincronizarTodo();
                });
        })
        .then(function (respuesta) {
            ok(respuesta.enCurso === true, 'El disparador concurrente no abre otro ciclo');
            igual(1, maximoEnVuelo, 'Solo hay una petición en vuelo');

            /* Se libera la primera subida: el ciclo termina y, si el
               funcionara, drenaría lo que se capturó durante el vuelo. */
            ok(liberar !== null, 'el ciclo llegó a subir la fotografía');
            liberar(respuestaDeFotografia(apiDePrueba(entorno), 'foto-1', 99));

            return ciclo;
        })
        .then(function (resumen) {
            igual(2, resumen.fotografias.sincronizadas, 'La fotografía encolada durante el ciclo se envió');
            igual(0, resumen.pendientes.total, 'La cola quedó vacía');
            igual(1, maximoEnVuelo, 'Nunca hubo dos peticiones de fotografía simultáneas');

            return s.diagnostico();
        })
        .then(function (informe) {
            igual(0, informe.resumen.total, 'El diagnóstico confirma la cola vacía');

            const subidas = informe.fotografias.map(function (foto) {
                return foto.uuid;
            });

            igual(2, subidas.length, 'Las dos fotografías constan como sincronizadas');
        });
}

function probarFalloNoBloqueaElDrenaje() {
    titulo('Un fallo transitorio no detiene el drenaje');

    /* La segunda fotografía falla. El fallo debe contar como intento —para no
       repetirla en bucle— pero no puede impedir que la tercera se envíe. */
    const fallos = {};
    let entorno;

    function servidor(url, init) {
        if (url === ENDPOINT_INSPECCIONES) {
            return respuestaDeInspeccion(this);
        }

        const uuid = uuidEnviado(init);

        fallos[uuid] = (fallos[uuid] || 0) + 1;

        if (uuid === 'foto-2' && fallos[uuid] === 1) {
            return this.respuesta(500, { ok: false, error: 'SERVER_ERROR' });
        }

        return respuestaDeFotografia(this, uuid, 99);
    }

    entorno = crearEntorno({ fetch: servidor });
    const s = entorno.sync;
    const esperas = espiarTemporizadores(entorno);

    return sembrar(entorno, crearInspeccion({ estado_local: 'SINCRONIZADA', servidor_id: 42 }), [
        fotoDeSerie(1),
        fotoDeSerie(2),
        fotoDeSerie(3)
    ])
        .then(function () {
            return Promise.all([
                s.encolar('FOTOGRAFIA', 'foto-1', UUID_INSP),
                s.encolar('FOTOGRAFIA', 'foto-2', UUID_INSP),
                s.encolar('FOTOGRAFIA', 'foto-3', UUID_INSP)
            ]);
        })
        .then(function () {
            return conTiempoLimite(s.sincronizarTodo(), 2000, 'el drenaje con fallo no terminó');
        })
        .then(function (resumen) {
            igual(2, resumen.fotografias.sincronizadas, 'Las fotografías sana y posterior se enviaron');
            igual(1, resumen.fotografias.errores, 'El fallo se informa como error');
            igual(1, resumen.pendientes.total, 'Solo queda pendiente la que falló');

            return s.diagnostico();
        })
        .then(function (informe) {
            const fallida = informe.operaciones.filter(function (operacion) {
                return operacion.entidad_uuid === 'foto-2';
            })[0];
            const entidad = informe.fotografias.filter(function (foto) {
                return foto.uuid === 'foto-2';
            })[0];

            igual('PENDIENTE', fallida.estado, 'La operación de la fallida vuelve a la cola');
            igual('PENDIENTE_SYNC', entidad.estado_local, 'La fotografía conserva su estado local');
            igual(1, fallida.intentos, 'El fallo consumió un intento');
            ok(fallida.elegible === false, 'Respeta la espera: no es elegible todavía');
            ok(fallida.requiere_reintento_manual === false, 'No agota los reintentos por insistencia');
            ok(esperas.length >= 1, 'Se programa un reintento diferido por el fallo');
            ok(esperas[esperas.length - 1] > 1000, 'La espera programada es la del backoff, no inmediata');
        });
}

function probarColaVaciaNoProgramaTemporizador() {
    titulo('La cola vacía no programa ningún temporizador');

    const entorno = crearEntorno({});
    const s = entorno.sync;
    const esperas = espiarTemporizadores(entorno);

    return s.sincronizarTodo()
        .then(function (resumen) {
            ok(resumen.vacio === true, 'Informa cola vacía');
            igual(0, resumen.pendientes.total, 'No hay nada pendiente');
            igual(0, resumen.pendientesElegibles, 'No hay nada elegible');
            igual(0, resumen.intentos, 'No se consumió ningún intento');
            igual(0, entorno.llamadas.length, 'No se realiza ninguna petición');
            igual(0, esperas.length, 'No queda ningún temporizador pendiente');
        });
}

function probarHijoSaleAlConfirmarElPadre() {
    titulo('La fotografía sale en la vuelta que confirma al padre');

    /* Situación real del dispositivo: la fotografía se capturó antes de que el
       servidor confirmara la inspección. La primera vuelta sincroniza la
       inspección; la fotografía, que en esa vuelta estaba bloqueada, se envía
       en la vuelta siguiente sin esperar a ningún disparador. */
    const entorno = crearEntorno({
        fetch: function (url, init) {
            if (url === ENDPOINT_INSPECCIONES) {
                return respuestaDeInspeccion(this);
            }

            return respuestaDeFotografia(this, uuidEnviado(init), 99);
        }
    });
    const s = entorno.sync;

    return sembrar(entorno, crearInspeccion(), [crearFotografia()])
        .then(function () {
            return s.encolar('FOTOGRAFIA', UUID_FOTO, UUID_INSP);
        })
        .then(function () {
            return conTiempoLimite(s.sincronizarTodo(), 2000, 'el ciclo no terminó');
        })
        .then(function (resumen) {
            igual(1, resumen.inspecciones.sincronizadas, 'La inspección se sincronizó');
            igual(1, resumen.fotografias.sincronizadas, 'La fotografía salió tras confirmar el padre');
            igual(2, resumen.intentos, 'Dos intentos: uno por operación');
            igual(0, resumen.pendientes.total, 'La cola quedó vacía');

            const orden = entorno.llamadas.map(function (llamada) {
                return llamada.url === ENDPOINT_INSPECCIONES ? 'inspeccion' : 'fotografia';
            });

            igual('inspeccion,fotografia', orden.join(','), 'El orden obligatorio se respetó');
        });
}

function probarColaBloqueadaNoGira() {
    titulo('Una fotografía bloqueada no hace girar el drenaje');

    /* La inspección padre falla y queda esperando su propio reintento. La
       fotografía permanece bloqueada y debe esperar a la siguiente vuelta
       real: el drenaje no puede quedarse girando sobre ella. */
    let entorno;

    const servidor = function (url) {
        if (url === ENDPOINT_INSPECCIONES) {
            return this.respuesta(500, { ok: false, error: 'SERVER_ERROR' });
        }

        return respuestaDeFotografia(apiDePrueba(entorno), UUID_FOTO, 99);
    };

    entorno = crearEntorno({ fetch: servidor });
    const s = entorno.sync;
    const esperas = espiarTemporizadores(entorno);

    return sembrar(entorno, crearInspeccion(), [crearFotografia()])
        .then(function () {
            return s.encolar('FOTOGRAFIA', UUID_FOTO, UUID_INSP);
        })
        .then(function () {
            return conTiempoLimite(s.sincronizarTodo(), 2000, 'el ciclo con cola bloqueada no terminó');
        })
        .then(function (resumen) {
            igual(1, resumen.inspecciones.errores, 'El fallo es del padre');
            igual(1, resumen.fotografias.bloqueadas, 'La fotografía se informa bloqueada');
            igual(0, resumen.fotografias.sincronizadas, 'La fotografía no se subió');
            igual(0, resumen.intentos - 1, 'Solo consumió el intento del padre');

            return s.diagnostico();
        })
        .then(function (informe) {
            const foto = informe.operaciones.filter(function (operacion) {
                return operacion.entidad_uuid === UUID_FOTO;
            })[0];

            igual(0, foto.intentos, 'La fotografía no consumió ningún intento');
            igual(1, informe.resumen.fotografias.pendientes, 'Sigue pendiente, no reintentada');
            igual(0, informe.resumen.fotografias.sincronizadas, 'No se subió nada');
            ok(esperas.length >= 1, 'El reintento del padre queda programado');
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
    probarTokenCsrfSoloDesdeMeta,
    probarVariasFotografiasConRotacionDeToken,
    probarCadaPeticionUsaElTokenRenovado,
    probarTokenSoloPorCabecera,
    probarTokenIrrenovablePausaSinBucle,
    probarRecargaRecuperaLaCola,
    probarInspeccionesRenuevanElToken,
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
    probarDiagnosticoNoEscribe,
    probarVariasFotografiasEnUnCiclo,
    probarDrenajeTrasCadaExito,
    probarDrenajeNoAbreSegundoProcesador,
    probarFalloNoBloqueaElDrenaje,
    probarColaVaciaNoProgramaTemporizador,
    probarHijoSaleAlConfirmarElPadre,
    probarColaBloqueadaNoGira
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
