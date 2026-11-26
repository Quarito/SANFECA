<script setup>
/*
 * Selector compacto con búsqueda integrada para catálogos extensos (estándar UI/UX, catálogos extensos).
 * - Disparador de 2 líneas visibles (título completo en el atributo title y en la lista).
 * - Lista FLOTANTE de posición fija (no altera la altura del documento ni desplaza el formulario),
 *   máximo ≈ 200 px con desplazamiento interno; se abre hacia arriba si no hay espacio abajo.
 * - Búsqueda parcial, por palabras, sin acentos ni mayúsculas; orden recibido (alfabético).
 * - Teclado: ↑/↓, Inicio/Fin, Re Pág/Av Pág, Intro, Escape; cierre con clic fuera y al seleccionar.
 * - Opción final opcional («No encuentro mi título») que emite «manual».
 * - Modo remoto opcional (prop «buscar»): para catálogos muy extensos la búsqueda se hace en el servidor; el navegador
 *   nunca recibe el catálogo completo. Si hay más coincidencias que el límite se pide afinar la búsqueda (ningún título
 *   queda inaccesible). Sin «buscar», el comportamiento es el original (filtrado local de «opciones»).
 */
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    id: { type: String, required: true },
    modelValue: { type: String, default: '' },          // clave seleccionada
    opciones: { type: Array, default: () => [] },       // [{ clave, titulo }]
    textoSeleccionado: { type: String, default: '' },   // título a mostrar (p. ej., valor guardado)
    placeholder: { type: String, default: 'Seleccione' },
    deshabilitado: { type: Boolean, default: false },
    cargando: { type: Boolean, default: false },
    opcionManual: { type: String, default: '' },
    invalido: { type: Boolean, default: false },
    buscar: { type: Function, default: null },          // async (texto) => { total, titulos: [{ clave, titulo }] }
    minimo: { type: Number, default: 3 },
    ayudaBusqueda: { type: String, default: 'Escriba al menos 3 letras de su título.' }
});
const emit = defineEmits(['update:modelValue', 'manual', 'abrir', 'elegido']);

const abierto = ref(false);
const busqueda = ref('');
const activo = ref(-1);
const disparador = ref(null);
const lista = ref(null);
const campoBusqueda = ref(null);
const posicion = ref({});
const idLista = `${props.id}-lista`;

const normalizar = t => (t || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();

const remotos = ref([]);
const totalRemoto = ref(0);
const buscando = ref(false);
const errorRemoto = ref('');
let temporizador = null;
let consulta = 0;
const busquedaCorta = computed(() => Boolean(props.buscar) && normalizar(busqueda.value).replace(/ /g, '').length < props.minimo);

const filtradas = computed(() => {
    if (props.buscar) return remotos.value;
    const palabras = normalizar(busqueda.value).split(' ').filter(Boolean);
    return props.opciones.filter(o => {
        const n = normalizar(o.titulo);
        return palabras.every(p => n.includes(p));
    });
});
const elementos = computed(() => props.opcionManual
    ? [...filtradas.value, { clave: '__manual__', titulo: props.opcionManual, manual: true }]
    : filtradas.value);
const mostrado = computed(() => props.textoSeleccionado
    || props.opciones.find(o => o.clave === props.modelValue)?.titulo || '');

function colocar() {
    const r = disparador.value?.getBoundingClientRect();
    if (!r) return;
    const alto = 248;                                    // búsqueda + lista de ≈ 200 px
    const abajo = window.innerHeight - r.bottom;
    const arriba = r.top;
    const haciaArriba = abajo < alto && arriba > abajo;
    posicion.value = {
        left: `${Math.max(8, r.left)}px`,
        width: `${Math.min(r.width, window.innerWidth - 16)}px`,
        ...(haciaArriba ? { bottom: `${window.innerHeight - r.top + 4}px` } : { top: `${r.bottom + 4}px` }),
        '--alto-lista': `${Math.max(120, Math.min(200, (haciaArriba ? arriba : abajo) - 60))}px`
    };
}

async function abrir() {
    if (props.deshabilitado) return;
    emit('abrir');
    abierto.value = true;
    busqueda.value = '';
    if (props.buscar) { remotos.value = []; totalRemoto.value = 0; errorRemoto.value = ''; }
    activo.value = Math.max(0, elementos.value.findIndex(o => o.clave === props.modelValue));
    colocar();
    await nextTick();
    campoBusqueda.value?.focus({ preventScroll: true });
    desplazarActivo();
}

function cerrar(devolverFoco = true) {
    abierto.value = false;
    if (devolverFoco) disparador.value?.focus({ preventScroll: true });
}

function elegir(o) {
    if (o.manual) emit('manual');
    else { emit('update:modelValue', o.clave); emit('elegido', o); }
    cerrar();
}

function desplazarActivo() {
    nextTick(() => lista.value?.querySelector('[data-activo="true"]')?.scrollIntoView({ block: 'nearest' }));
}

function teclado(e) {
    const n = elementos.value.length;
    const mover = d => { activo.value = n ? Math.min(Math.max(activo.value + d, 0), n - 1) : -1; desplazarActivo(); e.preventDefault(); };
    switch (e.key) {
        case 'ArrowDown': return mover(1);
        case 'ArrowUp': return mover(-1);
        case 'PageDown': return mover(6);
        case 'PageUp': return mover(-6);
        case 'Home': if (e.target === campoBusqueda.value && busqueda.value) return; activo.value = 0; desplazarActivo(); e.preventDefault(); return;
        case 'End': if (e.target === campoBusqueda.value && busqueda.value) return; activo.value = n - 1; desplazarActivo(); e.preventDefault(); return;
        case 'Enter': e.preventDefault(); if (elementos.value[activo.value]) elegir(elementos.value[activo.value]); return;
        case 'Escape': e.preventDefault(); e.stopPropagation(); cerrar(); return;
        case 'Tab': cerrar(false);
    }
}

function teclaDisparador(e) {
    if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(e.key)) { e.preventDefault(); abrir(); }
}

function clicFuera(e) {
    if (!abierto.value) return;
    if (!lista.value?.closest('.selector-flotante')?.contains(e.target) && !disparador.value?.contains(e.target)) cerrar(false);
}

watch(busqueda, (texto) => {
    activo.value = elementos.value.length ? 0 : -1;
    if (!props.buscar) return;
    clearTimeout(temporizador);
    errorRemoto.value = '';
    if (busquedaCorta.value) { remotos.value = []; totalRemoto.value = 0; buscando.value = false; return; }
    buscando.value = true;
    const numero = ++consulta;
    temporizador = setTimeout(async () => {
        try {
            const r = await props.buscar(texto);
            if (numero !== consulta) return;
            remotos.value = Array.isArray(r?.titulos) ? r.titulos : [];
            totalRemoto.value = Number(r?.total ?? remotos.value.length);
            activo.value = elementos.value.length ? 0 : -1;
        } catch (e) {
            if (numero !== consulta) return;
            remotos.value = []; totalRemoto.value = 0;
            errorRemoto.value = 'No fue posible consultar el catálogo. Puede elegir «' + (props.opcionManual || 'captura manual') + '».';
        } finally {
            if (numero === consulta) buscando.value = false;
        }
    }, 250);
});
watch(abierto, v => {
    const accion = v ? 'addEventListener' : 'removeEventListener';
    document[accion]('mousedown', clicFuera);
    window[accion]('resize', colocar);
    window[accion]('scroll', colocar, true);
});
onBeforeUnmount(() => {
    document.removeEventListener('mousedown', clicFuera);
    window.removeEventListener('resize', colocar);
    window.removeEventListener('scroll', colocar, true);
});
</script>

<template>
    <div class="selector-buscable" :class="{ invalido }">
        <button :id="id" ref="disparador" type="button" class="selector-disparador" :disabled="deshabilitado"
                role="combobox" :aria-expanded="abierto" aria-haspopup="listbox" :aria-controls="idLista"
                :title="mostrado || placeholder" @click="abierto ? cerrar() : abrir()" @keydown="teclaDisparador">
            <span class="selector-texto" :class="{ vacio: !mostrado }">{{ cargando ? 'Cargando títulos…' : (mostrado || placeholder) }}</span>
            <span class="selector-flecha" aria-hidden="true">▾</span>
        </button>
        <Teleport to="body">
            <div v-if="abierto" class="selector-flotante" :style="posicion" @keydown="teclado">
                <input ref="campoBusqueda" v-model="busqueda" type="search" class="selector-busqueda" placeholder="Buscar título…"
                       :aria-controls="idLista" aria-autocomplete="list" maxlength="100"
                       :aria-activedescendant="activo >= 0 ? `${idLista}-${activo}` : undefined">
                <ul :id="idLista" ref="lista" role="listbox" class="selector-lista" :aria-label="placeholder">
                    <li v-for="(o, i) in elementos" :id="`${idLista}-${i}`" :key="o.clave" role="option"
                        :aria-selected="o.clave === modelValue" :data-activo="i === activo"
                        :class="{ activo: i === activo, seleccionado: o.clave === modelValue, manual: o.manual }"
                        @mousedown.prevent="elegir(o)" @mousemove="activo = i">{{ o.titulo }}</li>
                    <li v-if="buscar && busquedaCorta" class="selector-vacio" role="presentation">{{ ayudaBusqueda }}</li>
                    <li v-else-if="buscar && buscando" class="selector-vacio" role="presentation">Buscando…</li>
                    <li v-else-if="errorRemoto" class="selector-vacio" role="presentation">{{ errorRemoto }}</li>
                    <li v-else-if="!filtradas.length" class="selector-vacio" role="presentation">Sin coincidencias para «{{ busqueda }}».</li>
                    <li v-else-if="buscar && totalRemoto > filtradas.length" class="selector-vacio" role="presentation">
                        Se muestran {{ filtradas.length }} de {{ totalRemoto }} coincidencias; escriba más palabras para afinar.
                    </li>
                </ul>
            </div>
        </Teleport>
    </div>
</template>

<style scoped>
.selector-buscable { width: 100%; min-width: 0; }
.selector-disparador {
    width: 100%; min-height: 46px; max-height: 48px; display: flex; align-items: center; gap: 6px;
    padding: 3px 8px; text-align: left; background: #fff; color: #26313c; font: inherit; font-size: 12.5px;
    border: 1px solid #b9b9b9; border-radius: 6px; cursor: pointer; line-height: 1.25;
}
.selector-disparador:disabled { background: #eee; cursor: not-allowed; }
.selector-disparador:focus-visible { outline: 2px solid #bd2434; outline-offset: 1px; }
.invalido .selector-disparador { border-color: #c62828; }
.selector-texto { flex: 1; min-width: 0; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow-wrap: anywhere; }
.selector-texto.vacio { color: #777; }
.selector-flecha { flex: 0 0 auto; color: #555; }
</style>

<style>
/* Lista flotante (teletransportada a <body>): no participa del flujo del formulario. */
.selector-flotante { position: fixed; z-index: 70; background: #fff; border: 1px solid #b9b9b9; border-radius: 6px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, .18); padding: 4px; display: flex; flex-direction: column; gap: 4px; max-width: calc(100vw - 16px); }
.selector-flotante .selector-busqueda { font: inherit; font-size: 12.5px; padding: 5px 8px; border: 1px solid #d6cfcf; border-radius: 5px; width: 100%; box-sizing: border-box; }
.selector-flotante .selector-lista { list-style: none; margin: 0; padding: 0; max-height: var(--alto-lista, 200px); overflow-y: auto; overscroll-behavior: contain; }
.selector-flotante .selector-lista li { padding: 5px 8px; font-size: 12.5px; line-height: 1.3; border-radius: 4px; cursor: pointer; overflow-wrap: anywhere; }
.selector-flotante .selector-lista li.activo { background: #fcf3f3; }
.selector-flotante .selector-lista li.seleccionado { font-weight: 600; color: #bd2434; }
.selector-flotante .selector-lista li.manual { border-top: 1px solid #e6d8da; margin-top: 2px; color: #bd2434; font-style: italic; }
.selector-flotante .selector-vacio { color: #777; cursor: default; }
</style>
