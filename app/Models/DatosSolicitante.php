<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DatosSolicitante extends Model
{
    protected $table = 'datos_solicitantes';

    protected $fillable = [
        'user_id',
        'curp',
        'rfc',
        'cedula_profesional',
        'nombres',
        'apellido_paterno',
        'apellido_materno',
        'calle',
        'numero_exterior',
        'numero_interior',
        'codigo_postal',
        'entidad_federativa',
        'colonia',
        'municipio',
        'correo_electronico',
        'telefono_particular',
        'telefono_celular',
'datos_autorizados',
'fecha_autorizacion',
    ];


protected function casts(): array
{
    return [
        'datos_autorizados' => 'boolean',
        'fecha_autorizacion' => 'datetime',
    ];
}
}

