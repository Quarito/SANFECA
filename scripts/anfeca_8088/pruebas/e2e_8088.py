#!/usr/bin/env python3
"""
Pruebas de aceptación de extremo a extremo por HTTP del ambiente 8088 (36 tablas oficiales).

Uso (solo contra la instancia AISLADA del 8088 con base limpia y catálogos cargados):
  ANFECA_URL=http://127.0.0.1:8088 PGHOST=127.0.0.1 PGPORT=5488 PGDATABASE=certificacion_anfeca \
  PGUSER=anfeca_8088 PGPASSWORD=... ANFECA_LOG=/var/www/ANFECA_8088/storage/logs/laravel.log \
  python3 scripts/anfeca_8088/pruebas/e2e_8088.py

Requiere: anfeca:catalogos --convocatoria-anio=<año>, anfeca:laboratorio (datos ficticios) y las cuentas de
coordinación ficticias indicadas en COORDINACION (creadas con anfeca:coordinador-alta).
Una prueba solo se aprueba si, además de la respuesta HTTP, los datos se encuentran en la tabla y fila correctas.
"""
import json, os, re, subprocess, sys, time, random
import requests

URL = os.environ.get('ANFECA_URL', 'http://127.0.0.1:8088').rstrip('/')
LOG = os.environ.get('ANFECA_LOG', '/var/www/ANFECA_8088/storage/logs/laravel.log')
PSQL = os.environ.get('PSQL', 'psql')
COORDINACION = json.loads(os.environ.get('ANFECA_COORDINACION', '{}'))  # {"nacional": [correo, clave], "zona7": [...], "zona6": [...]}

resultados = []


def sql(q, *args):
    for a in args:
        q = q.replace('%s', "'" + str(a).replace("'", "''") + "'", 1)
    r = subprocess.run([PSQL, '-X', '-At', '-F', '|', '-c', q], capture_output=True, text=True)
    if r.returncode:
        raise RuntimeError(r.stderr)
    return [l.split('|') for l in r.stdout.strip().splitlines() if l]


def uno(q, *args):
    filas = sql(q, *args)
    return filas[0][0] if filas else None


def prueba(num, nombre, condicion, detalle=''):
    resultados.append((num, nombre, bool(condicion), detalle))
    print(('APROBADA ' if condicion else 'FALLIDA  ') + f'[{num}] {nombre}' + (f' — {detalle}' if detalle else ''))


class Cliente:
    def __init__(self):
        self.s = requests.Session()

    def token(self, ruta):
        r = self.s.get(URL + ruta)
        m = re.search(r'name="_token" value="([^"]+)"|name="csrf-token" content="([^"]+)"|data-csrf="([^"]+)"', r.text)
        if m:
            return next(g for g in m.groups() if g)
        return self.s.cookies.get('XSRF-TOKEN')

    def get(self, ruta, **kw):
        return self.s.get(URL + ruta, allow_redirects=kw.pop('seguir', False), **kw)

    def post(self, ruta, datos=None, archivos=None, desde=None, seguir=False, json_=False):
        datos = dict(datos or {})
        datos['_token'] = self.token(desde or ruta)
        h = {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'} if json_ else {}
        if desde:
            h['Referer'] = URL + desde
        return self.s.post(URL + ruta, data=datos, files=archivos, allow_redirects=seguir, headers=h)


def pdf(nombre):
    contenido = (b'%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj '
                 b'3 0 obj<</Type/Page/MediaBox[0 0 200 200]/Parent 2 0 R>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n'
                 + b'% ' + nombre.encode() + b'\n')
    return (nombre + '.pdf', contenido, 'application/pdf')


def png(nombre):
    return (nombre + '.png', bytes.fromhex('89504e470d0a1a0a0000000d4948445200000001000000010806000000'
                                           '1f15c4890000000d49444154789c6360000002000154a24f5d0000000049454e44ae426082'), 'image/png')


def curp_ficticia():
    d = f'{random.randint(70, 99):02d}{random.randint(1, 12):02d}{random.randint(1, 28):02d}'
    return f'FICT{d}MDFCTA{random.randint(0, 9)}{random.randint(0, 9)}'


def ultimo_enlace_restablecer(correo):
    import glob
    archivos = sorted(glob.glob(LOG)) or sorted(glob.glob(os.path.join(os.path.dirname(LOG), 'laravel*.log')), key=os.path.getmtime)
    texto = ''.join(open(a, encoding='utf-8', errors='ignore').read() for a in archivos)
    enlaces = re.findall(r'(/restablecer/[A-Za-z0-9]+\?email=' + re.escape(requests.utils.quote(correo, safe='')) + r')', texto)
    enlaces += re.findall(r'(/restablecer/[A-Za-z0-9]+\?email=' + re.escape(correo) + r')', texto)
    return enlaces[-1] if enlaces else None


def registrar(c, curp, correo, clave, telefono_particular='5511112222'):
    return c.post('/registro/datos-personales', {
        'curp': curp, 'rfc': curp[:10] + 'AB1', 'nombres': 'Ana Prueba', 'apellido_paterno': 'Ficticia',
        'apellido_materno': 'Ocho', 'genero': 'Femenino', 'password': clave, 'password_confirmation': clave,
        'calle': 'Calle Ficticia', 'numero_exterior': '12', 'numero_interior': '3', 'codigo_postal': '04510',
        'entidad_federativa': 'Ciudad de México', 'colonia': 'Universidad Nacional Autónoma de México', 'municipio': 'Coyoacán',
        'correo_electronico': correo, 'telefono_particular': telefono_particular, 'telefono_celular': '',
    }, desde='/registro/datos-personales')


def flujo_formacion(c, persona, institucion, solicitud, sufijo, refrendo=False):
    afiliacion = uno('select inst_numero_afiliacion from institucion where inst_id_institucion = %s', institucion)
    datos = {'nivel_origen': 'Licenciatura', 'nivel_ultimo_grado': 'Maestría', 'titulo_ultimo_grado': 'Administración',
             'numero_afiliacion': afiliacion}
    if not refrendo or not uno("select 1 from persona_estudio_profesional p join estudio_profesional e on e.espr_id_estudio_profesional = p.pees_id_estuido_profesional "
                               "where p.pees_id_persona = %s and substr(e.espr_estado,3,1) = 'L'", persona):
        datos['titulo_origen'] = 'Administración'
    archivos = {'titulo_ultimo_grado_archivo': pdf('titulo_maestria_' + sufijo), 'cedula_ultimo_grado_archivo': png('cedula_maestria_' + sufijo)}
    if 'titulo_origen' in datos:
        archivos.update({'titulo_origen_archivo': pdf('titulo_lic_' + sufijo), 'cedula_origen_archivo': pdf('cedula_lic_' + sufijo)})
    r = c.post('/registro/formacion-academica', datos, archivos, desde='/registro/formacion-academica')
    guardada = r.status_code == 302 and r.headers.get('Location', '').endswith('/registro/formacion-academica')
    r2 = c.post('/registro/formacion-academica/autorizar', {'confirmacion_formacion': '1'}, desde='/registro/formacion-academica')
    autorizada = r2.status_code == 302 and '/registro/documentos-probatorios' in r2.headers.get('Location', '')
    fa = uno("select count(*) from estado_solicitud where esso_id_solicitud = %s and esso_siglas = 'FA'", solicitud)
    return guardada, autorizada, fa


def flujo_documentos(c, solicitud, articulo14=False):
    archivos = {'constancia_antiguedad_archivo': pdf('constancia'), 'comprobante_pago_archivo': png('pago')}
    datos = {}
    if articulo14:
        archivos.update({'solicitud_articulo_14_archivo': pdf('solicitud14'), 'constancia_articulo_14_archivo': pdf('constancia14')})
    else:
        datos['link'] = 'https://evidencias.example/anfeca/' + str(solicitud)
    return c.post('/registro/documentos-probatorios', datos, archivos, desde='/registro/documentos-probatorios')


def principal():
    sufijo = str(int(time.time()))[-6:]
    clave = 'ClaveFicticia#2026'

    # ------------------------------------------------------------------ 1 · Registro y autenticación
    c = Cliente()
    curp = curp_ficticia()
    correo = f'ana.ficticia.{sufijo}@example.test'
    r = registrar(c, curp, correo, clave)
    persona = uno('select pers_id_persona from persona where pers_curp = %s', curp)
    usuario = sql('select usua_id_usuario, usua_estatus, r.rol_nombre from usuario u join rol r on r.rol_id_rol = u.usua_id_rol where u.usua_id_persona = %s', persona) if persona else []
    domicilio = sql('select d.domi_calle, d.domi_numero_ext, d.domi_numero_int, e.enfe_nombre from domicilio d join entidad_federativa e on '
                    'e.enfe_id_entidad_federativa = d.domi_id_entidad_federativa where d.domi_id_persona = %s', persona) if persona else []
    prueba(1, 'Registro desde cero crea PERSONA, USUARIO (rol solicitante) y DOMICILIO en una transacción',
           r.status_code == 302 and persona and usuario and usuario[0][2] == 'solicitante' and domicilio
           and domicilio[0] == ['Calle Ficticia', '12', '3', 'Ciudad de México'], f'persona={persona} usuario={usuario} domicilio={domicilio}')
    hash_ok = uno('select usua_contrasenia like %s from usuario where usua_id_persona = %s', '$2y$%', persona) == 't'
    c2 = Cliente()
    r = c2.post('/login', {'email': correo.upper(), 'password': clave}, desde='/login')
    r_mal = Cliente().post('/login', {'email': correo, 'password': 'incorrecta-123456'}, desde='/login')
    prueba(1, 'Autenticación con USUARIO (hash bcrypt) y rechazo de contraseña incorrecta',
           hash_ok and r.status_code == 302 and '/mis-datos' in r.headers.get('Location', '') and r_mal.status_code == 302
           and '/login' in r_mal.headers.get('Location', ''), r.headers.get('Location', ''))

    # ------------------------------------------------------------------ 3 · Captura de datos personales y domicilio (modelo vigente)
    contacto = dict((k, v) for k, v in sql('select t.tico_tipo_comunicacion, c.comu_valor from comunicacion c join tipo_comunicacion t on '
                                             't.tico_id_tipo_comunicacion = c.comu_id_tipo_comunicacion where c.comu_id_persona = %s', persona))
    pagina = c.get('/mis-datos').text
    # TEL_PARTICULAR: «Teléfono de casa» (vigente en 8088) o su equivalente «Teléfono particular» (bases anteriores).
    TEL_PART = ('Teléfono de casa', 'Teléfono particular')
    prueba(3, 'Datos personales, correo y teléfonos persistidos; DOMICILIO con entidad derivada del código postal',
           len(contacto) == 2 and contacto.get('Correo electrónico') == correo
           and any(contacto.get(t) == '5511112222' for t in TEL_PART) and curp in pagina
           and domicilio and domicilio[0][3] == 'Ciudad de México', json.dumps(contacto, ensure_ascii=False))
    geograficos = uno("select count(*) from tipo_comunicacion where tico_tipo_comunicacion ~* '(postal|colonia|municipio|alcald|entidad)'")
    prueba(3, 'Código postal, colonia y municipio NO se guardan en COMUNICACION/TIPO_COMUNICACION ni en otro mecanismo',
           geograficos == '0' and 'Universidad Nacional Autónoma de México' not in pagina and '04510' not in pagina)

    # Validación de CAPTURA contra SEPOMEX (independiente de la persistencia): nada se guarda si no corresponde.
    def alta_invalida(**cambios):
        cx = Cliente()
        curp_x = curp_ficticia()
        datos = {'curp': curp_x, 'rfc': curp_x[:10] + 'AB1', 'nombres': 'Prueba', 'apellido_paterno': 'Captura', 'genero': 'Femenino',
                 'password': clave, 'password_confirmation': clave, 'calle': 'Calle', 'numero_exterior': '1', 'codigo_postal': '44130',
                 'entidad_federativa': 'Jalisco', 'colonia': 'Arcos Vallarta', 'municipio': 'Guadalajara',
                 'correo_electronico': f'captura.{curp_x.lower()}@example.test', 'telefono_celular': '3300000000'}
        datos.update(cambios)
        r = cx.post('/registro/datos-personales', datos, desde='/registro/datos-personales')
        return r, uno('select count(*) from persona where pers_curp = %s', curp_x)
    casos = {'codigo_postal': {'codigo_postal': '99999'}, 'colonia': {'colonia': 'Colonia Inexistente'},
             'municipio': {'municipio': 'Zapopan'}, 'entidad_federativa': {'entidad_federativa': 'Sonora'}}
    rechazados = {campo: alta_invalida(**cambio) for campo, cambio in casos.items()}
    prueba(3, 'Captura: CP inexistente, colonia, municipio o entidad que no corresponden al CP se rechazan sin crear cuenta',
           all(r.status_code == 302 and '/registro/datos-personales' in r.headers.get('Location', '') and n == '0' for r, n in rechazados.values()),
           str({k: (v[0].status_code, v[1]) for k, v in rechazados.items()}))

    # ------------------------------------------------------------------ 4 · Modificación restringida
    r = c.post('/mis-datos', {'_method': 'PUT', 'calle': 'Avenida Nueva', 'numero_exterior': '99', 'numero_interior': '',
                              'codigo_postal': '44100', 'entidad_federativa': 'Jalisco', 'colonia': 'Guadalajara Centro', 'municipio': 'Guadalajara',
                              'telefono_particular': '', 'telefono_celular': '3312345678',
                              'curp': 'XXXX000000HDFXXX00', 'nombres': 'Intruso', 'correo_electronico': 'otro@example.test'}, desde='/mis-datos')
    dom = sql('select d.domi_calle, d.domi_numero_ext, coalesce(d.domi_numero_int, \'\'), e.enfe_nombre from domicilio d join entidad_federativa e '
              'on e.enfe_id_entidad_federativa = d.domi_id_entidad_federativa where d.domi_id_persona = %s', persona)[0]
    pers = sql('select pers_curp, pers_nombre from persona where pers_id_persona = %s', persona)[0]
    tels = dict(sql("select t.tico_tipo_comunicacion, c.comu_valor from comunicacion c join tipo_comunicacion t on t.tico_id_tipo_comunicacion = "
                    "c.comu_id_tipo_comunicacion where c.comu_id_persona = %s", persona))
    sin_tel = c.post('/mis-datos', {'_method': 'PUT', 'calle': 'Avenida Nueva', 'numero_exterior': '99', 'codigo_postal': '44100',
                                    'entidad_federativa': 'Jalisco', 'colonia': 'Guadalajara Centro', 'municipio': 'Guadalajara',
                                    'telefono_particular': '', 'telefono_celular': ''}, desde='/mis-datos')
    tels2 = uno("select count(*) from comunicacion c join tipo_comunicacion t on t.tico_id_tipo_comunicacion = c.comu_id_tipo_comunicacion "
                "where c.comu_id_persona = %s and t.tico_tipo_comunicacion like 'Teléfono%%'", persona)
    mal_cp = c.post('/mis-datos', {'_method': 'PUT', 'calle': 'Otra', 'numero_exterior': '1', 'codigo_postal': '44100',
                                   'entidad_federativa': 'Jalisco', 'colonia': 'Arcos', 'municipio': 'Guadalajara', 'telefono_celular': '3312345678'},
                    desde='/mis-datos')
    prueba(4, 'Antes de autorizar solo cambian domicilio y teléfonos (entidad del CP 44100 = Jalisco); identidad y correo bloqueados; '
              'al menos un teléfono; colonia de otro CP rechazada',
           r.status_code == 302 and dom == ['Avenida Nueva', '99', '', 'Jalisco'] and pers == [curp, 'Ana Prueba']
           and tels.get('Correo electrónico') == correo and tels.get('Teléfono celular') == '3312345678'
           and not any(t in tels for t in TEL_PART) and sin_tel.status_code == 302 and tels2 == '1'
           and uno('select domi_calle from domicilio where domi_id_persona = %s', persona) == 'Avenida Nueva',
           f'domicilio={dom} identidad={pers}')

    # ------------------------------------------------------------------ 5 · Autorización
    r = c.post('/mis-datos/autorizar', {'confirmacion_datos': '1'}, desde='/mis-datos')
    estatus = uno('select usua_estatus from usuario where usua_id_persona = %s', persona)
    r_bloq = c.post('/mis-datos', {'_method': 'PUT', 'calle': 'Calle Bloqueada', 'numero_exterior': '1', 'codigo_postal': '44100',
                                   'entidad_federativa': 'Jalisco', 'colonia': 'Guadalajara Centro', 'municipio': 'Guadalajara', 'telefono_celular': '3312345678'},
                    desde='/mis-datos')
    calle = uno('select domi_calle from domicilio where domi_id_persona = %s', persona)
    prueba(5, 'Autorización con los atributos exigibles del modelo (sin CP, colonia ni municipio guardados) y sin modificaciones posteriores',
           estatus == 'DATOS_AUTORIZADOS' and calle == 'Avenida Nueva' and '/registro/certificacion' in r.headers.get('Location', ''),
           f'estatus={estatus} calle={calle}')

    # ------------------------------------------------------------------ 6/7 · Selección y solicitud inicial
    inst = uno("select inst_id_institucion from institucion where inst_numero_afiliacion = 'LAB0002'")
    r = c.post('/registro/certificacion', {'certificacion': 'ANFECA', 'tipo_personal': 'Profesoras y profesores de Tiempo Completo',
                                           'area_certificacion': 'Administración', 'numero_certificado_anterior': ''}, desde='/registro/certificacion')
    sol = sql('select s.soli_id_solicitud, s.soli_folio_solicitud, a.area_descripcion, tp.tipa_descripcion, tc.tico_siglas, s.soli_numero_certificado '
              'from solicitud s join area_certificacion a on a.area_id_area_certificacion = s.soli_id_area_certificacion join tipo_personal_academico tp '
              'on tp.tipa_id_tipo_personal_academico = s.soli_id_tipo_personal_academico join convocatoria cv on cv.conv_id_convocatoria = '
              's.soli_id_convocatoria join tipo_convocatoria tc on tc.tico_id_tipo_convocatoria = cv.conv_id_tipo_convocatoria where s.soli_id_persona = %s', persona)
    solicitud = sol[0][0] if sol else None
    prueba(6, 'Selección de certificación, tipo de personal y área (catálogos FK)',
           r.status_code == 302 and '/registro/formacion-academica' in r.headers.get('Location', '') and sol
           and sol[0][2:5] == ['Administración', 'Profesoras y profesores de Tiempo Completo', 'ANFECA'], str(sol))
    prueba(7, 'Solicitud inicial persistida en SOLICITUD (folio = id, sin certificado) con evento «Borrador»',
           sol and sol[0][0] == sol[0][1] and sol[0][5] == '' and uno("select count(*) from estado_solicitud where esso_id_solicitud = %s and esso_siglas = 'BO'", solicitud) == '1')

    # ------------------------------------------------------------------ 9 · Formación académica
    ruta_institucion = '/registro/formacion-academica/institucion'
    buscar = lambda numero: c.get(ruta_institucion, params={'numero_afiliacion': numero}, headers={'Accept': 'application/json'})
    afiliada = buscar('LAB0002')
    prueba(9, 'Afiliación exacta, datos institucionales y Campus sin dato oficial',
           afiliada.status_code == 200 and str(afiliada.json()['id']) == inst
           and afiliada.json()['numero_afiliacion'] == 'LAB0002' and afiliada.json()['campus'] is None)
    prueba(9, 'La búsqueda exige sesión y rechaza afiliaciones parciales o inválidas',
           Cliente().get(ruta_institucion, params={'numero_afiliacion': 'LAB0002'}).status_code == 302
           and buscar('LAB000').status_code == 404 and buscar('LAB00022').status_code == 422)
    guardada, autorizada, fa = flujo_formacion(c, persona, inst, solicitud, 'ini')
    pagina_formacion = c.get('/registro/formacion-academica')
    prueba(9, 'La afiliación persistida se recupera al regresar a Formación',
           pagina_formacion.status_code == 200 and 'data-numero-afiliacion="LAB0002"' in pagina_formacion.text)

    pees = sql('select e.espr_nombre, n.nipr_nivel_profesional, e.espr_estado, p.pees_url_titulo is not null, p.pees_url_cedula is not null '
               'from persona_estudio_profesional p join estudio_profesional e on e.espr_id_estudio_profesional = p.pees_id_estuido_profesional '
               'join nivel_profesional n on n.nipr_id_nivel_profesional = e.espr_id_nivel_profesional where p.pees_id_persona = %s order by 3', persona)
    inst_p = uno('select pers_id_institucion from persona where pers_id_persona = %s', persona)
    prueba(9, 'Formación (licenciatura + maestría) en ESTUDIO_PROFESIONAL/PERSONA_ESTUDIO_PROFESIONAL, institución en PERSONA y autorización',
           guardada and autorizada and fa == '1' and len(pees) == 2 and inst_p == inst and all(x[3] == 't' and x[4] == 't' for x in pees),
           str(pees))
    r_mod = c.post('/registro/formacion-academica', {'nivel_origen': 'Licenciatura', 'titulo_origen': 'Administración'}, desde='/registro/formacion-academica')
    prueba(9, 'La formación autorizada ya no admite cambios', r_mod.status_code == 302 and uno(
        'select count(*) from persona_estudio_profesional where pees_id_persona = %s', persona) == '2')

    # ------------------------------------------------------------------ 10/11 · Documentos
    r = flujo_documentos(c, solicitud)
    docs = sql('select d.docu_descripcion, ed.esdo_estado_documento from solicitud_documento s join documento d on d.docu_id_documento = s.socu_id_documento '
               'join estado_solicitud_documento e on e.esdo_id_documentacion = s.socu_id_documentacion join estado_documento ed on '
               'ed.esdo_id_estado_documento = e.esdo_id_estado_documento where s.socu_id_solicitud = %s order by s.socu_id_documentacion', solicitud)
    pago = uno('select pago_url_comprobante from pago where pago_id_solicitud = %s', solicitud)
    prueba(10, 'Carga de documentos: SOLICITUD_DOCUMENTO + estado «Cargado», PAGO y enlace de evidencias',
           r.status_code == 302 and '/registro/compromiso' in r.headers.get('Location', '') and len(docs) == 7 and pago
           and any(d[0] == 'Enlace de evidencias' for d in docs), f'{len(docs)} documentos, pago={pago}')
    v1 = c.get('/registro/documentos-probatorios/ver/constancia_antiguedad_archivo')
    v2 = c.get('/registro/documentos-probatorios/ver/titulo_origen_archivo')
    otro = Cliente()
    prueba(11, 'Consulta documental del propio expediente (PDF íntegro) y acceso denegado sin sesión',
           v1.status_code == 200 and v1.content.startswith(b'%PDF') and b'constancia' in v1.content and v2.status_code == 200
           and otro.get('/registro/documentos-probatorios/ver/constancia_antiguedad_archivo').status_code == 302)
    r_rep = flujo_documentos(c, solicitud)
    prueba(10, 'Los documentos guardados quedan en solo consulta', uno('select count(*) from solicitud_documento where socu_id_solicitud = %s', solicitud) == '7')

    # ------------------------------------------------------------------ 12 · Compromiso
    sin = c.post('/registro/compromiso/guardar', {}, desde='/registro/compromiso')
    ca0 = uno("select count(*) from estado_solicitud where esso_id_solicitud = %s and esso_siglas = 'CA'", solicitud)
    texto_compromiso = 'Para completar su solicitud, deberá entregar las fotografías indicadas en la convocatoria y el comprobante de pago original.'
    vue = open(os.path.join(os.path.dirname(__file__), '../../../resources/js/components/AceptacionCompromiso.vue'), encoding='utf-8').read()
    envio_previo = c.post('/registro/completado/confirmar', {}, desde='/registro/compromiso')
    r = c.post('/registro/compromiso/guardar', {'compromiso_aceptado': '1'}, desde='/registro/compromiso')
    ca = uno("select count(*) from estado_solicitud where esso_id_solicitud = %s and esso_siglas = 'CA'", solicitud)
    prueba(12, 'Casilla obligatoria validada en el servidor, texto definitivo y evento «Compromiso de entrega aceptado»',
           ca0 == '0' and ca == '1' and texto_compromiso in vue and uno("select count(*) from estado_solicitud where esso_id_solicitud = %s and esso_siglas = 'EN'", solicitud) == '0')

    # ------------------------------------------------------------------ 13 · Envío
    r = c.post('/registro/completado/confirmar', {}, desde='/registro/completado')
    final = c.get('/registro/completado').text
    en = uno("select count(*) from estado_solicitud where esso_id_solicitud = %s and esso_siglas = 'EN'", solicitud)
    antes = uno('select count(*) from solicitud_documento where socu_id_solicitud = %s', solicitud)
    c.post('/registro/documentos-probatorios', {'link': 'https://otro.example'}, {'constancia_antiguedad_archivo': pdf('otra')}, desde='/registro/completado')
    c.post('/registro/compromiso/guardar', {'compromiso_aceptado': '1'}, desde='/registro/completado')
    bloqueada = uno('select count(*) from solicitud_documento where socu_id_solicitud = %s', solicitud) == antes and uno(
        "select count(*) from estado_solicitud where esso_id_solicitud = %s and esso_siglas = 'CA'", solicitud) == '1'
    prueba(13, 'Envío: evento «Enviada», mensaje «Su solicitud fue registrada correctamente.» y bloqueo posterior',
           en == '1' and bloqueada and 'Su solicitud fue registrada correctamente.' in final and uno(
               "select count(*) from estado_solicitud where esso_id_solicitud = %s", solicitud) == '5', f'eventos={sql("select esso_siglas from estado_solicitud where esso_id_solicitud = %s order by 1", solicitud)}')

    # ------------------------------------------------------------------ 14 · PDF
    a = c.get(f'/registro/acuse/{solicitud}')
    prueba(14, 'Acuse PDF generado para la solicitud enviada (y 404 para solicitudes ajenas)',
           a.status_code == 200 and a.content.startswith(b'%PDF') and len(a.content) > 2000
           and c.get(f'/registro/acuse/{int(solicitud) + 999}').status_code == 404, f'{len(a.content)} bytes')

    # ------------------------------------------------------------------ 15 · Seguimiento
    panel = c.get('/panel').text
    menu = c.get('/registro/completado').text
    prueba(15, 'Seguimiento: panel y finalización muestran el folio y el estado enviado',
           f'<strong>{solicitud}</strong>' in menu and 'Formación Académica' in panel)

    # ------------------------------------------------------------------ 2 · Recuperación de contraseña
    rc = Cliente()
    rc.post('/recuperar', {'curp': curp, 'email': correo}, desde='/recuperar')
    enlace = ultimo_enlace_restablecer(correo)
    restablecida = False
    if enlace:
        token = enlace.split('/restablecer/')[1].split('?')[0]
        nueva = 'NuevaClaveFicticia#2026'
        rr = rc.post('/restablecer', {'token': token, 'email': correo, 'password': nueva, 'password_confirmation': nueva}, desde=enlace)
        log = Cliente().post('/login', {'email': correo, 'password': nueva}, desde='/login')
        restablecida = rr.status_code == 302 and '/login' in rr.headers.get('Location', '') and log.status_code == 302 and '/login' not in log.headers.get('Location', '')
        clave = nueva
    prueba(2, 'Recuperación por CURP + correo: token en caché de archivos, nueva contraseña en USUARIO', restablecida, enlace or 'sin enlace')

    # ------------------------------------------------------------------ 8 · Refrendo histórico
    cr = Cliente()
    curp_r = 'FILR800101HDFCTC09'
    correo_r = f'luis.refrendo.{sufijo}@example.test'
    if uno('select count(*) from persona where pers_curp = %s', curp_r) != '0':
        print('AVISO: la persona del refrendo ya existe; use una base limpia para repetir la prueba 8.')
    registrar(cr, curp_r, correo_r, clave, '5599990000')
    persona_r = uno('select pers_id_persona from persona where pers_curp = %s', curp_r)
    cr.post('/mis-datos/autorizar', {'confirmacion_datos': '1'}, desde='/mis-datos')
    consulta = cr.post('/registro/certificacion/consultar', {'numero_certificado': '1001'}, desde='/registro/certificacion', json_=True)
    ajeno = cr.post('/registro/certificacion/consultar', {'numero_certificado': '1003'}, desde='/registro/certificacion', json_=True)
    vigente = cr.post('/registro/certificacion', {'certificacion': 'ANFECA', 'tipo_personal': 'Profesoras y profesores de Medio Tiempo',
                                                  'area_certificacion': 'Contaduría Pública', 'numero_certificado_anterior': '1002'}, desde='/registro/certificacion')
    automatica = cr.post('/registro/certificacion', {'certificacion': 'ANFECA', 'tipo_personal': 'Profesoras y profesores de Medio Tiempo',
                                                     'area_certificacion': 'Administración', 'numero_certificado_anterior': ''}, desde='/registro/certificacion')
    sin_sol = uno('select count(*) from solicitud where soli_id_persona = %s', persona_r)
    r = cr.post('/registro/certificacion', {'certificacion': 'ANFECA', 'tipo_personal': 'Profesoras y profesores de Medio Tiempo',
                                            'area_certificacion': 'Administración', 'numero_certificado_anterior': '1001'}, desde='/registro/certificacion')
    sol_r = uno('select soli_id_solicitud from solicitud where soli_id_persona = %s and soli_numero_certificado = %s', persona_r, '1001')
    ref = cr.get('/registro/refrendo').text
    cr.post('/registro/refrendo', {'aplica_articulo_14': '1', 'inciso_articulo_14': '14.1'}, desde='/registro/refrendo')
    art = sql('select s.soli_art14, s.soli_art13, a.arce_subindice from solicitud s left join articulo_subindice a on a.arce_id_articulo = s.soli_id_articulo where s.soli_id_solicitud = %s', sol_r)
    inst_r = uno("select inst_id_institucion from institucion where inst_numero_afiliacion = 'LAB0003'")
    guardada, autorizada, fa = flujo_formacion(cr, persona_r, inst_r, sol_r, 'ref', refrendo=True)
    d = flujo_documentos(cr, sol_r, articulo14=True)
    cr.post('/registro/compromiso/guardar', {'compromiso_aceptado': '1'}, desde='/registro/compromiso')
    cr.post('/registro/completado/confirmar', {}, desde='/registro/completado')
    en_r = uno("select count(*) from estado_solicitud where esso_id_solicitud = %s and esso_siglas = 'EN'", sol_r)
    hist = uno('select count(*) from historico_certificado where hice_folio_certificado = %s', '1001')
    duplicado = cr.post('/registro/certificacion', {'certificacion': 'ANFECA', 'tipo_personal': 'Profesoras y profesores de Medio Tiempo',
                                                    'area_certificacion': 'Administración', 'numero_certificado_anterior': '1001'}, desde='/registro/certificacion', seguir=True)
    total_r = uno('select count(*) from solicitud where soli_id_persona = %s', persona_r)
    acuse_r = cr.get(f'/registro/acuse/{sol_r}')
    prueba(8, 'Refrendo: consulta propia válida, certificado ajeno rechazado, vigencia de 3 años (1002), sin selección automática',
           consulta.ok and consulta.json().get('encontrado') is True and ajeno.json().get('encontrado') is False and sin_sol == '0'
           and vigente.status_code == 302, consulta.text[:120])
    prueba(8, 'Refrendo del certificado 1001: SOLI_NUMERO_CERTIFICADO, refrendo n.º 2 calculado, art. 14.1, formación y 4 documentos',
           bool(sol_r) and re.search(r'refrendo_actual(&quot;|")\s*:\s*2\b', ref) is not None, f'art={art}')
    prueba(8, 'Refrendo enviado conservando el antecedente histórico (sin modificar HISTORICO_CERTIFICADO) y acuse PDF',
           en_r == '1' and hist == '2' and art == [['t', 'f', '14.1']] and guardada and autorizada and fa == '1'
           and acuse_r.content.startswith(b'%PDF'))
    prueba(8, 'Se impide un segundo refrendo del mismo certificado en el periodo vigente', total_r == '1')

    # ------------------------------------------------------------------ 16/17 · Coordinación
    if not COORDINACION:
        print('AVISO: sin ANFECA_COORDINACION no se ejecutan las pruebas 16 y 17.')
    else:
        # El inicio de sesión está limitado a 5 intentos por minuto y por IP (regla de la aplicación): se respeta.
        time.sleep(61)
        nac = Cliente()
        nac.post('/login', {'email': COORDINACION['nacional'][0], 'password': COORDINACION['nacional'][1]}, desde='/login')
        api = nac.get('/coordinacion/api/solicitudes?por_pagina=50', headers={'Accept': 'application/json'})
        datos = api.json().get('datos', []) if api.ok else []
        folios = {str(x['folio']): x for x in datos}
        exp = nac.get(f'/coordinacion/solicitudes/{solicitud}')
        docu = nac.get(f'/coordinacion/solicitudes/{solicitud}/documentos/comprobante_pago_archivo')
        prueba(16, 'Coordinación nacional: bandeja con ambas solicitudes, expediente real y visor de documentos',
               solicitud in folios and sol_r in folios and folios[solicitud]['zona'].startswith('Zona 7')
               and folios[sol_r]['tramite'] == 'Refrendo' and folios[sol_r]['certificado_historico'] == '1001'
               and exp.status_code == 200 and 'Ana Prueba' in exp.text and docu.status_code == 200
               and docu.headers.get('Content-Type', '').startswith('image/png'), f'folios={list(folios)}')
        ind = nac.get('/coordinacion/api/indicadores', headers={'Accept': 'application/json'})
        seg = nac.get('/coordinacion/api/seguimiento', headers={'Accept': 'application/json'})
        prueba(16, 'Dashboard (indicadores) y seguimiento de registros responden con datos de las 36 tablas',
               ind.ok and seg.ok and 'tarjetas' in ind.json(), f'indicadores={ind.status_code} seguimiento={seg.status_code}')
        z7 = Cliente()
        z7.post('/login', {'email': COORDINACION['zona7'][0], 'password': COORDINACION['zona7'][1]}, desde='/login')
        lista7 = [str(x['folio']) for x in z7.get('/coordinacion/api/solicitudes', headers={'Accept': 'application/json'}).json().get('datos', [])]
        z6 = Cliente()
        z6.post('/login', {'email': COORDINACION['zona6'][0], 'password': COORDINACION['zona6'][1]}, desde='/login')
        lista6 = [str(x['folio']) for x in z6.get('/coordinacion/api/solicitudes', headers={'Accept': 'application/json'}).json().get('datos', [])]
        prueba(17, 'Coordinación regional limitada a su zona (Z7 ve solo la de CDMX; Z6 solo la del Sur) y 404 fuera de alcance',
               solicitud in lista7 and sol_r not in lista7 and sol_r in lista6 and solicitud not in lista6
               and z6.get(f'/coordinacion/solicitudes/{solicitud}').status_code == 404
               and z6.get(f'/coordinacion/solicitudes/{solicitud}/documentos/comprobante_pago_archivo').status_code == 404,
               f'z7={lista7} z6={lista6}')
        prueba(17, 'Un solicitante no accede a Coordinación y la coordinación no usa el flujo de solicitantes',
               c.get('/coordinacion/solicitudes').status_code == 403 and nac.get('/registro/certificacion').status_code == 302)

    # ------------------------------------------------------------------ 19 · Regresión de pantallas
    pantallas = {
        '/login': (Cliente(), 'ANFECA'), '/registro/datos-personales': (Cliente(), 'Datos Personales'),
        '/recuperar': (Cliente(), 'Recuperación de contraseña'),
        '/mis-datos': (c, 'Autorización de Datos'), '/registro/certificacion': (c, 'Certificación'),
        '/registro/formacion-academica': (cr, 'Formación'), '/registro/documentos-probatorios': (cr, 'Documentos'),
        '/registro/compromiso': (c, 'Aceptación y compromiso'), '/registro/completado': (c, 'Solicitud enviada correctamente'),
        '/panel': (c, 'Datos Personales'),
    }
    if COORDINACION:
        pantallas.update({'/coordinacion': (nac, 'Inicio'), '/coordinacion/solicitudes': (nac, 'Solicitudes'),
                          '/coordinacion/seguimiento': (nac, 'Seguimiento'), f'/coordinacion/solicitudes/{solicitud}': (nac, f'Solicitud {solicitud}'), f'/coordinacion/solicitudes/{sol_r}': (nac, f'Solicitud {sol_r}')})
    malas = []
    for ruta, (cli, texto) in pantallas.items():
        r = cli.get(ruta, seguir=True)
        if r.status_code != 200 or texto.lower() not in r.text.lower() or 'Exception' in r.text:
            malas.append(f'{ruta}:{r.status_code}')
    prueba(19, f'Pantallas existentes responden con su contenido ({len(pantallas)} rutas, sin errores del servidor)', not malas, ', '.join(malas))

    # ------------------------------------------------------------------ 18 · Integridad PK/FK
    huerfanos = uno('''select
        (select count(*) from usuario u left join persona p on p.pers_id_persona = u.usua_id_persona where p.pers_id_persona is null) +
        (select count(*) from solicitud s left join persona p on p.pers_id_persona = s.soli_id_persona where p.pers_id_persona is null) +
        (select count(*) from solicitud_documento d left join solicitud s on s.soli_id_solicitud = d.socu_id_solicitud where s.soli_id_solicitud is null)''')
    fks = uno("select count(*) from pg_constraint where contype = 'f' and connamespace = 'public'::regnamespace and convalidated")
    prueba(18, 'Integridad: 35 FK validadas y sin registros huérfanos', fks == '35' and huerfanos == '0', f'fk={fks} huerfanos={huerfanos}')

    # ------------------------------------------------------------------ 20 · Ausencia de tablas adicionales
    tablas = uno("select count(*) from information_schema.tables where table_schema not in ('pg_catalog', 'information_schema')")
    esquemas = uno("select string_agg(nspname, ',') from pg_namespace where nspname !~ '^pg_' and nspname <> 'information_schema'")
    prueba(20, 'Solo 36 tablas en el esquema public (sin sessions, cache, jobs, users, migrations ni esquemas extra)',
           tablas == '36' and esquemas == 'public', f'tablas={tablas} esquemas={esquemas}')

    aprobadas = sum(1 for r in resultados if r[2])
    print(f'\nResultado: {aprobadas}/{len(resultados)} verificaciones aprobadas.')
    with open(os.environ.get('ANFECA_E2E_SALIDA', '/tmp/e2e_8088.json'), 'w', encoding='utf-8') as f:
        json.dump([{'prueba': r[0], 'nombre': r[1], 'aprobada': r[2], 'detalle': r[3]} for r in resultados], f, ensure_ascii=False, indent=1)
    return 0 if aprobadas == len(resultados) else 1


if __name__ == '__main__':
    sys.exit(principal())
