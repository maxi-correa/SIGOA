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
 *   │   ├── IMAGENES/
 *   │   └── THUMBNAILS/
 *   └── OBR-000002/
 *
 * La raíz se resuelve desde SIGOA_STORAGE_PATH. Los nombres de carpetas
 * se derivan únicamente de `obras.codigo`, que se valida con el formato
 * interno `OBR-XXXXXX` antes de usarse, impidiendo traversal de rutas.
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
}