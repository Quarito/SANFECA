#!/usr/bin/env python3
"""
ANFECA · Resumen AGRUPADO de los títulos de posgrado que requieren revisión (apoyo a la aprobación conjunta).

    python3 -I scripts/anfeca/catalogos/resumir_revision.py <matriz_clasificacion_posgrados.xlsx> <salida.xlsx>

- Solo LEE la matriz (se verifica su SHA-256 antes y después); nunca la modifica.
- Agrupa por familia académica (área de estudio original, columna A), carrera genérica (B) y carrera específica (C);
  cada grupo se divide en SUBGRUPOS por área propuesta, de modo que cada fila de «Grupos» es homogénea y puede
  decidirse en conjunto. La hoja «Por tema» cruza los temas interdisciplinarios con el área propuesta.
- NO aprueba nada: las columnas «Decisión conjunta» y «Área autorizada» se entregan vacías.
- La salida contiene nombres del catálogo: se guarda FUERA del repositorio (no se publica).
"""
import hashlib
import sys
from collections import Counter, defaultdict
from pathlib import Path

import openpyxl
from openpyxl.styles import Alignment, Font, PatternFill
from openpyxl.utils import get_column_letter

AREAS = ['Administración', 'Contaduría', 'Informática', 'Negocios Internacionales', 'Otro']
SIN = '(sin dato)'


def sha(p):
    return hashlib.sha256(Path(p).read_bytes()).hexdigest()


def tema(obs):
    for t in str(obs or '').split(';'):
        t = t.strip()
        if t.lower().startswith('tema de revisión:'):
            return t.split(':', 1)[1].strip()
    return ''


def temas(l):
    return ', '.join(sorted({x.strip() for p in l for x in p['tema'].split(',') if x.strip()}))


def leer(matriz):
    ws = openpyxl.load_workbook(matriz, read_only=True)['Matriz']
    filas = ws.iter_rows(values_only=True)
    enc = list(next(filas))
    i = {k: enc.index(k) for k in enc if k}
    pend = []
    for f in filas:
        if f[i['Requiere revisión']] != 'Sí':
            continue
        pend.append({
            'familia': (f[i['Área de estudio original']] or SIN).strip(), 'generica': (f[i['Carrera genérica']] or SIN).strip(),
            'especifica': (f[i['Carrera específica']] or SIN).strip(), 'titulo': f[i['Nombre original del posgrado']],
            'nivel': f[i['Nivel académico']], 'area': f[i['Área ANFECA propuesta']], 'alt': f[i['Área alternativa']] or '',
            'confianza': f[i['Confianza']], 'criterio': f[i['Criterio de clasificación']], 'tema': tema(f[i['Observaciones']]),
            'clave': f[i['Clave estable del catálogo']],
        })
    return pend


def dist(c):
    return ' · '.join(f'{a} {c[a]}' for a in AREAS if c.get(a))


def estilo(ws, anchos):
    for n, a in enumerate(anchos, 1):
        ws.column_dimensions[get_column_letter(n)].width = a
    for c in ws[1]:
        c.font = Font(bold=True, color='FFFFFF')
        c.fill = PatternFill('solid', fgColor='9E1B32')
        c.alignment = Alignment(wrap_text=True, vertical='center')
    ws.freeze_panes = 'A2'
    ws.auto_filter.ref = ws.dimensions
    for fila in ws.iter_rows(min_row=2):
        for c in fila:
            c.alignment = Alignment(wrap_text=True, vertical='top')


def main(matriz, salida):
    huella = sha(matriz)
    pend = leer(matriz)
    grupos = defaultdict(list)
    for p in pend:
        grupos[(p['familia'], p['generica'], p['especifica'])].append(p)
    orden = sorted(grupos, key=lambda k: (-len(grupos[k]), k))
    ids = {k: f'G{n:03d}' for n, k in enumerate(orden, 1)}
    subgrupos = []   # (id, clave de grupo, área propuesta, títulos)
    for k in orden:
        por_area = defaultdict(list)
        for p in grupos[k]:
            por_area[p['area']].append(p)
        for j, (a, l) in enumerate(sorted(por_area.items(), key=lambda x: (-len(x[1]), x[0]))):
            sid = ids[k] + ('' if len(por_area) == 1 else chr(ord('a') + j))
            subgrupos.append((sid, k, a, l))
            for p in l:
                p['sub'] = sid

    wb = openpyxl.Workbook()
    ins = wb.active
    ins.title = 'Instrucciones'
    for linea in [
        ['Resumen agrupado de títulos que requieren revisión (catálogo ANUIES 2025, clasificación administrativa).'],
        [f'Títulos pendientes: {len(pend)} · grupos: {len(grupos)} · subgrupos homogéneos (filas de «Grupos»): {len(subgrupos)} · familias: {len({k[0] for k in grupos})}'],
        [f'Matriz de origen (sin modificar): SHA-256 {huella}'],
        ['La clasificación es administrativa: no es equivalencia académica ni criterio de elegibilidad. «Otro» no significa «no elegible».'],
        ['Para aprobar en conjunto: en «Grupos», escriba «Aprobar propuesta», «Aprobar con área autorizada» o «Revisar individualmente».'],
        ['Cada fila de «Grupos» es un subgrupo con UNA sola área propuesta (p. ej., G001a, G001b): puede aprobarse o corregirse en bloque.'],
        ['Para revisar título por título, filtre la hoja «Detalle» por «Subgrupo».'],
        ['Nada se aprueba automáticamente: las columnas de decisión se entregan vacías.'],
    ]:
        ins.append(linea)
    ins.column_dimensions['A'].width = 140

    fam = wb.create_sheet('Familias')
    fam.append(['Familia académica (área de estudio)', 'Títulos', 'Grupos', 'Áreas propuestas', 'Temas de revisión', 'Confianza baja'])
    porfam = defaultdict(list)
    for p in pend:
        porfam[p['familia']].append(p)
    for f_, l in sorted(porfam.items(), key=lambda x: -len(x[1])):
        fam.append([f_, len(l), sum(1 for k in grupos if k[0] == f_), dist(Counter(p['area'] for p in l)),
                    ', '.join(f'{t} {n}' for t, n in Counter(x.strip() for p in l for x in p['tema'].split(',') if x.strip()).most_common()),
                    sum(1 for p in l if p['confianza'] == 'baja')])
    estilo(fam, [45, 10, 10, 60, 60, 12])

    gr = wb.create_sheet('Grupos')
    gr.append(['Subgrupo', 'Familia académica', 'Carrera genérica', 'Carrera específica', 'Área propuesta', 'Títulos', 'Títulos del grupo',
               'Niveles', 'Áreas alternativas', 'Temas de revisión', 'Confianza (alta/media/baja)', 'Ejemplos',
               'Decisión conjunta', 'Área autorizada', 'Observaciones del revisor'])
    for sid, k, a, l in subgrupos:
        conf = Counter(p['confianza'] for p in l)
        gr.append([sid, *k, a, len(l), len(grupos[k]), dist_niveles(l), dist(Counter(p['alt'] for p in l if p['alt'])),
                   temas(l), f"{conf['alta']}/{conf['media']}/{conf['baja']}", '\n'.join(sorted(p['titulo'] for p in l)[:3]), None, None, None])
    estilo(gr, [10, 28, 28, 34, 20, 8, 9, 22, 30, 28, 12, 60, 18, 18, 30])

    te = wb.create_sheet('Por tema')
    te.append(['Tema de revisión', 'Títulos'] + AREAS)
    porTema = defaultdict(Counter)
    for p in pend:
        for x in (p['tema'].split(',') if p['tema'] else ['(sin tema: confianza baja o señales en conflicto)']):
            porTema[x.strip()][p['area']] += 1
    for t, c in sorted(porTema.items(), key=lambda x: -sum(x[1].values())):
        te.append([t, sum(c.values())] + [c.get(a, 0) for a in AREAS])
    estilo(te, [45, 10, 16, 14, 14, 16, 10])

    de = wb.create_sheet('Detalle')
    de.append(['Subgrupo', 'Clave estable', 'Nivel', 'Nombre original del posgrado', 'Familia académica', 'Carrera genérica', 'Carrera específica',
               'Área propuesta', 'Área alternativa', 'Confianza', 'Tema de revisión', 'Criterio'])
    for k in orden:
        for p in sorted(grupos[k], key=lambda p: (p['nivel'], p['titulo'])):
            de.append([p['sub'], p['clave'], p['nivel'], p['titulo'], *k, p['area'], p['alt'], p['confianza'], p['tema'], p['criterio']])
    estilo(de, [8, 18, 13, 60, 28, 28, 30, 20, 20, 10, 22, 50])

    wb.save(salida)
    Path(salida).chmod(0o600)
    if sha(matriz) != huella:
        sys.exit('ERROR: la matriz cambió durante la lectura')
    total_grupos = sum(len(v) for v in grupos.values())
    assert total_grupos == len(pend)
    assert sum(len(x[3]) for x in subgrupos) == len(pend)
    print(f'pendientes={len(pend)} grupos={len(grupos)} subgrupos={len(subgrupos)} familias={len(porfam)} '
          f'subgrupos_de_5_o_mas={sum(1 for x in subgrupos if len(x[3]) >= 5)} (cubren {sum(len(x[3]) for x in subgrupos if len(x[3]) >= 5)} títulos) '
          f'matriz_sin_cambios={huella[:16]}')


def dist_niveles(l):
    c = Counter(p['nivel'] for p in l)
    return ' · '.join(f'{n} {c[n]}' for n in ('Especialidad', 'Maestría', 'Doctorado') if c.get(n))


if __name__ == '__main__':
    if len(sys.argv) != 3:
        sys.exit(__doc__)
    main(sys.argv[1], sys.argv[2])
