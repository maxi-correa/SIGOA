/* ===================================================================
   SIGOA — Service Worker (Fase D.1: fundación PWA / app shell)
   ===================================================================
   Estrategia deliberadamente simple:

   * Precache del app shell (recursos estáticos de /assets/, manifest,
     robots.txt y favicon) al instalar.
   * GET sobre recursos estáticos → cache-first con fallback a red.
   * Todo lo demás (páginas HTML, APIs, login, sincronización futura,
     respuestas con sesión) NO se intercepta ni se cachea.

   NO se almacenan:
   * cookies de sesión (ci_session);
   * tokens CSRF;
   * contraseñas;
   * páginas autenticadas ni respuestas JSON privadas/dinámicas.

   NO hay Background Sync ni cola de operaciones: eso es Fase D.x.

   ACTUALIZACIÓN DE VERSIÓN: al modificar cualquier asset del app shell
   hay que incrementar CACHE_VERSION para no quedar con assets viejos.
   =================================================================== */

'use strict';

var CACHE_VERSION = 'sigoa-shell-v2';
var SHELL_CACHE = 'sigoa-shell-v2';

var PREFIJO_ESTATICO = '/assets/';

var SHELL_ASSETS = [
    '/manifest.json',
    '/robots.txt',
    '/favicon.ico',
    '/assets/css/app.css',
    '/assets/css/components/alerts.css',
    '/assets/css/components/badges.css',
    '/assets/css/components/buttons.css',
    '/assets/css/components/connectivity.css',
    '/assets/css/components/estados.css',
    '/assets/css/components/forms.css',
    '/assets/css/components/modal.css',
    '/assets/css/components/navbar.css',
    '/assets/css/components/sidebar.css',
    '/assets/css/components/tables.css',
    '/assets/css/pages/inspector-dashboard.css',
    '/assets/css/pages/inspector-obra.css',
    '/assets/css/pages/inspeccion-nueva.css',
    '/assets/fonts/inter/fonts.css',
    '/assets/fonts/inter/Inter-Regular.woff2',
    '/assets/fonts/inter/Inter-Medium.woff2',
    '/assets/fonts/inter/Inter-SemiBold.woff2',
    '/assets/fonts/inter/Inter-Bold.woff2',
    '/assets/vendor/bootstrap-icons/bootstrap-icons.min.css',
    '/assets/vendor/bootstrap-icons/fonts/bootstrap-icons.woff',
    '/assets/vendor/bootstrap-icons/fonts/bootstrap-icons.woff2',
    '/assets/js/components/sidebar.js',
    '/assets/js/components/uuid.js',
    '/assets/js/components/indexeddb.js',
    '/assets/js/components/connectivity.js',
    '/assets/js/components/camera-resize.js',
    '/assets/js/pages/inspeccion-nueva.js',
    '/assets/js/pages/obra-inspecciones.js',
    '/assets/js/app.js'
];

function abs(ruta) {
    return self.location.origin + ruta;
}

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(SHELL_CACHE).then(function (cache) {
            return cache.addAll(SHELL_ASSETS.map(abs));
        })
    );
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (nombres) {
            var obsoletos = nombres.filter(function (nombre) {
                return nombre.indexOf('sigoa-shell-') === 0 && nombre !== SHELL_CACHE;
            });

            return Promise.all(obsoletos.map(function (nombre) {
                return caches.delete(nombre);
            }));
        }).then(function () {
            return self.clients.claim();
        })
    );
});

function esRecursoEstatico(url) {
    if (url.origin !== self.location.origin) {
        return false;
    }

    return url.pathname.indexOf(PREFIJO_ESTATICO) === 0
        || url.pathname === '/manifest.json'
        || url.pathname === '/robots.txt'
        || url.pathname === '/favicon.ico';
}

self.addEventListener('fetch', function (event) {
    var request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    var url;

    try {
        url = new URL(request.url);
    } catch (error) {
        return;
    }

    if (!esRecursoEstatico(url)) {
        return;
    }

    event.respondWith(
        caches.match(request).then(function (respuestaCache) {
            if (respuestaCache) {
                return respuestaCache;
            }

            return fetch(request).then(function (respuesta) {
                var cacheable = respuesta
                    && respuesta.ok
                    && respuesta.type === 'basic'
                    && !respuesta.headers.has('set-cookie');

                if (cacheable) {
                    var copia = respuesta.clone();
                    caches.open(SHELL_CACHE).then(function (cache) {
                        cache.put(request, copia);
                    });
                }

                return respuesta;
            });
        })
    );
});
