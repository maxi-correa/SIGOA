/* ===================================================================
   SIGOA — Estado de conectividad online/offline (Fase D.1 / §52.10)
   ===================================================================
   Capa propia de detección de conectividad:

   * estado inicial: `navigator.onLine`;
   * cambios: eventos `online` y `offline` del navegador;
   * un indicador discreto en la topbar se actualiza con texto +
     iconografía + color (RNF §44), nunca solo con color.

   IMPORTANTE (documentado en docs/SIGOA.md §53):
   `navigator.onLine` solo informa que el dispositivo tiene red: NO
   confirma que el servidor SIGOA esté disponible ni que exista sesión
   válida. La futura sincronización deberá validar servidor y sesión
   en cada intento.
   =================================================================== */

(function () {
    'use strict';

    var INDICADOR_ID = 'sigaConnectividad';

    var conectado = (typeof navigator !== 'undefined' && typeof navigator.onLine === 'boolean')
        ? navigator.onLine
        : true;

    var escuchas = [];

    function esOnline() {
        return conectado;
    }

    function notificar() {
        if (typeof document !== 'undefined') {
            pintarIndicador();
        }

        for (var i = 0; i < escuchas.length; i++) {
            try {
                escuchas[i](conectado);
            } catch (error) {
                if (window.console && console.error) {
                    console.error('SIGOA: consumidor de conectividad falló.', error);
                }
            }
        }
    }

    function pintarIndicador() {
        var indicador = document.getElementById(INDICADOR_ID);
        if (!indicador) {
            return;
        }

        var icono = indicador.querySelector('.connectivity-icon');
        var texto = indicador.querySelector('.connectivity-text');

        indicador.classList.toggle('is-online', conectado);
        indicador.classList.toggle('is-offline', !conectado);

        if (icono) {
            icono.className = 'bi connectivity-icon ' + (conectado ? 'bi-wifi' : 'bi-wifi-off');
        }

        if (texto) {
            texto.textContent = conectado ? 'En línea' : 'Sin conexión';
        }

        indicador.setAttribute(
            'aria-label',
            conectado ? 'Conectado a Internet' : 'Sin conexión a Internet'
        );
    }

    function alCambiar(callback) {
        if (typeof callback === 'function') {
            escuchas.push(callback);
        }

        return function () {
            var posicion = escuchas.indexOf(callback);
            if (posicion !== -1) {
                escuchas.splice(posicion, 1);
            }
        };
    }

    function iniciar() {
        window.addEventListener('online', function () {
            conectado = true;
            notificar();
        });

        window.addEventListener('offline', function () {
            conectado = false;
            notificar();
        });

        notificar();
    }

    window.SIGOA = window.SIGOA || {};
    window.SIGOA.conectividad = {
        esOnline: esOnline,
        alCambiar: alCambiar,
        iniciar: iniciar
    };
})();