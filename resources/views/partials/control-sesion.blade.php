{{--
    Control de inactividad de ANFECA (aviso de 15 s, «Continuar sesión», sesión finalizada y respuestas 419).
    - El servidor entrega SEGUNDOS RESTANTES (no horas absolutas): no depende de que los relojes coincidan.
    - Sin consultas periódicas: una consulta de estado poco antes del vencimiento, que no renueva la sesión.
    - Renuevan la sesión las peticiones normales al servidor (como en Laravel) y el clic explícito en «Continuar sesión».
      La actividad local del navegador (teclear, mover el ratón) NO renueva.
    - El navegador nunca cierra la sesión: la cierra el servidor al cumplirse session.lifetime de inactividad.
    - No intercepta ni altera formularios o respuestas de otros componentes; solo bloquea envíos cuando el servidor
      ya confirmó que la sesión terminó. No guarda datos en el navegador; nunca borra lo capturado.
    - Independiente del temporizador del acuse (identificadores y eventos propios).
--}}
@if (config('anfeca_sesion.activo') && !request()->is('preview/*'))
@php
    $anfecaSesion = [
        'restante' => \App\Http\Middleware\ControlInactividad::restante(request()),
        'aviso' => (int) config('anfeca_sesion.aviso_segundos', 15),
        'margen' => (int) config('anfeca_sesion.margen_segundos', 5),
        'csrf' => csrf_token(),
        'autenticado' => auth()->check(),
        'urlEstado' => route('sesion.estado'),
        'urlContinuar' => route('sesion.continuar'),
        'urlFinalizada' => route('sesion.finalizada'),
    ];
@endphp
<style>
    .anfeca-sesion-aviso { position: fixed; z-index: 10000; left: 50%; bottom: 24px; transform: translateX(-50%);
        width: min(420px, calc(100vw - 32px)); padding: 16px 18px; border: 1px solid #e8b4bb; border-left: 5px solid #bb1025;
        border-radius: 8px; background: #fff8f8; color: #3b1015; box-shadow: 0 8px 24px rgba(0, 0, 0, .18);
        font-family: inherit; font-size: 14px; font-weight: 400; line-height: 1.45; text-align: left; }
    .anfeca-sesion-aviso[hidden] { display: none; }
    .anfeca-sesion-aviso strong.anfeca-sesion-titulo { display: block; margin-bottom: 6px; color: #a71930; font-size: 15px; }
    .anfeca-sesion-aviso p { margin: 6px 0; font-weight: 400; }
    .anfeca-sesion-acciones { display: flex; gap: 10px; justify-content: flex-end; margin-top: 12px; flex-wrap: wrap; }
    .anfeca-sesion-boton { border: 0; border-radius: 6px; padding: 9px 14px; cursor: pointer; font-weight: 600; font-size: 13px;
        background: #bb1025; color: #fff; text-decoration: none; }
    .anfeca-sesion-boton:hover, .anfeca-sesion-boton:focus { background: #821326; }
    .anfeca-sesion-boton[disabled] { opacity: .6; cursor: wait; }
</style>

<div id="anfeca-sesion-aviso" class="anfeca-sesion-aviso" role="alertdialog" aria-live="assertive"
     aria-labelledby="anfeca-sesion-titulo" aria-describedby="anfeca-sesion-texto" hidden>
    <strong id="anfeca-sesion-titulo" class="anfeca-sesion-titulo">Su sesión está por finalizar</strong>
    <p id="anfeca-sesion-texto">
        No hemos detectado actividad. Por seguridad, la sesión se cerrará en
        <strong id="anfeca-sesion-segundos">{{ $anfecaSesion['aviso'] }}</strong> segundos.
    </p>
    <p id="anfeca-sesion-consecuencia">
        Si no continúa, deberá iniciar sesión nuevamente y los datos de esta pantalla que no haya guardado se perderán.
    </p>
    <div class="anfeca-sesion-acciones">
        <button type="button" id="anfeca-sesion-continuar" class="anfeca-sesion-boton">Continuar sesión</button>
        <button type="button" id="anfeca-sesion-actualizar" class="anfeca-sesion-boton" hidden>Actualizar página</button>
        <a id="anfeca-sesion-ir" class="anfeca-sesion-boton" href="{{ $anfecaSesion['urlFinalizada'] }}" hidden>Iniciar sesión</a>
    </div>
</div>

<script type="application/json" id="anfeca-sesion-config">@json($anfecaSesion)</script>
<script>
(function () {
    'use strict';
    if (window.__anfecaControlSesion) { return; }
    window.__anfecaControlSesion = true;

    var cfg = JSON.parse(document.getElementById('anfeca-sesion-config').textContent);
    var $ = function (id) { return document.getElementById(id); };
    var aviso = $('anfeca-sesion-aviso'), titulo = $('anfeca-sesion-titulo'), texto = $('anfeca-sesion-texto');
    var consecuencia = $('anfeca-sesion-consecuencia'), segundosEl = $('anfeca-sesion-segundos');
    var botonContinuar = $('anfeca-sesion-continuar'), botonActualizar = $('anfeca-sesion-actualizar'), enlaceIr = $('anfeca-sesion-ir');
    var fetchOriginal = window.fetch ? window.fetch.bind(window) : null;
    var temporizador = null, intervalo = null, finalizada = false, enCurso = false;
    var textoInicial = texto.innerHTML, consecuenciaInicial = consecuencia.textContent;

    if (!cfg.autenticado) {
        titulo.textContent = 'El formulario está por expirar';
        consecuenciaInicial = 'Si no continúa, el formulario expirará y los datos que no haya enviado se perderán.';
        consecuencia.textContent = consecuenciaInicial;
    }

    // Programa la única consulta de estado: (restante − aviso − margen − 1) segundos después (el segundo adicional
    // compensa el redondeo a segundos enteros para que la cuenta regresiva muestre los 15 s completos).
    function programar(restante, siguiente) {
        clearTimeout(temporizador);
        var espera = Math.max(0, restante - cfg.aviso - cfg.margen - 1) * 1000;
        temporizador = setTimeout(siguiente || revisar, Math.min(espera, 2147483000));
    }

    function solicitar(url, metodo) {
        var opciones = { method: metodo, credentials: 'same-origin', cache: 'no-store',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } };
        if (metodo === 'POST') { opciones.headers['X-CSRF-TOKEN'] = cfg.csrf; }
        return (fetchOriginal || window.fetch)(url, opciones);
    }

    // Lee el estado real en el servidor (no renueva). Devuelve null si la sesión ya terminó.
    function consultarEstado() {
        return solicitar(cfg.urlEstado, 'GET').then(function (r) {
            if (r.headers.get('X-Anfeca-Sesion') === 'finalizada') { return null; }
            if (!r.ok) { throw new Error('estado ' + r.status); }
            return r.json().then(function (d) {
                // Una sesión autenticada que el servidor ya no reconoce también terminó.
                return (!d.vigente || (cfg.autenticado && !d.autenticado)) ? null : d;
            });
        });
    }

    function revisar() {
        if (finalizada) { return; }
        consultarEstado().then(function (d) {
            if (!d) { return sesionTerminada(true); }
            if (d.restante > cfg.aviso + cfg.margen + 2) { return programar(d.restante); } // hubo actividad (otra pestaña o AJAX)
            mostrarAviso(d.restante);
        }).catch(function () { programar(cfg.aviso + cfg.margen + 10); }); // red no disponible: se reintenta una vez más tarde
    }

    // Cuenta regresiva hasta (vencimiento − margen). Al terminar NO se cierra nada: se espera el vencimiento real.
    function mostrarAviso(restante) {
        var segundos = Math.max(1, Math.min(cfg.aviso, restante - cfg.margen));
        texto.innerHTML = textoInicial; consecuencia.textContent = consecuenciaInicial;
        segundosEl = $('anfeca-sesion-segundos');
        segundosEl.textContent = segundos;
        botonContinuar.hidden = false; botonContinuar.disabled = false; botonActualizar.hidden = true; enlaceIr.hidden = true;
        aviso.hidden = false;
        botonContinuar.focus({ preventScroll: true });
        clearInterval(intervalo);
        intervalo = setInterval(function () {
            segundos -= 1;
            segundosEl.textContent = Math.max(segundos, 0);
            if (segundos <= 0) {
                clearInterval(intervalo); intervalo = null;
                // El cierre ocurre en el servidor al cumplirse el tiempo; se verifica 2 s después.
                clearTimeout(temporizador);
                temporizador = setTimeout(verificarCierre, (cfg.margen + 2) * 1000);
            }
        }, 1000);
    }

    function verificarCierre() {
        consultarEstado().then(function (d) {
            if (!d) { return sesionTerminada(true); }
            if (d.restante > cfg.margen + 2) { ocultarAviso(); return programar(d.restante); } // se continuó en otra pestaña
            clearTimeout(temporizador);
            temporizador = setTimeout(verificarCierre, (d.restante + 2) * 1000);
        }).catch(function () { temporizador = setTimeout(verificarCierre, 5000); });
    }

    function ocultarAviso() {
        clearInterval(intervalo); intervalo = null;
        aviso.hidden = true;
    }

    // Renovación SOLO por acción explícita del usuario (botón). El servidor rechaza sesiones vencidas.
    function continuar() {
        if (enCurso || finalizada) { return; }
        enCurso = true; botonContinuar.disabled = true;
        solicitar(cfg.urlContinuar, 'POST').then(function (r) {
            if (r.headers.get('X-Anfeca-Sesion') === 'finalizada' || r.headers.get('X-Anfeca-Sesion') === 'expirada') { return sesionTerminada(true); }
            if (!r.ok) { throw new Error('continuar ' + r.status); }
            return r.json().then(function (d) {
                if (!d.vigente || (cfg.autenticado && !d.autenticado)) { return sesionTerminada(true); } // nunca «continuar» como visitante
                ocultarAviso(); clearTimeout(temporizador); programar(d.restante);
            });
        }).catch(function () {
            texto.textContent = 'No fue posible confirmar la sesión. Verifique su conexión e intente de nuevo.';
            botonContinuar.disabled = false;
        }).then(function () { enCurso = false; });
    }

    // La sesión ya terminó en el servidor: mensaje claro; lo capturado en pantalla no se borra.
    function sesionTerminada(redirigir) {
        finalizada = true;
        clearTimeout(temporizador); clearInterval(intervalo);
        titulo.textContent = cfg.autenticado ? 'Su sesión finalizó' : 'El formulario expiró';
        texto.textContent = 'Por seguridad, la sesión terminó y la información no se puede enviar.';
        consecuencia.textContent = 'Inicie sesión nuevamente para continuar.';
        botonContinuar.hidden = true; botonActualizar.hidden = true; enlaceIr.hidden = false;
        aviso.hidden = false;
        if (redirigir) { window.location.assign(cfg.urlFinalizada); }
    }

    // 419 con la sesión todavía vigente: la página expiró (no la sesión). No se bloquea nada.
    function paginaExpirada() {
        if (finalizada) { return; }
        clearInterval(intervalo);
        titulo.textContent = 'La página expiró';
        texto.textContent = 'Su sesión sigue activa, pero esta página permaneció abierta demasiado tiempo.';
        consecuencia.textContent = 'Actualice la página para continuar. Los datos no guardados de esta pantalla deberán capturarse de nuevo.';
        botonContinuar.hidden = true; enlaceIr.hidden = true; botonActualizar.hidden = false;
        aviso.hidden = false;
    }

    botonContinuar.addEventListener('click', continuar);
    botonActualizar.addEventListener('click', function () { window.location.reload(); });

    // Solo cuando el servidor YA confirmó que la sesión terminó se impide enviar formularios (no depende del reloj
    // local ni intercepta envíos en ningún otro caso).
    document.addEventListener('submit', function (e) {
        if (finalizada) { e.preventDefault(); e.stopImmediatePropagation(); sesionTerminada(false); }
    }, true);

    // fetch existentes: la respuesta llega intacta al componente. Solo se informa si el servidor marcó la causa.
    if (fetchOriginal) {
        window.fetch = function () {
            return fetchOriginal.apply(window, arguments).then(function (r) {
                var marca = r.headers.get('X-Anfeca-Sesion');
                if (marca === 'finalizada' || marca === 'expirada') { sesionTerminada(false); }
                else if (marca === 'token') { paginaExpirada(); }
                return r;
            });
        };
    }

    // Al volver a la pestaña (p. ej. tras suspender el equipo) se verifica el estado real si el aviso no está abierto.
    var cargada = Date.now();
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden && !finalizada && aviso.hidden
            && Date.now() - cargada >= (cfg.restante - cfg.aviso - cfg.margen) * 1000) { revisar(); }
    });

    programar(cfg.restante);
})();
</script>
@endif
