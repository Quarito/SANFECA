
import { createApp } from 'vue';

import DatosPersonales from './components/DatosPersonales.vue';
import SeleccionCertificacion from './components/SeleccionCertificacion.vue';
import FormacionAcademica from './components/FormacionAcademica.vue';
import DocumentosProbatorios from './components/DocumentosProbatorios.vue';
import RecuperarPassword from './components/RecuperarPassword.vue';
import NuevaPassword from './components/NuevaPassword.vue';

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
// PANTALLA 5: DOCUMENTOS PROBATORIOS
const contenedorDocumentos = document.getElementById(
    'vue-documentos-probatorios'
);

if (contenedorDocumentos) {
    const academicos = JSON.parse(
        contenedorDocumentos.dataset.academicos || '{}'
    );

    const nuevos = JSON.parse(
        contenedorDocumentos.dataset.nuevos || '{}'
    );

    const linkInicial = contenedorDocumentos.dataset.link || '';

    createApp(DocumentosProbatorios, {
        academicos,
        nuevos,
        linkInicial
    }).mount(contenedorDocumentos);
}
// Recuperación de contraseña
const contenedorRecuperacion = document.getElementById(
    'vue-recuperar-password'
);

if (contenedorRecuperacion) {
    createApp(RecuperarPassword, {
        curpInicial: contenedorRecuperacion.dataset.curp || '',
        emailInicial: contenedorRecuperacion.dataset.email || ''
    }).mount(contenedorRecuperacion);
}

// Establecer nueva contraseña
const contenedorNuevaPassword = document.getElementById(
    'vue-nueva-password'
);

if (contenedorNuevaPassword) {
    createApp(NuevaPassword).mount(contenedorNuevaPassword);
}
