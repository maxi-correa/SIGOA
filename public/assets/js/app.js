/* ===================================================================
   SIGOA — Inicialización global (Fase D.1: fundación PWA / offline)
   ===================================================================
   Responsabilidad global concreta (RNF §41b / docs/SIGOA.md §53):

   * registrar el Service Worker cuando el navegador lo soporta y el
     contexto lo permite (HTTPS o localhost);
   * iniciar la capa de conectividad online/offline;
   * crear/abre la base IndexedDB local (stores preparados).

   Ninguna falla de esta capa debe romper la aplicación: todo queda
   encapsulado y solo se informa por consola del navegador.
   =================================================================== */

(function () {
    'use strict';

    function esContextoSeguro() {
        if (window.location.protocol === 'https:') {
            return true;
        }

        var host = window.location.hostname;

        /* Excepción de desarrollo (Chrome/Safari sobre localhost). */
        return host === 'localhost' || host === '127.0.0.1' || host === '[::1]';
    }

    function registrarServiceWorker() {
        if (!('serviceWorker' in navigator)) {
            return;
        }

        if (!esContextoSeguro()) {
            if (window.console && console.info) {
                console.info(
                    'SIGOA: Service Worker no se registra fuera de un contexto seguro '
                    + '(HTTPS o localhost). Pendiente de activar en producción.'
                );
            }
            return;
        }

        navigator.serviceWorker.register('/sw.js').then(function () {
            if (window.console && console.info) {
                console.info('SIGOA: Service Worker registrado.');
            }
        }).catch(function (error) {
            if (window.console && console.error) {
                console.error('SIGOA: no se pudo registrar el Service Worker.', error);
            }
        });
    }

    function iniciar() {
        if (window.SIGOA && window.SIGOA.conectividad) {
            window.SIGOA.conectividad.iniciar();
        }

        if (window.SIGOA && window.SIGOA.almacenamiento) {
            window.SIGOA.almacenamiento.iniciar();
        }

        registrarServiceWorker();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();