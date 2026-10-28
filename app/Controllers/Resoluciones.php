<?php

namespace App\Controllers;

use App\Libraries\ResolucionObra;
use App\Models\AuditoriaModel;
use App\Models\EstadoObraModel;
use App\Models\ObraModel;
use App\Models\ResolucionModel;
use App\Models\TipoResolucionModel;

/**
 * Resoluciones (actos administrativos) de una obra.
 *
 * Pantalla de consulta y registro de los trámites aprobados durante la
 * ejecución de la obra. Los trámites nacen sin confirmar y pueden
 * editarse o eliminarse; confirmarlos los vuelve inmutables.
 *
 * Solo tienen sentido en obra en ejecución (o neutralizada). La autoridad
 * se valida en servidor y no se confía en los campos enviados por el
 * cliente: monto y días se normalizan según el tipo de resolución.
 */
class Resoluciones extends BaseController
{
    private const ROLES_EDICION = ['SUPERADMINISTRADOR', 'ADMINISTRADOR'];

    /**
     * Pantalla de resoluciones de la obra.
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

        $estadoModel = new EstadoObraModel();
        $tipos       = [];
        foreach ((new TipoResolucionModel())->findAllActivos() as $tipo) {
            $reglas                     = ResolucionObra::reglas($tipo->tipo_resolucion);
            $reglas['observaciones']    = ResolucionObra::requiereObservaciones($tipo->tipo_resolucion)
                ? ResolucionObra::CAMPO_REQUERIDO
                : ResolucionObra::CAMPO_OPCIONAL;

            $tipos[] = [
                'id'     => (int) $tipo->id,
                'nombre' => $tipo->tipo_resolucion,
                'reglas' => $reglas,
            ];
        }

        return view('obras/resoluciones', [
            'titulo'            => 'Resoluciones de obra',
            'user_name'         => session()->get('user_name'),
            'username'          => session()->get('username'),
            'roles'             => $roles,
            'obra'              => $obra,
            'resoluciones'      => (new ResolucionModel())->listarPorObra($id),
            'tipos'             => $tipos,
            'puede_editar'      => array_intersect(self::ROLES_EDICION, $roles) !== [],
            'permite_registrar' => $estadoModel->permiteRegistrarResoluciones($obra->estado_nombre ?? ''),
        ]);
    }

    /**
     * Registra una resolución nueva (sin confirmar).
     */
    public function crear()
    {
        $obra = $this->obraDeEntrada();

        if (! is_object($obra)) {
            return $obra;
        }

        $destino = '/obras/resoluciones/' . (int) $obra->id;

        if (! $this->puedeGestionar($obra)) {
            return redirect()->to($destino)
                ->with('error', 'El estado de la obra no permite registrar resoluciones.');
        }

        $datos   = $this->tomarDatos();
        $errores = $this->validar($obra->id, null, $datos);

        if ($errores !== []) {
            return redirect()->to($destino)
                ->withInput()
                ->with('errores_resolucion', $errores);
        }

        $resolucionModel = new ResolucionModel();
        if (! $resolucionModel->crear($obra->id, $datos)) {
            return redirect()->to($destino)
                ->withInput()
                ->with('errores_resolucion', ['No se pudo registrar la resolución.']);
        }

        $ultima = $resolucionModel->where('obra_id', $obra->id)->orderBy('id', 'DESC')->first();
        if ($ultima !== null) {
            (new AuditoriaModel())->registrar(
                'ALTA',
                'resoluciones',
                (int) $ultima->id,
                (int) (session()->get('user_id') ?? 0) ?: null,
                null,
                null,
                json_encode([
                    'obra_id' => (int) $obra->id,
                    'tipo_resolucion_id' => $datos['tipo_resolucion_id'] ?? null,
                    'numero' => $datos['numero'] ?? null,
                    'anio' => $datos['anio'] ?? null,
                    'fecha_resolucion' => $datos['fecha_resolucion'] ?? null,
                    'monto' => $datos['monto'] ?? null,
                    'dias' => $datos['dias'] ?? null,
                ], JSON_UNESCAPED_UNICODE),
                'Alta de resolución'
            );
        }

        return redirect()->to($destino)
            ->with('success', 'La resolución fue registrada. Queda pendiente de confirmación.');
    }

    /**
     * Modifica una resolución no confirmada.
     */
    public function actualizar()
    {
        $obra = $this->obraDeEntrada();

        if (! is_object($obra)) {
            return $obra;
        }

        $destino = '/obras/resoluciones/' . (int) $obra->id;

        if (! $this->puedeGestionar($obra)) {
            return redirect()->to($destino)
                ->with('error', 'El estado de la obra no permite modificar resoluciones.');
        }

        $resolucionId = (int) $this->request->getPost('resolucion_id');
        $resolucion   = $this->resolucionDeObra($obra->id, $resolucionId);

        if ($resolucion === null) {
            return redirect()->to($destino)
                ->with('error', 'La resolución indicada no existe.');
        }

        if ((int) $resolucion->confirmada === 1) {
            return redirect()->to($destino)
                ->with('error', 'Una resolución confirmada no puede modificarse.');
        }

        $datos   = $this->tomarDatos();
        $errores = $this->validar($obra->id, $resolucionId, $datos);

        if ($errores !== []) {
            return redirect()->to($destino)
                ->withInput()
                ->with('errores_resolucion', $errores);
        }

        $resolucionAntes = (new ResolucionModel())->find($resolucionId);
        $antes = $resolucionAntes === null ? [] : [
            'tipo_resolucion_id' => isset($resolucionAntes->tipo_resolucion_id) ? (string) $resolucionAntes->tipo_resolucion_id : null,
            'numero'             => $resolucionAntes->numero === null ? null : (string) $resolucionAntes->numero,
            'anio'               => $resolucionAntes->anio === null ? null : (string) $resolucionAntes->anio,
            'fecha_resolucion'   => $resolucionAntes->fecha_resolucion ?? null,
            'monto'              => $resolucionAntes->monto === null ? null : (string) $resolucionAntes->monto,
            'dias'               => $resolucionAntes->dias === null ? null : (string) $resolucionAntes->dias,
            'observaciones'      => $resolucionAntes->observaciones ?? null,
        ];

        $resolucionModel = new ResolucionModel();
        if (! $resolucionModel->actualizar($resolucionId, $datos)) {
            return redirect()->to($destino)
                ->withInput()
                ->with('errores_resolucion', ['No se pudo modificar la resolución.']);
        }

        $despues = [
            'tipo_resolucion_id' => isset($datos['tipo_resolucion_id']) ? (string) $datos['tipo_resolucion_id'] : ($resolucionAntes->tipo_resolucion_id ?? null),
            'numero'             => isset($datos['numero']) && $datos['numero'] !== '' && $datos['numero'] !== null ? (string) $datos['numero'] : null,
            'anio'               => isset($datos['anio']) && $datos['anio'] !== '' && $datos['anio'] !== null ? (string) $datos['anio'] : null,
            'fecha_resolucion'   => $datos['fecha_resolucion'] ?? null,
            'monto'              => isset($datos['monto']) && $datos['monto'] !== '' && $datos['monto'] !== null ? (string) $datos['monto'] : null,
            'dias'               => isset($datos['dias']) && $datos['dias'] !== '' && $datos['dias'] !== null ? (string) $datos['dias'] : null,
            'observaciones'      => $datos['observaciones'] ?? null,
        ];

        (new AuditoriaModel())->registrarCambios(
            'CAMBIO',
            'resoluciones',
            $resolucionId,
            (int) (session()->get('user_id') ?? 0) ?: null,
            $antes,
            $despues,
            'Modificación de resolución'
        );

        return redirect()->to($destino)
            ->with('success', 'La resolución fue modificada.');
    }

    /**
     * Confirma la resolución. Una vez confirmada no puede editarse ni
     * eliminarse.
     */
    public function confirmar()
    {
        $obra = $this->obraDeEntrada();

        if (! is_object($obra)) {
            return $obra;
        }

        $destino = '/obras/resoluciones/' . (int) $obra->id;

        $resolucionId = (int) $this->request->getPost('resolucion_id');
        $resolucion   = $this->resolucionDeObra($obra->id, $resolucionId);

        if ($resolucion === null) {
            return redirect()->to($destino)
                ->with('error', 'La resolución indicada no existe.');
        }

        if ((int) $resolucion->confirmada === 1) {
            return redirect()->to($destino)
                ->with('error', 'La resolución ya estaba confirmada.');
        }

        if (! ResolucionObra::puedeConfirmarse(
            $resolucion->tipo_nombre ?? null,
            $resolucion->numero === null ? null : (int) $resolucion->numero,
            $resolucion->anio === null ? null : (int) $resolucion->anio,
            $resolucion->fecha_resolucion,
            $resolucion->observaciones
        )) {
            return redirect()->to($destino)
                ->with('error', ResolucionObra::requiereObservaciones($resolucion->tipo_nombre ?? null)
                    ? 'Debe completar número, año, fecha y observaciones antes de confirmar la resolución.'
                    : 'Debe completar número, año y fecha antes de confirmar la resolución.');
        }

        $db = db_connect();
        $db->transStart();

        $confirmada = (new ResolucionModel())->confirmar($resolucionId);

        if ($confirmada && ResolucionObra::ajustaMonto($resolucion->tipo_nombre ?? null)) {
            $confirmada = (new ObraModel())->recalcularMontoContractualVigente((int) $obra->id);
        }

        if ($confirmada) {
            (new AuditoriaModel())->registrar(
                'CAMBIO_ESTADO',
                'resoluciones',
                $resolucionId,
                (int) (session()->get('user_id') ?? 0) ?: null,
                'confirmada',
                '0',
                '1',
                'Confirmación de resolución'
            );
        }

        $db->transComplete();

        if (! $confirmada || ! $db->transStatus()) {
            return redirect()->to($destino)
                ->with('error', 'No se pudo confirmar la resolución.');
        }

        return redirect()->to($destino)
            ->with('success', 'La resolución fue confirmada y ya no puede modificarse.');
    }

    /**
     * Elimina una resolución no confirmada.
     */
    public function eliminar()
    {
        $obra = $this->obraDeEntrada();

        if (! is_object($obra)) {
            return $obra;
        }

        $destino = '/obras/resoluciones/' . (int) $obra->id;

        if (! $this->puedeGestionar($obra)) {
            return redirect()->to($destino)
                ->with('error', 'El estado de la obra no permite eliminar resoluciones.');
        }

        $resolucionId = (int) $this->request->getPost('resolucion_id');
        $resolucion   = $this->resolucionDeObra($obra->id, $resolucionId);

        if ($resolucion === null) {
            return redirect()->to($destino)
                ->with('error', 'La resolución indicada no existe.');
        }

        if ((int) $resolucion->confirmada === 1) {
            return redirect()->to($destino)
                ->with('error', 'Una resolución confirmada no puede eliminarse.');
        }

        $resolucionElim = (new ResolucionModel())->find($resolucionId);
        if (! (new ResolucionModel())->delete($resolucionId)) {
            return redirect()->to($destino)
                ->with('error', 'No se pudo eliminar la resolución.');
        }

        if ($resolucionElim !== null) {
            (new AuditoriaModel())->registrar(
                'BAJA',
                'resoluciones',
                $resolucionId,
                (int) (session()->get('user_id') ?? 0) ?: null,
                null,
                json_encode([
                    'obra_id' => (int) $obra->id,
                    'tipo_resolucion_id' => $resolucionElim->tipo_resolucion_id ?? null,
                    'numero' => $resolucionElim->numero ?? null,
                    'anio' => $resolucionElim->anio ?? null,
                ], JSON_UNESCAPED_UNICODE),
                null,
                'Eliminación de resolución no confirmada'
            );
        }

        return redirect()->to($destino)
            ->with('success', 'La resolución fue eliminada.');
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
     * Resolución existente de la obra indicada.
     */
    private function resolucionDeObra(int $obraId, int $resolucionId): ?object
    {
        return (new ResolucionModel())
            ->select('resoluciones.*')
            ->select('tipos_resolucion.tipo_resolucion AS tipo_nombre')
            ->join('tipos_resolucion', 'tipos_resolucion.id = resoluciones.tipo_resolucion_id', 'left')
            ->where('resoluciones.obra_id', $obraId)
            ->where('resoluciones.id', $resolucionId)
            ->first();
    }

    /**
     * Indica si el rol y el estado de la obra permiten editar.
     */
    private function puedeGestionar(object $obra): bool
    {
        $roles = session()->get('roles') ?? [];

        if (array_intersect(self::ROLES_EDICION, $roles) === []) {
            return false;
        }

        return (new EstadoObraModel())->permiteRegistrarResoluciones($obra->estado_nombre ?? '');
    }

    /**
     * Datos del formulario normalizados.
     *
     * @return array<string, mixed>
     */
    private function tomarDatos(): array
    {
        $monto = ResolucionObra::parseMonto($this->request->getPost('monto'));
        $dias  = ResolucionObra::parseDias($this->request->getPost('dias'));

        return [
            'tipo_resolucion_id' => (int) $this->request->getPost('tipo_resolucion_id'),
            'numero'             => ResolucionObra::parseNumero($this->request->getPost('numero')),
            'anio'               => ResolucionObra::parseAnio($this->request->getPost('anio')),
            'fecha_resolucion'   => $this->parseFecha($this->request->getPost('fecha_resolucion')),
            'monto'              => $monto,
            'dias'               => $dias,
            'observaciones'      => $this->textoOpcional($this->request->getPost('observaciones')),
        ];
    }

    /**
     * Valida los datos de una resolución contra el catálogo y sus reglas.
     *
     * @param array<string, mixed> $datos
     *
     * @return list<string>
     */
    private function validar(int $obraId, ?int $resolucionId, array $datos): array
    {
        $errores = [];

        $tipoModel = new TipoResolucionModel();
        $tipo      = $tipoModel->find($datos['tipo_resolucion_id']);

        if ($tipo === null || (int) $tipo->activo !== 1) {
            return ['Debe seleccionar un tipo de resolución válido.'];
        }

        if ($this->requiereValor($this->request->getPost('numero')) && $datos['numero'] === null) {
            $errores[] = 'El número de resolución no es válido.';
        }

        if ($this->requiereValor($this->request->getPost('anio')) && $datos['anio'] === null) {
            $errores[] = 'El año de resolución no es válido.';
        }

        if ($this->requiereValor($this->request->getPost('fecha_resolucion')) && $datos['fecha_resolucion'] === null) {
            $errores[] = 'La fecha de resolución no es válida.';
        }

        $errores = array_merge($errores, ResolucionObra::validar(
            $datos['monto'],
            $datos['dias'],
            $tipo->tipo_resolucion
        ));

        if (
            $datos['numero'] !== null
            && $datos['anio'] !== null
            && (new ResolucionModel())->existeNumeroAnio($obraId, $datos['numero'], $datos['anio'], $resolucionId)
        ) {
            $errores[] = 'Ya existe una resolución con ese número y año para la obra.';
        }

        return $errores;
    }

    private function requiereValor(mixed $valor): bool
    {
        return is_string($valor) ? trim($valor) !== '' : $valor !== null;
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
