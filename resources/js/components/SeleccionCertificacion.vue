
<script setup>
import { reactive } from 'vue';

const props = defineProps({
    seleccion: {
        type: Object,
        default: () => ({})
    }
});

const formulario = reactive({
    certificacion: props.seleccion.certificacion ?? '',
    tipo_personal: props.seleccion.tipo_personal ?? '',
    area_certificacion: props.seleccion.area_certificacion ?? ''
});

const certificaciones = [
    'ANFECA',
    'CACSLA',
    'CACECA'
];

const tiposPersonal = [
    'Tiempo Completo',
    'Medio Tiempo',
    'PAES'
];

const areasCertificacion = [
    'Contaduría',
    'Administración',
    'Informática',
    'Negocios Internacionales',
    'Disciplinas vinculadas al ámbito...'
];
</script>

<template>
    <div class="certificacion-vue">
        <div class="columnas-certificacion-vue">

            <!-- COLUMNA 1 -->
            <section class="seccion-certificacion-vue">
                <h2>Certificación</h2>

                <p class="descripcion-certificacion">
                    Seleccione la Certificación Académica
                    a la que se aspira
                </p>

                <div class="opciones-certificacion">
                    <label
                        v-for="opcion in certificaciones"
                        :key="opcion"
                        class="opcion-certificacion"
                    >
                        <input
                            v-model="formulario.certificacion"
                            type="radio"
                            name="certificacion"
                            :value="opcion"
                            required
                        >

                        <span>{{ opcion }}</span>
                    </label>
                </div>
            </section>

            <!-- COLUMNA 2 -->
            <section class="seccion-certificacion-vue">
                <h2>Personal Académico</h2>

                <div class="campo-certificacion">
                    <label for="tipo_personal">
                        Tipo de Personal Académico
                    </label>

                    <select
                        id="tipo_personal"
                        v-model="formulario.tipo_personal"
                        name="tipo_personal"
                        required
                    >
                        <option value="">
                            Seleccione una opción
                        </option>

                        <option
                            v-for="tipo in tiposPersonal"
                            :key="tipo"
                            :value="tipo"
                        >
                            {{ tipo }}
                        </option>
                    </select>
                </div>

                <div class="campo-certificacion">
                    <label for="area_certificacion">
                        Área de Certificación Personal
                    </label>

                    <select
                        id="area_certificacion"
                        v-model="formulario.area_certificacion"
                        name="area_certificacion"
                        required
                    >
                        <option value="">
                            Seleccione una opción
                        </option>

                        <option
                            v-for="area in areasCertificacion"
                            :key="area"
                            :value="area"
                        >
                            {{ area }}
                        </option>
                    </select>
                </div>
            </section>

        </div>
    </div>
</template>
