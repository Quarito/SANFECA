<script setup>
/*
 * Orquesta las acciones de documentos del expediente:
 *  - «Ver» (siempre, aun con la revisión documental desactivada): abre VisorDocumento.
 *  - «Revisar» (solo con la revisión activa y solicitudes enviadas): visor + panel de revisión 70/30.
 * Los enlaces del Blade conservan su href como alternativa sin JavaScript.
 */
import { onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import VisorDocumento from './VisorDocumento.vue';
import PanelRevisionDocumental from './PanelRevisionDocumental.vue';

const props = defineProps({
    folio: { type: [String, Number], required: true },
    urlBase: { type: String, required: true },     // …/coordinacion/solicitudes/{folio}/documentos
    revisionActiva: { type: Boolean, default: false }
});

const CARGA = { no_cargado: 'El documento no fue cargado por el solicitante.', no_localizado: 'El documento está registrado, pero el archivo no se localiza en el almacenamiento.' };
const visor = reactive({ abierto: false, url: '', urlDescarga: '', titulo: '', sinArchivo: '', revisar: false, campo: '' });
const detalle = ref(null);
const sucio = ref(false);
const error = ref('');

function abrirVer(enlace) {
    Object.assign(visor, {
        abierto: true, revisar: false, campo: enlace.dataset.ver, titulo: enlace.dataset.etiqueta, sinArchivo: '',
        url: enlace.getAttribute('href'), urlDescarga: enlace.dataset.descarga || ''
    });
}

async function cargarDetalle(campo) {
    error.value = '';
    const r = await fetch(`${props.urlBase}/${campo}/revisiones`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
    if (!r.ok) throw new Error(r.status === 404 ? 'El documento no está disponible para revisión.' : `Error ${r.status}`);
    detalle.value = await r.json();
    const v = detalle.value.version;
    visor.sinArchivo = v.estado === 'cargado' ? '' : CARGA[v.estado];
    visor.url = `${props.urlBase}/${campo}?v=${v.token}`;
    visor.urlDescarga = v.estado === 'cargado' ? `${props.urlBase}/${campo}?v=${v.token}&descargar=1` : '';
}

async function abrirRevisar(boton) {
    detalle.value = null;
    sucio.value = false;
    Object.assign(visor, { revisar: true, campo: boton.dataset.revisar, titulo: boton.dataset.etiqueta || 'Documento', url: '', sinArchivo: '' });
    try {
        await cargarDetalle(visor.campo);
        visor.abierto = true;
    } catch (e) {
        window.alert(e.message);
    }
}

function alPulsar(evento) {
    const ver = evento.target.closest('a[data-ver]');
    const revisar = evento.target.closest('[data-revisar]');
    if (ver) { evento.preventDefault(); abrirVer(ver); }
    else if (revisar && props.revisionActiva) { evento.preventDefault(); abrirRevisar(revisar); }
}

function alGuardar() {
    sucio.value = false;
    window.location.reload();          // la página muestra solo estados persistidos
}

function cerrar() {
    visor.abierto = false;
    sucio.value = false;
}

function antesDeSalir(e) {
    if (visor.abierto && sucio.value) { e.preventDefault(); e.returnValue = ''; }
}

onMounted(() => {
    document.addEventListener('click', alPulsar);
    window.addEventListener('beforeunload', antesDeSalir);
});
onBeforeUnmount(() => {
    document.removeEventListener('click', alPulsar);
    window.removeEventListener('beforeunload', antesDeSalir);
});
</script>

<template>
    <VisorDocumento :abierto="visor.abierto" :url="visor.url" :url-descarga="visor.urlDescarga" :titulo="visor.titulo"
                    :folio="folio" :sin-archivo="visor.sinArchivo" :hay-cambios-sin-guardar="sucio" @cerrar="cerrar">
        <template v-if="visor.revisar && detalle" #panel>
            <PanelRevisionDocumental :url-base="urlBase" :campo="visor.campo" :detalle="detalle"
                                     @sucio="v => (sucio = v)" @guardado="alGuardar"
                                     @actualizar="cargarDetalle(visor.campo)" />
        </template>
    </VisorDocumento>
</template>
