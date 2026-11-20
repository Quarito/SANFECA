<?php

/*
|--------------------------------------------------------------------------
| ANFECA — Decisiones registro por registro para la migración al PDM (F2)
|--------------------------------------------------------------------------
| Este archivo es la ÚNICA vía para resolver incidencias bloqueantes. Cada entrada
| debe provenir de una fuente confirmada (padrón oficial, documento del solicitante,
| corrección validada). Nada se infiere automáticamente.
|
| Las claves son IDs de public (no datos personales). Mientras falte una decisión,
| "php artisan anfeca:pdm-migrar --ejecutar" se niega a escribir.
| Ver docs/anfeca/F2_MIGRACION_DATOS.md.
*/

return [

    // D-11 · instituciones_afiliadas.id => datos oficiales confirmados, o 'no_migrar'.
    // Ejemplo: 1 => ['numero_afiliacion' => 'XXXXXXX', 'facultad' => 'Nombre oficial', 'fuente' => 'Padrón ANFECA 2026'],
    'instituciones' => [
    ],

    // D-13 · datos_solicitantes.id => entidad del catálogo INEGI confirmada
    // (texto exacto de config anfeca_pdm.catalogos.entidades_federativas) o 'sin_domicilio'.
    // Ejemplo: 7 => ['entidad' => 'Nuevo León', 'fuente' => 'Confirmado con la persona'],
    'domicilios' => [
    ],

    // D-14 · certificados_historicos.id => número corregido (^[0-9]{1,7}$) con su fuente,
    // o 'conservar_en_revision' (se conserva íntegro en la importación, sin eventos normalizados).
    // Ejemplo: 12 => ['numero' => '1000045', 'fuente' => 'Acta 2021'],
    'certificados' => [
    ],

    // D-8 · regla para SOLICITUD.SOLO_ART13 (aceptada como recomendación del plan).
    'regla_solo_art13' => 'refrendo_sin_articulo_14',
];
