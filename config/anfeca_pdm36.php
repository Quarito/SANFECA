<?php

return [
    // Opera Laravel EXCLUSIVAMENTE sobre el modelo aprobado de 36 tablas (anfeca_modelo), sin public ni anfeca_compat.
    // Desactivado por omisión: con false la aplicación usa sus tablas actuales sin ningún cambio.
    'activo' => (bool) env('ANFECA_PDM36', false),
];
