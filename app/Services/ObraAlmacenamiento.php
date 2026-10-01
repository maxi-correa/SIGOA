<?php

namespace App\Services;

/**
 * Almacenamiento físico de archivos de una obra.
 *
 * Organiza el almacenamiento de fotografías por obra bajo la raíz
 * configurada en Config\SigoaStorage. La estructura de una obra es únicamente
 * su carpeta, y las fotografías de cada inspección se organizan con la
 * estructura anidada definitiva (Fase E.2, §52.8):
 *
 *   <RAIZ_SIGOA>/
 *   └── OBR-000001/
 *       └── 2026-09-30/
 *           ├── 11-14-00/
 *           │   ├── IMAGENES/
 *           │   └── THUMBNAILS/
 *           └── 11-14-00-2/
 *               ├── IMAGENES/
 *               └── THUMBNAILS/
 *
 * `asegurarEstructuraObra()` crea solo la carpeta de la obra y se apoya en el
 * llamador `Inspector\Obras::ver()`. `asegurarEstructuraInspeccion()` agrega
 * los niveles fecha + nombre de inspección e IMAGENES/THUMBNAILS.
 *
 * El esquema anterior `OBR-XXXXXX/{IMAGENES,THUMBNAILS}`, que solo servía como
 * estructura base, fue retirado en la Fase E.1.2 junto con las carpetas vacías
 * que dejaba en el almacenamiento. Las carpetas IMAGENES y THUMBNAILS existen
 * únicamente dentro de la carpeta de una inspección.
 *
 * Desde la Fase E.2 el nombre de la carpeta de inspección se deriva
 * exclusivamente de `inspecciones.fecha_inspeccion` e
 * `inspecciones.hora_inspeccion` (más un sufijo ordinal ante colisiones de
 * fecha+hora dentro de la misma obra). El UUID de la inspección es su
 * identidad técnica y de sincronización, pero nunca forma parte del nombre
 * de una carpeta física.
 *
 * La raíz se resuelve desde SIGOA_STORAGE_PATH. Los nombres de carpetas se
 * derivan de `obras.codigo`, de la fecha, de la hora y del sufijo, todos
 * validados con formatos internos estrictos antes de usarse, lo que impide
 * traversal de rutas.
 *
 * En la base de datos solo se almacenan referencias relativas; nunca
 * rutas absolutas (misma convención que `empresas.ruta_logo`).
 */
class ObraAlmacenamiento
{
    /** Carpeta de fotografías originales dentro de cada inspección. */
    public const DIR_IMAGENES = 'IMAGENES';

    /** Carpeta de miniaturas dentro de cada inspección. */
    public const DIR_THUMBNAILS = 'THUMBNAILS';

    /** Patrón de la carpeta de fecha (`YYYY-MM-DD`) dentro de una obra. */
    private const PATRON_FECHA = '/\A\d{4}-\d{2}-\d{2}\z/';

    /** Patrón de hora HH:MM:SS. */
    private const PATRON_HORA = '/\A(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d\z/';

    /**
     * Patrón del nombre físico de la carpeta de una inspección: `HH-MM-SS`
     * (o la medianoche `00-00-00`), `SIN-HORA`, y ambos con sufijo ordinal
     * opcional (`-2`, `-3`, …). El sufijo es un entero positivo sin ceros a
     * la izquierda: nunca se produce `-0` ni `-01`.
     *
     * Es la barrera anti-traversal del nombre de carpeta: aunque el nombre lo
     * compone el propio servicio, se revalida antes de usarlo en una ruta.
     */
    private const PATRON_CARPETA_INSPECCION = '/\A(?:(?:[01]\d|2[0-3])-[0-5]\d-[0-5]\d|SIN-HORA)(?:-[1-9]\d{0,8})?\z/';

    /** Longitud de la trama aleatoria del nombre físico de una fotografía. */
    private const LONGITUD_TRAMA = 6;

    /** Permisos utilizados al crear directorios. */
    private const PERMISOS_DIRECTORIO = 0755;

    private string $raiz;

    /**
     * @param string|null $raiz Sobreescritura de la raíz (para pruebas).
     */
    public function __construct(?string $raiz = null)
    {
        $this->raiz = rtrim($raiz ?? (string) config('SigoaStorage')->storagePath, '/\\');
    }

    /**
     * Raíz física de almacenamiento configurada.
     */
    public function raiz(): string
    {
        return $this->raiz;
    }

    /**
     * Valida y normaliza el código interno de una obra para usarse
     * como nombre de carpeta.
     *
     * Acepta únicamente el formato `OBR-XXXXXX`. Devuelve null si el
     * código no cumple el formato, lo que impide el uso de rutas
     * arbitrarias o componentes de traversal.
     */
    public function normalizarCodigo(string $codigo): ?string
    {
        $codigo = mb_strtoupper(trim($codigo));

        return preg_match('/^OBR-\d{6}$/', $codigo) === 1 ? $codigo : null;
    }

    /**
     * Verifica que la raíz esté configurada, la crea si no existe y
     * comprueba que sea escriturable.
     */
    public function asegurarRaiz(): bool
    {
        if ($this->raiz === '') {
            return false;
        }

        if (! is_dir($this->raiz)) {
            @mkdir($this->raiz, self::PERMISOS_DIRECTORIO, true);

            if (! is_dir($this->raiz)) {
                return false;
            }
        }

        return is_writable($this->raiz);
    }

    /**
     * Crea automáticamente la carpeta base de una obra: raíz y `OBR-XXXXXX`.
     *
     * No crea las carpetas `IMAGENES` y `THUMBNAILS` de nivel de obra: desde la
     * Fase E.1.2 ese esquema se retiró porque solo dejaba carpetas vacías en el
     * almacenamiento. Los subdirectorios se crean por inspección, con fecha y
     * hora, mediante `asegurarEstructuraInspeccion()`.
     *
     * La operación es idempotente: si la carpeta ya existe solo verifica que
     * esté escriturable.
     */
    public function asegurarEstructuraObra(string $codigo): bool
    {
        $codigo = $this->normalizarCodigo($codigo);

        if ($codigo === null || ! $this->asegurarRaiz()) {
            return false;
        }

        $directorio = $this->raiz . DIRECTORY_SEPARATOR . $codigo;

        if (! is_dir($directorio)) {
            @mkdir($directorio, self::PERMISOS_DIRECTORIO, true);

            if (! is_dir($directorio)) {
                return false;
            }
        }

        return is_writable($directorio);
    }

    /**
     * Referencia relativa de la obra dentro de la raíz (`OBR-XXXXXX`),
     * o null si el código no es válido.
     */
    public function rutaRelativaObra(string $codigo): ?string
    {
        return $this->normalizarCodigo($codigo);
    }

    /* ==================================================================
       Estructura anidada de una inspección (Fase D.4 / §52.8, E.2)

       OBR-XXXXXX / YYYY-MM-DD / NOMBRE-INSPECCION / {IMAGENES,THUMBNAILS}
       ================================================================== */

    /**
     * Valida y normaliza la fecha de la inspección para usarla como nombre
     * de carpeta (`YYYY-MM-DD`).
     *
     * Verifica además que la fecha exista en el calendario: un `2026-02-31`
     * sintácticamente válido se rechaza para no crear carpetas imposibles.
     */
    public function normalizarFecha(string $fecha): ?string
    {
        $fecha = trim($fecha);

        if (preg_match(self::PATRON_FECHA, $fecha) !== 1) {
            return null;
        }

        [$anio, $mes, $dia] = array_map('intval', explode('-', $fecha));

        return checkdate($mes, $dia, $anio) ? $fecha : null;
    }

    /**
     * Normaliza la hora de inspección al nombre de carpeta correspondiente.
     *
     * Reglas (Fase E.2):
     *
     *  * `11:14:00` → `11-14-00`;
     *  * `00:00:00` → `00-00-00`: la medianoche es una hora válida y **no**
     *    equivale a una hora ausente;
     *  * hora ausente (null o vacía) → `SIN-HORA`;
     *  * cualquier otra forma → null.
     *
     * Una hora ilegible **no** se degrada a `SIN-HORA`: `SIN-HORA` significa
     * exclusivamente "la inspección no tiene hora", y confundir ambas cosas
     * mezclaría dos carpetas de inspecciones distintas. Un dato ilegible es un
     * error de datos y se rechaza (null), no se renombra.
     *
     * La comparación con la base de datos para desambiguar inspecciones de
     * una misma obra, fecha y hora es responsabilidad de quien determina el
     * sufijo (`InspeccionModel::nombreCarpetaInspeccion()`); aquí solo se
     * decide la forma del nombre a partir de la hora.
     */
    public function normalizarHoraCarpeta(?string $hora): ?string
    {
        if ($hora === null || trim($hora) === '') {
            return 'SIN-HORA';
        }

        $hora = trim($hora);

        return preg_match(self::PATRON_HORA, $hora) === 1 ? str_replace(':', '-', $hora) : null;
    }

    /**
     * Nombre físico de la carpeta de una inspección a partir de su hora y del
     * sufijo ordinal dentro de la misma obra + fecha + hora.
     *
     * El sufijo 1 (la inspección con menor `id` del grupo) usa el nombre base;
     * los siguientes añaden `-2`, `-3`, etc.
     *
     * Devuelve null si el sufijo es menor que 1 o si la hora no es válida, de
     * modo que una inspection nunca se escriba dentro de una carpeta que no le
     * corresponde.
     */
    public function nombreCarpetaInspeccion(?string $hora, int $sufijo): ?string
    {
        $base = $this->normalizarHoraCarpeta($hora);

        if ($base === null || $sufijo < 1) {
            return null;
        }

        return $sufijo === 1 ? $base : $base . '-' . $sufijo;
    }

    /**
     * Ruta relativa de la carpeta de una inspección, sin subdirectorio final:
     * `OBR-XXXXXX/YYYY-MM-DD/NOMBRE-INSPECCION`.
     *
     * Devuelve null si el código, la fecha o el nombre de carpeta no son
     * válidos, de modo que nunca se compone una ruta a partir de datos no
     * verificados.
     */
    public function rutaRelativaInspeccion(string $codigo, string $fecha, string $nombreCarpetaInspeccion): ?string
    {
        $codigo = $this->normalizarCodigo($codigo);
        $fecha  = $this->normalizarFecha($fecha);
        $nombre = $this->validarNombreCarpeta($nombreCarpetaInspeccion);

        if ($codigo === null || $fecha === null || $nombre === null) {
            return null;
        }

        return $codigo . '/' . $fecha . '/' . $nombre;
    }

    /**
     * Crea (idempotentemente) la estructura completa de una inspección:
     * obra / fecha / nombre-carpeta / IMAGENES / THUMBNAILS.
     *
     * Devuelve la ruta relativa de la carpeta de la inspección
     * (`OBR-XXXXXX/YYYY-MM-DD/NOMBRE`) o null si algún dato no es válido o si
     * el sistema de archivos no permite preparar las carpetas.
     */
    public function asegurarEstructuraInspeccion(string $codigo, string $fecha, string $nombreCarpetaInspeccion): ?string
    {
        $relativa = $this->rutaRelativaInspeccion($codigo, $fecha, $nombreCarpetaInspeccion);

        if ($relativa === null || ! $this->asegurarRaiz()) {
            return null;
        }

        foreach (['', '/' . self::DIR_IMAGENES, '/' . self::DIR_THUMBNAILS] as $sufijo) {
            $directorio = $this->raiz . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, $relativa . $sufijo);

            if (! is_dir($directorio) && ! @mkdir($directorio, self::PERMISOS_DIRECTORIO, true) && ! is_dir($directorio)) {
                return null;
            }

            if (! is_writable($directorio)) {
                return null;
            }
        }

        return $relativa;
    }

    /**
     * Directorio absoluto de las imágenes de una inspección, o null si la
     * estructura no existe o algún dato no es válido.
     */
    public function directorioImagenesInspeccion(string $codigo, string $fecha, string $nombreCarpetaInspeccion): ?string
    {
        return $this->directorioSubcarpetaInspeccion($codigo, $fecha, $nombreCarpetaInspeccion, self::DIR_IMAGENES);
    }

    /**
     * Directorio absoluto de las miniaturas de una inspección, o null si la
     * estructura no existe o algún dato no es válido.
     */
    public function directorioThumbnailsInspeccion(string $codigo, string $fecha, string $nombreCarpetaInspeccion): ?string
    {
        return $this->directorioSubcarpetaInspeccion($codigo, $fecha, $nombreCarpetaInspeccion, self::DIR_THUMBNAILS);
    }

    /**
     * Ruta relativa del archivo de una imagen de una inspección
     * (`OBR-XXXXXX/YYYY-MM-DD/NOMBRE/IMAGENES/nombre`).
     */
    public function rutaRelativaImagen(string $codigo, string $fecha, string $nombreCarpetaInspeccion, string $nombreArchivo): ?string
    {
        $base = $this->rutaRelativaInspeccion($codigo, $fecha, $nombreCarpetaInspeccion);

        if ($base === null || ! $this->esNombreArchivoSeguro($nombreArchivo)) {
            return null;
        }

        return $base . '/' . self::DIR_IMAGENES . '/' . $nombreArchivo;
    }

    /**
     * Ruta relativa del thumbnail de una fotografía
     * (`OBR-XXXXXX/YYYY-MM-DD/NOMBRE/THUMBNAILS/nombre`).
     */
    public function rutaRelativaThumbnail(string $codigo, string $fecha, string $nombreCarpetaInspeccion, string $nombreArchivo): ?string
    {
        $base = $this->rutaRelativaInspeccion($codigo, $fecha, $nombreCarpetaInspeccion);

        if ($base === null || ! $this->esNombreArchivoSeguro($nombreArchivo)) {
            return null;
        }

        return $base . '/' . self::DIR_THUMBNAILS . '/' . $nombreArchivo;
    }

    /**
     * Nombre físico de una fotografía. Lo genera el servidor; el cliente
     * nunca lo decide (§52.8 / §56).
     *
     * Formato: `INS-{id de inspección en 5 dígitos}-{Ymd-His}-{random6}.{ext}`
     *
     * El identificador de la inspección se utiliza porque la fotografía solo
     * se procesa cuando su inspección padre ya fue confirmada en el servidor:
     * en ese momento el `id` interno existe y es estable.
     *
     * @param int    $inspeccionId Id interno de `inspecciones`.
     * @param string $marcaTiempo  Marca temporal `Ymd-His` (por carga).
     * @param string $extension    Extensión ya normalizada, sin punto.
     */
    public function nombreFotografia(int $inspeccionId, string $marcaTiempo, string $extension): string
    {
        $marcaTiempo = preg_match('/\A\d{8}-\d{6}\z/', $marcaTiempo) === 1
            ? $marcaTiempo
            : date('Ymd-His');

        $extension = strtolower(preg_replace('/[^a-z0-9]/i', '', $extension) ?? '');
        $extension = $extension === '' ? 'jpg' : $extension;

        $trama = bin2hex(random_bytes((int) ceil(self::LONGITUD_TRAMA / 2)));

        return sprintf(
            'INS-%05d-%s-%s.%s',
            $inspeccionId,
            $marcaTiempo,
            substr($trama, 0, self::LONGITUD_TRAMA),
            $extension
        );
    }

    /**
     * Nombre físico del thumbnail de una fotografía.
     *
     * El thumbnail se regenera siempre en el servidor como JPEG a partir del
     * archivo ya optimizado por el cliente, de modo que su nombre comparte la
     * marca temporal y solo difiere en el prefijo y la extensión.
     */
    public function nombreThumbnailFotografia(int $inspeccionId, string $marcaTiempo): string
    {
        $extension = 'jpg';

        $marcaTiempo = preg_match('/\A\d{8}-\d{6}\z/', $marcaTiempo) === 1
            ? $marcaTiempo
            : date('Ymd-His');

        $trama = bin2hex(random_bytes((int) ceil(self::LONGITUD_TRAMA / 2)));

        return sprintf(
            'THB-%05d-%s-%s.%s',
            $inspeccionId,
            $marcaTiempo,
            substr($trama, 0, self::LONGITUD_TRAMA),
            $extension
        );
    }

    /**
     * Resuelve una ruta relativa ya construida contra la raíz, devolviendo
     * null si la raíz no está configurada o si la ruta escapa de ella.
     */
    public function absolutoDesdeRelativa(string $relativa): ?string
    {
        $relativa = trim($relativa);

        if ($relativa === '' || $this->raiz === '') {
            return null;
        }

        /* Normaliza y descarta cualquier tentativa de salir de la raíz. */
        $unificada = str_replace('\\', '/', $relativa);

        if (strpos($unificada, '/../') !== false
            || str_contains($unificada, '../')
            || str_starts_with($unificada, '/')
            || preg_match('/\A[A-Za-z]:/', $unificada) === 1
        ) {
            return null;
        }

        return $this->raiz . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $unificada);
    }

    /**
     * Comprueba que un nombre de archivo no introduzca separadores de
     * directorio ni rutas relativas. El nombre lo genera el servidor, pero se
     * valida igual antes de componer la ruta final.
     */
    private function esNombreArchivoSeguro(string $nombreArchivo): bool
    {
        $nombreArchivo = trim($nombreArchivo);

        return $nombreArchivo !== ''
            && basename($nombreArchivo) === $nombreArchivo
            && ! str_contains($nombreArchivo, '/')
            && ! str_contains($nombreArchivo, '\\')
            && $nombreArchivo !== '.'
            && $nombreArchivo !== '..'
            && preg_match('/\A[A-Za-z0-9._-]+\z/', $nombreArchivo) === 1;
    }

    /**
     * Valida un nombre de carpeta de inspección contra el patrón interno,
     * rechazando separadores y rutas relativas.
     */
    private function validarNombreCarpeta(string $nombre): ?string
    {
        $nombre = trim($nombre);

        return preg_match(self::PATRON_CARPETA_INSPECCION, $nombre) === 1 ? $nombre : null;
    }

    /**
     * Resuelve un subdirectorio concreto de una inspección
     * (`OBR/fecha/nombre/IMAGENES` o `/THUMBNAILS`) y lo devuelve si existe
     * en disco.
     */
    private function directorioSubcarpetaInspeccion(string $codigo, string $fecha, string $nombreCarpetaInspeccion, string $subcarpeta): ?string
    {
        $relativa = $this->rutaRelativaInspeccion($codigo, $fecha, $nombreCarpetaInspeccion);

        if ($relativa === null) {
            return null;
        }

        return $this->directorioRelativo($relativa . '/' . $subcarpeta);
    }

    /**
     * Resuelve una ruta relativa de un subdirectorio ya compuesto
     * (`OBR/fecha/nombre/IMAGENES`) y la devuelve si existe en disco.
     */
    private function directorioRelativo(string $relativa): ?string
    {
        $absoluta = $this->absolutoDesdeRelativa($relativa);

        if ($absoluta === null) {
            return null;
        }

        return is_dir($absoluta) ? $absoluta : null;
    }
}