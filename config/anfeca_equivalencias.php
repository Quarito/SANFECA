<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Equivalencias de títulos de origen por área de certificación
    |--------------------------------------------------------------------------
    |
    | Las claves deben coincidir con los valores de SeleccionCertificacion.vue.
    | Los títulos deben coincidir con FormacionAcademica.vue.
    |
    | Cada área puede admitir uno o varios títulos.
    |
    */

    'areas' => [

        'Contaduría Pública' => [
            'Contaduría',
        ],

        'Administración' => [
            'Administración',
        ],

        'Informática Administrativa' => [
            'Informática',
        ],

        'Negocios Internacionales' => [
            'Negocios Internacionales',
        ],

        'Disciplinas vinculadas al ámbito económico-administrativo' => [
            'Otro',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Títulos sujetos a revisión
    |--------------------------------------------------------------------------
    */

    'titulos_revision' => [
        'Otro',
    ],

];
