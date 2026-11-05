@extends('refrendos.layout')
@section('contenido')
<div class="mensaje"><h2>¡Su registro se ha completado de manera exitosa!</h2><p>Puede descargar su comprobante</p><a class="boton" href="{{ route('refrendos.comprobante', $solicitud->id) }}">Descargar</a><p class="ayuda">El comprobante acredita el registro de la solicitud; no constituye un dictamen de certificación.</p><a class="boton" href="{{ route('panel') }}">{{ $solicitud->articulo_14 ? 'Finalizar' : 'Volver' }}</a></div>
@endsection
