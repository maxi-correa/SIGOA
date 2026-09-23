<?php

namespace App\Controllers;

use App\Libraries\PlazoObra;
use App\Models\BarrioModel;
use App\Models\EmpresaModel;
use App\Models\EstadoObraModel;
use App\Models\InspectoresObrasModel;
use App\Models\ObraModel;
use App\Models\ObrasRepresentantesTecnicosModel;
use App\Models\RepresentanteTecnicoModel;
use App\Models\TipoLicitacionModel;
use App\Models\UsuarioModel;

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
     * Ficha de una obra: identificación, datos operativos e inspector.
     *
     * Accesible para SUPERADMINISTRADOR, ADMINISTRADOR y CONSULTA. La
     * edición se habilita solo a los dos primeros roles.
     */
    public function ver(int $id)
    {
        $roles     = session()->get('roles') ?? [];
        $obraModel = new ObraModel();
        $obra      = $obraModel->findDetalle($id);

        if ($obra === null) {
            return redirect()->to($this->getDashboardPath($roles))
                ->with('error', 'La obra seleccionada no existe.');
        }

        $inspectoresModel = new InspectoresObrasModel();
        $inspectorVigente = $inspectoresModel->findVigenteConInspector($id);

        $representantesModel = new ObrasRepresentantesTecnicosModel();

        $plazoDias = ($obra->plazo_original_dias !== null)
            ? (int) $obra->plazo_original_dias
            : null;

        $fechaFin = ($obra->fecha_inicio !== null && $plazoDias !== null)
            ? PlazoObra::sumarDias((string) $obra->fecha_inicio, $plazoDias)
            : null;

        $session = session();

        return view('obras/ficha', [
            'titulo'            => 'Ficha de obra',
            'user_name'         => $session->get('user_name'),
            'username'          => $session->get('username'),
            'roles'             => $roles,
            'obra'              => $obra,
            'inspectores'       => (new UsuarioModel())->findInspectoresActivos(),
            'inspector_vigente' => $inspectorVigente,
            'representantes'    => (new RepresentanteTecnicoModel())->listarActivasConTitulo(),
            'representante_vigente' => $representantesModel->findVigenteConRepresentante($id),
            'unidades'          => PlazoObra::unidades(),
            'plazo_dias'        => $plazoDias,
            'fecha_fin'         => $fechaFin,
            'puede_editar'      => array_intersect(['SUPERADMINISTRADOR', 'ADMINISTRADOR'], $roles) !== [],
        ]);
    }

    /**
     * Guarda los datos operativos de la ficha: Expte. contable, fecha de
     * inicio y plazo original (valor, unidad y días corridos calculados).
     *
     * Solo se actualiza `updated_at` si existe una modificación real.
     */
    public function actualizarFicha()
    {
        $roles     = session()->get('roles') ?? [];
        $obraModel = new ObraModel();
        $obraId    = (int) $this->request->getPost('obra_id');
        $obra      = $obraModel->find($obraId);

        if ($obra === null) {
            return redirect()->to($this->getDashboardPath($roles))
                ->with('error', 'La obra seleccionada no existe.');
        }

        $datos   = $this->tomarDatosFicha();
        $errores = $this->validarDatosFicha($datos);

        if ($errores !== []) {
            return redirect()->to('/obras/ver/' . $obraId)
                ->withInput()
                ->with('errores_ficha', $errores);
        }

        if ($this->fichaSinCambios($datos, $obra)) {
            return redirect()->to('/obras/ver/' . $obraId)
                ->with('success', 'No se registraron cambios en la ficha de la obra.');
        }

        if (! $obraModel->actualizarFicha($obraId, $datos)) {
            return redirect()->to('/obras/ver/' . $obraId)
                ->with('error', 'No se pudieron guardar los cambios de la ficha.');
        }

        return redirect()->to('/obras/ver/' . $obraId)
            ->with('success', 'La ficha de la obra fue actualizada correctamente.');
    }

    /**
     * Registra un cambio de inspector vigente de la obra.
     *
     * Cierra la asignación abierta y crea una nueva, conservando el
     * historial en `inspectores_obras`. La obra se marca como modificada.
     */
    public function actualizarInspector()
    {
        $roles     = session()->get('roles') ?? [];
        $obraModel = new ObraModel();
        $obraId    = (int) $this->request->getPost('obra_id');
        $obra      = $obraModel->find($obraId);

        if ($obra === null) {
            return redirect()->to($this->getDashboardPath($roles))
                ->with('error', 'La obra seleccionada no existe.');
        }

        $inspectoresModel = new InspectoresObrasModel();
        $inspectorVigente = $inspectoresModel->findVigentePorObra($obraId);

        $inspectorId = (int) $this->request->getPost('inspector_id');
        $fechaCambio = PlazoObra::parseFecha((string) $this->request->getPost('fecha_cambio'));

        $errores = $this->validarCambioInspector($inspectorId, $fechaCambio, $inspectorVigente);

        if ($errores !== []) {
            return redirect()->to('/obras/ver/' . $obraId)
                ->withInput()
                ->with('errores_inspector', $errores);
        }

        if (! $inspectoresModel->cambiarAsignacion($obraId, $inspectorId, $fechaCambio)) {
            return redirect()->to('/obras/ver/' . $obraId)
                ->with('error', 'No se pudo registrar el cambio de inspector.');
        }

        $obraModel->touch($obraId);

        return redirect()->to('/obras/ver/' . $obraId)
            ->with('success', 'El inspector vigente fue actualizado correctamente.');
    }

    /**
     * Registra un cambio de representante técnico vigente de la obra.
     *
     * Cierra la asignación abierta y crea una nueva, conservando el
     * historial en `obras_representantes_tecnicos`. La obra se marca como
     * modificada dentro de la transacción del modelo.
     */
    public function actualizarRepresentante()
    {
        $roles     = session()->get('roles') ?? [];
        $obraModel = new ObraModel();
        $obraId    = (int) $this->request->getPost('obra_id');
        $obra      = $obraModel->find($obraId);

        if ($obra === null) {
            return redirect()->to($this->getDashboardPath($roles))
                ->with('error', 'La obra seleccionada no existe.');
        }

        $representantesModel = new ObrasRepresentantesTecnicosModel();
        $representanteVigente = $representantesModel->findVigentePorObra($obraId);

        $representanteId = (int) $this->request->getPost('representante_tecnico_id');
        $fechaCambio     = PlazoObra::parseFecha((string) $this->request->getPost('fecha_cambio'));

        $errores = $this->validarCambioRepresentante($representanteId, $fechaCambio, $representanteVigente);

        if ($errores !== []) {
            return redirect()->to('/obras/ver/' . $obraId)
                ->withInput()
                ->with('errores_representante', $errores);
        }

        if (! $representantesModel->cambiarAsignacion($obraId, $representanteId, $fechaCambio)) {
            return redirect()->to('/obras/ver/' . $obraId)
                ->with('error', 'No se pudo registrar el cambio de representante técnico.');
        }

        return redirect()->to('/obras/ver/' . $obraId)
            ->with('success', 'El representante técnico vigente fue actualizado correctamente.');
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

    /**
     * Toma y normaliza los datos operativos de la ficha de obra.
     *
     * Conserva los valores crudos necesarios para validar y los valores
     * ya convertidos para persistir. El plazo se traduce a días corridos
     * mediante `PlazoObra`.
     *
     * @return array<string, mixed>
     */
    private function tomarDatosFicha(): array
    {
        $expediente    = trim((string) $this->request->getPost('expediente_contable'));
        $fechaInicioRaw = trim((string) $this->request->getPost('fecha_inicio'));
        $plazoValorRaw  = trim((string) $this->request->getPost('plazo_valor'));
        $plazoUnidad    = trim((string) $this->request->getPost('plazo_unidad'));

        $plazoValor       = null;
        $plazoUnidadFinal = null;
        $plazoDias        = null;

        if ($plazoValorRaw !== '' && ctype_digit($plazoValorRaw) && PlazoObra::esUnidadValida($plazoUnidad)) {
            $plazoValor       = (int) $plazoValorRaw;
            $plazoUnidadFinal = $plazoUnidad;
            $plazoDias        = PlazoObra::diasDesdeUnidad($plazoValor, $plazoUnidadFinal);
        }

        return [
            'expediente_contable_raw' => $expediente,
            'fecha_inicio_raw'        => $fechaInicioRaw,
            'plazo_valor_raw'         => $plazoValorRaw,
            'plazo_unidad'            => $plazoUnidad,

            'expediente_contable'   => ($expediente === '') ? null : mb_strtoupper($expediente),
            'fecha_inicio'          => PlazoObra::parseFecha($fechaInicioRaw),
            'plazo_original_valor'  => $plazoValor,
            'plazo_original_unidad' => $plazoUnidadFinal,
            'plazo_original_dias'   => $plazoDias,
        ];
    }

    /**
     * Valida los datos operativos de la ficha.
     *
     * @param array<string, mixed> $datos
     *
     * @return list<string>
     */
    private function validarDatosFicha(array $datos): array
    {
        $errores = [];

        if ($datos['expediente_contable_raw'] !== '' && mb_strlen($datos['expediente_contable_raw']) > 50) {
            $errores[] = 'El Expte. contable no puede superar los 50 caracteres.';
        }

        if ($datos['fecha_inicio_raw'] !== '' && $datos['fecha_inicio'] === null) {
            $errores[] = 'La fecha de inicio no es válida. Utilice el formato dd/mm/aaaa.';
        }

        if ($datos['plazo_valor_raw'] !== '') {
            if (! ctype_digit($datos['plazo_valor_raw']) || (int) $datos['plazo_valor_raw'] < 1) {
                $errores[] = 'El plazo debe ser un número entero mayor a cero.';
            } elseif (! PlazoObra::esUnidadValida($datos['plazo_unidad'])) {
                $errores[] = 'La unidad del plazo no es válida.';
            }
        }

        return $errores;
    }

    /**
     * Determina si los datos enviados modifican realmente la obra.
     *
     * Se compara contra los valores almacenados normalizando nulos y
     * tipos, para no refrescar `updated_at` sin cambios reales.
     *
     * @param array<string, mixed> $datos
     */
    private function fichaSinCambios(array $datos, object $obra): bool
    {
        $normalizar = static function ($valor): ?string {
            return ($valor === null || $valor === '') ? null : (string) $valor;
        };

        $actuales = [
            'expediente_contable'   => $normalizar($obra->expediente_contable ?? null),
            'fecha_inicio'          => $normalizar($obra->fecha_inicio ?? null),
            'plazo_original_valor'  => $normalizar($obra->plazo_original_valor ?? null),
            'plazo_original_unidad' => $normalizar($obra->plazo_original_unidad ?? null),
            'plazo_original_dias'   => $normalizar($obra->plazo_original_dias ?? null),
        ];

        foreach ($actuales as $campo => $valorActual) {
            if ($normalizar($datos[$campo] ?? null) !== $valorActual) {
                return false;
            }
        }

        return true;
    }

    /**
     * Valida un cambio de inspector vigente.
     *
     * El nuevo inspector debe ser un usuario activo con rol INSPECTOR y
     * no puede coincidir con el inspector vigente actual.
     *
     * @return list<string>
     */
    private function validarCambioInspector(int $inspectorId, ?string $fechaCambio, ?object $inspectorVigente): array
    {
        $errores = [];

        if ($fechaCambio === null) {
            $errores[] = 'La fecha desde la que rige el cambio no es válida. Utilice el formato dd/mm/aaaa.';
        }

        if ($inspectorId <= 0) {
            $errores[] = 'Debe seleccionar un nuevo inspector.';

            return $errores;
        }

        $inspectores = (new UsuarioModel())->findInspectoresActivos();
        $idsValidos  = array_map(static fn ($inspector) => (int) $inspector->id, $inspectores);

        if (! in_array($inspectorId, $idsValidos, true)) {
            $errores[] = 'El inspector seleccionado no existe o no tiene el rol INSPECTOR.';
        } elseif ($inspectorVigente !== null && (int) $inspectorVigente->usuario_id === $inspectorId) {
            $errores[] = 'El inspector seleccionado ya es el inspector vigente de la obra.';
        }

        return $errores;
    }

    /**
     * Valida un cambio de representante técnico vigente.
     *
     * La fecha debe ser válida y no futura; si ya existe una asignación
     * vigente, la nueva fecha debe ser estrictamente posterior a su inicio
     * para no generar períodos superpuestos. El nuevo representante debe
     * existir y no puede coincidir con el vigente.
     *
     * @return list<string>
     */
    private function validarCambioRepresentante(int $representanteId, ?string $fechaCambio, ?object $representanteVigente): array
    {
        $errores = [];

        if ($fechaCambio === null) {
            $errores[] = 'La fecha desde la que rige el cambio no es válida. Utilice el formato dd/mm/aaaa.';
        } elseif (strcmp($fechaCambio, date('Y-m-d')) > 0) {
            $errores[] = 'La fecha del cambio no puede ser posterior a la fecha actual.';
        } elseif ($representanteVigente !== null && strcmp($fechaCambio, (string) $representanteVigente->fecha_inicio) <= 0) {
            $errores[] = 'La fecha del cambio debe ser posterior al inicio del representante técnico vigente.';
        }

        if ($representanteId <= 0) {
            $errores[] = 'Debe seleccionar un nuevo representante técnico.';

            return $errores;
        }

        $representantes = (new RepresentanteTecnicoModel())->listarActivas();
        $idsValidos     = array_map(static fn ($representante) => (int) $representante->id, $representantes);

        if (! in_array($representanteId, $idsValidos, true)) {
            $errores[] = 'El representante técnico seleccionado no existe o no está activo.';
        } elseif ($representanteVigente !== null && (int) $representanteVigente->representante_tecnico_id === $representanteId) {
            $errores[] = 'El representante técnico seleccionado ya es el vigente de la obra.';
        }

        return $errores;
    }
}