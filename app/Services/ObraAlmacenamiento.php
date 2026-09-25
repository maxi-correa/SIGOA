<?php

namespace App\Services;

/**
 * Almacenamiento físico de archivos de una obra.
 *
 * Organiza el almacenamiento de fotografías por obra bajo la raíz
 * configurada en Config\SigoaStorage:
 *
 *   <RAIZ_SIGOA>/
 *   ├── OBR-000001/
 *   │   ├── IMAGENES/                (estructura base, Fase D.1/D.2)
 *   │   └── THUMBNAILS/
 *   └── OBR-000002/
 *
 * Desde la Fase D.4 (§52.8) las fotografías de una inspección se organizan
 * con la estructura anidada definitiva:
 *
 *   <RAIZ_SIGOA>/
 *   └── OBR-000001/
 *       └── 2026-09-22/
 *           └── UUID-DE-INSPECCION/
 *               ├── IMAGENES/
 *               └── THUMBNAILS/
 *
 * Ambos esquemas conviven: `asegurarEstructuraObra()` y sus subdirectorios
 * siguen disponibles para el llamador de D.2 (`Inspector\Obras::ver()`), y los
 * métodos `*Inspeccion()` agregan el nivel fecha + uuid sin romperlos.
 *
 * La raíz se resuelve desde SIGOA_STORAGE_PATH. Los nombres de carpetas se
 * derivan únicamente de `obras.codigo`, de la fecha de la inspección y de su
 * UUID, los tres validados con formatos internos estricto antes de usarse,
 * lo que impide traversal de rutas.
 *
 * En la base de datos solo se almacenan referencias relativas; nunca
 * rutas absolutas (misma convención que `empresas.ruta_logo`).
 */
class ObraAlmacenamiento
{
    /** Carpeta de fotografías originales dentro de cada obra. */
    public const DIR_IMAGENES = 'IMAGENES';

    /** Carpeta de miniaturas dentro de cada obra. */
    public const DIR_THUMBNAILS = 'THUMBNAILS';

    /** Patrón de la carpeta de fecha (`YYYY-MM-DD`) dentro de una obra. */
    private const PATRON_FECHA = '/\A\d{4}-\d{2}-\d{2}\z/';

    /** Patrón RFC 4122 de la carpeta de inspección. */
    private const PATRON_UUID = '/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/i';

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
     * Crea automáticamente la estructura de carpetas de una obra:
     * raíz, carpeta de la obra, IMAGENES y THUMBNAILS.
     *
     * La operación es idempotente: si las carpetas ya existen solo
     * verifica que estén escriturables.
     */
    public function asegurarEstructuraObra(string $codigo): bool
    {
        $codigo = $this->normalizarCodigo($codigo);

        if ($codigo === null || ! $this->asegurarRaiz()) {
            return false;
        }

        foreach ([
            $codigo,
            $codigo . DIRECTORY_SEPARATOR . self::DIR_IMAGENES,
            $codigo . DIRECTORY_SEPARATOR . self::DIR_THUMBNAILS,
        ] as $relativo) {
            $directorio = $this->raiz . DIRECTORY_SEPARATOR . $relativo;

            if (! is_dir($directorio)) {
                @mkdir($directorio, self::PERMISOS_DIRECTORIO, true);

                if (! is_dir($directorio)) {
                    return false;
                }
            }

            if (! is_writable($directorio)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Directorio absoluto de fotografías de una obra, o null si la
     * carpeta no existe o el código no es válido.
     */
    public function directorioImagenes(string $codigo): ?string
    {
        return $this->directorioObra($codigo, self::DIR_IMAGENES);
    }

    /**
     * Directorio absoluto de miniaturas de una obra, o null si la
     * carpeta no existe o el código no es válido.
     */
    public function directorioThumbnails(string $codigo): ?string
    {
        return $this->directorioObra($codigo, self::DIR_THUMBNAILS);
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
       Estructura anidada de una inspección (Fase D.4 / §52.8)

       OBR-XXXXXX / YYYY-MM-DD / UUID-INSPECCION / {IMAGENES,THUMBNAILS}
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
     * Valida y normaliza el UUID de la inspección para usarlo como nombre de
     * carpeta. Devuelve la forma canónica en minúsculas o null si no es un
     * UUID RFC 4122.
     */
    public function normalizarUuid(string $uuid): ?string
    {
        $uuid = trim($uuid);

        return preg_match(self::PATRON_UUID, $uuid) === 1 ? strtolower($uuid) : null;
    }

    /**
     * Ruta relativa de la carpeta de una inspección, sin subdirectorio final:
     * `OBR-XXXXXX/YYYY-MM-DD/UUID-INSPECCION`.
     *
     * Devuelve null si el código, la fecha o el UUID no son válidos, de modo
     * que nunca se compone una ruta a partir de datos no verificados.
     */
    public function rutaRelativaInspeccion(string $codigo, string $fecha, string $uuidInspeccion): ?string
    {
        $codigo = $this->normalizarCodigo($codigo);
        $fecha  = $this->normalizarFecha($fecha);
        $uuid   = $this->normalizarUuid($uuidInspeccion);

        if ($codigo === null || $fecha === null || $uuid === null) {
            return null;
        }

        return $codigo . '/' . $fecha . '/' . $uuid;
    }

    /**
     * Crea (idempotentemente) la estructura completa de una inspección:
     * obra / fecha / uuid / IMAGENES / THUMBNAILS.
     *
     * Devuelve la ruta relativa de la carpeta de la inspección
     * (`OBR-XXXXXX/YYYY-MM-DD/UUID`) o null si algún dato no es válido o si
     * el sistema de archivos no permite preparar las carpetas.
     */
    public function asegurarEstructuraInspeccion(string $codigo, string $fecha, string $uuidInspeccion): ?string
    {
        $relativa = $this->rutaRelativaInspeccion($codigo, $fecha, $uuidInspeccion);

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
    public function directorioImagenesInspeccion(string $codigo, string $fecha, string $uuidInspeccion): ?string
    {
        return $this->directorioRelativo(
            $this->rutaRelativaInspeccion($codigo, $fecha, $uuidInspeccion) . '/' . self::DIR_IMAGENES
        );
    }

    /**
     * Directorio absoluto de las miniaturas de una inspección, o null si la
     * estructura no existe o algún dato no es válido.
     */
    public function directorioThumbnailsInspeccion(string $codigo, string $fecha, string $uuidInspeccion): ?string
    {
        return $this->directorioRelativo(
            $this->rutaRelativaInspeccion($codigo, $fecha, $uuidInspeccion) . '/' . self::DIR_THUMBNAILS
        );
    }

    /**
     * Ruta relativa del archivo de una imagen de una inspección
     * (`OBR-XXXXXX/YYYY-MM-DD/UUID/IMAGENES/nombre`).
     */
    public function rutaRelativaImagen(string $codigo, string $fecha, string $uuidInspeccion, string $nombreArchivo): ?string
    {
        $base = $this->rutaRelativaInspeccion($codigo, $fecha, $uuidInspeccion);

        if ($base === null || ! $this->esNombreArchivoSeguro($nombreArchivo)) {
            return null;
        }

        return $base . '/' . self::DIR_IMAGENES . '/' . $nombreArchivo;
    }

    /**
     * Ruta relativa del thumbnail de una fotografía
     * (`OBR-XXXXXX/YYYY-MM-DD/UUID/THUMBNAILS/nombre`).
     */
    public function rutaRelativaThumbnail(string $codigo, string $fecha, string $uuidInspeccion, string $nombreArchivo): ?string
    {
        $base = $this->rutaRelativaInspeccion($codigo, $fecha, $uuidInspeccion);

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
     * Resuelve y valida un subdirectorio de una obra.
     */
    private function directorioObra(string $codigo, string $subdirectorio): ?string
    {
        $codigo = $this->normalizarCodigo($codigo);

        if ($codigo === null || $this->raiz === '') {
            return null;
        }

        $directorio = $this->raiz . DIRECTORY_SEPARATOR
            . $codigo . DIRECTORY_SEPARATOR . $subdirectorio;

        return is_dir($directorio) ? $directorio : null;
    }

    /**
     * Resuelve una ruta relativa de un subdirectorio ya compuesto
     * (`OBR/fecha/uuid/IMAGENES`) y la devuelve si existe en disco.
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