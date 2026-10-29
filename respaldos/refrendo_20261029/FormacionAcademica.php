<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormacionAcademica extends Model
{
    protected $table = 'formaciones_academicas';

    protected $fillable = [
        'user_id',
        'nivel_origen',
        'titulo_origen',
        'nivel_ultimo_grado',
        'titulo_ultimo_grado',
        'institucion_afiliada_id',
        'titulo_origen_archivo',
        'cedula_origen_archivo',
        'titulo_ultimo_grado_archivo',
        'cedula_ultimo_grado_archivo',
    ];

    public function institucion()
    {
        return $this->belongsTo(
            InstitucionAfiliada::class,
            'institucion_afiliada_id'
        );
    }
}

