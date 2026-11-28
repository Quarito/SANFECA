#!/usr/bin/env python3
"""
Pruebas HTTP del control de sesión (servidor). SOLO contra una copia aislada con duración reducida:
SESSION_LIFETIME=1 en el .env de esa copia (nunca en 8088/8086/8008). Solo usa cuentas ficticias existentes.

  ANFECA_URL=http://127.0.0.1:8189 ANFECA_CORREO=... ANFECA_CLAVE=... ANFECA_SOLICITUD_ENVIADA=<folio> \
  ANFECA_SESIONES=<storage/framework/sessions de la copia> python3 prueba_control_sesion.py
Con APP_DEBUG=true Laravel añade la traza al JSON original; por eso solo se compara su campo «message».
"""
import glob, os, re, sys, time
import requests

URL = os.environ.get('ANFECA_URL', 'http://127.0.0.1:8189').rstrip('/')
CORREO, CLAVE = os.environ['ANFECA_CORREO'], os.environ['ANFECA_CLAVE']
FOLIO = os.environ.get('ANFECA_SOLICITUD_ENVIADA')
SESIONES = os.environ.get('ANFECA_SESIONES')
JSON = {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
resultados = []


def prueba(nombre, ok, detalle=''):
    resultados.append(bool(ok))
    print(('APROBADA ' if ok else 'FALLIDA  ') + nombre + (f' — {detalle}' if detalle else ''))


def token(s, ruta='/login'):
    m = re.search(r'name="_token" value="([^"]+)"', s.get(URL + ruta).text)
    return m.group(1) if m else None


def login():
    # Respeta el limitador existente (5 intentos por minuto por IP): ante 429 espera y reintenta; nunca se relaja.
    for _ in range(3):
        s = requests.Session()
        r = s.post(URL + '/login', data={'_token': token(s), 'email': CORREO, 'password': CLAVE}, allow_redirects=False)
        if r.status_code != 429:
            if r.status_code != 302 or r.headers.get('Location', '').endswith('/login'):
                raise SystemExit(f'No fue posible iniciar sesión con la cuenta ficticia (HTTP {r.status_code}).')
            return s, r
        time.sleep(61)
    raise SystemExit('Inicio de sesión limitado (429) de forma persistente.')


def estado(s):
    r = s.get(URL + '/sesion/estado', headers=JSON)
    return r.status_code, (r.json() if r.headers.get('content-type', '').startswith('application/json') else {}), r


def tecnico(texto):
    return any(x in texto for x in ['Exception', 'Stack trace', 'Page Expired', 'vendor/laravel', 'Whoops'])


def xsrf(s):
    return {'X-XSRF-TOKEN': requests.utils.unquote(s.cookies.get('XSRF-TOKEN'))}


def principal():
    # ---- Regresión: inicio de sesión, navegación, acuse y cierre manual
    s, r = login()
    prueba('Inicio de sesión sin regresiones', r.status_code == 302 and '/login' not in r.headers.get('Location', ''), r.headers.get('Location'))
    etapas = ['/mis-datos', '/registro/certificacion', '/registro/completado', '/registro/compromiso', '/panel']
    codigos = {e: s.get(URL + e, allow_redirects=False).status_code for e in etapas}
    prueba('Navegación entre etapas sin cierres inesperados', all(c == 200 for c in codigos.values()), str(codigos))
    pagina = s.get(URL + '/mis-datos').text
    prueba('Pantallas autenticadas incluyen el aviso oculto (sin formulario de cierre anticipado)',
           'id="anfeca-sesion-aviso"' in pagina and '"autenticado":true' in pagina and 'sesion/cerrar' not in pagina)
    if FOLIO:
        completado = s.get(URL + '/registro/completado').text
        pdf = s.get(URL + f'/registro/acuse/{FOLIO}')
        prueba('Acuse PDF y su temporizador propio se conservan', pdf.status_code == 200 and pdf.content.startswith(b'%PDF')
               and 'id="aviso-cierre-acuse"' in completado and 'segundos-cierre' in completado)
    r = s.post(URL + '/logout', data={'_token': token(s, '/mis-datos')}, allow_redirects=False)
    prueba('Cierre manual de sesión como antes', r.status_code == 302 and r.headers.get('Location', '').endswith('/login')
           and s.get(URL + '/mis-datos', allow_redirects=False).headers.get('Location', '').endswith('/login'))

    # ---- Visitantes (registro inicial)
    v = requests.Session()
    reg = v.get(URL + '/registro/datos-personales').text
    c, d, _ = estado(v)
    prueba('Visitante en el registro inicial: aviso disponible y estado de visitante', 'id="anfeca-sesion-aviso"' in reg
           and '"autenticado":false' in reg and c == 200 and d.get('autenticado') is False)

    # ---- 419 y 401: cuerpo y código originales; solo una cabecera cuando la causa es verificable
    s, _ = login()
    r = s.post(URL + '/registro/certificacion', data={'_token': 'invalido', 'certificacion': 'ANFECA'}, allow_redirects=False)
    prueba('419 con sesión vigente (HTML): página amigable «La página expiró», código 419, sin detalles técnicos',
           r.status_code == 419 and 'La página expiró' in r.text and not tecnico(r.text))
    r = s.post(URL + '/registro/certificacion/consultar', json={'numero_certificado': '1'}, headers={**JSON, 'X-CSRF-TOKEN': 'invalido'})
    prueba('419 con sesión vigente (AJAX): cuerpo original de Laravel intacto y marca «token» (no «sesión finalizada»)',
           r.status_code == 419 and r.json().get('message') == 'CSRF token mismatch.' and 'mensaje' not in r.json() and r.headers.get('X-Anfeca-Sesion') == 'token', r.text[:80])
    g = requests.Session()
    r = g.post(URL + '/login', data={'_token': 'invalido', 'email': CORREO, 'password': 'x'}, allow_redirects=False)
    prueba('419 de visitante (HTML): ofrece iniciar sesión', r.status_code == 419 and 'Su sesión terminó' in r.text and 'Iniciar sesión' in r.text)
    r = g.post(URL + '/registro/certificacion/consultar', json={}, headers={**JSON, 'X-CSRF-TOKEN': 'invalido'})
    prueba('419 sin sesión autenticada (AJAX): marca «expirada», cuerpo original', r.status_code == 419
           and r.headers.get('X-Anfeca-Sesion') == 'expirada' and r.json().get('message') == 'CSRF token mismatch.' and 'mensaje' not in r.json())
    r = requests.get(URL + '/coordinacion/api/solicitudes', headers=JSON)
    prueba('401 sin sesión previa: respuesta original de Laravel y SIN marca de sesión finalizada', r.status_code == 401
           and r.json().get('message') == 'Unauthenticated.' and 'mensaje' not in r.json() and 'X-Anfeca-Sesion' not in r.headers)
    if SESIONES:
        s4, _ = login()
        s4.get(URL + '/mis-datos')
        for archivo in glob.glob(os.path.join(SESIONES, '*')):   # simula la pérdida de la sesión en el servidor (copia aislada)
            if os.path.basename(archivo) != '.gitignore':
                os.remove(archivo)
        r = s4.get(URL + '/registro/catalogos/licenciaturas?q=adm', headers=JSON)
        prueba('401 de una sesión que existía y ya no está en el servidor: marca «finalizada», cuerpo original', r.status_code == 401
               and r.headers.get('X-Anfeca-Sesion') == 'finalizada' and r.json().get('message') == 'Unauthenticated.' and 'mensaje' not in r.json())

    # ---- Continuar sesión: POST + CSRF y solo si sigue vigente
    s, _ = login()
    prueba('Continuar sesión exige CSRF (419 sin token)', s.post(URL + '/sesion/continuar', headers=JSON).status_code == 419)
    limite = estado(s)[1].get('restante', 0)
    time.sleep(limite - 20)
    r = s.post(URL + '/sesion/continuar', headers={**JSON, **xsrf(s)})
    datos = r.json() if r.ok else {}
    prueba('Continuar sesión vigente: renueva al tiempo completo', r.status_code == 200 and datos.get('autenticado') is True
           and datos.get('restante', 0) >= limite - 2, str(datos))
    time.sleep(limite - 15)
    prueba('Después de continuar sigue activa más allá del vencimiento original', s.get(URL + '/mis-datos', allow_redirects=False).status_code == 200)

    # ---- Momento exacto del cierre: nunca antes del tiempo; la consulta de estado no renueva
    s2, _ = login()
    t0 = time.time()
    lecturas = []
    for _ in range(3):
        time.sleep(15)
        lecturas.append(estado(s2)[1].get('restante'))
    prueba('La consulta de estado no prolonga la sesión (restante decreciente)', lecturas == sorted(lecturas, reverse=True)
           and lecturas[-1] <= limite - 40, str(lecturas))
    time.sleep(max(0, t0 + limite - 3 - time.time()))
    c, d, _ = estado(s2)
    prueba('3 s antes del límite la sesión sigue vigente (sin cierre anticipado)', c == 200 and d.get('vigente') and d.get('autenticado'),
           f't={time.time() - t0:.1f}s {d}')
    time.sleep(max(0, t0 + limite + 2 - time.time()))
    r = s2.post(URL + '/sesion/continuar', headers={**JSON, **xsrf(s2)})
    prueba('Al cumplirse el límite la sesión termina y «continuar» no la reactiva (401 «finalizada»)', r.status_code == 401
           and r.json().get('vigente') is False and r.headers.get('X-Anfeca-Sesion') == 'finalizada', f't={time.time() - t0:.1f}s')
    r = s2.get(URL + '/mis-datos', allow_redirects=False)
    prueba('Sesión vencida: sin acceso autenticado (redirige a iniciar sesión)', r.status_code == 302 and r.headers.get('Location', '').endswith('/login'))

    # ---- Envío con sesión vencida (archivo aún existente): redirección amigable, sin 419
    s3, _ = login()
    t3 = re.search(r'name="_token" value="([^"]+)"', s3.get(URL + '/registro/certificacion').text).group(1)
    for _ in range(4):
        time.sleep(14)
        estado(s3)
    time.sleep(8)
    r = s3.post(URL + '/registro/certificacion', data={'_token': t3, 'certificacion': 'ANFECA'}, allow_redirects=False)
    final = s3.get(URL + '/sesion-finalizada')
    prueba('Envío con sesión vencida: redirección a «sesión finalizada» con mensaje amigable, sin 419',
           r.status_code == 302 and r.headers.get('Location', '').endswith('/sesion-finalizada') and 'Su sesión finalizó' in final.text
           and not tecnico(final.text))
    prueba('Sin bucles: «sesión finalizada» responde 200 y ofrece iniciar sesión', final.status_code == 200 and 'Iniciar sesión' in final.text
           and len(final.history) == 0)

    # ---- Visitante: formulario de registro vencido
    v = requests.Session()
    tv = re.search(r'name="_token" value="([^"]+)"', v.get(URL + '/registro/datos-personales').text).group(1)
    for _ in range(4):
        time.sleep(14)
        estado(v)
    time.sleep(8)
    r = v.post(URL + '/registro/datos-personales', data={'_token': tv, 'nombres': 'Ficticio'}, allow_redirects=False)
    f = v.get(URL + '/sesion-finalizada')
    prueba('Visitante con formulario vencido: mensaje «El tiempo del formulario terminó», sin crear cuenta',
           r.status_code == 302 and r.headers.get('Location', '').endswith('/sesion-finalizada') and 'El tiempo del formulario terminó' in f.text)

    print(f'\nResultado: {sum(resultados)}/{len(resultados)} verificaciones aprobadas.')
    return 0 if all(resultados) else 1


if __name__ == '__main__':
    sys.exit(principal())
