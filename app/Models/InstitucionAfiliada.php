<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstitucionAfiliada extends Model
{
    protected $table = 'instituciones_afiliadas';

    protected $fillable = [
        'numero_afiliacion',
        'nombre',
        'sede',
        'zona_anfeca',
    ];
}

