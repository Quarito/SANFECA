<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datos de Refrendo | ANFECA</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link rel="stylesheet" href="{{ asset('css/certificacion.css') }}">
    <link rel="stylesheet" href="{{ asset('css/formacion-academica.css') }}">
    <link rel="stylesheet" href="{{ asset('css/barra-usuario-certificacion.css') }}">
    <link rel="stylesheet" href="{{ asset('css/refrendo.css') }}">

    @if(request()->is('preview/*'))
        <style>
            .formulario-refrendo .botones button:disabled {
                background-color: #a6a6a6 !important;
                border-color: #a6a6a6 !important;
                color: #ffffff !important;
                cursor: not-allowed;
                opacity: 0.75;
            }
        </style>
    @endif
</head>

<body>
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

            <div
                data-vue-encabezado
                data-titulo="Datos de Refrendo"
                data-descripcion="Complete la información correspondiente a su solicitud de refrendo de certificación."
            ></div>

            @if(session('success'))
                <div class="mensaje-exito">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="mensaje-error">
                    {{ session('error') }}
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

            <form method="POST"
                  action="{{ route('registro.refrendo.guardar') }}"
                  class="formulario formulario-refrendo"
                  @if(request()->is('preview/*'))
                      onsubmit="return false;"
                  @endif>

                @csrf

                <div id="vue-datos-refrendo"
                     data-datos='@json($datosRefrendo ?? new stdClass())'
                     data-automaticos='@json($datosAutomaticos)'>
                </div>

                <div class="botones">
                    <a href="{{ request()->is('preview/*')
                        ? route('preview.certificacion')
                        : route('registro.certificacion') }}"
                       class="boton">
                        Anterior
                    </a>

                    @if(request()->is('preview/*'))
                        <button type="button"
                                class="boton"
                                disabled
                                title="Disponible únicamente en el registro real">
                            Siguiente
                        </button>
                    @else
                        <button type="submit" class="boton">
                            Siguiente
                        </button>
                    @endif
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
            Esta página puede ser reproducida con fines no
            lucrativos, siempre y cuando no se mutile y se
            cite la fuente completa y su dirección electrónica.
            AVISO DE PRIVACIDAD.
        </div>
    </footer>
</div>


</body>
</html>
