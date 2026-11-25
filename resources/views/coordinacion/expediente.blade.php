@extends('coordinacion.layout')

@section('titulo', 'Solicitud '.$detalle->folio)

@php
    $nombre = trim(collect([$detalle->nombres, $detalle->apellido_paterno, $detalle->apellido_materno])->filter()->implode(' '));
    $fecha = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->timezone('America/Mexico_City')->format('d/m/Y H:i') : '—';
    $titulo = fn ($t, $otro) => $t === 'Otro' && filled($otro) ? $otro : ($t ?: '—');
    $estadoDocumento = [
        'cargado' => 'Cargado',
        'no-cargado' => 'No cargado',
        'no-localizado' => 'Registrado, archivo no localizado',
    ];
    // Cuadrícula: grupos de 6 documentos → 3 columnas con 2 documentos cada una (columna por columna).
    $grupos = collect($documentos)->chunk(6)->map(fn ($g) => $g->values()->chunk(2));
@endphp

@section('contenido')
    <div class="coord-titulo-seccion">
        <h1>Solicitud {{ $detalle->folio }}</h1>
        <p>Consulta de la solicitud (solo lectura). La revisión documental corresponde a la fase 6.3.</p>
    </div>

    <p style="margin: 0 0 14px;">
        <a href="{{ route('coordinacion.solicitudes') }}" class="coord-boton secundario">← Volver a Solicitudes</a>
    </p>

    <div class="coord-fichas">
        {{-- Ficha izquierda (≈60 %): solicitante + formación académica en una sola tarjeta --}}
        <section class="coord-panel coord-ficha coord-ficha-solicitante" aria-labelledby="ficha-solicitante">
            <h2 id="ficha-solicitante">Ficha del solicitante</h2>

            <h3>Datos del solicitante</h3>
            <dl class="coord-datos">
                <div class="ancho-completo"><dt>Nombre completo</dt><dd>{{ $nombre !== '' ? $nombre : '—' }}</dd></div>
                <div><dt>CURP</dt><dd>{{ $detalle->curp ?? '—' }}</dd></div>
                <div><dt>Correo electrónico</dt><dd>{{ $detalle->correo_electronico ?? '—' }}</dd></div>
                <div class="ancho-completo"><dt>Institución</dt><dd>{{ $detalle->institucion ?? '—' }}</dd></div>
                <div><dt>Escuela o Facultad</dt><dd>{{ $detalle->facultad ?? '—' }}</dd></div>
                <div><dt>Campus</dt><dd>{{ $detalle->campus ?? '—' }}</dd></div>
                <div><dt>Zona ANFECA</dt><dd>
                    @if ($detalle->zona_siglas)
                        {{ $detalle->zona_siglas }}
                    @else
                        <span class="coord-insignia sin-zona">Pendiente de clasificación</span>
                    @endif
                </dd></div>
            </dl>

            <hr class="coord-divisor">

            <h3>Formación académica</h3>
            <dl class="coord-datos">
                <div><dt>Nivel de origen</dt><dd>{{ $detalle->nivel_origen ?? '—' }}</dd></div>
                <div><dt>Título de origen</dt><dd>{{ $titulo($detalle->titulo_origen, $detalle->titulo_origen_otro) }}</dd></div>
                <div><dt>Último grado</dt><dd>{{ $detalle->nivel_ultimo_grado ?? '—' }}</dd></div>
                <div><dt>Título del último grado</dt><dd>{{ $titulo($detalle->titulo_ultimo_grado, $detalle->titulo_ultimo_grado_otro) }}</dd></div>
                <div><dt>Autorizada por el solicitante</dt><dd>{{ $detalle->formacion_completada_at ? $fecha($detalle->formacion_completada_at) : 'No' }}</dd></div>
            </dl>
        </section>

        {{-- Ficha derecha (≈40 %): datos de la solicitud --}}
        <section class="coord-panel coord-ficha coord-ficha-solicitud" aria-labelledby="ficha-solicitud">
            <h2 id="ficha-solicitud">Datos de solicitud</h2>
            <dl class="coord-datos">
                <div><dt>Folio de solicitud</dt><dd>{{ $detalle->folio }}</dd></div>
                <div><dt>Estado</dt><dd>
                    <span class="coord-insignia {{ $detalle->estado === 'enviado' ? 'enviado' : 'borrador' }}">
                        {{ $detalle->estado === 'enviado' ? 'Enviada' : 'Iniciada (borrador)' }}
                    </span></dd></div>
                <div><dt>Tipo de trámite</dt><dd>{{ $detalle->tipo_tramite === 'refrendo' ? 'Refrendo' : 'Primera vez' }}</dd></div>
                <div><dt>Certificación</dt><dd>{{ $detalle->certificacion ?? '—' }}</dd></div>
                <div class="ancho-completo"><dt>Tipo de personal</dt><dd>{{ $detalle->tipo_personal ?? '—' }}</dd></div>
                <div class="ancho-completo"><dt>Área de conocimiento</dt><dd>{{ $detalle->area_certificacion ?? '—' }}</dd></div>
                <div><dt>Fecha de envío</dt><dd>{{ $fecha($detalle->fecha_envio) }}</dd></div>
                <div><dt>Compromiso de entrega</dt><dd>{{ $detalle->compromiso_aceptado ? 'Aceptado '.$fecha($detalle->compromiso_aceptado_en) : 'Pendiente' }}</dd></div>
                @if ($detalle->tipo_tramite === 'refrendo')
                    <div><dt>Certificado histórico</dt><dd>{{ $detalle->certificado_historico ?? '—' }}</dd></div>
                    <div><dt>Artículo 13/14</dt><dd>{{ $refrendo?->aplica_articulo_14 ? 'Sí, inciso '.$refrendo->inciso_articulo_14 : 'No' }}</dd></div>
                @endif
            </dl>
        </section>
    </div>

    <section class="coord-panel" aria-labelledby="titulo-documentos">
        <h2 id="titulo-documentos">Documentos</h2>

        @foreach ($grupos as $grupo)
            <div class="coord-documentos-grupo">
                @foreach ($grupo as $columna)
                    <div class="coord-documentos-columna">
                        @foreach ($columna as $doc)
                            <article class="coord-documento {{ $doc['estado'] }}" data-documento="{{ $doc['campo'] }}">
                                <span class="coord-documento-icono" aria-hidden="true">📄</span>
                                <div>
                                    <div class="coord-documento-nombre">{{ $doc['etiqueta'] }}</div>
                                    <div class="coord-documento-estado">
                                        <span class="coord-insignia {{ $doc['estado'] }}">{{ $estadoDocumento[$doc['estado']] }}</span>
                                    </div>
                                </div>
                                @if ($doc['estado'] === 'cargado')
                                    <a href="{{ route('coordinacion.solicitudes.documento', [$detalle->folio, $doc['campo']]) }}"
                                       class="coord-boton secundario pequeno" target="_blank" rel="noopener"
                                       aria-label="Ver {{ $doc['etiqueta'] }}">Ver</a>
                                @else
                                    <span class="coord-boton secundario pequeno" aria-disabled="true">Ver</span>
                                @endif
                                {{-- Espacio reservado para la revisión documental (fase 6.3): sin acciones todavía. --}}
                                <div class="coord-documento-revision">Revisión documental: pendiente (fase 6.3)</div>
                            </article>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endforeach

        @if ($noAplican !== [])
            <p class="coord-documentos-no-aplican">
                <span class="coord-insignia no-aplica">No aplica</span>
                {{ implode(' · ', $noAplican) }} (solo refrendos por artículo 13/14).
            </p>
        @endif
    </section>
@endsection
