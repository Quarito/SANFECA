{{--
    Barra de usuario de coordinación: mismas clases y estilo que la barra del módulo
    de solicitantes (barra-usuario / barra-usuario-certificacion), con rol y alcance.
--}}
@php
    $usuario = auth()->user();
    $alcance = app(\App\Services\Coordinacion\AlcanceZona::class);
@endphp

<div class="barra-usuario barra-usuario-certificacion coord-barra-usuario">
    <div class="barra-usuario-identidad">
        <span class="icono-usuario" aria-hidden="true">◉</span>
        <span>Usuario: {{ $usuario->name }}</span>
    </div>

    <span class="coord-chip">
        {{ $usuario->esCoordinadorNacional() ? 'Coordinación nacional' : 'Coordinación de zona' }}
    </span>
    <span class="coord-chip" title="Alcance autorizado">{{ $alcance->descripcion($usuario) }}</span>

    <form method="POST" action="{{ route('logout') }}" class="form-cerrar-sesion">
        @csrf
        <button type="submit" class="boton-cerrar-sesion">Cerrar sesión</button>
    </form>
</div>
