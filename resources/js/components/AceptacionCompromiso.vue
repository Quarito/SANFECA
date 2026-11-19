<script setup>
import { ref } from 'vue';

defineProps({
    accion: {
        type: String,
        required: true
    },
    csrf: {
        type: String,
        required: true
    },
    urlRegresar: {
        type: String,
        required: true
    }
});

const aceptado = ref(false);
</script>

<template>
    <section class="aceptacion-compromiso">
        <h3>Compromiso de entrega de documentación</h3>

        <p class="texto-compromiso">
            Para completar su solicitud, deberá entregar
            las fotografías indicadas en la convocatoria
            y el comprobante de pago original.
        </p>

        <form :action="accion" method="POST">
            <input
                type="hidden"
                name="_token"
                :value="csrf"
            >

            <label class="casilla-compromiso">
                <input
                    v-model="aceptado"
                    type="checkbox"
                    name="compromiso_aceptado"
                    value="1"
                    required
                >

                <span>
                    He leído la información y acepto el
                    compromiso de entregar la documentación indicada.
                </span>
            </label>

            <div class="acciones-compromiso">
                <a :href="urlRegresar" class="boton-regresar">
                    Anterior
                </a>

                <button
                    type="submit"
                    class="boton-aceptar"
                    :disabled="!aceptado"
                >
                    Aceptar y continuar
                </button>
            </div>
        </form>
    </section>
</template>

<style scoped>
.aceptacion-compromiso {
    width: 100%;
    max-width: 680px;
    margin: 0 auto;
    padding: 24px;
    border: 1px solid #dddddd;
    border-radius: 8px;
    background: #ffffff;
    box-sizing: border-box;
}

.aceptacion-compromiso h3 {
    color: #980d22;
    font-size: 17px;
    text-align: center;
    margin: 0 0 20px;
}

.texto-compromiso {
    font-size: 14px;
    line-height: 1.7;
    color: #333333;
    margin-bottom: 25px;
}

.casilla-compromiso {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    font-size: 14px;
    line-height: 1.6;
    cursor: pointer;
}

.casilla-compromiso input {
    margin-top: 5px;
    accent-color: #980d22;
}

.acciones-compromiso {
    display: flex;
    justify-content: center;
    gap: 15px;
    margin-top: 30px;
    flex-wrap: wrap;
}

.boton-regresar,
.boton-aceptar {
    padding: 10px 18px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    text-align: center;
}

.boton-regresar {
    background: #eeeeee;
    color: #333333;
}

.boton-aceptar {
    background: #980d22;
    color: #ffffff;
    border: none;
    cursor: pointer;
}

.boton-aceptar:disabled {
    background: #aaaaaa;
    cursor: not-allowed;
}
</style>
