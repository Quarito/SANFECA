# SEPOMEX_ANFECA · Carga del Catálogo Nacional de Códigos Postales con PostgreSQL

Paquete **privado y de uso interno** para instalar el catálogo oficial de Correos de México (SEPOMEX) en las tablas
existentes del modelo oficial de ANFECA (36 tablas): `ENTIDAD_FEDERATIVA`, `ALCALDIA_MUNICIPIO` y `COLONIA`.

Una vez cargado, ANFECA consulta **solo PostgreSQL**: no se conecta con SEPOMEX, no usa APIs externas, no descarga el
catálogo al desplegar ni al iniciar, y no hay tareas programadas ni sincronizaciones. Las actualizaciones futuras son
manuales, excepcionales y previamente autorizadas (se genera un paquete nuevo con el archivo oficial nuevo).

> **Restricción de uso.** La hoja «Nota» del archivo oficial indica: «El Catálogo Nacional de Códigos Postales, es
> elaborado por Correos de México y se proporciona en forma gratuita para uso particular, no estando permitida su
> comercialización, total o parcial, ni su distribución a terceros bajo ningún concepto». El archivo original y los SQL
> derivados (que reproducen sus datos) son para uso interno de ANFECA: **no se publican** en GitHub ni en ningún sitio,
> y no se entregan a terceros. El repositorio solo contiene el generador, las instrucciones y pruebas con datos ficticios.

## Contenido

| Ruta | Descripción |
|---|---|
| `original/CPdescarga.xls` | Archivo oficial (versión y huella en `manifiesto.json`) |
| `sql/00_diagnostico_previo.sql` | Diagnóstico **de solo lectura**: base, esquema, tablas, conteos, identificadores, duplicados, inconsistencias, secuencias y conflictos con el catálogo |
| `sql/01_insert_entidad_federativa.sql` | INSERT de las 32 entidades |
| `sql/02_insert_alcaldia_municipio.sql` | INSERT de las 2 478 alcaldías y municipios (una instrucción por entidad) |
| `sql/03_insert_colonia.sql` | INSERT de las 158 507 colonias y asentamientos (una instrucción por municipio) |
| `sql/04_verificacion_catalogos.sql` | Verificación **de solo lectura**: totales, cobertura y huella por entidad, integridad, secuencias, conservación y consultas de ejemplo |
| `sql/05_reversion_catalogos.sql` | Reversión controlada (simula por omisión; nunca se ejecuta sola) |
| `sql/SEPOMEX_CATALOGO_COMPLETO.sql` | Script maestro: 00 → 01 → 02 → 03 en **una transacción** → 04 |
| `herramientas/` | Generador (`generar_sql.sh` + fuentes), pruebas de escenarios y de funcionamiento sin Internet, empaquetador |
| `evidencias/` | `reporte_importacion.txt`, `reporte_duplicados.csv`, `conteos_por_entidad.csv`, `pruebas.sql` y registros de las ejecuciones reales |
| `manifiesto.json`, `SHA256SUMS` | Metadatos del catálogo y huella SHA-256 de cada archivo |

## Cómo funcionan los INSERT

- **Sin identificadores fijos.** Ningún script contiene identificadores, rutas, usuarios ni bases de un ambiente. Las
  referencias se resuelven en la base destino con claves naturales: la entidad por su nombre (sin distinguir acentos,
  mayúsculas ni espacios); el municipio por su nombre exacto dentro de su entidad; la colonia por municipio + código
  postal + nombre exacto.
- **Solo lo faltante.** Cada INSERT lleva `WHERE NOT EXISTS`; nunca actualiza ni elimina, no usa `ON CONFLICT` (el modelo
  no tiene restricciones únicas sobre nombres), ni `TRUNCATE`/`DROP`. Repetir la carga no duplica nada.
- **Entidades.** Si la entidad ya existe (con cualquier identificador o grafía) se reutiliza sin modificarla. Una entidad
  nueva toma la clave oficial (`c_estado`, 01–32) si está libre; si la ocupa otra entidad del catálogo, toma el siguiente
  valor de la secuencia; si la ocupa un nombre ajeno al catálogo, **se detiene** para revisión.
- **Detención ante ambigüedades.** La carga se detiene y se revierte completa si: una entidad está registrada más de una
  vez; hay municipios repetidos en su entidad; un municipio existente difiere del oficial solo en acentos, mayúsculas o
  espacios (no se duplica ni se renombra); o una fila del catálogo no resuelve exactamente una referencia.
- **Transacción y bloqueo.** Todo ocurre en una transacción `REPEATABLE READ`; las tres tablas se bloquean en modo
  `SHARE ROW EXCLUSIVE` (las consultas de la aplicación siguen funcionando; otra carga simultánea espera o falla por
  `lock_timeout` de 10 s). `ON_ERROR_STOP` detiene psql en el primer error y nada queda a medias.
- **Secuencias SERIAL.** Si una secuencia quedó por debajo del máximo existente se lleva a ese máximo (nunca se
  disminuye), para que los nuevos identificadores no colisionen.
- **Sin objetos permanentes.** Las comprobaciones usan funciones temporales de la sesión (`pg_temp`), que desaparecen al
  cerrar la conexión; el esquema oficial no cambia. Requiere el privilegio `TEMP` sobre la base (el diagnóstico lo muestra).
- **Codificación.** UTF-8; apóstrofes escapados; códigos postales como texto de 5 caracteres (ceros iniciales intactos);
  ningún nombre se trunca (todos caben en `VARCHAR(150)`/`VARCHAR(85)`; si no, PostgreSQL rechaza la carga).
- **`DOMICILIO` no se toca.** El maestro compara su conteo y su huella antes y después y se detiene si cambiara. No se usa
  `COMUNICACION` ni `TIPO_COMUNICACION`, ni se crea `DOMI_ID_COLONIA`.

## Requisitos de la base destino

PostgreSQL con el modelo oficial de 36 tablas (173 atributos, 35 llaves foráneas) en `public`, codificación UTF-8, y un
rol con `SELECT`/`INSERT` sobre las tres tablas, `USAGE`/`UPDATE` sobre sus secuencias y `TEMP` sobre la base (el rol de
la aplicación los tiene como propietario). Solo se necesita el cliente `psql`: no se requiere PHP, Laravel, Composer,
Vue.js ni Internet.

## Orden de ejecución

1. **Respaldo** de la base y comprobación de que se restaura (ver «Respaldo»).
2. **Diagnóstico** (no escribe):
   ```bash
   cd SEPOMEX_ANFECA/sql && psql -X -v ON_ERROR_STOP=1 -h <servidor> -p <puerto> -U <rol> -d <base> -f 00_diagnostico_previo.sql | tee diagnostico.txt
   ```
   Revisar: estructura (sección 2), duplicados (5, todo en 0), conciliación de entidades (6, sin «CONFLICTO») y
   municipios con conflicto de grafía (7, columna en 0).
3. **Carga** (una transacción; incluye 00 al inicio y 04 al final):
   ```bash
   cd SEPOMEX_ANFECA/sql && psql -X -v ON_ERROR_STOP=1 -h <servidor> -p <puerto> -U <rol> -d <base> -f SEPOMEX_CATALOGO_COMPLETO.sql 2>&1 | tee carga.log
   ```
   Conservar la línea «Marcas de reversión» de `carga.log`. Alternativamente, ejecutar `01`, `02` y `03` por separado y
   en ese orden (cada uno en su propia transacción) y después `04`.
4. **Verificación** (repetible, no escribe): `psql … -f 04_verificacion_catalogos.sql`. Esperado en una base sin catálogo
   previo: 32 / 2 478 / 158 507 / 31 860 códigos postales, «coincide» en las 32 entidades e integridad en 0.

## Tiempo, espacio y memoria (medidos en PostgreSQL 18.6, base vacía)

| Concepto | Medición en laboratorio |
|---|---|
| Duración total del maestro (00 + carga + 04) | 8–9 s (la inserción de las 158 507 colonias, ~3.5 s) |
| Espacio de las tres tablas cargadas (datos e índices) | 18 MB (la base completa con el modelo: 27 MB) |
| Paquete `sql/` | 9.1 MB (ZIP del paquete completo con el original: ver `manifiesto.json`) |
| Memoria del cliente `psql` | 13 MB pico |
| Memoria del proceso servidor de la sesión | 63 MB pico |
| Bloqueo | Las tres tablas quedan en solo lectura durante la transacción (segundos); la consulta por CP sigue funcionando |

Recomendación: al menos 200 MB libres en el volumen de datos de PostgreSQL (incluye WAL y margen) y 100 MB para el
paquete. No se requiere ajustar `work_mem`, `maintenance_work_mem` ni otros parámetros del servidor.

## Respaldo

Antes de cargar: `pg_dump -Fc` de la base, comprobación de que se restaura en una base **nueva** y conteo de filas por
tabla. En el 8088 lo hace el bloque 2 (`02_respaldo.sh`).

## Reversión

1. **Preferente — por las marcas de la carga** (no se ejecuta sola; simula por omisión):
   ```bash
   psql -X -v ON_ERROR_STOP=1 … <marcas copiadas de carga.log> -f 05_reversion_catalogos.sql
   psql -X -v ON_ERROR_STOP=1 … <marcas copiadas de carga.log> -v confirmar=si -f 05_reversion_catalogos.sql
   ```
   Elimina solo identificadores dentro de los rangos de esa carga; conserva todo registro previo, todo municipio con
   colonias que se conserven y toda entidad con municipios o domicilios.
2. **Si no hay marcas o hay duda — restauración del respaldo** en una base **nueva**, sin borrar la actual. Para preservar
   cambios posteriores a la carga (solicitantes, domicilios, expedientes), se exportan de la base actual las filas creadas
   después del respaldo y se incorporan en la restaurada antes de cambiar `DB_DATABASE`; o, más simple, se conserva la
   base actual (el catálogo cargado no afecta a versiones anteriores de la aplicación, que no lo consultan).

## Instalación en producción (solo con autorización explícita)

Mismo paquete, mismos scripts; ningún archivo contiene datos del ambiente. Sustituir `<…>` por los valores reales,
confirmados en el paso 2.

1. **Transferencia privada** del ZIP y su `.sha256` (scp/sftp interno; nunca correo, repositorios ni nubes públicas):
   ```bash
   sha256sum -c SEPOMEX_ANFECA_21112026.zip.sha256 && unzip -q SEPOMEX_ANFECA_21112026.zip -d <directorio privado> && cd <directorio privado>/SEPOMEX_ANFECA && sha256sum -c --quiet SHA256SUMS && echo "Paquete íntegro"
   ```
2. **Validación de la base destino** (servidor, puerto, base y rol reales; versión, codificación, 36/173/35, privilegio
   `TEMP`, duplicados y conflictos):
   ```bash
   cd <directorio privado>/SEPOMEX_ANFECA/sql && psql -X -v ON_ERROR_STOP=1 -h <servidor> -p <puerto> -U <rol> -d <base> -f 00_diagnostico_previo.sql | tee ~/sepomex_00_$(date +%Y%m%d_%H%M).txt
   ```
3. **Respaldo previo y prueba de restauración** (base nueva; nunca sobre la actual):
   ```bash
   pg_dump -Fc -h <servidor> -p <puerto> -U <rol> -d <base> -f <respaldos>/<base>_antes_sepomex_$(date +%Y%m%d_%H%M).dump
   ```
   ```bash
   createdb -h <servidor> -p <puerto> -U <rol administrador> -O <rol> <base>_restauracion_$(date +%Y%m%d) && pg_restore -h <servidor> -p <puerto> -U <rol> -d <base>_restauracion_$(date +%Y%m%d) --no-owner --exit-on-error <respaldos>/<archivo>.dump
   ```
   Comparar el conteo de filas de las 36 tablas entre la base y la restaurada.
4. **Carga** (8–9 s; las tres tablas quedan en solo lectura durante la transacción):
   ```bash
   cd <directorio privado>/SEPOMEX_ANFECA/sql && psql -X -v ON_ERROR_STOP=1 -h <servidor> -p <puerto> -U <rol> -d <base> -f SEPOMEX_CATALOGO_COMPLETO.sql 2>&1 | tee ~/sepomex_carga_$(date +%Y%m%d_%H%M).log
   ```
   Debe terminar con «Transacción confirmada» y la verificación 04. Guardar la línea «Marcas de reversión» junto al respaldo.
5. **Verificación posterior**: `04_verificacion_catalogos.sql` (32 entidades «coincide» si no había catálogo previo;
   integridad en 0; 36 tablas) y una consulta al servicio interno de la aplicación:
   ```bash
   curl -s -H 'Accept: application/json' http://<servidor de la aplicación>/catalogos/codigo-postal/04510
   ```
6. **Reversión** (solo si se autoriza): `05_reversion_catalogos.sql` con las marcas (simula; `-v confirmar=si` aplica) o
   restauración del respaldo en una base nueva (ver «Reversión»).

Dependencias: cliente `psql` de PostgreSQL 13 o posterior (probado con 18.6), base con el modelo oficial, rol con los
privilegios indicados. Ninguna dependencia de PHP, Laravel, Composer, Node, Vue.js ni de Internet.

## Validación realizada

Ver `evidencias/escenarios/resultados.txt`: base vacía; 32 entidades previas con otra grafía; municipios y colonias
previos; identificadores distintos y secuencias atrasadas; cuatro tipos de conflicto; ejecución repetida; error y
terminación de la conexión a mitad de la carga; conservación de domicilios y solicitantes ficticios; integridad
referencial; comparación con el importador Artisan; códigos postales con cero inicial; esquema de 36 tablas; reversión.
`evidencias/sin_internet/`: la aplicación funcionando en un espacio de red sin Internet.

## Regenerar el paquete

Con un archivo oficial nuevo (actualización excepcional y autorizada): comparar primero sus diferencias con la versión
validada (conteos por entidad de `conteos_por_entidad.csv`) y después:

```bash
ANFECA=<aplicación ANFECA> bash herramientas/generar_sql.sh <CPdescarga.xls> <directorio nuevo fuera de Git> <sha256>
```
