# Reporte · Carga permanente del catálogo SEPOMEX con scripts SQL

Fecha: 28/11/2026 · Rama: `integracion/fase9-8088` · **Nada se ha ejecutado en 8088, 8008, 8086 ni producción.** Todo lo
reportado como ejecutado se hizo en una instancia PostgreSQL 18.6 desechable con bases nuevas y datos ficticios.

## 1. Catálogo utilizado (reutilizado; no se descargó de nuevo)

| Dato | Valor |
|---|---|
| Archivo / fuente | `CPdescarga.xls` · https://www.correosdemexico.gob.mx/SSLServicios/ConsultaCP/CodigoPostal_Exportar.aspx |
| Versión (actualización) / descarga | 21/11/2026 / no registrada (el archivo dentro del ZIP recibido tiene fecha 21/11/2026 00:16) |
| SHA-256 | `0a8e2611b92635f47c646816b3641354ab4a554f64b058447890d77e8a052821` (ZIP: `ac8fbc11…c792d3`) |
| Registros originales → procesados | 159 527 filas → 158 507 colonias representables; **1 020** consolidados (solo difieren en tipo de asentamiento) |
| Resultado | **32** entidades · **2 478** municipios · **158 507** colonias = **161 017** registros; 31 860 CP (685 con cero inicial) |
| Inconsistencias | Ninguna estructural; 14 nombres con variante de acentos/mayúsculas (se conservan tal cual) |
| Restricción | Uso particular, sin comercialización ni distribución a terceros: el archivo y los SQL **no están en GitHub** (repositorio público) |

Los conteos se calcularon contra el archivo original (no se asumieron) y coinciden con los de referencia.

## 2. Qué se hizo

- **Generador** `php artisan anfeca:sepomex-sql` (reutiliza `LectorSepomex` y extrae la consolidación a
  `CatalogoSepomex`, compartida con `anfeca:sepomex`): produce `00`–`05` y `SEPOMEX_CATALOGO_COMPLETO.sql`.
- **INSERT idempotentes** por claves naturales, sin identificadores ni datos de ningún ambiente, en una transacción con
  bloqueo, detención ante ambigüedades, ajuste de secuencias sin disminuirlas, `DOMICILIO` verificado intacto y marcas
  para una reversión controlada. Sin tablas, columnas, índices, FK ni funciones permanentes (solo `pg_temp`).
- **Importador** alineado con la misma regla de entidades (reutiliza el id real por nombre; clave oficial solo si está
  libre; secuencias atrasadas corregidas hacia arriba).
- **Servidor**: bloques `04b_diagnostico_sql.sh` y `05b_cargar_sepomex_sql.sh` (alternativa B); documentación de las
  alternativas A/B en `OPERACION_8088_FASE9.md`.
- **Paquete privado** `SEPOMEX_ANFECA_21112026.zip` (16 MB; SHA-256 en
  `evidencias_8088/sepomex_sql/SEPOMEX_ANFECA_21112026.zip.sha256`) con original, SQL, herramientas, evidencias,
  `manifiesto.json`, `SHA256SUMS` y README (`docs/anfeca/SEPOMEX_PAQUETE_SQL.md`).

## 3. Validación real en PostgreSQL 18.6 — 33/33

`evidencias_8088/sepomex_sql/escenarios_resultados.txt` (script `scripts/anfeca_8088/sepomex_sql/probar_escenarios.sh`):

| # | Escenario | Resultado |
|---|---|---|
| 1 | Base nueva vacía | 32/2 478/158 507; huella exacta en las 32 entidades; 8.2 s |
| 2 | 32 entidades previas (otra grafía) | 0 entidades insertadas; nombres e ids intactos |
| 3 | Municipios y colonias previos | Solo faltantes (2 475 / 158 505); previos y colonia ajena conservados |
| 4 | Ids distintos y secuencias atrasadas | Jalisco = 30, Guadalajara = 90000 resueltos; 16 entidades nuevas con ids 33–48; secuencias ≥ máximo |
| 5 | Conflictos (municipio duplicado, clave ocupada, grafía distinta, entidad duplicada) | Los 4 se detienen; base sin cambios |
| 6 | Ejecución consecutiva (maestro y 01/02/03 por separado) | 0 insertados |
| 7 | Error a mitad y conexión terminada | Reversión total (0/0/0) |
| 8 | Domicilios y solicitantes ficticios | Huella idéntica antes y después |
| 9 | Integridad referencial | Huérfanos, duplicados, CP inválidos, FK y secuencias: 0 |
| 10 | Comparación con Artisan | Mismo contenido (158 507 filas, md5 igual) en base vacía y en el escenario 4; cada uno sobre la carga del otro: 0 pendientes |
| 11 | CP con cero inicial | 685 distintos; 01000, 04510, 06600, 09000 correctos |
| 12 | Esquema | 36 tablas, 173 atributos, 35 FK idénticos al SQL oficial |
| R | Reversión `05` | Simula por omisión; aplicada elimina solo lo cargado y conserva lo previo y lo referenciado |

Medidas: tres tablas 18 MB; cliente psql 13 MB; sesión del servidor 62 MB; carga ~8 s.
Ensayo de los bloques 4B y 5B tal cual (base que imita el 8088 con 32 entidades): diagnóstico sin conflictos; carga
0/2 478/158 507 en 8.0 s; repetición 0/0/0; estructura 36/173/35.

**Sin Internet** (`probar_sin_internet.sh`, espacio de red con solo `lo`, sin DNS): servicio interno por CP correcto
(404 para 99999) y prueba de navegador 12/12 (autocompletado, colonias del CP, validación, Mis datos, modificación,
autorización y bloqueo). El código no contiene clientes HTTP externos; Vue.js solo llama rutas internas.

## 4. Pendientes y riesgos

- **Pendiente: ejecutar en 8088** los bloques 1 → 2 → 3 → 4B → 5B → 6 → 7 (`OPERACION_8088_FASE9.md`) y confirmar allí
  los valores reales (ruta, base `certificacion_anfeca`, puerto 5488, rol); los bloques 1 y 4B los muestran.
- Producción: solo con autorización explícita (procedimiento en el README del paquete).
- Riesgo: entidades del 8088 con nombres que no coincidan con SEPOMEX → el bloque 4B lo marca «CONFLICTO» y la carga se
  detiene (requiere decisión, no se corrige automáticamente).
- Riesgo: el rol sin privilegio `TEMP` → el diagnóstico lo muestra; se requiere concederlo o usar la alternativa A.
