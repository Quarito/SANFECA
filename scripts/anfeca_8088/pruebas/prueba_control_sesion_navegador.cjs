// Pruebas en navegador (Playwright/Chromium) del aviso de inactividad. SOLO contra una copia aislada con
// SESSION_LIFETIME=1, ANFECA_SESION_MARGEN_SEGUNDOS=20 y ANFECA_SESION_AVISO_SEGUNDOS=15 (el aviso aparece a los 25 s).
// Uso: ANFECA_URL=http://127.0.0.1:8188 ANFECA_CORREO=... ANFECA_CLAVE=... ANFECA_CAPTURAS=<dir> node prueba_control_sesion_navegador.cjs
const { chromium } = require('playwright');

const URL = process.env.ANFECA_URL || 'http://127.0.0.1:8188';
const CAPTURAS = process.env.ANFECA_CAPTURAS || '.';
const resultados = [];
const prueba = (nombre, ok, detalle = '') => {
    resultados.push(!!ok);
    console.log(`${ok ? 'APROBADA' : 'FALLIDA '} ${nombre}${detalle ? ' — ' + detalle : ''}`);
};
const esperar = (ms) => new Promise((r) => setTimeout(r, ms));

async function entrar(contexto) {
    const p = await contexto.newPage();
    await p.goto(URL + '/login');
    await p.fill('input[name=email]', process.env.ANFECA_CORREO);
    await p.fill('input[name=password]', process.env.ANFECA_CLAVE);
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
    return p;
}

const estado = (p) => p.evaluate(async () => (await fetch('/sesion/estado', { headers: { Accept: 'application/json' } })).json());
const visible = (p) => p.locator('#anfeca-sesion-aviso').isVisible();

(async () => {
    const navegador = await chromium.launch();
    const ctx = await navegador.newContext({ viewport: { width: 1366, height: 768 } });
    const p = await entrar(ctx);
    await p.goto(URL + '/registro/certificacion', { waitUntil: 'networkidle' });
    const campo = p.locator('input[type=text]:visible, input:not([type]):visible').first();
    await campo.click();
    await campo.type('1001');                                   // usuario activo (captura sin guardar)

    await esperar(30000);                                       // pasó el primer punto de aviso (25 s)
    const e1 = await estado(p);
    prueba('Usuario activo: no recibe aviso y su actividad real renueva la sesión vigente', !(await visible(p)) && e1.autenticado && e1.restante >= 50,
        JSON.stringify(e1));

    await p.locator('#anfeca-sesion-aviso').waitFor({ state: 'visible', timeout: 40000 });
    const valores = [];
    for (let i = 0; i < 4; i++) { valores.push(Number(await p.locator('#anfeca-sesion-segundos').textContent())); await esperar(1000); }
    await p.screenshot({ path: `${CAPTURAS}/sesion_01_aviso.png` });
    prueba('Usuario inactivo: recibe el aviso antes del vencimiento', true);
    prueba('Aviso de 15 segundos con cuenta regresiva correcta', valores[0] <= 15 && valores[0] >= 13
        && valores.every((v, i) => i === 0 || v === valores[i - 1] - 1), valores.join(','));
    prueba('El aviso no elimina datos capturados sin guardar', (await campo.inputValue()) === '1001');

    const antes = p.url();
    await p.click('#anfeca-sesion-continuar');
    await p.locator('#anfeca-sesion-aviso').waitFor({ state: 'hidden', timeout: 5000 });
    const e2 = await estado(p);
    prueba('Continuar sesión: permanece conectado, en la misma pantalla y con sus datos', p.url() === antes && e2.autenticado
        && e2.restante >= 55 && (await campo.inputValue()) === '1001', JSON.stringify(e2));

    await p.locator('#anfeca-sesion-aviso').waitFor({ state: 'visible', timeout: 40000 });
    await p.waitForURL('**/sesion-finalizada', { timeout: 25000 });
    const texto = await p.textContent('body');
    await p.screenshot({ path: `${CAPTURAS}/sesion_02_finalizada.png` });
    prueba('Sin respuesta al aviso: la sesión se cierra y se muestra un mensaje amigable', texto.includes('Su sesión finalizó')
        && !/Exception|Page Expired|Stack trace/.test(texto));
    await p.goto(URL + '/mis-datos');
    prueba('Sesión vencida: no se reactiva (pide iniciar sesión)', p.url().endsWith('/login'));
    await p.goto(URL + '/login');
    const e3 = await estado(p);
    prueba('Tras el cierre por inactividad no queda sesión autenticada', e3.autenticado === false, JSON.stringify(e3));

    // Petición AJAX con la sesión ya inexistente en el servidor (se borra la cookie): Laravel 13 acepta las peticiones del
    // mismo origen por Sec-Fetch-Site (PreventRequestForgery), así que el navegador recibe 401 en lugar de 419.
    // El 419 sin Sec-Fetch-Site se comprueba en prueba_control_sesion.py.
    const ctx2 = await navegador.newContext({ viewport: { width: 1366, height: 768 } });
    const q = await entrar(ctx2);
    await q.goto(URL + '/registro/certificacion', { waitUntil: 'networkidle' });
    const campo2 = q.locator('input[type=text]:visible, input:not([type]):visible').first();
    await campo2.fill('77');
    await ctx2.clearCookies();
    const codigo = await q.evaluate(async () => (await fetch('/registro/certificacion/consultar', { method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': 'vencido' },
        body: JSON.stringify({ numero_certificado: '1' }) })).status);
    console.log('código AJAX', codigo);
    await q.locator('#anfeca-sesion-aviso').waitFor({ state: 'visible', timeout: 3000 });
    await q.screenshot({ path: `${CAPTURAS}/sesion_03_ajax_419.png` });
    prueba('Solicitud AJAX con sesión terminada (401/419): respuesta controlada y aviso sin borrar la captura', [401, 419].includes(codigo)
        && (await q.locator('#anfeca-sesion-ir').isVisible()) && (await campo2.inputValue()) === '77');
    const envio = await q.evaluate(() => { const f = document.querySelector('form[action$="/registro/certificacion"]');
        const ev = new Event('submit', { cancelable: true, bubbles: true }); f.dispatchEvent(ev); return ev.defaultPrevented; });
    prueba('Con la sesión dada por terminada se impide el envío del formulario', envio === true);

    // Acuse: su temporizador sigue funcionando y es independiente del aviso de inactividad.
    const ctx3 = await navegador.newContext();
    const r = await entrar(ctx3);
    await r.goto(URL + '/registro/completado', { waitUntil: 'domcontentloaded' });
    const ambos = (await r.locator('#aviso-cierre-acuse').count()) === 1 && (await r.locator('#anfeca-sesion-aviso').count()) === 1;
    await r.click('.boton-descargar[href*="/acuse/"]');
    await esperar(1500);
    const acuseVisible = await r.locator('#aviso-cierre-acuse').isVisible();
    const segundosAcuse = Number(await r.locator('#segundos-cierre').textContent());
    prueba('El temporizador del acuse conserva su comportamiento y no se mezcla con el aviso de inactividad',
        ambos && acuseVisible && segundosAcuse <= 14 && !(await visible(r)), `acuse=${segundosAcuse}`);
    await r.click('#cancelar-cierre-acuse');

    await navegador.close();
    console.log(`\nResultado: ${resultados.filter(Boolean).length}/${resultados.length} verificaciones aprobadas.`);
    process.exit(resultados.every(Boolean) ? 0 : 1);
})().catch((e) => { console.error('ERROR', e.message); process.exit(2); });
