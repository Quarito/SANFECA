<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    institucionGuardada: { type: Object, default: null },
    numeroInicial: { type: String, default: '' },
    url: { type: String, default: '' },
    bloqueada: { type: Boolean, default: false },
});

const numero = ref(props.numeroInicial);
const institucion = ref(null);
const estado = ref('');
const aviso = ref('');

watch(() => numero.value.trim(), (afiliacion, _, limpiar) => {
    institucion.value = null;
    aviso.value = '';
    estado.value = '';

    if (afiliacion === (props.institucionGuardada?.numero_afiliacion ?? '').trim()) {
        institucion.value = props.institucionGuardada;
        return;
    }

    if (props.bloqueada || !props.url || !afiliacion || afiliacion.length > 7) {
        return;
    }

    const consulta = new AbortController();
    estado.value = 'buscando';
    aviso.value = 'Buscando institución…';

    const espera = setTimeout(async () => {
        try {
            const url = new URL(props.url, window.location.href);
            url.searchParams.set('numero_afiliacion', afiliacion);
            const respuesta = await fetch(url, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
                signal: consulta.signal,
            });
            const datos = await respuesta.json();

            if (consulta.signal.aborted) return;

            if (respuesta.ok) {
                institucion.value = datos;
                estado.value = 'encontrada';
                aviso.value = 'Institución encontrada.';
            } else if ([404, 409].includes(respuesta.status)) {
                estado.value = 'invalida';
                aviso.value = datos.message;
            } else {
                throw new Error('No se pudo consultar la afiliación.');
            }
        } catch {
            if (consulta.signal.aborted) return;
            estado.value = 'error';
            aviso.value = 'No se pudo consultar la afiliación. Vuelve a capturar el número para reintentar.';
        }
    }, 300);

    limpiar(() => {
        clearTimeout(espera);
        consulta.abort();
    });
}, { immediate: true });

const campos = [
    ['nombre', 'Institución'],
    ['escuela_facultad', 'Escuela o Facultad'],
    ['campus', 'Campus'],
    ['sede', 'Sede'],
    ['zona_anfeca', 'Zona ANFECA'],
];
</script>

<template>
    <section class="procedencia" aria-labelledby="titulo-procedencia">
        <h2 id="titulo-procedencia">Institución de Procedencia</h2>

        <div class="procedencia-grid">
            <div class="procedencia-campo">
                <label for="numero_afiliacion">Número de Afiliación</label>
                <input
                    id="numero_afiliacion"
                    v-model="numero"
                    name="numero_afiliacion"
                    type="text"
                    maxlength="7"
                    autocomplete="off"
                    spellcheck="false"
                    :readonly="bloqueada || !url"
                    :required="!bloqueada && Boolean(url)"
                    :aria-invalid="estado === 'invalida' ? 'true' : undefined"
                    aria-describedby="ayuda-afiliacion aviso-afiliacion"
                >
                <p v-if="!bloqueada" id="ayuda-afiliacion" class="procedencia-ayuda">
                    Escríbelo tal como aparece, incluidos los ceros iniciales.
                </p>
            </div>

            <dl v-for="[clave, etiqueta] in campos" :key="clave" class="procedencia-campo">
                <dt>{{ etiqueta }}</dt>
                <dd>{{ institucion ? (institucion[clave]?.trim() || 'No disponible') : '—' }}</dd>
            </dl>
        </div>

        <p id="aviso-afiliacion" class="procedencia-aviso"
            :class="{ 'procedencia-error': ['invalida', 'error'].includes(estado) }" role="status">
            {{ aviso }}
        </p>
    </section>
</template>

<style scoped>
.procedencia {
    margin-top: 18px;
    padding: clamp(12px, 2vw, 20px);
    border: 1px solid #b8cde3;
    border-radius: 10px;
    background: #edf4fb;
    color: #163b61;
}

.procedencia h2 {
    margin: 0 0 14px;
    color: #163b61;
    font-size: 17px;
    font-weight: 700;
}

.procedencia-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.procedencia-campo {
    min-width: 0;
    margin: 0;
    padding: 10px 12px;
    border: 1px solid #c8d9ec;
    border-radius: 7px;
    background: #f8fbff;
}

.procedencia-campo label,
.procedencia-campo dt {
    display: block;
    margin: 0 0 5px;
    color: #163b61;
    font-size: 13px;
    font-weight: 700;
}

.procedencia-campo input {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid #6c92b7;
    border-radius: 5px;
    padding: 7px 9px;
    background: white;
    color: #102e4c;
}

.procedencia-campo input:focus-visible {
    outline: 2px solid #215d95;
    outline-offset: 2px;
}

.procedencia-campo input[aria-invalid="true"] {
    border-color: #a52535;
}

.procedencia-campo dd {
    margin: 0;
    color: #102e4c;
    overflow-wrap: anywhere;
}

.procedencia-ayuda,
.procedencia-aviso {
    margin: 6px 0 0;
    font-size: 12px;
}

.procedencia-aviso:empty {
    margin: 0;
}

.procedencia-error {
    color: #922335;
}

@media (max-width: 640px) {
    .procedencia-grid {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>
