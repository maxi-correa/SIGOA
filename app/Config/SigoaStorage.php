<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Configuración de almacenamiento físico de SIGOA.
 *
 * La raíz de los archivos compartidos se define mediante la variable de
 * entorno SIGOA_STORAGE_PATH, siguiendo la convención del proyecto de
 * centralizar la configuración en el archivo `.env` (patrón de Database).
 *
 * Ejemplo:
 *
 *   SIGOA_STORAGE_PATH = C:\Compartida\SIGOA
 *
 * La base de datos NO guarda la ruta física completa: únicamente guarda
 * referencias relativas (por ejemplo `EMPRESAS/000001-logo.png`) que se
 * combinan con esta raíz para resolver la ubicación real del archivo.
 */
class SigoaStorage extends BaseConfig
{
    /**
     * Ruta raíz física de almacenamiento de SIGOA.
     *
     * Se resuelve desde la variable de entorno SIGOA_STORAGE_PATH.
     * Si no está definida, queda vacía y las operaciones de archivos
     * no podrán ejecutarse.
     */
    public string $storagePath = '';

    public function __construct()
    {
        parent::__construct();

        $path = (string) env('SIGOA_STORAGE_PATH', '');

        if ($path !== '') {
            $this->storagePath = rtrim($path, '/\\');
        }
    }
}