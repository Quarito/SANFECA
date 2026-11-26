#!/usr/bin/env python3
"""
ANFECA · Descarga de denominaciones de licenciatura del Anuario Estadístico ANUIES (servicio público de la página).

    python3 -I scripts/anfeca/catalogos/descargar_anuario_licenciaturas.py <ciclo> <directorio_destino> [--institucion] [--contar]

- Usa el MISMO servicio que la interfaz de https://anuario.anuies.mx/ (POST action=exportReportRows / countTotalRows),
  sin credenciales y sin evadir restricciones. Una solicitud por ejecución.
- Filtro: niveles LICENCIATURA UNIVERSITARIA Y TECNOLÓGICA y LICENCIATURA EN EDUCACIÓN NORMAL; todas las entidades,
  sostenimientos, instituciones, modalidades y campos. Variables: matrícula, nuevo ingreso, egresados, titulados.
- Guarda la respuesta ORIGINAL sin modificar (NDJSON), permisos 600; nunca sobrescribe; verifica ≥ 5 GB libres.
- Condiciones de uso de la fuente: «© ANUIES — Todos los derechos reservados». No publicar los archivos.
"""
import hashlib
import json
import os
import shutil
import sys
import urllib.parse
import urllib.request
from datetime import datetime
from pathlib import Path

URL = 'https://anuario.anuies.mx/'
UA = 'Mozilla/5.0 (X11; Linux x86_64) consulta-institucional'
NIVELES = ['LICENCIATURA UNIVERSITARIA Y TECNOLÓGICA', 'LICENCIATURA EN EDUCACIÓN NORMAL']
VARIABLES = ['MATRÍCULA', 'EGRESADOS', 'TITULADOS', 'NUEVO INGRESO (NI)']
MINIMO_LIBRE = 5 * 1024 ** 3


def parametros(accion, ciclo, institucion):
    return [('action', accion), ('ciclo', ciclo)] + [('vars[]', v) for v in VARIABLES] + [
        ('desagSexo', '0'), ('desagDiscapacidad', '0'), ('desagEdad', '0'), ('desagHLI', '0'), ('desagProcedencia', '0'), ('niProcedencia[]', ''),
        ('includeEnts', '0'), ('entMode', 'all'), ('includeMuns', '0'), ('munMode', 'all'), ('includeSos', '0'), ('includeAnuies', '0'),
        ('includeSub', '0'), ('subMode', 'all'), ('includeInstitucion', '1' if institucion else '0'), ('includeEscuela', '0'),
        ('includeNivel', '1'), ('nivelesVals', json.dumps(NIVELES, ensure_ascii=False)), ('includeModalidad', '0'),
        ('includePrograma', '1'), ('includeStem', '0'),
        ('includeCampoAmplio', '1'), ('includeCampoEspecifico', '1'), ('includeCampoDetallado', '1'), ('includeCampoUnitario', '0')]


def solicitar(pares, timeout=900):
    req = urllib.request.Request(URL, data=urllib.parse.urlencode(pares).encode(),
                                 headers={'User-Agent': UA, 'Content-Type': 'application/x-www-form-urlencoded'})
    with urllib.request.urlopen(req, timeout=timeout) as r:
        return r.status, r.headers.get('Content-Type', ''), r.read()


def main():
    args = [a for a in sys.argv[1:] if not a.startswith('--')]
    if len(args) != 2:
        sys.exit(__doc__)
    ciclo, destino = args
    institucion, contar = '--institucion' in sys.argv, '--contar' in sys.argv
    if contar:
        st, _, cuerpo = solicitar(parametros('countTotalRows', ciclo, institucion), 300)
        print(st, cuerpo.decode('utf-8', 'replace'))
        return
    d = Path(destino)
    d.mkdir(parents=True, exist_ok=True)
    os.chmod(d, 0o700)
    if shutil.disk_usage(d).free < MINIMO_LIBRE:
        sys.exit('DETENIDO: menos de 5 GB libres')
    nombre = f"anuies_exportReportRows_{ciclo}_{'institucion_' if institucion else ''}programa_nivel_campos.json"
    ruta = d / nombre
    if ruta.exists():
        sys.exit(f'DETENIDO: ya existe {ruta} (no se sobrescribe)')
    momento = datetime.now().astimezone().isoformat(timespec='seconds')
    st, tipo, cuerpo = solicitar(parametros('exportReportRows', ciclo, institucion))
    if st != 200 or 'ndjson' not in tipo:
        sys.exit(f'DETENIDO: respuesta inesperada {st} {tipo}')
    lineas = cuerpo.decode('utf-8').splitlines()
    fin = json.loads(lineas[-1])
    filas = sum(1 for l in lineas if '"type":"row"' in l)
    if fin.get('type') != 'done' or fin.get('rows') != filas:
        sys.exit('DETENIDO: respuesta incompleta (sin cierre o con conteo distinto)')
    ruta.write_bytes(cuerpo)
    os.chmod(ruta, 0o600)
    print(json.dumps({'archivo': str(ruta), 'bytes': len(cuerpo), 'sha256': hashlib.sha256(cuerpo).hexdigest(), 'filas': filas,
                      'ciclo': ciclo, 'consulta': momento, 'url': URL, 'metodo': 'POST action=exportReportRows'}, ensure_ascii=False))


if __name__ == '__main__':
    main()
