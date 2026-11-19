<script setup>
import { reactive, ref } from 'vue';

const props = defineProps({
    datos: {
        type: Object,
        required: true
    },
    accion: {
        type: String,
        required: true
    },
    csrf: {
        type: String,
        required: true
    },
    editarInicialmente: {
        type: Boolean,
        default: false
    },

	accionAutorizar: {
    type: String,
    required: true
},
autorizadoInicialmente: {
    type: Boolean,
    default: false
},

	urlSiguiente: {
	    type: String,
	    required: true
	},

});

const editando = ref(props.editarInicialmente && !props.autorizadoInicialmente);
const confirmacionDatos = ref(false);
const campos = reactive({ ...props.datos });

// Identificar los campos telefónicos.
function esTelefono(nombre) {
    return [
        'telefono_celular',
        'telefono_particular'
    ].includes(nombre);
}

// Permitir únicamente números y un máximo de 10 dígitos.
function validarTelefono(nombre, event) {
    const valor = event.target.value
        .replace(/\D/g, '')
        .slice(0, 10);

    campos[nombre] = valor;
    event.target.value = valor;
}

// Campos de identidad protegidos después de autorizar.
const camposIdentidad = [
    'curp',
    'rfc',
    'nombres',
    'apellido_paterno',
    'apellido_materno',
    'genero',
    'cedula_profesional',
    'correo_electronico'
];

// Determinar si un campo puede modificarse.
function puedeEditar(nombre) {
    if (props.autorizadoInicialmente) {
        return false;
    }

    return [
        'calle',
        'numero_exterior',
        'numero_interior',
        'colonia',
        'municipio',
        'entidad_federativa',
        'codigo_postal',
        'telefono_celular',
        'telefono_particular'
    ].includes(nombre);
}

const grupos = [
    {
        titulo: 'Datos personales',
        campos: [
            ['curp', 'CURP'],
            ['rfc', 'RFC'],
            ['nombres', 'Nombre(s)'],
            ['apellido_paterno', 'Apellido paterno'],
            ['apellido_materno', 'Apellido materno'],
            ['genero', 'Género']
        ]
    },
    {
        titulo: 'Domicilio',
        campos: [
            ['calle', 'Calle'],
            ['numero_exterior', 'Número exterior'],
            ['numero_interior', 'Número interior'],
            ['colonia', 'Colonia'],
            ['municipio', 'Alcaldía/Municipio'],
            ['entidad_federativa', 'Entidad federativa'],
            ['codigo_postal', 'Código postal']
        ]
    },
    {
        titulo: 'Información de contacto',
        campos: [
            ['telefono_celular', 'Teléfono celular'],
            ['telefono_particular', 'Teléfono particular'],
            ['correo_electronico', 'Correo electrónico']
        ]
    }
];

const obligatorios = [
    'curp', 'rfc', 'nombres', 'apellido_paterno',
    'genero',
    'calle', 'numero_exterior', 'colonia',
    'municipio', 'entidad_federativa',
    'codigo_postal', 'correo_electronico'
];

function cancelar() {
    Object.assign(campos, props.datos);
    editando.value = false;
}
</script>

<template>
    <div>
        <form :action="accion" method="POST">
            <input type="hidden" name="_token" :value="csrf">
            <input type="hidden" name="_method" value="PUT">
<!-- Enviar todos los campos aunque estén en modo consulta -->
<template v-if="!editando">
    <input
        v-for="(valor, nombre) in campos"
        :key="nombre"
        type="hidden"
        :name="nombre"
        :value="valor ?? ''"
    >
</template>

<!-- Conservar el correo en el formulario cuando está protegido -->
<input
    v-if="editando && autorizadoInicialmente"
    type="hidden"
    name="correo_electronico"
    :value="datos.correo_electronico ?? ''"
>
            <div class="panel-etapas">
                <section
                    v-for="grupo in grupos"
                    :key="grupo.titulo"
                    class="tarjeta-etapa"
                >
                    <h3>{{ grupo.titulo }}</h3>

                    <div
                        v-for="[nombre, etiqueta] in grupo.campos"
                        :key="nombre"
                        class="campo-actualizacion"
                    >
                        <label :for="'actualizar-' + nombre">
                            {{ etiqueta }}
                        </label>


            <select
                v-if="editando && puedeEditar(nombre) && nombre === 'genero'"
                :id="'actualizar-' + nombre"
                :name="nombre"
                v-model="campos[nombre]"
                required
            >
                <option value="" disabled>Seleccione...</option>
                <option value="Femenino">Femenino</option>
                <option value="Masculino">Masculino</option>
            </select>

            <input
                v-else-if="editando && puedeEditar(nombre)"
                :id="'actualizar-' + nombre"
                :name="nombre"
                v-model="campos[nombre]"
                :required="obligatorios.includes(nombre)"
                :maxlength="
                    esTelefono(nombre) ? 10 :
                    nombre === 'curp' ? 18 :
                    nombre === 'codigo_postal' ? 5 :
                    undefined
                "
                :type="
                    esTelefono(nombre) ? 'tel' :
                    nombre === 'correo_electronico' ? 'email' :
                    'text'
                "
                :inputmode="esTelefono(nombre) ? 'numeric' : undefined"
                @input="esTelefono(nombre) && validarTelefono(nombre, $event)"
            >

            <span v-else>
                {{ campos[nombre] || 'No registrado' }}
            </span>

                    </div>
                </section>
            </div>

            <div class="fila-autorizacion"
                 style="display:flex; justify-content:flex-end; width:100%; margin-top:16px;">

                <div class="panel-acciones"
                     style="display:flex; justify-content:flex-end; align-items:center; gap:10px; width:100%;">

                    <template v-if="editando">

                        <button
                            type="button"
                            class="boton"
                            @click="cancelar"
                        >
                            Cancelar
                        </button>

                        <button
                            type="submit"
                            class="boton"
                        >
                            Guardar cambios
                        </button>

                    </template>

                </div>
            </div>
        </form>
	<!-- Autorización de datos personales -->
<section v-if="!editando" class="seccion-autorizacion">

    <template v-if="autorizadoInicialmente">

    
<div class="anfeca-acciones-autorizacion">

    <p class="mensaje-autorizado">
        ✓ Tus datos personales se encuentran autorizados.
    </p>

    <div class="anfeca-botones">


        <a
            :href="urlSiguiente"
            class="boton boton-siguiente"
        >
            Siguiente
        </a>
    </div>

</div>


</template>

    <form v-else :action="accionAutorizar" method="POST">

        <input type="hidden" name="_token" :value="csrf">

        <div class="fila-autorizacion">
        <label class="confirmacion-datos">
            <input
                type="checkbox"
                name="confirmacion_datos"
                value="1"
                v-model="confirmacionDatos"
                required
            >

            Confirmo que he revisado mis datos personales
            y que la información proporcionada es correcta.
        </label>

        <div class="panel-acciones">
            <button
                type="button"
                class="boton boton-modificar"
                @click="editando = true"
            >
                Modificar datos
            </button>
            <button
                type="submit"
                class="boton"
                :disabled="!confirmacionDatos"
            >
                Autorizar y continuar
            </button>
        </div>
        </div>

    </form>

</section>


</div>
</template>

<style scoped>
.campo-actualizacion {
    margin-bottom: 12px;
}

.campo-actualizacion label {
    display: block;
    font-weight: 600;
    margin-bottom: 5px;
}

.campo-actualizacion input {
    width: 100%;
    box-sizing: border-box;
    padding: 8px;
    border: 1px solid #c9c9c9;
    border-radius: 5px;
}

.campo-actualizacion span {
    display: block;
    overflow-wrap: anywhere;
}

/* Distribución de las tres tarjetas */
.panel-etapas {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
    width: 100%;
    align-items: start;
}

/* Cada tarjeta aprovecha su columna */
.tarjeta-etapa {
    min-width: 0;
    width: 100%;
    box-sizing: border-box;
    padding: 20px;
}

/* Evitar que el texto se corte innecesariamente */
.campo-actualizacion {
    overflow-wrap: break-word;
    margin-bottom: 15px;
}

.campo-actualizacion input {
    max-width: 100%;
}

/* Adaptación para pantallas pequeñas */
@media (max-width: 850px) {
    .panel-etapas {
        grid-template-columns: 1fr;
    }
}

.seccion-autorizacion {
    margin-top: 25px;
    padding: 20px;
    border: 1px solid #dddddd;
    border-radius: 6px;
    background: #fafafa;
}

.confirmacion-datos {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    font-size: 14px;
    line-height: 1.5;
    cursor: pointer;
}

.confirmacion-datos input {
    margin-top: 4px;
    flex-shrink: 0;
}

.mensaje-autorizado {
    color: #237a42;
    font-weight: 600;
    margin: 0;
}

.seccion-autorizacion button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* compactacion-mis-datos-97168 */

/* Distribución de las tarjetas */
.panel-etapas {
    gap: 12px !important;
    margin-bottom: 12px !important;
}

/* Tarjetas de información */
.tarjeta-etapa {
    padding: 12px 16px !important;
}

/* Campos de consulta */
.campo-actualizacion {
    margin-bottom: 8px !important;
    gap: 3px !important;
    font-size: 12px !important;
}

.campo-actualizacion label,
.campo-actualizacion strong,
.campo-actualizacion span {
    font-size: 12px !important;
    line-height: 1.35 !important;
}

/* Campos editables */
.campo-actualizacion input,
.campo-actualizacion select {
    min-height: 32px !important;
    font-size: 12px !important;
}

/* Botones */
.panel-acciones {
    margin: 10px 0 !important;
    gap: 8px !important;
}

.panel-acciones .boton {
    font-size: 12px !important;
    padding: 8px 16px !important;
    min-height: 32px !important;
}

/* Recuadro de autorización */
.seccion-autorizacion {
    padding: 12px 16px !important;
    margin-top: 12px !important;
}

/* Mensaje de autorización */
.mensaje-autorizado {
    font-size: 12px !important;
    margin: 0 0 8px !important;
    line-height: 1.4 !important;
}

/* Confirmación */
.confirmacion-datos {
    font-size: 12px !important;
    line-height: 1.4 !important;
}

@media (max-width: 768px) {
    .campo-actualizacion input,
    .campo-actualizacion select {
        font-size: 16px !important;
    }
}


/* ANFECA - Acciones de datos personales alineadas */

.anfeca-acciones-autorizacion {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    width: 100%;
}

.anfeca-acciones-autorizacion .mensaje-autorizado {
    flex: 1;
    margin: 0 !important;
    line-height: 1.25 !important;
}

.anfeca-botones {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    flex-shrink: 0;
}

.anfeca-botones .boton {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 125px;
    min-height: 34px;
    padding: 7px 12px;
    margin: 0 !important;
    border-radius: 6px;
    font-size: 12px;
    text-decoration: none;
}

.anfeca-botones .boton-modificar {
    background: #ffffff;
    color: #a00012;
    border: 1px solid #a00012;
}

.anfeca-botones .boton-siguiente {
    background: #a00012;
    color: #ffffff;
    border: 1px solid #a00012;
}

@media (max-width: 767px) {
    .anfeca-acciones-autorizacion {
        flex-direction: column;
        align-items: stretch;
    }

    .anfeca-botones {
        flex-wrap: wrap;
        width: 100%;
    }

    .anfeca-botones .boton {
        flex: 1;
    }
}


/* ANFECA COMPACTO V4 - DATOS PERSONALES */

@media (min-width: 1024px) {

    .panel-etapas {
        gap: 10px !important;
        margin-bottom: 8px !important;
    }

    .tarjeta-etapa {
        padding: 10px 14px !important;
    }

    .tarjeta-etapa h3 {
        margin: 0 0 8px !important;
        line-height: 1.15 !important;
    }

    .campo-actualizacion {
        margin-bottom: 5px !important;
        line-height: 1.2 !important;
    }

    .campo-actualizacion label {
        margin-bottom: 2px !important;
        line-height: 1.15 !important;
    }

    .campo-actualizacion span {
        line-height: 1.2 !important;
    }

    .seccion-autorizacion {
        margin-top: 8px !important;
        padding: 9px 12px !important;
    }

    .mensaje-autorizado {
        margin: 0 !important;
        line-height: 1.2 !important;
    }
}


/* INICIO ANFECA DATOS COMPACTOS V5 */

@media screen and (min-width: 1024px) {

    .panel-etapas {
        gap: 10px !important;
        margin-bottom: 6px !important;
        align-items: start;
    }

    .tarjeta-etapa {
        padding: 8px 12px !important;
    }

    .tarjeta-etapa h3 {
        margin: 0 0 6px !important;
        padding: 0 !important;
        font-size: 14px !important;
        line-height: 1.15 !important;
    }

    .campo-actualizacion {
        margin: 0 0 4px !important;
        padding: 0 !important;
        font-size: 12px !important;
        line-height: 1.15 !important;
    }

    .campo-actualizacion label {
        display: block;
        margin: 0 0 1px !important;
        padding: 0 !important;
        font-size: 12px !important;
        line-height: 1.15 !important;
    }

    .campo-actualizacion span {
        display: block;
        margin: 0 !important;
        padding: 0 !important;
        font-size: 12px !important;
        line-height: 1.15 !important;
    }

    .campo-actualizacion input,
    .campo-actualizacion select {
        min-height: 30px !important;
        padding: 5px 7px !important;
        line-height: 1.2 !important;
    }

    .seccion-autorizacion {
        margin-top: 6px !important;
        padding: 8px 12px !important;
    }

    .mensaje-autorizado {
        margin: 0 !important;
        line-height: 1.2 !important;
    }

    .anfeca-acciones-autorizacion {
        gap: 8px !important;
    }

    .anfeca-botones {
        gap: 8px !important;
    }
}

/* FIN ANFECA DATOS COMPACTOS V5 */


/* Alineación horizontal de autorización */
.fila-autorizacion {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 16px;
}

.fila-autorizacion .panel-acciones {
    margin: 0;
    flex-shrink: 0;
}

@media (max-width: 768px) {
    .fila-autorizacion {
        flex-direction: column;
        align-items: stretch;
    }
}


/* Alineación de confirmación y botón */
.fila-autorizacion {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    width: 100%;
}

.fila-autorizacion .confirmacion-datos {
    flex: 1;
    min-width: 0;
    margin: 0;
}

.fila-autorizacion .panel-acciones {
    flex: 0 0 auto;
    margin: 0;
}

@media (max-width: 768px) {
    .fila-autorizacion {
        flex-direction: column;
        align-items: stretch;
    }
}

</style>
