<script setup>
import { ref, computed, watch, nextTick, onMounted } from 'vue';
import SelectorBuscable from './SelectorBuscable.vue';

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
    // Catálogo de posgrados (bandera ANFECA_CATALOGO_POSGRADOS): { estado, url, version }.
    catalogo: { type: Object, default: () => ({ estado: 'desactivado' }) },
    // Catálogo de licenciaturas (bandera ANFECA_CATALOGO_LICENCIATURAS): { estado, aplica, url, minimo }.
    catalogoLicenciaturas: { type: Object, default: () => ({ estado: 'desactivado' }) },
    // Casilla «Cuento con estudios de posgrado concluidos» (bandera ANFECA_DECLARACION_POSGRADO o declaración guardada).
    declaracionPosgrado: { type: Boolean, default: false },
    // El área de certificación exige posgrado (Disciplinas vinculadas: Reglamento arts. 1 y 13; Instrumento 1.1).
    posgradoObligatorio: { type: Boolean, default: false },
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
    if (licActivo.value) {
        return modoOrigen.value === 'manual'
            ? 'El título capturado manualmente queda sujeto a revisión de procedencia.'
            : 'Título permitido con el área de certificación: ' + props.titulosPermitidos.join(', ');
    }
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

/*
 * Catálogo de posgrados (solo con estado «activo»: bandera, archivo íntegro y migración aplicada).
 * Licenciatura conserva su comportamiento; con la bandera desactivada se usa el formulario anterior.
 * El nombre del título lo determina el SERVIDOR a partir de la clave; el navegador solo envía la clave
 * (modo «catalogo») o el texto capturado (modo «manual», disponible en todas las áreas).
 */
const catalogoActivo = computed(() =>
    props.catalogo?.estado === 'activo' && Boolean(props.catalogo?.url)
);
const catalogoConError = computed(() => props.catalogo?.estado === 'error');
const esPosgrado = computed(() =>
    Boolean(nivelUltimoGrado.value) && nivelUltimoGrado.value !== 'Licenciatura'
);
const usaSelectorTitulo = computed(() => catalogoActivo.value && esPosgrado.value);

const f = props.formacion ?? {};
const origenGuardado = f.origen_titulo_ultimo_grado ?? null;
const historicoOtro = !origenGuardado && f.titulo_ultimo_grado === 'Otro' && Boolean(f.titulo_ultimo_grado_otro);
const modoTitulo = ref(
    f.modo_titulo_ultimo_grado
    || (origenGuardado === 'manual' || historicoOtro ? 'manual' : 'catalogo')
);
const claveTitulo = ref(
    f.clave_posgrado_catalogo_old ?? f.clave_posgrado_catalogo ?? ''
);
const tituloManual = ref(
    f.titulo_ultimo_grado_manual
    ?? (origenGuardado === 'manual' ? f.titulo_ultimo_grado_especifico : (historicoOtro ? f.titulo_ultimo_grado_otro : ''))
    ?? ''
);
const opcionesTitulo = ref([]);
const cargandoTitulos = ref(false);
const errorTitulos = ref('');
const cacheTitulos = new Map();
const campoClave = ref(null);
const campoModo = ref(null);
const campoManual = ref(null);

const textoTituloGuardado = computed(() =>
    claveTitulo.value && claveTitulo.value === f.clave_posgrado_catalogo
        ? (f.titulo_ultimo_grado_especifico ?? '') : ''
);
// Selección guardada vigente para el nivel y el área guardados (se conserva aunque la versión actual del
// catálogo ya no la incluya: el servidor la mantiene sin cambios mientras no se modifique).
const enSeleccionGuardada = () => Boolean(f.clave_posgrado_catalogo)
    && nivelUltimoGrado.value === f.nivel_ultimo_grado_guardado
    && tituloUltimoGrado.value === f.titulo_ultimo_grado_guardado;
const tituloFueraDeVersion = ref(false);
const placeholderTitulo = computed(() => {
    if (!nivelUltimoGrado.value || !tituloUltimoGrado.value) return 'Seleccione primero el nivel y el área';
    return 'Seleccione o busque su título';
});

function notificarCambio(elemento) {
    // La protección de cambios pendientes de la vista escucha «input» en el formulario.
    nextTick(() => elemento.value?.dispatchEvent(new Event('input', { bubbles: true })));
}

async function cargarTitulos() {
    errorTitulos.value = '';
    if (!usaSelectorTitulo.value || !tituloUltimoGrado.value) {
        opcionesTitulo.value = [];
        return;
    }
    const llave = `${nivelUltimoGrado.value}|${tituloUltimoGrado.value}`;
    if (cacheTitulos.has(llave)) {
        opcionesTitulo.value = cacheTitulos.get(llave);
        return;
    }
    cargandoTitulos.value = true;
    try {
        const parametros = new URLSearchParams({ nivel: nivelUltimoGrado.value, area: tituloUltimoGrado.value });
        const respuesta = await fetch(`${props.catalogo.url}?${parametros}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        });
        if (!respuesta.ok) throw new Error(String(respuesta.status));
        const datos = await respuesta.json();
        if (!Array.isArray(datos?.titulos)) throw new Error('formato');
        cacheTitulos.set(llave, datos.titulos);
        if (llave === `${nivelUltimoGrado.value}|${tituloUltimoGrado.value}`) {
            opcionesTitulo.value = datos.titulos;
            const presente = datos.titulos.some(t => t.clave === claveTitulo.value);
            tituloFueraDeVersion.value = Boolean(claveTitulo.value) && !presente
                && claveTitulo.value === f.clave_posgrado_catalogo && enSeleccionGuardada();
            // Una clave ajena al nivel y al área vigentes se descarta (el servidor también la rechaza), salvo la
            // selección guardada sin cambios, que se conserva con un aviso (nunca se borra en silencio).
            if (claveTitulo.value && !presente && !tituloFueraDeVersion.value) {
                claveTitulo.value = '';
                notificarCambio(campoClave);
            }
        }
    } catch (e) {
        opcionesTitulo.value = [];
        errorTitulos.value = 'No fue posible cargar el catálogo de títulos. Puede elegir «No encuentro mi título» y escribirlo.';
    } finally {
        cargandoTitulos.value = false;
    }
}

function seleccionarTitulo(clave) {
    claveTitulo.value = clave;
    tituloFueraDeVersion.value = false;
    modoTitulo.value = 'catalogo';
    notificarCambio(campoClave);
}

function capturarTituloManual() {
    modoTitulo.value = 'manual';
    notificarCambio(campoModo);
    nextTick(() => campoManual.value?.focus());
}

function volverAlCatalogo() {
    // El texto capturado se conserva (sin guardar) por si el solicitante regresa a la captura manual.
    modoTitulo.value = 'catalogo';
    notificarCambio(campoModo);
}

watch([nivelUltimoGrado, tituloUltimoGrado], () => {
    // Cambiar nivel o área invalida la selección del catálogo (no el texto manual); al volver al nivel y al
    // área guardados se recupera la selección guardada, para evitar recapturas innecesarias.
    const anterior = claveTitulo.value;
    claveTitulo.value = enSeleccionGuardada() ? f.clave_posgrado_catalogo : '';
    tituloFueraDeVersion.value = false;
    if (anterior !== claveTitulo.value) notificarCambio(campoClave);
    cargarTitulos();
});
onMounted(cargarTitulos);
/*
 * Formación de origen con el catálogo ANUIES de licenciaturas (mismo espacio de «Nombre del Título»; sin campos nuevos).
 * El navegador solo envía la clave (o el texto manual); el área, el nombre exacto y la versión los determina el servidor.
 */
const licActivo = computed(() => props.catalogoLicenciaturas?.aplica === true && Boolean(props.catalogoLicenciaturas?.url));
const licError = computed(() => props.catalogoLicenciaturas?.estado === 'error');
const historicoOrigenOtro = !f.origen_titulo_origen && f.titulo_origen === 'Otro' && Boolean(f.titulo_origen_otro);
const modoOrigen = ref(f.modo_titulo_origen || (f.origen_titulo_origen === 'manual' || historicoOrigenOtro ? 'manual' : 'catalogo'));
const claveOrigen = ref(f.clave_licenciatura_catalogo_old ?? f.clave_licenciatura_catalogo ?? '');
const textoOrigen = ref(
    f.titulo_origen_texto_old
    ?? (claveOrigen.value && claveOrigen.value === f.clave_licenciatura_catalogo ? (f.titulo_origen_especifico ?? '') : '')
);
const origenManual = ref(
    f.titulo_origen_manual
    ?? (f.origen_titulo_origen === 'manual' ? f.titulo_origen_especifico : (historicoOrigenOtro ? f.titulo_origen_otro : ''))
    ?? ''
);
const campoClaveOrigen = ref(null);
const campoModoOrigen = ref(null);
const campoManualOrigen = ref(null);

async function buscarLicenciaturas(texto) {
    const parametros = new URLSearchParams({ q: texto });
    const r = await fetch(`${props.catalogoLicenciaturas.url}?${parametros}`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin'
    });
    if (!r.ok) throw new Error(String(r.status));
    return r.json();
}
function elegirOrigen(opcion) {
    claveOrigen.value = opcion.clave;
    textoOrigen.value = opcion.titulo;
    modoOrigen.value = 'catalogo';
    notificarCambio(campoClaveOrigen);
}
function origenManualActivar() {
    modoOrigen.value = 'manual';
    notificarCambio(campoModoOrigen);
    nextTick(() => campoManualOrigen.value?.focus());
}
function origenVolverAlCatalogo() {
    modoOrigen.value = 'catalogo';
    notificarCambio(campoModoOrigen);
}

/*
 * Casilla «Cuento con estudios de posgrado concluidos». Dos escenarios: solo licenciatura (desmarcada) o licenciatura
 * con posgrado (marcada). Registros nuevos: desmarcada. Registros con posgrado guardado: se recupera marcada (solo
 * visualmente; nada se escribe hasta que el solicitante guarde). Al desmarcar con información o documentos de posgrado
 * guardados se pide confirmación: se CONSERVAN (no se borran), solo dejan de considerarse vigentes.
 */
const declaracion = computed(() => props.declaracionPosgrado === true);
const cuentaConPosgrado = ref(
    f.cuenta_con_posgrado === '1' ? true : (f.cuenta_con_posgrado === '0' ? false : Boolean(f.tiene_datos_posgrado))
);
const campoPosgrado = ref(null);
const muestraPosgrado = computed(() => !declaracion.value || cuentaConPosgrado.value);
const nivelesUltimo = computed(() => declaracion.value ? niveles.filter(n => n !== 'Licenciatura') : niveles);
function cambiarCasillaPosgrado(evento) {
    const guardado = f.cuenta_con_posgrado_guardado === true || Boolean(f.tiene_datos_posgrado)
        || Boolean(f.titulo_ultimo_grado_archivo) || Boolean(f.cedula_ultimo_grado_archivo);
    if (!evento.target.checked && guardado && !window.confirm(
        'Ya registró información o documentos de posgrado. Si indica que solo cuenta con licenciatura, se conservarán '
        + 'pero no se considerarán en esta solicitud. ¿Desea continuar?')) {
        cuentaConPosgrado.value = true;
        evento.target.checked = true;
        return;
    }
    notificarCambio(campoPosgrado);
}

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
                <p v-if="licError" class="aviso-catalogo-posgrados" role="status">
                    El catálogo de licenciaturas no está disponible por el momento; capture su información como de costumbre.
                </p>
                <div v-if="licActivo" class="fila-titulos-origen">
                    <div class="campo campo-selector-origen">
                        <input ref="campoModoOrigen" type="hidden" name="modo_titulo_origen" :value="modoOrigen">
                        <input ref="campoClaveOrigen" type="hidden" name="clave_licenciatura_catalogo" :value="modoOrigen === 'catalogo' ? claveOrigen : ''">
                        <input type="hidden" name="titulo_origen_texto" :value="modoOrigen === 'catalogo' ? textoOrigen : ''">
                        <div class="ug-encabezado-titulo">
                            <label :for="modoOrigen === 'manual' ? 'titulo_origen_manual' : 'titulo_origen'">Nombre del Título</label>
                            <button v-if="modoOrigen === 'manual' && !formacionBloqueada" type="button" class="ug-enlace"
                                    @click="origenVolverAlCatalogo">Volver al catálogo</button>
                        </div>
                        <SelectorBuscable
                            v-if="modoOrigen === 'catalogo'"
                            id="titulo_origen"
                            :model-value="claveOrigen"
                            :texto-seleccionado="textoOrigen"
                            placeholder="Seleccione o busque su título"
                            :deshabilitado="formacionBloqueada"
                            :buscar="buscarLicenciaturas"
                            :minimo="catalogoLicenciaturas.minimo || 3"
                            :ayuda-busqueda="`Escriba al menos ${catalogoLicenciaturas.minimo || 3} letras de su título (p. ej., «contaduría», «sistemas computacionales»).`"
                            opcion-manual="No encuentro mi título"
                            @elegido="elegirOrigen"
                            @manual="origenManualActivar"
                        />
                        <input v-else id="titulo_origen_manual" ref="campoManualOrigen" type="text" name="titulo_origen_manual"
                               class="ug-manual" v-model="origenManual" minlength="5" maxlength="200"
                               :required="!formacionBloqueada" :disabled="formacionBloqueada"
                               placeholder="Escriba el nombre completo de su título, como aparece en el documento">
                    </div>
                </div>
                <div
                    v-else
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
                <p v-if="catalogoConError" class="aviso-catalogo-posgrados" role="status">
                    El catálogo de títulos de posgrado no está disponible por el momento; capture su información como de costumbre.
                </p>

                <!-- Declaración de posgrado: única incorporación visible autorizada. -->
                <div v-if="declaracion" class="ug-declaracion">
                    <input ref="campoPosgrado" type="hidden" name="cuenta_con_posgrado" :value="cuentaConPosgrado ? '1' : '0'">
                    <label class="ug-casilla" for="cuenta_con_posgrado_casilla">
                        <input id="cuenta_con_posgrado_casilla" type="checkbox" v-model="cuentaConPosgrado"
                               :disabled="formacionBloqueada" @change="cambiarCasillaPosgrado">
                        Cuento con estudios de posgrado concluidos
                    </label>
                    <div v-if="!cuentaConPosgrado && posgradoObligatorio" class="ug-solo-licenciatura ug-requisito" role="alert">
                        <p>Para la certificación en {{ areaCertificacion }} es requisito contar con estudios de posgrado.</p>
                        <p>Marque la casilla y registre su último grado.</p>
                    </div>
                    <div v-else-if="!cuentaConPosgrado" class="ug-solo-licenciatura" role="status">
                        <p>Su formación académica de origen corresponde a su último grado de estudios.</p>
                        <p>No es necesario registrar nuevamente el título ni la cédula profesional.</p>
                    </div>
                </div>

                <template v-if="muestraPosgrado">
                <!-- Con el catálogo de posgrados activo: fila 1 Nivel | Área (50/50); fila 2 Nombre del título (100 %). -->
                <div v-if="catalogoActivo" class="ug-catalogo">
                    <div class="ug-fila-nivel-area">
                        <div class="ug-campo">
                            <label for="nivel_ultimo_grado">Nivel</label>
                            <select id="nivel_ultimo_grado" name="nivel_ultimo_grado" v-model="nivelUltimoGrado"
                                    :disabled="formacionBloqueada" required>
                                <option value="">Seleccione</option>
                                <option v-for="nivel in nivelesUltimo" :key="nivel" :value="nivel">{{ nivel }}</option>
                            </select>
                        </div>
                        <div class="ug-campo">
                            <label for="titulo_ultimo_grado">Área</label>
                            <select id="titulo_ultimo_grado" name="titulo_ultimo_grado" v-model="tituloUltimoGrado"
                                    :disabled="formacionBloqueada" required>
                                <option value="">Seleccione</option>
                                <option v-for="titulo in titulosUltimoGrado" :key="titulo" :value="titulo">{{ titulo }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- Posgrado: selector del catálogo o captura manual en el mismo espacio. -->
                    <div v-if="usaSelectorTitulo" class="ug-fila-titulo">
                        <input ref="campoModo" type="hidden" name="modo_titulo_ultimo_grado" :value="modoTitulo">
                        <input ref="campoClave" type="hidden" name="clave_posgrado_catalogo"
                               :value="modoTitulo === 'catalogo' ? claveTitulo : ''">
                        <div class="ug-encabezado-titulo">
                            <label :for="modoTitulo === 'manual' ? 'titulo_ultimo_grado_manual' : 'clave_posgrado_selector'">
                                Nombre del título <span class="campo-obligatorio">*</span>
                            </label>
                            <button v-if="modoTitulo === 'manual' && !formacionBloqueada" type="button" class="ug-enlace"
                                    @click="volverAlCatalogo">Volver al catálogo</button>
                        </div>
                        <SelectorBuscable
                            v-if="modoTitulo === 'catalogo'"
                            id="clave_posgrado_selector"
                            :model-value="claveTitulo"
                            :opciones="opcionesTitulo"
                            :texto-seleccionado="textoTituloGuardado"
                            :placeholder="placeholderTitulo"
                            :deshabilitado="formacionBloqueada || !tituloUltimoGrado"
                            :cargando="cargandoTitulos"
                            opcion-manual="No encuentro mi título"
                            @update:model-value="seleccionarTitulo"
                            @manual="capturarTituloManual"
                        />
                        <input v-else id="titulo_ultimo_grado_manual" ref="campoManual" type="text"
                               name="titulo_ultimo_grado_manual" class="ug-manual" v-model="tituloManual"
                               minlength="5" maxlength="200" :required="!formacionBloqueada" :disabled="formacionBloqueada"
                               placeholder="Escriba el nombre completo de su título, como aparece en el documento">
                        <small v-if="errorTitulos" class="ug-error" role="alert">{{ errorTitulos }}</small>
                        <small v-if="tituloFueraDeVersion && modoTitulo === 'catalogo'" class="ug-aviso" role="status">
                            Título registrado con la versión {{ f.version_catalogo_posgrados }} del catálogo; ya no figura en la versión vigente.
                            Se conserva sin cambios; puede elegir otro si lo desea.
                        </small>
                    </div>

                    <!-- Licenciatura: comportamiento actual (título libre solo con el área «Otro»). -->
                    <div v-else-if="nivelUltimoGrado === 'Licenciatura' && mostrarTituloUltimoGradoOtro" class="ug-fila-titulo">
                        <div class="ug-encabezado-titulo">
                            <label for="titulo_ultimo_grado_otro">Especifique el último grado <span class="campo-obligatorio">*</span></label>
                        </div>
                        <input id="titulo_ultimo_grado_otro" type="text" name="titulo_ultimo_grado_otro" class="ug-manual"
                               v-model.trim="tituloUltimoGradoOtro" maxlength="200"
                               :required="!formacionBloqueada" :disabled="formacionBloqueada"
                               placeholder="Ejemplo: Licenciatura en Economía">
                    </div>
                </div>

                <template v-else>
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
		            v-for="nivel in nivelesUltimo"
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
                </template>
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
                </template>
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

                <!-- Datos dinámicos del catálogo -->
                <div v-if="institucionSeleccionada" class="detalle-institucion">

                    <div
                        v-if="institucionSeleccionada.nombre?.trim()"
                        class="anfeca-institucion-principal"
                    >
                        <strong>Institución:</strong>
                        <span>{{ institucionSeleccionada.nombre }}</span>
                    </div>

                    <div class="anfeca-institucion-grid">

                        <div
                            v-if="institucionSeleccionada.escuela_facultad?.trim()"
                            class="anfeca-institucion-campo"
                        >
                            <strong>Escuela o Facultad:</strong>
                            <span>{{ institucionSeleccionada.escuela_facultad }}</span>
                        </div>

                        <div
                            v-if="institucionSeleccionada.campus?.trim()"
                            class="anfeca-institucion-campo"
                        >
                            <strong>Campus:</strong>
                            <span>{{ institucionSeleccionada.campus }}</span>
                        </div>

                        <div
                            v-if="institucionSeleccionada.sede?.trim()"
                            class="anfeca-institucion-campo"
                        >
                            <strong>Sede:</strong>
                            <span>{{ institucionSeleccionada.sede }}</span>
                        </div>


                        <div
                            v-if="institucionSeleccionada.zona_anfeca?.trim()"
                            class="anfeca-institucion-campo"
                        >
                            <strong>Zona ANFECA:</strong>
                            <span>{{ institucionSeleccionada.zona_anfeca }}</span>
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

<style>
/* Último grado con catálogo de posgrados (estándar de catálogos extensos). Sin efecto con la bandera desactivada. */
#vue-formacion-academica .aviso-catalogo-posgrados { margin: -10px 0 12px; padding: 6px 10px; font-size: 12px; text-align: center;
    color: #7a4b00; background: #fff8e6; border: 1px solid #f0d9a8; border-radius: 6px; }
#vue-formacion-academica .ug-catalogo { display: flex; flex-direction: column; gap: 10px; margin-bottom: 10px; }
#vue-formacion-academica .ug-fila-nivel-area { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; align-items: end; }
#vue-formacion-academica .ug-campo, #vue-formacion-academica .ug-fila-titulo { min-width: 0; margin: 0; padding: 0; }
#vue-formacion-academica .ug-campo label { display: block; margin: 0 0 4px; text-align: center; line-height: 1.25; }
#vue-formacion-academica .ug-catalogo select { width: 100%; max-width: none; height: 34px; min-height: 32px; margin: 0; padding: 0 10px;
    border: 1px solid #888; border-radius: 4px; background: #fff; box-sizing: border-box; }
#vue-formacion-academica .ug-catalogo select:disabled { background: #eee; }
#vue-formacion-academica .ug-encabezado-titulo { position: relative; display: flex; justify-content: center; align-items: flex-end; margin: 0 0 4px; min-height: 17px; }
#vue-formacion-academica .ug-encabezado-titulo label { margin: 0; text-align: center; line-height: 1.25; }
#vue-formacion-academica .ug-enlace { position: absolute; right: 0; bottom: 0; padding: 0; border: 0; background: none;
    color: #bd2434; font: inherit; font-size: 12px; text-decoration: underline; cursor: pointer; }
#vue-formacion-academica .ug-manual { display: block; width: 100%; height: 46px; padding: 4px 10px; box-sizing: border-box;
    border: 1px solid #777; border-radius: 6px; background: #fff; color: #202020; }
#vue-formacion-academica .ug-manual:focus { outline: 2px solid #2563eb; outline-offset: 1px; border-color: #2563eb; }
#vue-formacion-academica .ug-manual:disabled { background: #f1f1f1; cursor: not-allowed; }
#vue-formacion-academica .ug-error { display: block; margin-top: 4px; color: #c62828; font-size: 12px; }
#vue-formacion-academica .ug-aviso { display: block; margin-top: 4px; color: #7a4b00; font-size: 12px; }
@media (max-width: 480px) {
    #vue-formacion-academica .ug-fila-nivel-area { grid-template-columns: 1fr; gap: 8px; }
}
</style>

<style>
/* Formación de origen con catálogo de licenciaturas y declaración de posgrado (sin campos nuevos salvo la casilla). */
#vue-formacion-academica .ug-declaracion { margin: -8px 0 10px; }
#vue-formacion-academica .ug-casilla { display: flex; align-items: center; justify-content: center; gap: 8px; margin: 0; font-weight: 600; cursor: pointer; }
#vue-formacion-academica .ug-casilla input { width: 16px; height: 16px; margin: 0; accent-color: #9e1b32; }
#vue-formacion-academica .ug-solo-licenciatura { margin-top: 8px; padding: 8px 10px; border: 1px solid #d8e6dc; border-radius: 6px;
    background: #f4faf6; color: #326b48; font-size: 12.5px; line-height: 1.35; text-align: center; }
#vue-formacion-academica .ug-solo-licenciatura p { margin: 0; }
#vue-formacion-academica .ug-solo-licenciatura.ug-requisito { border-color: #f0d9a8; background: #fff8e6; color: #7a4b00; }
#vue-formacion-academica .fila-titulos-origen .selector-buscable { width: 100%; }
</style>

