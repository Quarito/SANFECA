<?php

/*
|--------------------------------------------------------------------------
| ANFECA · Módulo de Coordinación
|--------------------------------------------------------------------------
| Menú lateral. Las opciones con 'ruta' => null están PENDIENTES: se muestran
| deshabilitadas y no ejecutan ninguna operación.
*/

return [

    'menu' => [
        ['clave' => 'inicio', 'etiqueta' => 'Inicio', 'ruta' => 'coordinacion.inicio', 'icono' => '⌂'],
        ['clave' => 'solicitudes', 'etiqueta' => 'Solicitudes', 'ruta' => 'coordinacion.solicitudes', 'icono' => '☰'],
        ['clave' => 'expedientes', 'etiqueta' => 'Expedientes', 'ruta' => null, 'icono' => '▤', 'fase' => '6.3',
            'nota' => 'Consulta básica disponible desde Solicitudes'],
        ['clave' => 'evaluaciones', 'etiqueta' => 'Evaluaciones', 'ruta' => null, 'icono' => '✎', 'fase' => '6.4'],
        ['clave' => 'nuevo-registro', 'etiqueta' => 'Nuevo registro', 'ruta' => null, 'icono' => '+', 'fase' => '6.5'],
        ['clave' => 'actas', 'etiqueta' => 'Actas', 'ruta' => null, 'icono' => '§', 'fase' => '6.7'],
        ['clave' => 'certificados', 'etiqueta' => 'Certificados', 'ruta' => null, 'icono' => '✓', 'fase' => '6.7'],
        ['clave' => 'reportes', 'etiqueta' => 'Reportes', 'ruta' => null, 'icono' => '▦', 'fase' => '6.6'],
        ['clave' => 'historial', 'etiqueta' => 'Historial', 'ruta' => null, 'icono' => '↺', 'fase' => '6.8'],
        ['clave' => 'configuracion', 'etiqueta' => 'Configuración', 'ruta' => null, 'icono' => '⚙', 'fase' => '—'],
    ],

];
