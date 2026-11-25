<script setup>
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    urlIndicadores: { type: String, required: true },
    urlBandeja: { type: String, required: true },
    urlExpediente: { type: String, required: true }
});

const datos = ref(null);
const error = ref('');
const cargando = ref(true);

async function cargar() {
    cargando.value = true;
    error.value = '';
    try {
        const respuesta = await fetch(props.urlIndicadores, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        });
        if (!respuesta.ok) {
            throw new Error(`HTTP ${respuesta.status}`);
        }
        datos.value = await respuesta.json();
    } catch (e) {
        error.value = 'No fue posible obtener los indicadores. Intente nuevamente.';
    } finally {
        cargando.value = false;
    }
}

const distribuciones = computed(() => {
    if (!datos.value) {
        return [];
    }
    const d = datos.value.distribucion;
    return [
        { clave: 'zona', titulo: 'Distribución por zona', filas: d.zona },
        { clave: 'institucion', titulo: 'Distribución por institución', filas: d.institucion },
        { clave: 'certificacion', titulo: 'Distribución por certificación', filas: d.certificacion },
        { clave: 'tipo_tramite', titulo: 'Distribución por tipo de trámite', filas: d.tipo_tramite }
    ];
});

function maximo(filas) {
    return Math.max(1, ...filas.map(f => f.valor));
}

function estado(valor) {
    return valor === 'enviado' ? 'Enviada' : 'Iniciada';
}

onMounted(cargar);
</script>

<template>
    <div class="coord-inicio">
        <p v-if="cargando" class="coord-cargando">Cargando indicadores…</p>

        <div v-else-if="error" class="anfeca-aviso anfeca-aviso--error" role="alert">
            {{ error }}
            <button type="button" class="coord-boton secundario" style="margin-left: 10px;" @click="cargar">Reintentar</button>
        </div>

        <template v-else-if="datos">
            <div class="anfeca-aviso anfeca-aviso--info" style="margin-bottom: 14px;" role="note">
                Alcance: <strong>{{ datos.alcance.descripcion }}</strong> · Datos al {{ datos.generado_en }}.
                Convocatoria: <em>{{ datos.convocatoria.nota }}</em>.
            </div>

            <div class="coord-tarjetas">
                <div v-for="t in datos.tarjetas" :key="t.clave" class="coord-tarjeta">
                    <div class="coord-tarjeta-etiqueta">{{ t.etiqueta }}</div>
                    <div class="coord-tarjeta-valor">{{ t.valor }}</div>
                    <div class="coord-tarjeta-nota">{{ t.nota }}</div>
                </div>
            </div>

            <h2 class="coord-subtitulo">Evaluación</h2>
            <div class="coord-tarjetas">
                <div v-for="e in datos.evaluacion" :key="e.clave" class="coord-tarjeta pendiente">
                    <div class="coord-tarjeta-etiqueta">{{ e.etiqueta }}</div>
                    <div class="coord-tarjeta-valor">Pendiente de implementación</div>
                    <div class="coord-tarjeta-nota">{{ e.nota }}</div>
                </div>
            </div>

            <div class="coord-paneles">
                <section v-for="d in distribuciones" :key="d.clave" class="coord-panel">
                    <h2>{{ d.titulo }}</h2>
                    <p v-if="!d.filas.length" class="coord-cargando">Sin solicitudes en su alcance.</p>
                    <ul v-else class="coord-barras">
                        <li v-for="f in d.filas" :key="f.etiqueta"
                            class="coord-barra-fila" :class="{ 'sin-clasificar': f.sin_clasificar }">
                            <span class="coord-barra-etiqueta" :title="f.etiqueta">{{ f.etiqueta }}</span>
                            <span class="coord-barra-valor">{{ f.valor }}</span>
                            <span class="coord-barra-pista" aria-hidden="true">
                                <span class="coord-barra-relleno" :style="{ width: (f.valor / maximo(d.filas) * 100) + '%' }"></span>
                            </span>
                        </li>
                    </ul>
                </section>
            </div>

            <section class="coord-panel">
                <h2>Solicitudes recientes</h2>
                <p v-if="!datos.recientes.length" class="coord-cargando">Sin solicitudes en su alcance.</p>
                <div v-else class="coord-tabla-contenedor">
                    <table class="coord-tabla">
                        <thead>
                            <tr>
                                <th scope="col" class="col-num" title="Consecutivo visual; no es el folio">Núm.</th>
                                <th scope="col">Folio</th>
                                <th scope="col">Zona</th>
                                <th scope="col">Institución</th>
                                <th scope="col">Trámite</th>
                                <th scope="col">Certificación</th>
                                <th scope="col">Fecha</th>
                                <th scope="col">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in datos.recientes" :key="r.folio">
                                <td class="col-num">{{ r.num }}</td>
                                <td><a :href="`${urlExpediente}/${r.folio}`">{{ r.folio }}</a></td>
                                <td>
                                    <span v-if="r.zona_clasificada">{{ r.zona }}</span>
                                    <span v-else class="coord-insignia sin-zona">Sin clasificar</span>
                                </td>
                                <td>{{ r.institucion || '—' }}</td>
                                <td>{{ r.tramite }}</td>
                                <td>{{ r.certificacion || '—' }}</td>
                                <td>{{ r.fecha || '—' }}</td>
                                <td><span class="coord-insignia" :class="r.estado === 'enviado' ? 'enviado' : 'borrador'">{{ estado(r.estado) }}</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p style="margin: 10px 0 0;"><a :href="urlBandeja" class="coord-boton secundario">Ver todas las solicitudes</a></p>
            </section>
        </template>
    </div>
</template>
