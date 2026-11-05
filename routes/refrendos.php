<?php

use App\Http\Controllers\RefrendoController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'datos.autorizados'])->prefix('refrendos')->name('refrendos.')->group(function () {
    Route::get('/', [RefrendoController::class, 'inicio'])->name('inicio');
    Route::get('/certificacion', [RefrendoController::class, 'certificacion'])->name('certificacion');
    Route::post('/certificacion', [RefrendoController::class, 'guardarCertificacion'])->name('certificacion.guardar');
    Route::get('/detalle', [RefrendoController::class, 'detalle'])->name('detalle');
    Route::post('/detalle', [RefrendoController::class, 'guardarDetalle'])->name('detalle.guardar');
    Route::get('/{id}/formacion', [RefrendoController::class, 'formacion'])->name('formacion');
    Route::post('/{id}/formacion', [RefrendoController::class, 'guardarFormacion'])->name('formacion.guardar');
    Route::get('/{id}/documentos', [RefrendoController::class, 'documentos'])->name('documentos');
    Route::post('/{id}/documentos', [RefrendoController::class, 'guardarDocumentos'])->name('documentos.guardar');
    Route::get('/{id}/documentos/{campo}', [RefrendoController::class, 'ver'])->name('documentos.ver');
    Route::get('/{id}/completado', [RefrendoController::class, 'completado'])->name('completado');
    Route::get('/{id}/comprobante', [RefrendoController::class, 'comprobante'])->name('comprobante');
});
