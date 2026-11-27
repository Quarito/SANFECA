<?php

/*
| Control de inactividad de ANFECA. La duración NO se define aquí: es session.lifetime (120 minutos).
| Solo se configuran el aviso visible y el margen de seguridad para que «Continuar sesión» llegue al
| servidor antes del vencimiento (tolera diferencias de reloj y latencia: el navegador recibe segundos
| restantes calculados por el servidor, nunca horas absolutas).
*/

return [
    'activo' => (bool) env('ANFECA_SESION_CONTROL', true),
    // Cuenta regresiva visible (segundos).
    'aviso_segundos' => (int) env('ANFECA_SESION_AVISO_SEGUNDOS', 15),
    // La cuenta regresiva termina este número de segundos ANTES del vencimiento real en el servidor.
    'margen_segundos' => (int) env('ANFECA_SESION_MARGEN_SEGUNDOS', 45),
];
