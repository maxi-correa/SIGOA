<?php

namespace App\Controllers;

use App\Libraries\PlazoObra;
use App\Models\EstadoObraModel;
use App\Models\NeutralizacionModel;
use App\Models\ObraModel;

/**
 * Neutralizaciones y reinicios de una obra (BLOQUE 5).
 *
 * Una neutralización suspende el cómputo del plazo: la obra pasa a estado
 * NEUTRALIZADA y deja de contar días hasta que se registra el reinicio,
 * que la devuelve a EN EJECUCIÓN. Los días efectivamente neutralizados se
 * suman a la fecha de finalización (BLOQUES 3/4).
 *
 * Reglas de servidor, no delegables al cliente:
 *  - la neutralización nace confirmada (se crea y cambia el estado en una
 *    misma transacción) y es inmutable;
 *  - solo una obra en ejecución con el plazo inicial confirmado puede
 *    neutralizarse, y solo una neutralización abierta puede reiniciarse;
 *  - nunca puede existir más de una neutralización abierta por obra;
 *  - un período nuevo no puede superponerse con los períodos ya cerrados;
 *  - ante un fallo lógico posterior a la escritura se revierte la
 *    transacción completa (rollback explícito).
 */
class Neutralizaciones extends BaseController
{
    private const ROLES_EDICION = ['SUPERADMINISTRADOR', 'ADMINISTRADOR'];

    /**
     * Pantalla de neutralizaciones de la obra.
     *
     * Accesible para SUPERADMINISTRADOR, ADMINISTRADOR y CONSULTA.
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

        $estadoModel   = new EstadoObraModel();
        $neutModel     = new NeutralizacionModel();
        $neutralizaciones = $neutModel->listarPorObra($id);
        $abierta       = $neutModel->abierta($id);
        $plazoConfirmado = (int) ($obra->plazo_inicial_confirmado ?? 0) === 1;
        $estadoNombre  = $obra->estado_nombre ?? '';

        return view('obras/neutralizaciones', [
            'titulo'             => 'Neutralizaciones de obra',
            'user_name'          => session()->get('user_name'),
            'username'           => session()->get('username'),
            'roles'              => $roles,
            'obra'               => $obra,
            'neutralizaciones'   => $neutralizaciones,
            'abierta'            => $abierta,
            'total_neutralizado_dias' => PlazoObra::totalNeutralizado($neutralizaciones),
            'proximo_numero'     => $neutModel->proximoNumero($id),
            'puede_editar'       => array_intersect(self::ROLES_EDICION, $roles) !== [],
            'plazo_confirmado'   => $plazoConfirmado,
            'permite_iniciar'    => $estadoModel->permiteIniciarNeutralizacion($estadoNombre)
                && $plazoConfirmado
                && $abierta === null,
            'permite_reiniciar'  => $estadoModel->permiteReiniciarNeutralizacion($estadoNombre)
                && $abierta !== null,
        ]);
    }

    /**
     * Registra una neutralización nueva y neutraliza la obra.
     */
    public function crear()
    {
        $obra = $this->obraDeEntrada();

        if (! is_object($obra)) {
            return $obra;
        }

        $destino = '/obras/neutralizaciones/' . (int) $obra->id;

        if (! $this->tieneRolEdicion()) {
            return redirect()->to($destino)
                ->with('error', 'No tiene autorización para registrar neutralizaciones.');
        }

        if (! (new EstadoObraModel())->permiteIniciarNeutralizacion($obra->estado_nombre ?? '')) {
            return redirect()->to($destino)
                ->with('error', 'Solo una obra en ejecución puede neutralizarse.');
        }

        if ((int) ($obra->plazo_inicial_confirmado ?? 0) !== 1) {
            return redirect()->to($destino)
                ->with('error', 'El plazo inicial de la obra debe estar confirmado antes de neutralizarla.');
        }

        if ((new NeutralizacionModel())->tieneAbierta((int) $obra->id)) {
            return redirect()->to($destino)
                ->with('error', 'La obra ya tiene una neutralización abierta.');
        }

        $neutModel = new NeutralizacionModel();
        $datos     = $this->tomarDatosNeutralizacion();
        $errores   = $this->validarNeutralizacion(
            $obra,
            $datos,
            $neutModel->ultimaFechaReinicio((int) $obra->id)
        );

        if ($errores !== []) {
            return redirect()->to($destino)
                ->withInput()
                ->with('errores_neutralizacion', $errores);
        }

        $db = db_connect();
        $db->transStart();

        $registrada = false;
        $erroresTx  = [];

        try {
            $this->bloquearObra((int) $obra->id);
            $actual = (new ObraModel())->findDetalle((int) $obra->id);

            if (
                $actual !== null
                && (int) ($actual->plazo_inicial_confirmado ?? 0) === 1
                && (new EstadoObraModel())->permiteIniciarNeutralizacion($actual->estado_nombre ?? '')
            ) {
                $neutModel = new NeutralizacionModel();

                if (! $neutModel->tieneAbierta((int) $obra->id)) {
                    $erroresTx = $this->validarNeutralizacion(
                        $actual,
                        $datos,
                        $neutModel->ultimaFechaReinicio((int) $obra->id)
                    );

                    if ($erroresTx === []) {
                        $registrada = $neutModel->crear((int) $obra->id, [
                            'numero'        => $neutModel->proximoNumero((int) $obra->id),
                            'fecha_inicio'  => $datos['fecha_inicio'],
                            'observaciones' => $datos['observaciones'],
                        ]);

                        if ($registrada) {
                            $registrada = $this->cambiarEstado(
                                (int) $obra->id,
                                EstadoObraModel::ESTADO_NEUTRALIZADA
                            );
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            $db->transRollback();

            return redirect()->to($destino)
                ->with('error', 'No se pudo registrar la neutralización. Verifique el estado de la obra.');
        }

        if (! $registrada) {
            $db->transRollback();

            if ($erroresTx !== []) {
                return redirect()->to($destino)
                    ->withInput()
                    ->with('errores_neutralizacion', $erroresTx);
            }

            return redirect()->to($destino)
                ->with('error', 'No se pudo registrar la neutralización. Verifique el estado de la obra.');
        }

        $db->transComplete();

        if (! $db->transStatus()) {
            return redirect()->to($destino)
                ->with('error', 'No se pudo registrar la neutralización. Verifique el estado de la obra.');
        }

        return redirect()->to($destino)
            ->with('success', 'La neutralización fue registrada y la obra quedó neutralizada.');
    }

    /**
     * Registra el reinicio de la neutralización abierta.
     */
    public function reiniciar()
    {
        $obra = $this->obraDeEntrada();

        if (! is_object($obra)) {
            return $obra;
        }

        $destino = '/obras/neutralizaciones/' . (int) $obra->id;

        if (! $this->tieneRolEdicion()) {
            return redirect()->to($destino)
                ->with('error', 'No tiene autorización para registrar reinicios.');
        }

        if (! (new EstadoObraModel())->permiteReiniciarNeutralizacion($obra->estado_nombre ?? '')) {
            return redirect()->to($destino)
                ->with('error', 'Solo una obra neutralizada puede reiniciarse.');
        }

        $abierta = (new NeutralizacionModel())->abierta((int) $obra->id);

        if ($abierta === null) {
            return redirect()->to($destino)
                ->with('error', 'La obra no tiene una neutralización abierta.');
        }

        $datos   = $this->tomarDatosReinicio();
        $errores = $this->validarReinicio($abierta, $datos);

        if ($errores !== []) {
            return redirect()->to($destino)
                ->withInput()
                ->with('errores_neutralizacion', $errores);
        }

        $db = db_connect();
        $db->transStart();

        $reiniciada = false;

        try {
            $this->bloquearObra((int) $obra->id);
            $actual = (new ObraModel())->findDetalle((int) $obra->id);

            if (
                $actual !== null
                && (new EstadoObraModel())->permiteReiniciarNeutralizacion($actual->estado_nombre ?? '')
            ) {
                $neutModel     = new NeutralizacionModel();
                $abiertaActual = $neutModel->abierta((int) $obra->id);

                if ($abiertaActual !== null) {
                    $reiniciada = $neutModel->reiniciar((int) $abiertaActual->id, $datos['fecha_reinicio']);

                    if ($reiniciada) {
                        $reiniciada = $this->cambiarEstado(
                            (int) $obra->id,
                            EstadoObraModel::ESTADO_EN_EJECUCION
                        );
                    }
                }
            }
        } catch (\Throwable $e) {
            $db->transRollback();

            return redirect()->to($destino)
                ->with('error', 'No se pudo registrar el reinicio de la obra.');
        }

        if (! $reiniciada) {
            $db->transRollback();

            return redirect()->to($destino)
                ->with('error', 'No se pudo registrar el reinicio de la obra.');
        }

        $db->transComplete();

        if (! $db->transStatus()) {
            return redirect()->to($destino)
                ->with('error', 'No se pudo registrar el reinicio de la obra.');
        }

        return redirect()->to($destino)
            ->with('success', 'El reinicio fue registrado y la obra volvió a ejecución.');
    }

    /**
     * Obra indicada en el POST, resolviendo la redirección de error.
     *
     * @return object|\CodeIgniter\HTTP\RedirectResponse
     */
    private function obraDeEntrada()
    {
        $roles  = session()->get('roles') ?? [];
        $obraId = (int) $this->request->getPost('obra_id');
        $obra   = (new ObraModel())->findDetalle($obraId);

        if ($obra === null) {
            return redirect()->to($this->getDashboardPath($roles))
                ->with('error', 'La obra seleccionada no existe.');
        }

        return $obra;
    }

    /**
     * Indica si el usuario tiene un rol con permiso de escritura.
     */
    private function tieneRolEdicion(): bool
    {
        return array_intersect(self::ROLES_EDICION, session()->get('roles') ?? []) !== [];
    }

    /**
     * Serializa las escrituras concurrentes sobre la misma obra.
     *
     * El bloqueo de fila solo existe en motores que lo soportan; SQLite
     * (entorno de pruebas) serializa las transacciones por sí mismo, por
     * lo que se omite. El índice único de neutralización abierta actúa
     * como última barrera en cualquier motor.
     */
    private function bloquearObra(int $obraId): void
    {
        $db = db_connect();

        if (stripos($db->getPlatform(), 'sqlite') !== false) {
            return;
        }

        $tabla = $db->prefixTable('obras');

        $db->query("SELECT id FROM {$tabla} WHERE id = ? FOR UPDATE", [$obraId]);
    }

    /**
     * Cambia el estado de la obra por nombre de catálogo.
     */
    private function cambiarEstado(int $obraId, string $estadoNombre): bool
    {
        $estado = (new EstadoObraModel())->findByEstado($estadoNombre);

        if ($estado === null) {
            return false;
        }

        return (new ObraModel())->update($obraId, [
            'estado_obra_id' => (int) $estado->id,
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Datos del formulario de neutralización normalizados.
     *
     * @return array<string, mixed>
     */
    private function tomarDatosNeutralizacion(): array
    {
        return [
            'fecha_inicio'  => $this->parseFecha($this->request->getPost('fecha_inicio')),
            'observaciones' => $this->textoOpcional($this->request->getPost('observaciones')),
        ];
    }

    /**
     * Datos del formulario de reinicio normalizados.
     *
     * @return array{fecha_reinicio: ?string}
     */
    private function tomarDatosReinicio(): array
    {
        return [
            'fecha_reinicio' => $this->parseFecha($this->request->getPost('fecha_reinicio')),
        ];
    }

    /**
     * Valida la fecha de una neutralización nueva.
     *
     * Las precondiciones de estado, plazo confirmado y ausencia de
     * neutralización abierta se comprueban antes y se informan como aviso
     * general, para que el mensaje no quede oculto tras un modal cerrado.
     *
     * La fecha no puede ser anterior al inicio de la obra ni anterior al
     * reinicio del último período cerrado: un período nuevo debe comenzar
     * a partir del fin del anterior para no solaparse y duplicar días.
     *
     * @param array<string, mixed> $datos
     *
     * @return list<string>
     */
    private function validarNeutralizacion(object $obra, array $datos, ?string $ultimaReinicio = null): array
    {
        if ($datos['fecha_inicio'] === null) {
            return ['La fecha de neutralización no es válida.'];
        }

        if (
            $obra->fecha_inicio !== null
            && strcmp($datos['fecha_inicio'], (string) $obra->fecha_inicio) < 0
        ) {
            return ['La fecha de neutralización no puede ser anterior al inicio de la obra.'];
        }

        if ($ultimaReinicio !== null && strcmp($datos['fecha_inicio'], $ultimaReinicio) < 0) {
            return ['La fecha de neutralización no puede ser anterior al reinicio del último período ('
                . PlazoObra::formatearFecha($ultimaReinicio)
                . '). Los períodos de neutralización no pueden superponerse.'];
        }

        return [];
    }

    /**
     * Valida la fecha de reinicio contra la neutralización abierta.
     *
     * @param array{fecha_reinicio: ?string} $datos
     *
     * @return list<string>
     */
    private function validarReinicio(object $abierta, array $datos): array
    {
        if ($datos['fecha_reinicio'] === null) {
            return ['La fecha de reinicio no es válida.'];
        }

        if (strcmp($datos['fecha_reinicio'], (string) $abierta->fecha_inicio) < 0) {
            return ['La fecha de reinicio no puede ser anterior a la fecha de la neutralización.'];
        }

        return [];
    }

    /**
     * Normaliza una fecha de formulario a `Y-m-d`.
     */
    private function parseFecha(mixed $valor): ?string
    {
        $texto = trim((string) $valor);

        if ($texto === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y'] as $formato) {
            $fecha   = \DateTimeImmutable::createFromFormat('!' . $formato, $texto);
            $errores = \DateTimeImmutable::getLastErrors();

            if ($fecha === false) {
                continue;
            }

            if ($errores !== false && ($errores['warning_count'] > 0 || $errores['error_count'] > 0)) {
                continue;
            }

            return $fecha->format('Y-m-d');
        }

        return null;
    }

    private function textoOpcional(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }
}
