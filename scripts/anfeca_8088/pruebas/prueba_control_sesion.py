#!/usr/bin/env python3
"""
Pruebas HTTP del control de sesión (servidor). Ejecutar SOLO contra una copia aislada con duración reducida, p. ej.
SESSION_LIFETIME=1 y ANFECA_SESION_MARGEN_SEGUNDOS=20 en el .env de esa copia (nunca en 8088/8086/8008).
No crea ni modifica registros: solo inicia y cierra sesión con cuentas ficticias existentes.

  ANFECA_URL=http://127.0.0.1:8188 ANFECA_CORREO=... ANFECA_CLAVE=... ANFECA_SOLICITUD_ENVIADA=<folio> \
  python3 scripts/anfeca_8088/pruebas/prueba_control_sesion.py
"""
import os, re, sys, time
import requests

URL = os.environ.get('ANFECA_URL', 'http://127.0.0.1:8188').rstrip('/')
CORREO, CLAVE = os.environ['ANFECA_CORREO'], os.environ['ANFECA_CLAVE']
FOLIO = os.environ.get('ANFECA_SOLICITUD_ENVIADA')
JSON = {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
resultados = []


def prueba(nombre, ok, detalle=''):
    resultados.append(bool(ok))
    print(('APROBADA ' if ok else 'FALLIDA  ') + nombre + (f' — {detalle}' if detalle else ''))


def token(s, ruta='/login'):
    t = s.get(URL + ruta).text
    m = re.search(r'name="_token" value="([^"]+)"', t)
    return m.group(1) if m else None


def login():
    s = requests.Session()
    r = s.post(URL + '/login', data={'_token': token(s), 'email': CORREO, 'password': CLAVE}, allow_redirects=False)
    return s, r


def estado(s):
    r = s.get(URL + '/sesion/estado', headers=JSON)
    return r.status_code, (r.json() if r.headers.get('content-type', '').startswith('application/json') else {})


def tecnico(texto):
    return any(x in texto for x in ['Exception', 'Stack trace', 'Page Expired', 'vendor/laravel', 'Whoops'])


def principal():
    # Inicio de sesión, navegación y cierre manual (regresión)
    s, r = login()
    prueba('Inicio de sesión sin regresiones', r.status_code == 302 and '/login' not in r.headers.get('Location', ''), r.headers.get('Location'))
    etapas = ['/mis-datos', '/registro/certificacion', '/registro/completado', '/registro/compromiso', '/panel']
    codigos = {e: s.get(URL + e, allow_redirects=False).status_code for e in etapas}
    prueba('Navegación entre etapas sin cierres inesperados', all(c == 200 for c in codigos.values()), str(codigos))
    pagina = s.get(URL + '/mis-datos').text
    prueba('Las pantallas del registro incluyen el aviso (oculto) y la configuración', 'id="anfeca-sesion-aviso"' in pagina
           and 'anfeca-sesion-config' in pagina and re.search(r'"restante":\d+', pagina) is not None)
    if FOLIO:
        completado = s.get(URL + '/registro/completado').text
        pdf = s.get(URL + f'/registro/acuse/{FOLIO}')
        prueba('Acuse PDF y su temporizador propio se conservan', pdf.status_code == 200 and pdf.content.startswith(b'%PDF')
               and 'id="aviso-cierre-acuse"' in completado and 'segundos-cierre' in completado and 'anfeca-sesion-aviso' in completado)
    t = token(s, '/mis-datos')
    r = s.post(URL + '/logout', data={'_token': t}, allow_redirects=False)
    prueba('Cierre manual de sesión como antes', r.status_code == 302 and r.headers.get('Location', '').endswith('/login')
           and s.get(URL + '/mis-datos', allow_redirects=False).headers.get('Location', '').endswith('/login'))

    # 419: token CSRF inválido (HTML y JSON), sin desactivar la protección
    s, _ = login()
    r = s.post(URL + '/registro/certificacion', data={'_token': 'invalido', 'certificacion': 'ANFECA'}, allow_redirects=False)
    prueba('HTTP 419 con sesión activa: página amigable, código 419 conservado, sin detalles técnicos',
           r.status_code == 419 and 'La página expiró' in r.text and not tecnico(r.text))
    r = s.post(URL + '/registro/certificacion/consultar', json={'numero_certificado': '1'}, headers={**JSON, 'X-CSRF-TOKEN': 'invalido'})
    prueba('Solicitud AJAX con HTTP 419: JSON controlado (mensaje + marca de sesión)', r.status_code == 419 and r.json().get('mensaje')
           and r.headers.get('X-Anfeca-Sesion') == 'expirada' and not tecnico(r.text), r.text[:90])
    r = requests.Session().post(URL + '/login', data={'_token': 'invalido', 'email': CORREO, 'password': 'x'}, allow_redirects=False)
    prueba('HTTP 419 de visitante (login): ofrece iniciar sesión', r.status_code == 419 and 'Su sesión terminó' in r.text and 'Iniciar sesión' in r.text)

    # Continuar sesión: renueva solo con POST + CSRF y solo si está vigente
    sin_csrf = s.post(URL + '/sesion/continuar', headers=JSON)
    prueba('Continuar sesión exige CSRF (419 sin token)', sin_csrf.status_code == 419)
    limite = estado(s)[1].get('restante', 0)
    time.sleep(limite - 20)
    # Token vigente de la página actual (sin generar actividad adicional antes de continuar).
    csrf = s.cookies.get('XSRF-TOKEN')
    r = s.post(URL + '/sesion/continuar', headers={**JSON, 'X-XSRF-TOKEN': requests.utils.unquote(csrf)})
    datos = r.json() if r.ok else {}
    prueba('Continuar sesión vigente: renueva al tiempo completo', r.status_code == 200 and datos.get('autenticado') is True
           and datos.get('restante', 0) >= limite - 2, str(datos))
    time.sleep(limite - 15)
    prueba('Después de continuar, la sesión sigue activa más allá del vencimiento original',
           s.get(URL + '/mis-datos', allow_redirects=False).status_code == 200)

    # Las consultas de estado NO renuevan; la sesión vencida no se reactiva
    s2, _ = login()
    lecturas = []
    for _ in range(3):
        time.sleep(15)
        lecturas.append(estado(s2)[1].get('restante'))
    prueba('La consulta de estado no prolonga la sesión (restante decreciente)', lecturas == sorted(lecturas, reverse=True)
           and lecturas[-1] <= limite - 40, str(lecturas))
    time.sleep(max(1, lecturas[-1] + 3))
    csrf = requests.utils.unquote(s2.cookies.get('XSRF-TOKEN'))
    r = s2.post(URL + '/sesion/continuar', headers={**JSON, 'X-XSRF-TOKEN': csrf})
    prueba('Sesión vencida: «continuar» no la reactiva (401, marca finalizada)', r.status_code == 401 and r.json().get('vigente') is False
           and r.headers.get('X-Anfeca-Sesion') == 'finalizada')
    r = s2.get(URL + '/mis-datos', allow_redirects=False)
    prueba('Sesión vencida: no hay acceso autenticado (redirige a iniciar sesión)', r.status_code == 302 and r.headers.get('Location', '').endswith('/login'))

    # Formulario enviado con sesión vencida (archivo de sesión aún vigente): redirección amigable, no 419
    s3, _ = login()
    pagina = s3.get(URL + '/registro/certificacion').text
    t3 = re.search(r'name="_token" value="([^"]+)"', pagina).group(1)
    for _ in range(4):
        time.sleep(14)
        estado(s3)          # mantiene vivo el archivo, pero no la actividad
    time.sleep(8)           # vence la inactividad; el envío es la primera petición posterior
    r = s3.post(URL + '/registro/certificacion', data={'_token': t3, 'certificacion': 'ANFECA'}, allow_redirects=False)
    final = s3.get(URL + '/sesion-finalizada')
    prueba('Envío con sesión vencida: redirección a «sesión finalizada» con mensaje amigable, sin 419',
           r.status_code == 302 and r.headers.get('Location', '').endswith('/sesion-finalizada') and 'Su sesión finalizó' in final.text
           and not tecnico(final.text))
    prueba('Sin bucles: «sesión finalizada» responde 200 y ofrece iniciar sesión', final.status_code == 200 and 'Iniciar sesión' in final.text
           and len(final.history) == 0)

    print(f'\nResultado: {sum(resultados)}/{len(resultados)} verificaciones aprobadas.')
    return 0 if all(resultados) else 1


if __name__ == '__main__':
    sys.exit(principal())
