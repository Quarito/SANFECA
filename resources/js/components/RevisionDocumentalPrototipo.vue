<script setup>
/*
 * PROTOTIPO DEMOSTRATIVO (fase 6.3.0) — sin red ni persistencia.
 * Datos ficticios fijos; los resultados y la auditoría viven solo en memoria.
 * Reglas representadas: estado de carga ≠ resultado de revisión; «No aplica» calculado;
 * observación obligatoria para «Con observaciones» / «No válido»; «Validado» bloqueado
 * sin archivo; cada cambio es una revisión nueva (historial); sin dictamen automático.
 */
import { computed, nextTick, reactive, ref } from 'vue';

const COORDINADOR = 'Coordinación demo (prototipo)';
const RESULTADOS = {
    validado: { etiqueta: 'Validado', clase: 'rev-validado' },
    con_observaciones: { etiqueta: 'Con observaciones', clase: 'rev-observaciones' },
    no_valido: { etiqueta: 'No válido', clase: 'rev-no-valido' },
};
const CARGA = {
    cargado: 'Cargado',
    'no-cargado': 'No cargado',
    'no-localizado': 'Registrado, archivo no localizado',
};

const primeraVez = ref(false);   // simula un trámite donde el art. 13/14 no aplica

function hace(horas) {
    return new Date(Date.now() - horas * 3600 * 1000).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' });
}

const documentos = reactive([
    { campo: 'titulo_origen', nombre: 'Título de licenciatura', carga: 'cargado', columna: 1,
        historial: [{ resultado: 'validado', observacion: '', coordinador: COORDINADOR, fecha: hace(26) }] },
    { campo: 'cedula_origen', nombre: 'Cédula de licenciatura', carga: 'cargado', columna: 1, historial: [] },
    { campo: 'titulo_ultimo', nombre: 'Título del último grado', carga: 'cargado', columna: 2, historial: [] },
    { campo: 'cedula_ultimo', nombre: 'Cédula del último grado', carga: 'no-localizado', columna: 2, historial: [] },
    { campo: 'constancia', nombre: 'Constancia de antigüedad', carga: 'cargado', columna: 3, historial: [] },
    { campo: 'pago', nombre: 'Comprobante de pago', carga: 'cargado', columna: 3,
        historial: [{ resultado: 'con_observaciones', observacion: 'El comprobante no muestra la referencia completa del pago (dato ficticio).', coordinador: COORDINADOR, fecha: hace(3) }] },
    { campo: 'solicitud_art', nombre: 'Solicitud por artículo', carga: 'cargado', adicional: true, historial: [] },
    { campo: 'constancia_art', nombre: 'Constancia probatoria de artículo', carga: 'no-cargado', adicional: true, historial: [] },
]);

const aplica = (d) => !(d.adicional && primeraVez.value);
const ultimo = (d) => d.historial.at(-1) ?? null;
const resultado = (d) => (aplica(d) ? (ultimo(d)?.resultado ?? 'por_revisar') : 'no_aplica');

const principales = computed(() => [1, 2, 3].map((c) => documentos.filter((d) => d.columna === c)));
const adicionales = computed(() => documentos.filter((d) => d.adicional));

const indicadores = computed(() => {
    const aplicables = documentos.filter(aplica);
    const cuenta = (fn) => aplicables.filter(fn).length;
    const total = aplicables.length;
    const revisados = cuenta((d) => ultimo(d) !== null);
    const validados = cuenta((d) => resultado(d) === 'validado');
    return [
        { etiqueta: 'Aplicables', valor: total },
        { etiqueta: 'Cargados', valor: cuenta((d) => d.carga === 'cargado') },
        { etiqueta: 'Faltantes', valor: cuenta((d) => d.carga !== 'cargado') },
        // Dimensión de revisión: por revisar + validados + con observaciones + no válidos = aplicables.
        { etiqueta: 'Por revisar', valor: cuenta((d) => ultimo(d) === null) },
        { etiqueta: 'Validados', valor: validados },
        { etiqueta: 'Con observaciones', valor: cuenta((d) => resultado(d) === 'con_observaciones') },
        { etiqueta: 'No válidos', valor: cuenta((d) => resultado(d) === 'no_valido') },
        { etiqueta: 'Avance de revisión', valor: total ? Math.round((revisados / total) * 100) + ' %' : '—', destacado: true },
        { etiqueta: 'Documentos validados', valor: total ? Math.round((validados / total) * 100) + ' %' : '—', destacado: true },
    ];
});

// Panel de revisión (diálogo; no ocupa espacio permanente)
const panel = reactive({ abierto: false, documento: null, resultado: '', observacion: '', error: '' });
const dialogo = ref(null);

async function abrir(d) {
    Object.assign(panel, { abierto: true, documento: d, resultado: '', observacion: '', error: '' });
    await nextTick();
    dialogo.value?.querySelector('input:not([disabled])')?.focus();
}

function cerrar() {
    panel.abierto = false;
}

function registrar() {
    const d = panel.documento;
    const obs = panel.observacion.trim();
    const cambia = ultimo(d) !== null;
    if (!panel.resultado) {
        panel.error = 'Seleccione un resultado.';
        return;
    }
    if (panel.resultado === 'validado' && d.carga !== 'cargado') {
        panel.error = 'No se puede validar un documento sin archivo disponible.';
        return;
    }
    if ((panel.resultado !== 'validado' || cambia) && obs.length < 10) {
        panel.error = panel.resultado === 'validado'
            ? 'Para cambiar un resultado registrado, escriba una justificación (mínimo 10 caracteres).'
            : 'La observación es obligatoria (mínimo 10 caracteres).';
        return;
    }
    d.historial.push({ resultado: panel.resultado, observacion: obs, coordinador: COORDINADOR, fecha: new Date().toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' }) });
    cerrar();
}

function etiquetaResultado(r) {
    return r === 'por_revisar' ? 'Por revisar' : r === 'no_aplica' ? 'No aplica' : RESULTADOS[r].etiqueta;
}
function claseResultado(r) {
    return r === 'por_revisar' ? 'rev-por-revisar' : r === 'no_aplica' ? 'rev-no-aplica' : RESULTADOS[r].clase;
}
</script>

<template>
    <div class="prototipo-revision">
        <section class="coord-panel prototipo-indicadores" aria-label="Indicadores de revisión documental (demostrativos)">
            <div v-for="i in indicadores" :key="i.etiqueta" class="prototipo-indicador" :class="{ destacado: i.destacado }">
                <span class="prototipo-indicador-valor">{{ i.valor }}</span>
                <span class="prototipo-indicador-etiqueta">{{ i.etiqueta }}</span>
            </div>
            <label class="prototipo-simulacion">
                <input v-model="primeraVez" type="checkbox"> Simular trámite de primera vez (art. 13/14 «No aplica»)
            </label>
        </section>

        <section class="coord-panel coord-panel-documentos" aria-labelledby="proto-docs">
            <div class="coord-documentos-encabezado">
                <h2 id="proto-docs">Documentos</h2>
                <p class="coord-documentos-leyenda">Estado de carga y resultado de revisión son independientes.</p>
            </div>

            <div class="coord-documentos-grupo">
                <div v-for="(col, n) in principales" :key="n" class="coord-documentos-columna">
                    <article v-for="d in col" :key="d.campo" class="coord-documento prototipo-documento" :class="d.carga">
                        <span class="coord-documento-icono" aria-hidden="true">📄</span>
                        <div class="coord-documento-texto">
                            <div class="coord-documento-nombre">{{ d.nombre }}</div>
                            <div class="prototipo-estados">
                                <span class="coord-insignia" :class="d.carga">{{ CARGA[d.carga] }}</span>
                                <span class="coord-insignia" :class="claseResultado(resultado(d))">{{ etiquetaResultado(resultado(d)) }}</span>
                            </div>
                        </div>
                        <div class="prototipo-acciones">
                            <span class="coord-boton secundario pequeno" aria-disabled="true" title="Prototipo: sin archivos reales">Ver</span>
                            <button type="button" class="coord-boton pequeno" :disabled="!aplica(d)" @click="abrir(d)">Revisar</button>
                        </div>
                        <details v-if="d.historial.length" class="prototipo-auditoria">
                            <summary>Auditoría ({{ d.historial.length }})</summary>
                            <ol>
                                <li v-for="(h, k) in [...d.historial].reverse()" :key="k">
                                    <strong>{{ etiquetaResultado(h.resultado) }}</strong> · {{ h.coordinador }} · {{ h.fecha }}
                                    <span v-if="h.observacion"> — {{ h.observacion }}</span>
                                </li>
                            </ol>
                        </details>
                    </article>
                </div>
            </div>

            <h3 class="coord-documentos-subtitulo">Documentos adicionales — Artículo 13/14</h3>
            <div class="coord-documentos-adicionales" :style="{ '--columnas': adicionales.length }">
                <article v-for="d in adicionales" :key="d.campo" class="coord-documento prototipo-documento" :class="d.carga">
                    <span class="coord-documento-icono" aria-hidden="true">📄</span>
                    <div class="coord-documento-texto">
                        <div class="coord-documento-nombre">{{ d.nombre }}</div>
                        <div class="prototipo-estados">
                            <span class="coord-insignia" :class="d.carga">{{ CARGA[d.carga] }}</span>
                            <span class="coord-insignia" :class="claseResultado(resultado(d))">{{ etiquetaResultado(resultado(d)) }}</span>
                        </div>
                    </div>
                    <div class="prototipo-acciones">
                        <span class="coord-boton secundario pequeno" aria-disabled="true" title="Prototipo: sin archivos reales">Ver</span>
                        <button type="button" class="coord-boton pequeno" :disabled="!aplica(d)" @click="abrir(d)">Revisar</button>
                    </div>
                    <details v-if="d.historial.length && aplica(d)" class="prototipo-auditoria">
                        <summary>Auditoría ({{ d.historial.length }})</summary>
                        <ol>
                            <li v-for="(h, k) in [...d.historial].reverse()" :key="k">
                                <strong>{{ etiquetaResultado(h.resultado) }}</strong> · {{ h.coordinador }} · {{ h.fecha }}
                                <span v-if="h.observacion"> — {{ h.observacion }}</span>
                            </li>
                        </ol>
                    </details>
                </article>
            </div>
            <p class="coord-documentos-no-aplican">
                La revisión documental no califica ni dictamina la solicitud. Las observaciones son internas (no se envían al solicitante en esta fase).
            </p>
        </section>

        <div v-if="panel.abierto" class="prototipo-velo" @click.self="cerrar" @keydown.esc="cerrar">
            <div ref="dialogo" class="prototipo-dialogo" role="dialog" aria-modal="true" aria-labelledby="proto-dialogo-titulo">
                <h2 id="proto-dialogo-titulo">Revisar: {{ panel.documento.nombre }}</h2>
                <p class="prototipo-dialogo-sub">
                    Carga: <strong>{{ CARGA[panel.documento.carga] }}</strong> ·
                    Resultado actual: <strong>{{ etiquetaResultado(resultado(panel.documento)) }}</strong>
                </p>
                <fieldset class="prototipo-opciones">
                    <legend>Resultado de revisión</legend>
                    <label v-for="(r, clave) in RESULTADOS" :key="clave" :class="{ inhabilitado: clave === 'validado' && panel.documento.carga !== 'cargado' }">
                        <input v-model="panel.resultado" type="radio" name="resultado" :value="clave"
                               :disabled="clave === 'validado' && panel.documento.carga !== 'cargado'">
                        {{ r.etiqueta }}
                        <small v-if="clave === 'validado' && panel.documento.carga !== 'cargado'">(sin archivo disponible)</small>
                    </label>
                </fieldset>
                <label class="prototipo-observacion">
                    <span>
                        Observaciones
                        <template v-if="panel.resultado && panel.resultado !== 'validado'">(obligatorias)</template>
                        <template v-else-if="ultimo(panel.documento)">(justificación obligatoria al cambiar el resultado)</template>
                    </span>
                    <textarea v-model="panel.observacion" rows="3" maxlength="1000"></textarea>
                </label>
                <p v-if="panel.error" class="anfeca-aviso anfeca-aviso--error prototipo-error" role="alert">{{ panel.error }}</p>
                <div class="prototipo-dialogo-acciones">
                    <button type="button" class="coord-boton secundario" @click="cerrar">Cancelar</button>
                    <button type="button" class="coord-boton" @click="registrar">Registrar (demostración)</button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.prototipo-indicadores {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
    gap: 6px 10px;
    padding: 10px 14px;
    margin-bottom: 12px;
}
.prototipo-indicador { display: flex; flex-direction: column; }
.prototipo-indicador-valor { font-size: 20px; font-weight: 700; color: #bd2434; line-height: 1.15; }
.prototipo-indicador.destacado .prototipo-indicador-valor { color: #256029; }
.prototipo-indicador-etiqueta { font-size: 11.5px; color: #66717d; }
.prototipo-simulacion { grid-column: 1 / -1; font-size: 12px; color: #66717d; display: flex; gap: 6px; align-items: center; }

.prototipo-documento { grid-template-columns: auto minmax(0, 1fr) auto; }
.prototipo-estados { display: flex; flex-wrap: wrap; gap: 4px; }
.prototipo-acciones { display: flex; gap: 6px; align-items: center; }
.prototipo-auditoria { grid-column: 1 / -1; font-size: 12px; color: #4a4a4a; }
.prototipo-auditoria summary { cursor: pointer; color: #bd2434; font-weight: 600; }
.prototipo-auditoria ol { margin: 4px 0 0; padding-left: 18px; }

.rev-por-revisar { background: #f4f4f4; border-color: #e2e2e2; color: #555; }
.rev-validado { background: #e8f5ea; border-color: #9fd0a8; color: #1e5a26; }
.rev-observaciones { background: #fff4e0; border-color: #e8c27a; color: #6b4a00; }
.rev-no-valido { background: #fdecee; border-color: #e3a3ab; color: #8a1c2b; }
.rev-no-aplica { background: transparent; border-color: #e2e2e2; color: #8a8a8a; }

.prototipo-velo { position: fixed; inset: 0; background: rgba(20, 20, 20, .35); display: grid; place-items: center; z-index: 50; padding: 16px; }
.prototipo-dialogo { width: min(520px, 100%); background: #fff; border-radius: 10px; padding: 16px 18px; box-shadow: 0 12px 32px rgba(0, 0, 0, .2); }
.prototipo-dialogo h2 { margin: 0 0 4px; font-size: 16px; color: #bd151a; }
.prototipo-dialogo-sub { margin: 0 0 10px; font-size: 12.5px; color: #66717d; }
.prototipo-opciones { border: 1px solid #e6d8da; border-radius: 8px; padding: 8px 12px; margin: 0 0 10px; display: flex; flex-wrap: wrap; gap: 6px 16px; }
.prototipo-opciones legend { font-size: 12px; color: #66717d; padding: 0 4px; }
.prototipo-opciones label { display: inline-flex; gap: 6px; align-items: center; font-size: 13.5px; }
.prototipo-opciones label.inhabilitado { color: #9a9a9a; }
.prototipo-observacion { display: flex; flex-direction: column; gap: 4px; font-size: 12.5px; color: #66717d; }
.prototipo-observacion textarea { font: inherit; font-size: 13px; padding: 6px 8px; border: 1px solid #d6cfcf; border-radius: 6px; resize: vertical; color: #26313c; }
.prototipo-error { margin: 8px 0 0; padding: 8px 10px; font-size: 12.5px; }
.prototipo-dialogo-acciones { display: flex; justify-content: flex-end; gap: 8px; margin-top: 12px; }
</style>
