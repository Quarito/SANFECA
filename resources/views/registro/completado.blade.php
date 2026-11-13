<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Revisión de solicitud | ANFECA</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link rel="stylesheet"
          href="{{ asset('css/certificacion.css') }}">
    <link rel="stylesheet"
          href="{{ asset('css/registro-completado.css') . '?v=639' }}">
    <link rel="stylesheet"
          href="{{ asset('css/barra-usuario-certificacion.css') }}">
</head>

<body @if(request()->is('preview/*')) class="modo-preview" @endif>
<div class="pagina-completado">

    <header class="encabezado">
        <div class="logos">
            <img
                src="{{ asset('images/logo-certificacion.png') }}"
                alt="Certificación ANFECA">
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

            <!-- ENCABEZADO INSTITUCIONAL REUTILIZABLE VUE 3 -->
            <div
                data-vue-encabezado
                data-titulo="Finalización del Registro"
                data-descripcion="Revise el estado de su solicitud y consulte la información correspondiente a su registro.">
            </div>
            <section class="tarjeta-completado">

                @if(session('success'))
                    <p role="status">
                        {{ session('success') }}
                    </p>
                @endif

                @if(session('error'))
                    <p role="alert">
                        {{ session('error') }}
                    </p>
                @endif

                @if($errors->any())
                    <div role="alert">
                        @foreach($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                @if($enviada ?? false)

                    <h2>Solicitud enviada correctamente</h2>

                    <p>
                        Su solicitud de
                        <strong>{{ $solicitud->certificacion }}</strong>
                        ha sido registrada.
                    </p>

                    <p>
                        Tipo de trámite:
                        <strong>
                            {{ $solicitud->tipo_tramite === 'refrendo'
                                ? 'Refrendo'
                                : 'Certificación inicial' }}
                        </strong>
                    </p>

                    <p>
                        Folio de solicitud:
                        <strong>{{ $solicitud->id }}</strong>
                    </p>

                    <p>
                        Fecha de envío:
                        <strong>
                            {{ $solicitud->fecha_envio
                                ? $solicitud->fecha_envio->copy()->timezone('America/Mexico_City')->format('d/m/Y H:i')
                                : 'Pendiente de registro' }}
                        </strong>
                    </p>

                    <p>Su solicitud fue recibida y queda pendiente de revisión.
                        La recepción no implica aprobación ni otorgamiento de la certificación.</p>

                    @if($acuseDisponible ?? false)
                        <a href="{{ route('solicitudes.acuse', $solicitud->id) }}"
                           class="boton-completado boton-descargar">Descargar acuse en PDF</a>
                    @else
                        <p>Esta solicitud no cuenta con un acuse disponible porque se envió
                           antes de habilitar esta función.</p>
                    @endif

                @else

                    @if($registroCompleto ?? false)

                        <h2>¡Su expediente está completo!</h2>

                        <p>
                            Puede confirmar el envío de su solicitud.
                        </p>

                        <form method="POST"
                              action="{{ route('registro.completado.confirmar') }}">
                            @csrf
                            <input type="hidden" name="solicitud_id" value="{{ $solicitud->id }}">

                            <button type="submit"
                                    class="boton-completado boton-finalizar">
                                Confirmar envío de solicitud
                            </button>
                        </form>

                    @else

                        <h2>Su expediente está incompleto</h2>

                        <p>
                            Complete los requisitos pendientes
                            antes de confirmar el envío.
                        </p>

                        <a href="{{ route('registro.documentos') }}"
                           class="boton-completado boton-descargar">
                            Continuar registro
                        </a>

                    @endif

                @endif

                <a href="{{ route('panel') }}"
                   class="boton-completado boton-finalizar">
                    Regresar al panel
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
