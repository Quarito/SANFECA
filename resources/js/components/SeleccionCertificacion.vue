<script setup>
import { reactive, computed, ref, watch, onMounted } from 'vue';



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
const tipoMensajeConsulta = ref('error');
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

// Recuperar automáticamente la validación al regresar.
onMounted(async () => {
    if (numeroCertificadoAnterior.value.trim()) {
        await consultarCertificadoAnterior();
    }
});

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
    'Profesoras y profesores de Asignatura de Educación Superior',

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
    tipoMensajeConsulta.value = 'error';
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
		tipoMensajeConsulta.value = 'exito';
		mensajeConsulta.value = resultado.mensaje;

		consultandoCertificado.value = false;
	} else {
	    tipoMensajeConsulta.value = 'error';
            mensajeConsulta.value =
                resultado.mensaje ??
                'No fue posible verificar un certificado con los datos proporcionados.';
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
    <div class="anfeca-refrendo-titulo">
        <strong><u>Sólo refrendo</u></strong>
    </div>


    

    <div class="consulta-certificado-info">
        <h2>Consulta de certificado anterior</h2>

        <p>
            ¿Cuenta con un número de certificado anterior?
            Ingréselo para consultar sus antecedentes históricos.
        </p>
    </div>


    <div class="consulta-certificado-formulario">

        <div class="anfeca-certificado-encabezado">
            <label for="numero_certificado_anterior">
                Número de certificado
            </label>

            <div
                v-if="mensajeConsulta"
                class="anfeca-aviso-certificado"
                :class="tipoMensajeConsulta === 'exito'
                    ? 'anfeca-aviso-certificado-exito'
                    : 'anfeca-aviso-certificado-error'"
                :role="tipoMensajeConsulta === 'exito' ? 'status' : 'alert'"
            >
                <span class="anfeca-aviso-icono">
                    {{ tipoMensajeConsulta === 'exito' ? '✓' : '!' }}
                </span>

                <span class="anfeca-aviso-texto">
                    {{ mensajeConsulta }}
                </span>
            </div>
        </div>

        <div class="consulta-certificado-controles">
            <input
                id="numero_certificado_anterior"
                name="numero_certificado_anterior"
                v-model="numeroCertificadoAnterior"
                type="text"
                autocomplete="off"
                inputmode="numeric"
                pattern="[0-9]{1,7}"
                maxlength="7"
                placeholder="Ingrese hasta 7 dígitos"
                @input="numeroCertificadoAnterior = $event.target.value.replace(/[^0-9]/g, '').slice(0, 7)"
            >

            <button
                type="button"
                class="boton-consultar-certificado"
                :disabled="consultandoCertificado || !numeroCertificadoAnterior.trim()"
                @click="consultarCertificadoAnterior"
            >
                {{ consultandoCertificado ? 'Consultando...' : 'Consultar' }}
            </button>
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

                <!-- FIX_CERTIFICACION_DISABLED_97195 -->
                <!-- Enviar la certificación aunque los radios estén bloqueados. -->
                <input
                    v-if="certificacionBloqueada"
                    type="hidden"
                    name="certificacion"
                    :value="formulario.certificacion"
                >

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

<style scoped>
.aviso-certificado {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-top: 14px;
    padding: 14px 16px;
    border: 1px solid;
    border-radius: 8px;
    font-size: 14px;
    line-height: 1.5;
}

.aviso-certificado-icono {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    font-weight: 700;
}

.aviso-certificado-contenido {
    flex: 1;
    min-width: 0;
}

.aviso-certificado-contenido strong {
    display: block;
    margin-bottom: 4px;
}

.aviso-certificado-contenido p {
    margin: 0;
}

.aviso-certificado-exito {
    background: #ecfdf3;
    border-color: #a6e5bd;
    color: #166534;
}

.aviso-certificado-exito .aviso-certificado-icono {
    background: #166534;
    color: #fff;
}

.aviso-certificado-error {
    background: #fff1f2;
    border-color: #fecdd3;
    color: #9f1239;
}

.aviso-certificado-error .aviso-certificado-icono {
    background: #be123c;
    color: #fff;
}

/* ANFECA - Avisos compactos de certificado */

.anfeca-certificado-encabezado {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 10px;
    min-width: 0;
}

.anfeca-certificado-encabezado label {
    margin: 0;
    flex-shrink: 0;
}

.anfeca-aviso-certificado {
    display: flex;
    align-items: center;
    gap: 7px;
    padding: 6px 9px;
    border: 1px solid;
    border-radius: 6px;
    font-size: 12px;
    line-height: 1.35;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.anfeca-aviso-icono {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 17px;
    height: 17px;
    border-radius: 50%;
    flex-shrink: 0;
    font-size: 11px;
    font-weight: 700;
}

.anfeca-aviso-texto {
    overflow-wrap: anywhere;
}

.anfeca-aviso-certificado-error {
    background: #fff1f2;
    border-color: #fecdd3;
    color: #9f1239;
}

.anfeca-aviso-certificado-error .anfeca-aviso-icono {
    background: #be123c;
    color: white;
}

.anfeca-aviso-certificado-exito {
    background: #ecfdf3;
    border-color: #a6e5bd;
    color: #166534;
}

.anfeca-aviso-certificado-exito .anfeca-aviso-icono {
    background: #166534;
    color: white;
}

@media (max-width: 768px) {
    .anfeca-certificado-encabezado {
        flex-direction: column;
        align-items: stretch;
    }

    .anfeca-aviso-certificado {
        width: 100%;
    }
}

</style>


<style>
/* ANFECA - Estilos consolidados de consulta de refrendo */

.consulta-certificado-vue {
    display: grid !important;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    column-gap: 20px;
    row-gap: 8px;
    align-items: start;
    padding-top: 12px !important;
    padding-bottom: 12px !important;
}

.consulta-certificado-vue > .anfeca-refrendo-titulo {
    grid-column: 1 / -1;
    width: 100%;
    text-align: center;
    font-size: 14px;
    font-weight: 700;
    color: #a71930;
    margin: 0;
    padding: 0;
    line-height: 1.2;
}

.consulta-certificado-vue > .consulta-certificado-info,
.consulta-certificado-vue > .consulta-certificado-formulario {
    min-width: 0;
    margin-top: 0;
    padding-top: 0;
}

.consulta-certificado-vue .consulta-certificado-info h2 {
    margin-top: 0;
    margin-bottom: 4px;
}

.consulta-certificado-vue .consulta-certificado-info p {
    margin-top: 0;
    margin-bottom: 0;
    line-height: 1.35;
}

.consulta-certificado-vue
.consulta-certificado-formulario label {
    margin-top: 0;
    margin-bottom: 4px;
}

@media (max-width: 768px) {
    .consulta-certificado-vue {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>

