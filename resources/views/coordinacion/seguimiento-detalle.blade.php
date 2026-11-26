@extends('coordinacion.layout')

@section('titulo', 'Seguimiento de registro')

@php
    use App\Services\Coordinacion\SeguimientoRegistros as SR;
    $fecha = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->timezone('America/Mexico_City')->format('d/m/Y H:i') : '—';
    $modalidad = fn ($t) => $t === null ? '—' : ($t === 'refrendo' ? 'Refrendo' : 'Primera vez');
    $estadoEtapa = [SR::COMPLETADA => 'Completada', SR::EN_PROCESO => 'En proceso', SR::PENDIENTE => 'Pendiente', SR::NO_APLICA => 'No aplica'];
@endphp

@section('contenido')
    <div class="coord-titulo-seccion coord-titulo-compacto">
        <div>
            <h1>{{ $r['solicitante'] }}</h1>
            <p>Seguimiento del registro (solo lectura). La coordinación no puede modificar, autorizar ni enviar el trámite.</p>
        </div>
        <a href="{{ route('coordinacion.seguimiento') }}" class="coord-boton secundario">← Volver a Seguimiento</a>
    </div>

    <div class="coord-fichas">
        <section class="coord-panel coord-ficha" aria-labelledby="ficha-persona">
            <h2 id="ficha-persona">Solicitante y trámite</h2>
            <dl class="coord-datos">
                <div class="ancho-completo"><dt>Nombre</dt><dd>{{ $r['solicitante'] }}</dd></div>
                <div class="ancho-completo"><dt>Correo electrónico</dt><dd>{{ $r['correo'] }}</dd></div>
                <div class="ancho-completo"><dt>Institución</dt><dd>{{ $r['institucion'] ?? 'Sin institución identificada' }}</dd></div>
                <div><dt>Zona</dt><dd>@if ($r['zona_clasificada']) {{ $r['zona'] }} @else <span class="coord-insignia sin-zona">Sin clasificar</span> @endif</dd></div>
                <div><dt>Folio</dt><dd>{{ $r['folio'] ?? 'Sin folio' }}</dd></div>
                <div><dt>Modalidad</dt><dd>{{ $modalidad($r['tipo_tramite']) }}</dd></div>
                <div><dt>Certificación</dt><dd>{{ $r['certificacion'] ?? '—' }}</dd></div>
            </dl>
        </section>
        <section class="coord-panel coord-ficha" aria-labelledby="ficha-avance">
            <h2 id="ficha-avance">Avance</h2>
            <dl class="coord-datos">
                <div class="ancho-completo"><dt>Estado</dt><dd><span class="coord-insignia seg-{{ $r['clasificacion'] }}">{{ $r['clasificacion_etiqueta'] }}</span></dd></div>
                <div><dt>Fecha de registro</dt><dd>{{ $fecha($r['fecha_registro']) }}</dd></div>
                <div><dt>Último avance registrado</dt><dd>{{ $fecha($r['ultimo_avance']) }}</dd></div>
                <div class="ancho-completo"><dt>Etapa actual</dt><dd>{{ $r['etapa_actual_etiqueta'] }}</dd></div>
                <div><dt>Avance de captura</dt><dd>{{ $r['avance'] }} %</dd></div>
                <div><dt>Etapas completadas</dt><dd>{{ $r['completadas'] }} de {{ $r['total'] }}</dd></div>
            </dl>
            <p class="coord-nota-dato">El avance de captura no incluye el envío: 100 % significa «listo para envío», no «enviada».</p>
        </section>
    </div>

    <section class="coord-panel" aria-labelledby="titulo-progreso">
        <h2 id="titulo-progreso">Línea de progreso</h2>
        <ol class="seg-progreso">
            @foreach (SR::ETAPAS as $clave => $etiqueta)
                @php $e = $r['etapas'][$clave]; @endphp
                <li class="seg-paso seg-{{ $e }} {{ $r['etapa_actual'] === $clave ? 'actual' : '' }}" data-etapa="{{ $clave }}" data-estado="{{ $e }}">
                    <span class="seg-marca" aria-hidden="true">{{ $e === SR::COMPLETADA ? '✓' : ($e === SR::NO_APLICA ? '–' : $loop->iteration) }}</span>
                    <span class="seg-texto">{{ $etiqueta }}<small>{{ $estadoEtapa[$e] }}{{ $r['etapa_actual'] === $clave ? ' · etapa actual' : '' }}</small></span>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="coord-panel" aria-labelledby="titulo-historial">
        <h2 id="titulo-historial">Otros trámites del solicitante</h2>
        @if ($r['historial'] === [])
            <p class="coord-cargando">Sin otros trámites registrados.</p>
        @else
            <div class="coord-tabla-contenedor">
                <table class="coord-tabla">
                    <thead><tr><th scope="col" class="col-num" title="Consecutivo visual; no es el folio">Núm.</th><th scope="col">Folio</th><th scope="col">Modalidad</th>
                        <th scope="col">Certificación</th><th scope="col">Certificado asociado</th><th scope="col">Fecha</th><th scope="col">Estado</th><th scope="col">Acciones</th></tr></thead>
                    <tbody>
                        @foreach ($r['historial'] as $h)
                            <tr>
                                <td class="col-num">{{ $loop->iteration }}</td>
                                <td>{{ $h['folio'] }}</td>
                                <td>{{ $modalidad($h['tipo_tramite']) }}</td>
                                <td>{{ $h['certificacion'] ?? '—' }}</td>
                                <td>{{ $h['certificado'] ?? '—' }}</td>
                                <td>{{ $fecha($h['fecha']) }}</td>
                                <td><span class="coord-insignia {{ $h['estado'] === 'enviado' ? 'enviado' : 'borrador' }}">{{ $h['estado'] === 'enviado' ? 'Enviada' : 'Iniciada' }}</span></td>
                                <td>@if ($h['estado'] === 'enviado')<a href="{{ route('coordinacion.solicitudes.ver', $h['folio']) }}" class="coord-boton secundario pequeno">Consultar</a>@else — @endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
