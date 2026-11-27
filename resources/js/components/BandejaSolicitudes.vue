<script setup>
import { onMounted, reactive, ref, watch } from 'vue';

const props = defineProps({
    urlApi: { type: String, required: true },
    urlExpediente: { type: String, required: true },
    opciones: { type: Object, required: true }
});

const filtros = reactive({ q: '', estado: '', tipo_tramite: '', certificacion: '', zona: '', institucion: '' });
const orden = reactive({ campo: 'fecha', direccion: 'desc' });
const pagina = ref(1);
const porPagina = ref(25);

const filas = ref([]);
const meta = ref(null);
const cargando = ref(false);
const error = ref('');

const columnas = [
    { clave: 'folio', etiqueta: 'Folio', orden: 'folio', clase: 'col-folio' },
    { clave: 'zona', etiqueta: 'Zona', orden: 'zona', clase: 'col-zona' },
    { clave: 'nombre', etiqueta: 'Nombre', orden: 'nombre', clase: 'col-nombre' },
    { clave: 'institucion', etiqueta: 'Institución', orden: 'institucion', clase: 'col-institucion' },
    { clave: 'facultad', etiqueta: 'Facultad', clase: 'col-facultad' },
    { clave: 'tramite', etiqueta: 'Modalidad', orden: 'tramite', clase: 'col-tramite' },
    { clave: 'certificacion', etiqueta: 'Certificación', orden: 'certificacion', clase: 'col-certificacion' },
    { clave: 'tipo_personal', etiqueta: 'Tipo de personal', clase: 'col-personal' },
    { clave: 'area', etiqueta: 'Área de conocimiento', clase: 'col-area' },
    { clave: 'certificado_historico', etiqueta: 'Certificado histórico', clase: 'col-certificado' },
    { clave: 'fecha_envio', etiqueta: 'Fecha de envío', orden: 'fecha', clase: 'col-fecha' },
    { clave: 'estado', etiqueta: 'Estado', orden: 'estado', clase: 'col-estado' }
];

let controlador = null;
let espera = null;

async function cargar() {
    controlador?.abort();
    controlador = new AbortController();
    cargando.value = true;
    error.value = '';

    const parametros = new URLSearchParams();
    Object.entries(filtros).forEach(([k, v]) => { if (v !== '') parametros.set(k, v); });
    parametros.set('orden', orden.campo);
    parametros.set('direccion', orden.direccion);
    parametros.set('pagina', String(pagina.value));
    parametros.set('por_pagina', String(porPagina.value));

    try {
        const r = await fetch(`${props.urlApi}?${parametros}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            signal: controlador.signal
        });
        if (!r.ok) {
            throw new Error(r.status === 403 ? 'Filtro fuera de su alcance.' : `HTTP ${r.status}`);
        }
        const json = await r.json();
        filas.value = json.datos;
        meta.value = json.meta;
    } catch (e) {
        if (e.name !== 'AbortError') {
            error.value = e.message.startsWith('HTTP') ? 'No fue posible obtener las solicitudes.' : e.message;
            filas.value = [];
        }
    } finally {
        cargando.value = false;
    }
}

function ordenar(campo) {
    if (!campo) return;
    if (orden.campo === campo) {
        orden.direccion = orden.direccion === 'asc' ? 'desc' : 'asc';
    } else {
        orden.campo = campo;
        orden.direccion = campo === 'fecha' || campo === 'folio' ? 'desc' : 'asc';
    }
    pagina.value = 1;
    cargar();
}

function indicadorOrden(campo) {
    if (orden.campo !== campo) return '';
    return orden.direccion === 'asc' ? '▲' : '▼';
}

function limpiar() {
    Object.keys(filtros).forEach(k => { filtros[k] = ''; });
    pagina.value = 1;
    cargar();
}

function irA(n) {
    pagina.value = n;
    cargar();
}

watch(() => ({ ...filtros, por: porPagina.value }), () => {
    clearTimeout(espera);
    espera = setTimeout(() => { pagina.value = 1; cargar(); }, 300);
});

onMounted(cargar);
</script>

<template>
    <div class="coord-bandeja">
        <form class="coord-filtros" role="search" @submit.prevent="cargar">
            <label class="coord-filtro-busqueda">
                Buscar
                <input v-model.trim="filtros.q" type="search" maxlength="100"
                       placeholder="Nombre, institución, folio o certificado histórico">
            </label>
            <label>Estado
                <select v-model="filtros.estado">
                    <option value="">Todos</option>
                    <option v-for="o in opciones.estados" :key="o.valor" :value="o.valor">{{ o.etiqueta }}</option>
                </select>
            </label>
            <label>Modalidad
                <select v-model="filtros.tipo_tramite">
                    <option value="">Todas</option>
                    <option v-for="o in opciones.tramites" :key="o.valor" :value="o.valor">{{ o.etiqueta }}</option>
                </select>
            </label>
            <label>Certificación
                <select v-model="filtros.certificacion">
                    <option value="">Todas</option>
                    <option v-for="o in opciones.certificaciones" :key="o.valor" :value="o.valor">{{ o.etiqueta }}</option>
                </select>
            </label>
            <label>Zona
                <select v-model="filtros.zona">
                    <option value="">Todas en su alcance</option>
                    <option v-for="o in opciones.zonas" :key="o.valor" :value="o.valor">{{ o.etiqueta }}</option>
                </select>
            </label>
            <label>Institución
                <select v-model="filtros.institucion">
                    <option value="">Todas</option>
                    <option v-for="o in opciones.instituciones" :key="o.valor" :value="o.valor">{{ o.etiqueta }}</option>
                </select>
            </label>
            <label>Por página
                <select v-model.number="porPagina">
                    <option :value="10">10</option>
                    <option :value="25">25</option>
                    <option :value="50">50</option>
                </select>
            </label>
            <div style="display: flex; align-items: flex-end;">
                <button type="button" class="coord-boton secundario" @click="limpiar">Limpiar filtros</button>
            </div>
        </form>

        <div v-if="meta" class="coord-resumen" aria-live="polite">
            <span class="coord-insignia neutra">Resultados: {{ meta.total }}</span>
            <span v-if="meta.total" class="coord-rango">Mostrando {{ meta.desde }}–{{ meta.hasta }} de {{ meta.total }}</span>
            <span class="coord-insignia enviado">Enviadas: {{ meta.por_estado.enviado }}</span>
            <span class="coord-insignia borrador">Iniciadas: {{ meta.por_estado.borrador }}</span>
            <span class="coord-nota">Participación: pendiente de definición · Calificación: fase 6.4 · Convocatoria: pendiente de validación</span>
        </div>

        <div v-if="error" class="anfeca-aviso anfeca-aviso--error" role="alert" style="margin-bottom: 10px;">{{ error }}</div>

        <div class="coord-tabla-contenedor" :aria-busy="cargando">
            <table class="coord-tabla">
                <thead>
                    <tr>
                        <th scope="col" class="col-num" title="Consecutivo visual; no es el folio">Núm.</th>
                        <th v-for="c in columnas" :key="c.clave" scope="col" :class="c.clase"
                            :aria-sort="c.orden && orden.campo === c.orden ? (orden.direccion === 'asc' ? 'ascending' : 'descending') : undefined">
                            <button v-if="c.orden" type="button" class="coord-th-boton" @click="ordenar(c.orden)">
                                {{ c.etiqueta }} <span aria-hidden="true">{{ indicadorOrden(c.orden) }}</span>
                            </button>
                            <template v-else>{{ c.etiqueta }}</template>
                        </th>
                        <th scope="col" class="col-acciones">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!cargando && !filas.length">
                        <td :colspan="columnas.length + 2" class="coord-cargando">No hay solicitudes con los criterios seleccionados.</td>
                    </tr>
                    <tr v-for="f in filas" :key="f.id ?? f.folio">
                        <td class="col-num">{{ f.num }}</td>
                        <td class="numero col-folio">{{ f.folio }}</td>
                        <td class="col-zona">
                            <span v-if="f.zona_clasificada">{{ f.zona }}</span>
                            <span v-else class="coord-insignia sin-zona" title="No hay información suficiente para identificar la zona">Sin clasificar</span>
                        </td>
                        <td class="col-nombre">{{ f.nombre || '—' }}</td>
                        <td class="col-institucion">{{ f.institucion || '—' }}</td>
                        <td class="col-facultad">{{ f.facultad || '—' }}</td>
                        <td class="col-tramite">{{ f.tramite }}</td>
                        <td class="col-certificacion">{{ f.certificacion || '—' }}</td>
                        <td class="col-personal">{{ f.tipo_personal || '—' }}</td>
                        <td class="col-area">{{ f.area || '—' }}</td>
                        <td class="col-certificado">{{ f.certificado_historico || '—' }}</td>
                        <td class="col-fecha">{{ f.fecha_envio || '—' }}</td>
                        <td class="col-estado">
                            <span class="coord-insignia" :class="f.estado === 'enviado' ? 'enviado' : 'borrador'">
                                {{ f.estado_etiqueta ?? (f.estado === 'enviado' ? 'Enviada' : 'Iniciada') }}
                            </span>
                        </td>
                        <td class="col-acciones"><a :href="`${urlExpediente}/${f.id ?? f.folio}`" class="coord-boton secundario pequeno">Consultar</a></td>
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
.coord-resumen {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
}
.coord-rango {
    font-size: 12px;
    color: #555;
}
.coord-nota {
    font-size: 12px;
    color: #777;
    margin-left: auto;
}
</style>
