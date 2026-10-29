<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Panel del solicitante | ANFECA</title>

    <link rel="stylesheet"
          href="{{ asset('css/certificacion.css') }}">

    <link rel="stylesheet"
          href="{{ asset('css/panel-anfeca.css') }}">
</head>

<body>
<div class="pagina-anfeca">

    <header class="encabezado">
        <div class="logos">
            

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

    <div class="barra-usuario">
        <span class="icono-usuario">◉</span>
        <span>
            Usuario: {{ auth()->user()->name }}
        </span>
    </div>

    <main class="contenido">
        <div class="panel-contenedor">

            <h2>Panel del solicitante</h2>

            <p class="panel-descripcion">
                Consulta el avance de tu registro
                y continúa con las etapas pendientes.
            </p>

            <div class="panel-progreso">
                <div class="progreso-encabezado">
                    <strong>Avance del registro</strong>
                    <span>{{ $porcentaje }}%</span>
                </div>

                <div class="barra-progreso">
                    <div
                        class="barra-progreso-valor"
                        style="width: {{ $porcentaje }}%">
                    </div>
                </div>

                <p>
                    {{ $completadas }} de
                    {{ count($etapas) }} etapas registradas.
                </p>
            </div>

            <div class="panel-etapas">
                @foreach($etapas as $etapa)
                    <section class="tarjeta-etapa">
                        <h3>{{ $etapa['nombre'] }}</h3>

                        <p>{{ $etapa['descripcion'] }}</p>

                        <span class="estado-etapa
                            {{ $etapa['completa'] ? 'completa' : 'pendiente' }}">
                            {{ $etapa['completa'] ? 'Registrada' : 'Pendiente' }}
                        </span>

                        <a href="{{ route($etapa['ruta']) }}"
                           class="boton">
                            {{ $etapa['completa'] ? 'Consultar' : 'Continuar' }}
                        </a>
                    </section>
                @endforeach
            </div>

            <div class="panel-acciones">
                <form method="POST"
                      action="{{ route('logout') }}">
                    @csrf

                    <button type="submit" class="boton">
                        Cerrar sesión
                    </button>
                </form>
            </div>

        </div>
    </main>

    <footer class="pie-pagina">
        <div class="origen">
            Hecho en México<br>
            D.R. © {{ date('Y') }}
        </div>

        <div class="legal">
            Esta página puede ser reproducida con fines no lucrativos,
            siempre y cuando no se mutile, se cite la fuente completa
            y su dirección electrónica.
            De otra forma requiere permiso previo por escrito
            de la institución.
            <strong>AVISO DE PRIVACIDAD.</strong>
        </div>
    </footer>

</div>
</body>
</html>
