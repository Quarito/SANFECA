<?php

/*
|--------------------------------------------------------------------------
| ANFECA · Módulo de Coordinación
|--------------------------------------------------------------------------
| Menú lateral. Las opciones con 'ruta' => null están PENDIENTES: se muestran
| deshabilitadas y no ejecutan ninguna operación.
*/

return [

    // Prototipos visuales AISLADOS (sin consultas ni escrituras en la base).
    // Desactivados por omisión: solo se habilitan en entornos de demostración desechables.
    'prototipos' => (bool) env('ANFECA_COORDINACION_PROTOTIPOS', false),

    // Revisión documental funcional (fase 6.3.1). DESACTIVADA por omisión: requiere la tabla
    // revisiones_documentales (database/migrations/2026_11_26_000000_…; se aplica solo con --path).
    'revision_documental' => (bool) env('ANFECA_COORDINACION_REVISION', false),

    // Límite homologado de observaciones y motivo de cambio (Vue, Laravel, PostgreSQL y PDM futuro).
    'revision_texto_maximo' => 1000,
    'revision_texto_minimo' => 10,

    'menu' => [
        ['clave' => 'inicio', 'etiqueta' => 'Inicio', 'ruta' => 'coordinacion.inicio', 'icono' => '⌂'],
        ['clave' => 'solicitudes', 'etiqueta' => 'Solicitudes', 'ruta' => 'coordinacion.solicitudes', 'icono' => '☰'],
        ['clave' => 'seguimiento', 'etiqueta' => 'Seguimiento de registros', 'ruta' => 'coordinacion.seguimiento', 'icono' => '◔'],
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
