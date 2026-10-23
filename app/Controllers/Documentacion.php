<?php

namespace App\Controllers;

use App\Exceptions\ValidacionDocumentoException;
use App\Models\DocumentoModel;
use App\Models\EstadoObraModel;
use App\Models\NeutralizacionModel;
use App\Models\ObraModel;
use App\Models\TipoDocumentoModel;
use App\Services\DocumentoArchivo;
use App\Services\ObraAlmacenamiento;
use CodeIgniter\HTTP\RedirectResponse;

class Documentacion extends BaseController
{
    private const ROLES_ADMIN = ['SUPERADMINISTRADOR', 'ADMINISTRADOR'];
    private const ROLES_TODOS = ['SUPERADMINISTRADOR', 'ADMINISTRADOR', 'CONSULTA'];

    private const TIPO_ACTA_ENTREGA_VEHICULO = 'ACTA DE ENTREGA DE VEHÍCULO';
    private const TIPO_ACTA_DEVOLUCION_VEHICULO = 'ACTA DE DEVOLUCIÓN DE VEHÍCULO';
    private const TIPO_ACTA_INICIO = 'ACTA DE INICIO';
    private const TIPO_ACTA_NEUTRALIZACION = 'ACTA DE NEUTRALIZACIÓN';
    private const TIPO_ACTA_REINICIO = 'ACTA DE REINICIO';
    private const TIPO_ACTA_RECEPCION_PROVISORIA = 'ACTA DE RECEPCIÓN PROVISORIA';
    private const TIPO_ACTA_RECEPCION_DEFINITIVA = 'ACTA DE RECEPCIÓN DEFINITIVA';
    private const TIPO_DESIGNACION_INSPECCION = 'DESIGNACIÓN DE INSPECCIÓN';
    private const TIPO_PLIEGO = 'PLIEGO';
    private const TIPO_PLANO = 'PLANO';
    private const TIPO_OTROS = 'OTROS';

    private const TIPOS_INCLUIDOS = [
        'ACTA DE ENTREGA DE VEHÍCULO',
        'ACTA DE DEVOLUCIÓN DE VEHÍCULO',
        'ACTA DE INICIO',
        'ACTA DE NEUTRALIZACIÓN',
        'ACTA DE REINICIO',
        'ACTA DE RECEPCIÓN PROVISORIA',
        'ACTA DE RECEPCIÓN DEFINITIVA',
        'DESIGNACIÓN DE INSPECCIÓN',
        'PLIEGO',
        'PLANO',
        'OTROS',
    ];

    /**
     * Tipos que representan un único documento lógico por obra: una vez
     * cargado (versión vigente) dejan de ofrecerse en «Agregar documento».
     */
    private const TIPOS_UNICOS = [
        self::TIPO_ACTA_ENTREGA_VEHICULO,
        self::TIPO_ACTA_DEVOLUCION_VEHICULO,
        self::TIPO_ACTA_INICIO,
        self::TIPO_ACTA_RECEPCION_PROVISORIA,
        self::TIPO_ACTA_RECEPCION_DEFINITIVA,
        self::TIPO_DESIGNACION_INSPECCION,
        self::TIPO_PLIEGO,
    ];

    /**
     * Tipos que se repiten por evento: un documento por neutralización o por
     * reinicio, y varios por obra.
     */
    private const TIPOS_POR_EVENTO = [
        self::TIPO_ACTA_NEUTRALIZACION,
        self::TIPO_ACTA_REINICIO,
    ];

    /** Tipos que admiten varios documentos independientes por obra. */
    private const TIPOS_MULTIPLES = [
        self::TIPO_PLANO,
        self::TIPO_OTROS,
    ];

    public function index(int $obraId)
    {
        $roles = session()->get('roles') ?? [];
        if (array_intersect(self::ROLES_TODOS, $roles) === []) {
            return redirect()->to($this->getDashboardPath($roles))
                ->with('warning', 'No tiene autorización para acceder a esa sección.');
        }

        $obraModel = new ObraModel();
        $obra = $obraModel->findDetalle($obraId);
        if ($obra === null) {
            return redirect()->to($this->getDashboardPath($roles))
                ->with('error', 'La obra seleccionada no existe.');
        }

        $estadoModel = new EstadoObraModel();
        $obraEstado = strtoupper((string) ($obra->estado_nombre ?? ''));

        $documentoModel = new DocumentoModel();
        $documentos = $documentoModel->listarPorObra($obraId);
        $documentosAgrupados = $this->agruparDocumentos($documentos);

        $tipoModel = new TipoDocumentoModel();
        $tiposActivos = array_filter($tipoModel->findAllActivos(), function ($t) {
            return in_array($t->tipo_documento, self::TIPOS_INCLUIDOS, true);
        });

        $neutralizaciones = (new NeutralizacionModel())->listarPorObra($obraId);
        $entregaExistente = $this->existeDocumento($obraId, self::TIPO_ACTA_ENTREGA_VEHICULO);

        $neutralizacionesPendientes = [];
        $reiniciosDocumentables = [];
        $reiniciosPendientes = [];
        foreach ($neutralizaciones as $n) {
            $tieneActa = $this->existeActaNeutralizacion($obraId, (int) $n->id);

            if (! $tieneActa) {
                $neutralizacionesPendientes[] = $n;
            }

            if (! empty($n->fecha_reinicio) && $tieneActa) {
                $reiniciosDocumentables[] = $n;

                if (! $this->existeActaReinicio($obraId, (int) $n->id)) {
                    $reiniciosPendientes[] = $n;
                }
            }
        }

        $opcionesTipo = [];
        foreach ($tiposActivos as $tipo) {
            if ($this->tipoSatisfecho(
                $tipo->tipo_documento,
                $obraId,
                $neutralizaciones,
                $neutralizacionesPendientes,
                $reiniciosDocumentables,
                $reiniciosPendientes
            )) {
                continue;
            }

            [$habilitado, $motivo] = $this->disponibilidadTipo(
                $tipo->tipo_documento,
                $obraEstado,
                $obra,
                $neutralizaciones,
                $entregaExistente
            );

            $opcionesTipo[] = [
                'id' => $tipo->id,
                'nombre' => $tipo->tipo_documento,
                'habilitado' => $habilitado,
                'motivo' => $motivo,
            ];
        }

        return view('obras/documentacion', [
            'titulo' => 'Documentación de obra',
            'user_name' => session()->get('user_name'),
            'username' => session()->get('username'),
            'roles' => $roles,
            'obra' => $obra,
            'documentosAgrupados' => $documentosAgrupados,
            'opcionesTipo' => $opcionesTipo,
            'puede_editar' => array_intersect(self::ROLES_ADMIN, $roles) !== [],
            'neutralizacionesPendientes' => $neutralizacionesPendientes,
            'reiniciosPendientes' => $reiniciosPendientes,
        ]);
    }

    /**
     * Agrupa los documentos por tipo y por documento lógico (raíz del
     * versionado): el vigente se muestra como principal y el resto pasa al
     * historial, del más nuevo al más antiguo.
     *
     * @param array<int, object> $documentos
     *
     * @return array<string, array<int, array{actual: ?object, principal: ?object, historial: array<int, object>}>>
     */
    private function agruparDocumentos(array $documentos): array
    {
        $agrupados = [];

        foreach ($documentos as $doc) {
            $tipo   = $doc->tipo_documento ?? 'OTROS';
            $padre  = (int) ($doc->documento_padre_id ?: $doc->id);
            $actual = (int) $doc->es_version_actual === 1;

            if (! isset($agrupados[$tipo][$padre])) {
                $agrupados[$tipo][$padre] = ['actual' => null, 'principal' => null, 'historial' => []];
            }

            if ($actual && $agrupados[$tipo][$padre]['actual'] === null) {
                $agrupados[$tipo][$padre]['actual'] = $doc;
            } else {
                $agrupados[$tipo][$padre]['historial'][] = $doc;
            }
        }

        foreach ($agrupados as &$grupos) {
            foreach ($grupos as &$grupo) {
                usort($grupo['historial'], static function ($a, $b) {
                    return (int) $b->version <=> (int) $a->version;
                });

                if ($grupo['actual'] === null && $grupo['historial'] !== []) {
                    $grupo['actual'] = array_shift($grupo['historial']);
                }

                $grupo['principal'] = $grupo['actual'];
            }
            unset($grupo);
        }
        unset($grupos);

        return $agrupados;
    }

    /**
     * Determina si un tipo documental ya está satisfecho y, por lo tanto, no
     * debe ofrecerse en «Agregar documento».
     *
     * Los tipos únicos se satisfacen con su versión vigente. Los tipos por
     * evento solo se ocultan cuando existe al menos un evento documentable y
     * todos los eventos documentables ya tienen su acta vigente.
     *
     * @param array<int, object> $neutralizaciones
     * @param array<int, object> $neutralizacionesPendientes
     * @param array<int, object> $reiniciosDocumentables
     * @param array<int, object> $reiniciosPendientes
     */
    private function tipoSatisfecho(
        string $tipoNombre,
        int $obraId,
        array $neutralizaciones,
        array $neutralizacionesPendientes,
        array $reiniciosDocumentables,
        array $reiniciosPendientes
    ): bool {
        if ($tipoNombre === self::TIPO_ACTA_NEUTRALIZACION) {
            return $neutralizaciones !== [] && $neutralizacionesPendientes === [];
        }

        if ($tipoNombre === self::TIPO_ACTA_REINICIO) {
            return $reiniciosDocumentables !== [] && $reiniciosPendientes === [];
        }

        return in_array($tipoNombre, self::TIPOS_UNICOS, true)
            && $this->existeDocumento($obraId, $tipoNombre);
    }

    /**
     * Reglas de disponibilidad de un tipo documental según el estado de la
     * obra y las condiciones documentales ya existentes.
     *
     * Se comparte entre la pantalla, la carga y el reemplazo para que una
     * petición directa no pueda saltear las reglas que la interfaz muestra.
     *
     * @param array<int, object> $neutralizaciones
     *
     * @return array{0: bool, 1: ?string}
     */
    private function disponibilidadTipo(
        string $tipoNombre,
        string $obraEstado,
        object $obra,
        array $neutralizaciones,
        bool $entregaExistente
    ): array {
        switch ($tipoNombre) {
            case self::TIPO_ACTA_ENTREGA_VEHICULO:
                if ($obraEstado !== 'PREVIO INICIO' && $obraEstado !== 'EN EJECUCIÓN') {
                    return [false, 'Solo disponible cuando la obra está en PREVIO INICIO o EN EJECUCIÓN.'];
                }
                break;

            case self::TIPO_ACTA_DEVOLUCION_VEHICULO:
                if (! $entregaExistente) {
                    return [false, 'Debe existir un acta de entrega de vehículo cargada para la obra.'];
                }
                break;

            case self::TIPO_ACTA_INICIO:
                if (empty($obra->fecha_inicio)) {
                    return [false, 'Debe existir fecha de inicio registrada.'];
                }
                break;

            case self::TIPO_ACTA_NEUTRALIZACION:
                foreach ($neutralizaciones as $n) {
                    if (! empty($n->fecha_inicio)) {
                        return [true, null];
                    }
                }

                return [false, 'Debe existir una fecha de neutralización registrada.'];

            case self::TIPO_ACTA_REINICIO:
                $tieneReinicio = false;
                $motivoReinicio = null;

                foreach ($neutralizaciones as $n) {
                    if (empty($n->fecha_reinicio)) {
                        continue;
                    }

                    if (! $this->existeActaNeutralizacion((int) $obra->id, (int) $n->id)) {
                        $motivoReinicio = 'Debe existir el acta de neutralización correspondiente cargada para la obra.';
                        continue;
                    }

                    $tieneReinicio = true;
                    break;
                }

                if (! $tieneReinicio) {
                    return [false, $motivoReinicio
                        ?? 'Debe estar registrada la fecha del reinicio que se desea documentar.'];
                }
                break;

            case self::TIPO_ACTA_RECEPCION_PROVISORIA:
                if ($obraEstado !== 'EN EJECUCIÓN') {
                    return [false, 'Solo disponible cuando la obra está EN EJECUCIÓN.'];
                }
                break;

            case self::TIPO_ACTA_RECEPCION_DEFINITIVA:
                if ($obraEstado !== 'EN PLAZO DE CONSERVACIÓN') {
                    return [false, 'Solo disponible cuando la obra está EN PLAZO DE CONSERVACIÓN.'];
                }
                break;

            case self::TIPO_DESIGNACION_INSPECCION:
                if ($obraEstado !== 'PREVIO INICIO' && $obraEstado !== 'EN EJECUCIÓN') {
                    return [false, 'Solo disponible cuando la obra está en PREVIO INICIO o EN EJECUCIÓN.'];
                }
                break;
        }

        return [true, null];
    }

    private function existeDocumento(int $obraId, string $tipoNombre): bool
    {
        $tipoModel = new TipoDocumentoModel();
        $tipo = $tipoModel->findByNombre($tipoNombre);
        if ($tipo === null) {
            return false;
        }
        $docModel = new DocumentoModel();
        $existente = $docModel->where('obra_id', $obraId)
            ->where('tipo_documento_id', $tipo->id)
            ->where('es_version_actual', 1)
            ->first();
        return $existente !== null;
    }

    /**
     * ¿Existe el acta de neutralización cargada para una neutralización
     * determinada de la obra? Solo cuenta la versión vigente.
     */
    private function existeActaNeutralizacion(int $obraId, int $neutralizacionId): bool
    {
        $tipo = (new TipoDocumentoModel())->findByNombre(self::TIPO_ACTA_NEUTRALIZACION);
        if ($tipo === null) {
            return false;
        }
        return (new DocumentoModel())->where('obra_id', $obraId)
            ->where('tipo_documento_id', $tipo->id)
            ->where('neutralizacion_id', $neutralizacionId)
            ->where('es_version_actual', 1)
            ->first() !== null;
    }

    /**
     * ¿Existe el acta de reinicio vigente para un reinicio determinado?
     */
    private function existeActaReinicio(int $obraId, int $reinicioId): bool
    {
        $tipo = (new TipoDocumentoModel())->findByNombre(self::TIPO_ACTA_REINICIO);
        if ($tipo === null) {
            return false;
        }

        return (new DocumentoModel())->where('obra_id', $obraId)
            ->where('tipo_documento_id', $tipo->id)
            ->where('reinicio_id', $reinicioId)
            ->where('es_version_actual', 1)
            ->first() !== null;
    }

    public function subir()
    {
        $roles = session()->get('roles') ?? [];
        if (array_intersect(self::ROLES_ADMIN, $roles) === []) {
            return redirect()->to($this->getDashboardPath($roles))
                ->with('warning', 'No tiene autorización para realizar esta acción.');
        }

        $obraId = (int) $this->request->getPost('obra_id');
        $obraModel = new ObraModel();
        $obra = $obraModel->find($obraId);
        if ($obra === null) {
            return redirect()->to($this->getDashboardPath($roles))
                ->with('error', 'La obra seleccionada no existe.');
        }

        $destino = '/obras/documentacion/' . $obraId;

        $tipoId = (int) $this->request->getPost('tipo_documento_id');
        $tipoModel = new TipoDocumentoModel();
        $tipo = $tipoModel->find($tipoId);
        if ($tipo === null || !in_array($tipo->tipo_documento, self::TIPOS_INCLUIDOS, true)) {
            return $this->volverConErrorDocumento($destino, ['Tipo de documento no válido.']);
        }

        $tipoNombre = $tipo->tipo_documento;

        /*
         * Eventos vinculados, validados antes de tocar el archivo: si la
         * condición documental no se cumple no se escribe nada en el
         * almacenamiento ni en la base de datos.
         */
        $neutralizacionId = null;
        $reinicioId = null;

        if ($tipoNombre === self::TIPO_ACTA_NEUTRALIZACION) {
            $neutralizacionId = (int) $this->request->getPost('neutralizacion_id');
            if ($neutralizacionId <= 0) {
                return $this->volverConErrorDocumento($destino, ['Debe seleccionar la neutralización correspondiente.']);
            }
            $nModel = new NeutralizacionModel();
            $n = $nModel->where('id', $neutralizacionId)->where('obra_id', $obraId)->first();
            if ($n === null) {
                return $this->volverConErrorDocumento($destino, ['Neutralización inválida para esta obra.']);
            }
        } elseif ($tipoNombre === self::TIPO_ACTA_REINICIO) {
            $reinicioId = (int) $this->request->getPost('reinicio_id');
            if ($reinicioId <= 0) {
                return $this->volverConErrorDocumento($destino, ['Debe seleccionar el reinicio correspondiente.']);
            }
            $nModel = new NeutralizacionModel();
            $n = $nModel->where('id', $reinicioId)->where('obra_id', $obraId)->first();
            if ($n === null) {
                return $this->volverConErrorDocumento($destino, ['Reinicio inválido para esta obra.']);
            }
            if (empty($n->fecha_reinicio)) {
                return $this->volverConErrorDocumento($destino, ['Debe estar registrada la fecha del reinicio que se desea documentar.']);
            }
            if (! $this->existeActaNeutralizacion($obraId, (int) $n->id)) {
                return $this->volverConErrorDocumento($destino, ['Debe existir el acta de neutralización correspondiente cargada para la obra.']);
            }
            // El acta de reinicio documenta el reinicio de una
            // neutralización: se vincula a ambos eventos de la misma fila.
            $neutralizacionId = (int) $n->id;
        }

        /*
         * Reglas de disponibilidad del tipo (estado de obra, actas
         * requeridas). Se revalidan en el servidor aunque la interfaz solo
         * ofrezca los tipos disponibles.
         */
        $estadoModel = new EstadoObraModel();
        $obraEstado = strtoupper((string) ($estadoModel->find($obra->estado_obra_id)->estado ?? ''));
        $neutralizaciones = (new NeutralizacionModel())->listarPorObra($obraId);
        [$habilitado, $motivo] = $this->disponibilidadTipo(
            $tipoNombre,
            $obraEstado,
            $obra,
            $neutralizaciones,
            $this->existeDocumento($obraId, self::TIPO_ACTA_ENTREGA_VEHICULO)
        );
        if (! $habilitado) {
            return $this->volverConErrorDocumento($destino, [$motivo]);
        }

        /*
         * Un tipo no múltiple admite un único documento lógico por obra o
         * por evento. Si ya hay versión vigente debe usarse «Reemplazar
         * documento», que conserva el historial.
         */
        if (! in_array($tipoNombre, self::TIPOS_MULTIPLES, true)) {
            $existente = (new DocumentoModel())->obtenerVersionActual($obraId, $tipo->id, $neutralizacionId, $reinicioId);
            if ($existente !== null) {
                return $this->volverConErrorDocumento($destino, [
                    'Ya existe un documento vigente de este tipo. Utilice «Reemplazar documento».',
                ]);
            }
        }

        try {
            $this->prepararArchivoPdf();
        } catch (ValidacionDocumentoException $e) {
            return $this->volverConErrorDocumento($destino, [$e->getMessage()]);
        }

        $almacenamiento = new ObraAlmacenamiento();
        if (! $almacenamiento->asegurarEstructuraObra($obra->codigo)) {
            return $this->volverConErrorDocumento($destino, ['No se pudo preparar el almacenamiento de la obra.']);
        }

        $docModel = new DocumentoModel();
        $usuarioId = session()->get('user_id');
        $esMultiple = in_array($tipoNombre, self::TIPOS_MULTIPLES, true);

        /*
         * El nombre físico se deriva del tipo del registro SIGOA y de la
         * versión calculada desde el historial, nunca del nombre original del
         * PDF. Para los tipos múltiples el identificador es el id raíz, que
         * solo se conoce tras insertar la fila: hasta entonces se compone el
         * nombre sin identificador y se corrige en el mismo registro.
         */
        $version = 1;
        $nombreGenerado = $almacenamiento->nombrePdfDocumento($obra->codigo, $tipoNombre, $version);
        if ($nombreGenerado === null) {
            return $this->volverConErrorDocumento($destino, ['No se pudo determinar el nombre del documento.']);
        }

        $insertId = $docModel->insert([
            'obra_id' => $obraId,
            'tipo_documento_id' => $tipo->id,
            'nombre_archivo_original' => $this->nombreOriginalPdf(),
            'nombre_archivo' => $nombreGenerado,
            'ruta_relativa' => $almacenamiento->normalizarCodigo($obra->codigo) . '/' . $nombreGenerado,
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'tamano_bytes' => $this->tamanoPdf(),
            'fecha_documento' => date('Y-m-d'),
            'observaciones' => $this->request->getPost('observaciones'),
            'usuario_id_carga' => $usuarioId ? (int) $usuarioId : null,
            'neutralizacion_id' => $neutralizacionId ?: null,
            'reinicio_id' => $reinicioId ?: null,
            'version' => $version,
            'es_version_actual' => 1,
            'documento_padre_id' => null,
        ]);

        if ($insertId === false) {
            return $this->volverConErrorDocumento($destino, ['No se pudo guardar el registro del documento.']);
        }

        $insertId = (int) $insertId;

        if ($esMultiple) {
            $identificador = $insertId;
            $nombreGenerado = $almacenamiento->nombrePdfDocumento($obra->codigo, $tipoNombre, $version, $identificador);
            if ($nombreGenerado === null
                || $docModel->update($insertId, [
                    'nombre_archivo' => $nombreGenerado,
                    'ruta_relativa' => $almacenamiento->normalizarCodigo($obra->codigo) . '/' . $nombreGenerado,
                ]) === false
            ) {
                $docModel->delete($insertId, true);
                return $this->volverConErrorDocumento($destino, ['No se pudo determinar el nombre del documento.']);
            }
        }

        $relativa = $almacenamiento->normalizarCodigo($obra->codigo) . '/' . $nombreGenerado;
        if ($almacenamiento->existeArchivoRelativo($relativa)) {
            $docModel->delete($insertId, true);
            return $this->volverConErrorDocumento($destino, ['Ya existe un archivo con el nombre generado. Intente nuevamente.']);
        }

        if (! $this->moverArchivoPdf($almacenamiento, $obra->codigo, $nombreGenerado)) {
            $docModel->delete($insertId, true);
            return $this->volverConErrorDocumento($destino, ['No se pudo guardar el archivo.']);
        }

        return redirect()->to($destino)
            ->with('success', 'El documento fue cargado correctamente.');
    }

    /**
     * Reemplaza el documento vigente por una nueva versión.
     *
     * Solo se ofrece a administradores. La obra se deriva del propio
     * documento y debe coincidir con el `obra_id` posteado; solo puede
     * reemplazarse la versión vigente. La versión reemplazada nunca se
     * elimina: queda en el historial con `es_version_actual = 0`.
     */
    public function reemplazar()
    {
        $roles = session()->get('roles') ?? [];
        if (array_intersect(self::ROLES_ADMIN, $roles) === []) {
            return redirect()->to($this->getDashboardPath($roles))
                ->with('warning', 'No tiene autorización para realizar esta acción.');
        }

        $documentoId = (int) $this->request->getPost('documento_id');
        $docModel = new DocumentoModel();
        $doc = $docModel->find($documentoId);
        if ($doc === null) {
            return redirect()->to($this->getDashboardPath($roles))
                ->with('error', 'El documento seleccionado no existe.');
        }

        $obraId = (int) $doc->obra_id;
        $destino = '/obras/documentacion/' . $obraId;

        if ((int) $this->request->getPost('obra_id') !== $obraId) {
            return redirect()->to($destino)
                ->with('errores_documento', ['La obra del documento no coincide con la indicada.']);
        }

        if ((int) $doc->es_version_actual !== 1) {
            return redirect()->to($destino)
                ->with('errores_documento', ['Solo puede reemplazarse la versión vigente del documento.']);
        }

        $obra = (new ObraModel())->find($obraId);
        if ($obra === null) {
            return redirect()->to($destino)
                ->with('error', 'La obra del documento no existe.');
        }

        $tipo = (new TipoDocumentoModel())->find($doc->tipo_documento_id);
        $tipoNombre = $tipo->tipo_documento ?? '';

        /*
         * Se revalida la pertenencia de los eventos: la nueva versión
         * hereda los vínculos del documento reemplazado, pero solo si
         * siguen siendo válidos para la obra.
         */
        if ($doc->neutralizacion_id !== null) {
            $pertenece = (new NeutralizacionModel())
                ->where('id', (int) $doc->neutralizacion_id)
                ->where('obra_id', $obraId)
                ->first();

            if ($pertenece === null) {
                return redirect()->to($destino)
                    ->with('errores_documento', ['La neutralización del documento ya no pertenece a esta obra.']);
            }
        }

        if ($doc->reinicio_id !== null) {
            $reinicio = (new NeutralizacionModel())
                ->where('id', (int) $doc->reinicio_id)
                ->where('obra_id', $obraId)
                ->first();

            if ($reinicio === null || empty($reinicio->fecha_reinicio)) {
                return redirect()->to($destino)
                    ->with('errores_documento', ['El reinicio del documento ya no es válido para esta obra.']);
            }
        }

        try {
            $this->prepararArchivoPdf();
        } catch (ValidacionDocumentoException $e) {
            return redirect()->to($destino)->with('errores_documento', [$e->getMessage()]);
        }

        $almacenamiento = new ObraAlmacenamiento();
        if (! $almacenamiento->asegurarEstructuraObra($obra->codigo)) {
            return redirect()->to($destino)
                ->with('errores_documento', ['No se pudo preparar el almacenamiento de la obra.']);
        }

        $esMultiple = in_array($tipoNombre, self::TIPOS_MULTIPLES, true);
        $identificador = $esMultiple
            ? (int) ($doc->documento_padre_id ?? $doc->id)
            : null;
        $version = $docModel->siguienteVersion(
            $obraId,
            (int) $doc->tipo_documento_id,
            $doc->neutralizacion_id !== null ? (int) $doc->neutralizacion_id : null,
            $doc->reinicio_id !== null ? (int) $doc->reinicio_id : null
        );

        $nombreGenerado = $almacenamiento->nombrePdfDocumento($obra->codigo, $tipoNombre, $version, $identificador);
        if ($nombreGenerado === null) {
            return redirect()->to($destino)
                ->with('errores_documento', ['No se pudo determinar el nombre del documento.']);
        }

        $relativa = $almacenamiento->normalizarCodigo($obra->codigo) . '/' . $nombreGenerado;

        if ($almacenamiento->existeArchivoRelativo($relativa)) {
            return redirect()->to($destino)
                ->with('errores_documento', ['Ya existe un archivo con el nombre generado. Intente nuevamente.']);
        }

        if (! $this->moverArchivoPdf($almacenamiento, $obra->codigo, $nombreGenerado)) {
            return redirect()->to($destino)
                ->with('errores_documento', ['No se pudo guardar el archivo.']);
        }

        $rutaCompleta = $almacenamiento->raiz() . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relativa);

        $usuarioId = session()->get('user_id');
        $nuevoId = $docModel->crearVersionDe($documentoId, [
            'nombre_archivo_original' => $this->nombreOriginalPdf(),
            'nombre_archivo' => $nombreGenerado,
            'ruta_relativa' => $relativa,
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'tamano_bytes' => $this->tamanoPdf(),
            'fecha_documento' => date('Y-m-d'),
            'observaciones' => $this->request->getPost('observaciones'),
            'usuario_id_carga' => $usuarioId ? (int) $usuarioId : null,
        ]);

        if ($nuevoId === null) {
            @unlink($rutaCompleta);
            return redirect()->to($destino)
                ->with('errores_documento', ['No se pudo registrar la nueva versión del documento.']);
        }

        return redirect()->to($destino)
            ->with('success', 'El documento fue reemplazado por una nueva versión.');
    }

    /**
     * Valida el archivo PDF subido (extensión, contenido real y tamaño)
     * deteniendo la petición con un error si no es admisible.
     *
     * Se comparte entre la carga y el reemplazo para que ambas apliquen
     * exactamente las mismas reglas.
     */
    private function prepararArchivoPdf(): void
    {
        $file = $this->request->getFile('archivo');

        if ($file === null || ! $file->isValid()) {
            $this->detenerConErrorDocumento('Debe seleccionar un archivo PDF válido.');
        }

        if ($file->getExtension() !== 'pdf') {
            $this->detenerConErrorDocumento('Solo se permiten archivos PDF.');
        }

        if ($file->getSize() > 10 * 1024 * 1024) {
            $this->detenerConErrorDocumento('El archivo excede el tamaño máximo permitido (10MB).');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeReal = $finfo !== false ? finfo_file($finfo, $file->getTempName()) : false;
        if ($finfo !== false) {
            finfo_close($finfo);
        }

        if ($mimeReal !== 'application/pdf') {
            $this->detenerConErrorDocumento('El archivo no parece ser un PDF válido.');
        }
    }

    private function detenerConErrorDocumento(string $mensaje): never
    {
        throw new ValidacionDocumentoException($mensaje);
    }

    /**
     * Redirige a la pantalla de documentación conservando los datos
     * enviados y solicitando la reapertura del modal «Agregar documento»,
     * de modo que el usuario pueda corregir el formulario sin volver a
     * completarlo.
     *
     * @param array<int, string> $errores
     */
    private function volverConErrorDocumento(string $destino, array $errores): RedirectResponse
    {
        return redirect()->to($destino)
            ->withInput()
            ->with('errores_documento', $errores)
            ->with('reabrir_modal_documento', 'alta');
    }

    private function moverArchivoPdf(ObraAlmacenamiento $almacenamiento, string $codigo, string $nombreGenerado): bool
    {
        $file = $this->request->getFile('archivo');
        if ($file === null) {
            return false;
        }

        $directorio = $almacenamiento->raiz() . DIRECTORY_SEPARATOR
            . $almacenamiento->normalizarCodigo($codigo);

        return (bool) $file->move($directorio, $nombreGenerado);
    }

    private function nombreOriginalPdf(): string
    {
        $file = $this->request->getFile('archivo');

        return $file !== null ? $file->getClientName() : 'documento.pdf';
    }

    private function tamanoPdf(): int
    {
        $file = $this->request->getFile('archivo');

        return $file !== null ? (int) $file->getSize() : 0;
    }

    public function ver(int $documentoId)
    {
        $roles = session()->get('roles') ?? [];
        if (array_intersect(self::ROLES_TODOS, $roles) === []) {
            return $this->response->setStatusCode(403)->setBody('Acceso denegado');
        }

        $docModel = new DocumentoModel();
        $doc = $docModel->find($documentoId);
        if ($doc === null) {
            return $this->response->setStatusCode(404)->setBody('Documento no encontrado');
        }

        // Verificar autorización sobre obra
        $obraModel = new ObraModel();
        $obra = $obraModel->find($doc->obra_id);
        if ($obra === null) {
            return $this->response->setStatusCode(404)->setBody('Obra no encontrada');
        }

        return (new DocumentoArchivo())->servirPdf($doc);
    }

    public function ubicacion(int $documentoId)
    {
        $roles = session()->get('roles') ?? [];
        if (array_intersect(self::ROLES_ADMIN, $roles) === []) {
            return $this->response->setJSON(['ok' => false, 'error' => 'No autorizado'])->setStatusCode(403);
        }

        $docModel = new DocumentoModel();
        $doc = $docModel->find($documentoId);
        if ($doc === null) {
            return $this->response->setJSON(['ok' => false, 'error' => 'No encontrado'])->setStatusCode(404);
        }

        $obraModel = new ObraModel();
        $obra = $obraModel->find($doc->obra_id);
        if ($obra === null) {
            return $this->response->setJSON(['ok' => false, 'error' => 'Obra no encontrada'])->setStatusCode(404);
        }

        $rutaNorm = $this->rutaAbsolutaAutorizada($doc->ruta_relativa);
        if ($rutaNorm === null) {
            return $this->response->setJSON(['ok' => false, 'error' => 'Ruta no autorizada'])->setStatusCode(403);
        }

        if (! is_file($rutaNorm)) {
            return $this->response->setJSON(['ok' => false, 'error' => 'Archivo no encontrado'])->setStatusCode(404);
        }

        return $this->response->setJSON([
            'ok' => true,
            'ruta_completa' => $rutaNorm,
            'directorio' => dirname($rutaNorm),
            'ruta_relativa' => $doc->ruta_relativa,
        ]);
    }

    /**
     * Resuelve la ruta relativa de un documento contra la raíz de
     * almacenamiento y la devuelve solo si queda realmente dentro de la raíz
     * autorizada.
     *
     * `ObraAlmacenamiento::absolutoDesdeRelativa()` rechaza travesías y
     * rutas absolutas; la comprobación con separador final evita aceptar
     * una raíz hermana que comparta prefijo (p. ej. `/storage-otra`).
     *
     * La resolución no exige que el archivo exista: la ausencia del PDF en
     * el almacenamiento se informa como 404, y solo la ruta no autorizada se
     * responde con 403.
     */
    private function rutaAbsolutaAutorizada(string $rutaRelativa): ?string
    {
        return (new DocumentoArchivo())->rutaNormalizada($rutaRelativa);
    }
}
