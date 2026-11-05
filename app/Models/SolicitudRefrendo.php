<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudRefrendo extends Model
{
    protected $table = 'solicitudes_refrendo';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['articulo_14' => 'boolean', 'seleccion' => 'array', 'formacion' => 'array',
            'documentos' => 'array', 'datos_personales' => 'array', 'completado_at' => 'datetime'];
    }
}
