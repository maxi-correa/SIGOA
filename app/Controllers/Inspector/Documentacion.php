<?php

namespace App\Controllers\Inspector;

use App\Controllers\BaseController;
use App\Models\DocumentoModel;
use App\Models\InspectoresObrasModel;
use App\Models\ObraModel;
use App\Services\DocumentoArchivo;

/**
 * Documentación disponible de la obra, vista del inspector.
 *
 * El inspector consulta **únicamente la versión vigente** de cada documento
 * lógico de la obra que tiene asignada. La gestión documental (carga,
 * reemplazo de versiones, neutralización) sigue siendo exclusiva de los roles
 * administrativos: aquí no hay alta, ni reemplazo, ni borrado.
 *
 * La pantalla funciona en dos modos con el mismo código de cliente:
 *
 * * **En línea**: el servidor entrega el listado vigente y el PDF se abre
 *   desde el servidor.
 * * **Sin conexión**: el listado y los PDF ya descargados se resuelven desde
 *   IndexedDB (`documentos`), y el service worker sirve el shell estático de
 *   la pantalla.
 *
 * La entrega del PDF reutiliza `App\Services\DocumentoArchivo`, el mismo
 * mecanismo que usa la administración: se comprueba la asignación vigente del
 * inspector sobre la obra del documento y la ruta del archivo se resuelve
 * contra la raíz de almacenamiento autorizada. En ningún caso se expone al
 * cliente la ruta física del archivo.
 */
class Documentacion extends BaseController
{
    private DocumentoArchivo $archivos;

    public function __construct()
    {
        $this->archivos = new DocumentoArchivo();
    }

    /**
     * Pantalla de documentación disponible de una obra.
     */
    public function index(int $obraId)
    {
        $session   = session();
        $usuarioId = (int) $session->get('user_id');

        $obra = (new ObraModel())->findDetalle($obraId);

        if ($obra === null) {
            return redirect()->to('/inspector/dashboard')
                ->with('error', 'La obra seleccionada no existe.');
        }

        if (! (new InspectoresObrasModel())->esVigente($obraId, $usuarioId)) {
            return redirect()->to('/inspector/dashboard')
                ->with('warning', 'No puede consultar la documentación de esa obra. Solo puede consultar las obras que tiene asignadas como inspector.');
        }

        $documentos = (new DocumentoModel())->listarVigentesPorObra($obraId);

        return view('inspector/documentacion', [
            'titulo'    => 'Documentación disponible',
            'user_name' => $session->get('user_name'),
            'username'  => $session->get('username'),
            'roles'     => $session->get('roles') ?? [],
            'obra'      => $obra,
            'documentos' => $documentos,
            'payload'   => $this->construirPayload($obra, $documentos, $usuarioId, $session->get('user_name')),
        ]);
    }

    /**
     * Listado vigente en JSON.
     *
     * Es el origen de datos de la pantalla cuando el inspector está en línea
     * y necesita refrescar el listado (por ejemplo, tras una actualización
     * automática de versiones).
     */
    public function api(int $obraId)
    {
        $session   = session();
        $usuarioId = (int) $session->get('user_id');

        $obra = (new ObraModel())->findDetalle($obraId);

        if ($obra === null) {
            return $this->response->setStatusCode(404)
                ->setJSON(['ok' => false, 'error' => 'Obra no encontrada']);
        }

        if (! (new InspectoresObrasModel())->esVigente($obraId, $usuarioId)) {
            return $this->response->setStatusCode(403)
                ->setJSON(['ok' => false, 'error' => 'No autorizado']);
        }

        $documentos = (new DocumentoModel())->listarVigentesPorObra($obraId);

        return $this->response->setJSON([
            'ok'     => true,
            'payload' => $this->construirPayload($obra, $documentos, $usuarioId, $session->get('user_name')),
        ]);
    }

    /**
     * Entrega el PDF de un documento de la obra.
     *
     * La autorización se resuelve sobre la obra a la que pertenece el
     * documento: el inspector solo puede descargarlo si tiene la asignación
     * vigente. Si se indica `obra_id` en la consulta, debe coincidir con la
     * obra real del documento.
     */
    public function ver(int $documentoId)
    {
        $session   = session();
        $usuarioId = (int) $session->get('user_id');

        $documento = (new DocumentoModel())->find($documentoId);

        if ($documento === null) {
            return $this->response->setStatusCode(404)->setBody('Documento no encontrado');
        }

        $obraId = (int) $documento->obra_id;

        if (! (new InspectoresObrasModel())->esVigente($obraId, $usuarioId)) {
            return $this->response->setStatusCode(403)->setBody('Acceso denegado');
        }

        $obraSolicitada = (int) $this->request->getGet('obra_id');

        if ($obraSolicitada > 0 && $obraSolicitada !== $obraId) {
            return $this->response->setStatusCode(403)->setBody('Acceso denegado');
        }

        return $this->archivos->servirPdf($documento);
    }

    /**
     * Datos que consume la pantalla, idénticos en la vista en línea y en el
     * shell sin conexión.
     *
     * `logico_id` identifica el documento lógico (`documento_padre_id` o el
     * propio `id`): es la clave con la que se empareja la copia local con la
     * versión vigente del servidor, de modo que un reemplazo de versión se
     * detecte aunque cambie el identificador físico del PDF.
     */
    private function construirPayload(object $obra, array $documentos, int $usuarioId, ?string $usuarioNombre): array
    {
        $lista = [];

        foreach ($documentos as $documento) {
            $logicoId = $documento->documento_padre_id !== null
                ? (int) $documento->documento_padre_id
                : (int) $documento->id;

            $lista[] = [
                'id'             => (int) $documento->id,
                'logico_id'      => $logicoId,
                'obra_id'        => (int) $obra->id,
                'version'        => (int) $documento->version,
                'tipo_documento' => (string) ($documento->tipo_documento ?? 'DOCUMENTO'),
                'nombre'         => (string) ($documento->nombre_archivo_original ?? 'documento.pdf'),
                'fecha_documento' => $documento->fecha_documento,
                'tamano_bytes'   => (int) $documento->tamano_bytes,
                'url'            => site_url('/inspector/documentacion/ver/' . (int) $documento->id),
            ];
        }

        return [
            'obra' => [
                'id'         => (int) $obra->id,
                'codigo'     => (string) ($obra->codigo ?? ''),
                'nombre'     => (string) ($obra->nombre ?? ''),
                'expediente' => (string) ($obra->expediente_municipal ?? ''),
                'estado'     => (string) ($obra->estado_nombre ?? ''),
            ],
            'usuario' => [
                'id'    => $usuarioId,
                'nombre' => (string) ($usuarioNombre ?? ''),
            ],
            'documentos' => $lista,
            'api'    => site_url('/inspector/documentacion/api/' . (int) $obra->id),
        ];
    }
}
