@extends('refrendos.layout')
@section('contenido')
<h2>Bienvenido de Nuevo: {{ $datos?->nombres }} {{ $datos?->apellido_paterno }} {{ $datos?->apellido_materno }}</h2>
<div class="datos">
    <section><h3>Datos personales</h3><p>Domicilio: {{ $datos?->calle }} {{ $datos?->numero_exterior }}, {{ $datos?->colonia }}, {{ $datos?->codigo_postal }} {{ $datos?->municipio }}, {{ $datos?->entidad_federativa }}</p><p>CURP: {{ $datos?->curp }}</p></section>
    <section><h3>Datos de contacto</h3><p>Teléfono de celular: {{ $datos?->telefono_celular }}</p><p>Teléfono particular: {{ $datos?->telefono_particular }}</p><p>Correo Electrónico: {{ $datos?->correo_electronico }}</p></section>
</div>
<div class="botones"><a class="boton" href="{{ route('mis-datos.edit') }}">Anterior</a><a class="boton" href="{{ route('refrendos.certificacion') }}">Siguiente</a></div>
@endsection
