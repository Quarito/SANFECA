@include('sesion.pagina', $motivo === 'formulario' ? [
    'titulo' => 'El tiempo del formulario terminó',
    'parrafos' => [
        'Por seguridad, el formulario se cerró después de un periodo prolongado sin actividad.',
        'La información que no se había guardado no pudo conservarse. Puede iniciar nuevamente su registro.',
    ],
    'acciones' => [['Iniciar sesión', route('login')], ['Registrarse', route('registro.datos')]],
] : [
    'titulo' => 'Su sesión finalizó',
    'parrafos' => [
        'Por seguridad, su sesión se cerró después de un periodo sin actividad.',
        'La información que ya había guardado se conserva. Los datos de la pantalla que no se habían guardado no pudieron conservarse.',
        'Inicie sesión nuevamente para continuar con su trámite.',
    ],
    'acciones' => [['Iniciar sesión', route('login')]],
])
