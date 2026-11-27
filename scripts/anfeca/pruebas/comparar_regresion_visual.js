// ANFECA · Compara dos capturas de regresion_visual.js (A = referencia, B = candidato). Diferencia de píxeles en el
// navegador (canvas; umbral por canal 40) y comparación de URL, HTTP, título, texto, campos, menú, botones, estilos,
// dimensiones, imágenes y errores JavaScript. Escribe <SALIDA>/comparacion.json e imágenes de diferencias.
//   A=<dir referencia> B=<dir candidato> SALIDA=<dir> node comparar_regresion_visual.js
const { chromium } = require('playwright');
const fs = require('fs');
const A = process.env.A, B = process.env.B, S = process.env.SALIDA; fs.mkdirSync(S, { recursive: true });
const ja = JSON.parse(fs.readFileSync(`${A}/paginas.json`)), jb = JSON.parse(fs.readFileSync(`${B}/paginas.json`));
(async () => {
  const b = await chromium.launch(); const p = await (await b.newContext()).newPage(); await p.setContent('<html><body></body></html>');
  const R = {};
  for (const n of Object.keys(ja)) {
    const x = ja[n], y = jb[n] || {};
    const dif = await p.evaluate(async ([u1, u2]) => {
      const carga = u => new Promise(r => { const i = new Image(); i.onload = () => r(i); i.src = u; });
      const [i1, i2] = await Promise.all([carga(u1), carga(u2)]);
      const w = Math.max(i1.width, i2.width), h = Math.max(i1.height, i2.height);
      const lienzo = (i) => { const c = document.createElement('canvas'); c.width = w; c.height = h; const g = c.getContext('2d'); g.fillStyle = '#ff00ff'; g.fillRect(0, 0, w, h); g.drawImage(i, 0, 0); return g.getImageData(0, 0, w, h); };
      const d1 = lienzo(i1), d2 = lienzo(i2); const c = document.createElement('canvas'); c.width = w; c.height = h; const g = c.getContext('2d'); const o = g.createImageData(w, h);
      let n = 0;
      for (let k = 0; k < d1.data.length; k += 4) {
        const dd = Math.max(Math.abs(d1.data[k] - d2.data[k]), Math.abs(d1.data[k + 1] - d2.data[k + 1]), Math.abs(d1.data[k + 2] - d2.data[k + 2]));
        if (dd > 40) { n++; o.data[k] = 255; o.data[k + 1] = 0; o.data[k + 2] = 0; o.data[k + 3] = 255; }
        else { const v = (d1.data[k] + d1.data[k + 1] + d1.data[k + 2]) / 3; o.data[k] = o.data[k + 1] = o.data[k + 2] = 200 + v / 5; o.data[k + 3] = 255; }
      }
      g.putImageData(o, 0, 0);
      return { pixeles: n, porcentaje: +(100 * n / (w * h)).toFixed(3), dims: [i1.width, i1.height, i2.width, i2.height], png: n ? c.toDataURL('image/png') : null };
    }, ['data:image/png;base64,' + fs.readFileSync(`${A}/${n}.png`).toString('base64'), 'data:image/png;base64,' + fs.readFileSync(`${B}/${n}.png`).toString('base64')]);
    if (dif.png) fs.writeFileSync(`${S}/dif_${n}.png`, Buffer.from(dif.png.split(',')[1], 'base64'));
    const igual = k => JSON.stringify(x[k]) === JSON.stringify(y[k]);
    const campos = ['status', 'url', 'titulo', 'texto', 'campos', 'menu', 'botones', 'estilos', 'alto', 'ancho', 'imagenes'];
    R[n] = { pixeles_distintos_pct: dif.porcentaje, dimensiones: dif.dims, difiere: campos.filter(k => !igual(k)), errores_js_A: x.errores_js, errores_js_B: y.errores_js,
             detalle: Object.fromEntries(campos.filter(k => !igual(k) && k !== 'texto').map(k => [k, [x[k], y[k]]])),
             texto: igual('texto') ? null : (() => { const a = x.texto.split(' '), c = (y.texto || '').split(' '); let i = 0; while (i < a.length && a[i] === c[i]) i++; return [a.slice(Math.max(0, i - 6), i + 14).join(' '), c.slice(Math.max(0, i - 6), i + 14).join(' ')]; })() };
  }
  await b.close();
  fs.writeFileSync(`${S}/comparacion.json`, JSON.stringify(R, null, 1));
  for (const [n, r] of Object.entries(R)) console.log(`${r.pixeles_distintos_pct.toFixed(3).padStart(7)}%  ${n.padEnd(40)} ${r.difiere.join(',') || 'igual'}${r.errores_js_B.length ? ' JS:' + r.errores_js_B.length : ''}`);
})().catch(e => { console.error('ERROR', e.message); process.exit(1); });
