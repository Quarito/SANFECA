{{-- Menú lateral de coordinación (config/anfeca_coordinacion.php). Opciones sin ruta: pendientes. --}}
<aside class="coord-menu">
    <div class="coord-menu-titulo">Coordinación</div>

    <nav aria-label="Módulo de coordinación">
        @foreach (config('anfeca_coordinacion.menu') as $opcion)
            @if ($opcion['ruta'])
                @php $activa = request()->routeIs($opcion['ruta'].'*'); @endphp
                <a href="{{ route($opcion['ruta']) }}"
                   class="coord-menu-item {{ $activa ? 'activo' : '' }}"
                   @if ($activa) aria-current="page" @endif>
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
</aside>
