<?php

namespace App\Controllers\Inspector;

use App\Controllers\BaseController;
use App\Models\InspectoresObrasModel;
use App\Models\ObraModel;
use App\Services\ObraAlmacenamiento;

/**
 * Vista operativa de las obras del inspector.
 *
 * En esta fase solo se prepara la ruta, la validación de pertenencia y la
 * estructura física de almacenamiento: un inspector únicamente puede
 * acceder a las obras que tiene asignadas de forma vigente. La vista
 * operativa completa se implementará en una etapa posterior.
 */
class Obras extends BaseController
{
    private ObraAlmacenamiento $almacenamiento;

    public function __construct()
    {
        $this->almacenamiento = new ObraAlmacenamiento();
    }

    /**
     * Pantalla preparada para la futura vista operativa de la obra.
     *
     * Verifica que el usuario autenticado sea el inspector vigente de la
     * obra antes de mostrar cualquier contenido, para impedir el acceso
     * arbitrario por URL a obras que no le corresponden. Solo cuando la
     * pertenencia es válida se prepara la estructura física de
     * almacenamiento de la obra (IMAGENES y THUMBNAILS).
     */
    public function ver(int $id)
    {
        $session   = session();
        $usuarioId = (int) $session->get('user_id');

        $obra = (new ObraModel())->findDetalle($id);

        if ($obra === null) {
            return redirect()->to('/inspector/dashboard')
                ->with('error', 'La obra seleccionada no existe.');
        }

        if (! (new InspectoresObrasModel())->esVigente($id, $usuarioId)) {
            return redirect()->to('/inspector/dashboard')
                ->with('warning', 'No puede acceder a esa obra. Solo puede consultar las obras que tiene asignadas como inspector.');
        }

        if (! $this->almacenamiento->asegurarEstructuraObra((string) $obra->codigo)) {
            session()->setFlashdata('error', 'No fue posible preparar el almacenamiento de la obra. Intente nuevamente.');
        }

        return view('inspector/obra', [
            'titulo'    => $obra->nombre,
            'user_name' => $session->get('user_name'),
            'username'  => $session->get('username'),
            'roles'     => $session->get('roles') ?? [],
            'obra'      => $obra,
        ]);
    }
}