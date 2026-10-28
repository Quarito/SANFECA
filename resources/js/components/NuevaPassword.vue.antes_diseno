<script setup>
import { ref } from 'vue';

const password = ref('');
const confirmacion = ref('');
const mostrar = ref(false);
</script>

<template>
    <section class="recuperacion-formulario">
        <h2>Generación de Nueva Contraseña</h2>

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
            <input v-model="mostrar" type="checkbox">
            Mostrar contraseñas
        </label>

        <p
            v-if="confirmacion && password !== confirmacion"
            class="nota-recuperacion"
        >
            Las contraseñas no coinciden.
        </p>
    </section>
</template>
