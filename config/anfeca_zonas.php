<?php

/*
|--------------------------------------------------------------------------
| ANFECA · Zonas regionales OFICIALES (referencia, no catálogo de base de datos)
|--------------------------------------------------------------------------
| Fuente: directorio oficial de la ANFECA, consultado el 2026-11-25:
|   https://www.anfeca.unam.mx/directorio_instituciones.php  («Zona 1 - Noroeste» … «Zona 7 - Ciudad de México»)
|   https://www.anfeca.unam.mx/zona1_noroeste.php … zona7_ciudad_mexico.php  (entidades por zona)
| Se usa para INTERPRETAR datos ya cargados (instituciones_afiliadas.zona_anfeca) y presentar la zona
| de forma homogénea. NO concede acceso: la autorización regional sigue dependiendo de zona_id y de
| coordinador_zona. Cambios en la integración territorial de la ANFECA deben actualizarse aquí con su
| fuente y fecha.
*/

return [
    'fuente' => 'https://www.anfeca.unam.mx/directorio_instituciones.php',
    'consultado' => '2026-11-25',
    'zonas' => [
        1 => ['romano' => 'I', 'nombre' => 'Noroeste', 'entidades' => ['Baja California', 'Baja California Sur', 'Chihuahua', 'Sinaloa', 'Sonora']],
        2 => ['romano' => 'II', 'nombre' => 'Norte', 'entidades' => ['Coahuila', 'Nuevo León', 'Tamaulipas']],
        3 => ['romano' => 'III', 'nombre' => 'Centro', 'entidades' => ['Aguascalientes', 'Durango', 'Querétaro', 'San Luis Potosí', 'Zacatecas']],
        4 => ['romano' => 'IV', 'nombre' => 'Centro Occidente', 'entidades' => ['Colima', 'Guanajuato', 'Jalisco', 'Michoacán', 'Nayarit']],
        5 => ['romano' => 'V', 'nombre' => 'Centro Sur', 'entidades' => ['Guerrero', 'Hidalgo', 'Estado de México', 'Morelos', 'Puebla', 'Tlaxcala']],
        6 => ['romano' => 'VI', 'nombre' => 'Sur', 'entidades' => ['Chiapas', 'Oaxaca', 'Tabasco', 'Veracruz', 'Campeche', 'Yucatán', 'Quintana Roo']],
        7 => ['romano' => 'VII', 'nombre' => 'Ciudad de México', 'entidades' => ['Ciudad de México']],
    ],
];
