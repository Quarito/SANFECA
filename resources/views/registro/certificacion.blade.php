
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Certificación | ANFECA</title>

    <link rel="stylesheet"
          href="{{ asset('css/certificacion.css') }}">
</head>
<body>

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
            {{ auth()->user()->name }}
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
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST"
              action="{{ route('registro.certificacion.guardar') }}"
              class="formulario">
            @csrf

            <div class="columnas">

                <!-- CERTIFICACIÓN -->
                <section class="seccion">
                    <h2>Certificación</h2>

                    <p class="descripcion">
                        Seleccione la Certificación Académica
                        a la que se aspira
                    </p>

                    <div class="opciones">
                        @foreach(['ANFECA', 'CACSLA', 'CACECA'] as $opcion)
                            <label class="opcion">
                                <input
                                    type="radio"
                                    name="certificacion"
                                    value="{{ $opcion }}"
                                    required
                                    {{ old('certificacion', $seleccion?->certificacion) === $opcion ? 'checked' : '' }}
                                >
                                <span>{{ $opcion }}</span>
                            </label>
                        @endforeach
                    </div>
                </section>

                <!-- PERSONAL ACADÉMICO -->
                <section class="seccion">
                    <h2>Personal Académico</h2>

                    <div class="campo">
                        <label for="tipo_personal">
                            Tipo de Personal Académico
                        </label>

                        <select id="tipo_personal"
                                name="tipo_personal"
                                required>
                            <option value="">
                                Seleccione una opción
                            </option>

                            @foreach([
                                'Tiempo Completo',
                                'Medio Tiempo',
                                'PAES'
                            ] as $tipo)
                                <option value="{{ $tipo }}"
                                    {{ old('tipo_personal', $seleccion?->tipo_personal) === $tipo ? 'selected' : '' }}>
                                    {{ $tipo }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="campo">
                        <label for="area_certificacion">
                            Área de Certificación Personal
                        </label>

                        <select id="area_certificacion"
                                name="area_certificacion"
                                required>
                            <option value="">
                                Seleccione una opción
                            </option>

                            @foreach([
                                'Contaduría',
                                'Administración',
                                'Informática'
				'Negocios Internacionales',
				'Disciplinas vinculadas al ámbito...',
                            ] as $area)
                                <option value="{{ $area }}"
                                    {{ old('area_certificacion', $seleccion?->area_certificacion) === $area ? 'selected' : '' }}>
                                    {{ $area }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </section>

            </div>

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

