<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Configuración de almacenamiento físico de SIGOA.
 *
 * ---------------------------------------------------------------------
 * Cómo se declara la raíz
 * ---------------------------------------------------------------------
 * La raíz se declara con SIGOA_STORAGE_COMPARTIDO, que admite dos formas:
 *
 *   1. Una ruta. Es la forma de producción y la recomendada:
 *
 *        SIGOA_STORAGE_COMPARTIDO = '//DESKTOP-RJ9VDRF/Compartido KM5/SIGOA'
 *
 *      Si el valor es una ruta, esa es la raíz, y además implica que el
 *      despliegue es compartido: no hace falta declarar nada más.
 *
 *   2. Un booleano, para marcar el carácter del despliegue sin declarar la
 *      ruta. Es la forma de desarrollo, en la que la ruta viene de
 *      SIGOA_STORAGE_PATH:
 *
 *        SIGOA_STORAGE_COMPARTIDO = 0
 *        SIGOA_STORAGE_PATH       = C:\writable\sigoa
 *
 * Precedencia: una ruta en SIGOA_STORAGE_COMPARTIDO gana sobre
 * SIGOA_STORAGE_PATH. Si SIGOA_STORAGE_COMPARTIDO trae una ruta, el valor de
 * SIGOA_STORAGE_PATH se ignora por completo.
 *
 * ---------------------------------------------------------------------
 * Recurso de red, no letra de unidad
 * ---------------------------------------------------------------------
 * En producción la raíz es el **recurso compartido de red**, alcanzado por
 * una ruta UNC (`\\servidor\recurso\SIGOA`), nunca por una letra de unidad.
 *
 * `Z:\…` o `Y:\…` son alias que Windows crea en la sesión del usuario
 * interactivo. Apache corre como la cuenta técnica `SIGOA_APACHE`, no como
 * ese usuario, y no ve las unidades mapeadas por otro: la ruta resuelve como
 * inexistente. La ruta UNC es la misma para todos los equipos y para
 * cualquier proceso del servidor.
 *
 * La identidad ya está resuelta a nivel de sistema por esa cuenta de
 * servicio. SIGOA no guarda usuario ni contraseña: no hay credenciales SMB
 * en `.env` ni en el código, ni debe añadirse (§10).
 *
 * ---------------------------------------------------------------------
 * Rutas relativas en base de datos
 * ---------------------------------------------------------------------
 * La base de datos NO guarda la ruta física: solo referencias relativas
 * (`OBR-000001/AAAA-MM-DD/UUID/IMAGENES/archivo.jpg`) que se combinan con
 * esta raíz. Cambiar la raíz no obliga a migrar datos ni a reescribir filas.
 *
 * `tests/unit/AlmacenamientoCompartidoTest` verifica el contrato.
 */
class SigoaStorage extends BaseConfig
{
    /**
     * Raíz física de almacenamiento de SIGOA.
     *
     * Se resuelve desde SIGOA_STORAGE_COMPARTIDO o, en desarrollo, desde
     * SIGOA_STORAGE_PATH. Si no está definida, queda vacía y las operaciones
     * de archivos no podrán ejecutarse.
     */
    public string $storagePath = '';

    /**
     * Indica que la raíz debe ser un recurso compartido de red.
     *
     * Es `true` cuando SIGOA_STORAGE_COMPARTIDO trae una ruta, y se resuelve
     * del propio valor cuando trae un booleano.
     */
    public bool $compartido = false;

    /**
     * Valores aceptados para una variable booleana de entorno, para no
     * depender de la interpretación de `env()` con textos en español.
     */
    private const VERDADEROS = ['1', 'true', 't', 'yes', 'y', 'si', 'sí', 'activo'];

    public function __construct()
    {
        parent::__construct();

        $declarado = trim((string) env('SIGOA_STORAGE_COMPARTIDO', ''));
        $auxiliar = trim((string) env('SIGOA_STORAGE_PATH', ''));

        if ($this->pareceRuta($declarado)) {
            $this->storagePath = rtrim($declarado, '/\\');
            $this->compartido  = true;

            return;
        }

        $this->compartido = $this->leerBooleano($declarado);

        if ($auxiliar !== '') {
            $this->storagePath = rtrim($auxiliar, '/\\');
        }
    }

    /**
     * ¿El valor de una variable de entorno es una ruta y no un indicador?
     *
     * Un indicador (`1`, `true`, `si`) no lleva separadores; cualquier ruta
     * legítima sí, porque es absoluta o UNC. Es la única forma fiable de
     * distinguir `SIGOA_STORAGE_COMPARTIDO = 1` de
     * `SIGOA_STORAGE_COMPARTIDO = //equipo/recurso/SIGOA`.
     */
    private function pareceRuta(string $valor): bool
    {
        return $valor !== '' && strpbrk($valor, '/\\:') !== false;
    }

    /**
     * La misma raíz con el separador del sistema, para poder compararla con
     * rutas absolutas reales del sistema donde corre la prueba.
     */
    public function rutaNormalizada(): string
    {
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->storagePath);
    }

    /**
     * ¿La raíz configurada es una ruta de red UNC (`\\servidor\recurso`)?
     *
     * Es la única forma de escritura que funciona por igual para todos los
     * equipos y para el proceso del servidor web.
     *
     * Se aceptan las dos grafías porque Windows y PHP tratan las barras
     * inversas y las normales igual, y porque en un `.env` las barras
     * inversas deben duplicarse dentro de comillas: el lector de
     * CodeIgniter las colapsa. Escribir `//servidor/recurso` evita de
     * origen ese tropiezo, y sería un error rechazarlo.
     */
    public function esRutaDeRed(): bool
    {
        return str_starts_with($this->storagePath, '\\\\')
            || str_starts_with($this->storagePath, '//');
    }

    /**
     * ¿La raíz configurada es una letra de unidad de Windows (`C:\…`, `Z:\…`)?
     *
     * No distingue un volumen local de una unidad mapeada a un recurso de
     * red: en configuración de producción ninguna de las dos es válida, y
     * adivinar cuál sería exigirle a la máquina una respuesta que el
     * contrato evita.
     */
    public function esUnidadDeDisco(): bool
    {
        return preg_match('/\A[A-Za-z]:/', $this->storagePath) === 1;
    }

    /**
     * ¿La raíz es utilizable por el proceso del servidor?
     *
     * Requiere estar configurada y ser una ruta absoluta. No comprueba
     * existencia ni permisos: eso depende del equipo y corresponde a la
     * prueba de escritura, no a la configuración.
     */
    public function esAbsoluta(): bool
    {
        if ($this->storagePath === '') {
            return false;
        }

        return $this->esRutaDeRed()
            || $this->esUnidadDeDisco()
            || str_starts_with($this->rutaNormalizada(), DIRECTORY_SEPARATOR);
    }

    /**
     * Incumplimientos del contrato de almacenamiento, en texto legible.
     *
     * Vacío significa que la configuración es coherente. Lo usan las
     * pruebas para informar con precisión en lugar de con un aserto
     * genérico.
     *
     * @return list<string>
     */
    public function problemasDeContrato(): array
    {
        if ($this->storagePath === '') {
            return ['SIGOA_STORAGE_COMPARTIDO no declara ninguna raíz: las operaciones de archivos no pueden ejecutarse.'];
        }

        if (! $this->esAbsoluta()) {
            return ['La raíz de almacenamiento debe ser una ruta absoluta, no relativa.'];
        }

        if (! $this->compartido || $this->esRutaDeRed()) {
            return [];
        }

        return [
            'El despliegue está declarado como compartido, pero la raíz no es una ruta de red '
            . '(`//equipo/recurso/SIGOA`). Una letra de unidad es un alias de la sesión del usuario '
            . 'y no existe para el proceso de Apache.',
        ];
    }

    /**
     * Interpreta un valor de entorno como booleano.
     */
    private function leerBooleano(mixed $valor): bool
    {
        if (is_bool($valor)) {
            return $valor;
        }

        return in_array(mb_strtolower(trim((string) $valor)), self::VERDADEROS, true);
    }
}
