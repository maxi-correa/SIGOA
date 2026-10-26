<?php

namespace App\Controllers;

use App\Libraries\PlazoObra;
use App\Models\EstadoObraModel;
use App\Models\ObraModel;
use App\Models\RecepcionDefinitivaModel;
use App\Models\RecepcionProvisoriaModel;
use App\Models\DocumentoModel;
use App\Models\TipoDocumentoModel;
use App\Services\RecepcionDefinitivaService;

/**
 * Recepciones provisoria y definitiva de una obra (BLOQUE 3 RP/RD).
 *
 * La recepción provisoria inicia el plazo de conservación y la definitiva
 * lo cierra. El alta y la corrección de la provisoria se realizan dentro
 * de una transacción que también mantiene coherente el estado de la obra.
 *
 * Reglas de servidor, no delegables al cliente:
 *  - una obra solo puede tener una recepción provisoria y una definitiva;
 *  - la definitiva exige la provisoria y no puede ser anterior a la fecha
 *    prevista calculada con el plazo de conservación aplicado;
 *  - las fechas de recepción no pueden ser futuras;
 *  - la provisoria puede corregirse mientras no exista la definitiva;
 *  - una vez registrada la definitiva, ambas son inmutables y no se eliminan.
 *
 * Las obras históricas que ya están EN PLAZO DE CONSERVACIÓN o FINALIZADA
 * pueden completar sus recepciones sin que el alta vuelva a cambiar el
 * estado. Solo se avanza de estado cuando la transición corresponde.
 */
class Recepciones extends BaseController
{
    private const ROLES_EDICION = ['SUPERADMINISTRADOR', 'ADMINISTRADOR'];

    /** Límite de seguridad para el plazo de conservación (100 años). */
    private const PLAZO_MAXIMO_DIAS = 36500;

    /**
     * Pantalla de recepciones de la obra.
     *
     * Accesible para SUPERADMINISTRADOR, ADMINISTRADOR y CONSULTA.
     */
    public function index(int $id)
    {
        $roles = session()->get('roles') ?? [];
        $obra  = (new ObraModel())->findDetalle($id);

        if ($obra === null) {
            return redirect()->to($this->getDashboardPath($roles))
                ->with('error', 'La obra seleccionada no existe.');
        }

        $provisoria = (new RecepcionProvisoriaModel())->porObra($id);
        $definitiva = (new RecepcionDefinitivaModel())->porObra($id);
        $estado     = new EstadoObraModel();
        $nombreEstado = $obra->estado_nombre ?? '';

        $puedeEditar = array_intersect(self::ROLES_EDICION, $roles) !== [];
        $permiteProvisoria = $provisoria === null
            && $definitiva === null
            && $estado->permiteRegistrarRecepcionProvisoria($nombreEstado);
        $permiteCorregirProvisoria = $provisoria !== null
            && $definitiva === null
            && $estado->permiteCorregirRecepcionProvisoria($nombreEstado);
        $permiteDefinitiva = $provisoria !== null
            && $definitiva === null
            && $estado->permiteRegistrarRecepcionDefinitiva($nombreEstado);

        $fechaPrevista = $provisoria === null
            ? null
            : (string) $provisoria->fecha_prevista_definitiva;
        $definitivaHabilitada = $fechaPrevista !== null
            && PlazoObra::permiteRecepcionDefinitiva(
                (string) $provisoria->fecha_recepcion,
                (int) $provisoria->plazo_conservacion_dias,
                date('Y-m-d')
            );

        $tipoActa   = (new TipoDocumentoModel())->findByNombre(RecepcionDefinitivaService::TIPO_ACTA);
        $actasDefinitiva = $tipoActa === null
            ? []
            : (new DocumentoModel())->listarVigentesPorTipo($id, (int) $tipoActa->id);

        return view('obras/recepciones', [
            'actas_definitiva'             => $actasDefinitiva,
            'titulo'                       => 'Recepciones de obra',
            'user_name'                    => session()->get('user_name'),
            'username'                     => session()->get('username'),
            'roles'                        => $roles,
            'obra'                         => $obra,
            'provisoria'                   => $provisoria,
            'definitiva'                   => $definitiva,
            'puede_editar'                 => $puedeEditar,
            'permite_provisoria'           => $permiteProvisoria,
            'permite_corregir_provisoria'  => $permiteCorregirProvisoria,
            'permite_definitiva'           => $permiteDefinitiva,
            'fecha_prevista_definitiva'    => $fechaPrevista,
            'definitiva_habilitada'        => $definitivaHabilitada,
            'plazo_conservacion_default'   => (int) ($obra->plazo_conservacion_dias ?? 180),
            'fecha_hoy'                    => date('Y-m-d'),
        ]);
    }

    /**
     * Registra la recepción provisoria y, si corresponde, inicia el plazo
     * de conservación.
     */
    public function registrarProvisoria()
    {
        $obra = $this->obraDeEntrada();

        if (! is_object($obra)) {
            return $obra;
        }

        $destino = '/obras/recepciones/' . (int) $obra->id;

        if (! $this->tieneRolEdicion()) {
            return redirect()->to($destino)
                ->with('error', 'No tiene autorización para registrar recepciones.');
        }

        $provisoriaModel = new RecepcionProvisoriaModel();
        $definitivaModel = new RecepcionDefinitivaModel();

        if ($provisoriaModel->tienePorObra((int) $obra->id)) {
            return redirect()->to($destino)
                ->with('error', 'La obra ya tiene una recepción provisoria registrada.');
        }

        if ($definitivaModel->tienePorObra((int) $obra->id)) {
            return redirect()->to($destino)
                ->with('error', 'La obra ya tiene una recepción definitiva registrada.');
        }

        if (! (new EstadoObraModel())->permiteRegistrarRecepcionProvisoria($obra->estado_nombre ?? '')) {
            return redirect()->to($destino)
                ->with('error', 'El estado actual de la obra no permite registrar la recepción provisoria.');
        }

        $datos   = $this->tomarDatosProvisoria();
        $errores = $this->validarProvisoria($obra, $datos);

        if ($errores !== []) {
            return redirect()->to($destino)
                ->withInput()
                ->with('errores_recepcion', $errores);
        }

        $datos['fecha_prevista_definitiva'] = PlazoObra::fechaPrevistaDefinitiva(
            (string) $datos['fecha_recepcion'],
            (int) $datos['plazo_conservacion_dias']
        );

        $db = db_connect();
        $db->transStart();

        $registrada = false;
        $erroresTx  = [];

        try {
            $this->bloquearObra((int) $obra->id);

            $actual          = (new ObraModel())->findDetalle((int) $obra->id);
            $provisoriaModel = new RecepcionProvisoriaModel();
            $definitivaModel = new RecepcionDefinitivaModel();

            if (
                $actual !== null
                && ! $provisoriaModel->tienePorObra((int) $obra->id)
                && ! $definitivaModel->tienePorObra((int) $obra->id)
                && (new EstadoObraModel())->permiteRegistrarRecepcionProvisoria($actual->estado_nombre ?? '')
            ) {
                $erroresTx = $this->validarProvisoria($actual, $datos);

                if ($erroresTx === []) {
                    $registrada = $provisoriaModel->crear((int) $obra->id, $datos);

                    if ($registrada) {
                        $registrada = (new ObraModel())->fijarPlazoConservacion(
                            (int) $obra->id,
                            (int) $datos['plazo_conservacion_dias']
                        );
                    }

                    if (
                        $registrada
                        && mb_strtoupper(trim((string) ($actual->estado_nombre ?? '')))
                            === mb_strtoupper(EstadoObraModel::ESTADO_EN_EJECUCION)
                    ) {
                        $registrada = $this->cambiarEstado(
                            (int) $obra->id,
                            EstadoObraModel::ESTADO_EN_PLAZO_CONSERVACION
                        );
                    }
                }
            }
        } catch (\Throwable $e) {
            $db->transRollback();

            return redirect()->to($destino)
                ->with('error', 'No se pudo registrar la recepción provisoria. Verifique el estado de la obra.');
        }

        if (! $registrada) {
            $db->transRollback();

            if ($erroresTx !== []) {
                return redirect()->to($destino)
                    ->withInput()
                    ->with('errores_recepcion', $erroresTx);
            }

            return redirect()->to($destino)
                ->with('error', 'No se pudo registrar la recepción provisoria. Verifique el estado de la obra.');
        }

        $db->transComplete();

        if (! $db->transStatus()) {
            return redirect()->to($destino)
                ->with('error', 'No se pudo registrar la recepción provisoria.');
        }

        return redirect()->to($destino)
            ->with('success', 'La recepción provisoria fue registrada.');
    }

    /**
     * Corrige fecha, plazo u observaciones de la recepción provisoria.
     *
     * Solo se admite mientras no exista recepción definitiva.
     */
    public function actualizarProvisoria()
    {
        $obra = $this->obraDeEntrada();

        if (! is_object($obra)) {
            return $obra;
        }

        $destino = '/obras/recepciones/' . (int) $obra->id;

        if (! $this->tieneRolEdicion()) {
            return redirect()->to($destino)
                ->with('error', 'No tiene autorización para modificar recepciones.');
        }

        $provisoria = (new RecepcionProvisoriaModel())->porObra((int) $obra->id);

        if ($provisoria === null) {
            return redirect()->to($destino)
                ->with('error', 'La obra no tiene una recepción provisoria registrada.');
        }

        if ((new RecepcionDefinitivaModel())->tienePorObra((int) $obra->id)) {
            return redirect()->to($destino)
                ->with('error', 'La recepción provisoria no puede modificarse después de registrar la definitiva.');
        }

        if (! (new EstadoObraModel())->permiteCorregirRecepcionProvisoria($obra->estado_nombre ?? '')) {
            return redirect()->to($destino)
                ->with('error', 'El estado actual de la obra no permite modificar la recepción provisoria.');
        }

        $datos   = $this->tomarDatosProvisoria();
        $errores = $this->validarProvisoria($obra, $datos);

        if ($errores !== []) {
            return redirect()->to($destino)
                ->withInput()
                ->with('errores_recepcion', $errores);
        }

        $datos['fecha_prevista_definitiva'] = PlazoObra::fechaPrevistaDefinitiva(
            (string) $datos['fecha_recepcion'],
            (int) $datos['plazo_conservacion_dias']
        );

        $db = db_connect();
        $db->transStart();

        $actualizada = false;
        $erroresTx   = [];

        try {
            $this->bloquearObra((int) $obra->id);

            $actual          = (new ObraModel())->findDetalle((int) $obra->id);
            $provisoriaModel = new RecepcionProvisoriaModel();
            $provisoriaActual = $provisoriaModel->porObra((int) $obra->id);
            $definitivaModel = new RecepcionDefinitivaModel();

            if (
                $actual !== null
                && $provisoriaActual !== null
                && ! $definitivaModel->tienePorObra((int) $obra->id)
                && (new EstadoObraModel())->permiteCorregirRecepcionProvisoria($actual->estado_nombre ?? '')
            ) {
                $erroresTx = $this->validarProvisoria($actual, $datos);

                if ($erroresTx === []) {
                    $actualizada = $provisoriaModel->actualizar(
                        (int) $provisoriaActual->id,
                        $datos
                    );

                    if ($actualizada) {
                        $actualizada = (new ObraModel())->fijarPlazoConservacion(
                            (int) $obra->id,
                            (int) $datos['plazo_conservacion_dias']
                        );
                    }
                }
            }
        } catch (\Throwable $e) {
            $db->transRollback();

            return redirect()->to($destino)
                ->with('error', 'No se pudo modificar la recepción provisoria.');
        }

        if (! $actualizada) {
            $db->transRollback();

            if ($erroresTx !== []) {
                return redirect()->to($destino)
                    ->withInput()
                    ->with('errores_recepcion', $erroresTx);
            }

            return redirect()->to($destino)
                ->with('error', 'No se pudo modificar la recepción provisoria.');
        }

        $db->transComplete();

        if (! $db->transStatus()) {
            return redirect()->to($destino)
                ->with('error', 'No se pudo modificar la recepción provisoria.');
        }

        return redirect()->to($destino)
            ->with('success', 'La recepción provisoria fue actualizada.');
    }

    /**
     * Registra la recepción definitiva. Las reglas, la transición a
     * FINALIZADA y la auditoría se resuelven en RecepcionDefinitivaService,
     * dentro de una única transacción.
     */
    public function registrarDefinitiva()
    {
        $obra = $this->obraDeEntrada();

        if (! is_object($obra)) {
            return $obra;
        }

        $destino = '/obras/recepciones/' . (int) $obra->id;

        if (! $this->tieneRolEdicion()) {
            return redirect()->to($destino)
                ->with('error', 'No tiene autorización para registrar recepciones.');
        }

        $resultado = (new RecepcionDefinitivaService())->registrar(
            (int) $obra->id,
            $this->tomarDatosDefinitiva(),
            $this->usuarioId()
        );

        if (! $resultado['registrada']) {
            return redirect()->to($destino)
                ->withInput()
                ->with('errores_recepcion', $resultado['errores']);
        }

        return redirect()->to($destino)
            ->with('success', 'La recepción definitiva fue registrada.');
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
     * SQLite (entorno de pruebas) serializa las transacciones por sí mismo.
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
     * Datos del formulario de recepción provisoria normalizados.
     *
     * @return array<string, mixed>
     */
    private function tomarDatosProvisoria(): array
    {
        return [
            'fecha_recepcion'          => $this->parseFecha($this->request->getPost('fecha_recepcion')),
            'plazo_conservacion_dias'  => $this->parseEnteroPositivo(
                $this->request->getPost('plazo_conservacion_dias')
            ),
            'observaciones'            => $this->textoOpcional($this->request->getPost('observaciones')),
        ];
    }

    /**
     * Datos del formulario de recepción definitiva normalizados.
     *
     * @return array<string, mixed>
     */
    private function tomarDatosDefinitiva(): array
    {
        $documento = trim((string) $this->request->getPost('documento_id'));

        return [
            'fecha_recepcion' => $this->parseFecha($this->request->getPost('fecha_recepcion')),
            'observaciones'   => $this->textoOpcional($this->request->getPost('observaciones')),
            'documento_id'    => ctype_digit($documento) ? (int) $documento : null,
        ];
    }

    private function usuarioId(): ?int
    {
        $id = session()->get('user_id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Valida la fecha y el plazo de una recepción provisoria.
     *
     * @param array<string, mixed> $datos
     *
     * @return list<string>
     */
    private function validarProvisoria(object $obra, array $datos): array
    {
        $errores = [];

        if ($datos['fecha_recepcion'] === null) {
            $errores[] = 'La fecha de recepción provisoria no es válida.';
        } else {
            if (strcmp((string) $datos['fecha_recepcion'], date('Y-m-d')) > 0) {
                $errores[] = 'La fecha de recepción provisoria no puede ser posterior a la fecha actual.';
            }

            if (
                $obra->fecha_inicio !== null
                && $obra->fecha_inicio !== ''
                && strcmp((string) $datos['fecha_recepcion'], (string) $obra->fecha_inicio) < 0
            ) {
                $errores[] = 'La fecha de recepción provisoria no puede ser anterior al inicio de la obra.';
            }
        }

        $plazo = $datos['plazo_conservacion_dias'];

        if ($plazo === null) {
            $errores[] = 'El plazo de conservación debe ser un número entero mayor a cero.';
        } elseif ((int) $plazo > self::PLAZO_MAXIMO_DIAS) {
            $errores[] = 'El plazo de conservación no puede superar los '
                . self::PLAZO_MAXIMO_DIAS . ' días.';
        }

        if (
            $errores === []
            && PlazoObra::fechaPrevistaDefinitiva(
                (string) $datos['fecha_recepcion'],
                (int) $datos['plazo_conservacion_dias']
            ) === null
        ) {
            $errores[] = 'No se pudo calcular la fecha prevista de la recepción definitiva.';
        }

        return $errores;
    }

    /**
     * Normaliza una fecha de formulario a `Y-m-d`.
     *
     * Acepta el valor nativo de `<input type="date">` y el formato visual
     * dd/mm/aaaa.
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

            if ($fecha->format($formato) !== $texto) {
                continue;
            }

            return $fecha->format('Y-m-d');
        }

        return null;
    }

    /**
     * Convierte un entero positivo acotado del formulario.
     */
    private function parseEnteroPositivo(mixed $valor): ?int
    {
        $texto = trim((string) $valor);

        if ($texto === '') {
            return null;
        }

        $numero = filter_var($texto, FILTER_VALIDATE_INT, [
            'options' => [
                'min_range' => 1,
                'max_range' => self::PLAZO_MAXIMO_DIAS,
            ],
        ]);

        return $numero === false ? null : (int) $numero;
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
