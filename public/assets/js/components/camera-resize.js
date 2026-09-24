/* ===================================================================
   SIGOA — Procesamiento de fotografías en el cliente (Fase D.2 / F.8)
   ===================================================================
   Optimiza cada fotografía ANTES de guardarla en IndexedDB:

   * decodifica el Blob/File (with EXIF orientation, incluido iOS);
   * calcula las dimensiones;
   * redimensiona si el lado mayor supera 2560 px;
   * convierte a JPEG con calidad objetivo aproximada 80–85 %;
   * genera un thumbnail independiente de 400 px en el lado mayor.

   El archivo original de la cámara NO se conserva: solo se almacenan el
   Blob optimizado y el thumbnail (decisión §52.9 / D.2 punto 10, la
   fotografía original no se persiste).

   API expuesta: `SIGOA.imagenes.optimizar(blob) -> Promise< {...} >`
   APIs web estándar (canvas, createImageBitmap/Image, toBlob)
   compatibles con Android + Chrome e iPhone + Safari (§52.20).

   NO se usa getUserMedia mediante stream propietario: la captura se
   resuelve con <input type="file" accept="image/*" capture>.
   =================================================================== */

(function () {
    'use strict';

    var LIMITE_LADO_FOTO = 2560;
    var LIMITE_LADO_THUMBNAIL = 400;
    var CALIDAD_FOTO = 0.82;
    var CALIDAD_THUMBNAIL = 0.8;
    var TIPO_JPEG = 'image/jpeg';
    var EXTENSION_JPEG = 'jpg';

    function decodificar(blob) {
        /* createImageBitmap aplica la orientación EXIF por defecto
           (imageOrientation: 'from-image'); si no está disponible se
           recurre a <img>, cuyo renderizado también respeta EXIF en los
           navegadores modernos. */
        if (typeof createImageBitmap === 'function') {
            return createImageBitmap(blob, { imageOrientation: 'from-image' })
                .catch(function () {
                    return decodificarConImagen(blob);
                });
        }

        return decodificarConImagen(blob);
    }

    function decodificarConImagen(blob) {
        return new Promise(function (resolver, rechazar) {
            var url = URL.createObjectURL(blob);
            var imagen = new Image();

            imagen.onload = function () {
                URL.revokeObjectURL(url);
                resolver(imagen);
            };

            imagen.onerror = function () {
                URL.revokeObjectURL(url);
                rechazar(new Error('No fue posible decodificar la fotografía seleccionada.'));
            };

            imagen.src = url;
        });
    }

    function dimensiones(origen) {
        var ancho = origen.width || origen.naturalWidth || 0;
        var alto  = origen.height || origen.naturalHeight || 0;

        if (ancho <= 0 || alto <= 0 || !isFinite(ancho) || !isFinite(alto)) {
            throw new Error('La fotografía seleccionada no tiene dimensiones válidas.');
        }

        return { ancho: ancho, alto: alto };
    }

    function dibujarRedimensionada(origen, ladoMaximo) {
        var original = dimensiones(origen);
        var escala   = Math.min(1, ladoMaximo / Math.max(original.ancho, original.alto));

        var canvas = document.createElement('canvas');
        canvas.width  = Math.max(1, Math.round(original.ancho * escala));
        canvas.height = Math.max(1, Math.round(original.alto * escala));

        canvas.getContext('2d').drawImage(origen, 0, 0, canvas.width, canvas.height);

        return canvas;
    }

    function canvasABlob(canvas, calidad) {
        return new Promise(function (resolver, rechazar) {
            canvas.toBlob(function (blob) {
                if (blob) {
                    resolver(blob);
                } else {
                    rechazar(new Error('No fue posible codificar la fotografía como JPEG.'));
                }
            }, TIPO_JPEG, calidad);
        });
    }

    /**
     * Optimiza un archivo de imagen (File/Blob) y devuelve la versión
     * procesada junto con su thumbnail y la metadata de la versión final.
     *
     * @param {Blob} blob
     * @return {Promise<{blob: Blob, thumbnail: Blob, ancho: number,
     *          alto: number, tamano_bytes: number, mime_type: string,
     *          extension: string}>}
     */
    function optimizar(blob) {
        if (!blob || typeof blob.size !== 'number') {
            return Promise.reject(new Error('No se recibió una fotografía válida.'));
        }

        return decodificar(blob).then(function (origen) {
            var canvasFoto   = dibujarRedimensionada(origen, LIMITE_LADO_FOTO);
            var canvasThumb  = dibujarRedimensionada(origen, LIMITE_LADO_THUMBNAIL);

            return Promise.all([
                canvasABlob(canvasFoto, CALIDAD_FOTO),
                canvasABlob(canvasThumb, CALIDAD_THUMBNAIL)
            ]).then(function (blobs) {
                return {
                    blob:         blobs[0],
                    thumbnail:    blobs[1],
                    ancho:        canvasFoto.width,
                    alto:         canvasFoto.height,
                    tamano_bytes: blobs[0].size,
                    mime_type:    TIPO_JPEG,
                    extension:    EXTENSION_JPEG
                };
            });
        });
    }

    window.SIGOA = window.SIGOA || {};
    window.SIGOA.imagenes = {
        LIMITE_LADO_FOTO: LIMITE_LADO_FOTO,
        LIMITE_LADO_THUMBNAIL: LIMITE_LADO_THUMBNAIL,
        optimizar: optimizar
    };
})();