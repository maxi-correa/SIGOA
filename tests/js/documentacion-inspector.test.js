/* ===================================================================
   Pruebas de la documentación disponible del inspector
   ===================================================================
   Ejecuta `public/assets/js/pages/documentacion-inspector.js` en un
   entorno Node simulado (módulo `vm`) y verifica las funciones puras
   que resuelven el emparejamiento entre el listado del servidor y las
   copias descargadas en el dispositivo.

   Lo que se comprueba es la regla de negocio central de la pantalla:

   * la identidad de un documento es **lógica** (`documento_padre_id` o el
     propio `id`), no el identificador físico del PDF: al reemplazarse una
     versión cambia el documento, pero no la identidad;
   * una copia local desactualizada se detecta por versión;
   * una copia local cuyo documento ya no figura entre los vigentes se
     conserva y se marca como tal (salvo en el modo general sin conexión,
     donde no hay con qué compararla);
   * los filtros «disponibles offline» y «pendientes de descargar» se
     apoyan en esos estados.

   Uso:  node tests/js/documentacion-inspector.test.js
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
/* Carga del módulo                                                    */
/* ------------------------------------------------------------------ */

/**
 * Carga la página y devuelve sus funciones puras.
 *
 * El documento simulado queda en estado `loading`, de modo que la
 * inicialización (que sí necesita un DOM completo) no se ejecuta: aquí
 * solo se verifican las funciones de emparejamiento y filtrado.
 */
function cargarPagina() {
    const document = {
        readyState: 'loading',
        addEventListener() {}
    };

    const window = {
        console,
        location: { pathname: '/inspector/documentacion/1' },
        SIGOA: {}
    };

    window.window = window;

    const contexto = vm.createContext({
        window,
        document,
        Promise,
        Object,
        Number,
        Array,
        String,
        isFinite,
        setTimeout,
        clearTimeout
    });

    const archivo = path.join(__dirname, '..', '..', 'public', 'assets', 'js', 'pages', 'documentacion-inspector.js');

    vm.runInContext(fs.readFileSync(archivo, 'utf8'), contexto, { filename: archivo });

    return window.SIGOA.documentacion;
}

/* ------------------------------------------------------------------ */
/* Pantalla (DOM simulado)                                            */
/* ------------------------------------------------------------------ */

/**
 * Elemento del DOM simulado.
 *
 * Se usan **solo APIs estándar del DOM** (`childNodes`, `firstChild`,
 * `appendChild`...): el simulado no define propiedades inventadas, de modo
 * que cualquier acceso a una propiedad inexistente —como `hijos`— falla
 * igual que en el navegador.
 */
function elemento(tag) {
    return {
        tagName: String(tag).toUpperCase(),
        className: '',
        textContent: '',
        atributos: {},
        childNodes: [],
        parentNode: null,
        appendChild(hijo) {
            this.childNodes.push(hijo);
            hijo.parentNode = this;
            return hijo;
        },
        removeChild(hijo) {
            const posicion = this.childNodes.indexOf(hijo);

            if (posicion >= 0) {
                this.childNodes.splice(posicion, 1);
            }

            return hijo;
        },
        get firstChild() {
            return this.childNodes.length ? this.childNodes[0] : null;
        },
        setAttribute(nombre, valor) {
            this.atributos[nombre] = valor;
        },
        getAttribute(nombre) {
            return Object.prototype.hasOwnProperty.call(this.atributos, nombre)
                ? this.atributos[nombre]
                : null;
        },
        removeAttribute(nombre) {
            delete this.atributos[nombre];
        },
        addEventListener() {},
        removeEventListener() {},
        querySelector() {
            return null;
        },
        querySelectorAll() {
            return [];
        }
    };
}

const IDS_PANTALLA = [
    'documentacionDisponible', 'didLista', 'didVacio', 'didVacioTitulo', 'didVacioTexto',
    'didAviso', 'didAvisoTexto', 'didConexion', 'didConexionTexto', 'didFiltros',
    'didRefrescar', 'didResumen', 'didObra', 'didVolver', 'didDatos'
];

/**
 * Carga la página completa y ejecuta su inicialización.
 *
 * A diferencia de `cargarPagina()`, aquí sí se ejecuta el arranque real
 * (`iniciar()`): se pinta el listado, se cuentan los documentos y se actualiza
 * el estado de conexión. Es la vía por la que se detecta cualquier error de
 * manipulating del DOM en la construcción de las tarjetas.
 *
 * @param {Object|null} payload Datos iniciales de la página (null = shell
 *                           sin conexión, sin listado del servidor).
 *
 * @return {Promise<Object>} `{ porId, errores }` una vez pintado.
 */
function cargarPantalla(payload) {
    const porId = {};

    IDS_PANTALLA.forEach(function (id) {
        porId[id] = elemento('div');
    });

    if (payload) {
        porId.didDatos.textContent = JSON.stringify(payload);
    }

    const errores = [];

    const document = {
        readyState: 'complete',
        addEventListener() {},
        createElement: (tag) => elemento(tag),
        getElementById: (id) => (Object.prototype.hasOwnProperty.call(porId, id) ? porId[id] : null),
        querySelector: () => null
    };

    const consola = {
        info() {},
        warn() {},
        log() {},
        error: (...args) => {
            errores.push(args.map((argumento) => {
                return (argumento && argumento.stack) ? argumento.stack : String(argumento);
            }).join(' '));
        }
    };

    const window = {
        console: consola,
        location: { pathname: '/inspector/documentacion/2' },
        SIGOA: {},
        addEventListener() {},
        fetch: () => Promise.reject(new Error('sin conexión con el servidor'))
    };

    window.window = window;

    const contexto = vm.createContext({
        window,
        document,
        console: consola,
        Promise,
        Object,
        Number,
        Array,
        String,
        isFinite,
        setTimeout,
        clearTimeout,
        Error,
        Math,
        JSON,
        Date
    });

    const archivo = path.join(__dirname, '..', '..', 'public', 'assets', 'js', 'pages', 'documentacion-inspector.js');

    vm.runInContext(fs.readFileSync(archivo, 'utf8'), contexto, { filename: archivo });

    return new Promise((resolver) => {
        setTimeout(() => {
            resolver({ porId: porId, errores: errores });
        }, 0);
    });
}

function payloadConDocumentos(cantidad) {
    const documentos = [];

    for (let i = 1; i <= cantidad; i++) {
        documentos.push({
            id: i,
            logico_id: i,
            obra_id: 2,
            version: 1,
            tipo_documento: 'ACTA DE INICIO ' + i,
            nombre: 'documento-' + i + '.pdf',
            fecha_documento: '2026-05-02',
            tamano_bytes: 2048,
            url: 'http://10.11.20.161/index.php/inspector/documentacion/ver/' + i
        });
    }

    return {
        obra: {
            id: 2,
            codigo: 'OBR-000001',
            nombre: 'OBRA DE PRUEBA',
            expediente: '1-M-2025',
            estado: 'EN EJECUCIÓN'
        },
        usuario: { id: 1, nombre: 'PRUEBA PRUEBA' },
        documentos: documentos,
        api: 'http://10.11.20.161/index.php/inspector/documentacion/api/2'
    };
}

/* ------------------------------------------------------------------ */
/* Fixtures                                                            */
/* ------------------------------------------------------------------ */

function documento(extra) {
    return Object.assign({
        id: 7,
        logico_id: 3,
        obra_id: 1,
        version: 2,
        tipo_documento: 'ACTA DE INICIO',
        nombre: 'ACTA.pdf',
        fecha_documento: '2026-05-02',
        tamano_bytes: 2048
    }, extra || {});
}

function copia(extra) {
    return Object.assign({
        clave: '1::1::3',
        clave_usuario_obra: '1::1',
        usuario_id: 1,
        obra_id: 1,
        documento_id: 7,
        documento_logico_id: 3,
        tipo_documento: 'ACTA DE INICIO',
        version: 2,
        tamano_bytes: 2048,
        fecha_descarga: '2026-06-01T10:00:00.000Z'
    }, extra || {});
}

/* ------------------------------------------------------------------ */
/* Casos                                                               */
/* ------------------------------------------------------------------ */
function casoClaves() {
    titulo('las claves separan usuario, obra y documento lógico');

    const doc = cargarPagina();

    igual('1::1::3', doc.claveDocumento(1, 1, 3), 'la clave de la copia local incluye los tres identificadores');
    igual('1::1', doc.claveUsuarioObra(1, 1), 'la clave de usuario y obra permite indexar las copias de una obra');
    igual('1::3', doc.claveLogico(1, 3), 'la clave de emparejamiento con el servidor no depende del usuario');
    igual(
        '2::4::9',
        doc.claveDocumento(2, 4, 9),
        'dos cuentas distintas en el mismo dispositivo no comparten copias'
    );
}

function casoEstados() {
    titulo('el estado de un documento depende de su copia local');

    const doc = cargarPagina();

    igual(
        doc.ESTADOS.PENDIENTE,
        doc.estadoDocumento(documento(), null),
        'sin copia local el documento está pendiente de descargar'
    );

    igual(
        doc.ESTADOS.GUARDADO,
        doc.estadoDocumento(documento({ version: 2 }), copia({ version: 2 })),
        'con la misma versión la copia está al día'
    );

    igual(
        doc.ESTADOS.ACTUALIZACION,
        doc.estadoDocumento(documento({ version: 3 }), copia({ version: 2 })),
        'con una versión más nueva en el servidor hay actualización disponible'
    );

    igual(
        doc.ESTADOS.NO_VIGENTE,
        doc.estadoDocumento(null, copia()),
        'una copia cuyo documento ya no está vigente se marca como tal'
    );
}

function casoEmparejamientoPorDocumentoLogico() {
    titulo('el emparejamiento se hace por documento lógico, no por id físico');

    const doc = cargarPagina();

    /* La v2 del acta tiene id 7 pero cuelga del documento raíz 3: la copia
       local descargada cuando era la v1 (id 5) debe emparejarse igual. */
    const entradas = doc.combinar(
        [documento({ id: 7, logico_id: 3, version: 2 })],
        {
            '1::3': copia({ documento_id: 5, documento_logico_id: 3, version: 1 })
        }
    );

    igual(1, entradas.length, 'no se duplica el documento');

    igual(
        doc.ESTADOS.ACTUALIZACION,
        entradas[0].estado,
        'la copia de la v1 se detecta como desactualizada frente a la v2 vigente'
    );
}

function casoCopiaLocalNoVigente() {
    titulo('una copia local que ya no está vigente se conserva y se señala');

    const doc = cargarPagina();

    const entradas = doc.combinar(
        [documento({ id: 7, logico_id: 3 })],
        {
            '1::3': copia(),
            '1::9': copia({ documento_logico_id: 9, documento_id: 9, tipo_documento: 'ACTA DE REINICIO' })
        }
    );

    igual(2, entradas.length, 'la copia huérfana sigue en el listado');

    const huerfana = entradas.filter(function (entrada) {
        return entrada.local && Number(entrada.local.documento_logico_id) === 9;
    })[0];

    igual(
        doc.ESTADOS.NO_VIGENTE,
        huerfana.estado,
        'la copia que ya no está vigente se marca como tal'
    );
}

function casoModoGeneralSinConexion() {
    titulo('sin servidor no hay versiones que caduquen');

    const doc = cargarPagina();

    const entradas = doc.combinar(
        [],
        { '1::3': copia() },
        { sinServidor: true }
    );

    igual(1, entradas.length, 'se listan las copias descargadas');

    igual(
        doc.ESTADOS.GUARDADO,
        entradas[0].estado,
        'sin listado del servidor la copia se considera disponible'
    );
}

function casoFiltros() {
    titulo('los filtros se apoyan en los estados');

    const doc = cargarPagina();

    const entradas = [
        { documento: documento({ logico_id: 1 }), local: null, estado: doc.ESTADOS.PENDIENTE },
        { documento: documento({ logico_id: 2, version: 3 }), local: copia({ documento_logico_id: 2, version: 2 }), estado: doc.ESTADOS.ACTUALIZACION },
        { documento: documento({ logico_id: 3 }), local: copia({ documento_logico_id: 3 }), estado: doc.ESTADOS.GUARDADO }
    ];

    igual(3, doc.filtrar(entradas, doc.FILTROS.TODOS).length, '«todos» devuelve el listado completo');

    igual(
        2,
        doc.filtrar(entradas, doc.FILTROS.OFFLINE).length,
        '«disponibles offline» incluye la copia al día y la desactualizada'
    );

    igual(
        2,
        doc.filtrar(entradas, doc.FILTROS.PENDIENTES).length,
        '«pendientes» incluye lo que falta descargar y lo que está desactualizado'
    );

    igual(
        0,
        doc.filtrar([], doc.FILTROS.TODOS).length,
        'un listado vacío no produce entradas'
    );
}

function casoFormatos() {
    titulo('el tamaño y la fecha se presentan de forma legible');

    const doc = cargarPagina();

    igual('', doc.formatearTamano(0), 'un tamaño desconocido no se muestra');
    igual('512 B', doc.formatearTamano(512), 'los bytes se muestran tal cual');
    igual('1 KB', doc.formatearTamano(1024), 'el kilo se redondea');
    igual('1.5 MB', doc.formatearTamano(1572864), 'el mega se muestra con un decimal');

    igual('', doc.formatearFecha(''), 'una fecha ausente no se muestra');
    igual('02/05/2026', doc.formatearFecha('2026-05-02'), 'la fecha se muestra en formato local');
}

function casoPintadoDelListado() {
    titulo('el listado vigente se pinta en la pantalla');

    return cargarPantalla(payloadConDocumentos(3)).then(function (pantalla) {
        igual(0, pantalla.errores.length, 'la pantalla se pinta sin errores');

        igual(
            3,
            pantalla.porId.didLista.childNodes.length,
            'se pinta una tarjeta por cada documento vigente del servidor'
        );

        igual(
            'Conectado con el servidor. La documentación está actualizada.',
            pantalla.porId.didConexionTexto.textContent,
            'el estado de conexión confirma que el servidor entregó el listado'
        );

        igual(
            '3 documentos · 0 disponibles offline',
            pantalla.porId.didResumen.textContent,
            'el resumen cuenta los documentos y las copias locales'
        );

        igual(
            true,
            pantalla.porId.didVacio.hidden,
            'el estado vacío no se muestra cuando hay documentos'
        );
    });
}

function casoPintadoSinDocumentos() {
    titulo('una obra sin documentación vigente lo indica');

    return cargarPantalla(payloadConDocumentos(0)).then(function (pantalla) {
        igual(0, pantalla.errores.length, 'la pantalla se pinta sin errores');

        igual(
            0,
            pantalla.porId.didLista.childNodes.length,
            'no se pinta ninguna tarjeta'
        );

        igual(
            false,
            pantalla.porId.didVacio.hidden,
            'el estado vacío se muestra'
        );

        igual(
            'La obra no tiene documentación vigente',
            pantalla.porId.didVacioTitulo.textContent,
            'el mensaje explica que no hay documentación vigente'
        );
    });
}

function casoPintadoSinServidor() {
    titulo('sin servidor la pantalla lo dice y no inventa un listado');

    return cargarPantalla(null).then(function (pantalla) {
        igual(0, pantalla.errores.length, 'la pantalla se pinta sin errores');

        igual(
            0,
            pantalla.porId.didLista.childNodes.length,
            'sin servidor no se pintan documentos'
        );

        igual(
            'Sin conexión con el servidor. Se muestran los documentos descargados en este dispositivo.',
            pantalla.porId.didConexionTexto.textContent,
            'el estado de conexión avisa de que no hay servidor'
        );

        igual(
            'No hay información disponible sin conexión',
            pantalla.porId.didVacioTitulo.textContent,
            'el mensaje pide conectarse al servidor'
        );
    });
}

/* ------------------------------------------------------------------ */
/* Ejecución                                                           */
/* ------------------------------------------------------------------ */

(async function () {
    casoClaves();
    casoEstados();
    casoEmparejamientoPorDocumentoLogico();
    casoCopiaLocalNoVigente();
    casoModoGeneralSinConexion();
    casoFiltros();
    casoFormatos();

    await casoPintadoDelListado();
    await casoPintadoSinDocumentos();
    await casoPintadoSinServidor();

    console.log('');
    console.log('  ' + pruebas + ' pruebas, ' + fallos + ' fallos');

    if (fallos > 0) {
        process.exit(1);
    }
})();
