@extends('refrendos.layout')
@section('contenido')
<form class="formulario" method="POST" action="{{ route('refrendos.certificacion.guardar') }}">@csrf
<div class="columnas">
<section><h2>Certificación</h2><p>Seleccione la Certificación Académica a la que se aspira</p>
@foreach(['ANFECA','CACSLA','CACECA'] as $opcion)<label><input type="radio" name="certificacion" value="{{ $opcion }}" @checked(old('certificacion', $seleccion?->certificacion) === $opcion) required> {{ $opcion }}</label>@endforeach
</section>
<section><h2>Personal Académico</h2>
@foreach(['tipo_personal'=>['Tiempo Completo','Medio Tiempo','PAES'], 'area_certificacion'=>['Contaduría','Administración','Informática','Negocios Internacionales','Disciplinas vinculadas al ámbito...']] as $campo=>$opciones)
<label for="{{ $campo }}">{{ $campo === 'tipo_personal' ? 'Tipo de Personal Académico' : 'Área de Certificación Personal' }}</label>
<select id="{{ $campo }}" name="{{ $campo }}" required><option value="">Seleccione una opción</option>@foreach($opciones as $opcion)<option @selected(old($campo, $seleccion?->{$campo}) === $opcion)>{{ $opcion }}</option>@endforeach</select>
@endforeach
</section></div>
<div class="botones"><a class="boton" href="{{ route('refrendos.inicio') }}">Anterior</a><button class="boton">Siguiente</button></div>
</form>
@endsection
