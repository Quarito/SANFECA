<script setup>
defineProps({
    titulo: {
        type: String,
        required: true
    },
    descripcion: {
        type: String,
        default: ''
    }
});
</script>

<template>
    <section class="encabezado-proceso">
        <div class="encabezado-proceso__titulo">
            <h2>{{ titulo }}</h2>
        </div>

        <p v-if="descripcion" class="encabezado-proceso__descripcion">
            {{ descripcion }}
        </p>
    </section>
</template>

<style scoped>
.encabezado-proceso {
    width: 100%;
    margin: 0 auto 24px;
    text-align: center;
}

.encabezado-proceso__titulo {
    padding: 18px 24px;
    background: linear-gradient(
        110deg,
        #ffffff 0%,
        #f9eeee 50%,
        #f6e5e7 100%
    );
    border-left: 4px solid #a30917;
    border-radius: 12px;
}

.encabezado-proceso__titulo h2 {
    margin: 0;
    color: #a30917;
    font-size: clamp(22px, 2.5vw, 30px);
    font-weight: 700;
    line-height: 1.3;
    text-align: center;
}

.encabezado-proceso__descripcion {
    max-width: 760px;
    margin: 18px auto 0;
    padding: 0 12px;
    color: #333333;
    font-size: 15px;
    font-weight: 500;
    line-height: 1.6;
    text-align: center;
}

.encabezado-proceso__descripcion::after {
    content: "";
    display: block;
    width: 90px;
    height: 3px;
    margin: 14px auto 0;
    background-color: #a30917;
    border-radius: 3px;
}

@media (max-width: 768px) {
    .encabezado-proceso__titulo {
        padding: 16px;
    }

    .encabezado-proceso__descripcion {
        font-size: 14px;
    }
}

/* encabezado-tenue-97156 */

/* Contenedor principal del encabezado */
.encabezado-proceso,
.encabezado-pantalla,
.titulo-proceso {
    background: linear-gradient(
        110deg,
        #FFFFFF 0%,
        #FFFFFF 60%,
        #FDFBFC 85%,
        #FAF6F7 100%
    ) !important;

    padding-top: 12px !important;
    padding-bottom: 12px !important;
    min-height: 0 !important;
}

/* Título */
.encabezado-proceso h1,
.encabezado-proceso h2,
.encabezado-pantalla h1,
.encabezado-pantalla h2,
.titulo-proceso h1,
.titulo-proceso h2 {
    color: #980D22 !important;
    font-size: 19px !important;
    line-height: 1.3 !important;
    margin-top: 0 !important;
    margin-bottom: 0 !important;
}

/* encabezado compacto en pantallas pequeñas */
@media (max-width: 768px) {
    .encabezado-proceso,
    .encabezado-pantalla,
    .titulo-proceso {
        padding: 10px !important;
    }
}


/* encabezado-compacto-97160 */

/* Reducir el espacio total */
.encabezado-proceso {
    margin: 0 auto 12px !important;
    padding: 0 !important;
    background: transparent !important;
}

/* Encabezado institucional más compacto */
.encabezado-proceso__titulo {
    padding: 10px 16px !important;
    background: linear-gradient(
        110deg,
        #FFFFFF 0%,
        #FFFFFF 60%,
        #FDFBFC 85%,
        #FAF6F7 100%
    ) !important;
    border-left: 3px solid #A30917;
    border-radius: 8px;
}

/* Título */
.encabezado-proceso__titulo h2 {
    font-size: 17px !important;
    line-height: 1.25 !important;
    font-weight: 600 !important;
}

/* Descripción */
.encabezado-proceso__descripcion {
    font-size: 12px !important;
    line-height: 1.4 !important;
    margin: 8px auto 0 !important;
    padding: 0 8px !important;
}

/* Línea decorativa */
.encabezado-proceso__descripcion::after {
    width: 55px !important;
    height: 2px !important;
    margin: 7px auto 0 !important;
}

@media (max-width: 768px) {
    .encabezado-proceso__titulo {
        padding: 9px 12px !important;
    }
}

</style>
