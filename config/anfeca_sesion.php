<?php

/*
| Control de inactividad de ANFECA. La duración NO se define aquí: es session.lifetime (120 minutos).
| Solo se configuran el aviso visible y el margen de red. El navegador recibe segundos restantes calculados por el
| servidor (nunca horas absolutas), así que no depende de que los relojes coincidan.
| Con 120 min, aviso 15 s y margen 5 s: aviso a los 119:40 de inactividad, cuenta regresiva hasta 119:55,
| cierre en el servidor al cumplirse 120:00 y verificación del navegador a los 120:02.
*/

return [
    'activo' => (bool) env('ANFECA_SESION_CONTROL', true),
    // Cuenta regresiva visible (segundos).
    'aviso_segundos' => (int) env('ANFECA_SESION_AVISO_SEGUNDOS', 15),
    // Margen de red: la cuenta regresiva termina este número de segundos antes del vencimiento real, para que un
    // clic en «Continuar sesión» en el último segundo llegue al servidor a tiempo. NO adelanta el cierre: la sesión
    // termina en el servidor al cumplirse exactamente session.lifetime de inactividad.
    'margen_segundos' => (int) env('ANFECA_SESION_MARGEN_SEGUNDOS', 5),
];
