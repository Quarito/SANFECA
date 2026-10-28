
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Datos Personales | ANFECA</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link rel="stylesheet" href="{{ asset('css/certificacion.css') }}">
    <link rel="stylesheet" href="{{ asset('css/datos-personales-vue.css') }}">
</head>
<body @if(request()->is("preview/*")) class="modo-preview" @endif>
<div class="pagina-anfeca">

    <header class="encabezado">
        <div class="logos">
            <img src="{{ asset('images/logo-anfeca.png') }}" alt="ANFECA">
            <img src="{{ asset('images/logo-certificacion.png') }}" alt="Certificación">
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
            Usuario: {{ auth()->user()?->name ?? 'Nuevo registro' }}
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
                <strong>Revisa los siguientes datos:</strong>
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('registro.datos.guardar') }}"
            class="formulario"
        
@if(request()->is("preview/*"))
    onsubmit="return false;"
@endif
>
            @csrf

            <div
                id="vue-datos-personales"
                data-datos="{{ json_encode([
                    'curp' => old('curp', $datos?->curp),
                    'rfc' => old('rfc', $datos?->rfc),
                    'nombres' => old('nombres', $datos?->nombres),
                    'apellido_paterno' => old('apellido_paterno', $datos?->apellido_paterno),
                    'apellido_materno' => old('apellido_materno', $datos?->apellido_materno),
                    'calle' => old('calle', $datos?->calle),
                    'numero_exterior' => old('numero_exterior', $datos?->numero_exterior),
                    'numero_interior' => old('numero_interior', $datos?->numero_interior),
                    'codigo_postal' => old('codigo_postal', $datos?->codigo_postal),
                    'entidad_federativa' => old('entidad_federativa', $datos?->entidad_federativa),
                    'colonia' => old('colonia', $datos?->colonia),
                    'municipio' => old('municipio', $datos?->municipio),
                    'correo_electronico' => old('correo_electronico', $datos?->correo_electronico ?? auth()->user()?->email),
                    'telefono_particular' => old('telefono_particular', $datos?->telefono_particular),
                    'telefono_celular' => old('telefono_celular', $datos?->telefono_celular)
                ]) }}"
            ></div>

            <div class="botones">
                <a href="{{ route('login') }}" class="boton">
                    Anterior
                </a>

                <button type="submit" class="boton">
                    Guardar y continuar
                </button>
            </div>
        </form>
    </main>
</div>

    <footer class="pie-pagina">
        <div class="origen">
            Hecho en México<br>
            D.R. © {{ date('Y') }}
        </div>

        <div class="legal">
            Esta página puede ser reproducida con fines no lucrativos,
            siempre y cuando no se mutile, se cite la fuente completa
            y su dirección electrónica. De otra forma requiere permiso
            previo por escrito de la institución.
            <strong>AVISO DE PRIVACIDAD.</strong>
        </div>
    </footer>
</div>
</body>
</html>
