/* ===================================================================
   Pruebas del buscador y filtro por estado del listado de obras
   ===================================================================
   Ejecuta `public/assets/js/pages/obras.js` en un entorno Node simulado
   (módulo `vm`) con un DOM mínimo y verifica el modelo de búsqueda
   explícita del listado:

   - escribir no envía el formulario (búsqueda explícita);
   - cambiar el estado se aplica de inmediato, conservando el texto;
   - el envío del formulario conserva el término y el estado;
   - tras la recarga se restaura el foco y el cursor del buscador;
   - sin término de búsqueda el buscador no roba el foco.

   Uso:  node tests/js/obras-filtros.test.js
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
/* DOM mínimo                                                          */
/* ------------------------------------------------------------------ */

function crearElemento(extra) {
    const oyentes = {};

    const elemento = Object.assign({
        value: '',
        oyentes,
        addEventListener(tipo, fn) {
            oyentes[tipo] = fn;
        },
        disparar(tipo) {
            if (typeof oyentes[tipo] === 'function') {
                oyentes[tipo]();
            }
        },
        focus() {
            this.enfocado = true;
        },
        setSelectionRange(inicio, fin) {
            this.seleccion = [inicio, fin];
        }
    }, extra || {});

    return elemento;
}

/**
 * Carga obras.js con un formulario de filtros simulado.
 *
 * Devuelve los elementos y los envíos registrados. Cada envío conserva
 * el término de búsqueda y el estado que el formulario transmitiría.
 */
function cargarFiltros(opciones) {
    const config = Object.assign({ buscarInicial: '', estadoInicial: '' }, opciones || {});

    const envios = [];

    const inputBuscar = crearElemento({ value: config.buscarInicial });
    const selectEstado = crearElemento({ value: config.estadoInicial });

    const formFiltros = crearElemento({
        submit() {
            envios.push({
                buscar: inputBuscar.value,
                estado: selectEstado.value
            });
        }
    });

    const elementos = {
        obrasFiltrosForm: formFiltros,
        buscarObra: inputBuscar,
        filtroEstado: selectEstado
    };

    const document = {
        body: { style: {} },
        getElementById(id) {
            return Object.prototype.hasOwnProperty.call(elementos, id) ? elementos[id] : null;
        },
        querySelectorAll() {
            return [];
        },
        addEventListener() {}
    };

    const contexto = vm.createContext({
        window: { document },
        document,
        Object,
        Number,
        Array,
        String,
        parseInt,
        isNaN
    });

    const archivo = path.join(__dirname, '..', '..', 'public', 'assets', 'js', 'pages', 'obras.js');

    vm.runInContext(fs.readFileSync(archivo, 'utf8'), contexto, { filename: archivo });

    return {
        inputBuscar,
        selectEstado,
        formFiltros,
        envios() {
            return envios;
        },
        ultimoEnvio() {
            return envios.length > 0 ? envios[envios.length - 1] : null;
        }
    };
}

/* ------------------------------------------------------------------ */
/* Casos                                                               */
/* ------------------------------------------------------------------ */

function casoEscribirNoEnvia() {
    titulo('escribir en el buscador no envía el formulario');

    const pagina = cargarFiltros();

    pagina.inputBuscar.value = 'plaza';
    pagina.inputBuscar.disparar('input');

    igual(0, pagina.envios().length, 'no se envía solo con escribir');
}

function casoCambioDeEstadoInmediato() {
    titulo('cambiar el estado envía de inmediato');

    const pagina = cargarFiltros();

    pagina.selectEstado.value = '3';
    pagina.selectEstado.disparar('change');

    igual(1, pagina.envios().length, 'el cambio de estado se aplica sin pulsar un botón');
    igual('3', pagina.ultimoEnvio().estado, 'se envía el estado seleccionado');
}

function casoCambioDeEstadoConservaTexto() {
    titulo('cambiar el estado conserva el texto ingresado');

    const pagina = cargarFiltros({ buscarInicial: 'plaza' });

    pagina.selectEstado.value = '2';
    pagina.selectEstado.disparar('change');

    igual(1, pagina.envios().length, 'se envía una vez');
    igual('plaza', pagina.ultimoEnvio().buscar, 'el término de búsqueda se conserva');
    igual('plaza', pagina.inputBuscar.value, 'el campo mantiene el texto');
}

function casoTipoConCambioDeEstado() {
    titulo('el texto escrito se combina con el estado al cambiarlo');

    const pagina = cargarFiltros();

    pagina.inputBuscar.value = 'pelliza';
    pagina.inputBuscar.disparar('input');
    pagina.selectEstado.value = '4';
    pagina.selectEstado.disparar('change');

    igual(1, pagina.envios().length, 'solo se envía al cambiar el estado');
    igual('pelliza', pagina.ultimoEnvio().buscar, 'se envía el texto no aplicado aún');
    igual('4', pagina.ultimoEnvio().estado, 'se envía el estado elegido');
}

function casoRestauraFoco() {
    titulo('tras la recarga se restaura el foco y el cursor del buscador');

    const pagina = cargarFiltros({ buscarInicial: 'plaza' });

    igual(true, pagina.inputBuscar.enfocado === true, 'el buscador recupera el foco');
    igual('5,5', String(pagina.inputBuscar.seleccion), 'el cursor queda al final del texto');
}

function casoSinFocoSiVacio() {
    titulo('sin término de búsqueda el buscador no roba el foco');

    const pagina = cargarFiltros();

    igual(false, pagina.inputBuscar.enfocado === true, 'no se enfoca el buscador vacío');
}

/* ------------------------------------------------------------------ */
/* Ejecución                                                           */
/* ------------------------------------------------------------------ */

casoEscribirNoEnvia();
casoCambioDeEstadoInmediato();
casoCambioDeEstadoConservaTexto();
casoTipoConCambioDeEstado();
casoRestauraFoco();
casoSinFocoSiVacio();

console.log('');
console.log('  ' + pruebas + ' pruebas, ' + fallos + ' fallos');

if (fallos > 0) {
    process.exit(1);
}
