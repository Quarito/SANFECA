#!/usr/bin/env python3
"""
ANFECA · Importación y clasificación ADMINISTRATIVA del catálogo de posgrados (ANUIES 2025).

    python3 scripts/anfeca/catalogos/clasificar_posgrados.py <excel_fuente> <directorio_salida>

- Lee EXCLUSIVAMENTE las hojas ESPECIALIDADES, MAESTRÍAS y DOCTORADOS (nunca «Catálogo Posgrados ANUIES»).
- Conserva los nombres originales (solo se recortan espacios extremos) y los metadatos A–C y la fila de origen.
- Excluye únicamente encabezados, filas sin nombre de posgrado y duplicados exactos (se documentan).
- Clasifica en Administración, Contaduría, Informática, Negocios Internacionales u Otro analizando
  conjuntamente el NOMBRE del título (señal principal), la carrera específica y la carrera genérica.
  Nunca por una sola palabra clave aislada: hay reglas de exclusión, de prioridad y de contexto.
- La clasificación es ADMINISTRATIVA para facilitar la captura; NO es una equivalencia académica oficial
  ni un criterio de elegibilidad. Los casos inciertos se marcan «Requiere revisión».
- Salidas (FUERA del repositorio): posgrados_anuies_2025.json, matriz_clasificacion_posgrados.xlsx,
  manifiesto_catalogo.json. Requiere openpyxl (entorno virtual aislado).
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

VERSION_CATALOGO = 'ANUIES-2025'
VERSION_REGLAS = 'reglas-1.3'
HOJAS = {'ESPECIALIDADES': ('Especialidad', 'ESP'), 'MAESTRÍAS': ('Maestría', 'MAE'), 'DOCTORADOS': ('Doctorado', 'DOC')}
AREAS = ['Administración', 'Contaduría', 'Informática', 'Negocios Internacionales', 'Otro']
ADM, CON, INF, NEG, OTR = AREAS


def normal(texto):
    """Minúsculas sin acentos ni signos, para COMPARAR (nunca para almacenar)."""
    t = unicodedata.normalize('NFKD', str(texto or '')).encode('ascii', 'ignore').decode().lower()
    return re.sub(r'\s+', ' ', re.sub(r'[^a-z0-9]+', ' ', t)).strip()


def sin_prefijo(titulo_n):
    return re.sub(r'^(especialidad(es)?|especializacion|maestria|doctorado|master)\b(\s+(en|de|del|con|para|y)\b)*\s*', '', titulo_n).strip()


# --- Reglas: (patrón sobre el título normalizado sin prefijo, área, peso, criterio) -------------------------
# Pesos: 3 = señal fuerte y específica; 2 = señal clara; 1 = señal débil (requiere contexto).
REGLAS = [
    # Contaduría
    (r'\bcontadur', CON, 3, 'contaduría'), (r'\bcontab', CON, 3, 'contabilidad'), (r'\bauditor', CON, 3, 'auditoría'),
    (r'\bimpuest', CON, 3, 'impuestos'), (r'\btribut', CON, 3, 'tributación'), (r'\bfiscal', CON, 2, 'fiscal'),
    (r'\bcostos\b', CON, 2, 'costos'), (r'\bfinanzas\b|\bfinanciera?s?\b', CON, 1, 'finanzas (relación contable por confirmar)'),
    (r'\blavado de dinero|\bprevencion de operaciones', CON, 1, 'prevención de lavado de dinero'),
    (r'\bnormas? (internacionales )?de informacion financiera|\bniif\b|\bnif\b', CON, 3, 'información financiera'),
    # Informática
    (r'\binformatic', INF, 3, 'informática'), (r'\bcomputa|\bcomputo\b', INF, 3, 'computación'),
    (r'\btecnologias? (de la |de )?informacion|\btic\b|\btics\b', INF, 3, 'tecnologías de información'),
    (r'\bsoftware\b|\bprogramacion\b|\bdesarrollo web\b|\baplicaciones (web|moviles)', INF, 3, 'software'),
    (r'\bciencias? de (los )?datos\b|\bbig data\b|\bmineria de datos\b|\bbases? de datos\b', INF, 3, 'ciencia de datos'),
    (r'\bciberseguridad\b|\bseguridad (informatica|de la informacion|en redes|en tecnologias)', INF, 3, 'seguridad informática'),
    (r'\bredes\b(?! sociales)', INF, 2, 'redes'), (r'\btelecomunicacion', INF, 3, 'telecomunicaciones'),
    (r'\binteligencia artificial\b|\baprendizaje (automatico|de maquina)', INF, 3, 'inteligencia artificial'),
    (r'\bsistemas (computacionales|de informacion|informaticos|de computo)\b', INF, 3, 'sistemas de información'),
    (r'\bingenieria (de|en) sistemas\b|\bsistemas\b', INF, 1, 'sistemas (por confirmar)'),
    (r'\banalitica\b|\binteligencia de negocios\b', INF, 1, 'analítica'),
    (r'\bgestion (de la )?tecnolog|\binnovacion tecnologica\b|\btransformacion digital\b|\bdigital(es)?\b', INF, 1, 'gestión tecnológica / digital'),
    # Negocios internacionales
    (r'\bnegocios internacionales\b', NEG, 3, 'negocios internacionales'),
    (r'\bcomercio (internacional|exterior)\b', NEG, 3, 'comercio internacional/exterior'),
    (r'\baduan', NEG, 3, 'aduanas'), (r'\blogistica internacional\b|\bcadenas? (globales? )?de suministro internacional', NEG, 3, 'logística internacional'),
    (r'\bmercados? internacionales?\b|\bcomercializacion internacional\b|\bmarketing internacional\b|\bmercadotecnia internacional\b', NEG, 3, 'mercados internacionales'),
    (r'\boperaciones internacionales\b|\bnegocios y (operaciones|mercados) internacionales\b', NEG, 3, 'operaciones internacionales'),
    (r'\bnegocios (globales|transnacionales)\b|\bempresas? (internacionales|globales)\b|\bglobal business\b', NEG, 2, 'negocios globales'),
    (r'\blogistica\b|\bcadenas? de (suministro|valor)\b', NEG, 1, 'logística (no necesariamente internacional)'),
    # Administración
    (r'\badministracion (de empresas|de negocios|de organizaciones|organizacional|empresarial|estrategica|integral|general)\b', ADM, 3, 'administración de empresas/organizaciones'),
    (r'\badministracion publica\b|\badministracion gubernamental\b|\bgestion publica\b|\bpoliticas publicas\b|\bgobierno\b', ADM, 2, 'administración pública'),
    (r'\bdireccion (de empresas|estrategica|de negocios|de organizaciones|empresarial|general)\b|\balta direccion\b', ADM, 3, 'dirección estratégica'),
    (r'\brecursos humanos\b|\bcapital humano\b|\btalento humano\b|\bdesarrollo organizacional\b|\bcomportamiento organizacional\b', ADM, 3, 'recursos humanos / desarrollo organizacional'),
    (r'\bmercadotecnia\b|\bmarketing\b|\bmercadeo\b|\bcomercializacion\b|\bventas\b|\bpublicidad\b', ADM, 2, 'mercadotecnia'),
    (r'\b(direccion|gestion|administracion) de proyectos\b|\bproject management\b', ADM, 3, 'dirección de proyectos'),
    (r'\bgestion empresarial\b|\bgerencia\b|\bnegocios\b|\bemprend|\bpymes?\b|\bempresas?\b|\borganizaciones\b|\bcalidad\b|\bmba\b|\bliderazgo\b|\bestrategi', ADM, 2, 'gestión empresarial'),
    (r'\badministracion\b|\bgestion\b|\bdireccion\b', ADM, 1, 'administración (genérica)'),
]

# Exclusiones de dominio: el título pertenece a otra disciplina aunque contenga «administración», «gestión», etc.
OTRO_DOMINIO = [
    (r'\bderecho\b|\bjuridic|\bjusticia\b|\bpenal\b|\bprocesal\b|\bconstitucional\b|\bnotari|\blitigacion\b|\bjuicio', 'derecho'),
    (r'\bcriminal|\bforense\b|\bpolicia|\bseguridad publica\b|\bproteccion civil\b', 'seguridad pública / criminalística'),
    (r'\bpsicolog|\bpsicoterap|\bpsicoanal|\bterapia\b|\bsalud mental\b', 'psicología'),
    (r'\beducacion\b|\beducativ|\bdocencia\b|\bpedagog|\benseñanza\b|\bensenanza\b|\bdidactic|\bcurricul', 'educación'),
    (r'\bcomunicacion\b|\bperiodismo\b|\bmedios\b|\bimagologia\b|\brelaciones publicas\b|\bimagen\b', 'comunicación'),
    (r'\bsalud\b|\bhospital|\bclinic|\bmedic|\benfermer|\bnutric|\bodontolog|\bepidemiolog', 'salud'),
    (r'\barquitect|\burban|\bconstruccion\b|\bdiseño\b|\bdiseno\b|\barte|\bmusica\b|\bcultur|\bpatrimonio\b|\brestauracion\b', 'arquitectura, diseño y cultura'),
    (r'\bbibliotec|\barchiv|\bdocumentacion\b|\bciencias de la informacion\b', 'bibliotecología e información'),
    (r'\bciencia politica\b|\bciencias politicas\b|\brelaciones internacionales\b|\bestudios internacionales\b|\bsociolog|\bantropolog|\bhistoria\b|\bfilosof|\bhumanidades\b|\bciencias sociales\b|\bgenero\b|\bderechos humanos\b|\bdesarrollo (social|comunitario|regional|humano|sustentable|rural)\b|\bpoblacion\b|\bmigracion\b|\bdemograf', 'ciencias sociales y políticas'),
    (r'\bdeport|\bactividad fisica\b|\bfutbol\b|\bentrenamiento\b', 'deporte'),
    (r'\bturis|\bhoteler|\bgastronom|\brestaurant|\bhospitalidad\b', 'turismo y hospitalidad'),
    (r'\bagro|\bacuicol|\bforestal\b|\bambiental\b|\bmedio ambiente\b|\becolog|\bmarin', 'agro y ambiente'),
    (r'\bmatematic|\bestadistic|\bactuar|\bfisica\b|\bquimic|\bbiolog', 'ciencias exactas y naturales'),
    (r'\bmecatronic|\bmanufactura\b|\bindustrial(es)?\b|\bingenieria (mecanica|electrica|electronica|civil)\b|\bseguridad e higiene\b|\brobotica\b|\bautomatizacion\b', 'ingeniería e industria'),
    (r'\beconomia\b|\beconomic', 'economía'),
]

# Prior de contexto por carrera ESPECÍFICA (más fiable) y GENÉRICA (más amplia).
CONTEXTO_ESPECIFICA = {
    'auditoria': CON, 'administracion fiscal': CON, 'contabilidad financiera': CON, 'contaduria y finanzas': CON,
    'aduanas': NEG, 'aduanera': NEG, 'vista aduanal': NEG, 'administracion de negocios internacionales': NEG, 'comercializacion internacional': NEG,
    'ciencias de la computacion': INF, 'computacion e informatica': INF,
    'administracion': ADM, 'administracion y mercadotecnia': ADM, 'administracion y comercio': ADM, 'administracion y direccion estrategica': ADM,
    'creacion y direccion de empresas': ADM, 'ciencias empresariales': ADM, 'direccion de proyectos': ADM, 'recursos humanos': ADM,
    'desarrollo del personal en empresa': ADM, 'administracion gubernamental': ADM, 'administracion financiera': ADM,
    'administracion con enfasis en desarrollo empresarial y nuevos negocios': ADM, 'comercializacion': ADM, 'mercadotecnia y comercio': ADM,
    'relaciones industriales': ADM, 'accion politica y administracion publica': ADM,
}
CONTEXTO_GENERICA = {'contaduria': CON, 'finanzas': CON, 'comercio internacional': NEG, 'computacion e informatica': INF,
                     'administracion': ADM, 'mercadotecnia y comercio': ADM, 'relaciones industriales': ADM}

# Temas que, por instrucción, siempre se revisan (interdisciplinarios o con varias áreas posibles).
TEMAS_REVISION = [
    (r'\bfinanz|\bfinanciera?s?\b', 'finanzas'), (r'\beconomi', 'economía'), (r'\blogistica\b', 'logística'),
    (r'\badministracion educativa\b|\bgestion educativa\b|\bdireccion (de instituciones )?educativa', 'administración educativa'),
    (r'\bderecho fiscal\b|\bderecho tributario\b', 'derecho fiscal'), (r'\blavado de dinero\b', 'prevención de lavado de dinero'),
    (r'\bgestion (de la )?tecnolog|\binnovacion tecnologica\b', 'gestión tecnológica'), (r'\banalitica\b|\binteligencia de negocios\b', 'analítica de negocios'),
    (r'\badministracion de (servicios|hospitales|instituciones de salud|la salud|empresas turisticas|empresas de la hospitalidad|restaurantes)', 'administración de servicios especializados'),
]


GESTION = r'(administracion|gestion|direccion|direccion estrategica|planeacion|planeacion estrategica|gerencia|alta direccion|auditoria|evaluacion)'
OBJETO_GESTION = re.compile(r'^' + GESTION + r'( (y|e) [a-z]+)? (de|en|del|para) (las |los |la |el )?(?P<obj>.+)$')
ENFASIS = re.compile(r'\b(con|en el area de|area de|con (enfoque|especialidad|concentracion|acentuacion|orientacion|enfasis|especializacion|terminal)) (en |de )?(?P<obj>.+)$')
DOMINIOS_OBJETO = (INF, CON, NEG)


def puntuar(texto):
    """Puntaje por área = peso máximo de sus reglas + 0.25 por cada regla adicional distinta (sin doble conteo)."""
    maximos, criterios = defaultdict(int), defaultdict(list)
    for patron, area, peso, criterio in REGLAS:
        if re.search(patron, texto):
            maximos[area] = max(maximos[area], peso)
            criterios[area].append(criterio)
    puntajes = {a: maximos[a] + 0.25 * (len(criterios[a]) - 1) for a in maximos}
    return defaultdict(float, puntajes), criterios


def area_de_objeto(objeto):
    """Área de dominio (Informática, Contaduría o Negocios Internacionales) del objeto gestionado, si es clara."""
    if any(re.search(p, objeto) for p, _ in OTRO_DOMINIO if not re.search(r'industrial|economi', p)):
        return None
    p, _ = puntuar(objeto)
    candidatas = sorted(((p[a], a) for a in DOMINIOS_OBJETO if p[a] >= 3), reverse=True)
    if candidatas and (len(candidatas) == 1 or candidatas[0][0] > candidatas[1][0]):
        return candidatas[0][1]
    return None


def clasificar(titulo, generica, especifica):
    t = sin_prefijo(normal(titulo))
    puntajes, criterios = puntuar(t)
    dominios = [d for patron, d in OTRO_DOMINIO if re.search(patron, t)]

    # Señales específicas (peso ≥ 2) de las 4 áreas frente a señales genéricas.
    fuertes = {a: p for a, p in puntajes.items() if p >= 2}
    revision = []
    temas = sorted({tema for patron, tema in TEMAS_REVISION if re.search(patron, t)})

    ctx_esp = CONTEXTO_ESPECIFICA.get(normal(especifica))
    ctx_gen = CONTEXTO_GENERICA.get(normal(generica))
    contexto = ctx_esp or ctx_gen
    # El contexto solo desempata; nunca supera a una señal del título.
    if ctx_esp:
        puntajes[ctx_esp] += 0.4
    elif ctx_gen:
        puntajes[ctx_gen] += 0.2

    # Reglas de prioridad explícitas (ejemplos aprobados como criterio de diseño, no como equivalencias oficiales).
    if re.search(r'\badministracion (tributaria|fiscal)\b', t):
        return CON, 'alta', 'administración tributaria/fiscal → Contaduría', ADM, bool(temas), temas
    if re.search(r'\badministracion de (las )?(tecnologias de (la )?informacion|redes|sistemas|telecomunicaciones)', t):
        return INF, 'alta', 'administración de tecnologías/redes → Informática', ADM, bool(temas), temas
    if re.search(r'\badministracion de (los )?negocios internacionales\b', t):
        return NEG, 'alta', 'administración de negocios internacionales → Negocios Internacionales', ADM, bool(temas), temas

    # Gestión de un dominio: «administración/gestión/dirección de X» → área de X (alternativa: Administración).
    m = OBJETO_GESTION.match(t)
    if m and (obj := area_de_objeto(m.group('obj'))):
        conf = 'alta' if m.group(1) in ('administracion', 'gestion') else 'media'
        return obj, conf, f'{m.group(1)} de un objeto de {obj}', ADM, conf != 'alta' or bool(temas), temas
    # Énfasis declarado: «Administración con enfoque/concentración en X» → área de X (alternativa: Administración).
    m = ENFASIS.search(t)
    base = t[:m.start()].strip() if m else ''
    if m and re.fullmatch(r'(ciencias? )?(administracion|negocios|administracion de (empresas|negocios)|direccion|gestion)( empresarial)?', base) \
            and (obj := area_de_objeto(m.group('obj'))):
        return obj, 'media', f'{base} con énfasis en un área de {obj}', ADM, False, temas

    # Dominio ajeno SIN señal fuerte de las cuatro áreas → Otro (p. ej., administración de justicia).
    if dominios and not fuertes:
        alt = max(puntajes, key=puntajes.get) if puntajes else None
        # Si además hay señal débil de un área base (p. ej., «Administración con orientación en turismo»), se revisa.
        senal_base = any(puntajes.get(a, 0) >= 1 for a in (ADM, CON, INF, NEG))
        conf = 'media' if senal_base else 'alta'
        return OTR, conf, 'disciplina: ' + ', '.join(dominios), (alt if alt and alt != OTR else None), bool(temas) or senal_base, temas

    orden = sorted(((p, a) for a, p in puntajes.items() if a != OTR), reverse=True)
    if not orden:
        return OTR, 'media', 'sin relación clara con las cuatro áreas base', None, bool(temas), temas
    (p1, a1), (p2, a2) = orden[0], (orden[1] if len(orden) > 1 else (0, None))
    if p1 < 1:                                               # sin señal del título: solo contexto
        return (contexto or OTR), 'baja', 'sin señal en el título; contexto de la carrera', OTR if contexto else None, True, temas
    texto = '; '.join(criterios[a1]) or 'contexto de la carrera'

    if dominios:                                             # interdisciplinario: área base + otra disciplina
        revision.append('interdisciplinario: ' + ', '.join(dominios))
        conf = 'media' if p1 >= 3 else 'baja'
        return a1, conf, f'{texto} (con {", ".join(dominios)})', OTR if p1 < 3 else (a2 or OTR), True, temas
    if p1 - p2 < 0.5 and a2 and p2 >= 2:                    # dos áreas con señales fuertes casi empatadas
        return a1, 'baja', f'{texto} / {"; ".join(criterios[a2]) or "contexto"}', a2, True, temas
    if p1 >= 3 and not temas:
        return a1, 'alta', texto, a2, False, temas
    if p1 >= 2:
        return a1, 'media', texto, a2, bool(temas), temas
    # Solo señal débil o de contexto.
    return a1, 'baja', texto + ' (señal débil o de contexto)', a2 or OTR, True, temas


def clave_estable(prefijo, titulo):
    return f'{prefijo}-{hashlib.sha256(normal(titulo).encode()).hexdigest()[:12].upper()}'


def main(fuente, salida):
    fuente, salida = Path(fuente), Path(salida)
    sha_fuente = hashlib.sha256(fuente.read_bytes()).hexdigest()
    wb = openpyxl.load_workbook(fuente, read_only=True, data_only=True)
    registros, excluidos, conteo_hojas = [], [], {}
    for hoja, (nivel, prefijo) in HOJAS.items():
        filas = list(wb[hoja].iter_rows(values_only=True))
        encabezado = [str(v or '').strip() for v in filas[0][:4]]
        assert normal(encabezado[3]) == normal(hoja), f'Encabezado inesperado en {hoja}: {encabezado}'
        vistos = set()
        conteo_hojas[hoja] = {'filas_con_datos': 0, 'titulos': 0}
        for n, fila in enumerate(filas[1:], start=2):
            a, b, c, d = (fila + (None,) * 4)[:4]
            if all(v in (None, '') for v in (a, b, c, d)):
                continue
            conteo_hojas[hoja]['filas_con_datos'] += 1
            titulo = str(d).strip() if d is not None else ''
            if not titulo:
                excluidos.append({'hoja': hoja, 'fila': n, 'motivo': 'fila sin nombre de posgrado', 'datos': [a, b, c]})
                continue
            llave = (normal(a), normal(b), normal(c), titulo)
            if llave in vistos:
                excluidos.append({'hoja': hoja, 'fila': n, 'motivo': 'duplicado exacto', 'datos': [a, b, c, titulo]})
                continue
            vistos.add(llave)
            area, conf, criterio, alternativa, revisar, temas = clasificar(titulo, b, c)
            if revisar and conf == 'alta':
                conf = 'media'                               # coherencia: lo que se revisa no es de confianza alta
            obs = []
            if not str(c or '').strip():
                obs.append('sin carrera específica')
                revisar = True
            if temas:
                obs.append('tema de revisión: ' + ', '.join(temas))
            registros.append({
                'clave': clave_estable(prefijo, titulo), 'nivel': nivel, 'area': area, 'titulo': titulo,
                'area_estudio': (str(a).strip() if a else None), 'carrera_generica': (str(b).strip() if b else None),
                'carrera_especifica': (str(c).strip() if c else None), 'hoja': hoja, 'fila': n,
                'confianza': conf, 'requiere_revision': bool(revisar or conf == 'baja'), 'criterio': criterio,
                'area_alternativa': alternativa if alternativa != area else None, 'observaciones': '; '.join(obs) or None,
            })
            conteo_hojas[hoja]['titulos'] += 1
    claves = Counter(r['clave'] for r in registros)
    assert all(c == 1 for c in claves.values()), 'Colisión de claves'

    registros.sort(key=lambda r: (['Especialidad', 'Maestría', 'Doctorado'].index(r['nivel']), normal(r['titulo'])))
    catalogo = {
        'version': VERSION_CATALOGO, 'reglas': VERSION_REGLAS, 'niveles': ['Especialidad', 'Maestría', 'Doctorado'], 'areas': AREAS,
        'aviso': 'Clasificación administrativa propuesta para facilitar la captura; no es equivalencia académica oficial ni criterio de elegibilidad.',
        'titulos': [{k: r[k] for k in ('clave', 'nivel', 'area', 'titulo', 'confianza', 'requiere_revision',
                                       'area_estudio', 'carrera_generica', 'carrera_especifica', 'hoja', 'fila')} for r in registros],
    }
    salida.mkdir(parents=True, exist_ok=True)
    json_path = salida / 'posgrados_anuies_2025.json'
    json_path.write_text(json.dumps(catalogo, ensure_ascii=False, indent=1), encoding='utf-8')
    sha_json = hashlib.sha256(json_path.read_bytes()).hexdigest()

    resumen = defaultdict(Counter)
    for r in registros:
        resumen[r['area']][r['nivel']] += 1
    manifiesto = {
        'version': VERSION_CATALOGO, 'reglas': VERSION_REGLAS, 'fuente': fuente.name, 'sha256_fuente': sha_fuente,
        'catalogo': json_path.name, 'sha256_catalogo': sha_json, 'generado': datetime.now(timezone.utc).isoformat(timespec='seconds'),
        'hojas_utilizadas': list(HOJAS), 'hoja_excluida': 'Catálogo Posgrados ANUIES', 'conteo_hojas': conteo_hojas,
        'conteo_niveles': dict(Counter(r['nivel'] for r in registros)), 'conteo_areas': dict(Counter(r['area'] for r in registros)),
        'conteo_area_nivel': {a: dict(resumen[a]) for a in AREAS}, 'pendientes_revision': sum(r['requiere_revision'] for r in registros),
        'confianza': dict(Counter(r['confianza'] for r in registros)), 'excluidos': excluidos, 'total': len(registros),
        'aviso': catalogo['aviso'],
    }
    (salida / 'manifiesto_catalogo.json').write_text(json.dumps(manifiesto, ensure_ascii=False, indent=1), encoding='utf-8')
    escribir_matriz(salida / 'matriz_clasificacion_posgrados.xlsx', registros, manifiesto)
    print(json.dumps({k: manifiesto[k] for k in ('total', 'conteo_hojas', 'conteo_area_nivel', 'pendientes_revision', 'confianza', 'sha256_catalogo')}, ensure_ascii=False))


def escribir_matriz(ruta, registros, manifiesto):
    wb = openpyxl.Workbook()
    ws = wb.active
    ws.title = 'Matriz'
    columnas = ['Núm.', 'Hoja de origen', 'Fila original', 'Nivel académico', 'Área de estudio original', 'Carrera genérica',
                'Carrera específica', 'Nombre original del posgrado', 'Área ANFECA propuesta', 'Criterio de clasificación',
                'Confianza', 'Requiere revisión', 'Área alternativa', 'Observaciones', 'Clasificación autorizada', 'Clave estable del catálogo']
    ws.append(columnas)
    for i, r in enumerate(registros, start=1):
        ws.append([i, r['hoja'], r['fila'], r['nivel'], r['area_estudio'], r['carrera_generica'], r['carrera_especifica'], r['titulo'],
                   r['area'], r['criterio'], r['confianza'], 'Sí' if r['requiere_revision'] else 'No', r['area_alternativa'],
                   r['observaciones'], None, r['clave']])
    estilo_tabla(ws, [6, 15, 8, 13, 22, 24, 30, 60, 18, 45, 10, 10, 18, 35, 18, 18])

    amb = wb.create_sheet('Casos ambiguos')
    amb.append(['Núm.'] + columnas[1:])
    for i, r in enumerate([r for r in registros if r['requiere_revision']], start=1):
        amb.append([i, r['hoja'], r['fila'], r['nivel'], r['area_estudio'], r['carrera_generica'], r['carrera_especifica'], r['titulo'],
                    r['area'], r['criterio'], r['confianza'], 'Sí', r['area_alternativa'], r['observaciones'], None, r['clave']])
    estilo_tabla(amb, [6, 15, 8, 13, 22, 24, 30, 60, 18, 45, 10, 10, 18, 35, 18, 18])

    res = wb.create_sheet('Resumen', 0)
    res.append(['Catálogo de posgrados — clasificación administrativa PROPUESTA (pendiente de autorización)'])
    res.append([manifiesto['aviso']])
    res.append([f"Fuente: {manifiesto['fuente']} · SHA-256 {manifiesto['sha256_fuente']}"])
    res.append([f"Versión {manifiesto['version']} · {manifiesto['reglas']} · generado {manifiesto['generado']}"])
    res.append([])
    res.append(['Área', 'Especialidades', 'Maestrías', 'Doctorados', 'Total'])
    totales = Counter()
    for a in AREAS:
        fila = [manifiesto['conteo_area_nivel'][a].get(n, 0) for n in ('Especialidad', 'Maestría', 'Doctorado')]
        totales.update(dict(zip(('E', 'M', 'D'), fila)))
        res.append([a] + fila + [sum(fila)])
    res.append(['Total', totales['E'], totales['M'], totales['D'], sum(totales.values())])
    res.append([])
    res.append(['Pendientes de revisión', manifiesto['pendientes_revision']])
    for k, v in manifiesto['confianza'].items():
        res.append([f'Confianza {k}', v])
    res.append(['Filas excluidas', len(manifiesto['excluidos'])])
    for e in manifiesto['excluidos']:
        res.append([f"  {e['hoja']} fila {e['fila']}", e['motivo']])
    for c in res[6]:
        c.font = Font(bold=True)
    res.column_dimensions['A'].width = 34
    for col in 'BCDE':
        res.column_dimensions[col].width = 15
    res['A1'].font = Font(bold=True, size=13, color='BD2434')
    wb.save(ruta)


def estilo_tabla(ws, anchos):
    for i, ancho in enumerate(anchos, start=1):
        ws.column_dimensions[get_column_letter(i)].width = ancho
    for celda in ws[1]:
        celda.font = Font(bold=True, color='FFFFFF')
        celda.fill = PatternFill('solid', fgColor='BD2434')
        celda.alignment = Alignment(wrap_text=True, vertical='center')
    ws.freeze_panes = 'B2'
    ws.auto_filter.ref = ws.dimensions


if __name__ == '__main__':
    if len(sys.argv) != 3:
        sys.exit(__doc__)
    main(sys.argv[1], sys.argv[2])
