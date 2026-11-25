import EncabezadoProceso from './components/EncabezadoProceso.vue';
import DatosRefrendo from './components/DatosRefrendo.vue';

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

    const autenticado =
        contenedorDatos.dataset.autenticado === '1';

    createApp(DatosPersonales, {
        datos,
        autenticado
    }).mount(contenedorDatos);
}

// PANTALLA 2: SELECCIÓN DE CERTIFICACIÓN
const contenedorCertificacion = document.getElementById(
    'vue-seleccion-certificacion'
);

if (contenedorCertificacion) {
    const seleccion = JSON.parse(
        contenedorCertificacion.dataset.seleccion || '{}'
    );

    const antecedentes = JSON.parse(
        contenedorCertificacion.dataset.antecedentes || '{}'
    );

    createApp(SeleccionCertificacion, {
    seleccion,
    antecedentes,
    numeroCertificadoInicial:
        contenedorCertificacion.dataset.numeroCertificadoAnterior || ''
}).mount(contenedorCertificacion);
}

// PANTALLA 4: FORMACIÓN ACADÉMICA
const contenedorFormacion = document.getElementById('vue-formacion-academica');

if (contenedorFormacion) {
    const instituciones = JSON.parse(
        contenedorFormacion.dataset.instituciones || '[]'
    );

    const formacion = JSON.parse(
        contenedorFormacion.dataset.formacion || '{}'
    );
const areaCertificacion =
    contenedorFormacion.dataset.areaCertificacion || '';

const titulosPermitidos = JSON.parse(
    contenedorFormacion.dataset.titulosPermitidos || '[]'
);

const esRefrendo =
    contenedorFormacion.dataset.esRefrendo === 'true';
const formacionBloqueada =
    contenedorFormacion.dataset.formacionBloqueada === 'true';

     createApp(FormacionAcademica, {
        instituciones,
        formacion,
        areaCertificacion,
        titulosPermitidos,
        esRefrendo,
        formacionBloqueada
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
    const esArticulo14 =
        contenedorDocumentos.dataset.esArticulo14 === '1';

    createApp(DocumentosProbatorios, {
        academicos,
        nuevos,
        linkInicial,
        esArticulo14,
        documentosBloqueados:
            contenedorDocumentos.dataset.documentosBloqueados === '1',
        formatoArticulo14Url:
            contenedorDocumentos.dataset.formatoArticulo14Url || ''
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
// ACTUALIZACIÓN DE DATOS PERSONALES
import ActualizarDatos from './components/ActualizarDatos.vue';

const contenedorActualizar = document.getElementById(
    'vue-actualizar-datos'
);

if (contenedorActualizar) {
    const datos = JSON.parse(
        contenedorActualizar.dataset.datos || '{}'
    );

    createApp(ActualizarDatos, {
    datos: JSON.parse(contenedorActualizar.dataset.datos),
    accion: contenedorActualizar.dataset.accion,
    csrf: contenedorActualizar.dataset.csrf,
    editarInicialmente: contenedorActualizar.dataset.editar === '1',
    accionAutorizar: contenedorActualizar.dataset.accionAutorizar,
    urlSiguiente: contenedorActualizar.dataset.urlSiguiente,
    autorizadoInicialmente: contenedorActualizar.dataset.autorizado === '1',



}).mount(contenedorActualizar);
}


// PANTALLA 3: DATOS DE REFRENDO
const contenedorRefrendo = document.getElementById(
    'vue-datos-refrendo'
);

if (contenedorRefrendo) {
    const datos = JSON.parse(
        contenedorRefrendo.dataset.datos || '{}'
    );

    const automaticos = JSON.parse(
        contenedorRefrendo.dataset.automaticos || '{}'
    );

    createApp(DatosRefrendo, {
        datos,
        automaticos
    }).mount(contenedorRefrendo);
}


// ENCABEZADOS INSTITUCIONALES DEL REGISTRO
document.querySelectorAll('[data-vue-encabezado]').forEach(
    contenedor => {
        createApp(EncabezadoProceso, {
            titulo: contenedor.dataset.titulo || '',
            descripcion: contenedor.dataset.descripcion || ''
        }).mount(contenedor);
    }
);


// PANTALLA 7: ACEPTACION Y COMPROMISO
import AceptacionCompromiso from './components/AceptacionCompromiso.vue';

const contenedorCompromiso = document.getElementById(
    'vue-aceptacion-compromiso'
);

if (contenedorCompromiso) {
    createApp(AceptacionCompromiso, {
        accion: contenedorCompromiso.dataset.accion,
        csrf: contenedorCompromiso.dataset.csrf,
        urlRegresar: contenedorCompromiso.dataset.urlRegresar,
        urlContinuar: contenedorCompromiso.dataset.urlContinuar || '',
        aceptadoInicialmente: contenedorCompromiso.dataset.aceptado === '1',
        aceptadoEn: contenedorCompromiso.dataset.aceptadoEn || '',
        soloConsulta: contenedorCompromiso.dataset.soloConsulta === '1'
    }).mount(contenedorCompromiso);
}


// MÓDULO DE COORDINACIÓN: DASHBOARD
import CoordinacionInicio from './components/CoordinacionInicio.vue';

const contenedorCoordinacionInicio = document.getElementById('vue-coordinacion-inicio');

if (contenedorCoordinacionInicio) {
    createApp(CoordinacionInicio, {
        urlIndicadores: contenedorCoordinacionInicio.dataset.urlIndicadores,
        urlBandeja: contenedorCoordinacionInicio.dataset.urlBandeja,
        urlExpediente: contenedorCoordinacionInicio.dataset.urlExpediente
    }).mount(contenedorCoordinacionInicio);
}


// MÓDULO DE COORDINACIÓN: BANDEJA DE SOLICITUDES
import BandejaSolicitudes from './components/BandejaSolicitudes.vue';

const contenedorBandeja = document.getElementById('vue-coordinacion-bandeja');

if (contenedorBandeja) {
    createApp(BandejaSolicitudes, {
        urlApi: contenedorBandeja.dataset.urlApi,
        urlExpediente: contenedorBandeja.dataset.urlExpediente,
        opciones: JSON.parse(contenedorBandeja.dataset.opciones || '{}')
    }).mount(contenedorBandeja);
}


// MÓDULO DE COORDINACIÓN: PROTOTIPO AISLADO DE REVISIÓN DOCUMENTAL (sin red ni persistencia)
import RevisionDocumentalPrototipo from './components/RevisionDocumentalPrototipo.vue';

const contenedorPrototipoRevision = document.getElementById('vue-prototipo-revision');

if (contenedorPrototipoRevision) {
    createApp(RevisionDocumentalPrototipo).mount(contenedorPrototipoRevision);
}

// Revisión documental funcional (fase 6.3.1): diálogo sobre el expediente; solo con la revisión activa.
import RevisionDocumentalDialogo from './components/RevisionDocumentalDialogo.vue';

const contenedorRevisionDocumental = document.getElementById('vue-revision-documental');

if (contenedorRevisionDocumental) {
    createApp(RevisionDocumentalDialogo, {
        urlBase: contenedorRevisionDocumental.dataset.urlBase
    }).mount(contenedorRevisionDocumental);
}
