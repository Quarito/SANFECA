#!/usr/bin/env python3
"""
Prueba HTTP de Formación académica con las banderas vigentes en 8086 (catálogo de licenciaturas, catálogo de
posgrados y declaración «Cuento con estudios de posgrado concluidos»). Mismas variables que e2e_8088.py.
Escenario 2: título de licenciatura y de maestría desde el catálogo. Escenario 1: solo licenciatura (manual).
"""
import html, json, os, re, sys, time
sys.path.insert(0, os.path.dirname(__file__))
from e2e_8088 import Cliente, curp_ficticia, pdf, png, prueba, registrar, resultados, sql, uno


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
               'e.espr_id_nivel_profesional where p.pees_id_persona = %s order by 3', persona)


def principal():
    sufijo = str(int(time.time()))[-6:]
    inst = uno("select inst_id_institucion from institucion where inst_numero_afiliacion = 'LAB0001'")

    c = Cliente()
    persona, sol = preparar(c, 'e2' + sufijo, 'Administración')
    r = c.post('/registro/formacion-academica', {
        'nivel_origen': 'Licenciatura', 'modo_titulo_origen': 'catalogo', 'clave_licenciatura_catalogo': 'LIC-00000000000B',
        'cuenta_con_posgrado': '1', 'nivel_ultimo_grado': 'Maestría', 'titulo_ultimo_grado': 'Administración',
        'modo_titulo_ultimo_grado': 'catalogo', 'clave_posgrado_catalogo': 'MAE-00000000000A', 'institucion_afiliada_id': inst,
    }, {'titulo_origen_archivo': pdf('t1'), 'cedula_origen_archivo': pdf('c1'), 'titulo_ultimo_grado_archivo': pdf('t2'),
        'cedula_ultimo_grado_archivo': png('c2')}, desde='/registro/formacion-academica')
    est = estudios(persona)
    f = formacion_pantalla(c)
    prueba(9, 'Catálogos activos · escenario 2: títulos del catálogo en ESTUDIO_PROFESIONAL (CAL/CAU) y recuperados con su clave',
           r.status_code == 302 and est == [['Licenciatura Ficticia en Administración', 'Licenciatura', 'CAL'],
                                             ['Maestría Ficticia en Dirección de Empresas', 'Maestría', 'CAU']]
           and f.get('clave_licenciatura_catalogo') == 'LIC-00000000000B' and f.get('clave_posgrado_catalogo') == 'MAE-00000000000A'
           and f.get('origen_titulo_origen') == 'catalogo' and f.get('cuenta_con_posgrado') == '1', f'{est} {r.headers.get("Location")}')
    a = c.post('/registro/formacion-academica/autorizar', {'confirmacion_formacion': '1'}, desde='/registro/formacion-academica')
    prueba(9, 'Catálogos activos · escenario 2 autorizado (4 documentos académicos)', '/registro/documentos-probatorios' in a.headers.get('Location', '')
           and uno("select count(*) from estado_solicitud where esso_id_solicitud = %s and esso_siglas = 'FA'", sol) == '1')

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
