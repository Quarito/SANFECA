
<!DOCTYPE html>
<html lang="es">
<head>
@vite(['resources/css/app.css', 'resources/js/app.js'])
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Certificación | ANFECA</title>

    <link rel="stylesheet"
	          href="{{ asset('css/certificacion.css') }}">
	<link rel="stylesheet"
	      href="{{ asset('css/certificacion-vue.css') }}">

</head>
<body @if(request()->is("preview/*")) class="modo-preview" @endif>

<div class="pagina-anfeca">

    <!-- ENCABEZADO INSTITUCIONAL -->
    <header class="encabezado">
        <div class="logos">
            <img src="{{ asset('images/logo-anfeca.png') }}"
                 alt="Logotipo ANFECA">

            <img src="{{ asset('images/logo-certificacion.png') }}"
                 alt="Logotipo de certificación">
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

    <!-- USUARIO -->
    <div class="barra-usuario">
        <span class="icono-usuario">◉</span>
        <span>
            Usuario:
            {{ auth()->user()?->name ?? 'Vista previa' }}
        </span>
    </div>

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
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST"
              action="{{ route('registro.certificacion.guardar') }}"
              class="formulario"
@if(request()->is("preview/*"))
    onsubmit="return false;"
@endif
>
            @csrf


	<!-- SELECCIÓN DE CERTIFICACIÓN CON VUE 3 -->
	<div
	    id="vue-seleccion-certificacion"
	    data-seleccion="{{ json_encode([
	        'certificacion' => old(
	            'certificacion',
	            $seleccion?->certificacion
	        ),
	        'tipo_personal' => old(
	            'tipo_personal',
	            $seleccion?->tipo_personal
	        ),
	        'area_certificacion' => old(
	            'area_certificacion',
	            $seleccion?->area_certificacion
	        )
	    ]) }}"
	></div>


            <!-- NAVEGACIÓN -->
            <div class="botones">
                <a href="{{ route('registro.datos') }}"
                   class="boton">
                    Anterior
                </a>

                <button type="submit" class="boton">
                    Siguiente
                </button>
            </div>
        </form>

    </main>
</div>

    <!-- PIE DE PÁGINA -->
    <footer class="pie-pagina">
        <div class="origen">
            Hecho en México<br>
            D.R. © {{ date('Y') }}
        </div>

        <div class="legal">
            Esta página puede ser reproducida con fines no
            lucrativos, siempre y cuando no se mutile,
            se cite la fuente completa y su dirección electrónica.
            De otra forma requiere permiso previo por escrito
            de la institución.
            AVISO DE PRIVACIDAD.
        </div>
    </footer>

</div>

</body>
</html>

