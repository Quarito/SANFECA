<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('titulo', 'Coordinación') | ANFECA</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link rel="stylesheet" href="{{ asset('css/tipografia-global-anfeca.css') }}?v=97159">
    <link rel="stylesheet" href="{{ asset('css/anfeca-avisos.css') }}?v=1">
    <link rel="stylesheet" href="{{ asset('css/coordinacion.css') }}?v=3">
</head>
<body class="coord-body">
<div class="coord-pagina">

    <header class="coord-encabezado">
        <img src="{{ asset('images/logo-certificacion.png') }}" alt="Certificación ANFECA" class="coord-logo">
        <div class="coord-encabezado-titulo">
            <span class="coord-encabezado-sup">Sistema de Certificación de la ANFECA</span>
            <strong>Coordinación de Certificación Académica</strong>
        </div>
    </header>

    @php
        $usuario = auth()->user();
        $alcance = app(\App\Services\Coordinacion\AlcanceZona::class);
    @endphp

    <div class="coord-barra-usuario">
        <div class="coord-identidad">
            <span aria-hidden="true">◉</span>
            <span>{{ $usuario->name }}</span>
            <span class="coord-perfil">
                {{ $usuario->esCoordinadorNacional() ? 'Coordinación nacional' : 'Coordinación de zona' }}
            </span>
            <span class="coord-zonas">{{ $alcance->descripcion($usuario) }}</span>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="coord-boton-salir">Cerrar sesión</button>
        </form>
    </div>

    <div class="coord-layout">
        <nav class="coord-menu" aria-label="Módulo de coordinación">
            @foreach (config('anfeca_coordinacion.menu') as $opcion)
                @if ($opcion['ruta'])
                    <a href="{{ route($opcion['ruta']) }}"
                       class="coord-menu-item {{ request()->routeIs($opcion['ruta'].'*') ? 'activo' : '' }}"
                       @if (request()->routeIs($opcion['ruta'].'*')) aria-current="page" @endif>
                        <span class="coord-menu-icono" aria-hidden="true">{{ $opcion['icono'] }}</span>
                        <span>{{ $opcion['etiqueta'] }}</span>
                    </a>
                @else
                    <span class="coord-menu-item pendiente" aria-disabled="true"
                          title="Pendiente de implementación (fase {{ $opcion['fase'] }}){{ isset($opcion['nota']) ? '. '.$opcion['nota'] : '' }}">
                        <span class="coord-menu-icono" aria-hidden="true">{{ $opcion['icono'] }}</span>
                        <span>{{ $opcion['etiqueta'] }}</span>
                        <span class="coord-etiqueta-pendiente">Pendiente</span>
                    </span>
                @endif
            @endforeach
        </nav>

        <main class="coord-contenido">
            @yield('contenido')
        </main>
    </div>

    <footer class="coord-pie">
        Coordinación Nacional de Certificación Académica · ANFECA · D.R. © {{ date('Y') }}
    </footer>
</div>
</body>
</html>
