
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Solicitud recibida | ANFECA</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link rel="stylesheet"
          href="{{ asset('css/certificacion.css') }}">

    <link rel="stylesheet"
          href="{{ asset('css/recuperar-password.css') }}">
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

        <section class="tarjeta-confirmacion">

            <div class="confirmacion-icono">✓</div>

            <h2>Solicitud recibida</h2>

            <p>
                Si los datos proporcionados corresponden
                a una cuenta registrada, recibirás un
                correo electrónico con las instrucciones
                para restablecer tu contraseña.
            </p>

            <p>
                Revisa también tu carpeta de correo
                no deseado.
            </p>

            <div class="botones-recuperacion">
                <a href="{{ route('login') }}"
                   class="boton-rojo">
                    Volver al inicio de sesión
                </a>
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
