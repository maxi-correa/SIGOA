<?php

namespace App\Controllers;

use App\Libraries\PlazoObra;
use App\Models\AuditoriaModel;
use App\Models\DocumentoModel;
use App\Models\EstadoObraModel;
use App\Models\ObraModel;
use App\Models\RecepcionDefinitivaModel;
use App\Models\RecepcionProvisoriaModel;
use App\Models\TipoDocumentoModel;
use App\Services\RecepcionCorreccionService;
use App\Services\RecepcionDefinitivaService;
use App\Services\RecepcionProvisoriaService;
use App\Services\RecepcionValidacion;

/**
 * Recepciones provisoria y definitiva de una obra (RP/RD).
 *
 * El controlador valida la autorización, normaliza la entrada y muestra los
 * resultados. Las reglas, las transacciones y la auditoría viven en los
 * servicios de `App\Services\Recepcion*`.
 */
class Recepciones extends BaseController
{
    private const ROLES_EDICION = ['SUPERADMINISTRADOR', 'ADMINISTRADOR'];

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

        $provisoria   = (new RecepcionProvisoriaModel())->porObra($id);
        $definitiva   = (new RecepcionDefinitivaModel())->porObra($id);
        $estado       = new EstadoObraModel();
        $nombreEstado = (string) ($obra->estado_nombre ?? '');

        $fechaPrevista = $provisoria === null
            ? null
            : (string) $provisoria->fecha_prevista_definitiva;
        $definitivaHabilitada = $fechaPrevista !== null
            && PlazoObra::permiteRecepcionDefinitiva(
                (string) $provisoria->fecha_recepcion,
                (int) $provisoria->plazo_conservacion_dias,
                date('Y-m-d')
            );

        $puedeCorregir = $estado->permiteCorregirRecepcionProvisoria($nombreEstado);

        return view('obras/recepciones', [
            'titulo'                      => 'Recepciones de obra',
            'user_name'                   => session()->get('user_name'),
            'username'                    => session()->get('username'),
            'roles'                       => $roles,
            'obra'                        => $obra,
            'provisoria'                  => $provisoria,
            'definitiva'                  => $definitiva,
            'puede_editar'                => array_intersect(self::ROLES_EDICION, $roles) !== [],
            'permite_provisoria'          => $provisoria === null
                && $definitiva === null
                && $estado->permiteRegistrarRecepcionProvisoria($nombreEstado),
            'permite_corregir_provisoria' => $provisoria !== null && $puedeCorregir,
            'permite_corregir_definitiva' => $definitiva !== null && $puedeCorregir,
            'permite_corregir_conjunta'   => $provisoria !== null && $definitiva !== null && $puedeCorregir,
            'permite_definitiva'          => $provisoria !== null
                && $definitiva === null
                && $estado->permiteRegistrarRecepcionDefinitiva($nombreEstado),
            'fecha_prevista_definitiva'   => $fechaPrevista,
            'definitiva_habilitada'       => $definitivaHabilitada,
            'plazo_conservacion_default'  => (int) ($obra->plazo_conservacion_dias ?? 180),
            'fecha_hoy'                   => date('Y-m-d'),
            'actas_provisoria'            => $this->actasVigentes(RecepcionValidacion::TIPO_ACTA_PROVISORIA, $id),
            'actas_definitiva'            => $this->actasVigentes(RecepcionValidacion::TIPO_ACTA_DEFINITIVA, $id),
            'estados_nombres'             => $this->nombresDeEstados(),
            'historial'                   => (new AuditoriaModel())->historialRecepciones(
                $id,
                $provisoria?->id !== null ? (int) $provisoria->id : null,
                $definitiva?->id !== null ? (int) $definitiva->id : null
            ),
        ]);
    }

    /**
     * Registra la recepción provisoria (también sirve para regularizar un
     * acta existente con la fecha confirmada por el usuario).
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

        $resultado = (new RecepcionProvisoriaService())->registrar(
            (int) $obra->id,
            $this->datosProvisoriaDe($this->request->getPost()),
            $this->usuarioId()
        );

        return $this->resultado(
            $destino,
            $resultado['registrada'],
            $resultado['errores'],
            'La recepción provisoria fue registrada.',
            'No se pudo registrar la recepción provisoria.'
        );
    }

    /**
     * Corrige la recepción provisoria (fecha, plazo, observaciones y acta).
     */
    public function actualizarProvisoria()
    {
        return $this->corregir(fn (array $post) => [$this->datosProvisoriaDe($post), null]);
    }

    /**
     * Corrige la recepción definitiva (fecha, observaciones y acta).
     */
    public function actualizarDefinitiva()
    {
        return $this->corregir(fn (array $post) => [null, $this->datosDefinitivaDe($post)]);
    }

    /**
     * Corrige provisoria y definitiva en una sola operación atómica.
     */
    public function corregirConjunta()
    {
        return $this->corregir(fn (array $post) => [
            $this->datosProvisoriaDe((array) ($post['rp'] ?? [])),
            $this->datosDefinitivaDe((array) ($post['rd'] ?? [])),
        ]);
    }

    /**
     * Registra la recepción definitiva.
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
            $this->datosDefinitivaDe($this->request->getPost()),
            $this->usuarioId()
        );

        return $this->resultado(
            $destino,
            $resultado['registrada'],
            $resultado['errores'],
            'La recepción definitiva fue registrada.',
            'No se pudo registrar la recepción definitiva.'
        );
    }

    /**
     * Ejecuta una corrección. `$datos` devuelve [provisoria|null, definitiva|null].
     */
    private function corregir(callable $datos)
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

        [$provisoria, $definitiva] = $datos($this->request->getPost());

        $resultado = (new RecepcionCorreccionService())->corregir(
            (int) $obra->id,
            $provisoria,
            $definitiva,
            $this->usuarioId()
        );

        return $this->resultado(
            $destino,
            $resultado['corregida'],
            $resultado['errores'],
            'La corrección fue guardada en el historial de recepciones.',
            'No se pudo guardar la corrección.'
        );
    }

    /**
     * Respuesta común de altas y correcciones.
     *
     * @param list<string> $errores
     */
    private function resultado(string $destino, bool $ok, array $errores, string $mensajeOk, string $mensajeError)
    {
        if ($ok) {
            return redirect()->to($destino)->with('success', $mensajeOk);
        }

        return redirect()->to($destino)
            ->withInput()
            ->with('errores_recepcion', $errores !== [] ? $errores : [$mensajeError]);
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

    private function tieneRolEdicion(): bool
    {
        return array_intersect(self::ROLES_EDICION, session()->get('roles') ?? []) !== [];
    }

    private function usuarioId(): ?int
    {
        $id = session()->get('user_id');

        return $id === null ? null : (int) $id;
    }

    /**
     * @param array<string, mixed> $src
     *
     * @return array{fecha_recepcion: ?string, plazo_conservacion_dias: ?int, observaciones: ?string, documento_id: ?int}
     */
    private function datosProvisoriaDe(array $src): array
    {
        return [
            'fecha_recepcion'         => $this->parseFecha($src['fecha_recepcion'] ?? null),
            'plazo_conservacion_dias' => $this->parseEntero($src['plazo_conservacion_dias'] ?? null),
            'observaciones'           => $this->textoOpcional($src['observaciones'] ?? null),
            'documento_id'            => $this->idOpcional($src['documento_id'] ?? null),
        ];
    }

    /**
     * @param array<string, mixed> $src
     *
     * @return array{fecha_recepcion: ?string, observaciones: ?string, documento_id: ?int}
     */
    private function datosDefinitivaDe(array $src): array
    {
        return [
            'fecha_recepcion' => $this->parseFecha($src['fecha_recepcion'] ?? null),
            'observaciones'   => $this->textoOpcional($src['observaciones'] ?? null),
            'documento_id'    => $this->idOpcional($src['documento_id'] ?? null),
        ];
    }

    /**
     * Nombre de cada estado del catálogo, indexado por id, para mostrar el
     * historial de cambios de estado en lenguaje de negocio.
     *
     * @return array<string, string>
     */
    private function nombresDeEstados(): array
    {
        $nombres = [];

        foreach ((new EstadoObraModel())->findAll() as $estado) {
            $nombres[(string) $estado->id] = (string) $estado->estado;
        }

        return $nombres;
    }

    /**
     * @return list<object>
     */
    private function actasVigentes(string $tipoNombre, int $obraId): array
    {
        $tipo = (new TipoDocumentoModel())->findByNombre($tipoNombre);

        return $tipo === null
            ? []
            : (new DocumentoModel())->listarVigentesPorTipo($obraId, (int) $tipo->id);
    }

    /**
     * Normaliza una fecha de formulario a `Y-m-d`: acepta el valor nativo de
     * `<input type="date">` y el formato visual dd/mm/aaaa.
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
     * Entero del formulario. Los rangos los valida el servicio, para que el
     * mensaje de error sea el correcto.
     */
    private function parseEntero(mixed $valor): ?int
    {
        $texto = trim((string) $valor);

        if ($texto === '' || ! ctype_digit($texto)) {
            return null;
        }

        return (int) $texto;
    }

    private function idOpcional(mixed $valor): ?int
    {
        $texto = trim((string) $valor);

        return ctype_digit($texto) && $texto !== '0' ? (int) $texto : null;
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
