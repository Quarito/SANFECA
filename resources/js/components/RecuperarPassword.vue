<script setup>
import { ref } from 'vue';

const props = defineProps({
    curpInicial: { type: String, default: '' },
    emailInicial: { type: String, default: '' }
});

const curp = ref(props.curpInicial);
const email = ref(props.emailInicial);

function normalizarCurp() {
    curp.value = curp.value.toUpperCase().replace(/\s/g, '');
}
</script>

<template>
    <section class="recuperacion-formulario">
        <h2>Recuperación de Contraseña</h2>

        <div class="campo-recuperacion">
            <label for="curp">CURP</label>
            <input
                id="curp"
                v-model="curp"
                name="curp"
                type="text"
                maxlength="18"
                minlength="18"
                autocomplete="off"
                required
                @input="normalizarCurp"
            >
        </div>

        <div class="campo-recuperacion">
            <label for="email">Correo Electrónico</label>
            <input
                id="email"
                v-model="email"
                name="email"
                type="email"
                autocomplete="email"
                required
            >
        </div>

        <p class="nota-recuperacion">
            <strong>Nota:</strong> Se enviará un
            <strong>enlace de generación de nueva contraseña</strong>
            con base en los datos ingresados.
        </p>
    </section>
</template>
