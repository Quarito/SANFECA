<script setup>
/*
 * Visor documental REUTILIZABLE (bloque C). Ventana modal dentro de la página: no descarga ni abre pestañas.
 * - PDF con PDF.js integrado localmente (pdfjs-dist, cargado bajo demanda; sin CDN ni servicios externos).
 * - Imágenes (JPG/PNG, admitidas por el módulo de solicitantes) con <img>.
 * - El archivo se obtiene de la ruta PROTEGIDA del expediente (autenticación, permisos y zona en el servidor).
 * - Panel lateral opcional (slot «panel»): la revisión documental se muestra al 30 % en escritorio y
 *   debajo del documento en móvil.
 * - Libera recursos al cerrar: cancela descargas y renders pendientes y destruye el documento PDF.
 */
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    abierto: { type: Boolean, default: false },
    url: { type: String, default: '' },              // ruta protegida (con ?v=<token> si hay revisión)
    urlDescarga: { type: String, default: '' },      // vacío = descarga no permitida
    titulo: { type: String, default: '' },
    folio: { type: [String, Number], default: '' },
    sinArchivo: { type: String, default: '' },       // motivo cuando no hay archivo (no se intenta cargar)
    hayCambiosSinGuardar: { type: Boolean, default: false }
});
const emit = defineEmits(['cerrar']);
const LOGO = '/images/logo-certificacion.png';   // servido desde public/, no se empaqueta

const contenedor = ref(null);
const lienzoCaja = ref(null);
const lienzo = ref(null);
const ventana = ref(null);
const estado = ref('inactivo');          // inactivo | cargando | listo | error
const mensajeError = ref('');
const tipo = ref('');                    // pdf | imagen
const urlImagen = ref('');
const pagina = ref(1);
const totalPaginas = ref(0);
const escala = ref(1);
const ajusteAncho = ref(true);
const pantallaCompleta = ref(false);

let controlador = null;
let tareaCarga = null;
let documentoPdf = null;
let tareaRender = null;
let observador = null;
let focoPrevio = null;
let desplazamiento = 0;

const porcentaje = computed(() => Math.round(escala.value * 100));

function seguro(paso) {
    try {
        paso();
    } catch {
        /* liberar nunca debe interrumpir el cierre del visor */
    }
}

function liberar() {
    seguro(() => controlador?.abort());
    controlador = null;
    seguro(() => tareaRender?.cancel());
    tareaRender = null;
    // En PDF.js 6 la tarea de carga destruye el documento y su worker (PDFDocumentProxy ya no expone destroy()).
    seguro(() => tareaCarga?.destroy());
    tareaCarga = null;
    seguro(() => typeof documentoPdf?.cleanup === 'function' && documentoPdf.cleanup());
    documentoPdf = null;
    if (urlImagen.value) {
        URL.revokeObjectURL(urlImagen.value);
        urlImagen.value = '';
    }
    observador?.disconnect();
    observador = null;
}

async function cargar() {
    liberar();
    estado.value = 'cargando';
    mensajeError.value = '';
    pagina.value = 1;
    totalPaginas.value = 0;
    if (props.sinArchivo) {
        estado.value = 'error';
        mensajeError.value = props.sinArchivo;
        return;
    }
    controlador = new AbortController();
    try {
        const r = await fetch(props.url, { credentials: 'same-origin', signal: controlador.signal, headers: { Accept: 'application/pdf,image/*' } });
        if (!r.ok) {
            throw new Error({
                403: 'No tiene permiso para consultar este documento.',
                404: 'El documento no está disponible.',
                409: 'El archivo cambió desde que se abrió el expediente. Actualice la página para ver la versión vigente.'
            }[r.status] ?? `No fue posible obtener el documento (error ${r.status}).`);
        }
        const contenido = (r.headers.get('Content-Type') || '').split(';')[0].trim();
        const datos = await r.arrayBuffer();
        if (contenido === 'application/pdf') {
            tipo.value = 'pdf';
            // Compilación «legacy» de PDF.js: incluye compatibilidad para navegadores no tan recientes
            // (p. ej., sin Uint8Array.prototype.toHex). Se carga solo al abrir un documento.
            const pdfjs = await import('pdfjs-dist/legacy/build/pdf.mjs');
            const { default: urlTrabajador } = await import('pdfjs-dist/legacy/build/pdf.worker.min.mjs?url');
            pdfjs.GlobalWorkerOptions.workerSrc = urlTrabajador;
            tareaCarga = pdfjs.getDocument({ data: new Uint8Array(datos), stopAtErrors: false });
            documentoPdf = await tareaCarga.promise;
            totalPaginas.value = documentoPdf.numPages;
            estado.value = 'listo';
            await nextTick();
            observarAncho();
            await dibujar();
        } else if (contenido.startsWith('image/')) {
            tipo.value = 'imagen';
            urlImagen.value = URL.createObjectURL(new Blob([datos], { type: contenido }));
            estado.value = 'listo';
        } else {
            throw new Error('Formato de archivo no admitido por el visor.');
        }
    } catch (e) {
        if (e.name === 'AbortError') return;
        estado.value = 'error';
        mensajeError.value = /Invalid PDF|InvalidPDF|corrupt|damaged|FormatError/i.test(`${e.name} ${e.message}`)
            ? 'El PDF está dañado o no se puede interpretar.'
            : e.message;
    }
}

async function dibujar() {
    if (!documentoPdf || !lienzo.value) return;
    const doc = documentoPdf;
    seguro(() => tareaRender?.cancel());
    let pag;
    try {
        pag = await doc.getPage(pagina.value);
    } catch {
        return;                                   // el documento se liberó mientras tanto
    }
    if (doc !== documentoPdf) return;
    const base = pag.getViewport({ scale: 1 });
    if (ajusteAncho.value && lienzoCaja.value) {
        const ancho = Math.max(lienzoCaja.value.clientWidth - 24, 200);
        escala.value = Math.min(Math.max(ancho / base.width, 0.25), 4);
    }
    const proporcion = window.devicePixelRatio || 1;
    const vista = pag.getViewport({ scale: escala.value * proporcion });
    const c = lienzo.value;
    c.width = Math.floor(vista.width);
    c.height = Math.floor(vista.height);
    c.style.width = `${Math.floor(vista.width / proporcion)}px`;
    c.style.height = `${Math.floor(vista.height / proporcion)}px`;
    tareaRender = pag.render({ canvas: c, viewport: vista });
    try {
        await tareaRender.promise;
    } catch (e) {
        if (e?.name !== 'RenderingCancelledException') {
            estado.value = 'error';
            mensajeError.value = 'No fue posible mostrar esta página.';
        }
    }
}

function observarAncho() {
    if (!lienzoCaja.value || typeof ResizeObserver === 'undefined') return;
    let espera = null;
    observador = new ResizeObserver(() => {
        if (!ajusteAncho.value) return;
        clearTimeout(espera);
        espera = setTimeout(dibujar, 120);
    });
    observador.observe(lienzoCaja.value);
}

function irA(n) {
    if (!totalPaginas.value) return;
    pagina.value = Math.min(Math.max(1, n), totalPaginas.value);
    dibujar();
}
function zoom(factor) {
    ajusteAncho.value = false;
    escala.value = Math.min(Math.max(escala.value * factor, 0.25), 4);
    dibujar();
}
function alAncho() {
    ajusteAncho.value = true;
    dibujar();
}
async function alternarPantallaCompleta() {
    try {
        if (document.fullscreenElement) {
            await document.exitFullscreen();
        } else {
            await ventana.value?.requestFullscreen?.();
        }
    } catch {
        /* el navegador puede negarlo; el visor sigue funcionando */
    }
}
function alCambiarPantallaCompleta() {
    pantallaCompleta.value = document.fullscreenElement === ventana.value;
    if (ajusteAncho.value) setTimeout(dibujar, 150);
}

function solicitarCierre() {
    if (props.hayCambiosSinGuardar && !window.confirm('Hay cambios sin guardar en la revisión. ¿Desea cerrar el documento?')) {
        return;
    }
    emit('cerrar');
}

function teclado(e) {
    if (!props.abierto) return;
    const enCampo = ['TEXTAREA', 'INPUT', 'SELECT'].includes(e.target?.tagName);
    if (e.key === 'Escape') {
        if (document.fullscreenElement) return;          // el navegador sale de pantalla completa primero
        e.preventDefault();
        solicitarCierre();
    } else if (!enCampo && tipo.value === 'pdf') {
        if (['ArrowRight', 'PageDown'].includes(e.key)) { e.preventDefault(); irA(pagina.value + 1); }
        if (['ArrowLeft', 'PageUp'].includes(e.key)) { e.preventDefault(); irA(pagina.value - 1); }
        if (e.key === '+' || e.key === '=') { e.preventDefault(); zoom(1.2); }
        if (e.key === '-') { e.preventDefault(); zoom(1 / 1.2); }
    }
    if (e.key === 'Tab' && ventana.value) {                // foco atrapado dentro del modal
        const enfocables = [...ventana.value.querySelectorAll('button:not([disabled]), a[href], input:not([disabled]), textarea, select, summary, [tabindex="0"]')];
        if (!enfocables.length) return;
        const primero = enfocables[0];
        const ultimo = enfocables[enfocables.length - 1];
        if (e.shiftKey && document.activeElement === primero) { e.preventDefault(); ultimo.focus(); }
        if (!e.shiftKey && document.activeElement === ultimo) { e.preventDefault(); primero.focus(); }
    }
}

watch(() => [props.abierto, props.url, props.sinArchivo], async ([abierto]) => {
    if (abierto) {
        focoPrevio = document.activeElement;
        desplazamiento = window.scrollY;
        document.documentElement.classList.add('visor-abierto');
        document.addEventListener('keydown', teclado);
        document.addEventListener('fullscreenchange', alCambiarPantallaCompleta);
        await nextTick();
        ventana.value?.querySelector('.visor-cerrar')?.focus({ preventScroll: true });
        cargar();
    } else {
        liberar();
        estado.value = 'inactivo';
        document.documentElement.classList.remove('visor-abierto');
        document.removeEventListener('keydown', teclado);
        document.removeEventListener('fullscreenchange', alCambiarPantallaCompleta);
        window.scrollTo({ top: desplazamiento });       // conserva la posición del expediente
        focoPrevio?.focus?.({ preventScroll: true });
    }
});

onBeforeUnmount(() => {
    liberar();
    document.removeEventListener('keydown', teclado);
    document.removeEventListener('fullscreenchange', alCambiarPantallaCompleta);
});

defineExpose({ recargar: cargar });
</script>

<template>
    <div v-if="abierto" class="visor-velo" @click.self="solicitarCierre">
        <div ref="ventana" class="visor-ventana" :class="{ 'con-panel': !!$slots.panel }" role="dialog" aria-modal="true" aria-labelledby="visor-titulo">
            <header class="visor-encabezado">
                <img :src="LOGO" alt="" class="visor-logo">
                <div class="visor-titulos">
                    <h2 id="visor-titulo">{{ titulo }}</h2>
                    <p>Solicitud {{ folio }}<template v-if="tipo === 'pdf' && totalPaginas"> · {{ totalPaginas }} página(s)</template></p>
                </div>
                <div class="visor-herramientas" role="toolbar" aria-label="Controles del documento">
                    <template v-if="estado === 'listo' && tipo === 'pdf'">
                        <button type="button" class="coord-boton secundario pequeno" :disabled="pagina <= 1" aria-label="Página anterior" @click="irA(pagina - 1)">‹</button>
                        <span class="visor-pagina" aria-live="polite">Página <input type="number" min="1" :max="totalPaginas" :value="pagina" aria-label="Página actual" @change="irA(+$event.target.value)"> de {{ totalPaginas }}</span>
                        <button type="button" class="coord-boton secundario pequeno" :disabled="pagina >= totalPaginas" aria-label="Página siguiente" @click="irA(pagina + 1)">›</button>
                        <span class="visor-separador"></span>
                        <button type="button" class="coord-boton secundario pequeno" aria-label="Alejar" @click="zoom(1 / 1.2)">−</button>
                        <span class="visor-zoom">{{ porcentaje }} %</span>
                        <button type="button" class="coord-boton secundario pequeno" aria-label="Acercar" @click="zoom(1.2)">+</button>
                        <button type="button" class="coord-boton secundario pequeno" :aria-pressed="ajusteAncho" @click="alAncho">Ajustar al ancho</button>
                    </template>
                    <button type="button" class="coord-boton secundario pequeno" @click="alternarPantallaCompleta">{{ pantallaCompleta ? 'Salir de pantalla completa' : 'Pantalla completa' }}</button>
                    <a v-if="urlDescarga && estado === 'listo'" :href="urlDescarga" class="coord-boton secundario pequeno">Descargar</a>
                    <button type="button" class="coord-boton pequeno visor-cerrar" aria-label="Cerrar visor (Esc)" @click="solicitarCierre">Cerrar ✕</button>
                </div>
            </header>
            <div class="visor-cuerpo">
                <section ref="lienzoCaja" class="visor-documento" aria-label="Documento" tabindex="0" :data-estado="estado" :data-tipo="tipo">
                    <p v-if="estado === 'cargando'" class="visor-estado" role="status"><span class="visor-indicador" aria-hidden="true"></span> Cargando documento…</p>
                    <p v-else-if="estado === 'error'" class="visor-estado visor-error" role="alert">{{ mensajeError }}</p>
                    <canvas v-show="estado === 'listo' && tipo === 'pdf'" ref="lienzo" class="visor-lienzo"></canvas>
                    <img v-if="estado === 'listo' && tipo === 'imagen'" :src="urlImagen" :alt="titulo" class="visor-imagen">
                </section>
                <aside v-if="$slots.panel" class="visor-panel" aria-label="Revisión documental">
                    <slot name="panel" />
                </aside>
            </div>
        </div>
    </div>
</template>

<style scoped>
.visor-velo { position: fixed; inset: 0; background: rgba(20, 20, 20, .45); display: grid; place-items: center; z-index: 60; }
.visor-ventana { width: min(94vw, 1700px); height: 90vh; background: #fff; border-radius: 10px; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 16px 40px rgba(0, 0, 0, .3); }
.visor-ventana:fullscreen { width: 100vw; height: 100vh; border-radius: 0; }
.visor-encabezado { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 8px 12px; border-bottom: 3px solid #bd2434; background: #fcf3f3; }
.visor-logo { height: 34px; width: auto; }
.visor-titulos { flex: 1 1 220px; min-width: 0; }
.visor-titulos h2 { margin: 0; font-size: 15px; color: #bd151a; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.visor-titulos p { margin: 0; font-size: 12px; color: #66717d; }
.visor-herramientas { display: flex; align-items: center; gap: 4px; flex-wrap: wrap; }
.visor-pagina, .visor-zoom { font-size: 12px; color: #26313c; white-space: nowrap; }
.visor-pagina input { width: 46px; font: inherit; padding: 2px 4px; border: 1px solid #d6cfcf; border-radius: 4px; }
.visor-separador { width: 1px; height: 20px; background: #e6d8da; margin: 0 4px; }
.visor-cuerpo { flex: 1; min-height: 0; display: grid; grid-template-columns: 1fr; }
.visor-ventana.con-panel .visor-cuerpo { grid-template-columns: minmax(0, 7fr) minmax(300px, 3fr); }
.visor-documento { overflow: auto; background: #525659; padding: 12px; text-align: center; min-height: 0; }
.visor-documento:focus { outline: 2px solid #bd2434; outline-offset: -2px; }
.visor-lienzo, .visor-imagen { background: #fff; box-shadow: 0 2px 10px rgba(0, 0, 0, .4); max-width: none; }
.visor-imagen { max-width: 100%; height: auto; }
.visor-estado { color: #fff; font-size: 14px; margin: 40px auto; max-width: 520px; }
.visor-error { background: #fdecee; color: #8a1c2b; border: 1px solid #e3a3ab; border-radius: 8px; padding: 12px 14px; }
.visor-indicador { display: inline-block; width: 14px; height: 14px; border: 2px solid #fff; border-top-color: transparent; border-radius: 50%; animation: girar .8s linear infinite; vertical-align: -2px; }
@keyframes girar { to { transform: rotate(360deg); } }
.visor-panel { overflow: auto; border-left: 1px solid #e6d8da; padding: 12px 14px; min-height: 0; }
@media (max-width: 900px) {
    .visor-ventana { width: 100vw; height: 100vh; border-radius: 0; }
    .visor-ventana.con-panel .visor-cuerpo { grid-template-columns: 1fr; grid-template-rows: minmax(55vh, auto) auto; overflow: auto; }
    .visor-panel { border-left: 0; border-top: 1px solid #e6d8da; overflow: visible; }
    .visor-documento { min-height: 55vh; }
}
</style>
