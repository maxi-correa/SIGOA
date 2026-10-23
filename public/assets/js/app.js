/* ===================================================================
   SIGOA — Inicialización global (Fase D.1: fundación PWA / offline)
   ===================================================================
   Responsabilidad global concreta (RNF §41b / docs/SIGOA.md §53):

   * registrar el Service Worker cuando el navegador lo soporta y el
     contexto lo permite (HTTPS o localhost);
   * iniciar la capa de conectividad online/offline;
   * crear/abre la base IndexedDB local (stores preparados);
   * registrar el perfil local de la sesión (a qué cuenta pertenecen las
     copias descargadas en el dispositivo);
   * iniciar la cola de sincronización (Fase D.4 / §56), que reanuda las
     operaciones pendientes del dispositivo y registra sus disparadores.

   El orden importa: `sincronizacion.iniciar()` necesita la base local y
   la capa de conectividad ya inicializadas.

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

    /* ------------------------------------------------------------------
       Perfil local de la sesión.

       Las copias de documentación descargadas en el dispositivo pertenecen
       a la cuenta que las descargó. El perfil registra qué cuenta está
       activa para que la pantalla sin conexión solo muestre las suyas.

       Se borra al cerrar sesión (`components/sidebar.js`), de modo que
       cerrar sesión deja el dispositivo sin acceso a la documentación
       descargada. No se guarda ningún dato sensible: solo el identificador
       de usuario y su nombre.
       ------------------------------------------------------------------ */
    function registrarPerfilLocal() {
        if (!window.SIGOA || !window.SIGOA.almacenamiento) {
            return Promise.resolve(false);
        }

        if (typeof document.querySelector !== 'function') {
            return Promise.resolve(false);
        }

        var meta = document.querySelector('meta[name="sigOA-usuario-id"]');

        if (!meta) {
            return Promise.resolve(false);
        }

        var usuarioId = parseInt(meta.getAttribute('content'), 10);

        if (!usuarioId) {
            return Promise.resolve(false);
        }

        var nombre = '';
        var metaNombre = document.querySelector('meta[name="sigOA-usuario-nombre"]');

        if (metaNombre) {
            nombre = metaNombre.getAttribute('content') || '';
        }

        var almacen = window.SIGOA.almacenamiento;

        return almacen.guardar(almacen.ALMACENES.perfiles, {
            id: 'activo',
            usuario_id: usuarioId,
            usuario_nombre: nombre,
            actualizado_en: new Date().toISOString()
        }).then(function () {
            return true;
        }).catch(function (error) {
            if (window.console && console.warn) {
                console.warn('SIGOA: no se pudo registrar el perfil local de la sesión.', error);
            }

            return false;
        });
    }

    function iniciar() {
        if (window.SIGOA && window.SIGOA.conectividad) {
            window.SIGOA.conectividad.iniciar();
        }

        /* La cola espera a que la base local esté abierta: cada operación
           del store abre su propia conexión, y encadenar la inicialización
           evita dos aperturas simultáneas al arrancar. */
        var baseLista = window.SIGOA && window.SIGOA.almacenamiento
            ? window.SIGOA.almacenamiento.iniciar()
            : Promise.resolve(false);

        Promise.resolve(baseLista)
            .catch(function () {
                return false;
            })
            .then(function () {
                return registrarPerfilLocal();
            })
            .then(function () {
                if (!window.SIGOA || !window.SIGOA.sincronizacion) {
                    return;
                }

                return window.SIGOA.sincronizacion.iniciar();
            })
            .catch(function (error) {
                if (window.console && console.error) {
                    console.error('SIGOA: no se pudo iniciar la cola de sincronización.', error);
                }
            });

        registrarServiceWorker();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();