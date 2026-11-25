<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('titulo', 'Coordinación') | ANFECA</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Hojas institucionales compartidas con el módulo de solicitantes (mismo orden). --}}
    <link rel="stylesheet" href="{{ asset('css/certificacion.css') }}">
    <link rel="stylesheet" href="{{ asset('css/barra-usuario-certificacion.css') }}">
    <link rel="stylesheet" href="{{ asset('css/tipografia-global-anfeca.css') }}?v=97159">
    <link rel="stylesheet" href="{{ asset('css/anfeca-layout-compacto.css') }}?v=2">
    <link rel="stylesheet" href="{{ asset('css/anfeca-layout-compacto-v3.css') }}?v=3">
    <link rel="stylesheet" href="{{ asset('css/anfeca-avisos.css') }}?v=1">
    <link rel="stylesheet" href="{{ asset('css/anfeca-compacto-v4.css') }}?v=4">

    {{-- Estilos propios del módulo administrativo. --}}
    <link rel="stylesheet" href="{{ asset('css/coordinacion.css') }}?v=8">
    @stack('estilos')
</head>
<body class="coord-body">
<div class="coord-pagina">

    @include('components.anfeca.encabezado-institucional')

    @include('coordinacion.partials.barra-usuario')

    <div class="coord-layout">
        @include('coordinacion.partials.menu')

        <main class="coord-contenido">
            @yield('contenido')
        </main>
    </div>

    @include('components.anfeca.pie-institucional')
</div>
@stack('scripts')
</body>
</html>
