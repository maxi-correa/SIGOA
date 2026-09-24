/* ===================================================================
   SIGOA — UUID v4 en el cliente (Fase C / F.2 / §52.3 / Fase D.1)
   ===================================================================
   El UUID es la identidad estable de sincronización de inspecciones y
   fotografías. Se genera en el cliente:

   * vía `crypto.randomUUID()` cuando está disponible (Chrome ≥ 92,
     Safari ≥ 15.4, contexto seguro);
   * con fallback RFC 4122 v4 sobre `crypto.getRandomValues` para
     navegadores que aún no exponen `randomUUID`.

   No se usa SHA-256, timestamps ni ids autoincrementales como identidad.
   =================================================================== */

(function () {
    'use strict';

    var PATRON = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

    function cryptoWeb() {
        return (typeof window !== 'undefined' && window.crypto) ? window.crypto : null;
    }

    function v4ConGetRandomValues() {
        var crypto = cryptoWeb();

        if (!crypto || typeof crypto.getRandomValues !== 'function') {
            throw new Error('SIGOA: no hay fuente criptográfica segura para generar el UUID.');
        }

        var bytes = new Uint8Array(16);
        crypto.getRandomValues(bytes);

        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        bytes[8] = (bytes[8] & 0x3f) | 0x80;

        var hex = '';
        for (var i = 0; i < 16; i++) {
            hex += (bytes[i] < 16 ? '0' : '') + bytes[i].toString(16);
        }

        return hex.substring(0, 8) + '-' + hex.substring(8, 12) + '-' + hex.substring(12, 16)
            + '-' + hex.substring(16, 20) + '-' + hex.substring(20, 32);
    }

    function v4() {
        var crypto = cryptoWeb();

        if (crypto && typeof crypto.randomUUID === 'function') {
            return crypto.randomUUID().toLowerCase();
        }

        return v4ConGetRandomValues();
    }

    function esValido(valor) {
        return typeof valor === 'string' && PATRON.test(valor);
    }

    window.SIGOA = window.SIGOA || {};
    window.SIGOA.uuid = {
        v4: v4,
        esValido: esValido
    };
})();