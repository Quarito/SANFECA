@extends('coordinacion.layout')

@section('titulo', 'Solicitudes')

@section('contenido')
    <div class="coord-titulo-seccion">
        <h1>Solicitudes</h1>
        <p>Bandeja de solicitudes de certificación dentro de su alcance (solo consulta).</p>
    </div>

    @if($soloConsultaPdm ?? false)
        <p>La institución y la zona corresponden a la persona. La convocatoria y el estado se leen del expediente.</p>
    @endif

    <div id="vue-coordinacion-bandeja"
         data-url-api="{{ route('coordinacion.api.solicitudes') }}"
         data-url-expediente="{{ url('coordinacion/solicitudes') }}"
         data-opciones="{{ json_encode($opciones) }}">
        <p class="coord-cargando">Cargando solicitudes…</p>
    </div>
@endsection
