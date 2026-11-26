#!/usr/bin/env python3
"""
ANFECA · Catálogo PRELIMINAR de denominaciones de licenciatura (Anuario Estadístico ANUIES) y clasificación
ADMINISTRATIVA propuesta en las 5 áreas del sistema.

    python3 -I scripts/anfeca/catalogos/clasificar_licenciaturas.py <dir_fuentes_licenciaturas> <ciclo>
    python3 -I scripts/anfeca/catalogos/clasificar_licenciaturas.py <dir_fuentes_licenciaturas> <ciclo> --consolidar <dir_historico>

Modo --consolidar: une el ciclo actual con los ciclos históricos agrupados (2000-2001 a 2024-2025) del directorio
indicado. Conserva las claves (mismo nombre normalizado → misma clave), registra los ciclos de aparición y clasifica
con el campo detallado del ciclo MÁS RECIENTE en que aparece la denominación (taxonomías antiguas como contexto débil).
Salidas adicionales: licenciaturas_anuies_consolidado.json · matriz_clasificacion_licenciaturas_consolidada.xlsx ·
manifiesto_licenciaturas_consolidado.json (las salidas del ciclo actual no se modifican).

Entradas (sin modificar; obtenidas del servicio público de exportación del Anuario, ver manifiesto):
  anuies_exportReportRows_<ciclo>_programa_nivel_campos.json              (NDJSON; agrupado por nivel, campos y carrera)
  anuies_exportReportRows_<ciclo>_institucion_programa_nivel_campos.json  (NDJSON; además por institución: trazabilidad)
Salidas (en el mismo directorio; FUERA del repositorio; permisos 600):
  licenciaturas_anuies_<ciclo>.json · matriz_clasificacion_licenciaturas.xlsx · manifiesto_licenciaturas.json

Reglas:
- Solo niveles LICENCIATURA UNIVERSITARIA Y TECNOLÓGICA y LICENCIATURA EN EDUCACIÓN NORMAL (filtro aplicado en la consulta).
- Denominación única = nombre normalizado (sin acentos, mayúsculas, puntuación ni espacios extra). El nombre original
  se conserva (forma más frecuente) con sus variantes; nunca se fusionan denominaciones distintas.
- Se conservan programas sin matrícula activa si constan en la fuente.
- Clasificación por señales del NOMBRE (principal) y del CAMPO DETALLADO ANUIES (contexto); nunca por una sola
  palabra: exclusiones, prioridades y conflictos nombre/campo → «Requiere revisión».
- La clasificación es administrativa: no es equivalencia normativa ni criterio de elegibilidad; «Otro» ≠ no elegible.
"""
import hashlib
import json
import re
import sys
import unicodedata
from collections import Counter, defaultdict
from datetime import datetime, timezone
from pathlib import Path

import openpyxl
from openpyxl.styles import Alignment, Font, PatternFill
from openpyxl.utils import get_column_letter

VERSION_REGLAS = 'lic-reglas-1.2'
AREAS = ['Administración', 'Contaduría', 'Informática', 'Negocios Internacionales', 'Otro']
ADM, CON, INF, NEG, OTR = AREAS
# Equivalencias ACTUALES del sistema (config/anfeca_equivalencias.php): área del título → área de certificación.
EQUIVALENCIA_ACTUAL = {CON: 'Contaduría Pública', ADM: 'Administración', INF: 'Informática Administrativa',
                       NEG: 'Negocios Internacionales', OTR: 'Disciplinas vinculadas al ámbito económico-administrativo'}
FUNDAMENTO = ('Convocatoria 2026 PTC/MT p. 1 (áreas convocadas) y p. 3, base I.3 (título de licenciatura); '
              'Glosario 2026 p. 9 (requisito de procedencia 1: licenciatura «de las aceptadas en la convocatoria»). '
              'No existe lista oficial de licenciaturas aceptadas ni equivalencias por denominación.')


def normal(t):
    t = unicodedata.normalize('NFKD', t or '').encode('ascii', 'ignore').decode().lower()
    return re.sub(r'\s+', ' ', re.sub(r'[^a-z0-9]+', ' ', t)).strip()


def clave(n):
    return 'LIC-' + hashlib.sha256(n.encode()).hexdigest()[:12].upper()


# --- Señales del nombre (peso 3 = fuerte, 2 = media, 1 = débil) ----------------------------------------------
NOMBRE = [
    (r'\bcontadur|\bcontador|\bcontabilidad|\bcontables?\b', CON, 3, 'contaduría/contabilidad'),
    (r'\bauditor', CON, 3, 'auditoría'),
    (r'\b(fiscal|fiscales|tributari|impuestos)\b', CON, 2, 'fiscal/tributario'),
    (r'\binformatica\b', INF, 3, 'informática'),
    (r'\bcomputacion|\bcomputacionales?\b|\bcomputo\b', INF, 3, 'computación'),
    (r'\bsistemas (de informacion|informaticos|computacionales|digitales)\b', INF, 3, 'sistemas de información/computacionales'),
    (r'\btecnologias? de (la )?informacion\b|\btics?\b', INF, 3, 'tecnologías de la información'),
    (r'\bsoftware\b|\bprogramacion\b|\bdesarrollo web\b|\baplicaciones (web|moviles)\b', INF, 3, 'software/programación'),
    (r'\bciberseguridad\b|\bseguridad informatica\b|\btelematica\b', INF, 3, 'ciberseguridad/telemática'),
    (r'\bredes\b(?! sociales)', INF, 2, 'redes'),
    (r'\bciencia de datos\b|\binteligencia artificial\b|\banalitica de datos\b', INF, 2, 'datos/IA'),
    (r'\bnegocios internacionales\b|\bnegocios globales\b', NEG, 3, 'negocios internacionales'),
    (r'\bcomercio (internacional|exterior)\b|\brelaciones comerciales internacionales\b', NEG, 3, 'comercio internacional/exterior'),
    (r'\baduan', NEG, 2, 'aduanas'),
    (r'\binternacional(es)?\b', NEG, 1, 'internacional'),
    (r'\badministracion\b', ADM, 2, 'administración'),
    (r'^(licenciatura|licenciado|licenciada|lic)( en)? administracion( general| de organizaciones)?$', ADM, 3, 'administración (denominación general)'),
    (r'\badministracion (de|y) (empresas|negocios|organizaciones)\b|\badministracion de empresas\b', ADM, 3, 'administración de empresas'),
    (r'\bgestion empresarial\b|\bdireccion de empresas\b|\bemprendimiento\b|\bemprendedor', ADM, 3, 'gestión/dirección de empresas'),
    (r'\brecursos humanos\b|\bcapital humano\b|\btalento humano\b', ADM, 2, 'recursos humanos'),
    (r'\bmercadotecnia\b|\bmarketing\b', ADM, 2, 'mercadotecnia'),
    (r'\bnegocios\b', ADM, 1, 'negocios'),
]
# Dominios ajenos a las cuatro áreas (fuertes): llevan a «Otro» salvo señal fuerte contraria con prioridad.
DOMINIO_OTRO = r'\b(derecho|juridic|psicolog|pedagog|educacion|docencia|ensenanza|economia|economic|actuaria|sociolog|medicina|medico|enfermeria|odontolog|nutricion|arquitectura|diseno|artes?|musica|comunicacion|periodismo|turismo|gastronomia|hotel|agronom|veterinari|biolog|quimic|fisica|matematicas|estadistica|historia|filosofia|letras|idiomas|lengua|traduccion|trabajo social|criminolog|ciencias? politicas?|abogac|relaciones internacionales|antropolog|geograf|deporte|cultura fisica|seguridad publica|salud)'
INGENIERIA = r'\bingenieria\b|\bingeniero\b'
# Temas interdisciplinarios: siempre «Requiere revisión».
TEMAS = [(r'\bfinanz|\bfinanc', 'finanzas'), (r'\beconom', 'economía'), (r'\bactuari', 'actuaría'), (r'\blogistic', 'logística'),
         (r'\bderecho fiscal\b|\bderecho (tributario|financiero)\b', 'derecho fiscal'), (r'\badministracion publica\b|\bgestion publica\b', 'administración pública'),
         (r'\bturis|\bhotel|\bgastronom|\brestauran', 'administración turística'), (r'\bmercadotecnia\b|\bpublicidad\b', 'mercadotecnia'),
         (r'\bingenieria (industrial|en gestion empresarial)\b', 'ingeniería industrial/gestión'), (r'\bciencia de datos\b|\bintelig', 'datos/IA'),
         (r'\badministracion (educativa|escolar|de (la )?(salud|servicios de salud|hospital|instituciones educativas|empresas agropecuarias))\b', 'administración en otro dominio')]

# Campo detallado ANUIES → señal de contexto (peso 1) o tema.
CAMPO = {'Contabilidad y fiscalización': CON, 'Administración de empresas': ADM, 'Mercadotecnia y publicidad': ADM,
         'Negocios y comercio': NEG, 'Administración pública': ADM, 'Administración turística y de restaurantes': ADM,
         'Informática': INF, 'Desarrollo de software': INF, 'Ciencias computacionales': INF,
         'Soporte y servicios de mantenimiento a las tecnologías de la información y comunicación': INF,
         'Planes multidisciplinarios o generales del campo de innovación en tecnologías de la información y la comunicación': INF,
         'Planes multidisciplinarios o generales del campo de implementación de las tecnologías  de la información y la comunicación': INF,
         'Planes multidisciplinarios o generales del campo de administración y gestión': ADM,
         'Planes multidisciplinarios o generales del campo de negocios y contabilidad': ADM,
         # Taxonomía anterior (ciclos 2009-2010 a 2015-2016 aprox.): contexto débil.
         'Administración y gestión de empresas': ADM, 'Negocios y administración, programas multidisciplinarios o generales': ADM,
         'Ciencias de la computación': INF, 'Tecnología de la información y la comunicación': INF}


def clasificar(nombre, campos):
    """Devuelve (área, confianza, criterio, alternativa, revisar, temas)."""
    n = normal(nombre)
    puntos, crit = defaultdict(float), defaultdict(list)
    for pat, area, peso, etiqueta in NOMBRE:
        if re.search(pat, n):
            puntos[area] = max(puntos[area], peso) + (0.25 if puntos[area] else 0)
            crit[area].append(etiqueta)
    temas = sorted({t for pat, t in TEMAS if re.search(pat, n)})
    ctx = Counter(CAMPO.get(c['CAMPO_DETALLADO']) for c in campos)
    ctx.pop(None, None)
    areas_campo = set(ctx)
    conflicto_campo = len(areas_campo) > 1
    # «Tecnologías de la información y (la) comunicación(es)» no es el dominio Comunicación.
    dominio = re.search(DOMINIO_OTRO, re.sub(r'informacion y (la )?comunicacion(es)?', 'informacion', n))
    ingenieria = re.search(INGENIERIA, n)

    # Prioridades explícitas.
    if ingenieria and re.search(r'\b(robotic|embebid|mecatronic|electronic|biomedic|proceso|automatizacion|control)', n) and puntos.get(INF):
        return INF, 'media', 'ingeniería con componente computacional en otra disciplina', OTR, True, temas
    if re.search(r'\binformatica administrativa\b', n):
        return INF, 'alta', 'informática administrativa', None, False, temas
    if re.search(r'\bcontadur|\bcontador publico|\bcontabilidad\b', n) and not re.search(r'\bfinanz', n):
        return CON, 'alta', 'contaduría/contabilidad', None, bool(temas) or conflicto_campo, temas
    if re.search(r'\bfinanz', n):
        alt = CON if re.search(r'\bcontadur|\bcontab', n) else CON
        base = CON if re.search(r'\bcontadur|\bcontab', n) else ADM
        return base, 'baja', 'finanzas (tema interdisciplinario: no se asigna automáticamente a Contaduría)', alt if alt != base else ADM, True, temas or ['finanzas']
    if re.search(r'\bactuari', n):
        return OTR, 'media', 'actuaría (disciplina propia; no se asigna a Contaduría)', CON, True, temas
    if re.search(r'\beconom', n) and not re.search(r'\badministracion|\bnegocios|\bcomercio', n):
        return OTR, 'media', 'economía (disciplina propia)', ADM, True, temas
    if ingenieria and not puntos.get(INF, 0) >= 3:
        if re.search(r'\bgestion empresarial\b', n):
            return ADM, 'media', 'ingeniería en gestión empresarial', OTR, True, temas
        if re.search(r'\bindustrial\b', n):
            return OTR, 'media', 'ingeniería industrial (no es un área base)', ADM, True, temas
        if re.search(r'\blogistic', n):
            return OTR, 'baja', 'ingeniería en logística', NEG, True, temas
        return OTR, 'alta', 'ingeniería de otra disciplina (no todas las ingenierías son Informática)', None, False, temas
    if dominio and max(puntos.values() or [0]) < 3:
        # Ej.: «Administración de servicios de salud», «Turismo»: dominio ajeno con señal administrativa débil o media.
        alt = max(puntos, key=puntos.get) if puntos else None
        return OTR, 'media' if alt else 'alta', f'disciplina: {dominio.group(1)}', alt, bool(alt) or bool(temas), temas

    if puntos:
        orden = sorted(puntos.items(), key=lambda x: -x[1])
        a1, p1 = orden[0]
        a2 = orden[1][0] if len(orden) > 1 else None
        p2 = orden[1][1] if len(orden) > 1 else 0
        texto = '; '.join(crit[a1])
        contradice_campo = bool(areas_campo) and a1 not in areas_campo
        if p1 - p2 < 0.5 and a2:
            return a1, 'baja', f'{texto} / {"; ".join(crit[a2])}', a2, True, temas
        if dominio:
            return a1, 'media', f'{texto} (con dominio {dominio.group(1)})', OTR, True, temas
        revisar = bool(temas) or contradice_campo or conflicto_campo or p1 < 3
        conf = 'alta' if p1 >= 3 and not revisar else ('media' if p1 >= 2 else 'baja')
        alt = a2 or (next(iter(areas_campo - {a1}), None) if contradice_campo else None)
        return a1, conf, texto + (' (campo ANUIES distinto)' if contradice_campo else ''), alt, revisar, temas

    if areas_campo:
        a = ctx.most_common(1)[0][0]
        return a, 'baja', 'sin señal en el nombre; campo detallado ANUIES', OTR, True, temas
    return OTR, 'alta', 'sin relación con las cuatro áreas base (nombre y campo)', None, bool(temas), temas


def leer(ruta):
    meta, filas = None, []
    with open(ruta, encoding='utf-8') as f:
        for i, linea in enumerate(f, 1):
            o = json.loads(linea)
            if o.get('type') == 'meta':
                meta = o
            elif o.get('type') == 'row':
                o['row']['_linea'] = i
                filas.append(o['row'])
            elif o.get('type') == 'done':
                assert o['rows'] == len(filas), 'conteo de filas distinto al declarado por la fuente'
    return meta, filas


def main(directorio, ciclo):
    d = Path(directorio)
    agr = d / f'anuies_exportReportRows_{ciclo}_programa_nivel_campos.json'
    ins = d / f'anuies_exportReportRows_{ciclo}_institucion_programa_nivel_campos.json'
    _, filas = leer(agr)
    _, filas_inst = leer(ins)

    grupos = defaultdict(list)
    for r in filas:
        r['CARRERA'] = r['CARRERA']
        grupos[normal(r['CARRERA'])].append(r)
    inst = defaultdict(set)
    for r in filas_inst:
        inst[normal(r['CARRERA'])].add(r['NOMBRE_INSTITUCION'].strip())

    registros = []
    for n, rs in grupos.items():
        formas = Counter(r['CARRERA'].strip() for r in rs)
        original = formas.most_common(1)[0][0]
        campos = [{k: r[k] for k in ('CAMPO_AMPLIO', 'CAMPO_ESPECIFICO', 'CAMPO_DETALLADO')} for r in rs]
        combos = sorted({(c['CAMPO_AMPLIO'], c['CAMPO_ESPECIFICO'], c['CAMPO_DETALLADO']) for c in campos})
        area, conf, crit, alt, revisar, temas = clasificar(original, campos)
        if len({c[0] for c in combos}) > 1:
            revisar = True
        if revisar and conf == 'alta':
            conf = 'media'
        obs = []
        if len(combos) > 1:
            obs.append(f'conflicto de campos ANUIES ({len(combos)} combinaciones)')
        if len(formas) > 1:
            obs.append('variantes de escritura: ' + ' | '.join(sorted(formas)))
        if temas:
            obs.append('tema de revisión: ' + ', '.join(temas))
        suma = lambda k: sum(int(r[k] or 0) for r in rs)
        registros.append({
            'clave': clave(n), 'titulo': original, 'normalizado': n,
            'niveles': sorted({r['NIVEL'] for r in rs}), 'campos': [list(c) for c in combos],
            'area': area, 'area_alternativa': alt if alt != area else None, 'confianza': conf,
            'requiere_revision': bool(revisar or conf == 'baja'), 'criterio': crit, 'observaciones': '; '.join(obs) or None,
            'matricula': suma('MAT_TOTAL'), 'nuevo_ingreso': suma('NI'), 'egresados': suma('E'), 'titulados': suma('T'),
            'instituciones': len(inst.get(n, ())), 'lineas_origen': [r['_linea'] for r in rs],
        })
    claves = Counter(r['clave'] for r in registros)
    if any(v > 1 for v in claves.values()):
        sys.exit('DETENIDO: colisión de claves estables')
    registros.sort(key=lambda r: r['normalizado'])

    version = f'ANUIES-{ciclo}-LIC-PRELIMINAR'
    catalogo = {'version': version, 'reglas': VERSION_REGLAS, 'ciclo': ciclo, 'areas': AREAS,
                'aviso': 'Catálogo PRELIMINAR de denominaciones (Anuario Estadístico ANUIES). Clasificación administrativa propuesta: '
                         'no es equivalencia normativa ni criterio de elegibilidad; requiere aprobación.',
                'titulos': [{k: r[k] for k in ('clave', 'titulo', 'niveles', 'area', 'confianza', 'requiere_revision', 'campos')} for r in registros]}
    js = d / f'licenciaturas_anuies_{ciclo}.json'
    js.write_text(json.dumps(catalogo, ensure_ascii=False, indent=1), encoding='utf-8')
    xl = d / 'matriz_clasificacion_licenciaturas.xlsx'
    escribir_matriz(xl, registros, filas_inst, ciclo, agr, ins)
    manifiesto = {
        'version': version, 'reglas': VERSION_REGLAS, 'ciclo': ciclo, 'generado': datetime.now(timezone.utc).isoformat(timespec='seconds'),
        'fuentes': {p.name: {'sha256': hashlib.sha256(p.read_bytes()).hexdigest(), 'bytes': p.stat().st_size} for p in (agr, ins)},
        'filas_fuente_agrupada': len(filas), 'filas_fuente_institucion': len(filas_inst),
        'denominaciones_exactas': len({r['CARRERA'].strip() for r in filas}), 'denominaciones_unicas_normalizadas': len(registros),
        'por_area': dict(Counter(r['area'] for r in registros)), 'requieren_revision': sum(r['requiere_revision'] for r in registros),
        'confianza': dict(Counter(r['confianza'] for r in registros)),
        'conflictos_campo': sum(1 for r in registros if len(r['campos']) > 1),
        'sin_matricula_ni_egresos': sum(1 for r in registros if not any((r['matricula'], r['nuevo_ingreso'], r['egresados'], r['titulados']))),
        'salidas': {p.name: hashlib.sha256(p.read_bytes()).hexdigest() for p in (js, xl)},
    }
    mf = d / 'manifiesto_licenciaturas.json'
    mf.write_text(json.dumps(manifiesto, ensure_ascii=False, indent=1), encoding='utf-8')
    for p in (js, xl, mf):
        p.chmod(0o600)
    print(json.dumps({k: manifiesto[k] for k in ('filas_fuente_agrupada', 'filas_fuente_institucion', 'denominaciones_exactas',
                      'denominaciones_unicas_normalizadas', 'por_area', 'requieren_revision', 'confianza', 'conflictos_campo',
                      'sin_matricula_ni_egresos')}, ensure_ascii=False))


COLUMNAS = ['Núm.', 'Nombre original de la licenciatura', 'Nombre normalizado para búsqueda', 'Nivel académico', 'Campo amplio ANUIES',
            'Campo específico ANUIES', 'Campo detallado ANUIES', 'Ciclo escolar', 'Área ANFECA propuesta', 'Área alternativa',
            'Equivalencia actualmente configurada', 'Fundamento normativo', 'Confianza', 'Requiere revisión', 'Clasificación autorizada',
            'Observaciones', 'Clave estable', 'Referencia al registro original', 'Instituciones', 'Matrícula', 'Egresados', 'Titulados', 'Criterio']


def fila(i, r, ciclo, agr):
    c = r['campos']
    una = lambda k: c[0][k] if len(c) == 1 else ' || '.join(sorted({x[k] for x in c}))
    return [i, r['titulo'], r['normalizado'], ' / '.join(r['niveles']), una(0), una(1), una(2), ciclo, r['area'], r['area_alternativa'],
            f"«{r['area']}» → certificación {EQUIVALENCIA_ACTUAL[r['area']]}", FUNDAMENTO, r['confianza'],
            'Sí' if r['requiere_revision'] else 'No', None, r['observaciones'], r['clave'],
            f"{agr.name} líneas {', '.join(map(str, r['lineas_origen']))}", r['instituciones'], r['matricula'], r['egresados'], r['titulados'], r['criterio']]


def estilo(ws, anchos):
    for i, a in enumerate(anchos, 1):
        ws.column_dimensions[get_column_letter(i)].width = a
    for c in ws[1]:
        c.font = Font(bold=True, color='FFFFFF'); c.fill = PatternFill('solid', fgColor='9E1B32')
        c.alignment = Alignment(wrap_text=True, vertical='center')
    ws.freeze_panes = 'C2'; ws.auto_filter.ref = ws.dimensions


def escribir_matriz(ruta, registros, filas_inst, ciclo, agr, ins):
    wb = openpyxl.Workbook()
    res = wb.active; res.title = 'Resumen'
    res.append(['Catálogo PRELIMINAR de licenciaturas · Anuario Estadístico ANUIES ' + ciclo])
    res.append(['Clasificación administrativa propuesta; NO es equivalencia normativa ni criterio de elegibilidad. «Otro» no significa no elegible.'])
    res.append(['La columna «Clasificación autorizada» se entrega vacía: requiere aprobación expresa.'])
    res.append([]); res.append(['Área', 'Denominaciones', 'Requieren revisión', 'Alta', 'Media', 'Baja'])
    for a in AREAS:
        l = [r for r in registros if r['area'] == a]
        res.append([a, len(l), sum(r['requiere_revision'] for r in l)] + [sum(1 for r in l if r['confianza'] == x) for x in ('alta', 'media', 'baja')])
    res.append(['Total', len(registros), sum(r['requiere_revision'] for r in registros)])
    res.column_dimensions['A'].width = 40
    anchos = [7, 55, 45, 30, 26, 30, 34, 11, 20, 20, 40, 50, 9, 9, 16, 50, 18, 40, 10, 10, 10, 10, 45]
    for nombre, filtro in [('Matriz completa', lambda r: True)] + [(a, (lambda a: lambda r: r['area'] == a)(a)) for a in AREAS] + \
            [('Casos ambiguos', lambda r: r['requiere_revision'])]:
        ws = wb.create_sheet(nombre[:31]); ws.append(COLUMNAS)
        for i, r in enumerate([r for r in registros if filtro(r)], 1):
            ws.append(fila(i, r, ciclo, agr))
        estilo(ws, anchos)
    eq = wb.create_sheet('Equivalencias actuales')
    eq.append(['Área de certificación (SeleccionCertificacion.vue)', 'Área del título de origen admitida (config/anfeca_equivalencias.php)',
               'Denominaciones ANUIES propuestas en esa área', 'De ellas, requieren revisión', 'Observación'])
    for a, cert in EQUIVALENCIA_ACTUAL.items():
        l = [r for r in registros if r['area'] == a]
        eq.append([cert, a, len(l), sum(r['requiere_revision'] for r in l),
                   'Hoy el sistema solo guarda el ÁREA del título; el nombre exacto solo se captura como texto libre en «Otro».'])
    estilo(eq, [45, 40, 18, 18, 70])
    fu = wb.create_sheet('Fuentes')
    fu.append(['Fuente', 'Detalle'])
    for k, v in [('Institución', 'ANUIES · Anuario Estadístico de Educación Superior (https://anuario.anuies.mx/)'),
                 ('Método', 'Servicio público de exportación que usa la propia interfaz (POST action=exportReportRows); sin credenciales'),
                 ('Ciclo', ciclo), ('Filtros', 'Niveles: Licenciatura universitaria y tecnológica; Licenciatura en educación normal. Todas las entidades, '
                                              'municipios, sostenimientos, instituciones (asociadas y no asociadas a ANUIES), modalidades y campos. '
                                              'Variables: matrícula, nuevo ingreso, egresados y titulados'),
                 ('Archivo agrupado', f'{agr.name} · SHA-256 {hashlib.sha256(agr.read_bytes()).hexdigest()}'),
                 ('Archivo por institución', f'{ins.name} · SHA-256 {hashlib.sha256(ins.read_bytes()).hexdigest()}'),
                 ('Condiciones de uso', '© ANUIES — Todos los derechos reservados (pie del Anuario). Uso interno; no redistribuir sin autorización'),
                 ('Fundamento normativo', FUNDAMENTO)]:
        fu.append([k, v])
    estilo(fu, [25, 140])
    tr = wb.create_sheet('Registros por institución')
    tr.append(['Clave estable', 'Institución', 'Nivel', 'Campo detallado', 'Carrera (original)', 'Matrícula', 'Línea en archivo por institución'])
    for r in filas_inst:
        tr.append([clave(normal(r['CARRERA'])), r['NOMBRE_INSTITUCION'].strip(), r['NIVEL'], r['CAMPO_DETALLADO'], r['CARRERA'].strip(),
                   int(r['MAT_TOTAL'] or 0), r['_linea']])
    estilo(tr, [18, 60, 30, 40, 60, 10, 12])
    wb.save(ruta)


def consolidar(directorio, ciclo, dir_historico):
    d, h = Path(directorio), Path(dir_historico)
    actual = d / f'anuies_exportReportRows_{ciclo}_programa_nivel_campos.json'
    archivos = sorted(h.glob('anuies_exportReportRows_*_programa_nivel_campos.json')) + [actual]
    porn = defaultdict(lambda: {'formas': Counter(), 'ciclos': set(), 'filas': [], 'niveles': set()})
    huellas = {}
    for f in archivos:
        cic = f.name.split('_')[2]
        _, filas = leer(f)
        huellas[f.name] = {'sha256': hashlib.sha256(f.read_bytes()).hexdigest(), 'filas': len(filas), 'ciclo': cic}
        for r in filas:
            k = normal(r['CARRERA'])
            if not k:
                continue
            g = porn[k]
            g['formas'][r['CARRERA'].strip()] += 1
            g['ciclos'].add(cic)
            g['niveles'].add(r['NIVEL'])
            g['filas'].append((cic, r))
    registros = []
    for k, g in porn.items():
        ciclos = sorted(g['ciclos'])
        # Campos del ciclo más reciente con campo detallado informado.
        con_campo = sorted([(c, r) for c, r in g['filas'] if r.get('CAMPO_DETALLADO')], key=lambda x: x[0])
        reciente = con_campo[-1][0] if con_campo else None
        campos = [{x: r[x] for x in ('CAMPO_AMPLIO', 'CAMPO_ESPECIFICO', 'CAMPO_DETALLADO')} for c, r in con_campo if c == reciente]
        original = g['formas'].most_common(1)[0][0]
        area, conf, crit, alt, revisar, temas = clasificar(original, campos)
        obs = []
        combos = sorted({(c['CAMPO_AMPLIO'], c['CAMPO_ESPECIFICO'], c['CAMPO_DETALLADO']) for c in campos})
        if len({c[0] for c in combos}) > 1:
            revisar = True
            obs.append(f'conflicto de campos ANUIES ({len(combos)} combinaciones)')
        if not campos:
            obs.append('sin campo de formación en la fuente (solo nombre)')
            if area != OTR or alt:
                revisar = True
        if ciclo not in ciclos:
            obs.append(f'histórica: no aparece en {ciclo}')
        if len(g['formas']) > 1:
            obs.append('variantes de escritura: ' + ' | '.join(sorted(g['formas'])[:6]) + (' …' if len(g['formas']) > 6 else ''))
        if temas:
            obs.append('tema de revisión: ' + ', '.join(temas))
        if revisar and conf == 'alta':
            conf = 'media'
        registros.append({'clave': clave(k), 'titulo': original, 'normalizado': k, 'niveles': sorted(g['niveles']),
                          'campos': [list(c) for c in combos], 'campo_ciclo': reciente, 'ciclos': ciclos,
                          'primer_ciclo': ciclos[0], 'ultimo_ciclo': ciclos[-1], 'vigente': ciclo in ciclos,
                          'area': area, 'area_alternativa': alt if alt != area else None, 'confianza': conf,
                          'requiere_revision': bool(revisar or conf == 'baja'), 'criterio': crit, 'observaciones': '; '.join(obs) or None})
    if any(v > 1 for v in Counter(r['clave'] for r in registros).values()):
        sys.exit('DETENIDO: colisión de claves estables')
    previo = json.loads((d / f'licenciaturas_anuies_{ciclo}.json').read_text(encoding='utf-8'))
    claves_previas = {t['clave'] for t in previo['titulos']}
    nuevas = {r['clave'] for r in registros}
    if not claves_previas <= nuevas:
        sys.exit('DETENIDO: el consolidado perdería claves del catálogo del ciclo actual')
    registros.sort(key=lambda r: r['normalizado'])
    version = f'ANUIES-2000-{ciclo[-4:]}-LIC-PRELIMINAR'
    catalogo = {'version': version, 'reglas': VERSION_REGLAS, 'ciclos': sorted({v['ciclo'] for v in huellas.values()}), 'areas': AREAS,
                'aviso': 'Catálogo PRELIMINAR consolidado de denominaciones (Anuario Estadístico ANUIES, 2000-2001 a ' + ciclo + '). '
                         'Clasificación administrativa propuesta: no es equivalencia normativa ni criterio de elegibilidad.',
                'titulos': [{x: r[x] for x in ('clave', 'titulo', 'niveles', 'area', 'confianza', 'requiere_revision', 'campos', 'primer_ciclo', 'ultimo_ciclo')}
                            for r in registros]}
    js = d / 'licenciaturas_anuies_consolidado.json'
    js.write_text(json.dumps(catalogo, ensure_ascii=False, indent=1), encoding='utf-8')
    xl = d / 'matriz_clasificacion_licenciaturas_consolidada.xlsx'
    wb = openpyxl.Workbook(); res = wb.active; res.title = 'Resumen'
    res.append([f'Catálogo PRELIMINAR consolidado · ANUIES 2000-2001 a {ciclo} · {len(registros)} denominaciones'])
    res.append(['Clasificación administrativa propuesta; «Clasificación autorizada» vacía; requiere aprobación.'])
    res.append([]); res.append(['Área', 'Denominaciones', 'Vigentes en ' + ciclo, 'Solo históricas', 'Requieren revisión'])
    for a in AREAS:
        l = [r for r in registros if r['area'] == a]
        res.append([a, len(l), sum(r['vigente'] for r in l), sum(not r['vigente'] for r in l), sum(r['requiere_revision'] for r in l)])
    cols = ['Núm.', 'Nombre original de la licenciatura', 'Nombre normalizado para búsqueda', 'Nivel académico', 'Campo amplio ANUIES',
            'Campo específico ANUIES', 'Campo detallado ANUIES', 'Ciclo del campo', 'Ciclos de origen', 'Primer ciclo', 'Último ciclo',
            'Área ANFECA propuesta', 'Área alternativa', 'Equivalencia actualmente configurada', 'Fundamento normativo', 'Confianza',
            'Requiere revisión', 'Clasificación autorizada', 'Observaciones', 'Clave estable', 'Criterio']
    for nombre, filtro in [('Matriz completa', lambda r: True)] + [(a, (lambda a: lambda r: r['area'] == a)(a)) for a in AREAS] + \
            [('Casos ambiguos', lambda r: r['requiere_revision']), ('Solo históricas', lambda r: not r['vigente'])]:
        ws = wb.create_sheet(nombre[:31]); ws.append(cols)
        for i, r in enumerate([r for r in registros if filtro(r)], 1):
            una = lambda j: ' || '.join(sorted({c[j] or '' for c in r['campos']})) if r['campos'] else ''
            ws.append([i, r['titulo'], r['normalizado'], ' / '.join(r['niveles']), una(0), una(1), una(2), r['campo_ciclo'], ', '.join(r['ciclos']),
                       r['primer_ciclo'], r['ultimo_ciclo'], r['area'], r['area_alternativa'], f"«{r['area']}» → certificación {EQUIVALENCIA_ACTUAL[r['area']]}",
                       FUNDAMENTO, r['confianza'], 'Sí' if r['requiere_revision'] else 'No', None, r['observaciones'], r['clave'], r['criterio']])
        estilo(ws, [7, 55, 45, 30, 24, 28, 32, 11, 40, 11, 11, 20, 20, 40, 50, 9, 9, 16, 50, 18, 45])
    fu = wb.create_sheet('Fuentes'); fu.append(['Archivo', 'Ciclo', 'Filas', 'SHA-256'])
    for nombre, v in sorted(huellas.items(), key=lambda x: x[1]['ciclo']):
        fu.append([nombre, v['ciclo'], v['filas'], v['sha256']])
    estilo(fu, [70, 12, 10, 70])
    wb.save(xl)
    man = {'version': version, 'reglas': VERSION_REGLAS, 'generado': datetime.now(timezone.utc).isoformat(timespec='seconds'), 'fuentes': huellas,
           'denominaciones': len(registros), 'vigentes': sum(r['vigente'] for r in registros), 'solo_historicas': sum(not r['vigente'] for r in registros),
           'por_area': dict(Counter(r['area'] for r in registros)), 'requieren_revision': sum(r['requiere_revision'] for r in registros),
           'confianza': dict(Counter(r['confianza'] for r in registros)), 'sin_campo': sum(1 for r in registros if not r['campos']),
           'claves_del_ciclo_actual_conservadas': len(claves_previas),
           'salidas': {p.name: hashlib.sha256(p.read_bytes()).hexdigest() for p in (js, xl)}}
    mf = d / 'manifiesto_licenciaturas_consolidado.json'
    mf.write_text(json.dumps(man, ensure_ascii=False, indent=1), encoding='utf-8')
    for p in (js, xl, mf):
        p.chmod(0o600)
    print(json.dumps({k: man[k] for k in ('denominaciones', 'vigentes', 'solo_historicas', 'por_area', 'requieren_revision', 'confianza', 'sin_campo',
                                            'claves_del_ciclo_actual_conservadas')}, ensure_ascii=False))


if __name__ == '__main__':
    if len(sys.argv) == 5 and sys.argv[3] == '--consolidar':
        consolidar(sys.argv[1], sys.argv[2], sys.argv[4])
    elif len(sys.argv) == 3:
        main(sys.argv[1], sys.argv[2])
    else:
        sys.exit(__doc__)
