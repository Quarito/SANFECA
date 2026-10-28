
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Recuperación de Contraseña | ANFECA</title>

    <link rel="stylesheet"
          href="{{ asset('css/certificacion.css') }}">

    <link rel="stylesheet"
          href="{{ asset('css/recuperacion-enviada.css') }}">
</head>

<body>
<div class="pagina-recuperacion-enviada">

    <header class="encabezado">
        <div class="logos">
            <img src="{{ asset('images/logo-anfeca.png') }}"
                 alt="ANFECA">

            <img src="{{ asset('images/logo-certificacion.png') }}"
                 alt="Certificación ANFECA">
        </div>

        <h1>RECUPERACIÓN DE CONTRASEÑA</h1>

        <div class="coordinacion">
            Coordinación Nacional de<br>
            Certificación Académica
        </div>
    </header>

    <main class="contenido-recuperacion-enviada">

        <section class="confirmacion-recuperacion">

            <h2>Recuperación de Contraseña</h2>

            <div class="mensaje-confirmacion">
                Si los datos corresponden a una cuenta
                registrada, se enviará un enlace de
                recuperación a su correo electrónico.
                <strong>
                    Revise su correo
                </strong>
                y siga las instrucciones.
            </div>

            <a href="{{ route('login') }}"
               class="boton-volver">
                Volver
            </a>

        </section>

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
