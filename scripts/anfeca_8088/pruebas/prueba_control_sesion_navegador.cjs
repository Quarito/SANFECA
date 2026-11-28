// Pruebas en navegador (Playwright/Chromium) del aviso de inactividad. SOLO contra una copia aislada con
// SESSION_LIFETIME=1, aviso 15 s y margen 5 s (el aviso aparece 20 s antes del límite: a los 40 s).
// Uso: ANFECA_URL=... ANFECA_CORREO=... ANFECA_CLAVE=... ANFECA_SESIONES=<sessions de la copia> ANFECA_CAPTURAS=<dir>
//      node prueba_control_sesion_navegador.cjs
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const URL = process.env.ANFECA_URL || 'http://127.0.0.1:8189';
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
const campoDe = (p) => p.locator('input[type=text]:visible, input:not([type]):visible').first();

(async () => {
    const navegador = await chromium.launch();

    // 1) Teclear sin peticiones NO renueva: el aviso aparece igual.
    const ctx = await navegador.newContext({ viewport: { width: 1366, height: 768 } });
    const p = await entrar(ctx);
    await p.goto(URL + '/registro/certificacion', { waitUntil: 'networkidle' });
    const t0 = Date.now();
    const campo = campoDe(p);
    await campo.click();
    await campo.type('1001');
    await p.locator('#anfeca-sesion-aviso').waitFor({ state: 'visible', timeout: 50000 });
    const tAviso = (Date.now() - t0) / 1000;
    prueba('La actividad local (teclear) no renueva: el aviso aparece ~20 s antes del límite', tAviso >= 37 && tAviso <= 45, `${tAviso.toFixed(1)} s`);
    const valores = [];
    for (let i = 0; i < 4; i++) { valores.push(Number(await p.locator('#anfeca-sesion-segundos').textContent())); await esperar(1000); }
    await p.screenshot({ path: `${CAPTURAS}/sesion_01_aviso.png` });
    prueba('Cuenta regresiva de 15 segundos correcta', valores[0] <= 15 && valores[0] >= 13 && valores.every((v, i) => i === 0 || v === valores[i - 1] - 1), valores.join(','));
    prueba('El aviso no elimina datos capturados sin guardar', (await campo.inputValue()) === '1001');

    // 2) Continuar sesión: acción explícita.
    const antes = p.url();
    await p.click('#anfeca-sesion-continuar');
    await p.locator('#anfeca-sesion-aviso').waitFor({ state: 'hidden', timeout: 5000 });
    const tContinuar = Date.now();
    const e2 = await estado(p);
    prueba('Continuar sesión (clic): misma pantalla, datos intactos y sesión renovada', p.url() === antes && e2.autenticado
        && e2.restante >= 55 && (await campo.inputValue()) === '1001', JSON.stringify(e2));

    // 3) Sin respuesta: el cierre ocurre al cumplirse el límite en el servidor, nunca antes.
    await p.locator('#anfeca-sesion-aviso').waitFor({ state: 'visible', timeout: 50000 });
    await p.waitForURL('**/sesion-finalizada', { timeout: 40000 });
    const tCierre = (Date.now() - tContinuar) / 1000;
    const texto = await p.textContent('body');
    await p.screenshot({ path: `${CAPTURAS}/sesion_02_finalizada.png` });
    prueba('Sin respuesta: la sesión termina al cumplirse los 60 s del límite (no antes) y se informa', tCierre >= 60 && tCierre <= 66
        && texto.includes('Su sesión finalizó') && !/Exception|Page Expired|Stack trace/.test(texto), `cierre a ${tCierre.toFixed(1)} s`);
    await p.goto(URL + '/mis-datos');
    prueba('Sesión vencida: no se reactiva (pide iniciar sesión)', p.url().endsWith('/login'));

    // 4) Actividad válida de Laravel (petición al servidor) renueva sin interrumpir.
    const ctx2 = await navegador.newContext({ viewport: { width: 1366, height: 768 } });
    const q = await entrar(ctx2);
    await q.goto(URL + '/registro/certificacion', { waitUntil: 'networkidle' });
    await esperar(15000);
    await q.evaluate(() => fetch('/registro/certificacion', { headers: { Accept: 'text/html' } }));   // petición normal (actividad)
    await esperar(32000);                                                                            // pasó el punto de aviso original (40 s)
    prueba('Una petición normal al servidor cuenta como actividad: no aparece el aviso en ese ciclo', !(await visible(q)));

    // 5) 401 de otro origen (sin marca) no se presenta como sesión vencida; la respuesta llega intacta.
    const r401 = await q.evaluate(async () => { const r = await fetch('/coordinacion/api/solicitudes', { headers: { Accept: 'application/json' } });
        return [r.status, (await r.text()).length > 0]; });
    prueba('Respuesta de error sin marca de sesión (403 de Coordinación): sin aviso de sesión vencida', r401[0] === 403 && !(await visible(q)), `código ${r401[0]}`);

    // 6) Formularios y Vue: sin bloqueo de reintentos; un formulario manejado por la página puede enviarse varias veces.
    const reintentos = await q.evaluate(() => {
        const f = document.createElement('form'); let enviados = 0;
        f.addEventListener('submit', (e) => { e.preventDefault(); enviados++; });
        document.body.appendChild(f);
        for (let i = 0; i < 3; i++) { f.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true })); }
        f.remove(); return enviados;
    });
    prueba('No hay bloqueo global de envíos: reintentos y formularios gestionados por la página funcionan', reintentos === 3, `${reintentos}/3`);

    // 7) Sesión perdida en el servidor: el 401 marcado se informa sin alterar la respuesta del componente.
    const campo2 = campoDe(q);
    await campo2.fill('77');
    if (process.env.ANFECA_SESIONES) {
        for (const f of fs.readdirSync(process.env.ANFECA_SESIONES)) { if (f !== '.gitignore') fs.unlinkSync(path.join(process.env.ANFECA_SESIONES, f)); }
    }
    const ajax = await q.evaluate(async () => { const r = await fetch('/registro/catalogos/licenciaturas?q=adm', { headers: { Accept: 'application/json' } });
        return [r.status, r.headers.get('X-Anfeca-Sesion'), (await r.json()).message]; });
    await q.locator('#anfeca-sesion-aviso').waitFor({ state: 'visible', timeout: 3000 });
    await q.screenshot({ path: `${CAPTURAS}/sesion_03_sesion_perdida.png` });
    prueba('AJAX con sesión terminada: respuesta original (401) y aviso claro sin borrar la captura', ajax[0] === 401 && ajax[1] === 'finalizada'
        && ajax[2] === 'Unauthenticated.' && (await q.locator('#anfeca-sesion-ir').isVisible()) && (await campo2.inputValue()) === '77', ajax.join(' · '));
    const bloqueado = await q.evaluate(() => { const f = document.querySelector('form[action$="/registro/certificacion"]');
        const ev = new Event('submit', { cancelable: true, bubbles: true }); f.dispatchEvent(ev); return ev.defaultPrevented; });
    prueba('Solo con la sesión confirmada como terminada se impide el envío', bloqueado === true);

    // 8) Visitante que captura su registro inicial.
    const ctx3 = await navegador.newContext({ viewport: { width: 1366, height: 768 } });
    const v = await ctx3.newPage();
    await v.goto(URL + '/registro/datos-personales', { waitUntil: 'networkidle' });
    await v.fill('input[name=nombres]', 'Visitante Ficticio');
    await v.locator('#anfeca-sesion-aviso').waitFor({ state: 'visible', timeout: 50000 });
    const tituloV = await v.textContent('#anfeca-sesion-titulo');
    await v.screenshot({ path: `${CAPTURAS}/sesion_04_visitante.png` });
    await v.click('#anfeca-sesion-continuar');
    await v.locator('#anfeca-sesion-aviso').waitFor({ state: 'hidden', timeout: 5000 });
    prueba('Visitante: aviso «El formulario está por expirar», continúa en la misma pantalla y conserva lo capturado',
        tituloV.includes('El formulario está por expirar') && v.url().endsWith('/registro/datos-personales')
        && (await v.inputValue('input[name=nombres]')) === 'Visitante Ficticio');

    // 9) Acuse: su temporizador conserva el comportamiento y no se mezcla con el aviso.
    const ctx4 = await navegador.newContext();
    const r = await entrar(ctx4);
    await r.goto(URL + '/registro/completado', { waitUntil: 'domcontentloaded' });
    await r.click('.boton-descargar[href*="/acuse/"]');
    await esperar(1500);
    const segundosAcuse = Number(await r.locator('#segundos-cierre').textContent());
    prueba('El temporizador del acuse funciona igual y es independiente del aviso de inactividad',
        (await r.locator('#aviso-cierre-acuse').isVisible()) && segundosAcuse <= 14 && !(await visible(r)), `acuse=${segundosAcuse}`);
    await r.click('#cancelar-cierre-acuse');

    await navegador.close();
    console.log(`\nResultado: ${resultados.filter(Boolean).length}/${resultados.length} verificaciones aprobadas.`);
    process.exit(resultados.every(Boolean) ? 0 : 1);
})().catch((e) => { console.error('ERROR', e.message); process.exit(2); });
