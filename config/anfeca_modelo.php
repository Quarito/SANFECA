<?php

/*
|--------------------------------------------------------------------------
| ANFECA — Modelo PowerDesigner (PDM) en esquema aislado
|--------------------------------------------------------------------------
| Configuración usada por:
|   php artisan anfeca:modelo-instalar   (crea el esquema; en seco por defecto)
|   php artisan anfeca:modelo-verificar  (solo lectura: inventario y mapeo)
|
| El esquema del PDM es de CONSULTA y COMPARACIÓN. Ningún controlador ni
| modelo Eloquent lo usa todavía; la aplicación sigue trabajando con las
| tablas Laravel de "public".
*/

return [

    // Esquema PostgreSQL donde vive el PDM. Nunca "public".
    'esquema' => 'anfeca_modelo',

    // Única base de datos donde se permite instalar el esquema.
    // Protege contra ejecutarlo por error en otra instalación (p. ej. /var/www/ANFECA).
    'bd_permitida' => env('ANFECA_MODELO_BD_PERMITIDA', 'bd_laravelanfeca_pruebas'),

    // Script versionado (aislado, sin DROP, calificado con el esquema).
    'script' => database_path('sql/anfeca_modelo/anfeca_modelo.sql'),

    // Totales esperados tras la instalación (fuente: anfeca_certifica_1.sql).
    'esperado' => [
        'tablas' => 36,
        'llaves_foraneas' => 35,
    ],

    /*
    |--------------------------------------------------------------------------
    | Mapeo PDM ↔ Laravel (tabla a tabla, campos clave)
    |--------------------------------------------------------------------------
    | "laravel" = tabla en public; "campos" = [columna PDM => columna Laravel].
    | Un valor null significa "sin equivalente directo todavía".
    | "origen": vigente = tabla de las migraciones de desarrollo/anfeca-pruebas
    |           (flujo por solicitud, 04–19/nov);
    |           legado = tabla antigua por usuario que aún se consulta (no borrar).
    | Las tablas PDM llevan nombres en minúsculas porque PostgreSQL pliega los
    | identificadores sin comillas.
    */
    'mapeo' => [
        'persona' => [
            'laravel' => 'datos_solicitantes',
            'origen' => 'vigente',
            'campos' => [
                'pers_nombre' => 'nombres',
                'pers_apellido_paterno' => 'apellido_paterno',
                'pers_apellido_materno' => 'apellido_materno',
                'pers_curp' => 'curp',
                'pers_rfc' => 'rfc',
                'pers_genero' => 'genero',
                'pers_id_institucion' => null,
                'pers_activo' => null,
            ],
        ],
        'usuario' => [
            'laravel' => 'users',
            'origen' => 'vigente',
            'campos' => [
                'usua_nombre' => 'email',
                'usua_contrasenia' => 'password',
                'usua_id_rol' => 'rol',
                'usua_estatus' => null,
            ],
        ],
        'domicilio' => [
            'laravel' => 'datos_solicitantes',
            'origen' => 'vigente',
            'campos' => [
                'domi_calle' => 'calle',
                'domi_numero_ext' => 'numero_exterior',
                'domi_numero_int' => 'numero_interior',
                'domi_id_entidad_federativa' => 'entidad_federativa',
            ],
        ],
        'comunicacion' => [
            'laravel' => 'datos_solicitantes',
            'origen' => 'vigente',
            'campos' => [
                'comu_valor' => 'telefono_particular',
            ],
        ],
        'colonia' => [
            'laravel' => 'datos_solicitantes',
            'origen' => 'vigente',
            'campos' => [
                'colo_nombre' => 'colonia',
                'colo_codigo_postal' => 'codigo_postal',
            ],
        ],
        'alcaldia_municipio' => [
            'laravel' => 'datos_solicitantes',
            'origen' => 'vigente',
            'campos' => ['almu_nombre' => 'municipio'],
        ],
        'institucion' => [
            'laravel' => 'instituciones_afiliadas',
            'origen' => 'vigente',
            'campos' => [
                'inst_numero_afiliacion' => 'numero_afiliacion',
                'inst_nombre' => 'nombre',
                'inst_zona' => 'zona_anfeca',
                'inst_facultad' => null,
            ],
        ],
        'sede' => [
            'laravel' => 'instituciones_afiliadas',
            'origen' => 'vigente',
            'campos' => ['sede_descripcion' => 'sede'],
        ],
        'rol' => [
            'laravel' => 'roles',
            'origen' => 'vigente',
            'campos' => ['rol_nombre' => 'nombre'],
        ],
        'privilegio' => [
            'laravel' => 'privilegios',
            'origen' => 'vigente',
            'campos' => [
                'priv_nombre' => 'nombre',
                'priv_descripcion' => 'descripcion',
            ],
        ],
        'rol_privilegio' => [
            'laravel' => 'rol_privilegio',
            'origen' => 'vigente',
            'campos' => [
                'ropr_id_rol' => 'rol_id',
                'ropr_id_privilegio' => 'privilegio_id',
            ],
        ],
        'solicitud' => [
            'laravel' => 'solicitudes_certificacion',
            'origen' => 'vigente',
            'campos' => [
                'soli_id_persona' => 'user_id',
                'soli_id_area_certificacion' => 'area_certificacion',
                'soli_id_tipo_personal_academico' => 'tipo_personal',
                'soli_id_convocatoria' => 'certificacion',
                'soli_fecha_registro_solicitud' => 'created_at',
                'soli_numero_certificado' => null,
                'soli_folio_solicitud' => null,
            ],
        ],
        'estado_solicitud' => [
            'laravel' => 'solicitudes_certificacion',
            'origen' => 'vigente',
            'campos' => [
                'esso_descripcion' => 'estado',
                'esso_fecha' => 'fecha_envio',
                'esso_motivo_rechazo' => null,
            ],
        ],
        'area_certificacion' => [
            'laravel' => 'selecciones_certificacion',
            'origen' => 'vigente',
            'campos' => ['area_descripcion' => 'area_certificacion'],
        ],
        'tipo_personal_academico' => [
            'laravel' => 'selecciones_certificacion',
            'origen' => 'vigente',
            'campos' => ['tipa_descripcion' => 'tipo_personal'],
        ],
        'historico_certificado' => [
            'laravel' => 'certificados_historicos',
            'origen' => 'vigente',
            'campos' => [
                'hice_curp' => 'curp_historica',
                'hice_numero_certificacion' => 'numero_certificado',
                'hice_folio_certificado' => null,
                'hice_area_certificacion' => null,
            ],
        ],
        'refrendo_certificado' => [
            'laravel' => 'datos_refrendo',
            'origen' => 'vigente',
            'campos' => [
                'rece_numero_refrendo' => 'numero_refrendo_asignado',
                'rece_art14' => 'aplica_articulo_14',
                'rece_id_certificado' => null,
                'rece_anio_refrendo' => null,
            ],
        ],
        'persona_estudio_profesional' => [
            'laravel' => 'formaciones_academicas_solicitud',
            'origen' => 'vigente',
            'campos' => [
                'pees_url_titulo' => 'titulo_origen_archivo',
                'pees_url_cedula' => 'cedula_origen_archivo',
                'pees_cedula' => null,
            ],
        ],
        'estudio_profesional' => [
            'laravel' => 'formaciones_academicas_solicitud',
            'origen' => 'vigente',
            'campos' => ['espr_nombre' => 'titulo_origen'],
        ],
        'nivel_profesional' => [
            'laravel' => 'formaciones_academicas_solicitud',
            'origen' => 'vigente',
            'campos' => ['nipr_nivel_profesional' => 'nivel_origen'],
        ],
        'solicitud_documento' => [
            'laravel' => 'documentos_probatorios_solicitud',
            'origen' => 'vigente',
            'campos' => ['socu_id_solicitud' => 'solicitud_id'],
        ],
        'pago' => [
            'laravel' => 'documentos_probatorios_solicitud',
            'origen' => 'vigente',
            'campos' => [
                'pago_id_solicitud' => 'solicitud_id',
                'pago_url_comprobante' => 'comprobante_pago_archivo',
            ],
        ],
    ],

    // Tablas del PDM sin contraparte Laravel por ahora (etapa posterior).
    'sin_contraparte' => [
        'acta', 'certificado', 'convocatoria', 'tipo_convocatoria', 'c_estado_solicitud',
        'documento', 'estado_documento', 'estado_solicitud_documento',
        'evaluacion', 'dictamen', 'tipo_dictamen', 'articulo_subindice',
        'entidad_federativa', 'tipo_comunicacion',
    ],
];
