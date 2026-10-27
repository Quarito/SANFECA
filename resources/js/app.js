
import { createApp } from 'vue';

import DatosPersonales from './components/DatosPersonales.vue';
import SeleccionCertificacion from './components/SeleccionCertificacion.vue';
import FormacionAcademica from './components/FormacionAcademica.vue';

// PANTALLA 1: DATOS PERSONALES
const contenedorDatos = document.getElementById('vue-datos-personales');

if (contenedorDatos) {
    const datos = JSON.parse(
        contenedorDatos.dataset.datos || '{}'
    );

    createApp(DatosPersonales, { datos }).mount(contenedorDatos);
}

// PANTALLA 2: SELECCIÓN DE CERTIFICACIÓN
const contenedorCertificacion = document.getElementById(
    'vue-seleccion-certificacion'
);

if (contenedorCertificacion) {
    const seleccion = JSON.parse(
        contenedorCertificacion.dataset.seleccion || '{}'
    );

    createApp(SeleccionCertificacion, {
        seleccion
    }).mount(contenedorCertificacion);
}

// PANTALLA 4: FORMACIÓN ACADÉMICA
const contenedorFormacion = document.getElementById(
    'vue-formacion-academica'
);

if (contenedorFormacion) {
    const instituciones = JSON.parse(
        contenedorFormacion.dataset.instituciones || '[]'
    );

    const formacion = JSON.parse(
        contenedorFormacion.dataset.formacion || '{}'
    );

    createApp(FormacionAcademica, {
        instituciones,
        formacion
    }).mount(contenedorFormacion);
}
