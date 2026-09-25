<?php

namespace App\Services;

/**
 * Validación y escritura física de una fotografía sincronizada (Fase D.4).
 *
 * Responsabilidades:
 *
 *  1. **Validar el archivo real** (§52.9, §56): tamaño máximo, MIME detectado
 *     por contenido (`finfo`) y decodificabilidad. Nunca se confía en
 *     `$_FILES['name']`, en el `type` declarado por el cliente ni en la
 *     extensión enviada.
 *  2. **Escribir el archivo y su thumbnail** en la estructura definitiva
 *     `OBR-XXXXXX/YYYY-MM-DD/UUID-INSPECCION/{IMAGENES,THUMBNAILS}`, con
 *     nombres físicos generados por el servidor (§52.8).
 *  3. **Generar el thumbnail en el servidor** a partir del archivo ya
 *     optimizado por el cliente: no se confía en un thumbnail enviado por el
 *     dispositivo, que podría haber sido alterado.
 *  4. **Limpiar** únicamente los archivos creados por la operación en curso,
 *     nunca archivos preexistentes de otra fotografía.
 *
 * El sistema de archivos y MariaDB no comparten transacción. El orden de
 * operaciones —validar, crear carpetas, escribir, insertar— vive en el
 * controlador; esta clase solo cubre la parte de archivos y devuelve los
 * datos ya normalizados.
 */
final class FotografiaArchivo
{
    /** Tope de 8 MB por fotografía (§52.9), también validado en el cliente. */
    public const TAMANO_MAXIMO = 8388608;

    /** Lado mayor del thumbnail generado en el servidor (§52.9). */
    public const LADO_THUMBNAIL = 400;

    /** Calidad del thumbnail generado en el servidor. */
    public const CALIDAD_THUMBNAIL = 80;

    /**
     * MIME reales aceptados → extensión canónica almacenada.
     *
     * La salida del cliente de D.2 es siempre JPEG (§54.6); se aceptan
     * además PNG y WEBP por el contrato de §52.9. HEIC/HEIF no se admiten.
     */
    private const MIME_PERMITIDOS = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    private ObraAlmacenamiento $almacenamiento;

    public function __construct(?ObraAlmacenamiento $almacenamiento = null)
    {
        $this->almacenamiento = $almacenamiento ?? new ObraAlmacenamiento();
    }

    /**
     * Inspecciona un archivo subido sin escribir nada en disco.
     *
     * @return array{ok: bool, error?: string, mensaje?: string, mime?: string,
     *               extension?: string, ancho?: int, alto?: int, tamano?: int}
     */
    public function inspeccionar(string $rutaOrigen, ?int $tamano = null): array
    {
        if ($rutaOrigen === '' || ! is_file($rutaOrigen) || ! is_readable($rutaOrigen)) {
            return $this->fallo('ARCHIVO_INVALIDO', 'No se recibió un archivo legible.');
        }

        $tamano = $tamano ?? (int) @filesize($rutaOrigen);

        if ($tamano <= 0) {
            return $this->fallo('ARCHIVO_INVALIDO', 'El archivo recibido está vacío.');
        }

        if ($tamano > self::TAMANO_MAXIMO) {
            return $this->fallo('ARCHIVO_DEMASIADO_GRANDE', 'La fotografía supera el máximo de 8 MB permitido.');
        }

        $mime = $this->detectarMime($rutaOrigen);

        if ($mime === null || ! isset(self::MIME_PERMITIDOS[$mime])) {
            return $this->fallo('MIME_NO_PERMITIDO', 'El formato de la fotografía no está permitido.');
        }

        /* La imagen debe ser decodificable: getimagesize() valida la cabecera
           real del archivo, no la extensión declarada. */
        $dimensiones = @getimagesize($rutaOrigen);

        if ($dimensiones === false
            || empty($dimensiones[0])
            || empty($dimensiones[1])
            || (int) $dimensiones[0] <= 0
            || (int) $dimensiones[1] <= 0
        ) {
            return $this->fallo('ARCHIVO_CORRUPTO', 'El archivo no es una imagen válida.');
        }

        return [
            'ok'        => true,
            'mime'      => $mime,
            'extension' => self::MIME_PERMITIDOS[$mime],
            'ancho'     => (int) $dimensiones[0],
            'alto'      => (int) $dimensiones[1],
            'tamano'    => $tamano,
        ];
    }

    /**
     * Verifica que el servidor pueda generar thumbnails (GD con los
     * códecs requeridos). Su ausencia no es un problema del cliente: se
     * reporta como error transitorio del servidor para que la fotografía
     * vuelva a la cola sin perder datos.
     */
    public function puedeGenerarThumbnail(): bool
    {
        return extension_loaded('gd')
            && function_exists('imagecreatetruecolor')
            && function_exists('imagecopyresampled')
            && function_exists('imagejpeg');
    }

    /**
     * Escribe el archivo original de una fotografía en la estructura
     * anidada de su inspección.
     *
     * @param array $datos Resultado previo de `inspeccionar()`.
     *
     * @return array{ok: bool, error?: string, mensaje?: string,
     *               ruta_relativa?: string, nombre_archivo?: string,
     *               ruta_absoluta?: string}
     */
    public function escribirImagen(
        string $rutaOrigen,
        string $codigoObra,
        string $fechaInspeccion,
        string $uuidInspeccion,
        int $inspeccionId,
        array $datos
    ): array {
        $directorio = $this->almacenamiento->directorioImagenesInspeccion(
            $codigoObra,
            $fechaInspeccion,
            $uuidInspeccion
        );

        if ($directorio === null) {
            $preparada = $this->almacenamiento->asegurarEstructuraInspeccion(
                $codigoObra,
                $fechaInspeccion,
                $uuidInspeccion
            );

            if ($preparada === null) {
                return $this->fallo('STORAGE_ERROR', 'No fue posible preparar el almacenamiento de la obra.');
            }

            $directorio = $this->almacenamiento->directorioImagenesInspeccion(
                $codigoObra,
                $fechaInspeccion,
                $uuidInspeccion
            );

            if ($directorio === null) {
                return $this->fallo('STORAGE_ERROR', 'No fue posible preparar el almacenamiento de la obra.');
            }
        }

        $nombre = $this->almacenamiento->nombreFotografia($inspeccionId, date('Ymd-His'), (string) $datos['extension']);

        if (! $this->copiarArchivo($rutaOrigen, $directorio . DIRECTORY_SEPARATOR . $nombre)) {
            return $this->fallo('STORAGE_ERROR', 'No fue posible guardar la fotografía en el servidor.');
        }

        $rutaRelativa = $this->almacenamiento->rutaRelativaImagen(
            $codigoObra,
            $fechaInspeccion,
            $uuidInspeccion,
            $nombre
        );

        if ($rutaRelativa === null) {
            @unlink($directorio . DIRECTORY_SEPARATOR . $nombre);

            return $this->fallo('STORAGE_ERROR', 'No fue posible determinar la ruta de la fotografía.');
        }

        return [
            'ok'             => true,
            'nombre_archivo' => $nombre,
            'ruta_relativa'  => $rutaRelativa,
            'ruta_absoluta'  => $directorio . DIRECTORY_SEPARATOR . $nombre,
        ];
    }

    /**
     * Genera y escribe el thumbnail de una fotografía ya escrita.
     *
     * El thumbnail se produce en el servidor a partir del archivo original
     * recién escrito, con lado mayor acotado a 400 px y salida JPEG. No se
     * acepta un thumbnail enviado por el cliente: la consistencia del
     *thumbnail queda garantizada por el servidor.
     *
     * @return array{ok: bool, error?: string, mensaje?: string,
     *               ruta_thumbnail?: string, nombre_thumbnail?: string,
     *               ruta_absoluta?: string}
     */
    public function escribirThumbnail(
        string $rutaImagen,
        string $codigoObra,
        string $fechaInspeccion,
        string $uuidInspeccion,
        int $inspeccionId
    ): array {
        if (! $this->puedeGenerarThumbnail()) {
            return $this->fallo('STORAGE_ERROR', 'El servidor no puede generar la miniatura de la fotografía.');
        }

        $directorio = $this->almacenamiento->directorioThumbnailsInspeccion(
            $codigoObra,
            $fechaInspeccion,
            $uuidInspeccion
        );

        if ($directorio === null) {
            $preparada = $this->almacenamiento->asegurarEstructuraInspeccion(
                $codigoObra,
                $fechaInspeccion,
                $uuidInspeccion
            );

            if ($preparada === null) {
                return $this->fallo('STORAGE_ERROR', 'No fue posible preparar el almacenamiento de la obra.');
            }

            $directorio = $this->almacenamiento->directorioThumbnailsInspeccion(
                $codigoObra,
                $fechaInspeccion,
                $uuidInspeccion
            );

            if ($directorio === null) {
                return $this->fallo('STORAGE_ERROR', 'No fue posible preparar el almacenamiento de la obra.');
            }
        }

        $nombre = $this->almacenamiento->nombreThumbnailFotografia($inspeccionId, date('Ymd-His'));
        $destino = $directorio . DIRECTORY_SEPARATOR . $nombre;

        if (! $this->generarThumbnail($rutaImagen, $destino)) {
            return $this->fallo('STORAGE_ERROR', 'No fue posible generar la miniatura de la fotografía.');
        }

        $rutaRelativa = $this->almacenamiento->rutaRelativaThumbnail(
            $codigoObra,
            $fechaInspeccion,
            $uuidInspeccion,
            $nombre
        );

        if ($rutaRelativa === null) {
            @unlink($destino);

            return $this->fallo('STORAGE_ERROR', 'No fue posible determinar la ruta de la miniatura.');
        }

        return [
            'ok'               => true,
            'nombre_thumbnail' => $nombre,
            'ruta_thumbnail'   => $rutaRelativa,
            'ruta_absoluta'    => $destino,
        ];
    }

    /**
     * Elimina un archivo a partir de su ruta relativa dentro de la raíz.
     *
     * Se usa únicamente para limpiar archivos creados por la operación en
     * curso cuando falla la escritura en base de datos. La ruta se vuelve a
     * validar y se exige que el archivo esté dentro de la raíz configurada,
     * por lo que nunca alcanza archivos ajenos.
     */
    public function eliminarRelativo(?string $rutaRelativa): void
    {
        if ($rutaRelativa === null || $rutaRelativa === '') {
            return;
        }

        $absoluta = $this->almacenamiento->absolutoDesdeRelativa($rutaRelativa);

        if ($absoluta === null) {
            return;
        }

        $raiz = $this->almacenamiento->raiz();

        if ($raiz === '' || ! str_starts_with($absoluta, $raiz . DIRECTORY_SEPARATOR)) {
            return;
        }

        if (is_file($absoluta)) {
            @unlink($absoluta);
        }
    }

    /**
     * Indica si el archivo físico de una ruta relativa existe.
     */
    public function existeRelativo(?string $rutaRelativa): bool
    {
        if ($rutaRelativa === null || $rutaRelativa === '') {
            return false;
        }

        $absoluta = $this->almacenamiento->absolutoDesdeRelativa($rutaRelativa);

        return $absoluta !== null && is_file($absoluta);
    }

    /**
     * Reescribe el archivo de una ruta relativa ya registrada en la base de
     * datos.
     *
     * Se usa únicamente para **reparar** una fila existente cuyo archivo
     * físico se perdió (§56). El nombre y el directorio los decidió el
     * servidor cuando la fila se creó, y la ruta se revalida contra la raíz,
     * por lo que solo se sobrescribe el archivo de esa misma fotografía: nunca
     * uno ajeno ni se altera la referencia almacenada.
     */
    public function repararArchivo(string $rutaOrigen, string $rutaRelativa): bool
    {
        $absoluta = $this->preparable($rutaRelativa);

        if ($absoluta === null) {
            return false;
        }

        return $this->copiarArchivo($rutaOrigen, $absoluta);
    }

    /**
     * Regenera el thumbnail de una ruta relativa ya registrada en la base de
     * datos, aplicando la misma regla que en la escritura original: lado
     * mayor acotado a 400 px y salida JPEG.
     */
    public function repararThumbnail(string $rutaOrigen, string $rutaRelativa): bool
    {
        $absoluta = $this->preparable($rutaRelativa);

        if ($absoluta === null || ! $this->puedeGenerarThumbnail()) {
            return false;
        }

        return $this->generarThumbnail($rutaOrigen, $absoluta);
    }

    /**
     * Resuelve una ruta relativa a un destino escribible dentro de la raíz.
     */
    private function preparable(string $rutaRelativa): ?string
    {
        $absoluta = $this->almacenamiento->absolutoDesdeRelativa($rutaRelativa);

        if ($absoluta === null) {
            return null;
        }

        $directorio = dirname($absoluta);

        if (! is_dir($directorio) || ! is_writable($directorio)) {
            return null;
        }

        return $absoluta;
    }

    /**
     * MIME detectado por el contenido real del archivo.
     */
    private function detectarMime(string $ruta): ?string
    {
        if (! function_exists('finfo_open')) {
            return null;
        }

        $finfo = @finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            return null;
        }

        $mime = @finfo_file($finfo, $ruta);
        finfo_close($finfo);

        return is_string($mime) ? strtolower(trim($mime)) : null;
    }

    /**
     * Copia el archivo temporal al destino definitivo.
     *
     * Se utiliza copia por flujo en lugar de `move_uploaded_file()`: la ruta
     * de origen proviene siempre de `$_FILES`, que solo el SAPI puede
     * completar, y nunca de un valor enviado por el cliente. La copia por
     * flujo además permite validar y limpiar sin consumir el temporal, y
     * mantiene el endpoint verificable de punta a punta en las pruebas de
     * integración.
     */
    private function copiarArchivo(string $origen, string $destino): bool
    {
        $entrada = @fopen($origen, 'rb');

        if ($entrada === false) {
            return false;
        }

        $salida = @fopen($destino, 'wb');

        if ($salida === false) {
            fclose($entrada);

            return false;
        }

        $copiado = stream_copy_to_stream($entrada, $salida);

        fclose($entrada);
        fclose($salida);

        if ($copiado === false) {
            @unlink($destino);

            return false;
        }

        return true;
    }

    /**
     * Genera el thumbnail reescalando la imagen con GD.
     */
    private function generarThumbnail(string $rutaImagen, string $destino): bool
    {
        $info = @getimagesize($rutaImagen);

        if ($info === false || empty($info[0]) || empty($info[1])) {
            return false;
        }

        $ancho = (int) $info[0];
        $alto  = (int) $info[1];

        $origen = $this->recursoGd($rutaImagen, (string) ($info['mime'] ?? ''));

        if ($origen === null) {
            return false;
        }

        $escala = min(1, self::LADO_THUMBNAIL / max($ancho, $alto));
        $nuevoAncho = max(1, (int) round($ancho * $escala));
        $nuevoAlto  = max(1, (int) round($alto * $escala));

        $destinoRecurso = imagecreatetruecolor($nuevoAncho, $nuevoAlto);

        if ($destinoRecurso === false) {
            imagedestroy($origen);

            return false;
        }

        /* Fondo blanco: los PNG con canal alfa quedarían negros sobre fondo
           negro al convertirlos a JPEG. */
        $blanco = imagecolorallocate($destinoRecurso, 255, 255, 255);
        imagefilledrectangle($destinoRecurso, 0, 0, $nuevoAncho, $nuevoAlto, $blanco);

        $ok = imagecopyresampled(
            $destinoRecurso,
            $origen,
            0,
            0,
            0,
            0,
            $nuevoAncho,
            $nuevoAlto,
            $ancho,
            $alto
        );

        $escrito = $ok ? imagejpeg($destinoRecurso, $destino, self::CALIDAD_THUMBNAIL) : false;

        imagedestroy($origen);
        imagedestroy($destinoRecurso);

        if (! $escrito) {
            @unlink($destino);

            return false;
        }

        return true;
    }

    /**
     * Carga la imagen en un recurso GD según su MIME real.
     */
    private function recursoGd(string $ruta, string $mime)
    {
        $recurso = null;

        switch ($mime) {
            case 'image/jpeg':
                $recurso = function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($ruta) : null;
                break;

            case 'image/png':
                $recurso = function_exists('imagecreatefrompng') ? @imagecreatefrompng($ruta) : null;
                break;

            case 'image/webp':
                $recurso = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($ruta) : null;
                break;
        }

        return $recurso === false ? null : $recurso;
    }

    /**
     * Resultado de error uniforme de esta clase.
     */
    private function fallo(string $error, string $mensaje): array
    {
        return ['ok' => false, 'error' => $error, 'mensaje' => $mensaje];
    }
}
