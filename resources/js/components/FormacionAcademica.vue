<script setup>
import { ref, computed } from 'vue';

const archivosSeleccionados = ref({});

function seleccionarArchivoAcademico(event, campo) {
    archivosSeleccionados.value[campo] =
        event.target.files?.[0]?.name ?? '';
}

const props = defineProps({
    formacionBloqueada: { type: Boolean, default: false },
    areaCertificacion: { type: String, default: '' },
    titulosPermitidos: { type: Array, default: () => [] },
    instituciones: {
        type: Array,
        default: () => []
    },
    formacion: {
        type: Object,
        default: () => ({})
    },
	esRefrendo: {
	    type: Boolean,
	    default: false
	},
});

const titulosOrigen = [
    'Administración',
    'Contaduría',
    'Informática',
    'Negocios Internacionales',
    'Otro'
];

const titulosOrigenFiltrados = computed(() => {
    const historico = props.formacion?.titulo_origen;

    // Mostrar siempre todas las opciones del catálogo.
    // Conservar también un título histórico que ya no
    // figure en el catálogo actual.
    if (historico && !titulosOrigen.includes(historico)) {
        return [historico, ...titulosOrigen];
    }

    return titulosOrigen;
});

const tituloOrigenDisponible = (titulo) => {
    // Un título histórico protegido debe seguir visible.
    if (
        props.esRefrendo &&
        props.formacion?.titulo_origen === titulo
    ) {
        return true;
    }

    return props.titulosPermitidos.includes(titulo);
};

const tituloHistoricoIncompatible = computed(() =>
    props.esRefrendo &&
    Boolean(props.formacion?.titulo_origen) &&
    !props.titulosPermitidos.includes(
        props.formacion.titulo_origen
    )
);

const tituloActualIncompatible = computed(() =>
    Boolean(tituloOrigen.value) &&
    !props.titulosPermitidos.includes(tituloOrigen.value)
);

const mensajeTituloOrigen = computed(() => {
    if (tituloHistoricoIncompatible.value) {
        return 'El título histórico se conserva aunque no coincida '
            + 'con el área actual.';
    }

    if (!props.areaCertificacion) {
        return 'Debes seleccionar el área de certificación '
            + 'antes de continuar.';
    }

    if (tituloActualIncompatible.value) {
        return 'El título seleccionado no corresponde al área '
            + 'de certificación. Debes corregirlo antes de guardar.';
    }

    if (tituloOrigen.value === 'Otro') {
        return 'El título Otro está sujeto a revisión académica.';
    }

    if (!tituloOrigen.value) {
        return 'Título permitido con el área de certificación: '
            + props.titulosPermitidos.join(', ');
    }

    return 'El título seleccionado corresponde al área '
        + 'de certificación.';
});

const niveles = [
    'Licenciatura',
    'Especialidad',
    'Maestría',
    'Doctorado'
];

const titulosUltimoGrado = [
    'Administración',
    'Contaduría',
    'Informática',
    'Negocios Internacionales',
    'Otro'
];

const tituloOrigen = ref(props.formacion?.titulo_origen ?? '');

const tituloOrigenOtro = ref(
    props.formacion?.titulo_origen_otro ?? ''
);

const mostrarTituloOrigenOtro = computed(() =>
    props.areaCertificacion ===
        'Disciplinas vinculadas al ámbito económico-administrativo'
    && tituloOrigen.value === 'Otro'
);
const nivelUltimoGrado = ref(
    props.formacion?.nivel_ultimo_grado ?? ''
);
const tituloUltimoGrado = ref(
    props.formacion?.titulo_ultimo_grado ?? ''
);

const tituloUltimoGradoOtro = ref(
    props.formacion?.titulo_ultimo_grado_otro ?? ''
);

const mostrarTituloUltimoGradoOtro = computed(() =>
    tituloUltimoGrado.value === 'Otro'
);
const institucionId = ref(
    String(props.formacion?.institucion_afiliada_id ?? '')
);

const institucionSeleccionada = computed(() => {
    return props.instituciones.find(
        institucion =>
            String(institucion.id) === institucionId.value
    );
});
</script>

<template>
    <div>
        <div class="columnas columnas-formacion">

            <!-- FORMACIÓN ACADÉMICA DE ORIGEN -->
            <section class="seccion mejora-columnas-97153">
		<h2>Formación Académica de Origen</h2>
		<div class="campo">
		    <label for="nivel_origen_visible">
		        Nivel
		    </label>

		    <select id="nivel_origen_visible" disabled>
		        <option value="Licenciatura">
		            Licenciatura
		        </option>
		    </select>

		    <input
			    
			    type="hidden"
			    name="nivel_origen"
			    value="Licenciatura"
			>
		</div>
                <div
                    class="fila-titulos-origen"
                    :class="{
                        'fila-titulos-origen--doble': mostrarTituloOrigenOtro
                    }"
                >
                    <div class="campo campo-selector-origen">
		    <label for="titulo_origen">
                        Nombre del Título
                    </label>

			<select
			    id="titulo_origen"
			    name="titulo_origen"
			    v-model="tituloOrigen"
			    :disabled="formacionBloqueada || (esRefrendo && Boolean(formacion?.titulo_origen))"
			    :required="!formacion?.titulo_origen"
			>
		        <option value="">Seleccione</option>

		        <option
		            v-for="titulo in titulosOrigenFiltrados"
		            :key="titulo"
		            :value="titulo"
                            :disabled="!tituloOrigenDisponible(titulo)"
		        >
		            {{ titulo }}{{ !tituloOrigenDisponible(titulo) ? ' — No disponible' : '' }}
		        </option>
		    </select>
                    </div>

                    <div
                        v-if="mostrarTituloOrigenOtro"
                        class="campo campo-titulo-origen-otro"
                    >
                        <label for="titulo_origen_otro">
                            Especifique el título de origen
                            <span class="campo-obligatorio">*</span>
                        </label>

                        <input
                            id="titulo_origen_otro"
                            type="text"
                            name="titulo_origen_otro"
                            v-model.trim="tituloOrigenOtro"
                            maxlength="200"
                            :required="!formacionBloqueada && !(esRefrendo && Boolean(formacion?.titulo_origen))"
                            :disabled="formacionBloqueada || (esRefrendo && Boolean(formacion?.titulo_origen))"
                            placeholder="Ejemplo: Economía"
                        >
                    </div>
                </div>

                <p class="mensaje-titulo-origen"
                        role="status"
                        aria-live="polite"
                        :style="{
                            marginTop: '8px',
                            fontSize: '13px',
                            color: (
                                tituloActualIncompatible ||
                                tituloHistoricoIncompatible ||
                                tituloOrigen === 'Otro' ||
                                !areaCertificacion
                            ) ? '#a33' : '#326b48'
                        }"
                    >
                        {{ mensajeTituloOrigen }}
                    </p>


                
		<!-- DOCUMENTOS DE ORIGEN -->
		<div class="documentos-vue documentos-anfeca">

		    <!-- TÍTULO PROFESIONAL -->
		    <div class="campo campo-documento">
		        <div class="fila-documento">
		            <span class="icono-documento" aria-hidden="true">📄</span>

		            <span class="nombre-documento">
		                Título Profesional
		            </span>

		            <div class="acciones-documento">
		                <a
		                    v-if="formacion?.titulo_origen_archivo"
		                    :href="formacion?.titulo_origen_archivo_url"
		                    class="accion-documento"
		                >
		                    Ver
		                </a>

		                <label
		                    v-if="!formacionBloqueada && (!esRefrendo || !formacion?.titulo_origen_archivo)"
		                    for="titulo_origen_archivo"
		                    class="accion-documento"
		                >
		                    {{ formacion?.titulo_origen_archivo ? 'Actualizar' : 'Añadir' }}
		                </label>
		            </div>
		        </div>

		        <input
		            v-if="!formacionBloqueada && (!esRefrendo || !formacion?.titulo_origen_archivo)"
		            id="titulo_origen_archivo"
		            type="file"
		            class="archivo-oculto"
		            name="titulo_origen_archivo"
		            accept=".pdf,.jpg,.jpeg,.png"
		            :required="!formacion?.titulo_origen_archivo"
		            @change="seleccionarArchivoAcademico($event, 'titulo_origen_archivo')"
		        >

		        <small
		            v-if="archivosSeleccionados['titulo_origen_archivo']"
		            class="archivo-elegido"
		        >
		            {{ archivosSeleccionados['titulo_origen_archivo'] }}
		        </small>

		        <small
		            v-if="!formacionBloqueada && (!esRefrendo || !formacion?.titulo_origen_archivo)"
		            class="ayuda-documento"
		        >
		            PDF, JPG o PNG. Máximo 5 MB.
		        </small>
		    </div>

		    <!-- CÉDULA PROFESIONAL -->
		    <div class="campo campo-documento">
		        <div class="fila-documento">
		            <span class="icono-documento" aria-hidden="true">📄</span>

		            <span class="nombre-documento">
		                Cédula Profesional
		            </span>

		            <div class="acciones-documento">
		                <a
		                    v-if="formacion?.cedula_origen_archivo"
		                    :href="formacion?.cedula_origen_archivo_url"
		                    class="accion-documento"
		                >
		                    Ver
		                </a>

		                <label
		                    v-if="!formacionBloqueada && (!esRefrendo || !formacion?.cedula_origen_archivo)"
		                    for="cedula_origen_archivo"
		                    class="accion-documento"
		                >
		                    {{ formacion?.cedula_origen_archivo ? 'Actualizar' : 'Añadir' }}
		                </label>
		            </div>
		        </div>

		        <input
		            v-if="!formacionBloqueada && (!esRefrendo || !formacion?.cedula_origen_archivo)"
		            id="cedula_origen_archivo"
		            type="file"
		            class="archivo-oculto"
		            name="cedula_origen_archivo"
		            accept=".pdf,.jpg,.jpeg,.png"
		            :required="!formacion?.cedula_origen_archivo"
		            @change="seleccionarArchivoAcademico($event, 'cedula_origen_archivo')"
		        >

		        <small
		            v-if="archivosSeleccionados['cedula_origen_archivo']"
		            class="archivo-elegido"
		        >
		            {{ archivosSeleccionados['cedula_origen_archivo'] }}
		        </small>

		        <small
		            v-if="!formacionBloqueada && (!esRefrendo || !formacion?.cedula_origen_archivo)"
		            class="ayuda-documento"
		        >
		            PDF, JPG o PNG. Máximo 5 MB.
		        </small>
		    </div>

		</div>
	</section>

            <!-- ÚLTIMO GRADO DE ESTUDIOS -->
            <section class="seccion mejora-columnas-97153">
                <h2>Último Grado de Estudios</h2>
		<div class="campo">
		    <label for="nivel_ultimo_grado">
		        Nivel
		    </label>

		    <select
		        id="nivel_ultimo_grado"
		        name="nivel_ultimo_grado"
		        v-model="nivelUltimoGrado" :disabled="formacionBloqueada"
		        required
		    >
		        <option value="">Seleccione</option>

		        <option
		            v-for="nivel in niveles"
		            :key="nivel"
		            :value="nivel"
		        >
		            {{ nivel }}
		        </option>
		    </select>
		</div>

		<div
    class="fila-titulos-origen fila-ultimo-grado"
    :class="{
        'fila-titulos-origen--doble': mostrarTituloUltimoGradoOtro
    }"
>
    <div class="campo campo-selector-origen">
        <label for="titulo_ultimo_grado">
            Nombre del Título o Grado
        </label>

        <select
            id="titulo_ultimo_grado"
            name="titulo_ultimo_grado"
            v-model="tituloUltimoGrado"
            :disabled="formacionBloqueada"
            required
        >
            <option value="">Seleccione</option>

            <option
                v-for="titulo in titulosUltimoGrado"
                :key="titulo"
                :value="titulo"
            >
                {{ titulo }}
            </option>
        </select>
    </div>

    <div
        v-if="mostrarTituloUltimoGradoOtro"
        class="campo campo-titulo-origen-otro"
    >
        <label for="titulo_ultimo_grado_otro">
            Especifique el último grado *
        </label>

        <input
            id="titulo_ultimo_grado_otro"
            type="text"
            name="titulo_ultimo_grado_otro"
            v-model.trim="tituloUltimoGradoOtro"
            maxlength="200"
            :required="!formacionBloqueada"
            :disabled="formacionBloqueada"
            placeholder="Ejemplo: Doctorado en Economía"
        >
    </div>
</div>
                <!-- DOCUMENTOS DEL ÚLTIMO GRADO -->
		<div class="documentos-vue documentos-anfeca">
		    <div class="campo campo-documento">
    <div class="fila-documento">
        <span class="icono-documento" aria-hidden="true">📄</span>

        <span class="nombre-documento">Título del último grado</span>

        <div class="acciones-documento">
            <a
                v-if="formacion?.titulo_ultimo_grado_archivo"
                :href="formacion?.titulo_ultimo_grado_archivo_url"
                class="accion-documento"
            >
                Ver
            </a>

            <label v-if="!formacionBloqueada" for="titulo_ultimo_grado_archivo"
                class="accion-documento"
            >
                {{ formacion?.titulo_ultimo_grado_archivo ? 'Actualizar' : 'Añadir' }}
            </label>
        </div>
    </div>

    <input v-if="!formacionBloqueada" id="titulo_ultimo_grado_archivo"
        type="file"
        class="archivo-oculto"
        name="titulo_ultimo_grado_archivo"
        accept=".pdf,.jpg,.jpeg,.png"
        :required="!formacion?.titulo_ultimo_grado_archivo"
        @change="seleccionarArchivoAcademico($event, 'titulo_ultimo_grado_archivo')"
    >

    <small
        v-if="archivosSeleccionados['titulo_ultimo_grado_archivo']"
        class="archivo-elegido"
    >
        {{ archivosSeleccionados['titulo_ultimo_grado_archivo'] }}
    </small>

    <small v-if="!formacionBloqueada" class="ayuda-documento">PDF, JPG o PNG. Máximo 5 MB.</small>
</div>

		    <div class="campo campo-documento">
    <div class="fila-documento">
        <span class="icono-documento" aria-hidden="true">📄</span>

        <span class="nombre-documento">Cédula del último grado</span>

        <div class="acciones-documento">
            <a
                v-if="formacion?.cedula_ultimo_grado_archivo"
                :href="formacion?.cedula_ultimo_grado_archivo_url"
                class="accion-documento"
            >
                Ver
            </a>

            <label v-if="!formacionBloqueada" for="cedula_ultimo_grado_archivo"
                class="accion-documento"
            >
                {{ formacion?.cedula_ultimo_grado_archivo ? 'Actualizar' : 'Añadir' }}
            </label>
        </div>
    </div>

    <input v-if="!formacionBloqueada" id="cedula_ultimo_grado_archivo"
        type="file"
        class="archivo-oculto"
        name="cedula_ultimo_grado_archivo"
        accept=".pdf,.jpg,.jpeg,.png"
        :required="!formacion?.cedula_ultimo_grado_archivo"
        @change="seleccionarArchivoAcademico($event, 'cedula_ultimo_grado_archivo')"
    >

    <small
        v-if="archivosSeleccionados['cedula_ultimo_grado_archivo']"
        class="archivo-elegido"
    >
        {{ archivosSeleccionados['cedula_ultimo_grado_archivo'] }}
    </small>

    <small v-if="!formacionBloqueada" class="ayuda-documento">PDF, JPG o PNG. Máximo 5 MB.</small>
</div>
		</div>
            </section>
        </div>

        <!-- INSTITUCIÓN DE PROCEDENCIA -->
        <section class="institucion">
            <h2>Institución de Procedencia</h2>

            <div class="institucion-datos">

                <!-- Número de Afiliación -->
                <div class="campo">
                    <label for="institucion_afiliada_id">
                        Número de Afiliación
                    </label>

                    <select
                        id="institucion_afiliada_id"
                        name="institucion_afiliada_id"
                        v-model="institucionId"
                        :disabled="formacionBloqueada"
                        required
                    >
                        <option value="">Seleccione</option>

                        <option
                            v-for="institucion in instituciones"
                            :key="institucion.id"
                            :value="String(institucion.id)"
                        >
                            {{ institucion.numero_afiliacion }}
                        </option>
                    </select>
                </div>

                <!-- Datos heredados del catálogo -->
                <div class="detalle-institucion">

                    <div class="anfeca-institucion-principal">
                        <strong>Institución:</strong>
                        <span>
                            {{ institucionSeleccionada?.nombre || '—' }}
                        </span>
                    </div>

                    <div class="anfeca-institucion-grid">

                        <div class="anfeca-institucion-campo">
                            <strong>Escuela o Facultad:</strong>
                            <span>—</span>
                        </div>

                        <div class="anfeca-institucion-campo">
                            <strong>Campus:</strong>
                            <span>
                                {{ institucionSeleccionada?.campus || '—' }}
                            </span>
                        </div>

                        <div class="anfeca-institucion-campo">
                            <strong>Sede:</strong>
                            <span>
                                {{ institucionSeleccionada?.sede || '—' }}
                            </span>
                        </div>

                        <div class="anfeca-institucion-campo">
                            <strong>Zona ANFECA:</strong>
                            <span>
                                {{ institucionSeleccionada?.zona_anfeca || '—' }}
                            </span>
                        </div>

                    </div>
                </div>
            </div>
        </section>
    </div>
</template>


<style scoped>
.campo-titulo-origen-otro {
    width: 80%;
    max-width: 360px;
    margin: 16px auto 0;
    padding: 12px;
    background: #eef6ff;
    border-radius: 8px;
    box-sizing: border-box;
}

.campo-titulo-origen-otro label {
    display: block;
    margin-bottom: 10px;
    font-weight: 600;
    color: #202020;
}

.campo-titulo-origen-otro input {
    display: block;
    width: 100%;
    min-height: 38px;
    padding: 9px 12px;
    background: #ffffff;
    border: 1px solid #777777;
    border-radius: 6px;
    box-sizing: border-box;
    color: #202020;
}

.campo-titulo-origen-otro input:focus {
    outline: 2px solid #2563eb;
    outline-offset: 1px;
    border-color: #2563eb;
}

.campo-titulo-origen-otro input:disabled {
    background: #f1f1f1;
    cursor: not-allowed;
}

.campo-obligatorio {
    color: #c62828;
}

@media (max-width: 768px) {
    .campo-titulo-origen-otro {
        width: 100%;
        max-width: 100%;
    }
}


/* ANFECA: dos visualizaciones del título de origen */

/* Otras áreas: un campo de ancho completo */
.fila-titulos-origen {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 12px;
    align-items: end;
    margin-bottom: 8px;
}

/* Disciplinas vinculadas + Otro: dos campos */
.fila-titulos-origen--doble {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.fila-titulos-origen .campo-selector-origen,
.fila-titulos-origen .campo-titulo-origen-otro {
    width: 100%;
    max-width: none;
    min-width: 0;
    margin: 0;
    padding: 0;
    background: transparent;
    box-sizing: border-box;
}

.fila-titulos-origen .campo-selector-origen label,
.fila-titulos-origen .campo-titulo-origen-otro label {
    display: block;
    margin: 0 0 4px;
    text-align: center;
    line-height: 1.25;
}

.fila-titulos-origen .campo-selector-origen select,
.fila-titulos-origen .campo-titulo-origen-otro input {
    display: block;
    width: 100%;
    max-width: none;
    min-height: 38px;
    box-sizing: border-box;
}

@media (max-width: 768px) {
    .fila-titulos-origen--doble {
        grid-template-columns: 1fr;
    }
}


/* Alinear etiquetas de Último Grado de Estudios */
.fila-ultimo-grado.fila-titulos-origen--doble {
    align-items: stretch;
}

.fila-ultimo-grado .campo-selector-origen,
.fila-ultimo-grado .campo-titulo-origen-otro {
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
}

.fila-ultimo-grado .campo-selector-origen label,
.fila-ultimo-grado .campo-titulo-origen-otro label {
    min-height: 36px;
    display: flex;
    align-items: flex-end;
    justify-content: center;
    text-align: center;
    margin-bottom: 4px;
}

.fila-ultimo-grado .campo-selector-origen select,
.fila-ultimo-grado .campo-titulo-origen-otro input {
    height: 38px;
    min-height: 38px;
}



/* ANFECA: alineación vertical de Último Grado */
@media (min-width: 769px) {
    .fila-ultimo-grado {
        transform: translateY(-16px);
    }
}

</style>
