<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Documentos Probatorios | ANFECA</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link rel="stylesheet"
          href="{{ asset('css/certificacion.css') }}">

    <link rel="stylesheet"
          href="{{ asset('css/documentos-probatorios.css') }}">

	<link rel="stylesheet"
	      href="{{ asset('css/barra-usuario-certificacion.css') }}">

</head>

<body @if(request()->is("preview/*")) class="modo-preview" @endif>
<div class="pagina-anfeca">

    <header class="encabezado">
        <div class="logos">
            

            <img src="{{ asset('images/logo-certificacion.png') }}"
                 alt="Certificación">
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

    <main class="contenido cert-main">

        @if(session('success'))
            <div class="mensaje-exito">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mensaje-error">
                <strong>Revisa los siguientes campos:</strong>
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('registro.documentos.guardar') }}"
            enctype="multipart/form-data"
            class="formulario"
        
@if(request()->is("preview/*"))
    onsubmit="return false;"
@endif
>
            @csrf

            <div
                id="vue-documentos-probatorios"
                data-academicos="{{ json_encode($academicos) }}"
                data-nuevos="{{ json_encode($nuevos) }}"
                data-link="{{ $link }}"
            ></div>

            <div class="botones">
                <a href="{{ route('registro.formacion') }}"
                   class="boton">
                    Anterior
                </a>

                <button type="submit" class="boton">
                    Guardar
                </button>
            </div>
        </form>
    </main>
</div>

    <footer class="pie-pagina">
        <div class="origen">
            Hecho en México<br>
            D.R. © {{ date('Y') }}
        </div>

        <div class="legal">
            Sistema de Certificación Académica ANFECA.
            Consulta el aviso de privacidad institucional.
        </div>
    </footer>

</div>
</body>
</html>
