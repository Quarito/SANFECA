<?php

/*
|--------------------------------------------------------------------------
| ANFECA — Integración funcional con el modelo PowerDesigner (anfeca_modelo)
|--------------------------------------------------------------------------
| Fuente de catálogos y reglas de normalización. Ver docs/anfeca/DECISIONES_DATOS_F1.md.
| Regla general: solo se registran equivalencias verificables (mayúsculas, acentos,
| espacios y nombres oficiales cortos de INEGI). Todo lo demás queda en REVISIÓN;
| nunca se reemplaza el dato histórico.
*/

return [

    // Ver App\Services\Pdm\ModoPdm. En F1 solo se admite "apagado".
    'modo' => env('ANFECA_PDM_MODO', 'apagado'),

    // Pruebas PostgreSQL: el nombre de la base debe terminar así (protección).
    'sufijo_bd_pruebas' => '_pruebas_pg',

    'catalogos' => [

        // Clave INEGI = ID en ENTIDAD_FEDERATIVA (nombres oficiales del Marco Geoestadístico).
        'entidades_federativas' => [
            1 => 'Aguascalientes', 2 => 'Baja California', 3 => 'Baja California Sur',
            4 => 'Campeche', 5 => 'Coahuila de Zaragoza', 6 => 'Colima', 7 => 'Chiapas',
            8 => 'Chihuahua', 9 => 'Ciudad de México', 10 => 'Durango', 11 => 'Guanajuato',
            12 => 'Guerrero', 13 => 'Hidalgo', 14 => 'Jalisco', 15 => 'México',
            16 => 'Michoacán de Ocampo', 17 => 'Morelos', 18 => 'Nayarit', 19 => 'Nuevo León',
            20 => 'Oaxaca', 21 => 'Puebla', 22 => 'Querétaro', 23 => 'Quintana Roo',
            24 => 'San Luis Potosí', 25 => 'Sinaloa', 26 => 'Sonora', 27 => 'Tabasco',
            28 => 'Tamaulipas', 29 => 'Tlaxcala', 30 => 'Veracruz de Ignacio de la Llave',
            31 => 'Yucatán', 32 => 'Zacatecas',
        ],

        // Alias verificables (forma ya normalizada: minúsculas, sin acentos, un espacio).
        // Solo abreviaturas y nombres cortos oficiales de la misma entidad.
        'alias_entidades' => [
            'cdmx' => 'Ciudad de México',
            'ciudad de mexico' => 'Ciudad de México',
            'coahuila' => 'Coahuila de Zaragoza',
            'michoacan' => 'Michoacán de Ocampo',
            'veracruz' => 'Veracruz de Ignacio de la Llave',
            'estado de mexico' => 'México',
        ],

        // Valores que NO son entidades: se marcan en revisión con este motivo.
        'no_entidades' => [
            'monterrey' => 'Monterrey es un municipio (Nuevo León); no se infiere la entidad sin confirmación.',
            'entidad de prueba' => 'Valor de prueba; no corresponde a ninguna entidad.',
        ],

        // TIPO_COMUNICACION: clave interna => descripción.
        'tipos_comunicacion' => [
            'TEL_PARTICULAR' => 'Teléfono particular',
            'TEL_CELULAR' => 'Teléfono celular',
            'CORREO' => 'Correo electrónico',
        ],

        // C_ESTADO_SOLICITUD (eventos, D-3) y siglas para ESTADO_SOLICITUD.
        'estados_solicitud' => [
            'BO' => 'Borrador',
            'FA' => 'Formación académica autorizada',
            'CA' => 'Compromiso de entrega aceptado',
            'EN' => 'Enviada',
        ],

        // DOCUMENTO: campo Laravel => descripción del catálogo.
        'documentos' => [
            'titulo_origen_archivo' => 'Título de licenciatura (origen)',
            'cedula_origen_archivo' => 'Cédula de licenciatura (origen)',
            'titulo_ultimo_grado_archivo' => 'Título del último grado',
            'cedula_ultimo_grado_archivo' => 'Cédula del último grado',
            'constancia_antiguedad_archivo' => 'Constancia de antigüedad',
            'comprobante_pago_archivo' => 'Comprobante de pago',
            'solicitud_articulo_14_archivo' => 'Solicitud de artículo 14',
            'constancia_articulo_14_archivo' => 'Constancia probatoria de artículo 14',
            'link' => 'Enlace de evidencias',
        ],

        'estados_documento' => [
            'CARGADO' => 'Cargado',
            'SUSTITUIDO' => 'Sustituido',
        ],

        // Incisos de DatosRefrendo.vue / DatoRefrendoController.
        'articulos_subindice' => [
            '13.1' => 'Artículo 13, inciso 13.1',
            '14.1' => 'Artículo 14, inciso 14.1',
            '14.2' => 'Artículo 14, inciso 14.2',
            '14.3' => 'Artículo 14, inciso 14.3',
        ],

        // FormacionAcademica.vue (niveles) y FormacionAcademicaController.
        'niveles_profesionales' => ['Licenciatura', 'Especialidad', 'Maestría', 'Doctorado'],

        // FormacionAcademica.vue (titulosOrigen = titulosUltimoGrado, sin "Otro").
        'titulos' => ['Administración', 'Contaduría', 'Informática', 'Negocios Internacionales'],

        // SeleccionCertificacion.vue
        'areas_certificacion' => [
            'Contaduría Pública',
            'Administración',
            'Informática Administrativa',
            'Negocios Internacionales',
            'Disciplinas vinculadas al ámbito económico-administrativo',
        ],

        'tipos_personal_academico' => [
            'Profesoras y profesores de Tiempo Completo',
            'Profesoras y profesores de Medio Tiempo',
            'Profesoras y profesores de Asignatura de Educación Superior',
        ],

        // Género: valor de Laravel => PERS_GENERO (1 carácter).
        'generos' => [
            'Femenino' => 'F',
            'Masculino' => 'M',
        ],
    ],
];
