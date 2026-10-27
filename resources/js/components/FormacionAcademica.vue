<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    instituciones: {
        type: Array,
        default: () => []
    },
    formacion: {
        type: Object,
        default: () => ({})
    }
});

const titulosOrigen = [
    'Informática',
    'Administración',
    'Negocios Internacionales'
];

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
const nivelUltimoGrado = ref(
    props.formacion?.nivel_ultimo_grado ?? ''
);
const tituloUltimoGrado = ref(
    props.formacion?.titulo_ultimo_grado ?? ''
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
            <section class="seccion">
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
                <div class="campo">
		    <label for="titulo_origen">
		        Nombre del Título o Grado
		    </label>

		    <select
		        id="titulo_origen"
		        name="titulo_origen"
		        v-model="tituloOrigen"
		        required
		    >
		        <option value="">Seleccione</option>

		        <option
		            v-for="titulo in titulosOrigen"
		            :key="titulo"
		            :value="titulo"
		        >
		            {{ titulo }}
		        </option>
		    </select>
		</div>

                
                <!-- DOCUMENTOS DE ORIGEN -->
		<div class="documentos-vue">
		    <div class="campo campo-documento">
		        <label for="titulo_origen_archivo">
		            Adjuntar Título Profesional
		        </label>

		        <input
		            type="file"
		            id="titulo_origen_archivo"
		            name="titulo_origen_archivo"
		            accept=".pdf,.jpg,.jpeg,.png"
		            :required="!formacion?.titulo_origen_archivo"
		        >

		        <small>PDF, JPG o PNG. Máximo 5 MB.</small>
		    </div>

		    <div class="campo campo-documento">
		        <label for="cedula_origen_archivo">
		            Adjuntar Cédula Profesional
		        </label>

		        <input
		            type="file"
		            id="cedula_origen_archivo"
		            name="cedula_origen_archivo"
		            accept=".pdf,.jpg,.jpeg,.png"
		            :required="!formacion?.cedula_origen_archivo"
		        >

		        <small>PDF, JPG o PNG. Máximo 5 MB.</small>
		    </div>
		</div>
	</section>

            <!-- ÚLTIMO GRADO DE ESTUDIOS -->
            <section class="seccion">
                <h2>Último Grado de Estudios</h2>
		<div class="campo">
		    <label for="nivel_ultimo_grado">
		        Nivel
		    </label>

		    <select
		        id="nivel_ultimo_grado"
		        name="nivel_ultimo_grado"
		        v-model="nivelUltimoGrado"
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

		<div class="campo">
		    <label for="titulo_ultimo_grado">
		        Nombre del Título o Grado
		    </label>

		    <select
		        id="titulo_ultimo_grado"
		        name="titulo_ultimo_grado"
		        v-model="tituloUltimoGrado"
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
                <!-- DOCUMENTOS DEL ÚLTIMO GRADO -->
		<div class="documentos-vue">
		    <div class="campo campo-documento">
		        <label for="titulo_ultimo_grado_archivo">
		            Adjuntar Título o Grado Académico
		        </label>

		        <input
		            type="file"
		            id="titulo_ultimo_grado_archivo"
		            name="titulo_ultimo_grado_archivo"
		            accept=".pdf,.jpg,.jpeg,.png"
		            :required="!formacion?.titulo_ultimo_grado_archivo"
		        >

		        <small>PDF, JPG o PNG. Máximo 5 MB.</small>
		    </div>

		    <div class="campo campo-documento">
		        <label for="cedula_ultimo_grado_archivo">
		            Adjuntar Cédula Profesional
		        </label>

		        <input
		            type="file"
		            id="cedula_ultimo_grado_archivo"
		            name="cedula_ultimo_grado_archivo"
		            accept=".pdf,.jpg,.jpeg,.png"
		            :required="!formacion?.cedula_ultimo_grado_archivo"
		        >

		        <small>PDF, JPG o PNG. Máximo 5 MB.</small>
		    </div>
		</div>
            </section>
        </div>

        <!-- INSTITUCIÓN DE PROCEDENCIA -->
        <section class="institucion">
            <h2>Institución de Procedencia</h2>

            <div class="institucion-datos">
                <div class="campo">
                    <label for="institucion_afiliada_id">
                        Número de Afiliación
                    </label>

                    <select
                        id="institucion_afiliada_id"
                        name="institucion_afiliada_id"
                        v-model="institucionId"
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

                <div class="detalle-institucion">
                    <p>
                        Institución:
                        <strong>
                            {{ institucionSeleccionada?.nombre || '—' }}
                        </strong>
                    </p>

                    <p>
                        Sede:
                        <strong>
                            {{ institucionSeleccionada?.sede || '—' }}
                        </strong>
                    </p>

                    <p>
                        Zona ANFECA:
                        <strong>
                            {{ institucionSeleccionada?.zona_anfeca || '—' }}
                        </strong>
                    </p>
                </div>
            </div>
        </section>
    </div>
</template>
