<script setup>
/* Bandeja de seguimiento de registros (bloque D): solo lectura, filtros y paginación en el servidor. */
import { onMounted, reactive, ref, watch } from 'vue';

const props = defineProps({
    urlApi: { type: String, required: true },
    urlDetalle: { type: String, required: true },
    opciones: { type: Object, required: true },
    filtrosIniciales: { type: Object, default: () => ({}) }
});

const filtros = reactive({
    q: '', zona: '', institucion: '', tipo_tramite: '', certificacion: '', estado: '', etapa: '',
    registro_desde: '', registro_hasta: '', avance_desde: '', avance_hasta: '', sin_avance: '',
    ...props.filtrosIniciales
});
const orden = reactive({ campo: 'registro', direccion: 'desc' });
const pagina = ref(1);
const porPagina = ref(25);
const filas = ref([]);
const meta = ref(null);
const cargando = ref(false);
const error = ref('');
let controlador = null;
let espera = null;

async function cargar() {
    controlador?.abort();
    controlador = new AbortController();
    cargando.value = true;
    error.value = '';
    const p = new URLSearchParams();
    Object.entries(filtros).forEach(([k, v]) => { if (v !== '' && v !== null && v !== undefined) p.set(k, v); });
    p.set('orden', orden.campo);
    p.set('direccion', orden.direccion);
    p.set('pagina', String(pagina.value));
    p.set('por_pagina', String(porPagina.value));
    try {
        const r = await fetch(`${props.urlApi}?${p}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', signal: controlador.signal });
        if (!r.ok) throw new Error(r.status === 403 ? 'Filtro fuera de su alcance.' : 'No fue posible obtener los registros.');
        const json = await r.json();
        filas.value = json.datos;
        meta.value = json.meta;
    } catch (e) {
        if (e.name !== 'AbortError') { error.value = e.message; filas.value = []; }
    } finally {
        cargando.value = false;
    }
}

function ordenar(campo) {
    if (orden.campo === campo) orden.direccion = orden.direccion === 'asc' ? 'desc' : 'asc';
    else { orden.campo = campo; orden.direccion = campo === 'solicitante' ? 'asc' : 'desc'; }
    pagina.value = 1;
    cargar();
}
function indicador(campo) { return orden.campo !== campo ? '' : (orden.direccion === 'asc' ? '▲' : '▼'); }
function limpiar() { Object.keys(filtros).forEach(k => { filtros[k] = ''; }); pagina.value = 1; cargar(); }
function irA(n) { pagina.value = n; cargar(); }

watch(() => ({ ...filtros, por: porPagina.value }), () => {
    clearTimeout(espera);
    espera = setTimeout(() => { pagina.value = 1; cargar(); }, 300);
});
onMounted(cargar);
</script>

<template>
    <div class="coord-bandeja">
        <form class="coord-filtros" role="search" @submit.prevent="cargar">
            <label class="coord-filtro-busqueda">Buscar
                <input v-model.trim="filtros.q" type="search" maxlength="100" placeholder="Nombre, correo o folio">
            </label>
            <label>Estado
                <select v-model="filtros.estado"><option value="">Todos</option><option v-for="o in opciones.estados" :key="o.valor" :value="o.valor">{{ o.etiqueta }}</option></select>
            </label>
            <label>Etapa actual
                <select v-model="filtros.etapa"><option value="">Todas</option><option v-for="o in opciones.etapas" :key="o.valor" :value="o.valor">{{ o.etiqueta }}</option></select>
            </label>
            <label>Zona
                <select v-model="filtros.zona"><option value="">Todas en su alcance</option><option v-for="o in opciones.zonas" :key="o.valor" :value="o.valor">{{ o.etiqueta }}</option></select>
            </label>
            <label>Institución
                <select v-model="filtros.institucion"><option value="">Todas</option><option v-for="o in opciones.instituciones" :key="o.valor" :value="o.valor">{{ o.etiqueta }}</option></select>
            </label>
            <label>Modalidad
                <select v-model="filtros.tipo_tramite"><option value="">Todas</option><option v-for="o in opciones.tramites" :key="o.valor" :value="o.valor">{{ o.etiqueta }}</option></select>
            </label>
            <label>Certificación
                <select v-model="filtros.certificacion"><option value="">Todas</option><option v-for="o in opciones.certificaciones" :key="o.valor" :value="o.valor">{{ o.etiqueta }}</option></select>
            </label>
            <label>Registro desde <input v-model="filtros.registro_desde" type="date"></label>
            <label>Registro hasta <input v-model="filtros.registro_hasta" type="date"></label>
            <label>Último avance desde <input v-model="filtros.avance_desde" type="date"></label>
            <label>Último avance hasta <input v-model="filtros.avance_hasta" type="date"></label>
            <label title="Registros no enviados cuyo último avance registrado es anterior a N días (no implica abandono)">Días sin avance
                <select v-model="filtros.sin_avance"><option value="">—</option><option v-for="d in ['3', '7', '15', '30']" :key="d" :value="d">{{ d }} o más</option></select>
            </label>
            <label>Por página
                <select v-model.number="porPagina"><option :value="10">10</option><option :value="25">25</option><option :value="50">50</option></select>
            </label>
            <div style="display: flex; align-items: flex-end;"><button type="button" class="coord-boton secundario" @click="limpiar">Limpiar filtros</button></div>
        </form>

        <div v-if="meta" class="coord-resumen" aria-live="polite">
            <span class="coord-insignia neutra">Registros: {{ meta.total }}</span>
            <span v-if="meta.total" class="coord-rango">Mostrando {{ meta.desde }}–{{ meta.hasta }} de {{ meta.total }}</span>
            <span class="coord-nota">Cada fila es una persona con su trámite actual · «Avance» = etapas de captura completadas (no incluye el envío)</span>
        </div>
        <div v-if="error" class="anfeca-aviso anfeca-aviso--error" role="alert" style="margin-bottom: 10px;">{{ error }}</div>

        <div class="coord-tabla-contenedor" :aria-busy="cargando">
            <table class="coord-tabla">
                <thead>
                    <tr>
                        <th scope="col" class="col-num" title="Consecutivo visual; no es el folio">Núm.</th>
                        <th scope="col"><button type="button" class="coord-th-boton" @click="ordenar('folio')">Folio <span aria-hidden="true">{{ indicador('folio') }}</span></button></th>
                        <th scope="col"><button type="button" class="coord-th-boton" @click="ordenar('solicitante')">Solicitante <span aria-hidden="true">{{ indicador('solicitante') }}</span></button></th>
                        <th scope="col">Correo electrónico</th>
                        <th scope="col">Institución</th>
                        <th scope="col">Zona</th>
                        <th scope="col">Tipo de trámite</th>
                        <th scope="col">Certificación</th>
                        <th scope="col">Etapa actual</th>
                        <th scope="col"><button type="button" class="coord-th-boton" @click="ordenar('avance')">Avance <span aria-hidden="true">{{ indicador('avance') }}</span></button></th>
                        <th scope="col"><button type="button" class="coord-th-boton" @click="ordenar('registro')">Fecha de registro <span aria-hidden="true">{{ indicador('registro') }}</span></button></th>
                        <th scope="col"><button type="button" class="coord-th-boton" @click="ordenar('avance_registrado')">Último avance registrado <span aria-hidden="true">{{ indicador('avance_registrado') }}</span></button></th>
                        <th scope="col">Estado</th>
                        <th scope="col">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!cargando && !filas.length"><td colspan="14" class="coord-cargando">No hay registros con los criterios seleccionados.</td></tr>
                    <tr v-for="f in filas" :key="f.clave">
                        <td class="col-num">{{ f.num }}</td>
                        <td class="numero">{{ f.folio ?? 'Sin folio' }}</td>
                        <td>{{ f.solicitante }}</td>
                        <td>{{ f.correo }}</td>
                        <td>{{ f.institucion || '—' }}</td>
                        <td><span v-if="f.zona_clasificada">{{ f.zona }}</span><span v-else class="coord-insignia sin-zona">Sin clasificar</span></td>
                        <td>{{ f.tipo_tramite || '—' }}</td>
                        <td>{{ f.certificacion || '—' }}</td>
                        <td>{{ f.etapa }}</td>
                        <td><div class="seg-avance" :title="`${f.completadas} de ${f.total} etapas`"><span class="seg-barra"><span :style="{ width: f.avance + '%' }"></span></span>{{ f.avance }} %</div><small>{{ f.completadas }}/{{ f.total }}</small></td>
                        <td>{{ f.fecha_registro }}</td>
                        <td>{{ f.ultimo_avance || '—' }}</td>
                        <td><span class="coord-insignia" :class="`seg-${f.clasificacion}`">{{ f.estado }}</span></td>
                        <td><a :href="`${urlDetalle}/${f.clave}`" class="coord-boton secundario pequeno">Consultar</a></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="meta && meta.ultima_pagina > 1" class="coord-paginacion">
            <span>Página {{ meta.pagina }} de {{ meta.ultima_pagina }}</span>
            <div class="coord-paginacion-botones">
                <button type="button" class="coord-boton secundario" :disabled="meta.pagina <= 1" @click="irA(meta.pagina - 1)">Anterior</button>
                <button type="button" class="coord-boton secundario" :disabled="meta.pagina >= meta.ultima_pagina" @click="irA(meta.pagina + 1)">Siguiente</button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.coord-resumen { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 10px; }
.coord-rango { font-size: 12px; color: #555; }
.coord-nota { font-size: 12px; color: #777; margin-left: auto; }
</style>
