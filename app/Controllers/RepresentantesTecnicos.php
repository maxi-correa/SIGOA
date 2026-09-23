<?php

namespace App\Controllers;

use App\Models\ObrasRepresentantesTecnicosModel;
use App\Models\RepresentanteTecnicoModel;
use App\Models\TipoTituloProfesionalModel;
use Throwable;

/**
 * Gestión de representantes técnicos.
 *
 * Accesible solo para SUPERADMINISTRADOR y ADMINISTRADOR.
 * La autorización se controla mediante el filtro RoleFilter en las rutas.
 *
 * La asignación de representantes a obras se gestiona desde la ficha de
 * obra; este módulo solo administra el padrón de representantes.
 */
class RepresentantesTecnicos extends BaseController
{
    protected RepresentanteTecnicoModel $representanteModel;

    protected TipoTituloProfesionalModel $tipoTituloModel;

    protected ObrasRepresentantesTecnicosModel $asignacionModel;

    public function __construct()
    {
        $this->representanteModel = new RepresentanteTecnicoModel();
        $this->tipoTituloModel    = new TipoTituloProfesionalModel();
        $this->asignacionModel    = new ObrasRepresentantesTecnicosModel();
    }

    /**
     * Listado de representantes técnicos ordenado por apellido y nombre.
     */
    public function index()
    {
        $session = session();

        $data = [
            'titulo'         => 'Representantes técnicos',
            'user_name'      => $session->get('user_name'),
            'username'       => $session->get('username'),
            'roles'          => $session->get('roles') ?? [],
            'representantes' => $this->representanteModel->listarTodasConTitulo(),
        ];

        return view('representantes/index', $data);
    }

    /**
     * Formulario de alta de representante técnico.
     */
    public function nuevo()
    {
        $session = session();

        $data = [
            'titulo'    => 'Nuevo representante técnico',
            'user_name' => $session->get('user_name'),
            'username'  => $session->get('username'),
            'roles'     => $session->get('roles') ?? [],
            'titulos'   => $this->tipoTituloModel->findAllActivos(),
        ];

        return view('representantes/formulario', $data);
    }

    /**
     * Procesa el alta de un representante técnico.
     */
    public function crear()
    {
        $datos   = $this->tomarDatos();
        $errores = $this->validarDatos($datos);

        if ($errores !== []) {
            return redirect()->to('/representantes/nuevo')
                ->withInput()
                ->with('errores_representante', $errores);
        }

        try {
            $this->representanteModel->crear($datos);
        } catch (Throwable $e) {
            return redirect()->to('/representantes/nuevo')
                ->withInput()
                ->with('error', 'No fue posible registrar el representante técnico. Verifique que la matrícula no esté repetida.');
        }

        return redirect()->to('/representantes')
            ->with('success', 'El representante técnico fue registrado correctamente.');
    }

    /**
     * Formulario de edición de representante técnico.
     */
    public function editar(int $id)
    {
        $representante = $this->representanteModel->find($id);

        if ($representante === null) {
            return redirect()->to('/representantes')
                ->with('error', 'El representante técnico seleccionado no existe.');
        }

        $session = session();

        $data = [
            'titulo'        => 'Editar representante técnico',
            'user_name'     => $session->get('user_name'),
            'username'      => $session->get('username'),
            'roles'         => $session->get('roles') ?? [],
            'representante' => $representante,
            'titulos'       => $this->tipoTituloModel->findAllActivos(),
        ];

        return view('representantes/formulario', $data);
    }

    /**
     * Procesa la edición de un representante técnico.
     *
     * `created_at` NO se modifica; `updated_at` se refresca.
     */
    public function actualizar(int $id)
    {
        $representante = $this->representanteModel->find($id);

        if ($representante === null) {
            return redirect()->to('/representantes')
                ->with('error', 'El representante técnico seleccionado no existe.');
        }

        $datos   = $this->tomarDatos();
        $errores = $this->validarDatos($datos, $id);

        if ($errores !== []) {
            return redirect()->to('/representantes/editar/' . $id)
                ->withInput()
                ->with('errores_representante', $errores);
        }

        try {
            $this->representanteModel->actualizar($id, $datos);
        } catch (Throwable $e) {
            return redirect()->to('/representantes/editar/' . $id)
                ->withInput()
                ->with('error', 'No fue posible actualizar el representante técnico. Verifique que la matrícula no esté repetida.');
        }

        return redirect()->to('/representantes')
            ->with('success', 'Los datos del representante técnico fueron actualizados correctamente.');
    }

    /**
     * Activa o desactiva un representante técnico del padrón.
     *
     * No se elimina físicamente: el representante inactivo conserva sus
     * datos y el historial de asignaciones de las obras. No se permite
     * desactivar un representante con asignación vigente, porque la obra
     * quedaría sin representante actual. Se solicita confirmación previa
     * desde el frontend.
     */
    public function cambiarEstado()
    {
        $id            = (int) $this->request->getPost('representante_id');
        $activar       = (int) $this->request->getPost('activo') === 1;
        $representante = $this->representanteModel->find($id);

        if ($representante === null) {
            return redirect()->to('/representantes')
                ->with('error', 'El representante técnico seleccionado no existe.');
        }

        if (! $activar && $this->asignacionModel->tieneAsignacionVigente($id)) {
            return redirect()->to('/representantes')
                ->with('error', 'No se puede desactivar el representante técnico porque está asignado actualmente a una obra. Para desactivarlo, primero debe reemplazarlo en esas obras.');
        }

        if (! $this->representanteModel->cambiarActivo($id, $activar ? 1 : 0)) {
            return redirect()->to('/representantes')
                ->with('error', 'No fue posible actualizar el estado del representante técnico.');
        }

        return redirect()->to('/representantes')
            ->with('success', $activar
                ? 'El representante técnico fue reactivado correctamente.'
                : 'El representante técnico fue desactivado correctamente. Conserva su historial en las obras.');
    }

    /* ================================================================
       MÉTODOS PRIVADOS
       ================================================================ */

    /**
     * Toma y normaliza los datos del formulario.
     *
     * Los nombres administrativos se almacenan en mayúsculas. La matrícula
     * vacía se guarda como NULL (permite múltiples sin matrícula).
     *
     * @return array<string, mixed>
     */
    private function tomarDatos(): array
    {
        $nombre    = trim((string) $this->request->getPost('nombre'));
        $apellido  = trim((string) $this->request->getPost('apellido'));
        $tituloId  = (int) $this->request->getPost('titulo_profesional_id');
        $matricula = trim((string) $this->request->getPost('matricula'));

        return [
            'nombre'                => mb_strtoupper($nombre),
            'apellido'              => mb_strtoupper($apellido),
            'titulo_profesional_id' => $tituloId,
            'matricula'             => ($matricula === '') ? null : $matricula,
        ];
    }

    /**
     * Valida los datos de un representante técnico.
     *
     * @param array<string, mixed> $datos
     * @param int|null             $exceptoId ID a excluir de la comprobación de matrícula (edición)
     *
     * @return list<string>
     */
    private function validarDatos(array $datos, ?int $exceptoId = null): array
    {
        $errores = [];

        if ($datos['nombre'] === '') {
            $errores[] = 'El nombre es obligatorio.';
        } elseif (mb_strlen((string) $datos['nombre']) > 100) {
            $errores[] = 'El nombre no puede superar los 100 caracteres.';
        }

        if ($datos['apellido'] === '') {
            $errores[] = 'El apellido es obligatorio.';
        } elseif (mb_strlen((string) $datos['apellido']) > 100) {
            $errores[] = 'El apellido no puede superar los 100 caracteres.';
        }

        if ($datos['titulo_profesional_id'] <= 0) {
            $errores[] = 'Debe seleccionar un título profesional.';
        } elseif (! $this->esTituloActivo($datos['titulo_profesional_id'])) {
            $errores[] = 'El título profesional seleccionado no es válido.';
        }

        if ($datos['matricula'] !== null) {
            if (mb_strlen((string) $datos['matricula']) > 30) {
                $errores[] = 'La matrícula no puede superar los 30 caracteres.';
            } elseif ($this->representanteModel->existeMatricula((string) $datos['matricula'], $exceptoId)) {
                $errores[] = 'Ya existe un representante técnico con la matrícula ' . (string) $datos['matricula'] . '.';
            }
        }

        return $errores;
    }

    /**
     * Indica si el título profesional existe y está activo.
     */
    private function esTituloActivo(int $tituloId): bool
    {
        foreach ($this->tipoTituloModel->findAllActivos() as $titulo) {
            if ((int) $titulo->id === $tituloId) {
                return true;
            }
        }

        return false;
    }
}
