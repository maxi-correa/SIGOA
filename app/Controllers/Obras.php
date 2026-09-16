<?php

namespace App\Controllers;

use App\Models\BarrioModel;
use App\Models\EmpresaModel;
use App\Models\EstadoObraModel;
use App\Models\ObraModel;
use App\Models\TipoLicitacionModel;

/**
 * Alta inicial y edición de datos básicos de obras.
 *
 * El listado se presenta dentro del dashboard administrativo
 * (Admin\Dashboard y Superadmin\Dashboard).
 */
class Obras extends BaseController
{
    /**
     * Procesa el alta inicial de una obra.
     *
     * El estado se impone desde el backend: toda obra nueva nace en
     * PREVIO INICIO. Ningún valor de estado enviado por el cliente es
     * considerado.
     */
    public function crear()
    {
        $roles   = session()->get('roles') ?? [];
        $obraModel = new ObraModel();

        $datos   = $this->tomarDatosObra();
        $errores = $this->validarDatosObra($datos);

        if ($errores !== []) {
            return redirect()->to($this->getDashboardPath($roles))
                ->withInput()
                ->with('errores_obra', $errores)
                ->with('reabrir_modal_obra', 'alta');
        }

        /* Estado inicial obligatorio: PREVIO INICIO (impuesto por el backend) */
        $estadoPrevio = (new EstadoObraModel())->findPrevioInicio();

        if ($estadoPrevio === null) {
            return redirect()->to($this->getDashboardPath($roles))
                ->withInput()
                ->with('errores_obra', ['El estado inicial PREVIO INICIO no se encuentra configurado en el sistema.'])
                ->with('reabrir_modal_obra', 'alta');
        }

        $codigo = $obraModel->generarCodigo();

        $obraModel->crear([
            'codigo'               => $codigo,
            'expediente_municipal' => mb_strtoupper($datos['expediente']),
            'nombre'               => mb_strtoupper($datos['nombre']),
            'barrio_id'            => $datos['barrio_id'],
            'empresa_id'           => $datos['empresa_id'],
            'tipo_licitacion_id'   => $datos['tipo_licitacion_id'],
            'numero_licitacion'    => $datos['numero_licitacion'],
            'estado_obra_id'       => (int) $estadoPrevio->id,
        ]);

        return redirect()->to($this->getDashboardPath($roles))
            ->with('success', 'La obra fue registrada correctamente.');
    }

    /**
     * Procesa la edición de los datos básicos de una obra existente.
     *
     * La obra se identifica únicamente mediante su `id` interno. El código
     * OBR-XXXXXX no se modifica ni se vuelve a generar, y `created_at`
     * se conserva.
     */
    public function actualizar()
    {
        $roles   = session()->get('roles') ?? [];
        $obraModel = new ObraModel();

        $obraId = (int) $this->request->getPost('obra_id');
        $obra   = $obraModel->find($obraId);

        if ($obra === null) {
            return redirect()->to($this->getDashboardPath($roles))
                ->with('error', 'La obra seleccionada no existe.');
        }

        $datos = $this->tomarDatosObra();

        $estadoId = $this->request->getPost('estado_obra_id');
        $estadoId = ($estadoId === '' || $estadoId === null) ? null : (int) $estadoId;

        $errores = $this->validarDatosObra($datos, $obraId);

        if ($estadoId === null || (new EstadoObraModel())->find($estadoId) === null) {
            $errores[] = 'El estado seleccionado no existe.';
        }

        if ($errores !== []) {
            return redirect()->to($this->getDashboardPath($roles))
                ->withInput()
                ->with('errores_obra', $errores)
                ->with('obra_edicion_codigo', $obra->codigo)
                ->with('reabrir_modal_obra', 'edicion');
        }

        $actualizado = $obraModel->actualizar($obraId, [
            'expediente_municipal' => mb_strtoupper($datos['expediente']),
            'nombre'               => mb_strtoupper($datos['nombre']),
            'barrio_id'            => $datos['barrio_id'],
            'empresa_id'           => $datos['empresa_id'],
            'tipo_licitacion_id'   => $datos['tipo_licitacion_id'],
            'numero_licitacion'    => $datos['numero_licitacion'],
            'estado_obra_id'       => $estadoId,
        ]);

        if (! $actualizado) {
            return redirect()->to($this->getDashboardPath($roles))
                ->with('error', 'No se pudieron guardar los cambios en la obra.');
        }

        return redirect()->to($this->getDashboardPath($roles))
            ->with('success', 'Los datos de la obra fueron actualizados correctamente.');
    }

    /**
     * Toma y normaliza los datos recibidos del formulario de obra.
     *
     * Los selectores de catálogos opcionales y el número de licitación
     * vacíos se convierten en NULL.
     *
     * @return mixed[]
     */
    private function tomarDatosObra(): array
    {
        $expediente = trim((string) $this->request->getPost('expediente_municipal'));
        $nombre     = trim((string) $this->request->getPost('nombre'));
        $numeroLic  = trim((string) $this->request->getPost('numero_licitacion'));

        $barrioId         = $this->request->getPost('barrio_id');
        $empresaId        = $this->request->getPost('empresa_id');
        $tipoLicitacionId = $this->request->getPost('tipo_licitacion_id');

        return [
            'expediente'         => $expediente,
            'nombre'             => $nombre,
            'numero_licitacion'  => ($numeroLic === '') ? null : $numeroLic,
            'barrio_id'          => ($barrioId === '' || $barrioId === null) ? null : (int) $barrioId,
            'empresa_id'         => ($empresaId === '' || $empresaId === null) ? null : (int) $empresaId,
            'tipo_licitacion_id' => ($tipoLicitacionId === '' || $tipoLicitacionId === null) ? null : (int) $tipoLicitacionId,
        ];
    }

    /**
     * Valida los datos básicos de una obra.
     *
     * El expediente debe ser único; al editar se excluye la propia obra
     * ($exceptoObraId) para permitir conservar el expediente actual.
     *
     * @param mixed[] $datos
     *
     * @return list<string>
     */
    private function validarDatosObra(array $datos, ?int $exceptoObraId = null): array
    {
        $errores = [];

        if ($datos['expediente'] === '') {
            $errores[] = 'El N° de expediente es obligatorio.';
        }

        if ($datos['nombre'] === '') {
            $errores[] = 'El nombre de obra es obligatorio.';
        }

        if ($datos['expediente'] !== '' && (new ObraModel())->existeExpediente($datos['expediente'], $exceptoObraId)) {
            $errores[] = 'Ya existe una obra registrada con ese N° de expediente.';
        }

        /* Integridad referencial de catálogos opcionales */
        if ($datos['barrio_id'] !== null && (new BarrioModel())->find($datos['barrio_id']) === null) {
            $errores[] = 'El barrio seleccionado no existe.';
        }

        if ($datos['empresa_id'] !== null && (new EmpresaModel())->find($datos['empresa_id']) === null) {
            $errores[] = 'La empresa seleccionada no existe.';
        }

        if ($datos['tipo_licitacion_id'] !== null && (new TipoLicitacionModel())->find($datos['tipo_licitacion_id']) === null) {
            $errores[] = 'El tipo de licitación seleccionado no existe.';
        }

        return $errores;
    }
}