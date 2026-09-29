/* ===================================================================
   Pruebas del aviso de sincronización de la página de obra (D.6.1)
   ===================================================================
   Ejecuta `public/assets/js/pages/obra-inspecciones.js` en un
   entorno Node simulado (módulo `vm`) con un DOM mínimo y verifica
   que el aviso del botón "Sincronizar" distinga los cuatro desenlaces
   reales de un ciclo: éxito, parcial, sin avances y error.

   El caso "sin avances" es la regresión de D.6: la cola seguía
   pendiente (1 inspección + 4 fotografías en espera) y la interfaz
   mostraba nonetheless un aviso verde de "Sincronización finalizada".

   Uso:  node tests/js/obra-inspecciones.test.js
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

function contiene(texto, fragmento, mensaje) {
    ok(
        texto.indexOf(fragmento) !== -1,
        mensaje + ' (no se encontró "' + fragmento + '" en "' + texto + '")'
    );
}

function noContiene(texto, fragmento, mensaje) {
    ok(
        texto.indexOf(fragmento) === -1,
        mensaje + ' (no debía aparecer "' + fragmento + '" en "' + texto + '")'
    );
}

function tieneClase(elemento, clase, mensaje) {
    ok(
        (' ' + elemento + ' ').indexOf(' ' + clase + ' ') !== -1,
        mensaje + ' (clases: ' + JSON.stringify(elemento) + ')'
    );
}

function titulo(nombre) {
    console.log('· ' + nombre);
}

/* ------------------------------------------------------------------ */
/* DOM mínimo                                                          */
/* ------------------------------------------------------------------ */

function crearElemento(atributos) {
    const atributosIniciales = atributos || {};

    const elemento = {
        className: '',
        innerHTML: '',
        textContent: '',
        dataset: {},
        atributos: Object.assign({}, atributosIniciales),
        hijos: [],
        oyentes: {},
        appendChild(hijo) {
            this.hijos.push(hijo);
            return hijo;
        },
        setAttribute(nombre, valor) {
            this.atributos[nombre] = valor;
        },
        removeAttribute(nombre) {
            delete this.atributos[nombre];
        },
        addEventListener(tipo, fn) {
            this.oyentes[tipo] = fn;
        },
        click() {
            if (typeof this.oyentes.click === 'function') {
                this.oyentes.click();
            }
        }
    };

    return elemento;
}

/**
 * Carga la página con un `document` falso y devuelve un disparador que
 * ejecuta un ciclo de sincronización con el resumen indicado.
 */
function cargarPagina(resumen) {
    const elementos = {
        inspeccionesLocales: crearElemento(),
        inspeccionesLocalesLista: crearElemento(),
        btnSincronizar: crearElemento(),
        sincronizacionEstado: crearElemento()
    };

    elementos.inspeccionesLocales.dataset.obraId = '1';

    const document = {
        readyState: 'complete',
        getElementById(id) {
            return Object.prototype.hasOwnProperty.call(elementos, id) ? elementos[id] : null;
        },
        createElement() {
            return crearElemento();
        },
        addEventListener() {}
    };

    const window = {
        console,
        SIGOA: {
            almacenamiento: {
                soportado() {
                    return true;
                },
                ALMACENES: { inspecciones: 'inspecciones', fotografias: 'fotografias' },
                buscarPorIndice() {
                    return Promise.resolve([]);
                }
            },
            sincronizacion: {
                obtenerOperaciones() {
                    return Promise.resolve([]);
                },
                sincronizarTodo() {
                    return Promise.resolve(resumen);
                }
            }
        }
    };

    window.window = window;

    const contexto = vm.createContext({
        window,
        document,
        URL: { createObjectURL() { return 'blob:x'; }, revokeObjectURL() {} },
        Promise,
        Object,
        Number,
        Array,
        isFinite,
        setTimeout,
        clearTimeout
    });

    const archivo = path.join(__dirname, '..', '..', 'public', 'assets', 'js', 'pages', 'obra-inspecciones.js');

    vm.runInContext(fs.readFileSync(archivo, 'utf8'), contexto, { filename: archivo });

    /* El ciclo de la página es asíncrono (promesas). La promesa que
       `sincronizar()` encadena no se expone, así que se espera un turno
       completo de la cola de tareas antes de leer el aviso. */
    elementos.btnSincronizar.click();

    return new Promise((resolver) => {
        setTimeout(() => {
            const alerta = elementos.sincronizacionEstado.hijos[0];

            resolver({
                clase: alerta ? alerta.className : null,
                texto: alerta && alerta.hijos.length > 1 ? alerta.hijos[1].textContent : '',
                oculto: Object.prototype.hasOwnProperty.call(elementos.sincronizacionEstado.atributos, 'hidden')
            });
        }, 0);
    });
}

function resumenBase(extra) {
    return Object.assign({
        vacio: false,
        inspecciones: { sincronizadas: 0, yaSincronizadas: 0, rechazadas: 0, errores: 0 },
        fotografias: { sincronizadas: 0, errores: 0, bloqueadas: 0 },
        pendientes: { total: 0, inspecciones: { pendientes: 0, errores: 0, sincronizadas: 0 }, fotografias: { pendientes: 0, errores: 0, sincronizadas: 0 } }
    }, extra || {});
}

/* ------------------------------------------------------------------ */
/* Casos                                                               */
/* ------------------------------------------------------------------ */

async function casoExito() {
    titulo('ciclo exitoso: aviso verde y cola vacía');

    const aviso = await cargarPagina(resumenBase({
        inspecciones: { sincronizadas: 2, yaSincronizadas: 1, rechazadas: 0, errores: 0 },
        fotografias: { sincronizadas: 3, errores: 0, bloqueadas: 0 },
        pendientes: { total: 0, inspecciones: { pendientes: 0, errores: 0, sincronizadas: 3 }, fotografias: { pendientes: 0, errores: 0, sincronizadas: 3 } }
    }));

    tieneClase(aviso.clase, 'alert-success', 'el ciclo completo se informa como exitoso');
    contiene(aviso.texto, 'Sincronización finalizada', 'el texto anuncia finalización');
    contiene(aviso.texto, '2 inspecciones sincronizadas', 'detalla inspecciones sincronizadas');
    contiene(aviso.texto, '3 fotografías sincronizadas', 'detalla fotografías sincronizadas');
    noContiene(aviso.texto, 'pendientes', 'no hay pendientes que anunciar');
    igual(false, aviso.oculto, 'el aviso queda visible');
}

async function casoParcial() {
    titulo('ciclo parcial: aviso de advertencia, no verde');

    const aviso = await cargarPagina(resumenBase({
        inspecciones: { sincronizadas: 1, yaSincronizadas: 0, rechazadas: 0, errores: 0 },
        fotografias: { sincronizadas: 0, errores: 0, bloqueadas: 0 },
        pendientes: { total: 2, inspecciones: { pendientes: 2, errores: 0, sincronizadas: 1 }, fotografias: { pendientes: 0, errores: 0, sincronizadas: 0 } }
    }));

    tieneClase(aviso.clase, 'alert-warning', 'un ciclo incompleto no se informa como exitoso');
    contiene(aviso.texto, 'Sincronización parcial', 'el texto anuncia sincronización parcial');
    contiene(aviso.texto, 'Todavía quedan 2 elementos pendientes', 'indica cuántos elementos siguen en cola');
}

async function casoSinAvances() {
    titulo('ciclo sin avances con cola pendiente: regresión de D.6');

    const aviso = await cargarPagina(resumenBase({
        vacio: false,
        inspecciones: { sincronizadas: 0, yaSincronizadas: 0, rechazadas: 0, errores: 0 },
        fotografias: { sincronizadas: 0, errores: 0, bloqueadas: 4 },
        pendientes: { total: 5, inspecciones: { pendientes: 1, errores: 0, sincronizadas: 0 }, fotografias: { pendientes: 4, errores: 0, sincronizadas: 0 } }
    }));

    tieneClase(aviso.clase, 'alert-warning', 'no se muestra verde cuando nada se procesó');
    noContiene(aviso.texto, 'Sincronización finalizada', 'no se afirma que la sincronización terminó');
    contiene(aviso.texto, 'Sincronización sin avances', 'el texto explica que no hubo progreso');
    contiene(aviso.texto, '4 fotografías en espera de su inspección', 'conserva el detalle de bloqueo');
}

async function casoSinPendientes() {
    titulo('nada pendiente: aviso informativo');

    const aviso = await cargarPagina(resumenBase({ vacio: true }));

    tieneClase(aviso.clase, 'alert-info', 'sin trabajo pendiente el aviso es informativo');
    contiene(aviso.texto, 'No hay inspecciones ni fotografías pendientes', 'lo indica de forma explícita');
}

async function casoRechazos() {
    titulo('rechazos: aviso de advertencia con detalle');

    const aviso = await cargarPagina(resumenBase({
        inspecciones: { sincronizadas: 0, yaSincronizadas: 0, rechazadas: 1, errores: 0 },
        pendientes: { total: 1, inspecciones: { pendientes: 0, errores: 1, sincronizadas: 0 }, fotografias: { pendientes: 0, errores: 0, sincronizadas: 0 } }
    }));

    tieneClase(aviso.clase, 'alert-warning', 'un rechazo no es un éxito');
    contiene(aviso.texto, '1 inspección rechazada', 'detalla el rechazo');
}

async function casoError() {
    titulo('error de red: aviso de peligro');

    const aviso = await cargarPagina(resumenBase({ error: 'RED' }));

    tieneClase(aviso.clase, 'alert-danger', 'un fallo de conexión es peligro');
    contiene(aviso.texto, 'No fue posible conectarse con el servidor', 'explica el motivo');
}

/* ------------------------------------------------------------------ */
/* Ejecución                                                           */
/* ------------------------------------------------------------------ */

(async function () {
    await casoExito();
    await casoParcial();
    await casoSinAvances();
    await casoSinPendientes();
    await casoRechazos();
    await casoError();

    console.log('');
    console.log('  ' + pruebas + ' pruebas, ' + fallos + ' fallos');

    if (fallos > 0) {
        process.exit(1);
    }
})();
