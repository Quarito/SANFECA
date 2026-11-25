<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DatosSolicitanteController;
use App\Http\Controllers\SeleccionCertificacionController;
use App\Http\Controllers\FormacionAcademicaController;
use App\Http\Controllers\DocumentoProbatorioController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\RegistroCompletadoController;
use App\Http\Controllers\Auth\RecuperacionPasswordController;

/*
|--------------------------------------------------------------------------
| RUTA PRINCIPAL
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    if (!auth()->check()) {
        return redirect()->route('login');
    }

    // Perfiles administrativos: módulo de coordinación.
    if (auth()->user()->esCoordinador()) {
        return redirect()->route('coordinacion.inicio');
    }

    $userId = auth()->id();

    $tieneBorrador = \App\Models\SolicitudCertificacion::query()
        ->where('user_id', $userId)
        ->where('estado', 'borrador')
        ->exists();

    if ($tieneBorrador) {
        return redirect()->route('mis-datos.edit');
    }

    $tieneEnviada = \App\Models\SolicitudCertificacion::query()
        ->where('user_id', $userId)
        ->where('estado', 'enviado')
        ->exists();

    if ($tieneEnviada) {
        return redirect()->route('registro.completado');
    }

    return redirect()->route('mis-datos.edit');
});

/*
|--------------------------------------------------------------------------
| AUTENTICACIÓN
|--------------------------------------------------------------------------
| Estas rutas son únicamente para usuarios sin sesión iniciada.
*/

Route::middleware('guest')->group(function () {

    // Mostrar pantalla de inicio de sesión
    Route::get('/login', [
        LoginController::class,
        'show'
    ])->name('login');

    // Procesar inicio de sesión
    Route::post('/login', [
        LoginController::class,
        'login'
    ])
    ->middleware('throttle:login')
    ->name('login.submit');

});

/*
|--------------------------------------------------------------------------
| CERRAR SESIÓN
|--------------------------------------------------------------------------
*/

Route::post('/logout', [
    LoginController::class,
    'logout'
])
->middleware('auth')
->name('logout');

/*
|--------------------------------------------------------------------------
| PANEL PRINCIPAL
|--------------------------------------------------------------------------
*/

Route::get('/panel', [
    PanelController::class,
    'index'
])
->middleware(['auth', 'solo.solicitantes'])
->name('panel');

/*
|--------------------------------------------------------------------------
| AUTORIZACIÓN Y ACTUALIZACIÓN DE DATOS
|--------------------------------------------------------------------------
| Exclusivo para usuarios autenticados.
*/

Route::middleware(['auth', 'solo.solicitantes'])->group(function () {

    Route::get('/mis-datos', [
        DatosSolicitanteController::class,
        'edit'
    ])->name('mis-datos.edit');

    Route::put('/mis-datos', [
        DatosSolicitanteController::class,
        'update'
    ])->name('mis-datos.update');

});

// Autorizar datos personales del solicitante
Route::post('/mis-datos/autorizar', [DatosSolicitanteController::class, 'autorizar'])
    ->middleware(['auth', 'solo.solicitantes'])
    ->name('mis-datos.autorizar');

/*
|--------------------------------------------------------------------------
| REGISTRO INICIAL
|--------------------------------------------------------------------------
*/

// P4: la vista registro.create no existe; se conserva el nombre de la ruta.
Route::redirect('/registro', '/registro/datos-personales')
    ->name('registro.create');

/*
|--------------------------------------------------------------------------
| DATOS PERSONALES
|--------------------------------------------------------------------------
| Se conserva el flujo de alta inicial.
*/

Route::get('/registro/datos-personales', [
    DatosSolicitanteController::class,
    'create'
])->middleware('solo.solicitantes')->name('registro.datos');

Route::post('/registro/datos-personales', [
    DatosSolicitanteController::class,
    'store'
])->middleware('solo.solicitantes')->name('registro.datos.guardar');

/*
|--------------------------------------------------------------------------
| RECUPERACIÓN DE CONTRASEÑA Y GUÍA
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {

    // Pantalla 1: CURP y correo electrónico
    Route::get(
        '/recuperar',
        [RecuperacionPasswordController::class, 'mostrarSolicitud']
    )->name('password.request');

    // Procesar solicitud de recuperación
    Route::post(
        '/recuperar',
        [RecuperacionPasswordController::class, 'enviarEnlace']
    )
    ->middleware('throttle:recuperacion')
    ->name('password.email');

    // Pantalla 2: Confirmación de solicitud
    Route::get(
        '/recuperacion-enviada',
        function () {
            return view('auth.recuperacion-enviada');
        }
    )->name('password.sent');

    // Pantalla 3: Establecer nueva contraseña
    Route::get(
        '/restablecer/{token}',
        [RecuperacionPasswordController::class, 'mostrarNuevaPassword']
    )->name('password.reset');

    // Guardar nueva contraseña
    Route::post(
        '/restablecer',
        [RecuperacionPasswordController::class, 'guardarNuevaPassword']
    )
    ->middleware('throttle:restablecer')
    ->name('password.update');

});

Route::get('/guia', function () {
    return 'Guía paso a paso pendiente.';
})->name('guia');

/*
|--------------------------------------------------------------------------
| REGISTRO DE USUARIOS AUTENTICADOS
|--------------------------------------------------------------------------
| Todas las siguientes pantallas requieren sesión iniciada.
*/

Route::middleware(['auth', 'solo.solicitantes'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | PANTALLA 3 - SELECCIÓN DE CERTIFICACIÓN
    |--------------------------------------------------------------------------
    */

    Route::get('/registro/certificacion', [
        SeleccionCertificacionController::class,
        'create'
    ])
	->middleware('datos.autorizados')
	->name('registro.certificacion');

    Route::post('/registro/certificacion', [
        SeleccionCertificacionController::class,
        'store'
    ])
	->middleware('datos.autorizados')
	->name('registro.certificacion.guardar');
	
	Route::middleware('datos.autorizados')->group(function () {
/*
|--------------------------------------------------------------------------
| CONSULTA DE CERTIFICADO ANTERIOR
|--------------------------------------------------------------------------
*/

Route::post('/registro/certificacion/consultar', [
    SeleccionCertificacionController::class,
    'consultarCertificadoAnterior'
])
->middleware('datos.autorizados')
->name('registro.certificacion.consultar');

    /*
    |--------------------------------------------------------------------------
    | PANTALLA 4 - DATOS DE REFRENDO
    |--------------------------------------------------------------------------
    */

    Route::get('/registro/refrendo', [
        \App\Http\Controllers\DatoRefrendoController::class,
        'create'
    ])->name('registro.refrendo');

    Route::post('/registro/refrendo', [
        \App\Http\Controllers\DatoRefrendoController::class,
        'store'
    ])->name('registro.refrendo.guardar');


    /*
    |--------------------------------------------------------------------------
    | PANTALLA 5 - FORMACIÓN ACADÉMICA
    |--------------------------------------------------------------------------
    */

    Route::get('/registro/formacion-academica', [
        FormacionAcademicaController::class,
        'create'
    ])->name('registro.formacion');

    Route::post('/registro/formacion-academica', [
        FormacionAcademicaController::class,
        'store'
    ])->name('registro.formacion.guardar');

    Route::post('/registro/formacion-academica/autorizar', [
        FormacionAcademicaController::class,
        'autorizar'
    ])->name('registro.formacion.autorizar');



    /*
    |--------------------------------------------------------------------------
    | PANTALLA 5 - DOCUMENTOS PROBATORIOS
    |--------------------------------------------------------------------------
    */

    // Mostrar documentos básicos, probatorios y Link
    Route::get('/registro/documentos-probatorios', [
        DocumentoProbatorioController::class,
        'create'
    ])->name('registro.documentos');

    // Guardar Constancia, Comprobante de Pago y Link
    Route::post('/registro/documentos-probatorios', [
        DocumentoProbatorioController::class,
        'store'
    ])->name('registro.documentos.guardar');

    // Formato oficial de refrendo por artículo 14 (Word, solo trámites elegibles)
    Route::get('/registro/documentos-probatorios/formato-articulo-14', [
        DocumentoProbatorioController::class,
        'formatoArticulo14'
    ])->name('registro.documentos.formato-articulo-14');

    // Descargar documentos del usuario autenticado
    Route::get('/registro/documentos-probatorios/ver/{campo}', [
        DocumentoProbatorioController::class,
        'ver'
    ])->name('registro.documentos.ver');


    /*
    |--------------------------------------------------------------------------
    | REGISTRO COMPLETADO
    |--------------------------------------------------------------------------
    */

    // ACEPTACION Y COMPROMISO
    Route::get(
        '/registro/compromiso',
        [\App\Http\Controllers\AceptacionCompromisoController::class, 'index']
    )->name('registro.compromiso');

    Route::post(
        '/registro/compromiso/guardar',
        [\App\Http\Controllers\AceptacionCompromisoController::class, 'guardar']
    )->name('registro.compromiso.guardar');

    Route::get(
    '/registro/completado',
    [RegistroCompletadoController::class, 'index']
     )->name('registro.completado');

    Route::post('/registro/completado/confirmar', [RegistroCompletadoController::class, 'confirmar'])->name('registro.completado.confirmar');

    // Descarga del acuse de una solicitud enviada.
    Route::get(
        '/registro/acuse/{solicitudId}',
        [\App\Http\Controllers\AcuseRecepcionController::class, 'descargar']
    )->whereNumber('solicitudId')->name('registro.acuse.descargar');
});

}); // Fin de rutas protegidas por autorización de datos
/*
|--------------------------------------------------------------------------
| MÓDULO DE COORDINACIÓN DE CERTIFICACIÓN ACADÉMICA
|--------------------------------------------------------------------------
| Solo cuentas con users.rol de coordinación (middleware 'coordinacion').
| El alcance por zona se resuelve SIEMPRE en el servidor (AlcanceZona).
*/

Route::middleware(['auth', 'coordinacion'])
    ->prefix('coordinacion')
    ->name('coordinacion.')
    ->group(function () {
        Route::get('/', [\App\Http\Controllers\Coordinacion\InicioController::class, 'index'])
            ->name('inicio');
        Route::get('/api/indicadores', [\App\Http\Controllers\Coordinacion\IndicadoresController::class, 'index'])
            ->name('api.indicadores');
        Route::get('/solicitudes', [\App\Http\Controllers\Coordinacion\SolicitudesController::class, 'index'])
            ->name('solicitudes');
        Route::get('/api/solicitudes', [\App\Http\Controllers\Coordinacion\SolicitudesController::class, 'api'])
            ->name('api.solicitudes');

        // Prototipo visual aislado de revisión documental (fase 6.3.0): 404 salvo ANFECA_COORDINACION_PROTOTIPOS=true.
        Route::get('/prototipos/revision-documental', [\App\Http\Controllers\Coordinacion\PrototipoRevisionController::class, 'index'])
            ->name('prototipos.revision-documental');

        // Consulta básica del expediente y documentos (Policy + alcance por zona; 404 fuera de alcance).
        Route::get('/solicitudes/{solicitud}', [\App\Http\Controllers\Coordinacion\ExpedienteController::class, 'show'])
            ->whereNumber('solicitud')->name('solicitudes.ver');
        Route::get('/solicitudes/{solicitud}/documentos/{campo}', [\App\Http\Controllers\Coordinacion\ExpedienteController::class, 'documento'])
            ->whereNumber('solicitud')->where('campo', '[a-z0-9_]+')->name('solicitudes.documento');

        // Revisión documental (fase 6.3.1): 404 salvo ANFECA_COORDINACION_REVISION=true.
        // Solo GET y POST (inserción): no existen rutas de actualización ni eliminación del historial.
        Route::get('/solicitudes/{solicitud}/revision', [\App\Http\Controllers\Coordinacion\RevisionDocumentalController::class, 'resumen'])
            ->whereNumber('solicitud')->name('solicitudes.revision');
        Route::get('/solicitudes/{solicitud}/documentos/{campo}/revisiones', [\App\Http\Controllers\Coordinacion\RevisionDocumentalController::class, 'historial'])
            ->whereNumber('solicitud')->where('campo', '[a-z0-9_]+')->name('solicitudes.revisiones');
        Route::post('/solicitudes/{solicitud}/documentos/{campo}/revisiones', [\App\Http\Controllers\Coordinacion\RevisionDocumentalController::class, 'registrar'])
            ->whereNumber('solicitud')->where('campo', '[a-z0-9_]+')->middleware('throttle:30,1')->name('solicitudes.revisiones.registrar');
    });

/*
|--------------------------------------------------------------------------
| Vistas previas ANFECA - SOLO DESARROLLO
|--------------------------------------------------------------------------
*/

// Vistas previas: solo en APP_ENV=local; nunca en producción.
if (app()->environment('local')) {

    Route::prefix('preview')
        ->name('preview.')
        ->group(function () {

            Route::get('/registro', function () {
                return view('registro.datos-personales', [
                    'datos' => null,
                ]);
            })->name('datos');



            Route::get('/certificacion', function () {
                return view('registro.certificacion', [
                    'seleccion' => null,
                ]);
            })->name('certificacion');

            Route::get('/formacion-academica', function () {
                return view('registro.formacion-academica', [
                    'formacion' => null,
                    'instituciones' => collect(),
                    'solicitud' => null,
                    'formacionBloqueada' => false,
                    'areaCertificacion' => '',
                    'titulosPermitidos' => [],
                ]);
            })->name('formacion');

            Route::get('/documentos', function () {

                $academicos = [
                    'titulo_origen_archivo' => [
                        'nombre' => 'Título de Licenciatura',
                        'disponible' => false,
                        'url' => null,
                    ],
                    'cedula_origen_archivo' => [
                        'nombre' => 'Cédula de Licenciatura',
                        'disponible' => false,
                        'url' => null,
                    ],
                    'titulo_ultimo_grado_archivo' => [
                        'nombre' => 'Título de último grado',
                        'disponible' => false,
                        'url' => null,
                    ],
                    'cedula_ultimo_grado_archivo' => [
                        'nombre' => 'Cédula de último grado',
                        'disponible' => false,
                        'url' => null,
                    ],
                ];

                $nuevos = [
                    'constancia_antiguedad_archivo' => [
                        'nombre' => 'Constancia de Antigüedad',
                        'disponible' => false,
                        'url' => null,
                    ],
                    'comprobante_pago_archivo' => [
                        'nombre' => 'Comprobante de Pago',
                        'disponible' => false,
                        'url' => null,
                    ],
                ];

                return view('registro.documentos-probatorios', [
                    'academicos' => $academicos,
                    'nuevos' => $nuevos,
                    'link' => '',
                ]);

            })->name('documentos');

            Route::get('/refrendo', function () {
                return view('registro.refrendo', [
                    'datosRefrendo' => (object) [
                        'numero_refrendo_asignado' => null,
                        'aplica_articulo_14' => null,
                        'inciso_articulo_14' => null,
                    ],
                    'datosAutomaticos' => [
                        'refrendo_actual' => 1,
                    ],
                ]);
            })->name('refrendo');

            Route::get('/compromiso', function () {
                return view('preview.compromiso', [
                    'aceptado' => false,
                    'aceptadoEn' => null,
                    'soloConsulta' => false,
                ]);
            })->name('compromiso');

            Route::get('/completado', function () {
                // Datos ficticios exclusivos de la vista previa.
                $solicitud = (object) [
                    'certificacion' => 'ANFECA',
                    'tipo_tramite' => 'primera_vez',
                ];

                return view('registro.completado', [
                    'solicitud' => $solicitud,
                    'enviada' => false,
                    'registroCompleto' => false,
                    'requisitos' => [
                        'datos_personales' => true,
                        'certificacion' => true,
                        'refrendo' => true,
                        'formacion_academica' => false,
                        'documentos_probatorios' => false,
                    ],
                ]);
            })->name('completado');
        });
}
