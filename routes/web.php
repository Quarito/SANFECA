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
    return redirect()->route('login');
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
    ->middleware('throttle:5,1')
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
->middleware('auth')
->name('panel');

/*
|--------------------------------------------------------------------------
| AUTORIZACIÓN Y ACTUALIZACIÓN DE DATOS
|--------------------------------------------------------------------------
| Exclusivo para usuarios autenticados.
*/

Route::middleware('auth')->group(function () {

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
    ->middleware('auth')
    ->name('mis-datos.autorizar');

/*
|--------------------------------------------------------------------------
| REGISTRO INICIAL
|--------------------------------------------------------------------------
*/

Route::view('/registro', 'registro.create')
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
])->name('registro.datos');

Route::middleware('guest')->group(function () {

    Route::post('/registro/datos-personales', [
        DatosSolicitanteController::class,
        'store'
    ])->name('registro.datos.guardar');

});

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
    ->middleware('throttle:3,1')
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
    ->middleware('throttle:5,1')
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

Route::middleware('auth')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | PANTALLA 3 - SELECCIÓN DE CERTIFICACIÓN
    |--------------------------------------------------------------------------
    */

    Route::get('/registro/certificacion', [
        SeleccionCertificacionController::class,
        'create'
    ])->name('registro.certificacion');

    Route::post('/registro/certificacion', [
        SeleccionCertificacionController::class,
        'store'
    ])->name('registro.certificacion.guardar');


    /*
    |--------------------------------------------------------------------------
    | PANTALLA 4 - FORMACIÓN ACADÉMICA
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

    Route::get(
    '/registro/completado',
    [RegistroCompletadoController::class, 'index']
     )->name('registro.completado');
});

/*
|--------------------------------------------------------------------------
| Vistas previas ANFECA - SOLO DESARROLLO
|--------------------------------------------------------------------------
*/

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

            Route::get('/completado', function () {
                return view('registro.completado', [
                    'registroCompleto' => false,
                ]);
            })->name('completado');
        });
}
