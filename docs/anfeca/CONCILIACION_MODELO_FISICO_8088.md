# Conciliación del modelo físico de 8088 con el modelo oficial

Referencia autorizada: `database/sql/oficial/anfeca_modelo_powerdesigner_certificacion_anfeca_sin_esquema.sql`
(SHA-256 `5798ce83…8285`): **36 tablas, 173 atributos, 35 llaves foráneas**. 8088 reporta 36 tablas, 180 atributos y
37 llaves foráneas: 15 diferencias. **Nada se ha modificado en 8088**: este documento entrega el diagnóstico, la
propuesta y su procedimiento; la modificación física requiere autorización posterior.

## 1. Origen: lo que muestra el historial

- **Ninguna de las 15 diferencias está en Git.** Se buscaron los 11 identificadores (`conv_anio`,
  `conv_id_certificacion`, `espr_id_area_certificacion`, `eval_folio_evaluacion`, `inst_coord_regional_cert_academica`,
  `inst_sede`, `sodo_url`, `fk_convocatoria_certificacion`, `fk_estudio_area_certificacion`, …), sin distinguir
  mayúsculas, en el historial completo de todas las ramas (`git log --all -i -G`): 0 commits. `inst_campus` solo aparece
  en `1bd80a7` (extensiones de una fase anterior sobre el esquema `anfeca_modelo`, retirada), con otra definición y sin
  `inst_sede`.
- **No hay una versión posterior de PowerDesigner en el repositorio.** Los dos exports versionados
  (`anfeca_certifica_1.original.sql` de `0126d22` y el SQL oficial corregido) coinciden con la referencia en estas
  tablas: `INS_COORD_REGIONAL_CERT_ACADEMI VARCHAR(200)`, `TICO_TIPO_CONVOCATORIA VARCHAR(50)`, `INST_NUMERO_AFILIACION`,
  `INST_FACULTAD` e `INST_ZONA` `not null`, y ninguno de los atributos adicionales. Las 7 ramas que contienen el SQL
  oficial lo tienen con la misma huella.
- **Ningún código depende de los atributos adicionales** (rama `integracion/fase9-8088` ni las ramas de 8088).
- Conclusión provisional: los cambios se aplicaron **directamente en la base de 8088**, fuera de Git (manualmente o con
  un script no versionado), o la base se creó con un SQL distinto no entregado. `diagnostico_origen_8088.sh` busca la
  evidencia en el servidor (posición física de cada columna, registros de PostgreSQL, historiales y archivos SQL).
- **No consta autorización** de estos cambios en el repositorio ni en la documentación. Si existe una versión posterior
  aprobada del modelo, debe entregarse para evaluarla; mientras tanto la referencia autorizada es la de 36/173/35.

## 2. Matriz de las 15 diferencias

«Datos» y «origen exacto» se completan con el diagnóstico en 8088 (secciones indicadas de
`diagnostico_diferencias_8088.sql`).

| # | Objeto | Diferencia | Dependencias en la aplicación | Información (diag.) | Riesgo de conciliar | Propuesta |
|---|---|---|---|---|---|---|
| 1 | `convocatoria.conv_anio` | Atributo adicional | Ninguna (el año está en `conv_nombre` y fechas oficiales) | §6 | Pérdida si tiene datos | Exportar a CSV y retirar |
| 2 | `convocatoria.conv_id_certificacion` | Atributo adicional | Ninguna (la certificación es `conv_id_tipo_convocatoria`) | §6 | Ídem; tiene FK (#14) | Exportar, retirar FK y atributo |
| 3 | `estudio_profesional.espr_id_area_certificacion` | Atributo adicional | Ninguna | §6 | Ídem; tiene FK (#15) | Exportar, retirar FK y atributo |
| 4 | `evaluacion.eval_folio_evaluacion` | Atributo adicional | Ninguna | §6 (solo conteos) | Ídem | Exportar y retirar |
| 5 | `institucion.inst_coord_regional_cert_academica` | Atributo adicional | **La búsqueda de institución usa el nombre oficial (#11)** | §6 | Ninguno: se renombra con sus datos | **Renombrar** a `ins_coord_regional_cert_academi` |
| 6 | `institucion.inst_campus` | Atributo adicional | Ninguna | §6 | Pérdida si tiene datos | Exportar y retirar |
| 7 | `institucion.inst_sede` | Atributo adicional | Ninguna | §6 | Ídem | Exportar y retirar |
| 8 | `institucion.inst_numero_afiliacion` | Permite nulos (oficial: not null) | Búsqueda y asignación de institución | §7 (nulos) | Falla si hay nulos | Restituir NOT NULL (detiene si hay nulos) |
| 9 | `institucion.inst_facultad` | Permite nulos | Lista de instituciones | §7 | Ídem | Restituir NOT NULL |
| 10 | `institucion.inst_zona` | Permite nulos | **Alcance por zona de Coordinación** | §7 | Ídem | Restituir NOT NULL |
| 11 | `institucion.ins_coord_regional_cert_academi` | **Atributo oficial faltante** | **`Instituciones.php`: la búsqueda de institución falla (SQLSTATE 42703)** | §8 | — | Se resuelve con #5 |
| 12 | `solicitud_documento.sodo_url` | Atributo adicional | Ninguna (la ruta del expediente se deriva de SOLI/SOCU) | §6 (solo prefijos) | Pérdida si tiene datos | Exportar y retirar |
| 13 | `tipo_convocatoria.tico_tipo_convocatoria` | VARCHAR(150) (oficial: 50) | Solo lectura de la descripción (las pantallas usan `tico_siglas`) | §7 (> 50) | Falla si hay textos > 50 | Restituir VARCHAR(50) (detiene si exceden) |
| 14 | FK `fk_convocatoria_certificacion` | FK adicional | Ninguna | §4 (definición) | Ninguno (no se usa) | Retirar |
| 15 | FK `fk_estudio_area_certificacion` | FK adicional | Ninguna | §4 | Ninguno | Retirar |

Hallazgo prioritario: con las diferencias #5/#11, la búsqueda de institución de **Formación académica** de la versión
actual falla en 8088. Confirmarlo con la sección 8 del diagnóstico y con
`storage/logs/laravel*.log` (paso 6 de `diagnostico_origen_8088.sh`).

## 3. Propuesta de conciliación (sin perder información)

Recomendación: **volver al modelo autorizado** (36/173/35), porque ninguna funcionalidad usa los atributos adicionales,
el nombre faltante rompe una funcionalidad vigente y las decisiones definitivas exigen conservar el modelo oficial.
Alternativa, solo si existe una versión posterior aprobada de PowerDesigner que incluya estos atributos: actualizar la
referencia con esa versión aprobada (nunca editar el SQL oficial para ocultar diferencias) y evaluar su impacto.

`scripts/anfeca_8088/servidor/conciliacion/propuesta_conciliacion_8088.sql`:

1. Exporta a CSV, antes de cualquier cambio, cada atributo adicional con la llave de su fila, con huellas SHA-256.
2. Renombra #5 → #11 (conserva los datos y repara la búsqueda de institución).
3. Restituye NOT NULL (#8–#10) y VARCHAR(50) (#13) **solo si ningún dato lo impide**; si lo impide, se detiene y se
   requiere decidir los valores (no se inventan ni se truncan).
4. Retira las FK #14–#15 y los atributos #1–#4, #6, #7, #12. Si contienen datos, se detiene salvo autorización expresa
   (`-v retirar_con_datos=si`); sus datos quedan en los CSV del paso 1.
5. Comprueba 36/173/35 dentro de la transacción. **Por omisión simula y revierte**; aplica solo con `-v confirmar=si`.

Validación en laboratorio (base que reproduce exactamente las 15 diferencias, `simular_diferencias_8088.sql`):
`prueba_conciliacion.sh` **12/12** — simula sin cambios; se detiene con datos sin autorización, con nulos y con textos
largos; con autorización deja el esquema en código 0 y los datos en CSV con huella; conserva la coordinación regional;
repetible; la búsqueda de institución pasa de 42703 a funcionar. `prueba_verificar_esquema.sh` **11/11**.

## 4. Procedimiento seguro (VS Code SSH), cuando se autorice

Diagnóstico (ahora, solo lectura):

```bash
R=/home/servidorweb/anfeca_8088_codigo; git -C $R fetch origin integracion/fase9-8088 && C=$(git -C $R rev-parse --short FETCH_HEAD) && mkdir -p ~/anfeca_fase9_scripts_$C && git -C $R archive FETCH_HEAD scripts/anfeca_8088 | tar -x -C ~/anfeca_fase9_scripts_$C && echo "Scripts en ~/anfeca_fase9_scripts_$C"
```
```bash
cd ~/anfeca_fase9_scripts_*/scripts/anfeca_8088/servidor/conciliacion && sudo -E SALIDA=/home/servidorweb/anfeca_fase9/conciliacion_diagnostico_$(date +%Y%m%d_%H%M) bash diagnostico_origen_8088.sh
```

Aplicación (solo con autorización escrita):

1. **Respaldo verificado** (bloque 2: `pg_dump -Fc`, restauración en una base **nueva**, conteos idénticos, huellas):
   ```bash
   cd ~/anfeca_fase9_scripts_*/scripts/anfeca_8088/servidor/fase9 && sudo -E bash 02_respaldo.sh
   ```
2. **Ensayo sobre la base restaurada** (`certificacion_anfeca_restauracion_<fecha>`, nunca la institucional):
   ```bash
   mkdir -p ~/anfeca_conciliacion_ensayo && chmod 700 ~/anfeca_conciliacion_ensayo && cd ~/anfeca_fase9_scripts_*/scripts/anfeca_8088/servidor/conciliacion && psql -X -h 127.0.0.1 -p 5488 -U anfeca_8088 -d certificacion_anfeca_restauracion_<fecha> -v ON_ERROR_STOP=1 -v exportar=$HOME/anfeca_conciliacion_ensayo -v confirmar=si -f propuesta_conciliacion_8088.sql 2>&1 | tee ~/anfeca_conciliacion_ensayo/ensayo.log
   ```
3. **Simulación en la base real** (no cambia nada) y revisión de los CSV:
   ```bash
   mkdir -p ~/anfeca_conciliacion && chmod 700 ~/anfeca_conciliacion && cd ~/anfeca_fase9_scripts_*/scripts/anfeca_8088/servidor/conciliacion && psql -X -h 127.0.0.1 -p 5488 -U anfeca_8088 -d certificacion_anfeca -v ON_ERROR_STOP=1 -v exportar=$HOME/anfeca_conciliacion -f propuesta_conciliacion_8088.sql 2>&1 | tee ~/anfeca_conciliacion/simulacion.log
   ```
4. **Aplicación** en ventana de mantenimiento (`php artisan down` en `/var/www/ANFECA_8088`), con `-v confirmar=si`
   (y `-v retirar_con_datos=si` solo si la autorización lo indica), y verificación:
   `php artisan anfeca:verificar-esquema` debe terminar con código 0.
5. **Reversión**: la base anterior se conserva en el respaldo. Restaurarlo en una base nueva y apuntar `DB_DATABASE` a
   ella (bloque 8, `MODO=datos`); los registros creados después del respaldo se exportan de la base actual antes de
   cambiar. Los CSV del paso 1 permiten reincorporar los atributos retirados si así se decidiera.

Mientras no se concilie, los bloques 3, 4, 4B, 5, 5B y 6 se detienen ante estas diferencias. Solo continúan si existe un
archivo de diferencias **autorizado por escrito** cuyo contenido coincide exactamente con las diferencias vigentes
(`DIFERENCIAS_AUTORIZADAS=<archivo>`); no es la vía recomendada.
