
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Recuperación de Contraseña | ANFECA</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link rel="stylesheet"
          href="{{ asset('css/certificacion.css') }}">

    <link rel="stylesheet"
          href="{{ asset('css/recuperar-password.css') }}?v=4"
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

        @if(session('status'))
            <div class="mensaje-recuperacion">
                {{ session('status') }}
            </div>
        @endif

        @if($errors->any())
            <div class="errores-recuperacion">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('password.email') }}"
            class="formulario-recuperacion"
        >
            @csrf

            <div
                id="vue-recuperar-password"
                data-curp="{{ old('curp', '') }}"
                data-email="{{ old('email', '') }}"
            ></div>

            <div class="botones-recuperacion">
                <a href="{{ route('login') }}"
                   class="boton-rojo">
                    Anterior
                </a>

                <button type="submit" class="boton-rojo">
                    Siguiente
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
