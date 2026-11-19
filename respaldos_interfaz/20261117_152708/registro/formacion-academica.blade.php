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
    <link rel="stylesheet"
          href="{{ asset('css/documentos-compartidos.css') }}">
    <link rel="stylesheet"
          href="{{ asset('css/barra-usuario-certificacion.css') }}">

<style id="mejora-visual-formacion-97152">
/* Mejorar la separación visual de los dos bloques académicos */
.formacion-columnas > *,
.columnas-formacion > *,
.formacion-academica .columna-formacion {
    box-sizing: border-box;
}

/* Desplegables: ancho más equilibrado y texto centrado */
#vue-formacion-academica select {
    width: 80%;
    max-width: 360px;
    min-height: 34px;
    margin-left: auto;
    margin-right: auto;
    display: block;
    text-align: center;
    text-align-last: center;
    box-sizing: border-box;
}

#vue-formacion-academica select option {
    text-align: center;
}

/* Conservar una presentación cómoda en pantallas pequeñas */
@media (max-width: 768px) {
    #vue-formacion-academica select {
        width: 100%;
        max-width: 100%;
    }
}
</style>


<style id="bordes-formacion-97153">
#vue-formacion-academica .mejora-columnas-97153 {
    border: 1px solid #d9d9d9;
    border-radius: 10px;
    padding: 22px 18px;
    background: #ffffff;
    box-sizing: border-box;
}

#vue-formacion-academica .mejora-columnas-97153 h2 {
    text-align: center;
    margin-top: 0;
    margin-bottom: 22px;
}

@media (max-width: 768px) {
    #vue-formacion-academica .mejora-columnas-97153 {
        padding: 16px 12px;
    }
}
</style>


<style id="mejora-institucion-97154">

/* Recuadro de Institución de Procedencia */
#vue-formacion-academica .institucion {
    border: 1px solid #d9d9d9;
    border-radius: 10px;
    background: #ffffff;
    padding: 22px 18px;
    margin-top: 24px;
    box-sizing: border-box;
}

/* Título */
#vue-formacion-academica .institucion h2 {
    text-align: center;
    margin-top: 0;
    margin-bottom: 22px;
}

/* Desplegable de número de afiliación */
#vue-formacion-academica .institucion select {
    width: 80%;
    max-width: 300px;
    margin-left: auto;
    margin-right: auto;
    display: block;
    text-align: center;
    text-align-last: center;
    box-sizing: border-box;
}

/* Adaptación a pantallas pequeñas */
@media (max-width: 768px) {
    #vue-formacion-academica .institucion {
        padding: 16px 12px;
    }

    #vue-formacion-academica .institucion select {
        width: 100%;
        max-width: 100%;
    }
}

</style>


<style id="tipografia-compacta-97158">

/* Títulos de las secciones académicas */
#vue-formacion-academica .seccion h2,
#vue-formacion-academica .institucion h2 {
    font-size: 16px !important;
    line-height: 1.3 !important;
}

/* Etiquetas y campos */
#vue-formacion-academica label,
#vue-formacion-academica select,
#vue-formacion-academica input {
    font-size: 13px !important;
}

/* Información de documentos */
#vue-formacion-academica .seccion {
    font-size: 13px;
}

</style>


    <!-- tipografia-global-97159 -->
    <link rel="stylesheet" href="{{ asset('css/tipografia-global-anfeca.css') }}?v=97159">

<style id="homologacion-tipografia-formacion-97173">

/* Homologación con la pantalla Certificación */

/* Texto general */
.cert-main {
    font-size: 12px !important;
}

/* Títulos de secciones */
.cert-main h2,
.cert-main h3 {
    font-size: 15px !important;
    line-height: 1.3 !important;
}

/* Etiquetas y contenido */
.cert-main label,
.cert-main p,
.cert-main small {
    font-size: 12px !important;
    line-height: 1.4 !important;
}

/* Valores de campos */
.cert-main input:not([type="checkbox"]):not([type="radio"]),
.cert-main select,
.cert-main textarea {
    font-size: 12px !important;
    min-height: 32px;
    box-sizing: border-box;
}

/* Botones */
.cert-main button,
.cert-main .boton {
    font-size: 12px !important;
    min-height: 32px;
}

/* Menú lateral */
.cert-layout aside,
.cert-layout aside a {
    font-size: 11px !important;
}

/* Mantener tamaño legible en móviles */
@media (max-width: 768px) {
    .cert-main input,
    .cert-main select,
    .cert-main textarea {
        font-size: 16px !important;
    }
}
</style>


<style id="estilos-autorizacion-formacion-1214">
.fila-autorizacion-formacion {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    margin-top: 12px;
    margin-bottom: 12px;
    width: 100%;
}

.fila-autorizacion-formacion .confirmacion-formacion {
    flex: 1;
    min-width: 0;
}

.etiqueta-confirmacion-formacion {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    line-height: 1.5;
}

.etiqueta-confirmacion-formacion input {
    flex-shrink: 0;
    width: 16px;
    height: 16px;
    accent-color: #b40000;
}

.fila-autorizacion-formacion #boton-autorizar-formacion {
    flex-shrink: 0;
    white-space: nowrap;
}

@media (max-width: 700px) {
    .fila-autorizacion-formacion {
        flex-direction: column;
        align-items: stretch;
        gap: 14px;
    }

    .fila-autorizacion-formacion #boton-autorizar-formacion {
        align-self: flex-end;
    }
}
</style>


<link rel="stylesheet" href="{{ asset('css/anfeca-layout-compacto-v3.css') }}?v=3">
</head>

<body @if(request()->is("preview/*")) class="modo-preview" @endif>
<div class="pagina-anfeca">

    <!-- ENCABEZADO INSTITUCIONAL -->
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

    <!-- USUARIO AUTENTICADO -->
    @include('components.barra-usuario-certificacion')

    <div class="cert-layout">
    @include('components.sidebar-certificacion')

    <main class="contenido cert-main">

        <!-- ENCABEZADO INSTITUCIONAL REUTILIZABLE VUE 3 -->
        <div
            data-vue-encabezado
            data-titulo="Formación Académica"
            data-descripcion="Registre su formación académica y adjunte los documentos correspondientes.">
        </div>

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
              class="formulario"
@if(request()->is("preview/*"))
    onsubmit="return false;"
@endif
>
            @csrf


<!-- CAMPOS ACADÉMICOS E INSTITUCIÓN CON VUE 3 -->
<div
    id="vue-formacion-academica"
    data-formacion-bloqueada="{{ $formacionBloqueada ? 'true' : 'false' }}"
    data-es-refrendo="{{ $solicitud?->tipo_tramite === 'refrendo' ? 'true' : 'false' }}"
    data-instituciones="{{ $instituciones->toJson() }}"
	    
	data-formacion="{{ json_encode([
	    'titulo_origen' => old('titulo_origen', $formacion?->titulo_origen),
	    'nivel_ultimo_grado' => old('nivel_ultimo_grado', $formacion?->nivel_ultimo_grado),
	    'titulo_ultimo_grado' => old('titulo_ultimo_grado', $formacion?->titulo_ultimo_grado),
	    'institucion_afiliada_id' => old('institucion_afiliada_id', $formacion?->institucion_afiliada_id),
	    'titulo_origen_archivo' => $formacion?->titulo_origen_archivo && \Illuminate\Support\Facades\Storage::disk('local')->exists($formacion->titulo_origen_archivo),
	    'cedula_origen_archivo' => $formacion?->cedula_origen_archivo && \Illuminate\Support\Facades\Storage::disk('local')->exists($formacion->cedula_origen_archivo),
	    'titulo_ultimo_grado_archivo' => $formacion?->titulo_ultimo_grado_archivo && \Illuminate\Support\Facades\Storage::disk('local')->exists($formacion->titulo_ultimo_grado_archivo),
	    'cedula_ultimo_grado_archivo' => $formacion?->cedula_ultimo_grado_archivo && \Illuminate\Support\Facades\Storage::disk('local')->exists($formacion->cedula_ultimo_grado_archivo),
            'titulo_origen_archivo_url' => route('registro.documentos.ver', 'titulo_origen_archivo'),
            'cedula_origen_archivo_url' => route('registro.documentos.ver', 'cedula_origen_archivo'),
            'titulo_ultimo_grado_archivo_url' => route('registro.documentos.ver', 'titulo_ultimo_grado_archivo'),
            'cedula_ultimo_grado_archivo_url' => route('registro.documentos.ver', 'cedula_ultimo_grado_archivo')
	]) }}"

></div>

            <!-- BOTONES DE CAPTURA -->
            <div class="botones">
                <a href="{{ route((isset($solicitud) && $solicitud?->tipo_tramite === 'refrendo') ? 'registro.refrendo' : 'registro.certificacion') }}"
                   class="boton">
                    Anterior
                </a>

                @if($formacionBloqueada)
                    <a href="{{ route('registro.documentos') }}"
                       class="boton">
                        Siguiente
                    </a>
                @else
                    <button type="submit" class="boton">
                        Guardar y revisar
                    </button>
                @endif
            </div>
        </form>

        @if(!$formacionBloqueada && $formacion)
            <form
                method="POST"
                action="{{ route('registro.formacion.autorizar') }}"
                class="formulario"
                style="margin-top: 20px;"
            >
                @csrf

                <!-- Confirmación y autorización en una misma fila -->
                <div class="fila-autorizacion-formacion">

                    <div class="confirmacion-formacion">
                        <label class="etiqueta-confirmacion-formacion">
                            <input
                                type="checkbox"
                                name="confirmacion_formacion"
                                value="1"
                                id="confirmacion_formacion"
                                required
                                onchange="document.getElementById('boton-autorizar-formacion').disabled = !this.checked;"
                            >

                            <span>
                                Confirmo que he revisado mi formación académica
                                y los documentos adjuntos, y que la información
                                proporcionada es correcta.
                            </span>
                        </label>
                    </div>

                    <button
                        type="submit"
                        class="boton"
                        id="boton-autorizar-formacion"
                        disabled
                    >
                        Autorizar y continuar
                    </button>

                </div>
            </form>
        @endif

    </main>
</div>

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



<script id="proteccion-cambios-formacion-1183">
document.addEventListener('DOMContentLoaded', function () {
    const contenedor = document.getElementById('vue-formacion-academica');
    const confirmacion = document.getElementById('confirmacion_formacion');
    const boton = document.getElementById('boton-autorizar-formacion');

    if (!contenedor || !confirmacion || !boton) {
        return;
    }

    const formularioCaptura = contenedor.closest('form');

    if (!formularioCaptura) {
        return;
    }

    const aviso = document.createElement('p');
    aviso.id = 'aviso-cambios-formacion-1183';
    aviso.textContent =
        'Hay cambios sin guardar. Selecciona "Guardar y revisar" antes de autorizar.';
    aviso.style.color = '#a33';
    aviso.style.fontSize = '12px';
    aviso.style.marginTop = '10px';
    aviso.hidden = true;

    confirmacion.closest('.confirmacion-formacion').appendChild(aviso);

    // Esperar a que Vue termine de montar los controles.
    const iniciar = function () {
        const campos = Array.from(
            formularioCaptura.querySelectorAll('input, select, textarea')
        ).filter(function (campo) {
            return campo.name && campo.name !== '_token';
        });

        if (!campos.some(function (campo) {
            return campo.name === 'titulo_ultimo_grado';
        })) {
            return false;
        }

        const valoresIniciales = new Map();

        campos.forEach(function (campo) {
            valoresIniciales.set(
                campo,
                campo.type === 'file' ? '' : campo.value
            );
        });

        let cambiosPendientes = false;

        function revisarCambios() {
            cambiosPendientes = campos.some(function (campo) {
                if (campo.type === 'file') {
                    return campo.files && campo.files.length > 0;
                }

                return campo.value !== valoresIniciales.get(campo);
            });

            aviso.hidden = !cambiosPendientes;

            if (cambiosPendientes) {
                confirmacion.checked = false;
                confirmacion.disabled = true;
                boton.disabled = true;
            } else {
                confirmacion.disabled = false;
                boton.disabled = !confirmacion.checked;
            }
        }

        formularioCaptura.addEventListener('change', revisarCambios);
        formularioCaptura.addEventListener('input', revisarCambios);

        confirmacion.addEventListener('change', revisarCambios);

        revisarCambios();
        return true;
    };

    if (iniciar()) {
        return;
    }

    // Vue puede montar después de DOMContentLoaded.
    const observador = new MutationObserver(function () {
        if (iniciar()) {
            observador.disconnect();
        }
    });

    observador.observe(contenedor, {
        childList: true,
        subtree: true
    });
});
</script>

</body>
</html>

