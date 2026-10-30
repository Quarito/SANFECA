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

const editando = ref(props.editarInicialmente);
const confirmacionDatos = ref(false);
const campos = reactive({ ...props.datos });

// Campos de identidad protegidos después de autorizar.
const camposIdentidad = [
    'curp',
    'rfc',
    'nombres',
    'apellido_paterno',
    'apellido_materno',
    'cedula_profesional',
    'correo_electronico'
];

// Determinar si un campo puede modificarse.
function puedeEditar(nombre) {
    if (!props.autorizadoInicialmente) {
        return true;
    }

    return !camposIdentidad.includes(nombre);
}

const grupos = [
    {
        titulo: 'Datos personales',
        campos: [
            ['curp', 'CURP'],
            ['rfc', 'RFC'],
            ['nombres', 'Nombre(s)'],
            ['apellido_paterno', 'Apellido paterno'],
            ['apellido_materno', 'Apellido materno']
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

                        <input
                            v-if="editando && puedeEditar(nombre)"
                            :id="'actualizar-' + nombre"
                            :name="nombre"
                            v-model="campos[nombre]"
                            :required="obligatorios.includes(nombre)"
                            :maxlength="nombre === 'curp' ? 18 : nombre === 'codigo_postal' ? 5 : undefined"
                            :type="nombre === 'correo_electronico' ? 'email' : 'text'"
                        >

                        <span v-else>
                            {{ campos[nombre] || 'No registrado' }}
                        </span>
                    </div>
                </section>
            </div>

            <div class="panel-acciones">
                <template v-if="editando">
                    <button type="submit" class="boton">
                        Guardar cambios
                    </button>

                    <button
                        type="button"
                        class="boton"
                        @click="cancelar"
                    >
                        Cancelar
                    </button>
                </template>

	         <button
		    v-else
		    type="button"
		    class="boton"
		    @click="editando = true"
		>
		    Modificar datos
		</button>
            </div>
        </form>
	<!-- Autorización de datos personales -->
<section v-if="!editando" class="seccion-autorizacion">

    <template v-if="autorizadoInicialmente">

    <p class="mensaje-autorizado">
        ✓ Tus datos personales se encuentran autorizados.
    </p>

    <div class="panel-acciones">
        <a
            :href="urlSiguiente"
            class="boton"
        >
            Siguiente
        </a>
    </div>

</template>

    <form v-else :action="accionAutorizar" method="POST">

        <input type="hidden" name="_token" :value="csrf">

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
                type="submit"
                class="boton"
                :disabled="!confirmacionDatos"
            >
                Autorizar y continuar
            </button>
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
</style>
