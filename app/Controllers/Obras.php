<?php

namespace App\Controllers;

use App\Models\BarrioModel;
use App\Models\EmpresaModel;
use App\Models\EstadoObraModel;
use App\Models\ObraModel;
use App\Models\TipoLicitacionModel;

/**
 * Alta inicial de obras.
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
        $session = session();
        $roles   = $session->get('roles') ?? [];

        $expediente = trim((string) $this->request->getPost('expediente_municipal'));
        $nombre     = trim((string) $this->request->getPost('nombre'));
        $numeroLic  = trim((string) $this->request->getPost('numero_licitacion'));

        $barrioId         = $this->request->getPost('barrio_id');
        $empresaId        = $this->request->getPost('empresa_id');
        $tipoLicitacionId = $this->request->getPost('tipo_licitacion_id');

        $barrioId         = ($barrioId === '' || $barrioId === null) ? null : (int) $barrioId;
        $empresaId        = ($empresaId === '' || $empresaId === null) ? null : (int) $empresaId;
        $tipoLicitacionId = ($tipoLicitacionId === '' || $tipoLicitacionId === null) ? null : (int) $tipoLicitacionId;
        $numeroLic        = ($numeroLic === '') ? null : $numeroLic;

        $errores = [];

        if ($expediente === '') {
            $errores[] = 'El N° de expediente es obligatorio.';
        }

        if ($nombre === '') {
            $errores[] = 'El nombre de obra es obligatorio.';
        }

        $obraModel = new ObraModel();

        if ($expediente !== '' && $obraModel->existeExpediente($expediente)) {
            $errores[] = 'Ya existe una obra registrada con ese N° de expediente.';
        }

        /* Integridad referencial de catálogos opcionales */
        if ($barrioId !== null && (new BarrioModel())->find($barrioId) === null) {
            $errores[] = 'El barrio seleccionado no existe.';
        }

        if ($empresaId !== null && (new EmpresaModel())->find($empresaId) === null) {
            $errores[] = 'La empresa seleccionada no existe.';
        }

        if ($tipoLicitacionId !== null && (new TipoLicitacionModel())->find($tipoLicitacionId) === null) {
            $errores[] = 'El tipo de licitación seleccionado no existe.';
        }

        if ($errores !== []) {
            return redirect()->to($this->getDashboardPath($roles))
                ->withInput()
                ->with('errores_obra', $errores)
                ->with('reabrir_modal_obra', '1');
        }

        /* Estado inicial obligatorio: PREVIO INICIO (impuesto por el backend) */
        $estadoPrevio = (new EstadoObraModel())->findPrevioInicio();

        if ($estadoPrevio === null) {
            return redirect()->to($this->getDashboardPath($roles))
                ->withInput()
                ->with('errores_obra', ['El estado inicial PREVIO INICIO no se encuentra configurado en el sistema.'])
                ->with('reabrir_modal_obra', '1');
        }

        $codigo = $obraModel->generarCodigo();

        $obraModel->crear([
            'codigo'               => $codigo,
            'expediente_municipal' => mb_strtoupper($expediente),
            'nombre'               => mb_strtoupper($nombre),
            'barrio_id'            => $barrioId,
            'empresa_id'           => $empresaId,
            'tipo_licitacion_id'   => $tipoLicitacionId,
            'numero_licitacion'    => $numeroLic,
            'estado_obra_id'       => (int) $estadoPrevio->id,
        ]);

        return redirect()->to($this->getDashboardPath($roles))
            ->with('success', 'La obra fue registrada correctamente.');
    }
}