<?php

namespace App\Controllers;

use App\Libraries\CertificacionObra;
use App\Models\CertificadoModel;
use App\Models\ObraModel;

/**
 * Pantalla de certificados de una obra: configuración económica,
 * bloqueo tras el primer certificado y carga de certificados.
 */
class Certificados extends BaseController
{
    /**
     * Pantalla de certificados de la obra.
     *
     * Accesible para SUPERADMINISTRADOR, ADMINISTRADOR y CONSULTA.
     * La edición queda reservada a los dos primeros roles.
     */
    public function index(int $id)
    {
        $roles     = session()->get('roles') ?? [];
        $obraModel = new ObraModel();
        $obra      = $obraModel->findDetalle($id);

        if ($obra === null) {
            return redirect()->to($this->getDashboardPath($roles))
                ->with('error', 'La obra seleccionada no existe.');
        }

        $certificadoModel = new CertificadoModel();
        $certificados     = $certificadoModel->listarPorObra($id);
        $cantidad         = count($certificados);
        $session          = session();

        return view('obras/certificados', [
            'titulo'                   => 'Certificados de obra',
            'user_name'                => $session->get('user_name'),
            'username'                 => $session->get('username'),
            'roles'                    => $roles,
            'obra'                     => $obra,
            'certificados'             => CertificacionObra::conAvances($obra, $certificados),
            'configuracion_confirmada' => CertificacionObra::estaConfirmada($obra),
            'configuracion_bloqueada'  => CertificacionObra::estaBloqueada($cantidad),
            'anticipo_total'           => CertificacionObra::anticipoTotal($obra),
            'meses'                    => CertificacionObra::meses(),
            'puede_editar'             => array_intersect(['SUPERADMINISTRADOR', 'ADMINISTRADOR'], $roles) !== [],
        ]);
    }

    /**
     * Confirma y persiste la configuración económica de certificación.
     *
     * No queda confirmada al editar los campos: solo este POST la establece.
     * Tras el primer certificado la configuración no puede modificarse.
     */
    public function confirmarConfiguracion()
    {
        $roles     = session()->get('roles') ?? [];
        $obraModel = new ObraModel();
        $obraId    = (int) $this->request->getPost('obra_id');
        $obra      = $obraModel->find($obraId);

        if ($obra === null) {
            return redirect()->to($this->getDashboardPath($roles))
                ->with('error', 'La obra seleccionada no existe.');
        }

        $destino = '/obras/certificados/' . $obraId;

        if (CertificacionObra::estaBloqueada((new CertificadoModel())->contarPorObra($obraId))) {
            return redirect()->to($destino)
                ->with('error', 'La configuración económica no puede modificarse porque ya se emitió el primer certificado.');
        }

        $datos   = $this->tomarDatosConfiguracion();
        $errores = CertificacionObra::validarConfiguracionParaCertificar($datos);

        if ($errores !== []) {
            return redirect()->to($destino)
                ->withInput()
                ->with('errores_configuracion', $errores);
        }

        $ok = $obraModel->actualizarConfiguracionEconomica($obraId, [
            'presupuesto_oficial'            => $datos['presupuesto_oficial'],
            'monto_contrato'                 => $datos['monto_contrato'],
            'tiene_anticipo_financiero'      => $datos['tiene_anticipo_financiero'],
            'porcentaje_anticipo_financiero' => $datos['porcentaje_anticipo_financiero'],
            'tiene_fondo_reparo'             => $datos['tiene_fondo_reparo'],
            'porcentaje_fondo_reparo'        => $datos['porcentaje_fondo_reparo'],
            'fondo_reparo_con_poliza'        => $datos['fondo_reparo_con_poliza'],
        ]);

        if (! $ok) {
            return redirect()->to($destino)
                ->with('error', 'No se pudo guardar la configuración económica.');
        }

        return redirect()->to($destino)
            ->with('success', 'La configuración económica fue confirmada. Ya puede comenzar a certificar.');
    }

    /**
     * Emite un certificado. Los descuentos, estados, fondo, neto y avances
     * se recalculan en servidor; no se confía en valores enviados por el cliente.
     */
    public function crear()
    {
        $roles     = session()->get('roles') ?? [];
        $obraModel = new ObraModel();
        $obraId    = (int) $this->request->getPost('obra_id');
        $obra      = $obraModel->find($obraId);

        if ($obra === null) {
            return redirect()->to($this->getDashboardPath($roles))
                ->with('error', 'La obra seleccionada no existe.');
        }

        $destino = '/obras/certificados/' . $obraId;

        if (! CertificacionObra::estaConfirmada($obra)) {
            return redirect()->to($destino)
                ->with('error', 'Debe confirmar la configuración económica antes de emitir certificados.');
        }

        $mes        = $this->request->getPost('mes');
        $anio       = $this->request->getPost('anio');
        $montoBruto = CertificacionObra::parseImporte($this->request->getPost('monto_bruto'));

        $certificadoModel = new CertificadoModel();
        $ultimo           = $certificadoModel->ultimoPorObra($obraId);
        $errores          = array_merge(
            CertificacionObra::validarDatosCertificado($mes, $anio, $montoBruto),
            CertificacionObra::validarPeriodoPosterior($mes, $anio, $ultimo)
        );

        if ($errores !== []) {
            return redirect()->to($destino)
                ->withInput()
                ->with('errores_certificado', $errores);
        }

        $db = db_connect();

        $db->transStart();

        $anteriores = $certificadoModel->listarPorObra($obraId);
        $calculado  = CertificacionObra::calcularValoresCertificado(
            $obra,
            $anteriores,
            (float) $montoBruto
        );

        $certificadoModel->crear([
            'obra_id'                => $obraId,
            'numero'                 => $certificadoModel->proximoNumero($obraId),
            'mes'                    => (int) $mes,
            'anio'                   => (int) $anio,
            'monto_bruto'            => $montoBruto,
            'descuento_anticipo'     => number_format($calculado['descuento_anticipo'], 3, '.', ''),
            'estado_anticipo'        => $calculado['estado_anticipo'],
            'retencion_fondo_reparo' => number_format($calculado['retencion_fondo_reparo'], 3, '.', ''),
            'monto_neto'             => number_format($calculado['monto_neto'], 3, '.', ''),
        ]);

        $db->transComplete();

        if (! $db->transStatus()) {
            return redirect()->to($destino)
                ->withInput()
                ->with('errores_certificado', ['No se pudo registrar el certificado.']);
        }

        return redirect()->to($destino)
            ->with('success', 'El certificado fue registrado correctamente.');
    }

    /**
     * @return array<string, mixed>
     */
    private function tomarDatosConfiguracion(): array
    {
        $tieneAnticipo = $this->request->getPost('tiene_anticipo_financiero');
        $tieneFondo    = $this->request->getPost('tiene_fondo_reparo');
        $conPoliza     = $this->request->getPost('fondo_reparo_con_poliza');

        $tieneAnticipo = ($tieneAnticipo === '1') ? 1 : 0;
        $tieneFondo    = ($tieneFondo === '1') ? 1 : 0;

        $porcentajeAnticipo = $tieneAnticipo
            ? CertificacionObra::parsePorcentaje($this->request->getPost('porcentaje_anticipo_financiero'))
            : null;

        $porcentajeFondo = $tieneFondo
            ? CertificacionObra::parsePorcentaje($this->request->getPost('porcentaje_fondo_reparo'))
            : null;

        if (! $tieneFondo) {
            $conPoliza = null;
        } elseif ($conPoliza === '1') {
            $conPoliza = 1;
        } elseif ($conPoliza === '0') {
            $conPoliza = 0;
        } else {
            $conPoliza = null;
        }

        return [
            'presupuesto_oficial'            => CertificacionObra::parseImporte($this->request->getPost('presupuesto_oficial')),
            'monto_contrato'                 => CertificacionObra::parseImporte($this->request->getPost('monto_contrato')),
            'tiene_anticipo_financiero'      => $tieneAnticipo,
            'porcentaje_anticipo_financiero' => $porcentajeAnticipo,
            'tiene_fondo_reparo'             => $tieneFondo,
            'porcentaje_fondo_reparo'        => $porcentajeFondo,
            'fondo_reparo_con_poliza'        => $conPoliza,
        ];
    }
}
