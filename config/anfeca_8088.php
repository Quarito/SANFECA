<?php

/*
|--------------------------------------------------------------------------
| ANFECA sobre las 36 tablas oficiales (PowerDesigner)
|--------------------------------------------------------------------------
|
| Diccionario de datos de los catálogos que la aplicación necesita dentro del modelo oficial.
| Solo son VALORES (filas de catálogo): ninguna entrada crea tablas, columnas ni esquemas.
| Las decisiones de mapeo están en docs/anfeca/INTEGRACION_36_TABLAS_8088.md.
|
*/

return [

    // Archivo aprobado y su huella. anfeca:verificar-esquema compara la base contra este archivo.
    'sql_oficial' => database_path('sql/oficial/anfeca_modelo_36_tablas_vigente.sql'),

    // Disco y carpeta de los expedientes. Ruta de cada archivo: {carpeta}/{SOLI_ID}/{SOCU_ID}.{ext}
    'disco' => env('ANFECA_DISCO_EXPEDIENTES', 'local'),
    'carpeta' => 'anfeca/expedientes',

    // USUARIO.USUA_ESTATUS (VARCHAR 20)
    'estatus_usuario' => [
        'ACTIVO' => 'ACTIVO',                       // cuenta registrada, datos sin autorizar
        'AUTORIZADO' => 'DATOS_AUTORIZADOS',        // datos personales autorizados (bloqueados)
        'INACTIVO' => 'INACTIVO',
    ],

    // TIPO_COMUNICACION: clave interna => TICO_TIPO_COMUNICACION (VARCHAR 50). Solo medios de contacto.
    // La información geográfica proviene exclusivamente de ENTIDAD_FEDERATIVA, ALCALDIA_MUNICIPIO y COLONIA (SEPOMEX).
    // TEL_PARTICULAR usa la descripción vigente en el catálogo de 8088 («Teléfono de casa»); la etiqueta del formulario
    // sigue siendo «Teléfono particular». Una base que ya lo registró como «Teléfono particular» se reconoce como
    // equivalente: se lee y se escribe sobre el tipo existente y nunca se crea un segundo tipo.
    'tipos_comunicacion' => [
        'CORREO' => 'Correo electrónico',
        'TEL_PARTICULAR' => 'Teléfono de casa',
        'TEL_CELULAR' => 'Teléfono celular',
    ],
    'tipos_comunicacion_equivalentes' => [
        'TEL_PARTICULAR' => ['Teléfono particular'],
    ],

    // C_ESTADO_SOLICITUD (VARCHAR 50) y siglas de ESTADO_SOLICITUD.ESSO_SIGLAS (VARCHAR 2)
    'estados_solicitud' => [
        'BO' => 'Borrador',
        'RF' => 'Datos de refrendo registrados',
        'FA' => 'Formación académica autorizada',
        'DP' => 'Documentos probatorios registrados',
        'CA' => 'Compromiso de entrega aceptado',
        'EN' => 'Enviada',
    ],

    // DOCUMENTO.DOCU_DESCRIPCION (VARCHAR 150): campo de la aplicación => descripción
    'documentos' => [
        'titulo_origen_archivo' => 'Título de licenciatura (origen)',
        'cedula_origen_archivo' => 'Cédula de licenciatura (origen)',
        'titulo_ultimo_grado_archivo' => 'Título del último grado',
        'cedula_ultimo_grado_archivo' => 'Cédula del último grado',
        'constancia_antiguedad_archivo' => 'Constancia de antigüedad',
        'comprobante_pago_archivo' => 'Comprobante de pago',
        'solicitud_articulo_14_archivo' => 'Solicitud por artículo 14',
        'constancia_articulo_14_archivo' => 'Constancia probatoria de artículo 14',
        'link' => 'Enlace de evidencias',
    ],

    // ESTADO_DOCUMENTO.ESDO_ESTADO_DOCUMENTO (VARCHAR 250)
    'estados_documento' => [
        'CARGADO' => 'Cargado por la persona solicitante',
        'SUSTITUIDO' => 'Sustituido por una versión posterior',
        'VALIDADO' => 'Validado por la coordinación',
        'CON_OBSERVACIONES' => 'Con observaciones de la coordinación',
        'NO_VALIDO' => 'No válido',
    ],

    'niveles_profesionales' => ['Licenciatura', 'Especialidad', 'Maestría', 'Doctorado'],

    // ESTUDIO_PROFESIONAL.ESPR_ESTADO (VARCHAR 3): [origen del nombre][categoría del título]
    //   origen: G = categoría general sin título específico, C = catálogo ANUIES, M = captura manual, O = «Otro» (texto libre)
    //   categoría: A Administración, C Contaduría, I Informática, N Negocios Internacionales, O Otro
    'categorias_titulo' => [
        'A' => 'Administración',
        'C' => 'Contaduría',
        'I' => 'Informática',
        'N' => 'Negocios Internacionales',
        'O' => 'Otro',
    ],

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

    // ARTICULO_SUBINDICE (subíndice VARCHAR 10 => descripción VARCHAR 100)
    'articulos_subindice' => [
        '13.1' => 'Artículo 13, inciso 13.1',
        '14' => 'Artículo 14',
        '14.1' => 'Artículo 14, inciso 14.1',
        '14.2' => 'Artículo 14, inciso 14.2',
        '14.3' => 'Artículo 14, inciso 14.3',
    ],

    // TIPO_CONVOCATORIA: siglas (certificación elegida en pantalla) => descripción (VARCHAR 50)
    'certificaciones' => [
        'ANFECA' => 'Certificación académica ANFECA',
        'CACSLA' => 'Certificación académica CACSLA',
        'CACECA' => 'Certificación académica CACECA',
    ],

    // ROL / PRIVILEGIO / ROL_PRIVILEGIO
    'roles' => [
        'solicitante' => ['solicitar_certificacion'],
        'coordinador_nacional' => ['consultar_solicitudes', 'consultar_expedientes', 'revisar_documentos', 'alcance_nacional'],
        'coordinador_zona_1' => ['consultar_solicitudes', 'consultar_expedientes', 'revisar_documentos', 'alcance_zona_1'],
        'coordinador_zona_2' => ['consultar_solicitudes', 'consultar_expedientes', 'revisar_documentos', 'alcance_zona_2'],
        'coordinador_zona_3' => ['consultar_solicitudes', 'consultar_expedientes', 'revisar_documentos', 'alcance_zona_3'],
        'coordinador_zona_4' => ['consultar_solicitudes', 'consultar_expedientes', 'revisar_documentos', 'alcance_zona_4'],
        'coordinador_zona_5' => ['consultar_solicitudes', 'consultar_expedientes', 'revisar_documentos', 'alcance_zona_5'],
        'coordinador_zona_6' => ['consultar_solicitudes', 'consultar_expedientes', 'revisar_documentos', 'alcance_zona_6'],
        'coordinador_zona_7' => ['consultar_solicitudes', 'consultar_expedientes', 'revisar_documentos', 'alcance_zona_7'],
    ],

    'privilegios' => [
        'solicitar_certificacion' => 'Registrar y enviar solicitudes de certificación propias',
        'consultar_solicitudes' => 'Consultar la bandeja de solicitudes dentro de su alcance',
        'consultar_expedientes' => 'Consultar expedientes y documentos dentro de su alcance',
        'revisar_documentos' => 'Registrar la revisión documental dentro de su alcance',
        'alcance_nacional' => 'Alcance de coordinación nacional (todas las zonas)',
        'alcance_zona_1' => 'Alcance de coordinación regional: Zona 1',
        'alcance_zona_2' => 'Alcance de coordinación regional: Zona 2',
        'alcance_zona_3' => 'Alcance de coordinación regional: Zona 3',
        'alcance_zona_4' => 'Alcance de coordinación regional: Zona 4',
        'alcance_zona_5' => 'Alcance de coordinación regional: Zona 5',
        'alcance_zona_6' => 'Alcance de coordinación regional: Zona 6',
        'alcance_zona_7' => 'Alcance de coordinación regional: Zona 7',
    ],

    // Periodicidad del refrendo (años) y número máximo de refrendos del histórico.
    'periodicidad_refrendo' => 3,
    'refrendos_maximos' => 7,
];
