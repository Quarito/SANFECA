<script setup>
import { ref } from 'vue';

const props = defineProps({
    nuevos: {
        type: Object,
        default: () => ({})
    },
    linkInicial: {
        type: String,
        default: ''
    }
});

const link = ref(props.linkInicial);

const archivos = ref({
    constancia_antiguedad_archivo: null,
    comprobante_pago_archivo: null
});

const probatorios = [
    'constancia_antiguedad_archivo',
    'comprobante_pago_archivo'
];

function documento(campo) {
    return props.nuevos[campo] ?? {};
}

function seleccionarArchivo(event, campo) {
    archivos.value[campo] = event.target.files?.[0] ?? null;
}
</script>

<template>
    <div class="documentos-vue">
        <section class="seccion-documentos">

            <h2>Documentos Probatorios</h2>

            <div class="probatorios-contenido">

                <!-- COLUMNA IZQUIERDA: DOCUMENTOS -->
                <div class="lista-documentos">

                    <div
                        v-for="campo in probatorios"
                        :key="campo"
                        class="fila-documento"
                    >
                        <span
                            class="icono-documento"
                            aria-hidden="true"
                        >
                            📄
                        </span>

                        <span class="nombre-documento">
                            {{ documento(campo).nombre }}
                        </span>

                        <div class="acciones-documento">
                            <a
                                v-if="documento(campo).disponible"
                                :href="documento(campo).url"
                                class="accion-documento"
                            >
                                Ver
                            </a>

                            <label
                                :for="campo"
                                class="accion-documento"
                            >
                                {{ documento(campo).disponible
                                    ? 'Cambiar'
                                    : 'Añadir' }}
                            </label>
                        </div>

                        <input
                            :id="campo"
                            class="archivo-oculto"
                            type="file"
                            :name="campo"
                            accept=".pdf,.jpg,.jpeg,.png"
                            :required="!documento(campo).disponible"
                            @change="seleccionarArchivo($event, campo)"
                        >

                        <small
                            v-if="archivos[campo]"
                            class="archivo-elegido"
                        >
                            {{ archivos[campo].name }}
                        </small>
                    </div>

                </div>

                <!-- COLUMNA DERECHA: LINK -->
                <div class="grupo-link">
                    <label for="link_probatorio">Link</label>

                    <input
                        id="link_probatorio"
                        v-model="link"
                        name="link"
                        type="url"
                        placeholder="https://ejemplo.com"
                        maxlength="2048"
                    >
                </div>

            </div>

        </section>
    </div>
</template>
