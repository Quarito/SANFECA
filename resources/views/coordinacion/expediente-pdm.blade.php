@extends('coordinacion.layout')

@section('titulo', 'Solicitud '.$solicitud->soli_folio_solicitud)

@section('contenido')
    <div class="coord-titulo-seccion">
        <h1>Solicitud {{ $solicitud->soli_folio_solicitud }}</h1>
        <a href="{{ route('coordinacion.solicitudes') }}" class="coord-boton secundario">Volver a Solicitudes</a>
    </div>
    <section class="coord-ficha">
        <h2>Expediente</h2>
        <dl>
            <dt>Nombre</dt>
            <dd>{{ implode(' ', array_filter([$solicitud->persona->pers_nombre, $solicitud->persona->pers_apellido_paterno, $solicitud->persona->pers_apellido_materno])) }}</dd>
            <dt>Institución registrada para la persona</dt>
            <dd>{{ $solicitud->persona->institucion?->inst_nombre ?? '—' }}</dd>
            <dt>Convocatoria</dt>
            <dd>{{ $solicitud->convocatoria?->conv_nombre ?? '—' }}</dd>
            <dt>Tipo de convocatoria</dt>
            <dd>{{ $solicitud->convocatoria?->tipoConvocatoria?->tico_tipo_convocatoria ?? '—' }}</dd>
            <dt>Tipo de personal</dt>
            <dd>{{ $solicitud->tipoPersonalAcademico->tipa_descripcion }}</dd>
            <dt>Área</dt>
            <dd>{{ $solicitud->areaCertificacion->area_descripcion }}</dd>
            <dt>Último estado registrado</dt>
            <dd>{{ $solicitud->ultimoEstado?->esso_descripcion ?? 'Sin estado' }} {{ $fechaEstado }}</dd>
        </dl>
    </section>
    <section class="coord-ficha">
        <h2>Documentos registrados</h2>
        <ul>
            @forelse ($solicitud->solicitudDocumentos as $documento)
                <li>{{ $documento->documento->docu_descripcion }}</li>
            @empty
                <li>Sin documentos registrados.</li>
            @endforelse
        </ul>
        @if ($pagoDisponible)
            <a class="coord-boton" href="{{ route('coordinacion.solicitudes.documento', ['solicitud' => $solicitud->getKey(), 'campo' => 'comprobante_pago_archivo']) }}" target="_blank" rel="noopener">Ver comprobante de pago</a>
        @endif
    </section>
@endsection
