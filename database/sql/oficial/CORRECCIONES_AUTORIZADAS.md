# Correcciones autorizadas al modelo oficial de 36 tablas

| Archivo | Papel | SHA-256 |
|---|---|---|
| `anfeca_modelo_powerdesigner_certificacion_anfeca_sin_esquema.sql` | Export original de PowerDesigner. **No se modifica** (trazabilidad). | `5798ce839af01339018536155ab17698c7cb953c7f150e2b9c6b268aab978285` |
| `anfeca_modelo_36_tablas_vigente.sql` | **Referencia vigente**: el original con las correcciones de esta tabla. La usan `anfeca:verificar-esquema`, los instaladores de laboratorio y las pruebas. | ver `anfeca_modelo_36_tablas_vigente.sql.sha256` |

| Id | Tabla | Original | Vigente | Tipo y restricciones | Autorización | Comprobación en 8088 |
|---|---|---|---|---|---|---|
| C-01 | `institucion` | `INS_COORD_REGIONAL_CERT_ACADEMI` | `INST_COORD_REGIONAL_CERT_ACADEMICA` | Sin cambio: `VARCHAR(200) null`, misma posición, sin índices ni FK | Responsable del proyecto, 28/11/2026 («el nombre definitivo y autorizado es `inst_coord_regional_cert_academica`») | Reportada por el bloque 1; confirmar con `diagnostico_diferencias_8088.sql` §2 (no debe aparecer) |

Reglas: la corrección es de nomenclatura (no agrega atributos: siguen siendo 36 tablas, 173 atributos, 36 PK y 35 FK).
En 8088 la columna ya tiene el nombre autorizado: **no se ejecuta ningún ALTER TABLE**. Una base creada con el SQL
original (solo laboratorio) se corrige con
`ALTER TABLE public.institucion RENAME COLUMN ins_coord_regional_cert_academi TO inst_coord_regional_cert_academica;`
(conserva los valores). Cualquier otra diferencia con el original requiere autorización propia y una fila nueva aquí.
