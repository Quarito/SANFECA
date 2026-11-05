@extends('refrendos.layout')
@section('contenido')
<form class="formulario" method="POST" enctype="multipart/form-data" action="{{ route('refrendos.documentos.guardar', $solicitud->id) }}">@csrf
<div class="columnas"><section><h2>Documentos Básicos</h2>
@foreach($campos as $campo=>$nombre)
@if($loop->index === 4)</section><section><h2>Documentos Refrendo</h2>@endif
<div class="documento"><label for="{{ $campo }}">{{ $nombre }}</label><div><input id="{{ $campo }}" name="{{ $campo }}" type="file" accept=".pdf,.jpg,.jpeg,.png" @required(empty($solicitud->documentos[$campo]))>@if(!empty($solicitud->documentos[$campo]))<a href="{{ route('refrendos.documentos.ver', [$solicitud->id,$campo]) }}">Descargar archivo actual</a>@endif</div></div>
@endforeach
</section></div><p class="ayuda">PDF, JPG o PNG. Máximo 5 MB por archivo. Al continuar se enviará el registro y no podrá modificarse.</p>
<div class="botones"><a class="boton" href="{{ route('refrendos.formacion', $solicitud->id) }}">Anterior</a><button class="boton">Siguiente</button></div></form>
@endsection
