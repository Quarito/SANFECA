// Consulta del catálogo nacional de códigos postales (SEPOMEX) en PostgreSQL: GET /catalogos/codigo-postal/{cp}.
// Llena entidad federativa y alcaldía/municipio, y ofrece las colonias del código postal como sugerencias del
// mismo campo de texto (sin cambiar la distribución de la pantalla). La validación definitiva la hace el servidor.
import { ref, watch } from 'vue';

export function usarCodigoPostal(campos, obtenerCampoCp = () => null) {
    const colonias = ref([]);
    let ultima = null;

    async function consultar(cp) {
        const elemento = obtenerCampoCp();
        colonias.value = [];
        elemento?.setCustomValidity('');
        if (!/^[0-9]{5}$/.test(cp || '')) {
            return;
        }
        ultima = cp;
        try {
            const r = await fetch(`/catalogos/codigo-postal/${cp}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (ultima !== cp) {
                return;
            }
            if (r.status === 404) {
                elemento?.setCustomValidity('El código postal no se encuentra en el catálogo nacional de Correos de México.');
                elemento?.reportValidity();
                return;
            }
            if (!r.ok) {
                return; // sin conexión con el catálogo: el servidor validará al guardar
            }
            const datos = await r.json();
            const municipio = datos.municipios?.[0];
            if (!municipio) {
                return;
            }
            campos.entidad_federativa = municipio.entidad_federativa.nombre;
            campos.municipio = municipio.nombre;
            colonias.value = municipio.colonias.map((c) => c.nombre);
            if (colonias.value.length === 1) {
                campos.colonia = colonias.value[0];
            } else if (campos.colonia && !colonias.value.includes(campos.colonia)) {
                campos.colonia = '';
            }
        } catch {
            // Error de red: no se modifica la captura; el servidor validará al guardar.
        }
    }

    watch(() => campos.codigo_postal, (cp) => consultar(cp), { immediate: true });

    return { colonias };
}
