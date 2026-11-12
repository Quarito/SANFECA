<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    datos: {
        type: Object,
        default: () => ({})
    },
    automaticos: {
        type: Object,
        default: () => ({})
    }
});

const aplicaArticulo14 = ref(
    props.datos.aplica_articulo_14 === null ||
    props.datos.aplica_articulo_14 === undefined
        ? ''
        : String(Number(props.datos.aplica_articulo_14))
);

const incisoArticulo14 = ref(
    props.datos.inciso_articulo_14 ?? ''
);

const mostrarInciso = computed(
    () => aplicaArticulo14.value === '1'
);

const incisos = ['14.1', '14.2', '14.3', '14.4'];



const refrendoActual = computed(
    () => `Refrendo número: ${props.automaticos.refrendo_actual ?? ''}`
);
</script>


<template>
    <div class="formulario formulario-refrendo">

        <!-- Selección de artículo 14 -->
        <div class="campo">
            <label for="aplica_articulo_14">
                ¿Corresponde a refrendo por artículo 14? *
            </label>

            <select
                id="aplica_articulo_14"
                v-model="aplicaArticulo14"
                name="aplica_articulo_14"
                required
            >
                <option value="">Seleccione una opción</option>
                <option value="1">Sí</option>
                <option value="0">No</option>
            </select>
        </div>

        <!-- Inciso, únicamente cuando corresponde -->
        <div v-if="mostrarInciso" class="campo">
            <label for="inciso_articulo_14">
                Inciso del artículo 14 *
            </label>

            <select
                id="inciso_articulo_14"
                v-model="incisoArticulo14"
                name="inciso_articulo_14"
                required
            >
                <option value="">Seleccione un inciso</option>

                <option
                    v-for="inciso in incisos"
                    :key="inciso"
                    :value="inciso"
                >
                    {{ inciso }}
                </option>
            </select>
        </div>

        <!-- Próximo refrendo calculado automáticamente -->
        <div class="campo">
            <label for="refrendo_actual">
                Próximo refrendo
            </label>

            <input
                id="refrendo_actual"
                type="text"
                :value="refrendoActual"
                readonly
            >
        </div>

        <!-- Mensaje para quienes no aplican al artículo 14 -->
        <div
            v-if="aplicaArticulo14 === '0'"
            class="mensaje-no-articulo-14"
            role="status"
        >
            Usted no está por Art. 14
        </div>

    </div>
</template>


<style scoped>
.formulario-refrendo {
    width: 100%;
    max-width: 540px;
    margin: 28px auto 0;
}

.formulario-refrendo .campo {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-bottom: 24px;
}

.formulario-refrendo .campo label {
    display: block;
    width: 100%;
    margin-bottom: 9px;
    text-align: center;
    font-size: 14px;
    font-weight: 600;
    color: #252525;
}

.formulario-refrendo .campo input,
.formulario-refrendo .campo select {
    box-sizing: border-box;
    display: block;
    width: 100%;
    min-height: 43px;
    padding: 10px 14px;
    text-align: center;
    text-align-last: center;
    font-size: 15px;
    border: 1px solid #999;
    border-radius: 7px;
    background-color: #fff;
}

.formulario-refrendo .campo input[readonly] {
    background-color: #f5f5f5;
    font-weight: 600;
    color: #333;
    cursor: default;
}

.formulario-refrendo .campo input:focus,
.formulario-refrendo .campo select:focus {
    outline: 2px solid #a30917;
    outline-offset: 2px;
}

@media (max-width: 768px) {
    .formulario-refrendo {
        max-width: 100%;
        padding: 0 12px;
    }
}

.mensaje-no-articulo-14 {
    width: 100%;
    box-sizing: border-box;
    margin: 8px auto 24px;
    padding: 12px 16px;
    background-color: #f5f5f5;
    border: 1px solid #dddddd;
    border-radius: 7px;
    color: #333333;
    font-size: 14px;
    font-weight: 600;
    text-align: center;
}
</style>
