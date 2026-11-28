# Conciliación del modelo físico de 8088 con la referencia vigente

**Referencia vigente:** `database/sql/oficial/anfeca_modelo_36_tablas_vigente.sql` = export original de PowerDesigner
(`anfeca_modelo_powerdesigner_certificacion_anfeca_sin_esquema.sql`, SHA-256 `5798ce83…8285`, conservado sin cambios)
+ correcciones autorizadas (`database/sql/oficial/CORRECCIONES_AUTORIZADAS.md`). **36 tablas, 173 atributos, 36 PK,
35 FK.** `anfeca:verificar-esquema` compara contra esta referencia.

8088 reportó 15 diferencias (36 tablas, 180 atributos, 37 FK). **1 autorizada (C-01) y 13 pendientes** (la 15.ª era la
otra cara de C-01: el nombre anterior «faltante»). Ninguna conciliación se ejecuta en 8088: solo en la copia
independiente `certificacion_anfeca_8090` y en laboratorio. La propuesta se niega a correr sobre `certificacion_anfeca`.

## 1. Corrección autorizada C-01 (resuelta en la referencia)

| Dato | Valor |
|---|---|
| Tabla / columna | `institucion.inst_coord_regional_cert_academica` (antes `ins_coord_regional_cert_academi` en el export) |
| Definición | `VARCHAR(200) null`, misma posición; sin índices, FK ni restricciones propias |
| Autorización | Responsable del proyecto, 28/11/2026: nombre definitivo `inst_coord_regional_cert_academica` |
| En 8088 | Ya existe con el nombre autorizado (bloque 1). **No se ejecuta ningún ALTER TABLE.** Confirmar con la consulta de abajo |
| Código | `Instituciones.php`, `Models/Pdm/Institucion.php`, `SembrarLaboratorioAnfeca.php` usan el nombre autorizado |
| Efecto | La búsqueda de institución de Formación académica deja de fallar con SQLSTATE 42703 |
| Pruebas (laboratorio) | Referencia vigente: código 0; base que imita 8088: C-01 ya no aparece (13 diferencias); base creada con el nombre anterior: se reporta y la propuesta aplica C-01 conservando los datos (código 0); búsqueda de institución correcta |

Comprobación de solo lectura en 8088 (VS Code SSH):

```bash
PGOPTIONS='-c default_transaction_read_only=on' psql -X -h 127.0.0.1 -p 5488 -U anfeca_8088 -d certificacion_anfeca -c "select column_name, data_type, character_maximum_length, is_nullable, ordinal_position from information_schema.columns where table_schema='public' and table_name='institucion' and column_name in ('inst_coord_regional_cert_academica','ins_coord_regional_cert_academi')"
```

Esperado: una sola fila, `inst_coord_regional_cert_academica | character varying | 200 | YES`. Cuando se confirme, C-01
queda **autorizada y resuelta**.

## 2. Origen de las 13 pendientes

Ninguna está en Git (búsqueda sin distinguir mayúsculas en todas las ramas) ni en los exports de PowerDesigner
versionados; ningún código las usa. No consta autorización. `diagnostico_origen_8088.sh` busca la evidencia en el
servidor (posición física de cada columna, registros de PostgreSQL, historiales y archivos).

## 3. Matriz de diferencias pendientes (13)

«Registros» y «no nulos» se obtienen en 8088 con `diagnostico_diferencias_8088.sql` (§6 y §7); hasta entonces figuran
como **por medir** y ninguna se concilia.

| # | Tabla.columna | Definición original (vigente) | Definición física 8088 | Registros / no nulos | Impacto funcional | Correspondencia con el modelo | Propuesta | Riesgo de pérdida | Reversión |
|---|---|---|---|---|---|---|---|---|---|
| P-01 | `convocatoria.conv_anio` | — (no existe) | Atributo adicional | Por medir (§6) | Ninguno: el año se deduce de `conv_nombre` y de las fechas oficiales | Ninguna columna oficial equivalente | Exportar y retirar en 8090 | Alto si tiene valores (quedan en CSV) | Restaurar respaldo o reagregar y recargar desde CSV |
| P-02 | `convocatoria.conv_id_certificacion` | — | Atributo adicional con FK P-12 | Por medir (§6) | Ninguno: la certificación es `conv_id_tipo_convocatoria` | Redundante con `conv_id_tipo_convocatoria` | Exportar, retirar FK y atributo | Ídem | Ídem |
| P-03 | `estudio_profesional.espr_id_area_certificacion` | — | Atributo adicional con FK P-13 | Por medir (§6) | Ninguno en el código; **revisar si los títulos ANUIES (ACT) lo usan** | Sin equivalente | Exportar y retirar; decidir con los datos | Alto si lo usan los títulos ANUIES | Ídem |
| P-04 | `evaluacion.eval_folio_evaluacion` | — | Atributo adicional | Por medir (§6, solo conteos) | Ninguno | Sin equivalente | Exportar y retirar | Ídem | Ídem |
| P-05 | `institucion.inst_campus` | — | Atributo adicional | Por medir (§6) | Ninguno en el código | Sin equivalente (podría formar parte de `inst_facultad` solo con autorización) | Exportar y retirar | Ídem | Ídem |
| P-06 | `institucion.inst_sede` | — | Atributo adicional | Por medir (§6) | Ninguno | Sin equivalente | Exportar y retirar | Ídem | Ídem |
| P-07 | `institucion.inst_numero_afiliacion` | `VARCHAR(7) not null` | `VARCHAR(7) null` | Por medir (§7: nulos) | Búsqueda y asignación de institución | Misma columna, restricción distinta | Restituir NOT NULL si no hay nulos | Ninguno (se detiene si hay nulos) | `ALTER … DROP NOT NULL` |
| P-08 | `institucion.inst_facultad` | `VARCHAR(500) not null` | `VARCHAR(500) null` | Por medir (§7) | Lista de instituciones | Ídem | Ídem | Ninguno | Ídem |
| P-09 | `institucion.inst_zona` | `VARCHAR(80) not null` | `VARCHAR(80) null` | Por medir (§7) | **Alcance por zona de Coordinación regional** («Sin clasificar» si es nula) | Ídem | Ídem; las nulas requieren asignar zona con Coordinación | Ninguno | Ídem |
| P-10 | `solicitud_documento.sodo_url` | — | Atributo adicional | Por medir (§6, solo prefijos) | Ninguno: la ruta se deriva de SOLI/SOCU | Sin equivalente | Exportar y retirar | Alto si tiene rutas | Ídem |
| P-11 | `tipo_convocatoria.tico_tipo_convocatoria` | `VARCHAR(50) not null` | `VARCHAR(150) not null` | Por medir (§7: textos > 50) | Solo lectura de la descripción (las pantallas usan `tico_siglas`) | Misma columna, longitud mayor | Restituir VARCHAR(50) si ningún texto excede | Ninguno (se detiene si exceden) | `ALTER … TYPE VARCHAR(150)` |
| P-12 | FK `fk_convocatoria_certificacion` | — | FK adicional (§4: destino real) | — | Ninguno | No existe en el modelo | Retirar | Ninguno (no guarda datos) | Volver a crearla con la definición de §4 |
| P-13 | FK `fk_estudio_area_certificacion` | — | FK adicional | — | Ninguno | No existe en el modelo | Retirar | Ninguno | Ídem |

### Grupos y dependencias

| Grupo | Diferencias | Dependencias (estructura y código) | Nota |
|---|---|---|---|
| **G-1 Área de los programas** | P-03 + P-13 | FK P-13 → `area_certificacion`; ningún código de la rama la usa; muy probablemente contiene la clasificación ANUIES por área que hoy proviene del JSON | Se analizan juntas. **No se autoriza agregarlas al modelo ni eliminarlas** sin conocer su contenido. Ver `ANUIES_AREAS_8090.md` |
| G-2 Certificación de la convocatoria | P-02 + P-12 | FK P-12 (destino real en §4 del diagnóstico); redundante con `conv_id_tipo_convocatoria` | Se analizan juntas |
| G-3 Restricciones de `institucion` | P-07, P-08, P-09 | Búsqueda de institución; P-09 define el alcance por zona («Sin clasificar» si es nula) | Restituir NOT NULL solo sin nulos |
| Individuales | P-01, P-04, P-05, P-06, P-10, P-11 | Sin índices, vistas, disparadores ni código que las use (diagnóstico §5 lo confirma en 8088) | — |

**Pendientes: 13** (9 decisiones: 3 grupos y 6 individuales). Se actualizará con los conteos reales de 8088. Ninguna columna con información se elimina sin
identificar antes su contenido y decidir una solución.

## 4. Propuesta (solo en la copia 8090 o en laboratorio)

`scripts/anfeca_8088/servidor/conciliacion/propuesta_conciliacion_8088.sql`: exporta a CSV con huella SHA-256 los
atributos adicionales; aplica C-01 solo si una base conserva el nombre anterior; restituye NOT NULL y VARCHAR(50) solo
si ningún dato lo impide; retira FK y atributos adicionales (con datos, solo con `-v retirar_con_datos=si`); comprueba
36/173/35; **simula y revierte por omisión**. Laboratorio: `prueba_conciliacion.sh` 12/12 y
`prueba_verificar_esquema.sh` 11/11, con la referencia vigente.

## 5. Respaldo y reversión

Antes de cualquier conciliación en la copia 8090: `pg_dump -Fc` de la copia, restauración comprobada en otra base nueva
y conteos idénticos (procedimiento del bloque 2). Reversión: restaurar ese respaldo en una base nueva y apuntar
`DB_DATABASE` de 8090 a ella; los CSV permiten reincorporar atributos retirados. 8088 no se toca.

Mientras haya diferencias no autorizadas, los bloques que escriben se detienen (`exigir_esquema_oficial`). Solo
continúan con un archivo de diferencias autorizado por escrito que coincida exactamente
(`DIFERENCIAS_AUTORIZADAS=<archivo>`).
