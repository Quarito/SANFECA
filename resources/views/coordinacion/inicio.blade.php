@extends('coordinacion.layout')

@section('titulo', 'Inicio')

@section('contenido')
    <div class="coord-titulo-seccion">
        <h1>Inicio</h1>
        <p>Indicadores de las solicitudes de certificación dentro de su alcance.</p>
    </div>

    <div id="vue-coordinacion-inicio"
         data-url-indicadores="{{ route('coordinacion.api.indicadores') }}"
         data-url-bandeja="{{ route('coordinacion.solicitudes') }}"
         data-url-expediente="{{ url('coordinacion/solicitudes') }}">
        <p class="coord-cargando">Cargando indicadores…</p>
    </div>
@endsection
