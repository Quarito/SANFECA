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
</style>
