<?php

/*
|--------------------------------------------------------------------------
| ANFECA · Catálogos académicos
|--------------------------------------------------------------------------
| Catálogo de posgrados (Especialidad, Maestría, Doctorado) para «Último grado de estudios».
| - El JSON se GENERA fuera del repositorio (scripts/anfeca/catalogos/clasificar_posgrados.py) y se
|   instala en una ubicación privada de ejecución legible por PHP (nunca en public/, nunca el Excel).
| - La bandera está DESACTIVADA por omisión. Con la bandera activa, el formulario nuevo solo se usa si
|   además el archivo es íntegro y la migración aditiva está aplicada; si no, se registra el error y se
|   conserva el formulario anterior con un aviso visible (nunca un catálogo vacío silencioso).
*/

return [
    'posgrados' => [
        'activo' => (bool) env('ANFECA_CATALOGO_POSGRADOS', false),
        'ruta' => env('ANFECA_CATALOGO_POSGRADOS_RUTA', storage_path('app/private/catalogos/posgrados_anuies_2025.json')),
        // Huella esperada del JSON instalado (opcional pero recomendada): si no coincide, el catálogo no se usa.
        'sha256' => env('ANFECA_CATALOGO_POSGRADOS_SHA256'),
        'version' => env('ANFECA_CATALOGO_POSGRADOS_VERSION', 'ANUIES-2025'),
        // Límites del endpoint y de la captura manual.
        'maximo_resultados' => 400,
        'titulo_manual_maximo' => 200,   // cabe también en titulo_ultimo_grado_otro (varchar 200)
        'titulo_manual_minimo' => 5,
    ],
];
