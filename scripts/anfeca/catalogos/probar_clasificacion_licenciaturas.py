#!/usr/bin/env python3
"""ANFECA · Pruebas del clasificador de licenciaturas (sin datos de la fuente; nombres de ejemplo)."""
import sys
from pathlib import Path
sys.dont_write_bytecode = True
sys.path.insert(0, str(Path(__file__).resolve().parent))
import clasificar_licenciaturas as c  # noqa: E402

CAMPO = lambda d: [{'CAMPO_AMPLIO': '', 'CAMPO_ESPECIFICO': '', 'CAMPO_DETALLADO': d}]
CASOS = [  # (nombre, campo detallado, área esperada, ¿revisión?)
    ('Licenciatura en Contaduría Pública', 'Contabilidad y fiscalización', c.CON, False),
    ('Licenciatura en Administración', 'Administración de empresas', c.ADM, False),
    ('Licenciatura en Informática Administrativa', 'Informática', c.INF, False),
    ('Ingeniería en Sistemas Computacionales', 'Desarrollo de software', c.INF, False),
    ('Ingeniería en Computación', 'Ciencias computacionales', c.INF, False),
    ('Ingeniería en Tecnologías de la Información y Comunicaciones', 'Informática', c.INF, False),
    ('Licenciatura en Negocios Internacionales', 'Negocios y comercio', c.NEG, False),
    ('Ingeniería Civil', 'Construcción e ingeniería civil', c.OTR, False),            # no todas las ingenierías son Informática
    ('Ingeniería Industrial', 'Ingeniería industrial', c.OTR, True),
    ('Licenciatura en Finanzas', 'Finanzas, banca y seguros', c.ADM, True),           # finanzas: nunca Contaduría automática
    ('Licenciatura en Economía', 'Economía', c.OTR, True),
    ('Licenciatura en Actuaría', 'Estadística y actuaría', c.OTR, True),
    ('Licenciatura en Derecho', 'Derecho', c.OTR, False),
    ('Licenciatura en Psicología', 'Psicología', c.OTR, False),
    ('Licenciatura en Pedagogía', 'Diseño curricular y pedagogía', c.OTR, False),
    ('Licenciatura en Sociología', 'Sociología y antropología', c.OTR, False),
    ('Licenciatura en Mercadotecnia', 'Mercadotecnia y publicidad', c.ADM, True),       # no todo «Administración y negocios» es Administración segura
    ('Licenciatura en Ciencias de la Comunicación', 'Ciencias de la comunicación', c.OTR, False),
    ('Ingeniería en Robótica Computacional', 'Electrónica y automatización', c.INF, True),
]
fallas = 0
for nombre, campo, area, rev in CASOS:
    a, conf, crit, alt, revisar, temas = c.clasificar(nombre, CAMPO(campo))
    revisar = revisar or conf == 'baja'
    ok = a == area and bool(revisar) == rev
    fallas += not ok
    print(('  ok  ' if ok else 'FALLA ') + f'{nombre} → {a} ({conf}, revisión={revisar}) [esperado {area}, revisión={rev}]')
k = c.clave(c.normal('Licenciatura en Contaduría Pública'))
ok = k == c.clave(c.normal('  LICENCIATURA EN CONTADURIA PUBLICA ')) and k.startswith('LIC-') and len(k) == 16
fallas += not ok
print(('  ok  ' if ok else 'FALLA ') + 'clave estable independiente de acentos, mayúsculas y espacios')
print('CORRECTO' if not fallas else f'{fallas} FALLA(S)')
sys.exit(1 if fallas else 0)
