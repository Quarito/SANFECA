
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

    <!-- tipografia-global-97159 -->
    <link rel="stylesheet" href="{{ asset('css/tipografia-global-anfeca.css') }}?v=97159">
<link rel="stylesheet"
      href="{{ asset('css/anfeca-layout-compacto.css') }}?v=2">


<link rel="stylesheet" href="{{ asset('css/anfeca-layout-compacto-v3.css') }}?v=3">
    <link rel="stylesheet" href="{{ asset('css/anfeca-compacto-v4.css') }}?v=4">
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

                {{-- Errores de validación del envío (incluido el espejo PDM de F3). --}}
                @if($errors->any())
                    @foreach(array_unique($errors->all()) as $error)
                        <p role="alert">
                            {{ $error }}
                        </p>
                    @endforeach
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
                        Fecha de registro:
                        <strong>
                            {{ $solicitud->fecha_envio
                                ? $solicitud->fecha_envio->copy()->timezone('America/Mexico_City')->format('d/m/Y H:i')
                                : 'Pendiente de registro' }}
                        </strong>
                    </p>

                    <p>
                        Su solicitud fue enviada para su
                        revisión correspondiente.
                    </p>

                    @if($solicitud->compromiso_aceptado)
                        <p>
                            Compromiso de entrega aceptado:
                            <strong>{{ $solicitud->compromiso_aceptado_en?->copy()->timezone('America/Mexico_City')->format('d/m/Y H:i') ?? 'Sí' }}</strong>
                            · <a href="{{ route('registro.compromiso') }}">Consultar</a>
                        </p>
                    @endif

                    <p>
                        <a href="{{ route('registro.acuse.descargar', ['solicitudId' => $solicitud->id]) }}"
                           class="boton-completado boton-descargar">
                            Descargar acuse de recepción (PDF)
                        </a>
                    </p>

                    @if(session('descargar_acuse_solicitud_id') == $solicitud->id)
                        <iframe
                            src="{{ route('registro.acuse.descargar', ['solicitudId' => $solicitud->id]) }}"
                            title="Descarga automática del acuse"
                            aria-hidden="true"
                            tabindex="-1"
                            style="display:none">
                        </iframe>
                    @endif

                @else

                    @if($registroCompleto ?? false)

                        <h2>¡Su expediente está completo!</h2>

                        <p>
                            Puede confirmar el envío de su solicitud.
                        </p>

                        <p>
                            Compromiso de entrega aceptado:
                            <strong>{{ $solicitud->compromiso_aceptado_en?->copy()->timezone('America/Mexico_City')->format('d/m/Y H:i') ?? 'Sí' }}</strong>
                            · <a href="{{ route('registro.compromiso') }}">Consultar</a>
                        </p>

                        <form method="POST"
                              action="{{ route('registro.completado.confirmar') }}">
                            @csrf

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

                @if(!($enviada ?? false))
                    <a href="{{ route('mis-datos.edit') }}"
                       class="boton-completado boton-finalizar">Revisar mi registro</a>
                @endif

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

@if(($enviada ?? false) && auth()->check())
<div id="aviso-cierre-acuse"
     role="status"
     aria-live="polite"
     hidden
     style="position:fixed;bottom:20px;right:20px;
            z-index:9999;max-width:380px;
            background:#fff;border:1px solid #d5d5d5;
            border-left:5px solid #bb1025;
            border-radius:10px;padding:18px;
            box-shadow:0 8px 24px rgba(0,0,0,.15);">

    <strong>Acuse de recepción</strong>

    <p style="margin:10px 0;">
        Se ha iniciado la descarga de su acuse.
        Por seguridad, su sesión se cerrará en
        <strong id="segundos-cierre">15</strong> segundos.
    </p>

    <p style="font-size:13px;">
        Haga clic en cualquier parte de la página
        para permanecer en su sesión.
    </p>

    <button type="button"
            id="cancelar-cierre-acuse"
            style="background:#bb1025;color:white;
                   border:0;border-radius:6px;
                   padding:8px 12px;cursor:pointer;">
        Permanecer en la sesión
    </button>
</div>

<form id="form-cierre-automatico"
      method="POST"
      action="{{ route('logout') }}"
      hidden>
    @csrf
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const aviso = document.getElementById('aviso-cierre-acuse');
    const contador = document.getElementById('segundos-cierre');
    const formulario = document.getElementById('form-cierre-automatico');
    const botonCancelar = document.getElementById('cancelar-cierre-acuse');
    const enlaceDescarga = document.querySelector('.boton-descargar[href*="/acuse/"]');
    const descargaAutomatica = document.querySelector(
        'iframe[title="Descarga automática del acuse"]'
    );

    let intervalo = null;
    let segundos = 15;

    function cancelarCierre() {
        if (!intervalo) return;

        clearInterval(intervalo);
        intervalo = null;
        aviso.hidden = true;
    }

    function iniciarCierre() {
        if (intervalo) clearInterval(intervalo);

        segundos = 15;
        contador.textContent = segundos;
        aviso.hidden = false;

        intervalo = setInterval(function () {
            segundos--;
            contador.textContent = segundos;

            if (segundos <= 0) {
                clearInterval(intervalo);
                intervalo = null;
                formulario.submit();
            }
        }, 1000);
    }

    document.addEventListener('click', function (evento) {
        if (!intervalo) return;

        if (enlaceDescarga && enlaceDescarga.contains(evento.target)) {
            return;
        }

        cancelarCierre();
    });

    document.addEventListener('keydown', cancelarCierre);

    botonCancelar.addEventListener('click', cancelarCierre);

    if (enlaceDescarga) {
        enlaceDescarga.addEventListener('click', function () {
            iniciarCierre();
        });
    }

    // Primer envío: el iframe ya solicita la descarga automática.
    // No depender del evento load de una respuesta PDF descargable.
    if (descargaAutomatica) {
        iniciarCierre();
    }
});
</script>
@endif

</body>
</html>
