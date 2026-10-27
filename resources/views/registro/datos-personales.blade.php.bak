<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Datos personales | ANFECA</title>

    <link rel="stylesheet"
          href="{{ asset('css/datos-personales.css') }}">
</head>

<body>
<div class="pagina">

    <header class="encabezado">
        <div class="logos">
            <img src="{{ asset('images/logo-anfeca.png') }}"
                 alt="ANFECA">
            <img src="{{ asset('images/logo-certificacion.png') }}"
                 alt="Certificación">
        </div>

        <h1>REGISTRO AL SISTEMA DE<br>
            CERTIFICACIÓN DE LA ANFECA</h1>

        <div class="coordinacion">
            Coordinación Nacional de<br>
            Certificación Académica
        </div>
    </header>

    <div class="usuario-barra">
        <span class="usuario-icono">👤</span>
        <span>Usuario:
            {{ auth()->user()?->name ?? 'Nuevo registro' }}
        </span>
    </div>

    <main>
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

        <form method="POST"
              action="{{ route('registro.datos.guardar') }}">
            @csrf

            <div class="secciones">

                <!-- DATOS PERSONALES -->
                <section class="seccion">
                    <h2>Datos Personales</h2>

                    <div class="dos-columnas">
                        <div class="campo">
                            <label for="curp">CURP*</label>
                            <input id="curp" name="curp"
                                   maxlength="18" required
                                   value="{{ old('curp', $datos?->curp) }}">
                        </div>

                        <div class="campo">
                            <label for="nombres">Nombre(s)*</label>
                            <input id="nombres" name="nombres"
                                   required
                                   value="{{ old('nombres', $datos?->nombres) }}">
                        </div>

                        <div class="campo">
                            <label for="rfc">RFC (homoclave)*</label>
                            <input id="rfc" name="rfc"
                                   maxlength="13" required
                                   value="{{ old('rfc', $datos?->rfc) }}">
                        </div>

                        <div class="campo">
                            <label for="apellido_paterno">
                                Apellido Paterno*
                            </label>
                            <input id="apellido_paterno"
                                   name="apellido_paterno" required
                                   value="{{ old('apellido_paterno', $datos?->apellido_paterno) }}">
                        </div>

                     <!-- todo este bloque esta comentado  <div class="campo">
                           <label for="cedula_profesional">
                             Cédula Profesional
                          </label>
                            <input id="cedula_profesional"
                                   name="cedula_profesional"
                                   value="{{ old('cedula_profesional', $datos?->cedula_profesional) }}">
                       </div> 
-->

                        <div class="campo">
                            <label for="apellido_materno">
                                Apellido Materno
                            </label>
                            <input id="apellido_materno"
                                   name="apellido_materno"
                                   value="{{ old('apellido_materno', $datos?->apellido_materno) }}">
                        </div>
                    
             
                        
                        <div class="campo">
                            <label for="password">Contraseña*</label>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                minlength="12"
                                autocomplete="new-password"
                                required
                             >
                         </div>

                         <div class="campo">
                            <label for="password_confirmation">
                                Confirmación contraseña*
                            </label>
                            <input
                                 type="password"
                                 id="password_confirmation"
                                 name="password_confirmation"
                                 minlength="12"
                                 autocomplete="new-password"
                                 required
                             >
                          </div>
                         </div>
                    </p>
                </section>

                <!-- DOMICILIO -->
                <section class="seccion">
                    <h2>Domicilio</h2>

                    <div class="dos-columnas">
                        <div class="campo">
                            <label for="calle">Calle*</label>
                            <input id="calle" name="calle" required
                                   value="{{ old('calle', $datos?->calle) }}">
                        </div>

                        <div class="campo">
                            <label for="entidad_federativa">
                                Entidad Federativa*
                            </label>
                            <input id="entidad_federativa"
                                   name="entidad_federativa" required
                                   value="{{ old('entidad_federativa', $datos?->entidad_federativa) }}">
                        </div>

                        <div class="campo">
                            <label for="numero_exterior">
                                Núm. Exterior*
                            </label>
                            <input id="numero_exterior"
                                   name="numero_exterior" required
                                   value="{{ old('numero_exterior', $datos?->numero_exterior) }}">
                        </div>

                        <div class="campo">
                            <label for="colonia">Colonia*</label>
                            <input id="colonia" name="colonia" required
                                   value="{{ old('colonia', $datos?->colonia) }}">
                        </div>

                        <div class="campo">
                            <label for="numero_interior">
                                Núm. Interior
                            </label>
                            <input id="numero_interior"
                                   name="numero_interior"
                                   value="{{ old('numero_interior', $datos?->numero_interior) }}">
                        </div>

                        <div class="campo">
                            <label for="municipio">
                                Alcaldía/Municipio*
                            </label>
                            <input id="municipio"
                                   name="municipio" required
                                   value="{{ old('municipio', $datos?->municipio) }}">
                        </div>

                        <div class="campo">
                            <label for="codigo_postal">
                                Código Postal*
                            </label>
                            <input id="codigo_postal"
                                   name="codigo_postal"
                                   maxlength="5" required
                                   value="{{ old('codigo_postal', $datos?->codigo_postal) }}">
                        </div>
                    </div>
                </section>

                <!-- COMUNICACIÓN -->
                <section class="seccion">
                    <h2>Comunicación</h2>

                    <div class="campo">
                        <label for="correo_electronico">
                            Correo Electrónico*
                        </label>
                        <input type="email"
                               id="correo_electronico"
                               name="correo_electronico" required
                               value="{{ old('correo_electronico', $datos?->correo_electronico ?? auth()->user()?->email) }}">
                    </div>

                    <div class="campo">
                        <label for="telefono_particular">
                            Teléfono Particular
                        </label>
                        <input type="tel"
                               id="telefono_particular"
                               name="telefono_particular"
                               value="{{ old('telefono_particular', $datos?->telefono_particular) }}">
                    </div>

                    <div class="campo">
                        <label for="telefono_celular">
                            Teléfono Celular
                        </label>
                        <input type="tel"
                               id="telefono_celular"
                               name="telefono_celular"
                               value="{{ old('telefono_celular', $datos?->telefono_celular) }}">
                    </div>
                </section>

            </div>

            <div class="acciones">
                <a href="{{ route('login') }}" class="boton">
                    Anterior
                </a>

                <button type="submit" class="boton">
                    Guardar y continuar
                </button>
            </div>
        </form>
    </main>

    <footer>
        <div class="pie-origen">
            Hecho en México<br>
            D.R. © {{ date('Y') }}
        </div>

        <div class="pie-legal">
            Esta página puede ser reproducida con fines no
            lucrativos, siempre y cuando no se mutile,
            se cite la fuente completa y su dirección electrónica.
            De otra forma requiere permiso previo por escrito
            de la institución.
            <strong>AVISO DE PRIVACIDAD.</strong>
        </div>
    </footer>

</div>
</body>
</html>

