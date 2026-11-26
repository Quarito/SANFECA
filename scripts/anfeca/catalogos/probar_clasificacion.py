#!/usr/bin/env python3
"""
ANFECA · Pruebas del clasificador de posgrados (sin datos del catálogo: títulos de ejemplo).

    python3 -I scripts/anfeca/catalogos/probar_clasificacion.py [<directorio_salidas> <excel_fuente>]

Sin argumentos: casos obligatorios de clasificación, temas de revisión y claves estables.
Con argumentos (solo en el servidor, nunca en GitHub): consistencia JSON ↔ matriz ↔ manifiesto y reproducibilidad.
"""
import hashlib
import json
import sys
import tempfile
from pathlib import Path

sys.dont_write_bytecode = True   # sin __pycache__ en el repositorio
sys.path.insert(0, str(Path(__file__).resolve().parent))
import clasificar_posgrados as c  # noqa: E402

FALLAS = []


def esperar(cond, mensaje):
    print(('  ok  ' if cond else 'FALLA ') + mensaje)
    if not cond:
        FALLAS.append(mensaje)


OBLIGATORIOS = [
    ('Maestría en Administración Tributaria', c.CON),
    ('Maestría en Administración de Tecnologías de la Información', c.INF),
    ('Especialidad en Administración de Redes y Telecomunicaciones', c.INF),
    ('Maestría en Administración de Negocios Internacionales', c.NEG),
    ('Maestría en Administración de Justicia Penal', c.OTR),
    ('Especialidad en Administración de Justicia Civil', c.OTR),
    ('Maestría en Administración de Empresas', c.ADM),
    ('Maestría en Dirección Estratégica de Empresas', c.ADM),
    ('Maestría en Auditoría', c.CON),
    ('Doctorado en Ciencias Computacionales', c.INF),
]
REVISION = ['Maestría en Finanzas', 'Maestría en Economía', 'Maestría en Logística', 'Maestría en Administración Educativa',
            'Especialidad en Derecho Fiscal', 'Especialidad en Prevención de Lavado de Dinero', 'Maestría en Gestión Tecnológica',
            'Maestría en Analítica de Negocios']


def unitarias():
    print('Clasificación obligatoria:')
    for titulo, area in OBLIGATORIOS:
        r = c.clasificar(titulo, '', '')
        esperar(r[0] == area, f'{titulo} → {area} (obtenido: {r[0]}, {r[1]}, {r[2]})')
    print('Temas interdisciplinarios marcados para revisión:')
    for titulo in REVISION:
        r = c.clasificar(titulo, '', '')
        esperar(r[4] is True, f'{titulo} requiere revisión (área propuesta {r[0]}, confianza {r[1]})')
    print('Una sola palabra no decide:')
    esperar(c.clasificar('Maestría en Marketing Digital y Redes Sociales', '', '')[0] != c.INF, '«redes sociales» no es Informática')
    esperar(c.clasificar('Maestría en Administración de Justicia', '', '')[0] == c.OTR, '«Administración de Justicia» no es Administración')
    print('Claves estables:')
    k1 = c.clave_estable('MAE', 'Maestría en Auditoría')
    esperar(k1 == c.clave_estable('MAE', '  MAESTRIA EN AUDITORIA '), 'la clave no depende de acentos, mayúsculas ni espacios')
    esperar(k1 != c.clave_estable('MAE', 'Maestría en Auditoría Fiscal'), 'títulos distintos → claves distintas')
    esperar(k1.startswith('MAE-') and len(k1) == 16, f'formato de clave ({k1})')


def integrales(salidas, excel):
    import openpyxl
    salidas = Path(salidas)
    catalogo = json.loads((salidas / 'posgrados_anuies_2025.json').read_text(encoding='utf-8'))
    manifiesto = json.loads((salidas / 'manifiesto_catalogo.json').read_text(encoding='utf-8'))
    print('Consistencia JSON ↔ matriz ↔ manifiesto:')
    titulos = catalogo['titulos']
    conteo = {n: sum(1 for t in titulos if t['nivel'] == n) for n in ('Especialidad', 'Maestría', 'Doctorado')}
    esperar(conteo == {'Especialidad': 568, 'Maestría': 1000, 'Doctorado': 226}, f'conteos por nivel {conteo}')
    esperar(len({t['clave'] for t in titulos}) == len(titulos) == 1794, 'claves únicas (1794)')
    ws = openpyxl.load_workbook(salidas / 'matriz_clasificacion_posgrados.xlsx', read_only=True)['Matriz']
    filas = list(ws.iter_rows(min_row=2, values_only=True))
    enc = [x for x in next(ws.iter_rows(max_row=1, values_only=True))]
    i = {n: enc.index(n) for n in ('Clave estable del catálogo', 'Nombre original del posgrado', 'Nivel académico', 'Área ANFECA propuesta')}
    i['Clave estable'], i['Nombre'], i['Nivel'] = i['Clave estable del catálogo'], i['Nombre original del posgrado'], i['Nivel académico']
    i['Área propuesta'] = i['Área ANFECA propuesta']
    matriz = {f[i['Clave estable']]: f for f in filas if f[i['Clave estable']]}
    esperar(len(matriz) == len(titulos), f'misma cantidad de registros ({len(matriz)})')
    difs = [t['clave'] for t in titulos if t['clave'] not in matriz or matriz[t['clave']][i['Nombre']] != t['titulo']
            or matriz[t['clave']][i['Área propuesta']] != t['area'] or matriz[t['clave']][i['Nivel']] != t['nivel']]
    esperar(not difs, f'clave, nombre, nivel y área coinciden ({len(difs)} diferencias)')
    pend = sum(1 for t in titulos if t['requiere_revision'])
    esperar(pend == manifiesto['pendientes_revision'] if 'pendientes_revision' in manifiesto else True, f'pendientes de revisión: {pend}')
    esperar(hashlib.sha256((salidas / 'posgrados_anuies_2025.json').read_bytes()).hexdigest() == manifiesto.get('sha256_catalogo'),
            'SHA-256 del catálogo registrado en el manifiesto')
    print('Reproducibilidad:')
    with tempfile.TemporaryDirectory() as tmp:
        c.main(excel, tmp)
        nuevo = json.loads((Path(tmp) / 'posgrados_anuies_2025.json').read_text(encoding='utf-8'))
        esperar(nuevo['titulos'] == titulos, 'una segunda ejecución produce el mismo catálogo')


if __name__ == '__main__':
    unitarias()
    if len(sys.argv) == 3:
        integrales(sys.argv[1], sys.argv[2])
    print(f'\n{"CORRECTO" if not FALLAS else f"{len(FALLAS)} FALLA(S)"}')
    sys.exit(1 if FALLAS else 0)
