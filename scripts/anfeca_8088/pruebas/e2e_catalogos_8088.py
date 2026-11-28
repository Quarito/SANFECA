#!/usr/bin/env python3
"""
Prueba HTTP de Formación académica con las banderas vigentes en 8086 (catálogo de licenciaturas, catálogo de
posgrados y declaración «Cuento con estudios de posgrado concluidos»). Mismas variables que e2e_8088.py.
Escenario 2: título de licenciatura y de maestría desde el catálogo. Escenario 1: solo licenciatura (manual).
"""
import html, json, os, re, sys, time
sys.path.insert(0, os.path.dirname(__file__))
from e2e_8088 import Cliente, curp_ficticia, pdf, png, prueba, registrar, resultados, sql, uno, flujo_documentos


def formacion_pantalla(c):
    t = c.get('/registro/formacion-academica', seguir=True).text
    m = re.search(r'data-formacion="([^"]+)"', t)
    return json.loads(html.unescape(m.group(1))) if m else {}


def preparar(c, sufijo, area):
    curp = curp_ficticia()
    registrar(c, curp, f'catalogo.{sufijo}@example.test', 'ClaveFicticia#2026')
    persona = uno('select pers_id_persona from persona where pers_curp = %s', curp)
    c.post('/mis-datos/autorizar', {'confirmacion_datos': '1'}, desde='/mis-datos')
    c.post('/registro/certificacion', {'certificacion': 'CACECA', 'tipo_personal': 'Profesoras y profesores de Asignatura de Educación Superior',
                                       'area_certificacion': area}, desde='/registro/certificacion')
    return persona, uno('select soli_id_solicitud from solicitud where soli_id_persona = %s', persona)


def estudios(persona):
    return sql('select e.espr_nombre, n.nipr_nivel_profesional, e.espr_estado from persona_estudio_profesional p join estudio_profesional e '
               'on e.espr_id_estudio_profesional = p.pees_id_estuido_profesional join nivel_profesional n on n.nipr_id_nivel_profesional = '
               "e.espr_id_nivel_profesional where p.pees_id_persona = %s order by case n.nipr_nivel_profesional when 'Licenciatura' then 0 else 1 end", persona)


def principal():
    reparar_fixture = os.environ.get('ANFECA_PROBAR_DOCUMENTO_FALTANTE') == '1'
    if reparar_fixture and (
        os.environ.get('APP_ENV') != 'testing'
        or os.environ.get('PGHOST') != '127.0.0.1'
        or os.environ.get('PGDATABASE') != 'anfeca_formacion_lab'
        or os.environ.get('DB_DATABASE') != 'anfeca_formacion_lab'
        or os.environ.get('DB_CONNECTION') != 'pgsql'
        or os.environ.get('DB_HOST') != '127.0.0.1'
        or os.environ.get('DB_PORT') != os.environ.get('PGPORT')
        or os.environ.get('DB_URL')
        or uno('select current_database()') != 'anfeca_formacion_lab'
    ):
        raise RuntimeError('La prueba de reparación exige el laboratorio temporal anfeca_formacion_lab.')
    sufijo = str(int(time.time()))[-6:]
    inst = uno("select inst_id_institucion from institucion where inst_numero_afiliacion = 'LAB0001'")

    importados = os.environ.get('ANFECA_PROBAR_ESTUDIOS_IMPORTADOS') == '1'
    if importados:
        for titulo, nivel in [
            ('Licenciatura Ficticia en Administración', 'Licenciatura'),
            ('Maestría Ficticia en Dirección de Empresas', 'Maestría'),
        ]:
            assert uno("select count(*) from estudio_profesional e join nivel_profesional n on n.nipr_id_nivel_profesional=e.espr_id_nivel_profesional "
                       "where lower(e.espr_nombre)=lower(%s) and n.nipr_nivel_profesional=%s and e.espr_estado='ACT'", titulo, nivel) == '1'

    c = Cliente()
    persona, sol = preparar(c, 'e2' + sufijo, 'Administración')
    captura = {
        'nivel_origen': 'Licenciatura', 'modo_titulo_origen': 'catalogo', 'clave_licenciatura_catalogo': 'LIC-00000000000B',
        'cuenta_con_posgrado': '1', 'nivel_ultimo_grado': 'Maestría', 'titulo_ultimo_grado': 'Administración',
        'modo_titulo_ultimo_grado': 'catalogo', 'clave_posgrado_catalogo': 'MAE-00000000000A', 'institucion_afiliada_id': inst,
    }
    archivos = {'titulo_origen_archivo': pdf('t1'), 'cedula_origen_archivo': pdf('c1'), 'titulo_ultimo_grado_archivo': pdf('t2'),
                'cedula_ultimo_grado_archivo': png('c2')}
    if importados and reparar_fixture:
        fallida = c.post('/registro/formacion-academica', captura, archivos, desde='/registro/formacion-academica', json_=True)
        prueba(9, 'DOCUMENTO faltante produce 422 y evita escrituras parciales',
               fallida.status_code == 422 and 'titulo_origen_archivo' in fallida.json().get('errors', {})
               and estudios(persona) == []
               and uno('select count(*) from solicitud_documento where socu_id_solicitud = %s', sol) == '0')
        import subprocess
        import tempfile
        with tempfile.TemporaryDirectory() as temporal:
            env = dict(os.environ, APP_CONFIG_CACHE=os.path.join(temporal, 'config.php'))
            subprocess.run(['php', 'artisan', 'anfeca:catalogos', '--solo-documentos'], env=env, check=True)
    r = c.post('/registro/formacion-academica', captura, archivos, desde='/registro/formacion-academica')
    est = estudios(persona)
    f = formacion_pantalla(c)
    lic = 'LICENCIATURA FICTICIA EN ADMINISTRACIÓN' if importados else 'Licenciatura Ficticia en Administración'
    mae = 'MAESTRÍA FICTICIA EN DIRECCIÓN DE EMPRESAS' if importados else 'Maestría Ficticia en Dirección de Empresas'
    prueba(9, 'Catálogos activos · escenario 2: títulos guardados y recuperados con su clave',
           r.status_code == 302 and est == [[lic, 'Licenciatura', 'ACT' if importados else 'CAL'],
                                             [mae, 'Maestría', 'ACT' if importados else 'CAU']]
           and f.get('clave_licenciatura_catalogo') == 'LIC-00000000000B' and f.get('clave_posgrado_catalogo') == 'MAE-00000000000A'
           and f.get('origen_titulo_origen') == 'catalogo' and f.get('cuenta_con_posgrado') == '1', f'{est} {r.headers.get("Location")}')
    a = c.post('/registro/formacion-academica/autorizar', {'confirmacion_formacion': '1'}, desde='/registro/formacion-academica')
    prueba(9, 'Catálogos activos · escenario 2 autorizado (4 documentos académicos)', '/registro/documentos-probatorios' in a.headers.get('Location', '')
           and uno("select count(*) from estado_solicitud where esso_id_solicitud = %s and esso_siglas = 'FA'", sol) == '1')

    if importados:
        for titulo in [lic, mae]:
            prueba(9, 'Reutiliza ACT sin duplicar el título',
                   uno('select count(*) from estudio_profesional where lower(espr_nombre) = lower(%s)', titulo) == '1', titulo)
        r = flujo_documentos(c, sol)
        prueba(10, 'Documentos probatorios registrados', r.status_code == 302
               and uno("select count(*) from estado_solicitud where esso_id_solicitud=%s and esso_siglas='DP'", sol) == '1')
        c.post('/registro/completado/confirmar', {}, desde='/registro/compromiso')
        prueba(11, 'Envío requiere compromiso', uno("select count(*) from estado_solicitud where esso_id_solicitud=%s and esso_siglas='EN'", sol) == '0')
        c.post('/registro/compromiso/guardar', {'compromiso_aceptado': '1'}, desde='/registro/compromiso')
        c.post('/registro/completado/confirmar', {}, desde='/registro/completado')
        acuse = c.get('/registro/acuse/' + str(sol))
        prueba(12, 'Envío y acuse PDF', acuse.status_code == 200 and acuse.content.startswith(b'%PDF')
               and uno("select count(*) from estado_solicitud where esso_id_solicitud=%s and esso_siglas='EN'", sol) == '1')

    c1 = Cliente()
    persona1, sol1 = preparar(c1, 'e1' + sufijo, 'Contaduría Pública')
    r = c1.post('/registro/formacion-academica', {
        'nivel_origen': 'Licenciatura', 'modo_titulo_origen': 'manual', 'titulo_origen_manual': 'Licenciatura Ficticia en Contaduría y Finanzas',
        'cuenta_con_posgrado': '0', 'institucion_afiliada_id': inst,
    }, {'titulo_origen_archivo': pdf('t1'), 'cedula_origen_archivo': pdf('c1')}, desde='/registro/formacion-academica')
    a = c1.post('/registro/formacion-academica/autorizar', {'confirmacion_formacion': '1'}, desde='/registro/formacion-academica')
    est1 = estudios(persona1)
    f1 = formacion_pantalla(c1)
    prueba(9, 'Catálogos activos · escenario 1 (solo licenciatura, captura manual): MCL, sin último grado, autorizada con 2 documentos',
           est1 == [['Licenciatura Ficticia en Contaduría y Finanzas', 'Licenciatura', 'MCL']] and f1.get('cuenta_con_posgrado') == '0'
           and f1.get('origen_titulo_origen') == 'manual' and '/registro/documentos-probatorios' in a.headers.get('Location', ''),
           f'{est1} {a.headers.get("Location")}')

    c3 = Cliente()
    persona3, sol3 = preparar(c3, 'e3' + sufijo, 'Disciplinas vinculadas al ámbito económico-administrativo')
    r = c3.post('/registro/formacion-academica', {
        'nivel_origen': 'Licenciatura', 'modo_titulo_origen': 'manual', 'titulo_origen_manual': 'Licenciatura Ficticia en Economía',
        'cuenta_con_posgrado': '0', 'institucion_afiliada_id': inst,
    }, {'titulo_origen_archivo': pdf('t1'), 'cedula_origen_archivo': pdf('c1')}, desde='/registro/formacion-academica')
    prueba(9, 'Área que exige posgrado: se rechaza «solo licenciatura» y no se guarda nada', r.status_code == 302 and estudios(persona3) == []
           and uno('select count(*) from solicitud_documento where socu_id_solicitud = %s', sol3) == '0')

    ok = all(x[2] for x in resultados)
    print(f'\nResultado: {sum(1 for x in resultados if x[2])}/{len(resultados)} verificaciones aprobadas.')
    return 0 if ok else 1


if __name__ == '__main__':
    sys.exit(principal())
