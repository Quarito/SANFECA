<script setup>
import { ref } from 'vue';

const props = defineProps({
    academicos: {
        type: Object,
        default: () => ({})
    },
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

const basicos = [
    'titulo_origen_archivo',
    'cedula_origen_archivo',
    'titulo_ultimo_grado_archivo'
];

const probatorios = [
    'constancia_antiguedad_archivo',
    'comprobante_pago_archivo',
    'cedula_ultimo_grado_archivo'
];

function documento(campo) {
    return props.academicos[campo]
        ?? props.nuevos[campo]
        ?? {};
}

function esHeredado(campo) {
    return Object.hasOwn(props.academicos, campo);
}

function seleccionarArchivo(event, campo) {
    archivos.value[campo] = event.target.files?.[0] ?? null;
}
</script>

<template>
    <div class="documentos-vue">
        <div class="documentos-columnas">

            <!-- DOCUMENTOS BÁSICOS -->
            <section class="seccion-documentos">
                <h2>Documentos Básicos</h2>

                <div class="lista-documentos">
                    <div
                        v-for="campo in basicos"
                        :key="campo"
                        class="fila-documento"
                    >
                        <span class="icono-documento" aria-hidden="true">
                            📄
                        </span>

                        <span class="nombre-documento">
                            {{ documento(campo).nombre }}
                        </span>

                        <a
                            v-if="documento(campo).disponible"
                            :href="documento(campo).url"
                            class="accion-documento"
                        >
                            Ver
                        </a>

                        <span v-else class="estado-pendiente">
                            Pendiente
                        </span>
                    </div>
                </div>
            </section>

            <!-- DOCUMENTOS PROBATORIOS Y LINK -->
            <section class="seccion-documentos">
                <h2>Documentos Probatorios</h2>

                <div class="probatorios-contenido">
                    <div class="lista-documentos">
                        <div
                            v-for="campo in probatorios"
                            :key="campo"
                            class="fila-documento"
                        >
                            <span class="icono-documento" aria-hidden="true">
                                📄
                            </span>

                            <span class="nombre-documento">
                                {{ documento(campo).nombre }}
                            </span>

                            <template v-if="esHeredado(campo)">
                                <a
                                    v-if="documento(campo).disponible"
                                    :href="documento(campo).url"
                                    class="accion-documento"
                                >
                                    Ver
                                </a>

                                <span v-else class="estado-pendiente">
                                    Pendiente
                                </span>
                            </template>

                            <template v-else>
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
                            </template>
                        </div>
                    </div>

                    <!-- LINK A LA DERECHA -->
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

        <p class="nota-documentos">
            Los títulos y cédulas académicas se administran
            desde Formación Académica. No es necesario
            adjuntarlos nuevamente.
        </p>
    </div>
</template>
