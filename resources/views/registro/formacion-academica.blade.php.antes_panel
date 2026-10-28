<!DOCTYPE html>
<html lang="es">
@vite(['resources/css/app.css', 'resources/js/app.js'])
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Formación Académica | ANFECA</title>

    <link rel="stylesheet"
          href="{{ asset('css/certificacion.css') }}">

    <link rel="stylesheet"
          href="{{ asset('css/formacion-academica.css') }}">
</head>

<body>
<div class="pagina-anfeca">

    <!-- ENCABEZADO INSTITUCIONAL -->
    <header class="encabezado">
        <div class="logos">
            <img src="{{ asset('images/logo-anfeca.png') }}"
                 alt="ANFECA">

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

    <!-- USUARIO AUTENTICADO -->
    <div class="barra-usuario">
        <span class="icono-usuario">◉</span>
        <span>
            Usuario: {{ auth()->user()->name }}
        </span>
    </div>

    <main class="contenido">

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

	<form method="POST"
              action="{{ route('registro.formacion.guardar') }}"
              enctype="multipart/form-data"
              class="formulario">
            @csrf


<!-- CAMPOS ACADÉMICOS E INSTITUCIÓN CON VUE 3 -->
<div
    id="vue-formacion-academica"
    data-instituciones="{{ $instituciones->toJson() }}"
	    
	data-formacion="{{ json_encode([
	    'titulo_origen' => old('titulo_origen', $formacion?->titulo_origen),
	    'nivel_ultimo_grado' => old('nivel_ultimo_grado', $formacion?->nivel_ultimo_grado),
	    'titulo_ultimo_grado' => old('titulo_ultimo_grado', $formacion?->titulo_ultimo_grado),
	    'institucion_afiliada_id' => old('institucion_afiliada_id', $formacion?->institucion_afiliada_id),
	    'titulo_origen_archivo' => $formacion?->titulo_origen_archivo,
	    'cedula_origen_archivo' => $formacion?->cedula_origen_archivo,
	    'titulo_ultimo_grado_archivo' => $formacion?->titulo_ultimo_grado_archivo,
	    'cedula_ultimo_grado_archivo' => $formacion?->cedula_ultimo_grado_archivo
	]) }}"

></div>

            <!-- BOTONES -->
            <div class="botones">
                <a href="{{ route('registro.certificacion') }}"
                   class="boton">
                    Anterior
                </a>

                <button type="submit" class="boton">
                    Siguiente
                </button>
            </div>
        </form>
    </main>

    <!-- PIE INSTITUCIONAL -->
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

