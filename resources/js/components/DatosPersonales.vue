
<script setup>
import { reactive } from 'vue';

const props = defineProps({
    datos: {
        type: Object,
        default: () => ({})
    }
});

const campos = reactive({
    curp: props.datos.curp ?? '',
    rfc: props.datos.rfc ?? '',
    nombres: props.datos.nombres ?? '',
    apellido_paterno: props.datos.apellido_paterno ?? '',
    apellido_materno: props.datos.apellido_materno ?? '',
    calle: props.datos.calle ?? '',
    numero_exterior: props.datos.numero_exterior ?? '',
    numero_interior: props.datos.numero_interior ?? '',
    codigo_postal: props.datos.codigo_postal ?? '',
    entidad_federativa: props.datos.entidad_federativa ?? '',
    colonia: props.datos.colonia ?? '',
    municipio: props.datos.municipio ?? '',
    correo_electronico: props.datos.correo_electronico ?? '',
    telefono_particular: props.datos.telefono_particular ?? '',
    telefono_celular: props.datos.telefono_celular ?? ''
});

const personales = [
    { name: 'curp', label: 'CURP', required: true, maxlength: 18 },
    { name: 'nombres', label: 'Nombre(s)', required: true },
    { name: 'rfc', label: 'RFC (homoclave)', required: true, maxlength: 13 },
    { name: 'apellido_paterno', label: 'Apellido Paterno', required: true },
    { name: 'apellido_materno', label: 'Apellido Materno' }
];

const domicilio = [
    { name: 'calle', label: 'Calle', required: true },
    { name: 'entidad_federativa', label: 'Entidad Federativa', required: true },
    { name: 'numero_exterior', label: 'Núm. Exterior', required: true },
    { name: 'colonia', label: 'Colonia', required: true },
    { name: 'numero_interior', label: 'Núm. Interior' },
    { name: 'municipio', label: 'Alcaldía/Municipio', required: true },
    { name: 'codigo_postal', label: 'Código Postal', required: true, maxlength: 5 }
];

const comunicacion = [
    { name: 'correo_electronico', label: 'Correo Electrónico', type: 'email', required: true },
    { name: 'telefono_particular', label: 'Teléfono Particular', type: 'tel' },
    { name: 'telefono_celular', label: 'Teléfono Celular', type: 'tel' }
];
</script>

<template>
    <div class="datos-vue">
        <div class="secciones-datos">
            <section class="seccion-datos">
                <h2>Datos Personales</h2>

                <div class="dos-columnas-datos">
                    <div
                        v-for="campo in personales"
                        :key="campo.name"
                        class="campo-datos"
                    >
                        <label :for="campo.name">
                            {{ campo.label }}{{ campo.required ? '*' : '' }}
                        </label>

                        <input
                            :id="campo.name"
                            :name="campo.name"
                            :type="campo.type || 'text'"
                            v-model="campos[campo.name]"
                            :maxlength="campo.maxlength"
                            :required="campo.required || false"
                            :autocomplete="'off'"
                        >
                    </div>

                    <div class="campo-datos">
                        <label for="password">Contraseña*</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            minlength="12"
                            autocomplete="new-password"
                            required
                        >
                    </div>

                    <div class="campo-datos">
                        <label for="password_confirmation">
                            Confirmación contraseña*
                        </label>
                        <input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            minlength="12"
                            autocomplete="new-password"
                            required
                        >
                    </div>
                </div>
            </section>

            <section class="seccion-datos">
                <h2>Domicilio</h2>

                <div class="dos-columnas-datos">
                    <div
                        v-for="campo in domicilio"
                        :key="campo.name"
                        class="campo-datos"
                    >
                        <label :for="campo.name">
                            {{ campo.label }}{{ campo.required ? '*' : '' }}
                        </label>

                        <input
                            :id="campo.name"
                            :name="campo.name"
                            v-model="campos[campo.name]"
                            :maxlength="campo.maxlength"
                            :required="campo.required || false"
                            type="text"
                        >
                    </div>
                </div>
            </section>

            <section class="seccion-datos">
                <h2>Comunicación</h2>

                <div class="dos-columnas-datos">
                    <div
                        v-for="campo in comunicacion"
                        :key="campo.name"
                        class="campo-datos"
                    >
                        <label :for="campo.name">
                            {{ campo.label }}{{ campo.required ? '*' : '' }}
                        </label>

                        <input
                            :id="campo.name"
                            :name="campo.name"
                            :type="campo.type || 'text'"
                            v-model="campos[campo.name]"
                            :required="campo.required || false"
                        >
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
