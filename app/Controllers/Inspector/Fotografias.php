<?php

namespace App\Controllers\Inspector;

use App\Controllers\BaseController;
use App\Libraries\Uuid;
use App\Models\FotografiaModel;
use App\Models\InspeccionModel;
use App\Models\InspectoresObrasModel;
use App\Services\ObraAlmacenamiento;

/**
 * Servidor de archivos de las fotografías de una inspección (Fase E.5).
 *
 * La raíz de almacenamiento está fuera de `public/` y en producción es un
 * recurso compartido de red, así que **no existe una URL estática** que sirva
 * estas imágenes: la raíz UNC nunca debe exponerse al navegador. Este controlador
 * es el mecanismo mínimo equivalente al patrón ya establecido en
 * `Empresas::verLogo()` (§52.19): resuelve la ruta física a partir de la
 * referencia relativa almacenada y responde el archivo con su `Content-Type`.
 *
 * Separación de responsabilidades, que es lo que sostiene la seguridad:
 *
 * 1. **Identidad técnica.** La URL lleva el `uuid` de la fotografía, no su
 *    `id`, no su nombre de archivo y no una ruta. El `uuid` no dice dónde está
 *    el archivo: solo permite localizar el registro.
 * 2. **Ruta física.** Nunca sale de la base de datos. El navegador no envía
 *    rutas, de modo que no existe una entrada por la que se pueda intentar un
 *    `path traversal`: `ruta_relativa` y `ruta_thumbnail` se toman del registro
 *    ya registrado y se resuelven contra la raíz con
 *     `ObraAlmacenamiento::absolutoDesdeRelativa()`.
 * 3. **URL de servicio.** Estas dos rutas. Ambas pasan por los filtros
 *    `auth` + `role:INSPECTOR` del grupo del inspector: no hay endpoint
 *    público de imágenes.
 *
 * La autorización no se recibe del cliente: se resuelve encadenando
 * fotografía → inspección → obra, igual que en `Inspecciones::detalle()`. Sin
 * asignación vigente del inspector sobre la obra de esa fotografía no hay
 * imagen, y la respuesta es 404 en todos los casos no servibles, para que el
 * endpoint no sirva de oráculo sobre la existencia de fotografías ajenas.
 *
 * Es de **solo lectura**: no escribe en la base, no toca el disco y no altera
 * la sincronización (§52.15). La caché histórica de fotografías no se
 * implementa en esta fase, de modo que la respuesta pide al navegador no
 * retener el archivo.
 */
class Fotografias extends BaseController
{
    /**
     * Sirve la fotografía original (`ruta_relativa`).
     *
     * La abre el usuario al seleccionar una miniatura de la galería.
     */
    public function ver(string $uuid)
    {
        $fotografia = $this->fotografiaAutorizada($uuid);

        if ($fotografia === null) {
            return $this->noDisponible();
        }

        return $this->servirImagen($fotografia->ruta_relativa ?? null);
    }

    /**
     * Sirve la miniatura (`ruta_thumbnail`).
     *
     * Es la imagen que la galería muestra en la retícula. La resolución la hace
     * el servidor al sincronizar (§52.9), por lo que el thumbnail siempre es
     * un JPEG: aquí no se reinterpreta el archivo, se sirve el que existe.
     */
    public function miniatura(string $uuid)
    {
        $fotografia = $this->fotografiaAutorizada($uuid);

        if ($fotografia === null) {
            return $this->noDisponible();
        }

        return $this->servirImagen($fotografia->ruta_thumbnail ?? null);
    }

    /**
     * Fotografía solicitada que además está autorizada para este inspector.
     *
     * La autorización se decide sobre los datos del registro, nunca sobre lo
     * que dice la URL: una fotografía de una obra ajena no es servible ni
     * siquiera si su `uuid` es correcto.
     */
    private function fotografiaAutorizada(string $uuid): ?object
    {
        $uuid = trim($uuid);

        if (! Uuid::isValid($uuid)) {
            return null;
        }

        /* Una fotografía anulada no se sirve: la anulación es lógica y el
           archivo sigue en disco, pero deja de ser parte de la inspección. */
        $fotografia = (new FotografiaModel())->findVisiblePorUuid($uuid);

        if ($fotografia === null) {
            return null;
        }

        $inspeccion = (new InspeccionModel())->findDetalle((int) $fotografia->inspeccion_id);

        if ($inspeccion === null) {
            return null;
        }

        $usuarioId = (int) session()->get('user_id');

        if (! (new InspectoresObrasModel())->esVigente((int) $inspeccion->obra_id, $usuarioId)) {
            return null;
        }

        return $fotografia;
    }

    /**
     * Responde el archivo de la ruta relativa indicada, o 404 si no es seguro.
     *
     * Son tres barreras y todas son necesarias:
     *
     * 1. `ObraAlmacenamiento::absolutoDesdeRelativa()` rechaza rutas vacías,
     *    rutas con `../`, rutas absolutas y rutas con letra de unidad;
     * 2. `realpath()` comprueba la contención real una vez resueltos los
     *    enlaces del sistema de archivos, con el separador incluido en la
     *    comparación para que una carpeta hermana de la raíz (`RAIZ-EVIL`)
     *    tampoco cuadre;
     * 3. el MIME se detecta por contenido (`finfo`) y tiene que ser una
     *    imagen: este endpoint no sirve nunca otro tipo de archivo.
     */
    private function servirImagen(?string $rutaRelativa)
    {
        if ($rutaRelativa === null || trim($rutaRelativa) === '') {
            return $this->noDisponible();
        }

        $almacenamiento = new ObraAlmacenamiento();
        $absoluta       = $almacenamiento->absolutoDesdeRelativa($rutaRelativa);

        if ($absoluta === null) {
            return $this->noDisponible();
        }

        $raizReal   = realpath($almacenamiento->raiz());
        $archivoReal = realpath($absoluta);

        if ($raizReal === false || $archivoReal === false) {
            return $this->noDisponible();
        }

        $prefijo = $raizReal . DIRECTORY_SEPARATOR;

        if (strncmp($archivoReal, $prefijo, strlen($prefijo)) !== 0) {
            return $this->noDisponible();
        }

        if (! is_file($archivoReal) || ! is_readable($archivoReal)) {
            return $this->noDisponible();
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($archivoReal);

        if ($mime === false || ! str_starts_with($mime, 'image/')) {
            return $this->noDisponible();
        }

        $contenido = file_get_contents($archivoReal);

        if ($contenido === false) {
            return $this->noDisponible();
        }

        /* Sin caché retenida: la galería histórica sin conexión es una fase
           posterior y hasta entonces el navegador no debe guardar estas
           imágenes por su cuenta.

           La cabecera se fija después de eliminar la que trae la respuesta por
           defecto: `MessageTrait::setHeader()` concatena los valores cuando la
           cabecera ya existe, y el resultado final debe ser exactamente el que
           se declara aquí. */
        return $this->response
            ->setHeader('Content-Type', $mime)
            ->setHeader('Content-Length', (string) strlen($contenido))
            ->removeHeader('Cache-Control')
            ->setHeader('Cache-Control', 'private, no-store, max-age=0, must-revalidate')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody($contenido);
    }

    /**
     * Respuesta única para todo lo que no se puede servir.
     *
     * Fotografía inexistente, anulada, de una obra ajena o con archivo no
     * legible responden igual: distinguir los casos le diría al navegador qué
     * fotografías existen en obras que no puede ver.
     */
    private function noDisponible()
    {
        return $this->response->setStatusCode(404)->setBody('');
    }
}
