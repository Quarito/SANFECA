<!DOCTYPE html>
<html lang="es">
<head>
@vite(['resources/css/app.css', 'resources/js/app.js'])
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Registro completado | ANFECA</title>

    <link rel="stylesheet"
          href="{{ asset('css/certificacion.css') }}">

    <link rel="stylesheet"
          href="{{ asset('css/registro-completado.css') }}">
    <link rel="stylesheet"
          href="{{ asset('css/barra-usuario-certificacion.css') }}">
</head>

<body @if(request()->is("preview/*")) class="modo-preview" @endif>
    <div class="pagina-completado">

        <header class="encabezado">
            <div class="logos">
                

                <img
                    src="{{ asset('images/logo-certificacion.png') }}"
                    alt="Certificación ANFECA"
                >
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

            <section class="tarjeta-completado">

                @if($registroCompleto)

                    <h2>
                        ¡Su registro se ha completado
                        de manera exitosa!
                    </h2>

                    <p>
                        Puede descargar su comprobante
                    </p>

                    <span
		    class="boton-completado boton-descargar"
		    aria-disabled="true"
 		    title="Descarga disponible próximamente"
            		>
		    ↓ Descargar
		</span>

                @else

                    <h2>
                        Su registro está pendiente
                        de completar
                    </h2>

                    <p>
                        Para descargar su comprobante,
                        primero debe completar las
                        etapas de registro.
                    </p>

                    <a
                        href="{{ route('registro.formacion') }}"
                        class="boton-completado boton-descargar"
                    >
                        Continuar registro
                    </a>

                @endif

                <a
                    href="{{ route('panel') }}"
                    class="boton-completado boton-finalizar"
                >
                    Finalizar
                </a>

            </section>

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

