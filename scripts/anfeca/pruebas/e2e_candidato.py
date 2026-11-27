#!/usr/bin/env python3
"""ANFECA · Pruebas funcionales de extremo a extremo (HTTP real) contra una aplicación CANDIDATA.

    python3 -I scripts/anfeca/pruebas/e2e_candidato.py http://127.0.0.1:18086 <salida.json> <documentos_huellas.json>

- Solo admite una URL de loopback que NO sea el puerto 8086 en vivo: la base conectada debe ser una COPIA
  de pruebas (el script crea solicitantes, solicitudes y archivos ficticios).
- Solicitantes: registro desde cero, bloqueo hasta autorizar datos, autorización, CURP duplicada,
  certificación por primera vez, refrendo con certificado histórico ficticio y número inexistente,
  formación (catálogo de licenciaturas con filtro por área y posgrado), documentos PDF/JPG/PNG,
  compromiso, envío, acuse PDF y consulta posterior con edición bloqueada.
- Coordinación: acceso por rol, dashboard, bandeja, API y filtros, expedientes nuevos e históricos
  (solo lectura), visor de documentos (tipo y huella), historial, revisión documental desactivada,
  coordinación de zona y rechazo a solicitantes.
Credenciales de coordinación: ~/ANFECA_CREDENCIALES/coordinacion_ensayo_8086.tsv (no se imprimen).
"""
import hashlib, io, json, os, re, sys, time, urllib.parse, zlib, struct
import requests

BASE, SALIDA, HUELLAS = sys.argv[1].rstrip('/'), sys.argv[2], sys.argv[3]
u = urllib.parse.urlparse(BASE)
if u.hostname not in ('127.0.0.1', '::1', 'localhost') or u.port in (8086, 8084, 8085, 8008):
    sys.exit('DETENIDO: use la URL de loopback de la aplicación candidata (nunca un ambiente en servicio).')
TC = 'Profesoras y profesores de Tiempo Completo'
R = {'v': {}, 'detalle': {}}
def ok(k, c, d=''):
    R['v'][k] = 'ok' if c else f'FALLA {d}'[:400]

def pdf(t): return (f'{t}.pdf', b'%PDF-1.4\n% ANFECA prueba ficticia ' + t.encode() + b'\n%%EOF\n', 'application/pdf')
def png(t):
    raw = b'\x00\xff\x00\x00' * 1
    def ch(k, d): return struct.pack('>I', len(d)) + k + d + struct.pack('>I', zlib.crc32(k + d) & 0xffffffff)
    return (f'{t}.png', b'\x89PNG\r\n\x1a\n' + ch(b'IHDR', struct.pack('>IIBBBBB', 1, 1, 8, 2, 0, 0, 0)) + ch(b'IDAT', zlib.compress(raw)) + ch(b'IEND', b''), 'image/png')
def jpg(t):  # JPEG mínimo válido (1x1)
    return (f'{t}.jpg', bytes.fromhex('ffd8ffe000104a46494600010100000100010000ffdb004300080606070605080707070909080a0c140d0c0b0b0c1912130f141d1a1f1e1d1a1c1c20242e2720222c231c1c2837292c30313434341f27393d38323c2e333432ffc0000b080001000101011100ffc4001f0000010501010101010100000000000000000102030405060708090a0bffc400b5100002010303020403050504040000017d01020300041105122131410613516107227114328191a1082342b1c11552d1f02433627282090a161718191a25262728292a3435363738393a434445464748494a535455565758595a636465666768696a737475767778797a838485868788898a92939495969798999aa2a3a4a5a6a7a8a9aab2b3b4b5b6b7b8b9bac2c3c4c5c6c7c8c9cad2d3d4d5d6d7d8d9dae1e2e3e4e5e6e7e8e9eaf1f2f3f4f5f6f7f8f9faffda0008010100003f00fbd3ffd9'), 'image/jpeg')

class Sesion:
    def __init__(s): s.h = requests.Session(); s.h.headers['User-Agent'] = 'anfeca-e2e'
    def token(s, ruta):
        r = s.h.get(BASE + ruta); m = re.search(r'name="_token" value="([^"]+)"', r.text) or re.search(r'name="csrf-token" content="([^"]+)"', r.text)
        return r, (m.group(1) if m else None)
    def post(s, ruta, datos, archivos=None, desde=None):
        _, t = s.token(desde or ruta)
        return s.h.post(BASE + ruta, data={**datos, '_token': t}, files=archivos, allow_redirects=False)
    def get(s, ruta, **k): return s.h.get(BASE + ruta, allow_redirects=False, **k)
    def entrar(s, correo, clave):
        r = s.post('/login', {'email': correo, 'password': clave}); return r.headers.get('location', '')


ts = time.strftime('%H%M%S')
def curp(n): return os.environ[f'E2E_CURP{n}']   # CURP ficticias únicas por ejecución

def registrar(n, correo):
    s = Sesion(); c = curp(n)
    r = s.post('/registro/datos-personales', {'curp': c, 'rfc': c[:10] + c[-3:], 'nombres': 'Prueba', 'apellido_paterno': f'Ficticia{n}',
        'genero': 'Femenino', 'calle': 'Calle Ficticia', 'numero_exterior': '10', 'codigo_postal': '01000', 'entidad_federativa': 'Ciudad de México',
        'colonia': 'Centro', 'municipio': 'Cuauhtémoc', 'correo_electronico': correo, 'telefono_celular': '5511111111', 'telefono_particular': '5522222222',
        'password': 'ContrasenaFicticia1', 'password_confirmation': 'ContrasenaFicticia1'})
    return s, c, r

def autorizar_datos(s):
    bloqueo = s.get('/registro/certificacion')
    r = s.post('/mis-datos/autorizar', {'confirmacion_datos': '1'}, desde='/mis-datos')
    return bloqueo, r

def formacion(s, area_cert, area_lic, q, posgrado=None):
    lic = s.get('/registro/catalogos/licenciaturas?' + urllib.parse.urlencode({'q': q, 'area': area_lic}), headers={'Accept': 'application/json'}).json()
    clave = lic['titulos'][0]['clave']
    d = {'nivel_origen': 'Licenciatura', 'modo_titulo_origen': 'catalogo', 'clave_licenciatura_catalogo': clave, 'institucion_afiliada_id': '3', 'cuenta_con_posgrado': '0'}
    f = {'titulo_origen_archivo': pdf('titulo'), 'cedula_origen_archivo': pdf('cedula')}
    if posgrado:
        nivel, area, qp = posgrado
        pos = s.get('/registro/catalogos/posgrados?' + urllib.parse.urlencode({'nivel': nivel, 'area': area, 'q': qp}), headers={'Accept': 'application/json'}).json()
        d.update({'cuenta_con_posgrado': '1', 'nivel_ultimo_grado': nivel, 'titulo_ultimo_grado': area, 'modo_titulo_ultimo_grado': 'catalogo',
                  'clave_posgrado_catalogo': pos['titulos'][0]['clave']})
        f.update({'titulo_ultimo_grado_archivo': pdf('titulo_ug'), 'cedula_ultimo_grado_archivo': pdf('cedula_ug')})
    r = s.post('/registro/formacion-academica', d, f)
    a = s.post('/registro/formacion-academica/autorizar', {'confirmacion_formacion': '1'}, desde='/registro/formacion-academica')
    return lic, r, a

def hasta_acuse(s, docs):
    r = s.post('/registro/documentos-probatorios', {'link': 'https://example.test/evidencias'}, docs)
    c = s.post('/registro/compromiso/guardar', {'compromiso_aceptado': '1'}, desde='/registro/compromiso')
    e = s.post('/registro/completado/confirmar', {}, desde='/registro/completado')
    m = re.search(r'/registro/acuse/(\d+)', s.h.get(BASE + '/registro/completado').text)
    acuse = s.get(f'/registro/acuse/{m.group(1)}') if m else None
    return r, c, e, (int(m.group(1)) if m else None), acuse

# ------------------------------------------------------------------ Solicitante 1: primera vez
correo1 = f'e2e.primera.{ts}@example.test'
s1, c1, r = registrar(1, correo1)
ok('registro_nuevo', r.status_code == 302 and '/login' not in r.headers.get('location', ''), f'{r.status_code} {r.headers.get("location")}')
_, rdup = registrar(1, f'e2e.dup.{ts}@example.test')[0], registrar(1, f'e2e.dup.{ts}@example.test')[2]
ok('curp_duplicada_rechazada', rdup.status_code == 302 and 'datos-personales' in rdup.headers.get('location', ''), rdup.headers.get('location'))
bloqueo, r = autorizar_datos(s1)
ok('certificacion_bloqueada_sin_autorizar_datos', bloqueo.status_code == 302 and 'mis-datos' in bloqueo.headers.get('location', ''), bloqueo.headers.get('location'))
ok('autorizacion_datos', r.status_code == 302, r.status_code)
r = s1.post('/registro/certificacion', {'certificacion': 'ANFECA', 'tipo_personal': TC, 'area_certificacion': 'Contaduría Pública'})
ok('certificacion_primera_vez', 'formacion-academica' in r.headers.get('location', ''), r.headers.get('location'))
lic, rf, ra = formacion(s1, 'Contaduría Pública', 'Contaduría', 'contaduria publica')
R['detalle']['licenciatura_filtrada'] = {'total': lic.get('total'), 'primero': lic['titulos'][0]['titulo']}
ok('formacion_guardada', rf.status_code == 302 and 'formacion-academica' in rf.headers.get('location', '') and lic['titulos'][0]['clave'] in s1.h.get(BASE + '/registro/formacion-academica').text, rf.status_code)
ok('formacion_autorizada', ra.status_code == 302 and 'documentos' in ra.headers.get('location', ''), ra.headers.get('location'))
r, c, e, sol1, acuse = hasta_acuse(s1, {'constancia_antiguedad_archivo': png('constancia'), 'comprobante_pago_archivo': jpg('pago')})
ok('documentos_png_jpg', r.status_code == 302, r.status_code); ok('compromiso', c.status_code == 302, c.status_code)
ok('envio', e.status_code == 302, e.status_code)
ok('acuse_pdf', acuse is not None and acuse.status_code == 200 and acuse.content[:4] == b'%PDF' and 'pdf' in acuse.headers.get('content-type', ''), acuse and acuse.status_code)
cons = s1.h.get(BASE + '/registro/completado')
ok('consulta_posterior', cons.status_code == 200 and str(sol1) in cons.text, cons.status_code)
antes = s1.h.get(BASE + '/mis-datos').text.count('Calle Ficticia')
s1.post('/mis-datos', {'_method': 'PUT', 'calle': 'Calle Modificada'}, desde='/mis-datos')
ok('edicion_bloqueada_tras_autorizar', 'Calle Modificada' not in s1.h.get(BASE + '/mis-datos').text and antes > 0)
R['detalle']['solicitud_primera_vez'] = sol1

# ------------------------------------------------------------------ Solicitante 2: refrendo con posgrado
correo2 = f'e2e.refrendo.{ts}@example.test'
s2, c2, r = registrar(2, correo2); autorizar_datos(s2)
r = s2.post('/registro/certificacion', {'certificacion': 'ANFECA', 'tipo_personal': TC, 'area_certificacion': 'Administración', 'numero_certificado_anterior': '9999998'})
ok('refrendo_numero_inexistente_rechazado', 'refrendo' not in r.headers.get('location', ''), r.headers.get('location'))
r = s2.post('/registro/certificacion', {'certificacion': 'ANFECA', 'tipo_personal': TC, 'area_certificacion': 'Administración', 'numero_certificado_anterior': os.environ['E2E_CERT_REFRENDO']})
ok('refrendo_certificado_historico', 'refrendo' in r.headers.get('location', ''), r.headers.get('location'))
r = s2.post('/registro/refrendo', {'aplica_articulo_14': '0'})
ok('datos_refrendo', 'formacion-academica' in r.headers.get('location', ''), r.headers.get('location'))
lic, rf, ra = formacion(s2, 'Administración', 'Administración', 'licenciatura en administracion', ('Maestría', 'Administración', 'administracion'))
ok('formacion_con_posgrado', rf.status_code == 302 and ra.status_code == 302 and 'documentos' in ra.headers.get('location', ''), f'{rf.status_code} {ra.headers.get("location")}')
r, c, e, sol2, acuse = hasta_acuse(s2, {'constancia_antiguedad_archivo': pdf('constancia'), 'comprobante_pago_archivo': pdf('pago')})
ok('refrendo_enviado_acuse', acuse is not None and acuse.content[:4] == b'%PDF', acuse and acuse.status_code)
R['detalle']['solicitud_refrendo'] = sol2

# ------------------------------------------------------------------ Coordinación
cred = [l.rstrip('\n').split('\t') for l in open(os.path.expanduser('~/ANFECA_CREDENCIALES/coordinacion_ensayo_8086.tsv')) if '\t' in l]
nac = next(x for x in cred if x[1] == 'coordinador_nacional'); zona = [x for x in cred if x[1] == 'coordinador_zona']
cn = Sesion(); loc = cn.entrar(nac[0], nac[2])
ok('login_coordinacion', '/coordinacion' in loc, loc)
ok('dashboard', cn.h.get(BASE + '/coordinacion').status_code == 200 and cn.h.get(BASE + '/coordinacion/api/indicadores', headers={'Accept': 'application/json'}).status_code == 200)
api = cn.h.get(BASE + '/coordinacion/api/solicitudes?por_pagina=50', headers={'Accept': 'application/json'}).json()
folios = [x['folio'] for x in api['datos']]
ok('bandeja_incluye_nuevas_e_historicas', sol1 in folios and sol2 in folios and all(f in folios for f in (3, 4, 5)), folios)
ok('filtro_zona_valido', cn.h.get(BASE + '/coordinacion/api/solicitudes?zona=z3', headers={'Accept': 'application/json'}).status_code == 200)
ok('filtro_zona_manipulado_rechazado', cn.h.get(BASE + '/coordinacion/api/solicitudes?zona=x;drop', headers={'Accept': 'application/json'}).status_code == 422)
for f in (sol1, sol2):
    t = cn.h.get(BASE + f'/coordinacion/solicitudes/{f}').text
    ok(f'expediente_{f}_formacion', 'catálogo de licenciaturas' in t and 'Prueba' in t)
ok('expediente_refrendo_posgrado', 'Maestr' in cn.h.get(BASE + f'/coordinacion/solicitudes/{sol2}').text)
for f, campo, tipo in ((sol1, 'constancia_antiguedad_archivo', 'image/png'), (sol1, 'comprobante_pago_archivo', 'image/jpeg'), (sol1, 'titulo_origen_archivo', 'application/pdf')):
    r = cn.h.get(BASE + f'/coordinacion/solicitudes/{f}/documentos/{campo}')
    ok(f'visor_{tipo.split("/")[1]}', r.status_code == 200 and tipo in r.headers.get('content-type', ''), f'{r.status_code} {r.headers.get("content-type")}')
huellas = json.load(open(HUELLAS))['documentos']; iguales = 0
for ruta, h in huellas.items():
    r = cn.h.get(BASE + ruta); iguales += r.status_code == 200 and hashlib.sha256(r.content).hexdigest() == h['sha256']
ok('documentos_historicos_identicos', iguales == len(huellas), f'{iguales}/{len(huellas)}'); R['detalle']['documentos_historicos'] = f'{iguales}/{len(huellas)}'
for f in (3, 4, 5):
    ok(f'expediente_historico_{f}', cn.h.get(BASE + f'/coordinacion/solicitudes/{f}').status_code == 200)
seg = cn.h.get(BASE + '/coordinacion/api/seguimiento', headers={'Accept': 'application/json'})
ok('seguimiento_historial', seg.status_code == 200 and cn.h.get(BASE + '/coordinacion/seguimiento').status_code == 200, seg.status_code)
rv = cn.get(f'/coordinacion/solicitudes/{sol1}/documentos/titulo_origen_archivo/revisiones', headers={'Accept': 'application/json'})
R['detalle']['revision_documental_http'] = rv.status_code
ok('revision_documental_desactivada', rv.status_code in (403, 404), rv.status_code)
for z in zona:
    time.sleep(15)                                 # respeta el límite de intentos de inicio de sesión
    cz = Sesion(); lz = cz.entrar(z[0], z[2]); ok(f'login_{z[0].split("@")[0]}', '/coordinacion' in lz, lz)
    d = cz.h.get(BASE + '/coordinacion/api/solicitudes?por_pagina=50', headers={'Accept': 'application/json'})
    R['detalle'][f'zona_{z[0].split("@")[0]}'] = {'http': d.status_code, 'folios': [x['folio'] for x in d.json().get('datos', [])] if d.status_code == 200 else None,
                                                 'expediente_ajeno': cz.get(f'/coordinacion/solicitudes/{sol1}').status_code}
    # Fuera de alcance responde 404 (no revela la existencia). Sin zona_id normalizado la bandeja de zona queda vacía.
    ok(f'alcance_{z[0].split("@")[0]}', d.status_code == 200 and sol1 not in (R['detalle'][f'zona_{z[0].split("@")[0]}']['folios'] or []) and R['detalle'][f'zona_{z[0].split("@")[0]}']['expediente_ajeno'] in (403, 404))
r = s1.get('/coordinacion')
ok('solicitante_sin_acceso_coordinacion', r.status_code in (302, 403) and '/coordinacion' not in r.headers.get('location', '/x'), f'{r.status_code} {r.headers.get("location")}')
ok('solicitante_sin_documento_ajeno', s1.get(f'/coordinacion/solicitudes/3/documentos/titulo_origen_archivo').status_code in (302, 403))

R['resumen'] = f"{sum(v == 'ok' for v in R['v'].values())}/{len(R['v'])}"
json.dump(R, open(SALIDA, 'w'), indent=1, ensure_ascii=False)
print(R['resumen']); [print(k, v) for k, v in R['v'].items() if v != 'ok']
