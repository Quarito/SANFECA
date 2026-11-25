@extends('coordinacion.layout')

@section('titulo', 'Prototipo de revisión documental')

@section('contenido')
    <div class="coord-titulo-seccion">
        <h1>Revisión documental (prototipo)</h1>
        <p>Propuesta visual de la fase 6.3 sobre la consulta de solicitud.</p>
    </div>

    <div class="anfeca-aviso anfeca-aviso--advertencia" role="note" style="margin-bottom: 12px;">
        <strong>PROTOTIPO DEMOSTRATIVO</strong> · datos ficticios · nada se guarda ni se consulta en la base de datos.
        Los resultados y la auditoría existen solo en este navegador mientras la página está abierta.
    </div>

    <div id="vue-prototipo-revision">
        <p class="coord-cargando">Cargando prototipo…</p>
    </div>
@endsection
