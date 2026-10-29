<!DOCTYPE html>
<html lang="es">
<head>
@vite(['resources/css/app.css', 'resources/js/app.js'])
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Autorización de Datos | ANFECA</title>

    <link rel="stylesheet"
          href="{{ asset('css/certificacion.css') }}">

    <link rel="stylesheet"


          href="{{ asset('css/panel-anfeca.css') }}">

<style>
/* Barra de usuario y cierre de sesión */

.barra-usuario-acciones {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 15px;
}

.usuario-bienvenida {
    display: flex;
    align-items: center;
    gap: 6px;
}

.barra-usuario-acciones form {
    margin: 0;
}

.btn-cerrar-sesion {
    background: #a71930;
    color: #ffffff;
    border: none;
    border-radius: 5px;
    padding: 9px 15px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
}

.btn-cerrar-sesion:hover {
    background: #821326;
}
</style>

</head>

<style>
    /* Ajustes exclusivos de la actualización de datos */

    .cert-layout .cert-main {
        min-width: 0;
        width: 100%;
    }

    .panel-contenedor {
        width: 100%;
        max-width: none;
        min-width: 0;
    }

    #vue-actualizar-datos {
        width: 100%;
        min-width: 0;
    }

    #vue-actualizar-datos .panel-etapas {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 18px;
        width: 100%;
    }

    #vue-actualizar-datos .tarjeta-etapa {
        min-width: 0;
        box-sizing: border-box;
        padding: 18px;
    }

    #vue-actualizar-datos .campo-actualizacion {
        min-width: 0;
        overflow-wrap: anywhere;
    }

    #vue-actualizar-datos .campo-actualizacion input {
        width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }

    @media (max-width: 900px) {
        #vue-actualizar-datos .panel-etapas {
            grid-template-columns: 1fr;
        }
    }
</style>



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

    <div class="barra-usuario barra-usuario-acciones">

    <div class="usuario-bienvenida">
        <span class="icono-usuario">◉</span>
        <span>
            Bienvenido de nuevo:
            {{ auth()->user()->name }}
        </span>
    </div>

    <form method="POST" action="{{ route('logout') }}">
        @csrf

        <button type="submit" class="btn-cerrar-sesion">
            Cerrar sesión
        </button>
    </form>

</div>

    <div class="cert-layout">

    @include('components.sidebar-certificacion')

    <main class="contenido cert-main">
        <div class="panel-contenedor">

		@if(session('success'))
    <div class="mensaje-exito" role="status">
        {{ session('success') }}
    </div>
@endif

@if($errors->any())
    <div class="mensaje-error" role="alert">
        <strong>No fue posible guardar los cambios:</strong>
        <ul>
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

            <h2>Autorización de Datos</h2>

            <p class="panel-descripcion">
                Consulta la información personal
                registrada en el sistema.
            </p>

            
            
<div
    id="vue-actualizar-datos"
	 style="width: 100%; min-width: 0;"    
data-datos="{{ json_encode([
        'curp' => old('curp', $datos->curp),
        'rfc' => old('rfc', $datos->rfc),
        'nombres' => old('nombres', $datos->nombres),
        'apellido_paterno' => old('apellido_paterno', $datos->apellido_paterno),
        'apellido_materno' => old('apellido_materno', $datos->apellido_materno),
        'calle' => old('calle', $datos->calle),
        'numero_exterior' => old('numero_exterior', $datos->numero_exterior),
        'numero_interior' => old('numero_interior', $datos->numero_interior),
        'codigo_postal' => old('codigo_postal', $datos->codigo_postal),
        'entidad_federativa' => old('entidad_federativa', $datos->entidad_federativa),
        'colonia' => old('colonia', $datos->colonia),
        'municipio' => old('municipio', $datos->municipio),
        'correo_electronico' => old('correo_electronico', $datos->correo_electronico),
        'telefono_particular' => old('telefono_particular', $datos->telefono_particular),
        'telefono_celular' => old('telefono_celular', $datos->telefono_celular)
    ]) }}"
    data-accion="{{ route('mis-datos.update') }}"
    data-csrf="{{ csrf_token() }}"
    data-editar="{{ $errors->any() ? '1' : '0' }}"
	data-accion-autorizar="{{ route('mis-datos.autorizar') }}"
    data-autorizado="{{ $datos->datos_autorizados ? '1' : '0' }}"
></div>

           

        
                </div>
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
            y su dirección electrónica.
            De otra forma requiere permiso previo por escrito
            de la institución.

            <strong>AVISO DE PRIVACIDAD.</strong>
        </div>
    </footer>

</div>
</body>
</html>
