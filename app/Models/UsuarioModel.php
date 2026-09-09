<?php

namespace App\Models;

use CodeIgniter\Model;

class UsuarioModel extends Model
{
    protected $table = 'usuarios';

    protected $primaryKey = 'id';

    protected $returnType = 'object';

    protected $allowedFields = [
        'nombre',
        'apellido',
        'usuario',
        'password_hash',
        'email',
        'activo',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = false;

    protected $useSoftDeletes = false;

    public function findByUsuario(string $usuario): ?object
    {
        return $this->where('usuario', $usuario)->first();
    }
}
