<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refrendos | ANFECA</title>
    <link rel="stylesheet" href="{{ asset('css/certificacion.css') }}">
    <link rel="stylesheet" href="{{ asset('css/barra-usuario-certificacion.css') }}">
    <link rel="stylesheet" href="{{ asset('css/refrendos.css') }}">
</head>
<body>
<div class="pagina-anfeca refrendos">
    <header class="encabezado">
        <div class="logos"><img class="logo-anfeca" src="{{ asset('images/logo-anfeca.png') }}" alt="ANFECA"><img src="{{ asset('images/logo-certificacion.png') }}" alt="Certificación académica"></div>
        <h1>REGISTRO AL SISTEMA DE<br>CERTIFICACIÓN DE LA ANFECA</h1>
        <div class="coordinacion">Coordinación Nacional de<br>Certificación Académica</div>
    </header>
    @include('components.barra-usuario-certificacion')
    <main class="contenido">
        @if($errors->any())<div class="mensaje-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @yield('contenido')
    </main>
    <footer class="pie-pagina">
        <div>Hecho en México<br>D.R. © {{ date('Y') }}</div>
        <div class="legal">Esta página puede ser reproducida con fines no lucrativos, siempre y cuando no se mutile, se cite la fuente completa y su dirección electrónica. De otra forma requiere permiso previo por escrito de la institución. <strong>AVISO DE PRIVACIDAD.</strong> Sitio web administrado por el Centro de Informática de la Facultad de Contaduría y Administración (CIFCA). Documento de seguridad | Instrumento jurídico | Aviso de privacidad simplificado</div>
    </footer>
</div>
</body>
</html>
