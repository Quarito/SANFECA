<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentoProbatorio extends Model
{
    protected $table = 'documentos_probatorios';

    protected $fillable = [
        'user_id',
        'constancia_antiguedad_archivo',
        'comprobante_pago_archivo',
        'link',
    ];
}
