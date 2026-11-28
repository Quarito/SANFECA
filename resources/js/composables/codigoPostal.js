// Consulta del catálogo nacional de códigos postales (SEPOMEX) en PostgreSQL: GET /catalogos/codigo-postal/{cp}.
// Llena entidad federativa y alcaldía/municipio, y ofrece SOLO las colonias del código postal ingresado como sugerencias
// del mismo campo de texto (se puede escribir para buscar). Un valor que no pertenezca al catálogo impide enviar el
// formulario; la validación definitiva la hace el servidor. No cambia la distribución de la pantalla.
import { ref, watch } from 'vue';

const normalizar = (t) => String(t ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/\s+/g, ' ').trim();

export function usarCodigoPostal(campos, obtenerCampoCp = () => null, obtenerCampoColonia = () => null) {
    const colonias = ref([]);
    const consultado = ref(false);   // hay respuesta del catálogo para el código actual
    let ultima = null;

    function validarColonia() {
        const elemento = obtenerCampoColonia();
        if (!elemento) {
            return;
        }
        const valor = normalizar(campos.colonia);
        if (!consultado.value || valor === '') {
            elemento.setCustomValidity('');
            return;
        }
        const coincidencia = colonias.value.find((c) => normalizar(c) === valor);
        elemento.setCustomValidity(coincidencia ? '' : 'Seleccione una de las colonias registradas para el código postal ingresado.');
    }

    async function consultar(cp) {
        const elemento = obtenerCampoCp();
        colonias.value = [];
        consultado.value = false;
        elemento?.setCustomValidity('');
        validarColonia();
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
            consultado.value = true;
            if (colonias.value.length === 1) {
                campos.colonia = colonias.value[0];
            } else if (campos.colonia && !colonias.value.some((c) => normalizar(c) === normalizar(campos.colonia))) {
                campos.colonia = '';
            }
            validarColonia();
        } catch {
            // Error de red: no se modifica la captura; el servidor validará al guardar.
        }
    }

    // Al elegir o escribir una colonia del catálogo se usa su escritura oficial.
    watch(() => campos.colonia, () => {
        const coincidencia = colonias.value.find((c) => normalizar(c) === normalizar(campos.colonia));
        if (coincidencia && coincidencia !== campos.colonia) {
            campos.colonia = coincidencia;
        }
        validarColonia();
    });

    watch(() => campos.codigo_postal, (cp) => consultar(cp), { immediate: true });

    return { colonias };
}
