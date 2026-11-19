<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Aceptación y compromiso | ANFECA</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link rel="stylesheet"
          href="{{ asset('css/certificacion.css') }}">

    <link rel="stylesheet"
          href="{{ asset('css/registro-completado.css') }}?v=639">

    <link rel="stylesheet"
          href="{{ asset('css/barra-usuario-certificacion.css') }}">

    <link rel="stylesheet"
          href="{{ asset('css/tipografia-global-anfeca.css') }}?v=97159">

    <link rel="stylesheet"
          href="{{ asset('css/anfeca-layout-compacto.css') }}?v=2">

    <link rel="stylesheet"
          href="{{ asset('css/anfeca-layout-compacto-v3.css') }}?v=3">

    <link rel="stylesheet"
          href="{{ asset('css/anfeca-compacto-v4.css') }}?v=4">
</head>

<body>
<div class="pagina-completado">

    <header class="encabezado">
        <div class="logos">
            <img
                src="{{ asset('images/logo-certificacion.png') }}"
                alt="Certificación ANFECA">
        </div>

        <h1>
            REGISTRO AL SISTEMA DE<br>
            CERTIFICACIÓN DE LA ANFECA
        </h1>

        <div class="coordinacion">
            Coordinación Nacional de<br>
            Certificación Académica
        </div>
    </header>

    @include('components.barra-usuario-certificacion')

    <div class="cert-layout">

        @include('components.sidebar-certificacion')

        <main class="contenido-completado cert-main">

            <div
                data-vue-encabezado
                data-titulo="Aceptación y compromiso"
                data-descripcion="Revise y acepte el compromiso de entrega de documentación para continuar con su solicitud.">
            </div>

            @if(session('error'))
                <p role="alert">
                    {{ session('error') }}
                </p>
            @endif

            @if($errors->any())
                <div role="alert">
                    @foreach($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <div
                id="vue-aceptacion-compromiso"
                data-accion="{{ route('registro.compromiso.guardar') }}"
                data-csrf="{{ csrf_token() }}"
                data-url-regresar="{{ route('registro.documentos') }}">
            </div>

        </main>
    </div>

    <footer class="pie-pagina pie-completado">
        <div class="origen">
            Hecho en México<br>
            D.R. © {{ date('Y') }}
        </div>

        <div class="legal">
            Esta página puede ser reproducida con fines no lucrativos,
            siempre y cuando no se mutile y se cite la fuente
            completa y su dirección electrónica.
            AVISO DE PRIVACIDAD.
            Sitio web administrado por el Centro de Informática
            de la Facultad de Contaduría y Administración (FCA).
        </div>
    </footer>

</div>
</body>
</html>
