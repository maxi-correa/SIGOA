/* ===================================================================
   Pruebas de la vista de obra: sincronización (D.6.1) y separación
   de la cola (E.3)
   ===================================================================
   Ejecuta `public/assets/js/pages/obra-inspecciones.js` en un
   entorno Node simulado (módulo `vm`) con un DOM mínimo y verifica
   que el aviso del botón "Sincronizar" distinga los cuatro desenlaces
   reales de un ciclo: éxito, parcial, sin avances y error.

   El caso "sin avances" es la regresión de D.6: la cola seguía
   pendiente (1 inspección + 4 fotografías en espera) y la interfaz
   mostraba nonetheless un aviso verde de "Sincronización finalizada".

   Desde E.3 se verifica además que la vista muestre **solo la cola**:
   una inspección ya sincronizada no aparece, salvo que conserve
   fotografías pendientes.

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

/**
 * Todo el texto que cuelga de un nodo del DOM simulado.
 */
function textoDe(elemento) {
    if (!elemento) {
        return '';
    }

    let texto = elemento.textContent || '';

    (elemento.hijos || []).forEach(function (hijo) {
        texto += ' ' + textoDe(hijo);
    });

    return texto;
}

/**
 * Descendientes con una clase dada, en orden de aparición.
 */
function conClase(elemento, clase, destino) {
    const encontrados = destino || [];

    (elemento && elemento.hijos ? elemento.hijos : []).forEach(function (hijo) {
        if (hijo.className && (' ' + hijo.className + ' ').indexOf(' ' + clase + ' ') !== -1) {
            encontrados.push(hijo);
        }

        conClase(hijo, clase, encontrados);
    });

    return encontrados;
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
        hasAttribute(nombre) {
            return Object.prototype.hasOwnProperty.call(this.atributos, nombre);
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
 *
 * `opciones` permite representar la cola local (E.3):
 *
 * * `inspecciones` — inspecciones de la obra en IndexedDB;
 * * `fotografias`  — mapa `inspeccion_uuid` → fotografías;
 * * `operaciones`  — cola local (`SIGOA.sincronizacion`);
 * * `ciclar`       — si es `false`, no se pulsa "Sincronizar" (falsea el
 *                    aviso y deja el render inicial intacto).
 */
function cargarPagina(resumen, opciones) {
    const config = Object.assign({ inspecciones: [], fotografias: {}, operaciones: [], ciclar: true }, opciones || {});

    const elementos = {
        inspeccionesLocales: crearElemento(),
        inspeccionesLocalesLista: crearElemento(),
        btnSincronizar: crearElemento(),
        sincronizacionEstado: crearElemento(),
        sincronizacionResumen: crearElemento()
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
                buscarPorIndice(store, indice, valor) {
                    if (store === 'inspecciones' && indice === 'por_obra') {
                        return Promise.resolve(config.inspecciones);
                    }

                    if (store === 'fotografias' && indice === 'por_inspeccion') {
                        return Promise.resolve(config.fotografias[valor] || []);
                    }

                    return Promise.resolve([]);
                }
            },
            sincronizacion: {
                TIPO_INSPECCION: 'inspeccion',
                TIPO_FOTOGRAFIA: 'fotografia',
                ESTADO_SINCRONIZADA: 'SINCRONIZADA',
                OP_PENDIENTE: 'PENDIENTE',
                OP_SINCRONIZANDO: 'SINCRONIZANDO',
                OP_SINCRONIZADA: 'SINCRONIZADA',
                OP_ERROR: 'ERROR',
                obtenerOperaciones() {
                    return Promise.resolve(config.operaciones);
                },
                buscarOperacion(operaciones, tipo, uuid) {
                    return (operaciones || []).find(function (operacion) {
                        return operacion.tipo === tipo && operacion.entidad_uuid === uuid;
                    }) || null;
                },
                requiereReintentoManual() {
                    return false;
                },
                reintentar() {
                    return Promise.resolve();
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

    /* El render inicial es asíncrono (promesas), igual que el ciclo del
       botón: se espera un turno completo de la cola de tareas antes de
       leer la lista o el aviso. */
    if (config.ciclar) {
        elementos.btnSincronizar.click();
    }

    return new Promise((resolver) => {
        setTimeout(() => {
            const alerta = elementos.sincronizacionEstado.hijos[0];

            resolver({
                lista: elementos.inspeccionesLocalesLista,
                resumen: elementos.sincronizacionResumen.textContent,
                clase: alerta ? alerta.className : null,
                texto: alerta && alerta.hijos.length > 1 ? alerta.hijos[1].textContent : '',
                oculto: Object.prototype.hasOwnProperty.call(elementos.sincronizacionEstado.atributos, 'hidden')
            });
        }, 0);
    });
}

function inspeccion(extra) {
    return Object.assign({
        uuid: 'uuid-1',
        obra_id: 1,
        fecha_inspeccion: '2026-09-22',
        hora_inspeccion: '10:30:00',
        observacion: 'Sin observación.',
        estado_local: 'PENDIENTE_SYNC'
    }, extra || {});
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
/* E.3 — la vista muestra la cola, no el historial                      */
/* ------------------------------------------------------------------ */

async function casoOcultaInspeccionesSincronizadas() {
    titulo('una inspección ya sincronizada sale de la vista');

    const vista = await cargarPagina(resumenBase({ vacio: true }), {
        ciclar: false,
        inspecciones: [
            inspeccion({ uuid: 'uuid-1', fecha_inspeccion: '2026-09-22' }),
            inspeccion({ uuid: 'uuid-2', fecha_inspeccion: '2026-09-21', estado_local: 'SINCRONIZADA', servidor_id: 17 })
        ]
    });

    const items = conClase(vista.lista, 'io-locales-item');

    igual(1, items.length, 'solo se muestra la inspección pendiente');
    contiene(textoDe(items[0]), '22/09/2026', 'es la inspección que sigue en cola');
    noContiene(textoDe(vista.lista), '21/09/2026', 'la inspección sincronizada no aparece');
    noContiene(textoDe(vista.lista), 'ID en servidor', 'no se informa el identificador del servidor de una inspección ya sincronizada');
}

async function casoConservaInspeccionConFotosPendientes() {
    titulo('una inspección sincronizada con fotos en cola se conserva');

    const vista = await cargarPagina(resumenBase({ vacio: true }), {
        ciclar: false,
        inspecciones: [
            inspeccion({ uuid: 'uuid-2', estado_local: 'SINCRONIZADA', servidor_id: 17 })
        ],
        fotografias: {
            'uuid-2': [
                { uuid: 'foto-1', estado_local: 'PENDIENTE_SYNC' }
            ]
        },
        operaciones: [
            { tipo: 'fotografia', entidad_uuid: 'foto-1', dependencia_uuid: 'uuid-2', estado: 'PENDIENTE' }
        ]
    });

    const items = conClase(vista.lista, 'io-locales-item');

    igual(1, items.length, 'la inspección se mantiene visible por sus fotografías');
    contiene(textoDe(items[0]), '1 fotografía pendiente de enviar', 'aclara que lo pendiente son las fotografías');
}

async function casoFiltraFotografiasSincronizadas() {
    titulo('una fotografía ya sincronizada sale de la galería de la cola');

    const vista = await cargarPagina(resumenBase({ vacio: true }), {
        ciclar: false,
        inspecciones: [inspeccion({ uuid: 'uuid-1' })],
        fotografias: {
            'uuid-1': [
                { uuid: 'foto-pendiente', estado_local: 'PENDIENTE_SYNC' },
                { uuid: 'foto-sincronizada', estado_local: 'SINCRONIZADA', ruta_thumbnail: 'OBR-000001/…' }
            ]
        },
        operaciones: [
            { tipo: 'fotografia', entidad_uuid: 'foto-pendiente', dependencia_uuid: 'uuid-1', estado: 'PENDIENTE' }
        ]
    });

    const toggle = conClase(vista.lista, 'io-locales-fotos-toggle')[0];

    ok(toggle, 'la inspección pendiente permite expandir sus fotografías');
    toggle.click();

    await new Promise((resolver) => setTimeout(resolver, 0));

    const fotos = conClase(vista.lista, 'io-locales-foto');

    igual(1, fotos.length, 'solo se muestra la fotografía pendiente');
    noContiene(textoDe(vista.lista), 'Archivo liberado', 'la fotografía sincronizada no se lista');
}

async function casoResumenDeCola() {
    titulo('el encabezado resume la cola pendiente');

    const vista = await cargarPagina(resumenBase({ vacio: true }), {
        ciclar: false,
        inspecciones: [
            inspeccion({ uuid: 'uuid-1' }),
            inspeccion({ uuid: 'uuid-2', fecha_inspeccion: '2026-09-20' })
        ],
        operaciones: [
            { tipo: 'fotografia', entidad_uuid: 'foto-1', dependencia_uuid: 'uuid-1', estado: 'PENDIENTE' },
            { tipo: 'fotografia', entidad_uuid: 'foto-2', dependencia_uuid: 'uuid-1', estado: 'ERROR', error: 'HTTP 500' }
        ]
    });

    igual(
        '2 inspecciones pendientes de sincronización · 2 fotografías pendientes de sincronización · 1 operación con error.',
        vista.resumen,
        'el encabezado informa inspecciones, fotografías y errores de la cola'
    );
}

async function casoColaVacia() {
    titulo('sin cola: el encabezado y la lista lo dicen explícitamente');

    const vista = await cargarPagina(resumenBase({ vacio: true }), {
        ciclar: false,
        inspecciones: [
            inspeccion({ uuid: 'uuid-2', estado_local: 'SINCRONIZADA', servidor_id: 17 })
        ]
    });

    igual(
        'No hay inspecciones ni fotografías pendientes de sincronización.',
        vista.resumen,
        'el encabezado no inventa pendientes'
    );

    igual(0, conClase(vista.lista, 'io-locales-item').length, 'no queda ninguna inspección en la lista');
    igual(1, conClase(vista.lista, 'io-locales-vacio').length, 'la lista informa el estado vacío de la cola');
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
    await casoOcultaInspeccionesSincronizadas();
    await casoConservaInspeccionConFotosPendientes();
    await casoFiltraFotografiasSincronizadas();
    await casoResumenDeCola();
    await casoColaVacia();

    console.log('');
    console.log('  ' + pruebas + ' pruebas, ' + fallos + ' fallos');

    if (fallos > 0) {
        process.exit(1);
    }
})();
