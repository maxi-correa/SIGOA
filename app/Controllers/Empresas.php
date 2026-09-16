<?php

namespace App\Controllers;

use App\Models\EmpresaModel;

/**
 * Gestión de empresas contratistas.
 *
 * Accesible solo para SUPERADMINISTRADOR y ADMINISTRADOR.
 * La autorización se controla mediante el filtro RoleFilter en las rutas.
 */
class Empresas extends BaseController
{
    protected EmpresaModel $empresaModel;

    /** Directorio de logos relativo a la raíz de almacenamiento. */
    private const LOGOS_DIR = 'EMPRESAS';

    /** Tamaño máximo de logo: 2 MB (2 097 152 bytes). */
    private const LOGO_MAX_BYTES = 2_097_152;

    /**
     * Mapa MIME → extensión válida para logos.
     *
     * Las claves son los tipos MIME reales detectados por finfo.
     */
    private const LOGO_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct()
    {
        $this->empresaModel = new EmpresaModel();
    }

    /**
     * Listado de todas las empresas.
     */
    public function index()
    {
        $session = session();

        $data = [
            'titulo'    => 'Empresas',
            'user_name' => $session->get('user_name'),
            'username'  => $session->get('username'),
            'roles'     => $session->get('roles') ?? [],
            'empresas'  => $this->empresaModel->listarTodas(),
        ];

        return view('empresas/index', $data);
    }

    /**
     * Formulario de alta de empresa.
     */
    public function nueva()
    {
        $session = session();

        $data = [
            'titulo'    => 'Nueva empresa',
            'user_name' => $session->get('user_name'),
            'username'  => $session->get('username'),
            'roles'     => $session->get('roles') ?? [],
        ];

        return view('empresas/formulario', $data);
    }

    /**
     * Procesa el alta de una empresa.
     */
    public function crear()
    {
        $datos   = $this->tomarDatos();
        $errores = $this->validarDatos($datos);

        if ($errores !== []) {
            return redirect()->to('/empresas/nueva')
                ->withInput()
                ->with('errores_empresa', $errores);
        }

        $this->empresaModel->crear($datos);

        return redirect()->to('/empresas')
            ->with('success', 'La empresa fue registrada correctamente.');
    }

    /**
     * Formulario de edición de empresa.
     */
    public function editar(int $id)
    {
        $empresa = $this->empresaModel->find($id);

        if ($empresa === null) {
            return redirect()->to('/empresas')
                ->with('error', 'La empresa seleccionada no existe.');
        }

        $session = session();

        $data = [
            'titulo'    => 'Editar empresa',
            'user_name' => $session->get('user_name'),
            'username'  => $session->get('username'),
            'roles'     => $session->get('roles') ?? [],
            'empresa'   => $empresa,
        ];

        return view('empresas/formulario', $data);
    }

    /**
     * Procesa la edición de una empresa.
     *
     * `created_at` NO se modifica; `updated_at` se refresca.
     */
    public function actualizar(int $id)
    {
        $empresa = $this->empresaModel->find($id);

        if ($empresa === null) {
            return redirect()->to('/empresas')
                ->with('error', 'La empresa seleccionada no existe.');
        }

        $datos   = $this->tomarDatos();
        $errores = $this->validarDatos($datos);

        if ($errores !== []) {
            return redirect()->to('/empresas/editar/' . $id)
                ->withInput()
                ->with('errores_empresa', $errores);
        }

        $this->empresaModel->actualizar($id, $datos);

        return redirect()->to('/empresas')
            ->with('success', 'Los datos de la empresa fueron actualizados correctamente.');
    }

    /* ================================================================
       LOGO — Servir imagen
       ================================================================ */

    /**
     * Sirve el logo de una empresa como imagen con el Content-Type correcto.
     *
     * La ruta física se resuelve combinando la raíz de almacenamiento
     * configurada con la referencia relativa almacenada en BD.
     * Si el archivo no existe o no puede resolverse, retorna 404.
     */
    public function verLogo(int $id)
    {
        $empresa = $this->empresaModel->find($id);

        if ($empresa === null || $empresa->ruta_logo === null || $empresa->ruta_logo === '') {
            return $this->response->setStatusCode(404)->setBody('');
        }

        $absoluto = $this->resolverRutaLogo($empresa->ruta_logo);

        if ($absoluto === null || ! is_file($absoluto)) {
            return $this->response->setStatusCode(404)->setBody('');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($absoluto);

        if ($mime === false) {
            $mime = 'application/octet-stream';
        }

        return $this->response
            ->setHeader('Content-Type', $mime)
            ->setHeader('Content-Length', (string) filesize($absoluto))
            ->setHeader('Cache-Control', 'private, max-age=86400')
            ->setBody((string) file_get_contents($absoluto));
    }

    /* ================================================================
       LOGO — Subir / reemplazar
       ================================================================ */

    /**
     * Recibe la carga de un logo desde el listado de empresas.
     *
     * Funciona tanto para carga inicial (empresa sin logo) como para
     * reemplazo (empresa con logo existente).
     *
     * El nombre físico se genera siempre a partir del ID de empresa:
     *   000001-logo.jpg, 000001-logo.png, etc.
     *
     * La ruta absoluta NO se almacena en BD: solo la referencia relativa
     * (EMPRESAS/000001-logo.jpg) para que sea portable.
     */
    public function subirLogo()
    {
        $empresaId = (int) $this->request->getPost('empresa_id');
        $empresa   = $this->empresaModel->find($empresaId);

        if ($empresa === null) {
            return redirect()->to('/empresas')
                ->with('error', 'La empresa seleccionada no existe.');
        }

        /* --- 1. Obtener el archivo subido --- */
        $archivo = $this->request->getFile('logo');

        if ($archivo === null) {
            return redirect()->to('/empresas')
                ->with('error', 'Debe seleccionar un archivo de imagen para el logo.');
        }

        /* 2. Validar que la subida no haya fallado (especialmente por upload_max_filesize) */
        if (! $archivo->isValid()) {
            $mensaje = ($archivo->getError() === UPLOAD_ERR_INI_SIZE)
                ? 'El logo supera el tamaño máximo permitido de 2 MB.'
                : 'No fue posible recibir el archivo del logo. Intente nuevamente.';

            return redirect()->to('/empresas')->with('error', $mensaje);
        }

        /* --- 3. Validar tamaño --- */
        if ($archivo->getSize() > self::LOGO_MAX_BYTES) {
            return redirect()->to('/empresas')
                ->with('error', 'El logo supera el tamaño máximo permitido de 2 MB.');
        }

        /* --- 4. Validar formato (MIME real via finfo) --- */
        $mime = strtolower((string) $archivo->getMimeType());

        if (! isset(self::LOGO_MIMES[$mime])) {
            return redirect()->to('/empresas')
                ->with('error', 'El formato del archivo no es válido. Los formatos permitidos son JPG, JPEG, PNG y WEBP.');
        }

        $extension = self::LOGO_MIMES[$mime];

        /* --- 5. Preparar directorio de destino --- */
        $directorio = $this->obtenerDirectorioLogos();

        if ($directorio === null) {
            return redirect()->to('/empresas')
                ->with('error', 'No fue posible preparar el almacenamiento de logos.');
        }

        /* --- 6. Nombre normalizado del archivo físico --- */
        $nombre   = sprintf('%06d-logo.%s', $empresaId, $extension);
        $destino  = $directorio . DIRECTORY_SEPARATOR . $nombre;

        /* --- 7. Referencia relativa que quedará en la BD --- */
        $rutaRelativa = self::LOGOS_DIR . '/' . $nombre;

        /* --- 8. Recordar logo anterior (por si reemplaza) --- */
        $logoAnterior = (! empty($empresa->ruta_logo) && $empresa->ruta_logo !== $rutaRelativa)
            ? $empresa->ruta_logo
            : null;

        /* --- 9. Mover el archivo al destino final --- */
        try {
            $archivo->move($directorio, $nombre, true);
        } catch (\Throwable $e) {
            return redirect()->to('/empresas')
                ->with('error', 'No fue posible guardar el logo.');
        }

        /* --- 10. Actualizar la BD --- */
        if (! $this->empresaModel->actualizarLogo($empresaId, $rutaRelativa)) {
            @unlink($destino);

            return redirect()->to('/empresas')
                ->with('error', 'No fue posible guardar el logo.');
        }

        /* --- 11. Eliminar el logo anterior si existía y tenía otra extensión --- */
        if ($logoAnterior !== null) {
            $this->eliminarArchivoLogo($logoAnterior);
        }

        $mensaje = ($logoAnterior === null)
            ? 'Logo cargado correctamente.'
            : 'Logo actualizado correctamente.';

        return redirect()->to('/empresas')->with('success', $mensaje);
    }

    /* ================================================================
       LOGO — Eliminar
       ================================================================ */

    /**
     * Elimina el logo de una empresa: archivo físico + referencia en BD.
     *
     * Se solicita confirmación previa desde el frontend (modal).
     * El archivo físico se elimina de forma segura; si no existe, se
     * deja la BD consistente sin lanzar errores fatales.
     */
    public function eliminarLogo()
    {
        $empresaId = (int) $this->request->getPost('empresa_id');
        $empresa   = $this->empresaModel->find($empresaId);

        if ($empresa === null) {
            return redirect()->to('/empresas')
                ->with('error', 'La empresa seleccionada no existe.');
        }

        $logoActual = (! empty($empresa->ruta_logo)) ? $empresa->ruta_logo : null;

        /* --- 1. Limpiar la referencia en BD --- */
        if (! $this->empresaModel->quitarLogo($empresaId)) {
            return redirect()->to('/empresas')
                ->with('error', 'No fue posible eliminar el logo.');
        }

        /* --- 2. Eliminar el archivo físico (silencioso si no existe) --- */
        if ($logoActual !== null) {
            $this->eliminarArchivoLogo($logoActual);
        }

        return redirect()->to('/empresas')
            ->with('success', 'Logo eliminado correctamente.');
    }

    /* ================================================================
       MÉTODOS PRIVADOS — UTILIDADES
       ================================================================ */

    /**
     * Toma y normaliza los datos del formulario de empresa.
     *
     * Los campos opcionales vacíos se convierten en NULL.
     *
     * @return array<string, mixed>
     */
    private function tomarDatos(): array
    {
        $razonSocial = trim((string) $this->request->getPost('razon_social'));
        $cuit        = trim((string) $this->request->getPost('cuit'));
        $domicilio   = trim((string) $this->request->getPost('domicilio'));
        $telefono    = trim((string) $this->request->getPost('telefono'));
        $email       = trim((string) $this->request->getPost('email'));
        $activo      = (int) $this->request->getPost('activo');

        return [
            'razon_social' => mb_strtoupper($razonSocial),
            'cuit'         => ($cuit === '') ? null : $cuit,
            'domicilio'    => ($domicilio === '') ? null : $domicilio,
            'telefono'     => ($telefono === '') ? null : $telefono,
            'email'        => ($email === '') ? null : $email,
            'activo'       => $activo === 1 ? 1 : 0,
        ];
    }

    /**
     * Valida los datos de una empresa.
     *
     * @param array<string, mixed> $datos
     *
     * @return list<string>
     */
    private function validarDatos(array $datos): array
    {
        $errores = [];

        if ($datos['razon_social'] === '') {
            $errores[] = 'La razón social es obligatoria.';
        }

        return $errores;
    }

    /**
     * Devuelve el directorio físico de logos, creándolo si no existe.
     *
     * Verifica que el directorio final sea escriturable.
     * Si la raíz de almacenamiento no está configurada, retorna null.
     */
    private function obtenerDirectorioLogos(): ?string
    {
        $raiz = config('SigoaStorage')->storagePath;

        if ($raiz === '') {
            return null;
        }

        $directorio = $raiz . DIRECTORY_SEPARATOR . self::LOGOS_DIR;

        if (! is_dir($directorio)) {
            $creado = @mkdir($directorio, 0755, true);

            if (! $creado && ! is_dir($directorio)) {
                return null;
            }
        }

        return $directorio;
    }

    /**
     * Resuelve la ruta absoluta de un logo a partir de su referencia
     * relativa almacenada en BD.
     *
     * Verifica que la ruta resultante se encuentre dentro de la raíz
     * de almacenamiento configurada, previniendo traversal.
     *
     * @return string|null Ruta absoluta del archivo o null si es insegura/inexistente.
     */
    private function resolverRutaLogo(string $rutaRelativa): ?string
    {
        $raiz = config('SigoaStorage')->storagePath;

        if ($raiz === '') {
            return null;
        }

        $normalizada = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rutaRelativa);

        /* Verificar que no haya componentes de traversal */
        $partes = explode(DIRECTORY_SEPARATOR, $normalizada);

        foreach ($partes as $parte) {
            if ($parte === '..' || $parte === '.') {
                return null;
            }
        }

        $absoluta   = $raiz . DIRECTORY_SEPARATOR . $normalizada;
        $raizReal   = realpath($raiz);
        $absReal    = realpath($absoluta);

        if ($raizReal === false) {
            return null;
        }

        /* Si el archivo no existe todavía, construir la ruta sin realpath del archivo */
        if ($absReal === false) {
            $candidate = $raizReal . DIRECTORY_SEPARATOR . $normalizada;

            /* Verificar que el componente realpath-resuelto del padre esté dentro de la raíz */
            $parentDir  = dirname($candidate);
            $parentReal = realpath($parentDir);

            if ($parentReal !== false && strncmp($parentReal, $raizReal, strlen($raizReal)) === 0) {
                return $candidate;
            }

            return null;
        }

        /* Archivo existente: verificar que esté dentro de la raíz */
        if (strncmp($absReal, $raizReal, strlen($raizReal)) !== 0) {
            return null;
        }

        return $absReal;
    }

    /**
     * Elimina un archivo de logo del disco a partir de su referencia relativa.
     *
     * Si el archivo no existe, la operación se completa sin errores.
     * No genera errores fatales bajo ninguna circunstancia.
     */
    private function eliminarArchivoLogo(string $rutaRelativa): void
    {
        $absoluto = $this->resolverRutaLogo($rutaRelativa);

        if ($absoluto !== null && is_file($absoluto)) {
            @unlink($absoluto);
        }
    }
}