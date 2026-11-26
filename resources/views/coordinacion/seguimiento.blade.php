@extends('coordinacion.layout')

@section('titulo', 'Seguimiento de registros')

@section('contenido')
    <div class="coord-titulo-seccion">
        <h1>Seguimiento de registros</h1>
        <p>Avance de los solicitantes desde la creación de su cuenta hasta el envío (solo consulta administrativa).
           Los expedientes enviados se gestionan en <a href="{{ route('coordinacion.solicitudes') }}">Solicitudes</a>.</p>
    </div>

    <div id="vue-coordinacion-seguimiento"
         data-url-api="{{ route('coordinacion.api.seguimiento') }}"
         data-url-detalle="{{ url('coordinacion/seguimiento') }}"
         data-opciones="{{ json_encode($opciones) }}"
         data-filtros="{{ json_encode((object) $filtrosIniciales) }}">
        <p class="coord-cargando">Cargando registros…</p>
    </div>
@endsection
