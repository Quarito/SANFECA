{{--
    Tarjeta compacta de documento (solo lectura): icono, nombre, estado de CARGA y «Ver».
    Preparada para incorporar en la fase 6.3 el resultado de revisión y sus acciones.
--}}
@php
    $etiquetasEstado = [
        'cargado' => 'Cargado',
        'no-cargado' => 'No cargado',
        'no-localizado' => 'Registrado, archivo no localizado',
    ];
@endphp
<article class="coord-documento {{ $doc['estado'] }}" data-documento="{{ $doc['campo'] }}">
    <span class="coord-documento-icono" aria-hidden="true">📄</span>
    <div class="coord-documento-texto">
        <div class="coord-documento-nombre">{{ $doc['etiqueta'] }}</div>
        <span class="coord-insignia {{ $doc['estado'] }}">{{ $etiquetasEstado[$doc['estado']] }}</span>
    </div>
    @if ($doc['estado'] === 'cargado')
        <a href="{{ route('coordinacion.solicitudes.documento', [$folio, $doc['campo']]) }}"
           class="coord-boton secundario pequeno" target="_blank" rel="noopener"
           aria-label="Ver {{ $doc['etiqueta'] }}">Ver</a>
    @else
        <span class="coord-boton secundario pequeno" aria-disabled="true">Ver</span>
    @endif
</article>
