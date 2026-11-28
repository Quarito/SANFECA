// Prueba en navegador del domicilio con el catálogo SEPOMEX: captura, Mis datos, modificación, autorización y bloqueo.
// Uso: ANFECA_URL=... ANFECA_CAPTURAS=<dir> node prueba_codigo_postal_navegador.cjs   (solo datos ficticios)
const { chromium } = require('playwright');

const URL = process.env.ANFECA_URL || 'http://127.0.0.1:8190';
const CAPTURAS = process.env.ANFECA_CAPTURAS || '.';
const CLAVE = 'ClaveFicticia#2026';
const resultados = [];
const prueba = (nombre, ok, detalle = '') => {
    resultados.push(!!ok);
    console.log(`${ok ? 'APROBADA' : 'FALLIDA '} ${nombre}${detalle ? ' — ' + detalle : ''}`);
};
const etiquetasDomicilio = (p) => p.$$eval('.tarjeta-etapa', (t) => {
    const s = t.find((x) => x.querySelector('h3')?.textContent.trim() === 'Domicilio');
    return s ? [...s.querySelectorAll('label')].map((l) => l.textContent.trim()) : [];
});

(async () => {
    const navegador = await chromium.launch();
    const ctx = await navegador.newContext({ viewport: { width: 1366, height: 768 } });
    const p = await ctx.newPage();
    await p.goto(URL + '/registro/datos-personales', { waitUntil: 'networkidle' });

    // Captura con el catálogo
    await p.fill('#codigo_postal', '44130');
    await p.waitForFunction(() => document.getElementById('municipio').value !== '', null, { timeout: 5000 });
    const opciones = await p.$$eval('#colonias-codigo-postal option', (o) => o.map((x) => x.value));
    prueba('El código postal llena entidad y municipio desde PostgreSQL', (await p.inputValue('#entidad_federativa')) === 'Jalisco'
        && (await p.inputValue('#municipio')) === 'Guadalajara');
    prueba('Las sugerencias de colonia son exclusivamente las del código postal ingresado',
        JSON.stringify(opciones) === JSON.stringify(['Arcos', 'Arcos Vallarta']), opciones.join(', '));
    await p.fill('#colonia', 'Colonia Inventada');
    const arbitraria = await p.$eval('#colonia', (e) => e.validationMessage);
    await p.fill('#colonia', 'arcos vallarta');
    await p.waitForFunction(() => document.getElementById('colonia').value === 'Arcos Vallarta', null, { timeout: 3000 });
    const busqueda = await p.$eval('#colonia', (e) => [e.validationMessage, e.value]);
    prueba('Colonia: se puede buscar entre las opciones, pero no se acepta un valor fuera del catálogo',
        arbitraria.includes('colonias registradas') && busqueda[0] === '' && busqueda[1] === 'Arcos Vallarta', `${arbitraria} · ${busqueda[1]}`);
    await p.fill('#codigo_postal', '04510');
    await p.waitForFunction(() => document.getElementById('municipio').value === 'Coyoacán', null, { timeout: 5000 });
    const opciones2 = await p.$$eval('#colonias-codigo-postal option', (o) => o.map((x) => x.value));
    prueba('Al cambiar de código postal las sugerencias se reemplazan y una colonia única se selecciona',
        (await p.inputValue('#colonia')) === 'Universidad Nacional Autónoma de México' && opciones2.length === 1);
    await p.fill('#codigo_postal', '99999');
    await p.waitForFunction(() => document.getElementById('codigo_postal').validationMessage !== '', null, { timeout: 5000 });
    prueba('Código postal inexistente: se impide enviar con un mensaje claro', (await p.$eval('#codigo_postal', (e) => e.validationMessage)).includes('no se encuentra'));

    // Registro completo de primera vez
    const sufijo = String(Date.now()).slice(-6);
    const curp = `FICT8${sufijo.slice(0, 5)}MDFCTA0${sufijo.slice(5)}`.slice(0, 18);
    const correo = `cp.${sufijo}@example.test`;
    await p.fill('#codigo_postal', '44130');
    await p.waitForFunction(() => document.getElementById('municipio').value === 'Guadalajara', null, { timeout: 5000 });
    await p.fill('#colonia', 'Arcos Vallarta');
    for (const [k, v] of Object.entries({ curp, rfc: curp.slice(0, 10) + 'AB1', nombres: 'Registro Postal', apellido_paterno: 'Ficticio',
        calle: 'Calle Uno', numero_exterior: '10', numero_interior: '2', correo_electronico: correo, telefono_celular: '3311112222' })) {
        await p.fill('#' + k, v);
    }
    await p.selectOption('#genero', 'Femenino');
    await p.fill('input[name=password]', CLAVE);
    await p.fill('input[name=password_confirmation]', CLAVE);
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
    prueba('Registro inicial con domicilio validado contra SEPOMEX', p.url().endsWith('/mis-datos'), p.url());

    // Mis datos (consulta y autorización): solo lo que guarda el modelo
    await p.goto(URL + '/mis-datos', { waitUntil: 'networkidle' });
    const visibles = await etiquetasDomicilio(p);
    await p.screenshot({ path: `${CAPTURAS}/cp_02_mis_datos.png`, fullPage: true });
    prueba('Mis datos muestra solo calle, número exterior, número interior y entidad federativa',
        JSON.stringify(visibles) === JSON.stringify(['Calle', 'Número exterior', 'Número interior', 'Entidad federativa'])
        && !(await p.textContent('body')).includes('Código postal'), visibles.join(', '));

    // Modificación antes de autorizar
    await p.click('button:has-text("Modificar datos")');
    const indicacion = await p.locator('.indicacion-codigo-postal').textContent();
    const enEdicion = await etiquetasDomicilio(p);
    await p.screenshot({ path: `${CAPTURAS}/cp_03_modificar.png`, fullPage: true });
    prueba('Modificar: conserva lo registrado, captura de nuevo CP, colonia y municipio, e indica revalidar el código postal',
        (await p.inputValue('#actualizar-calle')) === 'Calle Uno' && (await p.inputValue('#actualizar-entidad_federativa')) === 'Jalisco'
        && enEdicion.includes('Código postal') && enEdicion.includes('Colonia')
        && indicacion.trim() === 'Ingrese nuevamente su código postal para validar los datos de su domicilio.');
    await p.fill('#actualizar-calle', 'Calle Dos');
    await p.fill('#actualizar-codigo_postal', '44100');
    await p.waitForFunction(() => document.getElementById('actualizar-colonia').value === 'Guadalajara Centro', null, { timeout: 5000 });
    await Promise.all([p.waitForNavigation(), p.click('button:has-text("Guardar cambios")')]);
    prueba('Modificación guardada con el domicilio revalidado', (await p.textContent('body')).includes('Calle Dos'));

    // Recuperación después de cerrar sesión
    await Promise.all([p.waitForNavigation(), p.click('button:has-text("Cerrar sesión")')]);
    await p.fill('input[name=email]', correo);
    await p.fill('input[name=password]', CLAVE);
    await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
    await p.goto(URL + '/mis-datos', { waitUntil: 'networkidle' });
    const texto = await p.textContent('body');
    prueba('Tras cerrar e iniciar sesión se recuperan calle, números y entidad', texto.includes('Calle Dos') && texto.includes('10')
        && texto.includes('Jalisco') && !texto.includes('No registrado\n') && !texto.includes('Guadalajara Centro'));

    // Autorización y bloqueo
    await p.check('input[name=confirmacion_datos]');
    await Promise.all([p.waitForNavigation(), p.click('button:has-text("Autorizar y continuar")')]);
    prueba('La autorización procede con los atributos exigibles del modelo vigente', p.url().endsWith('/registro/certificacion'), p.url());
    await p.goto(URL + '/mis-datos', { waitUntil: 'networkidle' });
    prueba('Después de autorizar no se ofrece modificar los datos', (await p.locator('button:has-text("Modificar datos")').count()) === 0
        && JSON.stringify(await etiquetasDomicilio(p)) === JSON.stringify(['Calle', 'Número exterior', 'Número interior', 'Entidad federativa']));
    await p.screenshot({ path: `${CAPTURAS}/cp_04_autorizado.png`, fullPage: true });

    await navegador.close();
    console.log(`\nResultado: ${resultados.filter(Boolean).length}/${resultados.length} verificaciones aprobadas.`);
    process.exit(resultados.every(Boolean) ? 0 : 1);
})().catch((e) => { console.error('ERROR', e.message); process.exit(2); });
