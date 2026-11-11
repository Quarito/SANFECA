
<script setup>
import { reactive, computed } from 'vue';

const props = defineProps({
    seleccion: {
        type: Object,
        default: () => ({})
    },
    antecedentes: {
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

const certificacionCoincide = computed(() => {
    const certificacionGuardada =
        props.antecedentes.certificacion_consultada ?? '';

    return certificacionGuardada !== '' &&
        formulario.certificacion === certificacionGuardada;
});

const tiposPersonal = [
    'Tiempo Completo',
    'Medio Tiempo',
    'PAES'
];

const areasCertificacion = [
    'Contaduría Pública',
    'Administración',
    'Informática Administrativa',
    'Negocios Internacionales',
    'Disciplinas vinculadas al ámbito económico-administrativo',
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

        <!-- ANTECEDENTES HISTÓRICOS DE CERTIFICACIÓN -->
        <section
            class="antecedentes-certificacion-vue"
            aria-labelledby="titulo-antecedentes"
        >
            <h2 id="titulo-antecedentes">
                Antecedentes históricos de certificación
            </h2>

            <p v-if="!formulario.certificacion">
                Seleccione una certificación para consultar sus antecedentes.
            </p>

            <p v-else-if="!certificacionCoincide">
                Para consultar los antecedentes de esta certificación,
                primero debe guardar su selección.
            </p>

            <template v-else>
                <div
                    v-if="antecedentes.historial?.length"
                    class="lista-antecedentes-vue"
                >
                    <article
                        v-for="(registro, indice) in antecedentes.historial"
                        :key="indice"
                        class="registro-antecedente-vue"
                    >
                        <h3>{{ registro.certificacion }}</h3>

                        <p v-if="registro.primera_vez">
                            <strong>Primera certificación:</strong>
                            {{ registro.primera_vez }}
                        </p>

                        <p
                            v-for="refrendo in registro.refrendos || []"
                            :key="refrendo.numero"
                        >
                            <strong>
                                Refrendo {{ refrendo.numero }}:
                            </strong>
                            {{ refrendo.anio }}
                        </p>
                    </article>
                </div>

                <p v-else>
                    No se encontraron antecedentes históricos
                    verificados vinculados a esta certificación.
                </p>
            </template>
        </section>
    </div>
</template>
