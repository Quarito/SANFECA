<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Nueva Contraseña | ANFECA</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link rel="stylesheet"
          href="{{ asset('css/certificacion.css') }}">

    <link rel="stylesheet"
          href="{{ asset('css/recuperar-password.css') }}?v=5"
</head>

<body>
<div class="pagina-recuperacion">

    <header class="encabezado">
        <div class="logos">
            
            <img src="{{ asset('images/logo-certificacion.png') }}"
                 alt="Certificación ANFECA">
        </div>

        <h1>RECUPERACIÓN DE CONTRASEÑA</h1>

        <div class="coordinacion">
            Coordinación Nacional de<br>
            Certificación Académica
        </div>
    </header>

    <main class="contenido-recuperacion">

        @if($errors->any())
            <div class="errores-recuperacion">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST"
              action="{{ route('password.update') }}"
              class="formulario-recuperacion">

            @csrf

            <input type="hidden"
                   name="token"
                   value="{{ $token }}">

            <input type="hidden"
                   name="email"
                   value="{{ $email }}">

            <div id="vue-nueva-password"></div>

            <div class="botones-recuperacion">
                <a href="{{ route('login') }}"
                   class="boton-rojo">
                    Anterior
                </a>

                <button type="submit" class="boton-rojo">
                    Guardar Contraseña
                </button>
            </div>
        </form>
    </main>

    <footer class="pie-pagina">
        <div class="origen">
            Hecho en México<br>
            D.R. © {{ date('Y') }}
        </div>

        <div class="legal">
            Sistema de Certificación Académica ANFECA.
        </div>
    </footer>

</div>
</body>
</html>
