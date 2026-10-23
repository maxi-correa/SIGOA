<?php

namespace App\Services;

use CodeIgniter\HTTP\ResponseInterface;

/**
 * Servidor de archivos PDF de la documentación de obra.
 *
 * Concentra la **única** forma que tiene SIGOA de entregar un PDF del
 * almacenamiento compartido a un cliente. La usan tanto la pantalla
 * administrativa (`App\Controllers\Documentacion`) como la del inspector
 * (`App\Controllers\Inspector\Documentacion`), de modo que no exista un
 * segundo mecanismo de entrega ni una segunda copia de la lógica de
 * resolución de rutas.
 *
 * Reglas que se aplican siempre, con independencia del rol:
 *
 * * la ruta relativa se resuelve contra la raíz de `ObraAlmacenamiento` y se
 *   descarta si intenta salir de ella (`absolutoDesdeRelativa()` rechaza
 *   travesías y rutas absolutas);
 * * la comprobación con separador final evita aceptar una raíz hermana que
 *   comparta prefijo (p. ej. `/storage-otra`);
 * * nunca se expone la ruta física del servidor: el cliente recibe el
 *   contenido del archivo, no su ubicación en disco.
 *
 * @see \App\Services\ObraAlmacenamiento
 */
class DocumentoArchivo
{
    private ObraAlmacenamiento $almacenamiento;

    public function __construct(?ObraAlmacenamiento $almacenamiento = null)
    {
        $this->almacenamiento = $almacenamiento ?? new ObraAlmacenamiento();
    }

    /**
     * Resuelve la ruta relativa de un documento contra la raíz de
     * almacenamiento y la devuelve solo si el archivo existe y queda
     * realmente dentro de la raíz autorizada.
     */
    public function rutaAutorizada(string $rutaRelativa): ?string
    {
        $ruta = $this->rutaNormalizada($rutaRelativa);

        if ($ruta === null || ! is_file($ruta)) {
            return null;
        }

        return $ruta;
    }

    /**
     * Resuelve la ruta relativa de un documento dentro de la raíz
     * autorizada, **sin exigir que el archivo exista**.
     *
     * La distinción importa para el cliente: una ruta no autorizada es un
     * error de autorización (403) y un archivo que ya no está en el
     * almacenamiento es un recurso ausente (404). Resolver ambos casos por
     * separado evita responder «acceso denegado» a un documento que el
     * usuario sí puede ver pero cuyo PDF se retiró del servidor.
     *
     * La contención en la raíz se garantiza de dos formas: la normalización
     * léxica descarta cualquier segmento `..` que intente salir de la raíz,
     * y cuando la ruta existe se comprueba además con `realpath()`, que
     * resuelve enlaces simbólicos.
     */
    public function rutaNormalizada(string $rutaRelativa): ?string
    {
        if ($this->almacenamiento->absolutoDesdeRelativa($rutaRelativa) === null) {
            return null;
        }

        $raiz = rtrim($this->almacenamiento->raiz(), '/\\');

        if ($raiz === '') {
            return null;
        }

        $segmentos = [];

        foreach (explode('/', str_replace('\\', '/', $rutaRelativa)) as $segmento) {
            if ($segmento === '' || $segmento === '.') {
                continue;
            }

            if ($segmento === '..') {
                if ($segmentos === []) {
                    return null;
                }

                array_pop($segmentos);

                continue;
            }

            $segmentos[] = $segmento;
        }

        if ($segmentos === []) {
            return null;
        }

        $ruta = $raiz . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segmentos);

        $rutaNorm = realpath($ruta);

        if ($rutaNorm === false) {
            /* El archivo no existe todavía: la normalización léxica ya
               garantiza que la ruta queda dentro de la raíz. */
            return $ruta;
        }

        $raizNorm = realpath($raiz);

        if ($raizNorm === false) {
            return null;
        }

        $prefijo = rtrim($raizNorm, '/\\') . DIRECTORY_SEPARATOR;

        if ($rutaNorm !== $raizNorm && strpos($rutaNorm, $prefijo) !== 0) {
            return null;
        }

        return $rutaNorm;
    }

    /**
     * Entrega el PDF de un documento.
     *
     * @param object $documento Registro de `documentos` con `ruta_relativa`
     *                           y `nombre_archivo_original`.
     *
     * @return ResponseInterface 403 si la ruta no está autorizada, 404 si el
     *                           archivo no existe, 200 con el PDF en caso
     *                           contrario.
     */
    public function servirPdf(object $documento): ResponseInterface
    {
        $ruta = $this->rutaNormalizada((string) ($documento->ruta_relativa ?? ''));

        if ($ruta === null) {
            return service('response')->setStatusCode(403)->setBody('Acceso denegado');
        }

        if (! is_file($ruta)) {
            return service('response')->setStatusCode(404)->setBody('Archivo no encontrado');
        }

        $mime = mime_content_type($ruta) ?: 'application/pdf';

        $nombre = (string) ($documento->nombre_archivo_original ?? 'documento.pdf');

        return service('response')
            ->setHeader('Content-Type', $mime)
            ->setHeader('Content-Disposition', 'inline; filename="' . $nombre . '"')
            ->setBody((string) file_get_contents($ruta));
    }
}
