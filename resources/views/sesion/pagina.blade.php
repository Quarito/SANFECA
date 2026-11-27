{{-- Página de mensaje con el mismo diseño de «Recuperación de contraseña». Sin @vite: nunca depende de la compilación. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>{{ $titulo }} | ANFECA</title>
    <link rel="stylesheet" href="{{ asset('css/certificacion.css') }}">
    <link rel="stylesheet" href="{{ asset('css/recuperar-password.css') }}">
</head>
<body>
<div class="pagina-recuperacion">
    <header class="encabezado">
        <div class="logos">
            <img src="{{ asset('images/logo-certificacion.png') }}" alt="Certificación ANFECA">
        </div>
        <h1>REGISTRO AL SISTEMA DE CERTIFICACIÓN DE LA ANFECA</h1>
        <div class="coordinacion">
            Coordinación Nacional de<br>
            Certificación Académica
        </div>
    </header>

    <main class="contenido-recuperacion">
        <section class="tarjeta-confirmacion" role="alert">
            <div class="confirmacion-icono">!</div>
            <h2>{{ $titulo }}</h2>
            @foreach ($parrafos as $parrafo)
                <p>{{ $parrafo }}</p>
            @endforeach
            <div class="botones-recuperacion">
                @foreach ($acciones as [$texto, $url])
                    <a href="{{ $url }}" class="boton-rojo">{{ $texto }}</a>
                @endforeach
            </div>
        </section>
    </main>

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
