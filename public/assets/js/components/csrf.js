/* ===================================================================
   SIGOA — Token CSRF vigente (Fase D.6.3 / docs/SIGOA.md §61, sobre §52.7)
   ===================================================================
   Exposición: `SIGOA.csrf`.

   Este componente es el ÚNICO origen del token CSRF que el cliente envía en
   las peticiones `fetch()`. No hace peticiones, no guarda nada y no sabe
   nada de la cola: solo resuelve qué token usar ahora mismo.

   -------------------------------------------------------------------
   Por qué existe
   -------------------------------------------------------------------
   `Config\Security::$regenerate = true` hace que cada petición que supera la
   verificación genere un token nuevo. El servidor reescribe entonces la
   cookie `csrf_cookie_name`, pero el `<meta name="X-CSRF-TOKEN">` que emite
   `layouts/auth.php` es una foto del token en el momento de cargar la página
   y el navegador no lo actualiza.

   Un cliente que solo leyera el `<meta>` por tanto quedaría atascado: la
   primera fotografía subiría y las siguientes recibirían 403 `CSRF_INVALID`,
   una por recarga. Por eso el token se resuelve en cada envío y nunca se
   trata como un valor fijo.

   -------------------------------------------------------------------
   Orden de resolución
   -------------------------------------------------------------------
   1. **Cookie `csrf_cookie_name`.** Es el valor contra el que el servidor
      acaba de comparar (`csrfProtection = cookie`) y es el que la propia
      respuesta renueva. Es además el único canal compartido entre pestañas,
      así que no se queda obsoleto si otra pestaña sincroniza antes.
   2. **Cabecera `X-CSRF-TOKEN` de la última respuesta API.** La publica
      `App\Filters\CsrfApi::after()` con el token vigente tras regenerarlo.
      Es un canal explícito, independiente de la configuración de cookies
      (sigue funcionando con `httponly = true` o si el navegador bloquea la
      escritura) y no duplica el token en el DOM ni en el cuerpo JSON.
   3. **`<meta name="X-CSRF-TOKEN">` del layout.** Solo es la foto del token
      al cargar la página: sirve de último recurso antes de la primera
      respuesta API, cuando todavía no hubo ninguna rotación.

   El token resuelto se conserva **solo en memoria**. Nunca se escribe en
   IndexedDB, ni en la cola, ni en `localStorage`/`sessionStorage`: no forma
   parte de la operación persistida (§52.6, §52.7).

   -------------------------------------------------------------------
   Uso
   -------------------------------------------------------------------
       headers: { 'X-CSRF-TOKEN': SIGOA.csrf.token() }
       ...
       fetch(...).then(function (respuesta) {
           SIGOA.csrf.actualizar(respuesta);   // antes de leer el cuerpo
           ...
       });

   `actualizar()` es idempotente y se puede llamar con cualquier respuesta.
   Si la respuesta no trae la cabecera —un 403 `CSRF_INVALID`, o un endpoint
   que no pasa por `CsrfApi`— descarta el valor memorizado y la siguiente
   resolución vuelve a mirar la cookie y el `<meta>`, que es exactamente lo
   que se necesita cuando la cookie había vencido (§57.1.1).

   No hay ninguna lógica de reintento aquí: decidir qué hacer con un token
   inválido corresponde al sincronizador, que pausa la operación sin
   consumir intentos (§56.6).
   =================================================================== */

(function () {
    'use strict';

    /* Nombres fijados por `Config\Security` y `Config\Cookie`. */
    var CABECERA       = 'X-CSRF-TOKEN';
    var SELECTOR_META  = 'meta[name="X-CSRF-TOKEN"]';
    var PREFIJO_COOKIE = 'csrf_cookie_name=';

    /* Token recibido en la última respuesta API. Memoria únicamente: se
       pierde al recargar, que es lo correcto para un valor rotado. */
    var deLaRespuesta = null;

    /* ------------------------------------------------------------------
       Fuentes
       ------------------------------------------------------------------ */

    function tokenEnMeta() {
        if (typeof document === 'undefined' || typeof document.querySelector !== 'function') {
            return '';
        }

        var meta = document.querySelector(SELECTOR_META);

        if (!meta || typeof meta.getAttribute !== 'function') {
            return '';
        }

        return meta.getAttribute('content') || '';
    }

    function tokenEnCookie() {
        if (typeof document === 'undefined' || !document.cookie) {
            return '';
        }

        var cookies = String(document.cookie).split(';');

        for (var i = 0; i < cookies.length; i++) {
            var par = cookies[i].trim();

            if (par.indexOf(PREFIJO_COOKIE) === 0) {
                return par.slice(PREFIJO_COOKIE.length);
            }
        }

        return '';
    }

    function tokenDeCabecera(respuesta) {
        if (!respuesta || !respuesta.headers || typeof respuesta.headers.get !== 'function') {
            return '';
        }

        return respuesta.headers.get(CABECERA) || '';
    }

    /* ==================================================================
       API pública
       ================================================================== */

    /**
     * Token CSRF vigente para la próxima petición.
     *
     * @returns {string} Cadena vacía si ninguna fuente está disponible; en ese
     *                  caso el servidor responderá 403 y el sincronizador
     *                  pausará la cola como ante cualquier token inválido.
     */
    function token() {
        var deCookie = tokenEnCookie();

        if (deCookie) {
            return deCookie;
        }

        if (deLaRespuesta) {
            return deLaRespuesta;
        }

        return tokenEnMeta();
    }

    /**
     * Registra el token que una respuesta deja vigente.
     *
     * Debe llamarse antes de leer el cuerpo: la cabecera y el JSON son
     * excluyentes en el mismo objeto de respuesta.
     *
     * @param  {Response|null} respuesta Respuesta de la petición realizada.
     * @return {boolean} `true` si la respuesta trajo el token renovado.
     */
    function actualizar(respuesta) {
        var recibido = tokenDeCabecera(respuesta);

        if (recibido) {
            deLaRespuesta = recibido;

            return true;
        }

        /* Sin cabecera, el valor memorizado puede estar viejo: se descarta
           para que la próxima resolución vuelva a mirar la cookie y el
           `<meta>`, que es lo que se renueva. */
        deLaRespuesta = null;

        return false;
    }

    window.SIGOA = window.SIGOA || {};
    window.SIGOA.csrf = {
        token:      token,
        actualizar: actualizar,

        /* Constantes, para las pruebas y para cualquier consumidor. */
        CABECERA:       CABECERA,
        SELECTOR_META:  SELECTOR_META,
        PREFIJO_COOKIE: PREFIJO_COOKIE
    };
})();