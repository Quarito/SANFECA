// Prueba en navegador del domicilio con el catálogo SEPOMEX (registro inicial, sin cambios de distribución).
// Uso: ANFECA_URL=... ANFECA_CAPTURAS=<dir> node prueba_codigo_postal_navegador.cjs   (datos ficticios)
const { chromium } = require('playwright');

const URL = process.env.ANFECA_URL || 'http://127.0.0.1:8190';
const CAPTURAS = process.env.ANFECA_CAPTURAS || '.';
const resultados = [];
const prueba = (nombre, ok, detalle = '') => {
    resultados.push(!!ok);
    console.log(`${ok ? 'APROBADA' : 'FALLIDA '} ${nombre}${detalle ? ' — ' + detalle : ''}`);
};

(async () => {
    const navegador = await chromium.launch();
    const p = await (await navegador.newContext({ viewport: { width: 1366, height: 768 } })).newPage();
    await p.goto(URL + '/registro/datos-personales', { waitUntil: 'networkidle' });

    await p.fill('#codigo_postal', '44130');
    await p.waitForFunction(() => document.getElementById('municipio').value !== '', null, { timeout: 5000 });
    const opciones = await p.$$eval('#colonias-codigo-postal option', (o) => o.map((x) => x.value));
    prueba('El código postal llena entidad y municipio desde PostgreSQL', (await p.inputValue('#entidad_federativa')) === 'Jalisco'
        && (await p.inputValue('#municipio')) === 'Guadalajara');
    prueba('Las colonias del código postal se ofrecen como sugerencias del mismo campo', JSON.stringify(opciones) === JSON.stringify(['Arcos', 'Arcos Vallarta']),
        opciones.join(', '));

    await p.fill('#codigo_postal', '04510');
    await p.waitForFunction(() => document.getElementById('municipio').value === 'Coyoacán', null, { timeout: 5000 });
    prueba('Código postal con una sola colonia: se selecciona automáticamente', (await p.inputValue('#colonia')) === 'Universidad Nacional Autónoma de México'
        && (await p.inputValue('#entidad_federativa')) === 'Ciudad de México');

    await p.fill('#codigo_postal', '99999');
    await p.waitForFunction(() => document.getElementById('codigo_postal').validationMessage !== '', null, { timeout: 5000 });
    prueba('Código postal inexistente: el navegador impide enviar con un mensaje claro', (await p.$eval('#codigo_postal', (e) => e.validationMessage))
        .includes('no se encuentra en el catálogo'));

    // Registro completo desde la pantalla.
    const sufijo = String(Date.now()).slice(-6);
    const curp = `FICT8${sufijo.slice(0, 5)}MDFCTA0${sufijo.slice(5)}`.slice(0, 18);
    await p.fill('#codigo_postal', '44130');
    await p.waitForFunction(() => document.getElementById('municipio').value === 'Guadalajara', null, { timeout: 5000 });
    await p.fill('#colonia', 'Arcos Vallarta');
    const datos = { curp, rfc: curp.slice(0, 10) + 'AB1', nombres: 'Registro Postal', apellido_paterno: 'Ficticio', calle: 'Calle Uno',
        numero_exterior: '10', correo_electronico: `cp.${sufijo}@example.test`, telefono_celular: '3311112222' };
    for (const [k, v] of Object.entries(datos)) { await p.fill('#' + k, v); }
    await p.selectOption('#genero', 'Femenino');
    await p.fill('input[name=password]', 'ClaveFicticia#2026');
    await p.fill('input[name=password_confirmation]', 'ClaveFicticia#2026');
    await p.screenshot({ path: `${CAPTURAS}/cp_01_registro.png` });
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
    prueba('Registro inicial con domicilio validado contra SEPOMEX', p.url().endsWith('/registro/certificacion') || p.url().endsWith('/mis-datos'), p.url());

    await p.goto(URL + '/mis-datos', { waitUntil: 'networkidle' });
    const texto = await p.textContent('body');
    await p.screenshot({ path: `${CAPTURAS}/cp_02_mis_datos.png`, fullPage: true });
    prueba('Tras guardar se recuperan calle, números y entidad; CP, colonia y municipio no son recuperables',
        texto.includes('Calle Uno') && texto.includes('Jalisco') && !texto.includes('Arcos Vallarta') && !texto.includes('44130'));
    await Promise.all([p.waitForNavigation(), p.check('input[name=confirmacion_datos]').then(() => p.click('form[action$="/mis-datos/autorizar"] button[type=submit]'))]);
    prueba('La autorización procede con los atributos exigibles del modelo vigente', p.url().endsWith('/registro/certificacion'), p.url());

    await navegador.close();
    console.log(`\nResultado: ${resultados.filter(Boolean).length}/${resultados.length} verificaciones aprobadas.`);
    process.exit(resultados.every(Boolean) ? 0 : 1);
})().catch((e) => { console.error('ERROR', e.message); process.exit(2); });
