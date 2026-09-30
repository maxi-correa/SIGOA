/* ===================================================================
   Pruebas del componente de token CSRF (Fase D.6.3 / docs/SIGOA.md §61)
   ===================================================================
   Ejecuta `public/assets/js/components/csrf.js` en un entorno Node
   simulado (sin navegador) y verifica la resolución del token vigente y su
   renovación tras cada respuesta:

     * el orden de resolución cookie → cabecera de respuesta → `<meta>`;
     * el token recibido en una respuesta es el que usa la siguiente;
     * una respuesta sin cabecera descarta el valor memorizado;
     * el token no se persiste en ningún almacenamiento.

   Uso:  node tests/js/csrf.test.js
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
/* Entorno simulado                                                    */
/* ------------------------------------------------------------------ */

const RUTA_COMPONENTE = path.join(__dirname, '..', '..', 'public', 'assets', 'js', 'components', 'csrf.js');

/**
 * Carga el componente sobre un `document` simulado.
 *
 * `estado` es mutable desde la prueba: representa lo que el navegador puede
 * cambiar por su cuenta (la cookie, con cada `Set-Cookie`) y lo que solo
 * cambia al cargar la página (el `<meta>` del layout).
 *
 * @param {object} opciones
 * @param {string} [opciones.cookie] Valor crudo de `document.cookie`.
 * @param {string} [opciones.meta]   Token del `<meta name="X-CSRF-TOKEN">`.
 */
function crearContexto(opciones) {
    const config = opciones || {};

    const estado = {
        cookie: config.cookie === undefined ? 'csrf_cookie_name=token-A' : config.cookie,
        meta: config.meta === undefined ? 'token-A' : config.meta
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
        document: {
            get cookie() {
                return estado.cookie;
            },
            querySelector: function (selector) {
                if (selector === 'meta[name="X-CSRF-TOKEN"]' && estado.meta) {
                    return {
                        getAttribute: function (atributo) {
                            return atributo === 'content' ? estado.meta : null;
                        }
                    };
                }

                return null;
            }
        }
    };

    contexto.window = contexto;
    contexto.globalThis = contexto;

    vm.createContext(contexto);
    vm.runInContext(fs.readFileSync(RUTA_COMPONENTE, 'utf8'), contexto, { filename: 'csrf.js' });

    return {
        csrf: contexto.SIGOA.csrf,
        estado: estado
    };
}

/** Respuesta simulada con las cabeceras dadas. */
function respuesta(cabeceras) {
    const valores = cabeceras || {};

    return {
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
        }
    };
}

/* ------------------------------------------------------------------ */
/* Pruebas                                                             */
/* ------------------------------------------------------------------ */

function probarResolucionPorCookie() {
    titulo('Con cookie legible se usa la cookie');

    const ctx = crearContexto({ cookie: 'otra=1; csrf_cookie_name=token-C; mas=2', meta: 'token-A' });

    igual('token-C', ctx.csrf.token(), 'La cookie manda sobre el meta de la página');
}

function probarCaidaAlMeta() {
    titulo('Sin cookie legible se usa el meta del layout');

    const ctx = crearContexto({ cookie: 'otra=1', meta: 'token-A' });

    igual('token-A', ctx.csrf.token(), 'El meta del layout es el respaldo de la primera carga');
}

function probarCadenaVacia() {
    titulo('Sin ninguna fuente disponible se devuelve cadena vacía');

    const ctx = crearContexto({ cookie: '', meta: '' });

    igual('', ctx.csrf.token(), 'No se inventa un token: el servidor responderá 403');
}

function probarCabeceraDesplazaALaCookie() {
    titulo('La cabecera de la respuesta se usa cuando no hay cookie legible');

    const ctx = crearContexto({ cookie: '', meta: 'token-A' });

    igual('token-A', ctx.csrf.token(), 'Antes de hablar con el servidor solo existe el meta');

    ok(ctx.csrf.actualizar(respuesta({ 'X-CSRF-TOKEN': 'token-B' })), 'La respuesta aporta un token nuevo');
    igual('token-B', ctx.csrf.token(), 'La siguiente petición usa el token emitido');

    ctx.estado.cookie = 'csrf_cookie_name=token-C';
    igual('token-C', ctx.csrf.token(), 'Si la cookie vuelve a ser legible, manda ella');
}

function probarCabeceraSinDistinguirMayusculas() {
    titulo('La cabecera se lee sin distinguir mayúsculas');

    const ctx = crearContexto({ cookie: '', meta: '' });

    ok(ctx.csrf.actualizar(respuesta({ 'x-csrf-token': 'token-B' })), 'Se acepta el nombre en minúsculas');
    igual('token-B', ctx.csrf.token(), 'El token se registró igual');
}

function probarRespuestaSinToken() {
    titulo('Una respuesta sin token descarta el valor memorizado');

    const ctx = crearContexto({ cookie: '', meta: 'token-A' });

    ctx.csrf.actualizar(respuesta({ 'X-CSRF-TOKEN': 'token-B' }));
    igual('token-B', ctx.csrf.token(), 'El token renovado quedó registrado');

    /* Es el caso del 403 `CSRF_INVALID`: `CsrfApi` responde desde `before()`
       y los filtros `after` no se ejecutan, así que no llega token nuevo. */
    igual(false, ctx.csrf.actualizar(respuesta({})), 'La respuesta no aporta token');
    igual('token-A', ctx.csrf.token(), 'Vuelve a mirar el meta en lugar de insistir con el viejo');
}

function probarRespuestaAusenteOInvalida() {
    titulo('Una respuesta ausente o sin cabeceras no rompe la resolución');

    const ctx = crearContexto({ cookie: 'csrf_cookie_name=token-C' });

    igual(false, ctx.csrf.actualizar(null), 'Una respuesta nula no aporta token');
    igual(false, ctx.csrf.actualizar({}), 'Una respuesta sin `headers` no aporta token');
    igual('token-C', ctx.csrf.token(), 'Se sigue resolviendo el token por la vía normal');
}

function pruebaPersistencia() {
    titulo('El token no se persiste en ningún almacenamiento');

    let lanzo = false;
    const contexto = {
        console: console,
        Promise: Promise,
        Object: Object,
        Array: Array,
        JSON: JSON,
        String: String,
        Number: Number,
        Math: Math,
        Date: Date,
        isFinite: isFinite,
        isNaN: isNaN,
        localStorage: {
            setItem: function () {
                throw new Error('no debe escribir en localStorage');
            }
        },
        sessionStorage: {
            setItem: function () {
                throw new Error('no debe escribir en sessionStorage');
            }
        },
        document: {
            cookie: 'csrf_cookie_name=token-A',
            querySelector: function () {
                return null;
            }
        }
    };

    contexto.window = contexto;
    contexto.globalThis = contexto;

    try {
        vm.createContext(contexto);
        vm.runInContext(fs.readFileSync(RUTA_COMPONENTE, 'utf8'), contexto, { filename: 'csrf.js' });

        const csrf = contexto.SIGOA.csrf;

        csrf.token();
        csrf.actualizar(respuesta({ 'X-CSRF-TOKEN': 'token-B' }));
        csrf.token();
    } catch (error) {
        lanzo = true;
    }

    igual(false, lanzo, 'Ninguna resolución ni renovación toca un almacenamiento persistente');
}

/* ------------------------------------------------------------------ */
/* Ejecución                                                           */
/* ------------------------------------------------------------------ */

const pasos = [
    probarResolucionPorCookie,
    probarCaidaAlMeta,
    probarCadenaVacia,
    probarCabeceraDesplazaALaCookie,
    probarCabeceraSinDistinguirMayusculas,
    probarRespuestaSinToken,
    probarRespuestaAusenteOInvalida,
    pruebaPersistencia
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