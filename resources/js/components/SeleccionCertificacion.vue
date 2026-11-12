<script setup>
import { reactive, computed, ref, watch } from 'vue';



const props = defineProps({
    seleccion: {
        type: Object,
        default: () => ({})
    },
    antecedentes: {
        type: Object,
        default: () => ({})
    },
    numeroCertificadoInicial: {
        type: String,
        default: ''
    }
});

const formulario = reactive({
    certificacion: props.seleccion.certificacion ?? '',
    tipo_personal: props.seleccion.tipo_personal ?? '',
    area_certificacion: props.seleccion.area_certificacion ?? ''
});
const numeroCertificadoAnterior = ref(props.numeroCertificadoInicial);
const consultandoCertificado = ref(false);
const mensajeConsulta = ref('');
const historialConsultado = ref(null);

let versionConsulta = 0;
let actualizandoCertificacionHistorica = false;

watch(
    [numeroCertificadoAnterior, () => formulario.certificacion],
    () => {
        if (actualizandoCertificacionHistorica) {
            return;
        }

        versionConsulta++;
        historialConsultado.value = null;
        mensajeConsulta.value = '';
        consultandoCertificado.value = false;
    },
    { flush: 'sync' }
);

const certificaciones = [
    'ANFECA',
    'CACSLA',
    'CACECA'
];

const certificacionBloqueada = computed(() => {
    return historialConsultado.value !== null;
});

const certificacionCoincide = computed(() => {
    const certificacionGuardada =
        props.antecedentes.certificacion_consultada ?? '';

    return certificacionGuardada !== '' &&
        formulario.certificacion === certificacionGuardada;
});

const tiposPersonal = [
    'Profesoras y profesores de Tiempo Completo',
    'Profesoras y profesores de Medio Tiempo',
    'Profesoras y profesores de Asignatura',
];

const areasCertificacion = [
    'Contaduría Pública',
    'Administración',
    'Informática Administrativa',
    'Negocios Internacionales',
    'Disciplinas vinculadas al ámbito económico-administrativo',
];

async function consultarCertificadoAnterior() {
    const numero = numeroCertificadoAnterior.value.trim();

    mensajeConsulta.value = '';
    historialConsultado.value = null;

    if (!numero) {
        mensajeConsulta.value = 'Ingrese un número de certificado.';
        return;
    }

    
    consultandoCertificado.value = true;
const versionActual = ++versionConsulta;

    try {
        const token = document.querySelector(
            'input[name="_token"]'
        )?.value;

        const respuesta = await fetch(
            '/registro/certificacion/consultar',
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token ?? ''
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    numero_certificado: numero
                })
            }
        );

        const resultado = await respuesta.json();
	if (versionActual !== versionConsulta) {
    return;
}
        if (!respuesta.ok) {
            mensajeConsulta.value =
                resultado.mensaje ??
                'No fue posible realizar la consulta.';
            return;
        }

        if (
	    resultado.encontrado &&
	    certificaciones.includes(resultado.historial?.certificacion)
	) {
	       actualizandoCertificacionHistorica = true;

		formulario.certificacion = resultado.historial.certificacion;

		actualizandoCertificacionHistorica = false;

		historialConsultado.value = resultado.historial;
		mensajeConsulta.value = resultado.mensaje;

		consultandoCertificado.value = false;
	} else {
	    mensajeConsulta.value =
	        'No fue posible verificar el certificado anterior.';
	}

    } catch (error) {
        mensajeConsulta.value =
            'Ocurrió un error de comunicación. Intente nuevamente.';
    } finally {
    if (versionActual === versionConsulta) {
        consultandoCertificado.value = false;
    		}
	}
}


</script>

<template>
    <div class="certificacion-vue">

<!-- CONSULTA DE CERTIFICADO ANTERIOR -->
<section class="consulta-certificado-vue">

    <div class="consulta-certificado-info">
        <h2>Consulta de certificado anterior</h2>

        <p>
            ¿Cuenta con un número de certificado anterior?
            Ingréselo para consultar sus antecedentes históricos.
        </p>
    </div>

    <div class="consulta-certificado-formulario">
        <label for="numero_certificado_anterior">
            Número de certificado
        </label>

        <div class="consulta-certificado-controles">
            <input
                id="numero_certificado_anterior"
		name="numero_certificado_anterior"
		 v-model="numeroCertificadoAnterior"
		 type="text"
                placeholder="Ingrese su número de certificado"
                autocomplete="off"
            >

		<button
		    type="button"
		    class="boton-consultar-certificado"
		    :disabled="consultandoCertificado || !numeroCertificadoAnterior.trim()"
		    @click="consultarCertificadoAnterior"
		>
		    {{ consultandoCertificado ? 'Consultando...' : 'Consultar' }}
		</button>
		<p
    v-if="mensajeConsulta"
    class="mensaje-consulta-certificado"
    role="status"
>
    {{ mensajeConsulta }}
</p>
        </div>
    </div>

</section>


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
			    :disabled="certificacionBloqueada"
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
		v-if="historialConsultado && historialConsultado.certificacion === formulario.certificacion"
            class="antecedentes-certificacion-vue"
            aria-labelledby="titulo-antecedentes"
        >
            <h2 id="titulo-antecedentes">
                Antecedentes históricos de certificación
            </h2>

<div class="lista-antecedentes-vue">
    <article class="registro-antecedente-vue">
        <h3>{{ historialConsultado.certificacion }}</h3>

        <p v-if="historialConsultado.primera_vez">
            <strong>Primera certificación:</strong>
            {{ historialConsultado.primera_vez }}
        </p>

        <p
            v-for="refrendo in historialConsultado.refrendos || []"
            :key="refrendo.numero"
        >
            <strong>
                Refrendo {{ refrendo.numero }}:
            </strong>
            {{ refrendo.anio }}
        </p>
    </article>
</div>
        </section>
    </div>
</template>
