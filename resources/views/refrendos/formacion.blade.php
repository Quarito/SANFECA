@extends('refrendos.layout')
@section('contenido')
<form class="formulario" method="POST" action="{{ route('refrendos.formacion.guardar', $solicitud->id) }}">@csrf
<div class="columnas">
@foreach(['origen'=>'Formación Académica de Origen','ultimo_grado'=>'Último Grado de Estudios'] as $sufijo=>$titulo)
<section><h2>{{ $titulo }}</h2><label for="nivel_{{ $sufijo }}">Nivel</label><select id="nivel_{{ $sufijo }}" name="nivel_{{ $sufijo }}" required><option value="">Seleccione una opción</option>@foreach(['Licenciatura','Maestría','Doctorado','Especialidad'] as $nivel)<option @selected(old('nivel_'.$sufijo, $formacion['nivel_'.$sufijo] ?? '') === $nivel)>{{ $nivel }}</option>@endforeach</select>
<label for="titulo_{{ $sufijo }}">Nombre del Título o Grado</label><input id="titulo_{{ $sufijo }}" name="titulo_{{ $sufijo }}" value="{{ old('titulo_'.$sufijo, $formacion['titulo_'.$sufijo] ?? '') }}" maxlength="200" required></section>
@endforeach
</div><div class="institucion"><h2>Institución de Procedencia</h2><label for="institucion">Número de Afiliación — Institución</label><select id="institucion" name="institucion_afiliada_id" required><option value="">Seleccione una institución</option>@foreach($instituciones as $institucion)<option value="{{ $institucion->id }}" @selected((string)old('institucion_afiliada_id', $formacion['institucion_afiliada_id'] ?? '') === (string)$institucion->id)>{{ $institucion->numero_afiliacion }} — {{ $institucion->nombre }}</option>@endforeach</select>@if($instituciones->isEmpty())<p role="alert">El catálogo de instituciones debe ser cargado por la coordinación.</p>@endif</div>
<div class="botones"><a class="boton" href="{{ route('refrendos.detalle') }}">Anterior</a><button class="boton">Siguiente</button></div></form>
@endsection
