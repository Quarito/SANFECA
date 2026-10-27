<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeleccionCertificacion extends Model
{
    protected $table = 'selecciones_certificacion';

    protected $fillable = [
        'user_id',
        'certificacion',
        'tipo_personal',
        'area_certificacion',
    ];
}

