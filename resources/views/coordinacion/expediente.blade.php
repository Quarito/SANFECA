@extends('coordinacion.layout')

@section('titulo', 'Solicitud '.$detalle->folio)

@php
    $nombre = trim(collect([$detalle->nombres, $detalle->apellido_paterno, $detalle->apellido_materno])->filter()->implode(' '));
    $fecha = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->timezone('America/Mexico_City')->format('d/m/Y H:i') : '—';
    $titulo = fn ($t, $otro) => $t === 'Otro' && filled($otro) ? $otro : ($t ?: '—');
    // Cuadrícula principal: grupos de 6 → 3 columnas con 2 documentos cada una (columna por columna).
    $grupos = collect($principales)->chunk(6)->map(fn ($g) => $g->values()->chunk(2));
@endphp

@section('contenido')
    <div class="coord-titulo-seccion coord-titulo-compacto">
        <div>
            <h1>Solicitud {{ $detalle->folio }}</h1>
            <p>Consulta de la solicitud (solo lectura).</p>
        </div>
        <a href="{{ route('coordinacion.solicitudes') }}" class="coord-boton secundario">← Volver a Solicitudes</a>
    </div>

    <div class="coord-fichas">
        {{-- Ficha izquierda (50 %): solicitante + formación académica en una sola tarjeta --}}
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
                <div><dt>Zona ANFECA</dt><dd data-zona-origen="{{ $zona->origen }}">
                    @if ($zona->clasificada())
                        {{ $zona->etiqueta }}
                        @if ($zona->origen === \App\Services\Zonas\ResolucionZona::DATO_INSTITUCIONAL)
                            <small class="coord-nota-dato" title="Zona tomada del dato institucional cargado (zona_anfeca); falta la relación normalizada zona_id">· dato institucional</small>
                        @endif
                    @else
                        <span class="coord-insignia sin-zona">Sin clasificar</span>
                    @endif
                    @foreach ($zona->advertencias as $advertencia)
                        @unless ($advertencia === 'sin institución identificada')
                            <small class="coord-advertencia-zona" role="note">⚠ {{ $advertencia }}</small>
                        @endunless
                    @endforeach
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

        {{-- Ficha derecha (50 %): datos de la solicitud --}}
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
                    <div><dt>Zona del certificado histórico</dt><dd>{{ $zonaHistorica ?? 'No registrada' }}</dd></div>
                    <div><dt>Artículo 13/14</dt><dd>{{ $refrendo?->aplica_articulo_14 ? 'Sí, inciso '.$refrendo->inciso_articulo_14 : 'No' }}</dd></div>
                @endif
            </dl>
        </section>
    </div>

    <section class="coord-panel coord-panel-documentos" aria-labelledby="titulo-documentos">
        <div class="coord-documentos-encabezado">
            <h2 id="titulo-documentos">Documentos</h2>
            @if ($revision)
                <p class="coord-documentos-leyenda">Estado de carga y resultado de revisión. La revisión no califica ni dictamina la solicitud.</p>
            @else
                {{-- Una sola leyenda para toda la sección: el estado mostrado es de CARGA, no de revisión. --}}
                <p class="coord-documentos-leyenda">Estado de carga. Revisión documental: pendiente (fase 6.3).</p>
            @endif
        </div>

        @if ($revision)
            @php $ind = $revision['indicadores']; @endphp
            {{-- Indicadores en una sola línea compacta. Excluyen los no aplicables de los denominadores. --}}
            <dl class="coord-indicadores-revision" aria-label="Avance de revisión documental">
                <div><dt>Aplicables</dt><dd data-indicador="aplicables">{{ $ind['aplicables'] }}</dd></div>
                <div><dt>Cargados</dt><dd data-indicador="cargados">{{ $ind['cargados'] }}</dd></div>
                <div><dt>Faltantes</dt><dd data-indicador="faltantes">{{ $ind['faltantes'] }}</dd></div>
                <div><dt>Por revisar</dt><dd data-indicador="por_revisar">{{ $ind['por_revisar'] }}</dd></div>
                <div><dt>Validados</dt><dd data-indicador="validados">{{ $ind['validados'] }}</dd></div>
                <div><dt>Con observaciones</dt><dd data-indicador="con_observaciones">{{ $ind['con_observaciones'] }}</dd></div>
                <div><dt>No válidos</dt><dd data-indicador="no_validos">{{ $ind['no_validos'] }}</dd></div>
                <div class="separado"><dt>Revisado</dt><dd data-indicador="porcentaje_revisado">{{ $ind['porcentaje_revisado'] }} %</dd></div>
                <div><dt>Validado</dt><dd data-indicador="porcentaje_validado">{{ $ind['porcentaje_validado'] }} %</dd></div>
            </dl>
            @unless ($solicitudEnviada)
                <p class="coord-documentos-leyenda">La solicitud no ha sido enviada: la revisión documental no está disponible.</p>
            @endunless
        @endif

        @foreach ($grupos as $grupo)
            <div class="coord-documentos-grupo">
                @foreach ($grupo as $columna)
                    <div class="coord-documentos-columna">
                        @foreach ($columna as $doc)
                            @include('coordinacion.partials.documento', ['doc' => $doc, 'folio' => $detalle->folio, 'solicitudEnviada' => $solicitudEnviada ?? false])
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endforeach

        <h3 class="coord-documentos-subtitulo">Documentos adicionales — Artículo 13/14</h3>
        @if ($adicionales !== [])
            {{-- Distribución horizontal adaptable a la cantidad real (máximo 3 por fila). --}}
            <div class="coord-documentos-adicionales" style="--columnas: {{ min(count($adicionales), 3) }}">
                @foreach ($adicionales as $doc)
                    @include('coordinacion.partials.documento', ['doc' => $doc, 'folio' => $detalle->folio, 'solicitudEnviada' => $solicitudEnviada ?? false])
                @endforeach
            </div>
        @endif
        @if ($noAplican !== [])
            <p class="coord-documentos-no-aplican">
                <span class="coord-insignia no-aplica">No aplica</span>
                {{ implode(' · ', $noAplican) }} (solo refrendos por artículo 13/14).
            </p>
        @endif
    </section>

    {{-- Visor documental modal (siempre) y panel de revisión (solo con la revisión documental activa). --}}
    <div id="vue-expediente-documentos" data-folio="{{ $detalle->folio }}"
         data-url-base="{{ url('/coordinacion/solicitudes/'.$detalle->folio.'/documentos') }}"
         data-revision-activa="{{ $revision ? '1' : '0' }}"></div>
@endsection
