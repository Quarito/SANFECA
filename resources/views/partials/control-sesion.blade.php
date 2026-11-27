{{--
    Control de inactividad de ANFECA (aviso de 15 s, «Continuar sesión», sesión finalizada y respuestas 419).
    - El servidor entrega SEGUNDOS RESTANTES (no horas absolutas): no depende de que los relojes coincidan.
    - Sin consultas periódicas: una sola consulta de estado poco antes del vencimiento, que no renueva la sesión.
    - Solo «Continuar sesión» (o actividad real del usuario en la pantalla) renueva una sesión que siga vigente.
    - No guarda datos de los formularios en el navegador; nunca borra lo capturado.
    - Independiente del temporizador del acuse (identificadores y eventos propios).
--}}
@if (config('anfeca_sesion.activo') && !request()->is('preview/*'))
@php
    $anfecaSesion = [
        'restante' => \App\Http\Middleware\ControlInactividad::restante(request()),
        'aviso' => (int) config('anfeca_sesion.aviso_segundos', 15),
        'margen' => (int) config('anfeca_sesion.margen_segundos', 45),
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
        <a id="anfeca-sesion-ir" class="anfeca-sesion-boton" href="{{ $anfecaSesion['urlFinalizada'] }}" hidden>Iniciar sesión</a>
    </div>
</div>

<form id="anfeca-sesion-cerrar" method="POST" action="{{ route('sesion.cerrar') }}" hidden>
    @csrf
</form>

<script type="application/json" id="anfeca-sesion-config">@json($anfecaSesion)</script>
<script>
(function () {
    'use strict';
    if (window.__anfecaControlSesion) { return; }
    window.__anfecaControlSesion = true;

    var cfg = JSON.parse(document.getElementById('anfeca-sesion-config').textContent);
    var aviso = document.getElementById('anfeca-sesion-aviso');
    var titulo = document.getElementById('anfeca-sesion-titulo');
    var texto = document.getElementById('anfeca-sesion-texto');
    var consecuencia = document.getElementById('anfeca-sesion-consecuencia');
    var segundosEl = document.getElementById('anfeca-sesion-segundos');
    var botonContinuar = document.getElementById('anfeca-sesion-continuar');
    var enlaceIr = document.getElementById('anfeca-sesion-ir');
    var formCerrar = document.getElementById('anfeca-sesion-cerrar');
    var token = formCerrar.querySelector('input[name="_token"]').value;
    var fetchOriginal = window.fetch ? window.fetch.bind(window) : null;

    var temporizador = null, intervalo = null, limite = 0, interaccion = false, finalizada = false, enCurso = false;

    if (!cfg.autenticado) {
        titulo.textContent = 'El formulario está por expirar';
        consecuencia.textContent = 'Si no continúa, el formulario expirará y los datos que no haya enviado se perderán.';
    }

    function programar(restante) {
        clearTimeout(temporizador);
        limite = Date.now() + restante * 1000;
        var espera = Math.max(0, restante - cfg.aviso - cfg.margen) * 1000;
        temporizador = setTimeout(revisar, Math.min(espera, 2147483000));
    }

    // Actividad REAL en la pantalla (no peticiones automáticas): permite renovar sin interrumpir a quien está capturando.
    ['keydown', 'input', 'pointerdown', 'change'].forEach(function (tipo) {
        document.addEventListener(tipo, function () { if (aviso.hidden) { interaccion = true; } }, { capture: true, passive: true });
    });

    function solicitar(url, metodo) {
        var opciones = { method: metodo, credentials: 'same-origin', cache: 'no-store',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } };
        if (metodo === 'POST') { opciones.headers['X-CSRF-TOKEN'] = token; }
        return (fetchOriginal || window.fetch)(url, opciones);
    }

    function revisar() {
        if (finalizada) { return; }
        solicitar(cfg.urlEstado, 'GET').then(function (r) {
            if (r.status === 401 || r.status === 419) { return finalizar(); }
            return r.json().then(function (d) {
                if (!d.vigente) { return finalizar(); }
                if (d.restante > cfg.aviso + cfg.margen + 5) { return programar(d.restante); } // actividad en otra pestaña
                if (interaccion) { interaccion = false; return continuar(true); }
                limite = Date.now() + d.restante * 1000;
                mostrarAviso();
            });
        }).catch(function () { mostrarAviso(); });
    }

    function mostrarAviso() {
        var segundos = cfg.aviso;
        segundosEl.textContent = segundos;
        botonContinuar.hidden = false; botonContinuar.disabled = false; enlaceIr.hidden = true;
        aviso.hidden = false;
        botonContinuar.focus({ preventScroll: true });
        clearInterval(intervalo);
        intervalo = setInterval(function () {
            segundos -= 1;
            segundosEl.textContent = Math.max(segundos, 0);
            if (segundos <= 0) { clearInterval(intervalo); intervalo = null; cerrar(); }
        }, 1000);
    }

    function ocultarAviso() {
        clearInterval(intervalo); intervalo = null;
        aviso.hidden = true;
    }

    function continuar(silencioso) {
        if (enCurso || finalizada) { return; }
        enCurso = true; botonContinuar.disabled = true;
        solicitar(cfg.urlContinuar, 'POST').then(function (r) {
            if (!r.ok) { return finalizar(); }
            return r.json().then(function (d) {
                if (!d.vigente) { return finalizar(); }
                ocultarAviso(); interaccion = false; programar(d.restante);
            });
        }).catch(function () {
            if (!silencioso) { botonContinuar.disabled = false; }
            mostrarAviso();
        }).then(function () { enCurso = false; });
    }

    function cerrar() {
        if (finalizada) { return; }
        finalizada = true;
        botonContinuar.disabled = true;
        formCerrar.submit();
    }

    // Sesión ya terminada (servidor): mensaje y acceso a iniciar sesión; los datos en pantalla no se borran.
    function sesionTerminada(redirigir) {
        finalizada = true;
        clearTimeout(temporizador); clearInterval(intervalo);
        titulo.textContent = cfg.autenticado ? 'Su sesión finalizó' : 'El formulario expiró';
        texto.textContent = 'Por seguridad, la sesión terminó y la información no se puede enviar.';
        consecuencia.textContent = 'Inicie sesión nuevamente para continuar.';
        botonContinuar.hidden = true; enlaceIr.hidden = false;
        aviso.hidden = false;
        if (redirigir) { window.location.assign(cfg.urlFinalizada); }
    }

    function finalizar() { sesionTerminada(true); }

    botonContinuar.addEventListener('click', function () { continuar(false); });

    // Impide enviar formularios con una sesión vencida (evita el error 419 y el reenvío inútil).
    document.addEventListener('submit', function (e) {
        if (e.target === formCerrar) { return; }
        if (finalizada || (limite && Date.now() >= limite)) {
            e.preventDefault(); e.stopImmediatePropagation();
            sesionTerminada(false);
        }
    }, true);

    // Evita el doble envío del mismo formulario mientras la página navega.
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (e.defaultPrevented || !(f instanceof HTMLFormElement) || f === formCerrar) { return; }
        if (f.dataset.anfecaEnviado === '1') { e.preventDefault(); return; }
        f.dataset.anfecaEnviado = '1';
    });
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('form[data-anfeca-enviado]').forEach(function (f) { delete f.dataset.anfecaEnviado; });
    });

    // Peticiones AJAX/fetch existentes: la respuesta llega intacta al componente; solo se informa al usuario.
    if (fetchOriginal) {
        window.fetch = function () {
            return fetchOriginal.apply(window, arguments).then(function (r) {
                var marca = r.headers.get('X-Anfeca-Sesion');
                // 419: token vencido · 401: sesión inexistente en el servidor (auth) · marca: vencida por inactividad.
                if (r.status === 419 || r.status === 401 || marca === 'finalizada' || marca === 'expirada') { sesionTerminada(false); }
                return r;
            });
        };
    }

    // Al volver a la pestaña (o tras suspender el equipo) se verifica el estado real.
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden && !finalizada && limite && Date.now() >= limite - (cfg.aviso + cfg.margen) * 1000 && aviso.hidden) { revisar(); }
    });

    programar(cfg.restante);
})();
</script>
@endif
