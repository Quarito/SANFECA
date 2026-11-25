{{--
    Tarjeta compacta de documento: icono, nombre, estado de CARGA, resultado de REVISIÓN (fase 6.3.1,
    solo con la revisión activa) y acciones «Ver» / «Revisar». El formulario de revisión NO vive aquí:
    se abre en un diálogo modal.
--}}
@php
    $etiquetasEstado = [
        'cargado' => 'Cargado',
        'no-cargado' => 'No cargado',
        'no-localizado' => 'Registrado, archivo no localizado',
    ];
    $rev = $doc['revision'] ?? null;
    $etiquetasRevision = [
        'por_revisar' => 'Por revisar', 'validado' => 'Validado', 'con_observaciones' => 'Con observaciones',
        'no_valido' => 'No válido', 'no_aplica' => 'No aplica',
    ];
    $urlVer = route('coordinacion.solicitudes.documento', [$folio, $doc['campo']])
        .($rev && ($rev['version']['token'] ?? null) ? '?v='.$rev['version']['token'] : '');
@endphp
<article class="coord-documento {{ $doc['estado'] }}{{ $rev ? ' con-revision' : '' }}" data-documento="{{ $doc['campo'] }}">
    <span class="coord-documento-icono" aria-hidden="true">📄</span>
    <div class="coord-documento-texto">
        <div class="coord-documento-nombre">{{ $doc['etiqueta'] }}</div>
        <div class="coord-documento-insignias">
            <span class="coord-insignia {{ $doc['estado'] }}" title="Estado de carga">{{ $etiquetasEstado[$doc['estado']] }}</span>
            @if ($rev)
                <span class="coord-insignia rev rev-{{ $rev['estado'] }}" title="Resultado de revisión" data-resultado="{{ $rev['estado'] }}">{{ $etiquetasRevision[$rev['estado']] }}</span>
            @endif
        </div>
    </div>
    <div class="coord-documento-acciones">
        @if ($doc['estado'] === 'cargado' && ($solicitudEnviada ?? false))
            {{-- El visor modal intercepta el clic; el href se conserva como alternativa sin JavaScript. --}}
            <a href="{{ $urlVer }}" class="coord-boton secundario pequeno" target="_blank" rel="noopener"
               data-ver="{{ $doc['campo'] }}" data-etiqueta="{{ $doc['etiqueta'] }}"
               data-descarga="{{ $urlVer }}{{ str_contains($urlVer, '?') ? '&' : '?' }}descargar=1"
               aria-label="Ver {{ $doc['etiqueta'] }}">Ver</a>
        @else
            <span class="coord-boton secundario pequeno" aria-disabled="true"
                  title="{{ ($solicitudEnviada ?? false) ? 'Sin archivo disponible' : 'Los documentos de solicitudes no enviadas no pueden consultarse' }}">Ver</span>
        @endif
        @if ($rev)
            @if ($rev['aplica'] && ($solicitudEnviada ?? false))
                <button type="button" class="coord-boton pequeno" data-revisar="{{ $doc['campo'] }}" data-etiqueta="{{ $doc['etiqueta'] }}"
                        aria-label="Revisar {{ $doc['etiqueta'] }}">Revisar</button>
            @else
                <span class="coord-boton pequeno" aria-disabled="true"
                      title="{{ $rev['aplica'] ? 'Solo se revisan solicitudes enviadas' : 'No aplica al trámite' }}">Revisar</span>
            @endif
        @endif
    </div>
</article>
