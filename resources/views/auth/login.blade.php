<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Registro ANFECA</title>

    <link rel="stylesheet"
          href="{{ asset('css/login.css') }}">
</head>
<body>

<div class="contenedor">

    <!-- ENCABEZADO -->
    <header class="encabezado">
    <div class="logos">

        <img src="{{ asset('images/certificacion_anfeca.png') }}"
             alt="Certificación Académica ANFECA">
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

    <!-- CONTENIDO -->
    <main class="contenido">

        <section class="fotografia">
            <img src="{{ asset('images/evento_anfeca.jpg') }}"
                 alt="Evento académico ANFECA">
        </section>

        <section class="acceso">

	    {{-- Mensaje de confirmación de recuperación de contraseña --}}
	    @if (session('status'))
	        <div class="mensaje-exito" role="status">
	            <span class="mensaje-exito-icono">✓</span>
	            <span>{{ session('status') }}</span>
	        </div>
	    @endif

	    <form action="{{ route('login.submit') }}"
	          method="POST">
        @csrf

                <label for="email">Usuario</label>
                <input type="email"
                       id="email"
                       name="email"
                       value="{{ old('email') }}"
                       autocomplete="username"
                       required>

                <label for="password">Contraseña</label>
                <input type="password"
                       id="password"
                       name="password"
                       autocomplete="current-password"
                       required>

                @error('email')
                    <p class="error">{{ $message }}</p>
                @enderror

                <div class="enlaces">
                    <a href="{{ route('registro.datos') }}" class="enlace-registro">
                        Registrarse
                    </a> 

                    <a href="{{ route('password.request') }}">
                        Recuperar contraseña
                    </a>
                </div>

                <div class="botones">
                    <a class="boton"
                       href="{{ route('guia') }}">
                        Guía paso a paso
                    </a>

                    <button type="submit">
                        Iniciar sesión
                    </button>
                </div>
            </form>

            <p class="aviso">
                <strong>Aviso:</strong>
                Si aún no completas tu registro inicial
                o vas a realizar un refrendo, inicia sesión
                con tu <b>CORREO</b> y <b>CONTRASEÑA</b>
                para continuar.
            </p>
        </section>

    </main>

    <!-- PIE DE PÁGINA -->
    <footer class="pie">
        <div class="pie-fecha">
            Hecho en México<br>
            D.R. © 2026
        </div>

        <div class="pie-texto">
            Esta página puede ser reproducida con fines
            no lucrativos, siempre y cuando no se mutile,
            se cite la fuente completa y su dirección
            electrónica. De otra forma requiere permiso
            previo por escrito de la institución.
            <span>
                Aviso de privacidad | Instrumento jurídico
            </span>
        </div>
    </footer>

</div>

</body>
</html>

