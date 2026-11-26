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

    /*
     * Catálogo PRELIMINAR de licenciaturas (Anuario ANUIES 2000-2001 a 2025-2026) para «Nombre del Título» de la
     * Formación Académica de Origen. Desactivado por omisión; requiere la migración propuesta 2026_11_28_000000.
     * La clasificación es administrativa (no es equivalencia normativa ni criterio de elegibilidad).
     */
    'licenciaturas' => [
        'activo' => (bool) env('ANFECA_CATALOGO_LICENCIATURAS', false),
        'ruta' => env('ANFECA_CATALOGO_LICENCIATURAS_RUTA', storage_path('app/private/catalogos/licenciaturas_anuies.json')),
        'sha256' => env('ANFECA_CATALOGO_LICENCIATURAS_SHA256'),
        'version' => env('ANFECA_CATALOGO_LICENCIATURAS_VERSION', 'ANUIES-2000-2026-LIC-PRELIMINAR'),
        'maximo_resultados' => 60,
        'busqueda_minima' => 3,
        'titulo_manual_maximo' => 200,
        'titulo_manual_minimo' => 5,
    ],

    /*
     * Casilla «Cuento con estudios de posgrado concluidos» (escenarios: solo licenciatura / licenciatura con posgrado).
     * Bandera independiente del catálogo de licenciaturas. Requiere la columna cuenta_con_posgrado.
     */
    'declaracion_posgrado' => [
        'activo' => (bool) env('ANFECA_DECLARACION_POSGRADO', false),
    ],
];
