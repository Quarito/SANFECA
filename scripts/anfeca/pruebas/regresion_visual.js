// ANFECA · Captura de regresión visual y de interfaz (SOLO LECTURA: GET, sin enviar formularios que escriban).
//   BASE=<url> SALIDA=<dir> C_LIC=… C_FLUJO=… C_POS=… CN_CORREO=… CN_CLAVE=… CZ_CORREO=… CZ_CLAVE=… node regresion_visual.js
// Por página y resolución: captura, URL final, HTTP, título, texto visible normalizado, campos de formulario,
// enlaces de menú, botones y errores JavaScript. Las credenciales llegan por variables (no se imprimen).
const { chromium } = require('playwright');
const fs = require('fs');
const B = process.env.BASE, S = process.env.SALIDA; fs.mkdirSync(S, { recursive: true });
const VP = { escritorio: { width: 1366, height: 768 }, tableta: { width: 820, height: 1180 }, movil: { width: 390, height: 844 } };
const espera = ms => new Promise(r => setTimeout(r, ms));
(async () => {
  const b = await chromium.launch(); const R = {};
  const contexto = async (vp) => { const c = await b.newContext({ viewport: VP[vp], deviceScaleFactor: 1, locale: 'es-MX', timezoneId: 'America/Mexico_City' }); return c; };
  const info = async (p, err) => p.evaluate(() => {
    const t = s => (s || '').replace(/\s+/g, ' ').trim();
    const ocultar = /([0-9]{1,2}\/[0-9]{1,2}\/[0-9]{4}|[0-9]{4}-[0-9]{2}-[0-9]{2}|[0-9]{1,2}:[0-9]{2}(:[0-9]{2})?|hace [^.]{1,30})/g;
    return {
      titulo: document.title,
      texto: t(document.body.innerText).replace(ocultar, '#'),
      campos: [...document.querySelectorAll('input, select, textarea')].map(e => `${e.tagName.toLowerCase()}:${e.type || ''}:${e.name || e.id}${e.required ? '*' : ''}`).filter(x => !x.includes('_token')),
      menu: [...document.querySelectorAll('nav a, aside a, .sidebar a, header a')].map(a => t(a.innerText) + '→' + (a.getAttribute('href') || '').replace(/^https?:\/\/[^/]+/, '')),
      botones: [...document.querySelectorAll('button, input[type=submit], a.boton, .btn')].map(e => t(e.innerText || e.value)).filter(Boolean),
      estilos: (() => { const cs = getComputedStyle(document.body); return [cs.fontFamily, cs.color, cs.backgroundColor, cs.fontSize].join(' | '); })(),
      alto: document.documentElement.scrollHeight, ancho: document.documentElement.scrollWidth,
      imagenes: [...document.images].map(i => (i.getAttribute('src') || '').replace(/^https?:\/\/[^/]+/, '') + (i.complete && i.naturalWidth > 0 ? '' : ' (ROTA)')),
    };
  });
  const capturar = async (p, nombre, ruta, err, accion) => {
    let status = null;
    if (ruta) { const r = await p.goto(B + ruta, { waitUntil: 'networkidle' }).catch(e => null); status = r ? r.status() : 'error'; }
    if (accion) await accion(p);
    await espera(700);
    await p.screenshot({ path: `${S}/${nombre}.png`, fullPage: true });
    R[nombre] = { ruta, status, url: p.url().replace(/^https?:\/\/[^/]+/, ''), ...(await info(p)), errores_js: err.splice(0) };
  };
  const sesion = async (vp, correo, clave) => {
    const c = await contexto(vp); const p = await c.newPage(); const err = [];
    p.on('pageerror', e => err.push(e.message)); p.on('console', m => m.type() === 'error' && err.push(m.text()));
    if (correo) { await p.goto(B + '/login'); await p.fill('#email', correo); await p.fill('#password', clave); await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]); await espera(1500); }
    return { c, p, err };
  };
  // Invitado
  for (const vp of Object.keys(VP)) { const { p, err, c } = await sesion(vp); await capturar(p, `invitado_login_${vp}`, '/login', err); await c.close(); }
  { const { p, err, c } = await sesion('escritorio');
    await capturar(p, 'invitado_recuperar', '/recuperar', err);
    await capturar(p, 'invitado_registro_datos_personales', '/registro/datos-personales', err);
    await capturar(p, 'invitado_error_404', '/ruta-inexistente-anfeca', err);
    await capturar(p, 'invitado_validacion_login_vacio', '/login', err, async q => { await q.click('button[type=submit]'); });
    await capturar(p, 'invitado_ruta_protegida', '/coordinacion', err);
    await c.close(); }
  await espera(20000);
  // Solicitante con formación guardada (borrador, escenario 1)
  { const { p, err, c } = await sesion('escritorio', 'aceptacion.licenciatura@example.test', process.env.C_LIC);
    await capturar(p, 'sol_tras_login', null, err);
    await capturar(p, 'sol_mis_datos', '/mis-datos', err);
    await capturar(p, 'sol_certificacion', '/registro/certificacion', err);
    await capturar(p, 'sol_formacion', '/registro/formacion-academica', err);
    await capturar(p, 'sol_formacion_lista_licenciaturas', null, err, async q => { await q.click('#titulo_origen'); await q.fill('.selector-flotante .selector-busqueda', 'contaduria'); await espera(1200); });
    await capturar(p, 'sol_documentos_sin_autorizar', '/registro/documentos-probatorios', err);
    await capturar(p, 'sol_sin_acceso_coordinacion', '/coordinacion', err);
    for (const vp of ['tableta', 'movil']) { await p.setViewportSize(VP[vp]); await capturar(p, `sol_formacion_${vp}`, '/registro/formacion-academica', err); }
    await c.close(); }
  { const { p, err, c } = await sesion('escritorio', 'aceptacion.posgrado@example.test', process.env.C_POS);
    await capturar(p, 'sol_formacion_con_posgrado', '/registro/formacion-academica', err);
    await capturar(p, 'sol_formacion_posgrado_lista', null, err, async q => { await q.click('#clave_posgrado_selector'); await espera(800); });
    await c.close(); }
  await espera(20000);
  { const { p, err, c } = await sesion('escritorio', 'aceptacion.flujo@example.test', process.env.C_FLUJO);
    await capturar(p, 'sol_formacion_autorizada', '/registro/formacion-academica', err);
    await capturar(p, 'sol_documentos_probatorios', '/registro/documentos-probatorios', err);
    await capturar(p, 'sol_compromiso', '/registro/compromiso', err);
    await capturar(p, 'sol_completado', '/registro/completado', err);
    await c.close(); }
  // Coordinación nacional
  { const { p, err, c } = await sesion('escritorio', process.env.CN_CORREO, process.env.CN_CLAVE);
    await capturar(p, 'coord_tras_login', null, err);
    await capturar(p, 'coord_dashboard', '/coordinacion', err);
    await capturar(p, 'coord_bandeja', '/coordinacion/solicitudes', err);
    await capturar(p, 'coord_bandeja_filtro_zona', '/coordinacion/solicitudes?zona=z3', err);
    for (const f of [3, 4, 5, 10]) await capturar(p, `coord_expediente_${f}`, `/coordinacion/solicitudes/${f}`, err);
    await capturar(p, 'coord_visor_documento', '/coordinacion/solicitudes/3', err, async q => {
      const v = q.locator('a[href*="/documentos/"], button:has-text("Ver")').first(); if (await v.count()) { await v.click().catch(() => {}); await espera(2500); } });
    await capturar(p, 'coord_seguimiento', '/coordinacion/seguimiento', err);
    await capturar(p, 'coord_revision_desactivada', '/coordinacion/solicitudes/3/revision', err);
    for (const vp of ['tableta', 'movil']) { await p.setViewportSize(VP[vp]); await capturar(p, `coord_bandeja_${vp}`, '/coordinacion/solicitudes', err); }
    await c.close(); }
  await espera(20000);
  { const { p, err, c } = await sesion('escritorio', process.env.CZ_CORREO, process.env.CZ_CLAVE);
    await capturar(p, 'zona_bandeja', '/coordinacion/solicitudes', err);
    await capturar(p, 'zona_expediente_ajeno', '/coordinacion/solicitudes/3', err);
    await capturar(p, 'zona_cierre_sesion', null, err, async q => { const f = q.locator('form[action$="/logout"] button, form[action$="/logout"] [type=submit]').first(); if (await f.count()) { await Promise.all([q.waitForNavigation().catch(() => {}), f.click()]); } });
    await capturar(p, 'zona_tras_cierre_ruta_protegida', '/coordinacion', err);
    await c.close(); }
  await b.close();
  fs.writeFileSync(`${S}/paginas.json`, JSON.stringify(R, null, 1));
  console.log(Object.keys(R).length + ' páginas capturadas');
})().catch(e => { console.error('ERROR', e.message); process.exit(1); });
