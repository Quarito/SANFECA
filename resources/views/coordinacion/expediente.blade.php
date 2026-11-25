@extends('coordinacion.layout')

@section('titulo', 'Solicitud '.$detalle->folio)

@php
    $nombre = trim(collect([$detalle->nombres, $detalle->apellido_paterno, $detalle->apellido_materno])->filter()->implode(' '));
    $fecha = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->timezone('America/Mexico_City')->format('d/m/Y H:i') : '—';
    $titulo = fn ($t, $otro) => $t === 'Otro' && filled($otro) ? $otro : ($t ?: '—');
@endphp

@section('contenido')
    <div class="coord-titulo-seccion">
        <h1>Solicitud {{ $detalle->folio }}</h1>
        <p>Consulta básica del expediente (solo lectura). La revisión documental completa corresponde a la fase 6.3.</p>
    </div>

    <p style="margin: 0 0 14px;">
        <a href="{{ route('coordinacion.solicitudes') }}" class="coord-boton secundario">← Volver a Solicitudes</a>
    </p>

    <div class="coord-paneles">
        <section class="coord-panel">
            <h2>Solicitud</h2>
            <dl class="coord-datos">
                <div><dt>Folio de solicitud</dt><dd>{{ $detalle->folio }}</dd></div>
                <div><dt>Estado</dt><dd>
                    <span class="coord-insignia {{ $detalle->estado === 'enviado' ? 'enviado' : 'borrador' }}">
                        {{ $detalle->estado === 'enviado' ? 'Enviada' : 'Iniciada (borrador)' }}
                    </span></dd></div>
                <div><dt>Trámite</dt><dd>{{ $detalle->tipo_tramite === 'refrendo' ? 'Refrendo' : 'Primera vez' }}</dd></div>
                <div><dt>Certificación</dt><dd>{{ $detalle->certificacion ?? '—' }}</dd></div>
                <div><dt>Tipo de personal</dt><dd>{{ $detalle->tipo_personal ?? '—' }}</dd></div>
                <div><dt>Área de conocimiento</dt><dd>{{ $detalle->area_certificacion ?? '—' }}</dd></div>
                <div><dt>Fecha de envío</dt><dd>{{ $fecha($detalle->fecha_envio) }}</dd></div>
                <div><dt>Compromiso de entrega</dt><dd>{{ $detalle->compromiso_aceptado ? 'Aceptado '.$fecha($detalle->compromiso_aceptado_en) : 'Pendiente' }}</dd></div>
                @if ($detalle->tipo_tramite === 'refrendo')
                    <div><dt>Certificado histórico</dt><dd>{{ $detalle->certificado_historico ?? '—' }}</dd></div>
                    <div><dt>Artículo 13/14</dt><dd>{{ $refrendo?->aplica_articulo_14 ? 'Sí, inciso '.$refrendo->inciso_articulo_14 : 'No' }}</dd></div>
                @endif
            </dl>
        </section>

        <section class="coord-panel">
            <h2>Solicitante</h2>
            <dl class="coord-datos">
                <div><dt>Nombre</dt><dd>{{ $nombre !== '' ? $nombre : '—' }}</dd></div>
                <div><dt>CURP</dt><dd>{{ $detalle->curp ?? '—' }}</dd></div>
                <div><dt>Correo</dt><dd>{{ $detalle->correo_electronico ?? '—' }}</dd></div>
            </dl>
            <h2 style="margin-top: 12px;">Institución</h2>
            <dl class="coord-datos">
                <div><dt>Institución</dt><dd>{{ $detalle->institucion ?? '—' }}</dd></div>
                <div><dt>Escuela o Facultad</dt><dd>{{ $detalle->facultad ?? '—' }}</dd></div>
                <div><dt>Campus</dt><dd>{{ $detalle->campus ?? '—' }}</dd></div>
                <div><dt>Zona</dt><dd>
                    @if ($detalle->zona_siglas)
                        {{ $detalle->zona_siglas }}
                    @else
                        <span class="coord-insignia sin-zona">Pendiente de clasificación</span>
                    @endif
                </dd></div>
            </dl>
        </section>

        <section class="coord-panel">
            <h2>Formación académica</h2>
            <dl class="coord-datos">
                <div><dt>Nivel de origen</dt><dd>{{ $detalle->nivel_origen ?? '—' }}</dd></div>
                <div><dt>Título de origen</dt><dd>{{ $titulo($detalle->titulo_origen, $detalle->titulo_origen_otro) }}</dd></div>
                <div><dt>Último grado</dt><dd>{{ $detalle->nivel_ultimo_grado ?? '—' }}</dd></div>
                <div><dt>Título del último grado</dt><dd>{{ $titulo($detalle->titulo_ultimo_grado, $detalle->titulo_ultimo_grado_otro) }}</dd></div>
                <div><dt>Autorizada</dt><dd>{{ $detalle->formacion_completada_at ? $fecha($detalle->formacion_completada_at) : 'No' }}</dd></div>
            </dl>
        </section>
    </div>

    <section class="coord-panel">
        <h2>Documentos</h2>
        <div class="coord-tabla-contenedor">
            <table class="coord-tabla">
                <thead><tr><th scope="col">Documento</th><th scope="col">Estado</th><th scope="col">Consulta</th></tr></thead>
                <tbody>
                @foreach ($documentos as $doc)
                    <tr>
                        <td>{{ $doc['etiqueta'] }}</td>
                        <td>
                            <span class="coord-insignia {{ $doc['disponible'] ? 'enviado' : 'neutra' }}">
                                {{ $doc['disponible'] ? 'Cargado' : 'No disponible' }}
                            </span>
                        </td>
                        <td>
                            @if ($doc['disponible'])
                                <a href="{{ route('coordinacion.solicitudes.documento', [$detalle->folio, $doc['campo']]) }}"
                                   target="_blank" rel="noopener">Ver</a>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
