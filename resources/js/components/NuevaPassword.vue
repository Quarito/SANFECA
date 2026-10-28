
<script setup>
import { ref, computed } from 'vue';

const password = ref('');
const confirmacion = ref('');
const mostrar = ref(false);

const coinciden = computed(() =>
    password.value === confirmacion.value
);

const mostrarError = computed(() =>
    confirmacion.value.length > 0 && !coinciden.value
);
</script>

<template>
    <section class="recuperacion-formulario nueva-password">
        <h2>Generación de Nueva Contraseña</h2>

        <p class="instrucciones-password">
            Ingresa una nueva contraseña de al menos
            8 caracteres y confírmala para continuar.
        </p>

        <div class="campo-recuperacion">
            <label for="password">Nueva Contraseña</label>

            <input
                id="password"
                v-model="password"
                name="password"
                :type="mostrar ? 'text' : 'password'"
                autocomplete="new-password"
                minlength="8"
                required
            >
        </div>

        <div class="campo-recuperacion">
            <label for="password_confirmation">
                Confirmar Nueva Contraseña
            </label>

            <input
                id="password_confirmation"
                v-model="confirmacion"
                name="password_confirmation"
                :type="mostrar ? 'text' : 'password'"
                autocomplete="new-password"
                minlength="8"
                required
            >
        </div>

        <label class="mostrar-password">
            <input
                v-model="mostrar"
                type="checkbox"
            >
            Mostrar contraseñas
        </label>

        <p
            v-if="mostrarError"
            class="error-password"
            role="alert"
        >
            Las contraseñas no coinciden.
        </p>

        <p
            v-if="confirmacion && coinciden && password.length >= 8"
            class="exito-password"
        >
            Las contraseñas coinciden.
        </p>
    </section>
</template>
