<script setup>
/*
 * Panel de revisión documental (fase 6.3.1) para el lado derecho del visor (≈ 30 %).
 * Recibe el detalle consultado (versión, vigente, historial, clave de idempotencia) y REGISTRA una
 * revisión (solo inserción). Conserva los controles: idempotencia (misma clave en reintentos),
 * concurrencia y versión (409), validación (422) y permisos (404/403 del servidor).
 */
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    urlBase: { type: String, required: true },
    campo: { type: String, required: true },
    detalle: { type: Object, required: true }
});
const emit = defineEmits(['sucio', 'guardado', 'actualizar']);

const RESULTADOS = [
    { valor: 'validado', etiqueta: 'Validado' },
    { valor: 'con_observaciones', etiqueta: 'Con observaciones' },
    { valor: 'no_valido', etiqueta: 'No válido' }
];
const CARGA = { cargado: 'Cargado', no_cargado: 'No cargado', no_localizado: 'Registrado, archivo no localizado' };

const enviando = ref(false);
const error = ref('');
const conflicto = ref(null);
const errores = reactive({});
const formulario = reactive({ resultado: '', observaciones: '', motivo_cambio: '' });

const vigente = computed(() => props.detalle.vigente ?? null);
const sinArchivo = computed(() => props.detalle.version.estado !== 'cargado');
const exigeObservaciones = computed(() => ['con_observaciones', 'no_valido'].includes(formulario.resultado));
const cambiaResultado = computed(() => vigente.value && formulario.resultado && formulario.resultado !== vigente.value.resultado);
const maximo = computed(() => props.detalle.texto_maximo ?? 1000);
const sucio = computed(() => !!(formulario.resultado || formulario.observaciones.trim() || formulario.motivo_cambio.trim()));

watch(sucio, v => emit('sucio', v), { immediate: true });
watch(() => props.detalle, () => {
    formulario.resultado = '';
    formulario.observaciones = '';
    formulario.motivo_cambio = '';
    limpiarErrores();
});

function limpiarErrores() {
    Object.keys(errores).forEach(k => delete errores[k]);
    error.value = '';
    conflicto.value = null;
}

function validarLocal() {
    limpiarErrores();
    const min = props.detalle.texto_minimo ?? 10;
    if (!formulario.resultado) errores.resultado = 'Seleccione un resultado.';
    if (exigeObservaciones.value && formulario.observaciones.trim().length < min) {
        errores.observaciones = `Las observaciones son obligatorias (mínimo ${min} caracteres).`;
    }
    if (cambiaResultado.value && formulario.motivo_cambio.trim().length < min) {
        errores.motivo_cambio = `Indique el motivo del cambio (mínimo ${min} caracteres).`;
    }
    return Object.keys(errores).length === 0;
}

async function registrar() {
    if (!validarLocal()) return;
    enviando.value = true;
    try {
        const r = await fetch(`${props.urlBase}/${props.campo}/revisiones`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                resultado: formulario.resultado,
                observaciones: formulario.observaciones || null,
                motivo_cambio: cambiaResultado.value ? formulario.motivo_cambio : null,
                revision_vigente_id: vigente.value?.id ?? null,
                version: props.detalle.version.token,
                // Misma clave en los reintentos: el servidor no duplica la revisión.
                clave_idempotencia: props.detalle.clave_idempotencia
            })
        });
        const json = await r.json().catch(() => ({}));
        if (r.status === 201 || r.status === 200) {
            emit('sucio', false);
            emit('guardado', json);
            return;
        }
        if (r.status === 422) {
            Object.entries(json.errors ?? {}).forEach(([k, v]) => { errores[k] = v[0]; });
            error.value = json.message && !json.errors ? json.message : '';
        } else if (r.status === 409) {
            conflicto.value = json.message ?? 'La información cambió desde su consulta.';
        } else if (r.status === 419) {
            error.value = 'La sesión expiró. Actualice la página e inicie sesión de nuevo.';
        } else {
            error.value = json.message ?? `No fue posible registrar la revisión (error ${r.status}).`;
        }
    } catch {
        error.value = 'Sin conexión con el servidor. Puede reintentar: la revisión no se duplicará.';
    } finally {
        enviando.value = false;
    }
}

function bytes(n) {
    if (n === null || n === undefined) return '—';
    return n < 1024 ? `${n} B` : n < 1048576 ? `${(n / 1024).toFixed(1)} KB` : `${(n / 1048576).toFixed(2)} MB`;
}
function rol(r) {
    return r === 'coordinador_nacional' ? 'Coordinación nacional' : r === 'coordinador_zona' ? 'Coordinación de zona' : r;
}
</script>

<template>
    <div class="rev-panel">
        <h3 class="rev-titulo">Revisión documental</h3>
        <dl class="rev-version">
            <div><dt>Estado de carga</dt><dd>{{ CARGA[detalle.version.estado] }}</dd></div>
            <div><dt>Versión revisada</dt><dd>
                <template v-if="detalle.version.archivo">{{ detalle.version.archivo }} · SHA-256 {{ detalle.version.sha256_corto ?? '—' }}… · {{ bytes(detalle.version.bytes) }}</template>
                <template v-else>Sin archivo</template>
            </dd></div>
            <div><dt>Resultado vigente</dt><dd>
                <template v-if="vigente">
                    <span class="coord-insignia rev" :class="`rev-${detalle.vigente_de_esta_version ? vigente.resultado : 'por_revisar'}`">
                        {{ detalle.vigente_de_esta_version ? vigente.resultado_etiqueta : 'Por revisar' }}
                    </span>
                    <small v-if="!detalle.vigente_de_esta_version"> (la revisión anterior corresponde a otra versión: {{ vigente.resultado_etiqueta }})</small>
                    <small v-else class="rev-quien">{{ vigente.coordinador }} · {{ vigente.revisado_en }}</small>
                </template>
                <span v-else class="coord-insignia rev rev-por_revisar">Por revisar</span>
            </dd></div>
            <div><dt>Registrará</dt><dd>{{ detalle.coordinador.nombre }} ({{ rol(detalle.coordinador.rol) }}) · fecha y hora del servidor al guardar</dd></div>
        </dl>

        <p v-if="sinArchivo" class="rev-sin-archivo" role="note">
            No hay archivo disponible ({{ CARGA[detalle.version.estado] }}): solo pueden registrarse «Con observaciones» o «No válido».
        </p>

        <fieldset class="rev-opciones" :aria-invalid="!!errores.resultado">
            <legend>Resultado de revisión</legend>
            <label v-for="r in RESULTADOS" :key="r.valor" :class="{ inhabilitado: r.valor === 'validado' && sinArchivo }">
                <input v-model="formulario.resultado" type="radio" :name="`resultado-${campo}`" :value="r.valor"
                       :disabled="r.valor === 'validado' && sinArchivo">
                {{ r.etiqueta }}
            </label>
        </fieldset>
        <p v-if="errores.resultado" class="rev-error-campo">{{ errores.resultado }}</p>

        <label class="rev-campo">
            <span>Observaciones <template v-if="exigeObservaciones">(obligatorias)</template><template v-else>(opcionales)</template></span>
            <textarea v-model="formulario.observaciones" rows="4" :maxlength="maximo" :aria-invalid="!!errores.observaciones"></textarea>
            <small class="rev-contador">{{ formulario.observaciones.length }} / {{ maximo }}</small>
        </label>
        <p v-if="errores.observaciones" class="rev-error-campo">{{ errores.observaciones }}</p>

        <label v-if="cambiaResultado" class="rev-campo">
            <span>Motivo del cambio respecto de «{{ vigente.resultado_etiqueta }}» (obligatorio)</span>
            <textarea v-model="formulario.motivo_cambio" rows="2" :maxlength="maximo" :aria-invalid="!!errores.motivo_cambio"></textarea>
            <small class="rev-contador">{{ formulario.motivo_cambio.length }} / {{ maximo }}</small>
        </label>
        <p v-if="errores.motivo_cambio" class="rev-error-campo">{{ errores.motivo_cambio }}</p>

        <p v-if="conflicto" class="anfeca-aviso anfeca-aviso--advertencia rev-aviso" role="alert">
            {{ conflicto }}
            <button type="button" class="coord-boton secundario pequeno" @click="emit('actualizar')">Actualizar datos</button>
        </p>
        <p v-if="error" class="anfeca-aviso anfeca-aviso--error rev-aviso" role="alert">{{ error }}</p>

        <div class="rev-acciones">
            <button type="button" class="coord-boton" :disabled="enviando || !!conflicto" @click="registrar">
                {{ enviando ? 'Guardando…' : 'Guardar revisión' }}
            </button>
        </div>

        <details class="rev-historial">
            <summary>Historial de revisiones ({{ detalle.historial.length }})</summary>
            <p v-if="!detalle.historial.length" class="rev-sub">Sin revisiones registradas.</p>
            <div v-else class="coord-tabla-contenedor">
                <table class="coord-tabla">
                    <thead>
                        <tr>
                            <th scope="col" class="col-num" title="Consecutivo visual; no es un identificador">Núm.</th>
                            <th scope="col">Fecha y hora</th>
                            <th scope="col">Resultado</th>
                            <th scope="col">Coordinador</th>
                            <th scope="col">Observaciones / motivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="h in detalle.historial" :key="h.id">
                            <td class="col-num">{{ h.num }}</td>
                            <td>{{ h.revisado_en }}<br><small>{{ h.version.archivo ?? 'Sin archivo' }}<template v-if="h.version.sha256_corto"> · {{ h.version.sha256_corto }}…</template></small></td>
                            <td><span class="coord-insignia rev" :class="`rev-${h.resultado}`">{{ h.resultado_etiqueta }}</span></td>
                            <td>{{ h.coordinador }}<br><small>{{ rol(h.coordinador_rol) }}</small></td>
                            <td>
                                <span v-if="h.observaciones">{{ h.observaciones }}</span>
                                <small v-if="h.motivo_cambio" class="rev-motivo">Motivo del cambio: {{ h.motivo_cambio }}</small>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </details>
    </div>
</template>

<style scoped>
.rev-panel { font-size: 13px; }
.rev-titulo { margin: 0 0 8px; font-size: 14px; color: #bd151a; }
.rev-sub { margin: 0; font-size: 12.5px; color: #66717d; }
.rev-version { display: grid; grid-template-columns: max-content 1fr; gap: 3px 10px; margin: 0 0 8px; font-size: 12.5px; }
.rev-version div { display: contents; }
.rev-version dt { color: #66717d; }
.rev-version dd { margin: 0; color: #26313c; word-break: break-word; }
.rev-quien { display: block; color: #66717d; }
.rev-sin-archivo { margin: 0 0 8px; padding: 6px 8px; border-radius: 6px; background: #fff4e0; border: 1px solid #e8c27a; color: #6b4a00; font-size: 12px; }
.rev-opciones { border: 1px solid #e6d8da; border-radius: 8px; padding: 6px 10px; margin: 0 0 6px; display: flex; flex-wrap: wrap; gap: 4px 14px; }
.rev-opciones legend { font-size: 12px; color: #66717d; padding: 0 4px; }
.rev-opciones label { display: inline-flex; gap: 6px; align-items: center; }
.rev-opciones label.inhabilitado { color: #9a9a9a; }
.rev-campo { display: flex; flex-direction: column; gap: 3px; margin-top: 6px; font-size: 12.5px; color: #66717d; }
.rev-campo textarea { font: inherit; font-size: 13px; padding: 6px 8px; border: 1px solid #d6cfcf; border-radius: 6px; resize: vertical; color: #26313c; }
.rev-contador { align-self: flex-end; font-size: 11px; }
.rev-error-campo { margin: 3px 0 0; font-size: 12px; color: #8a1c2b; }
.rev-aviso { margin: 10px 0 0; padding: 8px 10px; font-size: 12.5px; display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.rev-acciones { display: flex; justify-content: flex-end; gap: 8px; margin: 10px 0; }
.rev-historial { margin-top: 6px; font-size: 12.5px; }
.rev-historial summary { cursor: pointer; color: #bd2434; font-weight: 600; margin-bottom: 6px; }
.rev-motivo { display: block; color: #66717d; }
</style>
