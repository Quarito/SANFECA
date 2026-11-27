#!/usr/bin/env python3
"""ANFECA · Regresión funcional COMPARATIVA (HTTP real) contra una instancia de prueba con base COPIA.

    E2E_SUFIJO=<único> E2E_CERT_REFRENDO=<número ficticio> python3 -I regresion_funcional.py <url_loopback> <salida.json>

Registra cada paso (método, ruta, HTTP, redirección y mensajes visibles de la página siguiente) para comparar
dos instancias paso a paso. Solo loopback y nunca puertos en servicio. Cuentas y expedientes FICTICIOS.
Credenciales de coordinación: ~/ANFECA_CREDENCIALES/coordinacion_ensayo_8086.tsv (no se imprimen).
"""
import json, os, re, struct, sys, time, urllib.parse, zlib, hashlib
import requests

BASE, SALIDA = sys.argv[1].rstrip('/'), sys.argv[2]
u = urllib.parse.urlparse(BASE)
if u.hostname not in ('127.0.0.1', '::1') or u.port in (8086, 8084, 8085, 8008):
    sys.exit('DETENIDO: solo instancias de prueba en loopback.')
SUF = os.environ['E2E_SUFIJO']; CERT = os.environ['E2E_CERT_REFRENDO']
TC = 'Profesoras y profesores de Tiempo Completo'
PASOS = []

def pdf(t): return (f'{t}.pdf', b'%PDF-1.4\n% ANFECA regresion ' + t.encode() + b'\n%%EOF\n', 'application/pdf')
def png(t):
    def ch(k, d): return struct.pack('>I', len(d)) + k + d + struct.pack('>I', zlib.crc32(k + d) & 0xffffffff)
    return (f'{t}.png', b'\x89PNG\r\n\x1a\n' + ch(b'IHDR', struct.pack('>IIBBBBB', 1, 1, 8, 2, 0, 0, 0)) + ch(b'IDAT', zlib.compress(b'\x00\xff\x00\x00')) + ch(b'IEND', b''), 'image/png')
def grande(t, mb): return (f'{t}.pdf', b'%PDF-1.4\n' + b'0' * (mb * 1024 * 1024), 'application/pdf')

def mensajes(html):
    t = re.findall(r'<(?:div|p|span|li|small)[^>]*class="[^"]*(?:alert|error|errores|mensaje|exito|success|invalid|aviso)[^"]*"[^>]*>(.*?)</(?:div|p|span|li|small)>', html, re.S)
    limpio = [re.sub(r'\s+', ' ', re.sub(r'<[^>]+>', ' ', x)).strip() for x in t]
    return sorted({re.sub(r'\d{1,2}/\d{1,2}/\d{4}|\d{4}-\d{2}-\d{2}|\d{1,2}:\d{2}', '#', x) for x in limpio if x})[:12]

class S:
    def __init__(s, nombre): s.h = requests.Session(); s.n = nombre
    def tok(s, ruta):
        r = s.h.get(BASE + ruta); m = re.search(r'name="_token" value="([^"]+)"', r.text) or re.search(r'name="csrf-token" content="([^"]+)"', r.text)
        return m.group(1) if m else ''
    def paso(s, caso, metodo, ruta, datos=None, archivos=None, desde=None, json_=False):
        if metodo == 'GET':
            r = s.h.get(BASE + ruta, allow_redirects=False, headers={'Accept': 'application/json'} if json_ else {})
        else:
            d = dict(datos or {}); d['_token'] = s.tok(desde or ruta)
            if metodo in ('PUT', 'DELETE'): d['_method'] = metodo
            r = s.h.post(BASE + ruta, data=d, files=archivos, allow_redirects=False)
        loc = r.headers.get('location', '').replace(BASE, '')
        seg = s.h.get(BASE + loc) if r.status_code in (301, 302) and loc else None
        cuerpo = seg.text if seg is not None else (r.text if 'html' in r.headers.get('content-type', '') else '')
        e = {'caso': caso, 'actor': s.n, 'metodo': metodo, 'ruta': re.sub(r'/\d+(?=/|$)', '/{id}', ruta), 'http': r.status_code,
             'redireccion': re.sub(r'/\d+(?=/|$)', '/{id}', loc), 'tipo': r.headers.get('content-type', '').split(';')[0],
             'mensajes': mensajes(cuerpo) if cuerpo else [], 'pdf': r.content[:4] == b'%PDF' if r.status_code == 200 else None,
             'descarga': 'attachment' in r.headers.get('content-disposition', '')}
        if json_ and r.status_code == 200:
            try: e['json_claves'] = sorted(r.json().keys())
            except Exception: pass
        PASOS.append(e); return r, seg

def entrar(s, correo, clave): return s.paso('inicio de sesión', 'POST', '/login', {'email': correo, 'password': clave})

def datos_personales(n):
    c = os.environ[f'E2E_CURP{n}']
    return {'curp': c, 'rfc': c[:10] + c[-3:], 'nombres': 'Regresion', 'apellido_paterno': f'Ficticia{n}', 'genero': 'Femenino', 'calle': 'Calle Ficticia',
            'numero_exterior': '10', 'codigo_postal': '01000', 'entidad_federativa': 'Ciudad de México', 'colonia': 'Centro', 'municipio': 'Cuauhtémoc',
            'correo_electronico': f'reg{n}.{SUF}@example.test', 'telefono_celular': '5511111111', 'telefono_particular': '5522222222',
            'password': 'ContrasenaFicticia1', 'password_confirmation': 'ContrasenaFicticia1'}

# ============================ Invitado
g = S('invitado')
g.paso('login visible', 'GET', '/login'); g.paso('ruta protegida sin sesión', 'GET', '/registro/formacion-academica')
g.paso('login vacío', 'POST', '/login', {'email': '', 'password': ''}, desde='/login')
g.paso('login incorrecto', 'POST', '/login', {'email': f'noexiste.{SUF}@example.test', 'password': 'Incorrecta123'}, desde='/login')
g.paso('recuperación visible', 'GET', '/recuperar')
g.paso('recuperación solicitada', 'POST', '/recuperar', {'email': f'noexiste.{SUF}@example.test'}, desde='/recuperar')
g.paso('registro incompleto', 'POST', '/registro/datos-personales', {'curp': 'X'}, desde='/registro/datos-personales')

# ============================ Solicitante 1: primera vez
s1 = S('solicitante1')
s1.paso('registro desde cero', 'POST', '/registro/datos-personales', datos_personales(1))
S('dup').paso('CURP duplicada', 'POST', '/registro/datos-personales', {**datos_personales(1), 'correo_electronico': f'dup.{SUF}@example.test'})
s1.paso('certificación antes de autorizar datos', 'GET', '/registro/certificacion')
s1.paso('edición de domicilio y teléfonos antes de autorizar', 'PUT', '/mis-datos', {'calle': 'Calle Editada', 'numero_exterior': '22', 'codigo_postal': '02000',
        'entidad_federativa': 'Ciudad de México', 'colonia': 'Nueva', 'municipio': 'Azcapotzalco', 'telefono_celular': '5533333333'}, desde='/mis-datos')
r, seg = s1.paso('mis datos tras editar', 'GET', '/mis-datos'); PASOS[-1]['edicion_visible'] = 'Calle Editada' in s1.h.get(BASE + '/mis-datos').text
s1.paso('autorización sin confirmar', 'POST', '/mis-datos/autorizar', {}, desde='/mis-datos')
s1.paso('autorización de datos', 'POST', '/mis-datos/autorizar', {'confirmacion_datos': '1'}, desde='/mis-datos')
s1.paso('edición tras autorizar', 'PUT', '/mis-datos', {'calle': 'Calle Bloqueada'}, desde='/mis-datos'); PASOS[-1]['bloqueada'] = 'Calle Bloqueada' not in s1.h.get(BASE + '/mis-datos').text
s1.paso('tipo de personal inválido', 'POST', '/registro/certificacion', {'certificacion': 'ANFECA', 'tipo_personal': 'Inexistente', 'area_certificacion': 'Contaduría Pública'})
s1.paso('certificado de otra persona (incompatible)', 'POST', '/registro/certificacion', {'certificacion': 'ANFECA', 'tipo_personal': TC, 'area_certificacion': 'Administración', 'numero_certificado_anterior': CERT})
s1.paso('certificación por primera vez', 'POST', '/registro/certificacion', {'certificacion': 'ANFECA', 'tipo_personal': TC, 'area_certificacion': 'Contaduría Pública'})
s1.paso('documentos antes de formación', 'GET', '/registro/documentos-probatorios')
lic = s1.h.get(BASE + '/registro/catalogos/licenciaturas?' + urllib.parse.urlencode({'q': 'contaduria publica', 'area': 'Contaduría'}), headers={'Accept': 'application/json'}).json()
PASOS.append({'caso': 'catálogo de licenciaturas con filtro por área', 'actor': 'solicitante1', 'total': lic.get('total'), 'titulos': [t['titulo'] for t in lic['titulos'][:5]]})
otra = s1.h.get(BASE + '/registro/catalogos/licenciaturas?' + urllib.parse.urlencode({'q': 'derecho', 'area': 'Otro'}), headers={'Accept': 'application/json'}).json()
PASOS.append({'caso': 'catálogo de licenciaturas, área Otro', 'actor': 'solicitante1', 'total': otra.get('total')})
pos = s1.h.get(BASE + '/registro/catalogos/posgrados?' + urllib.parse.urlencode({'nivel': 'Maestría', 'area': 'Contaduría', 'q': 'fiscal'}), headers={'Accept': 'application/json'}).json()
PASOS.append({'caso': 'catálogo de posgrados', 'actor': 'solicitante1', 'total': len(pos.get('titulos', [])), 'titulos': [t['titulo'] for t in pos.get('titulos', [])[:5]]})
base_f = {'nivel_origen': 'Licenciatura', 'modo_titulo_origen': 'catalogo', 'clave_licenciatura_catalogo': lic['titulos'][0]['clave'], 'institucion_afiliada_id': '3', 'cuenta_con_posgrado': '0'}
s1.paso('formación sin documentos', 'POST', '/registro/formacion-academica', base_f)
s1.paso('formación con área incompatible (D-LIC-1)', 'POST', '/registro/formacion-academica', {**base_f, 'clave_licenciatura_catalogo': otra['titulos'][0]['clave']}, {'titulo_origen_archivo': pdf('t'), 'cedula_origen_archivo': pdf('c')})
s1.paso('formación con archivo de 8 MB (límite de la aplicación 5 MB)', 'POST', '/registro/formacion-academica', base_f, {'titulo_origen_archivo': grande('g', 8), 'cedula_origen_archivo': pdf('c')})
s1.paso('formación guardada', 'POST', '/registro/formacion-academica', base_f, {'titulo_origen_archivo': pdf('t1'), 'cedula_origen_archivo': pdf('c1')})
s1.paso('sustitución del título antes de autorizar', 'POST', '/registro/formacion-academica', base_f, {'titulo_origen_archivo': pdf('t2')})
s1.paso('autorizar formación sin confirmar', 'POST', '/registro/formacion-academica/autorizar', {}, desde='/registro/formacion-academica')
s1.paso('autorizar formación', 'POST', '/registro/formacion-academica/autorizar', {'confirmacion_formacion': '1'}, desde='/registro/formacion-academica')
s1.paso('formación tras autorizar (bloqueo)', 'POST', '/registro/formacion-academica', {**base_f, 'cuenta_con_posgrado': '1'}, {'titulo_origen_archivo': pdf('t3')})
s1.paso('documentos sin archivos', 'POST', '/registro/documentos-probatorios', {'link': 'https://example.test/e'})
s1.paso('documentos con tipo no permitido', 'POST', '/registro/documentos-probatorios', {'link': 'https://example.test/e'}, {'constancia_antiguedad_archivo': ('x.txt', b'texto', 'text/plain'), 'comprobante_pago_archivo': pdf('p')})
s1.paso('documentos guardados', 'POST', '/registro/documentos-probatorios', {'link': 'https://example.test/evidencias'}, {'constancia_antiguedad_archivo': png('cons'), 'comprobante_pago_archivo': pdf('pago')})
s1.paso('sustitución de documento probatorio', 'POST', '/registro/documentos-probatorios', {'link': 'https://example.test/evidencias2'}, {'constancia_antiguedad_archivo': pdf('cons2')})
s1.paso('ver documento propio', 'GET', '/registro/documentos-probatorios/ver/constancia_antiguedad_archivo')
s1.paso('finalización antes del compromiso', 'GET', '/registro/completado')
s1.paso('compromiso sin aceptar', 'POST', '/registro/compromiso/guardar', {}, desde='/registro/compromiso')
s1.paso('compromiso aceptado', 'POST', '/registro/compromiso/guardar', {'compromiso_aceptado': '1'}, desde='/registro/compromiso')
s1.paso('pantalla de finalización', 'GET', '/registro/completado')
s1.paso('envío de solicitud', 'POST', '/registro/completado/confirmar', {}, desde='/registro/completado')
m = re.search(r'/registro/acuse/(\d+)', s1.h.get(BASE + '/registro/completado').text); folio1 = int(m.group(1)) if m else None
s1.paso('acuse PDF', 'GET', f'/registro/acuse/{folio1}')
s1.paso('consulta de solicitud enviada', 'GET', '/registro/completado')
s1.paso('formación tras envío', 'GET', '/registro/formacion-academica')
s1.paso('acuse de otra solicitud', 'GET', '/registro/acuse/3')
s1.paso('acceso a Coordinación', 'GET', '/coordinacion')
s1.paso('cierre de sesión', 'POST', '/logout', {}, desde='/registro/completado')
s1.paso('ruta tras cerrar sesión', 'GET', '/registro/completado')

# ============================ Solicitante 2: refrendo con posgrado
s2 = S('solicitante2'); s2.paso('registro desde cero', 'POST', '/registro/datos-personales', datos_personales(2))
s2.paso('autorización de datos', 'POST', '/mis-datos/autorizar', {'confirmacion_datos': '1'}, desde='/mis-datos')
s2.paso('refrendo con número inexistente', 'POST', '/registro/certificacion', {'certificacion': 'ANFECA', 'tipo_personal': TC, 'area_certificacion': 'Administración', 'numero_certificado_anterior': '9999998'})
s2.paso('refrendo con certificado histórico', 'POST', '/registro/certificacion', {'certificacion': 'ANFECA', 'tipo_personal': TC, 'area_certificacion': 'Administración', 'numero_certificado_anterior': CERT})
s2.paso('datos de refrendo sin artículo', 'POST', '/registro/refrendo', {})
s2.paso('datos de refrendo', 'POST', '/registro/refrendo', {'aplica_articulo_14': '0'})
lic2 = s2.h.get(BASE + '/registro/catalogos/licenciaturas?' + urllib.parse.urlencode({'q': 'licenciatura en administracion', 'area': 'Administración'}), headers={'Accept': 'application/json'}).json()
pos2 = s2.h.get(BASE + '/registro/catalogos/posgrados?' + urllib.parse.urlencode({'nivel': 'Maestría', 'area': 'Administración', 'q': 'administracion'}), headers={'Accept': 'application/json'}).json()
f2 = {'nivel_origen': 'Licenciatura', 'modo_titulo_origen': 'catalogo', 'clave_licenciatura_catalogo': lic2['titulos'][0]['clave'], 'institucion_afiliada_id': '3',
      'cuenta_con_posgrado': '1', 'nivel_ultimo_grado': 'Maestría', 'titulo_ultimo_grado': 'Administración', 'modo_titulo_ultimo_grado': 'catalogo', 'clave_posgrado_catalogo': pos2['titulos'][0]['clave']}
s2.paso('posgrado sin documentos de posgrado', 'POST', '/registro/formacion-academica', f2, {'titulo_origen_archivo': pdf('t'), 'cedula_origen_archivo': pdf('c')})
s2.paso('formación con posgrado', 'POST', '/registro/formacion-academica', f2, {'titulo_origen_archivo': pdf('t'), 'cedula_origen_archivo': pdf('c'), 'titulo_ultimo_grado_archivo': pdf('tu'), 'cedula_ultimo_grado_archivo': pdf('cu')})
s2.paso('autorizar formación', 'POST', '/registro/formacion-academica/autorizar', {'confirmacion_formacion': '1'}, desde='/registro/formacion-academica')
s2.paso('documentos guardados', 'POST', '/registro/documentos-probatorios', {'link': 'https://example.test/evidencias'}, {'constancia_antiguedad_archivo': pdf('c'), 'comprobante_pago_archivo': png('p')})
s2.paso('compromiso aceptado', 'POST', '/registro/compromiso/guardar', {'compromiso_aceptado': '1'}, desde='/registro/compromiso')
s2.paso('envío de solicitud', 'POST', '/registro/completado/confirmar', {}, desde='/registro/completado')
m = re.search(r'/registro/acuse/(\d+)', s2.h.get(BASE + '/registro/completado').text); folio2 = int(m.group(1)) if m else None
s2.paso('acuse PDF', 'GET', f'/registro/acuse/{folio2}')

# ============================ Coordinación
cred = [l.rstrip('\n').split('\t') for l in open(os.path.expanduser('~/ANFECA_CREDENCIALES/coordinacion_ensayo_8086.tsv')) if '\t' in l]
nac = next(x for x in cred if x[1] == 'coordinador_nacional')
c = S('coordinación nacional'); entrar(c, nac[0], nac[2])
c.paso('dashboard', 'GET', '/coordinacion'); c.paso('indicadores', 'GET', '/coordinacion/api/indicadores', json_=True)
ind = c.h.get(BASE + '/coordinacion/api/indicadores', headers={'Accept': 'application/json'}).json(); PASOS.append({'caso': 'valores de indicadores', 'actor': 'coordinación nacional', 'valores': ind})
c.paso('bandeja', 'GET', '/coordinacion/solicitudes')
api = c.h.get(BASE + '/coordinacion/api/solicitudes?por_pagina=50', headers={'Accept': 'application/json'}).json()
PASOS.append({'caso': 'numeración y orden de la bandeja', 'actor': 'coordinación nacional', 'claves': sorted(api['datos'][0].keys()) if api['datos'] else [],
              'filas': [(x.get('folio'), x.get('estado'), x.get('tipo_tramite'), x.get('area_certificacion')) for x in api['datos'] if x.get('folio') not in (folio1, folio2)], 'meta': {k: v for k, v in api['meta'].items() if k != 'total'}})
for q in ('zona=z3', 'zona=sin_zona', 'estado=enviado', 'buscar=Regresion', 'zona=manipulada'):
    c.paso(f'filtro {q.split("=")[0]}', 'GET', f'/coordinacion/api/solicitudes?{q}', json_=True)
for f in (3, 4, 5, 10, folio1, folio2): c.paso('expediente', 'GET', f'/coordinacion/solicitudes/{f}')
for f, campo in ((3, 'titulo_origen_archivo'), (folio1, 'constancia_antiguedad_archivo'), (folio2, 'comprobante_pago_archivo'), (folio1, 'titulo_origen_archivo')):
    r, _ = c.paso('visor de documento', 'GET', f'/coordinacion/solicitudes/{f}/documentos/{campo}')
    PASOS[-1]['sha256'] = hashlib.sha256(r.content).hexdigest() if f == 3 else None
    c.paso('descarga autorizada', 'GET', f'/coordinacion/solicitudes/{f}/documentos/{campo}?descargar=1')
c.paso('documento inexistente', 'GET', f'/coordinacion/solicitudes/{folio1}/documentos/campo_inventado')
c.paso('seguimiento', 'GET', '/coordinacion/seguimiento'); c.paso('seguimiento API', 'GET', '/coordinacion/api/seguimiento', json_=True)
c.paso('revisión documental (desactivada)', 'GET', f'/coordinacion/solicitudes/{folio1}/revision')
c.paso('historial de revisiones (desactivado)', 'GET', f'/coordinacion/solicitudes/{folio1}/documentos/titulo_origen_archivo/revisiones', json_=True)
c.paso('cierre de sesión', 'POST', '/logout', {}, desde='/coordinacion')
c.paso('ruta tras cerrar sesión', 'GET', '/coordinacion')
time.sleep(12)
z = next(x for x in cred if x[0].startswith('coordinador.zona3')); cz = S('coordinación zona 3'); entrar(cz, z[0], z[2])
cz.paso('bandeja de zona', 'GET', '/coordinacion/api/solicitudes', json_=True)
cz.paso('expediente fuera de zona', 'GET', f'/coordinacion/solicitudes/{folio1}')
cz.paso('documento fuera de zona', 'GET', '/coordinacion/solicitudes/3/documentos/titulo_origen_archivo')
cz.paso('filtro a otra zona', 'GET', '/coordinacion/api/solicitudes?zona=z6', json_=True)

json.dump({'pasos': PASOS, 'folios': [folio1, folio2]}, open(SALIDA, 'w'), indent=1, ensure_ascii=False)
print(f'{len(PASOS)} pasos registrados; folios {folio1}, {folio2}')
