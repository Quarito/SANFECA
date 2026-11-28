{{-- HTTP 419: token CSRF vencido o sesión terminada. Sin detalles técnicos; el código 419 se conserva. --}}
@include('sesion.pagina', auth()->check() ? [
    'titulo' => 'La página expiró',
    'parrafos' => [
        'La página permaneció abierta demasiado tiempo y la información no se envió.',
        'Su sesión sigue activa. Vuelva a la pantalla, actualícela y capture nuevamente los datos que no se habían guardado.',
    ],
    'acciones' => [['Volver al inicio', url('/')]],
] : [
    'titulo' => 'Su sesión terminó',
    'parrafos' => [
        'Por seguridad, la página expiró y la información no se envió.',
        'Inicie sesión nuevamente para continuar. La información que ya había guardado se conserva.',
    ],
    'acciones' => [['Iniciar sesión', route('login')]],
])
