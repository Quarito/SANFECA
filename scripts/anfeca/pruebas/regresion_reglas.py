#!/usr/bin/env python3
"""ANFECA · Regresión comparativa de reglas de negocio específicas (HTTP real, base COPIA, cuentas ficticias).

    E2E_SUFIJO=… E2E_CURP{1..4}=… E2E_CERT_A=… E2E_CERT_B=… E2E_CERT_RECIENTE=… \
      python3 -I regresion_reglas.py <url_loopback> <salida.json>

Registra el comportamiento observado (HTTP, redirección, mensajes y estado visible) para comparar dos instancias:
edición permitida antes de autorizar, teléfono obligatorio, texto del compromiso, artículo 14, certificados
múltiples y duplicados, periodicidad y refrendo no automático. No afirma la regla: la comparación lo hace.
"""
import json, os, re, sys, urllib.parse, hashlib
import requests
BASE, SALIDA = sys.argv[1].rstrip('/'), sys.argv[2]
u = urllib.parse.urlparse(BASE)
if u.hostname not in ('127.0.0.1', '::1') or u.port in (8086, 8084, 8085, 8008):
    sys.exit('DETENIDO: solo instancias de prueba en loopback.')
SUF = os.environ['E2E_SUFIJO']; TC = 'Profesoras y profesores de Tiempo Completo'
COMPROMISO = 'Para completar su solicitud, deberá entregar las fotografías indicadas en la convocatoria y el comprobante de pago original.'
R = []

def pdf(t): return (f'{t}.pdf', b'%PDF-1.4\n% ANFECA reglas ' + t.encode() + b'\n%%EOF\n', 'application/pdf')
def msgs(html):
    t = re.findall(r'<(?:div|p|span|li|small)[^>]*class="[^"]*(?:alert|error|errores|mensaje|exito|success|invalid|aviso)[^"]*"[^>]*>(.*?)</(?:div|p|span|li|small)>', html, re.S)
    return sorted({re.sub(r'\s+', ' ', re.sub(r'<[^>]+>', ' ', x)).strip() for x in t if x.strip()})[:10]

class S:
    def __init__(s): s.h = requests.Session()
    def tok(s, r):
        t = s.h.get(BASE + r).text; m = re.search(r'name="_token" value="([^"]+)"', t) or re.search(r'name="csrf-token" content="([^"]+)"', t); return m.group(1) if m else ''
    def p(s, caso, met, ruta, datos=None, arch=None, desde=None):
        d = dict(datos or {}); d['_token'] = s.tok(desde or ruta)
        if met == 'PUT': d['_method'] = 'PUT'
        r = s.h.post(BASE + ruta, data=d, files=arch, allow_redirects=False)
        loc = r.headers.get('location', '').replace(BASE, ''); seg = s.h.get(BASE + loc) if loc else None
        R.append({'caso': caso, 'http': r.status_code, 'redireccion': re.sub(r'/\d+(?=/|$)', '/{id}', loc), 'mensajes': msgs(seg.text) if seg is not None else []}); return r
    def g(s, ruta): return s.h.get(BASE + ruta)

def registrar(n):
    c = os.environ[f'E2E_CURP{n}']; s = S()
    s.p(f'registro {n}', 'POST', '/registro/datos-personales', {'curp': c, 'rfc': c[:10] + c[-3:], 'nombres': 'Reglas', 'apellido_paterno': f'Ficticia{n}',
        'genero': 'Femenino', 'calle': 'Calle Ficticia', 'numero_exterior': '10', 'codigo_postal': '01000', 'entidad_federativa': 'Ciudad de México',
        'colonia': 'Centro', 'municipio': 'Cuauhtémoc', 'correo_electronico': f'reglas{n}.{SUF}@example.test', 'telefono_celular': '5511111111',
        'password': 'ContrasenaFicticia1', 'password_confirmation': 'ContrasenaFicticia1'})
    return s

def estado(s, caso, ruta, buscar):
    t = s.g(ruta).text; R.append({'caso': caso, 'presentes': {k: (k in t) for k in buscar}})

# 1. Edición antes de autorizar: solo domicilio y teléfonos
s1 = registrar(1)
s1.p('PUT con nombre, correo y calle antes de autorizar', 'PUT', '/mis-datos', {'nombres': 'NombreCambiado', 'correo_electronico': f'otro.{SUF}@example.test',
     'calle': 'Calle Permitida', 'numero_exterior': '1', 'codigo_postal': '01000', 'entidad_federativa': 'Ciudad de México', 'colonia': 'C', 'municipio': 'M',
     'telefono_celular': '5511111111'}, desde='/mis-datos')
estado(s1, 'campos tras editar', '/mis-datos', ['NombreCambiado', f'otro.{SUF}@example.test', 'Calle Permitida', 'Reglas'])
s1.p('sin ningún teléfono', 'PUT', '/mis-datos', {'calle': 'Calle Permitida', 'numero_exterior': '1', 'codigo_postal': '01000', 'entidad_federativa': 'Ciudad de México',
     'colonia': 'C', 'municipio': 'M', 'telefono_celular': '', 'telefono_particular': ''}, desde='/mis-datos')
s1.p('autorización', 'POST', '/mis-datos/autorizar', {'confirmacion_datos': '1'}, desde='/mis-datos')
estado(s1, 'formulario tras autorizar', '/mis-datos', ['readonly', 'disabled', 'Autorizar'])
# 2. Refrendo no automático: con certificado histórico disponible, solicitar sin número
s2 = registrar(2); s2.p('autorización', 'POST', '/mis-datos/autorizar', {'confirmacion_datos': '1'}, desde='/mis-datos')
s2.p('certificación sin número teniendo certificado histórico', 'POST', '/registro/certificacion', {'certificacion': 'ANFECA', 'tipo_personal': TC, 'area_certificacion': 'Administración'})
# 3. Persona con dos certificados independientes y duplicado del mismo certificado
s3 = registrar(3); s3.p('autorización', 'POST', '/mis-datos/autorizar', {'confirmacion_datos': '1'}, desde='/mis-datos')
s3.p('refrendo certificado A', 'POST', '/registro/certificacion', {'certificacion': 'ANFECA', 'tipo_personal': TC, 'area_certificacion': 'Administración', 'numero_certificado_anterior': os.environ['E2E_CERT_A']})
s3.p('artículo 14 sin inciso', 'POST', '/registro/refrendo', {'aplica_articulo_14': '1'})
s3.p('artículo 14 con inciso', 'POST', '/registro/refrendo', {'aplica_articulo_14': '1', 'inciso_articulo_14': '14.1'})
f = s3.g('/registro/documentos-probatorios/formato-articulo-14'); R.append({'caso': 'formato artículo 14 antes de formación', 'http': f.status_code})
s3.p('cambiar al certificado B (independiente)', 'POST', '/registro/certificacion', {'certificacion': 'ANFECA', 'tipo_personal': TC, 'area_certificacion': 'Administración', 'numero_certificado_anterior': os.environ['E2E_CERT_B']}, desde='/registro/certificacion')
s4 = registrar(4); s4.p('autorización', 'POST', '/mis-datos/autorizar', {'confirmacion_datos': '1'}, desde='/mis-datos')
s4.p('certificado A de otra persona (duplicado)', 'POST', '/registro/certificacion', {'certificacion': 'ANFECA', 'tipo_personal': TC, 'area_certificacion': 'Administración', 'numero_certificado_anterior': os.environ['E2E_CERT_A']})
s4.p('certificado con menos de tres años', 'POST', '/registro/certificacion', {'certificacion': 'ANFECA', 'tipo_personal': TC, 'area_certificacion': 'Administración', 'numero_certificado_anterior': os.environ['E2E_CERT_RECIENTE']})
# 4. Texto del compromiso (vista y componente compilado)
html = s1.g('/registro/compromiso').text
js = ''.join(requests.get(BASE + '/build/' + m).text for m in set(re.findall(r'/build/(assets/[^"\']+\.js)', html)))
R.append({'caso': 'texto exacto del compromiso', 'en_html': COMPROMISO in html, 'en_js': COMPROMISO in js, 'http': s1.g('/registro/compromiso').status_code})
json.dump(R, open(SALIDA, 'w'), indent=1, ensure_ascii=False); print(f'{len(R)} casos')
