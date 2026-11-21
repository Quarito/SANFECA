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
    },
    // ART14_VUE_97186
    esArticulo14: {
        type: Boolean,
        default: false
    },
    documentosBloqueados: {
        type: Boolean,
        default: false
    },
    // Solo llega con valor en refrendos por artículo 14 (lo decide el servidor).
    formatoArticulo14Url: {
        type: String,
        default: ''
    }
});

const esArticulo14 = props.esArticulo14 ||
    Object.prototype.hasOwnProperty.call(
        props.nuevos,
        'solicitud_articulo_14_archivo'
    );

const link = ref(props.linkInicial);

const archivos = ref({
    constancia_antiguedad_archivo: null,
    comprobante_pago_archivo: null,
    solicitud_articulo_14_archivo: null,
    constancia_articulo_14_archivo: null
});

const probatorios = [
    'constancia_antiguedad_archivo',
    'comprobante_pago_archivo',
    ...(esArticulo14 ? [
        'solicitud_articulo_14_archivo',
        'constancia_articulo_14_archivo'
    ] : [])
];

function documento(campo) {
    return props.nuevos[campo] ?? {};
}

function seleccionarArchivo(event, campo) {
    archivos.value[campo] = event.target.files?.[0] ?? null;
}
</script>

<template>
    <div
        class="documentos-vue documentos-anfeca"
        :class="{ 'documentos-refrendo-articulo': esArticulo14 }"
    >

        <section class="seccion-documentos">

            <div
                v-if="formatoArticulo14Url"
                class="formato-articulo-14 anfeca-aviso anfeca-aviso--info"
                role="note"
            >
                <p class="formato-articulo-14-texto">
                    Descargue el formato de solicitud de refrendo por artículo 14 y complételo conforme a las indicaciones correspondientes.
                </p>
                <a
                    :href="formatoArticulo14Url"
                    class="formato-articulo-14-enlace"
                >
                    Descargar solicitud de refrendo por artículo 14 (Word)
                </a>
            </div>



            <div class="probatorios-contenido">

                <!-- COLUMNA IZQUIERDA: DOCUMENTOS -->
                <div class="lista-documentos">

                    <div
                        v-for="campo in probatorios"
                        :key="campo"
                        class="fila-documento campo-documento"
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
                                v-if="!documentosBloqueados"
                                :for="campo"
                                class="accion-documento"
                            >
                                {{ documento(campo).disponible
                                    ? 'Actualizar'
                                    : 'Añadir' }}
                            </label>
                        </div>

                        <input
                            v-if="!documentosBloqueados"
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
                <div v-if="!esArticulo14" class="grupo-link">
                    <label for="link_probatorio">Link</label>

                    <input
                        id="link_probatorio"
                        v-model="link"
                        name="link"
                        type="url"
                        placeholder="https://ejemplo.com"
                        maxlength="2048"
                        :readonly="documentosBloqueados"
                        :required="!documentosBloqueados"
                    >
                </div>

            </div>

        </section>
    </div>
</template>

<style scoped>
/* Aspecto homologado: public/css/anfeca-avisos.css (anfeca-aviso--info). */
.formato-articulo-14 {
    margin: 0 0 16px;
    font-size: 13px;
}

.formato-articulo-14-texto {
    margin: 0 0 8px;
    color: #333333;
}

.formato-articulo-14-enlace {
    display: inline-block;
    color: #980d22;
    font-weight: 600;
    text-decoration: underline;
}
</style>
