# Inventario consolidado de pendientes y criterios de cierre de la fase 6.3.4 · 2026-11-26

**Commit de referencia:** `9b21c42` (`desarrollo/modulo-coordinacion`; `ensayo/principal` sin cambios en `ff8f826`). **Actualizado el 2026-11-26** tras la activación del catálogo en el ensayo (HEAD `8d9e5df` y commits de documentación posteriores).

> **Numeración.** En `normativa/ANALISIS_BRECHAS_Y_DECISIONES.md` §13, «6.3.4» designaba la administración de convocatorias. El responsable denomina **6.3.4** a la fase actual (catálogo de posgrados y verificaciones complementarias). Se propone conservar esa denominación y renumerar convocatorias como **6.3.6**. **Decisión pendiente.**

**Estados utilizados** (un commit **no** implica que la función esté concluida):

| Estado | Significado |
|---|---|
| **Impl.** | Implementado |
| **Prob.** | Probado en aislamiento o en bases desechables |
| **Despl.** | Código presente en 8086 |
| **Activ.** | Activo en 8086 |
| **Acept.** | Aceptado por el responsable |
| **Pend. desarrollo** | Falta desarrollarlo |
| **Pend. autorización** | Falta la autorización del responsable |
| **Bloq.** | Bloqueado |

## 1. Inventario

| ID | Fase | Módulo | Descripción | Prioridad | Estado | Dependencias | Riesgo | Evidencia | ¿Autoriz.? | Criterio de aceptación |
|---|---|---|---|---|---|---|---|---|---|---|
| CAT-01 | 6.3.4 | Solicitantes | Pantalla de Formación con catálogo (Nivel/Área 50/50, título 100 %, búsqueda, lista flotante, captura manual) | Alta | Impl. · Prob. · Despl. · **Activ. en ensayo (26-nov)** · Aceptación visual **pendiente** | — | Bajo | Validación en 8086: 25/25; `CatalogoPosgrados*Test` (23) | Sí (aceptación) | Visto bueno del responsable en 8086 con `aceptacion.catalogo@example.test` |
| CAT-02 | 6.3.4 | Catálogo | Clasificación de los 1794 títulos; 572 por revisar | Alta | Impl. · Prob. · **Pend. autorización** | — | Medio (clasificación administrativa) | Matriz y resumen por bloques (A 182 · B 219 · C 171) | Sí | Columna «Clasificación autorizada» completada o decisiones por bloque registradas |
| CAT-03 | 6.3.4 | Base de datos | Migración aditiva (4 columnas + CHECK) | Alta | Impl. · Prob. · **Aplicada y verificada en 8086 (26-nov)** | — | Bajo | `aplicacion_catalogo_posgrados_20261126_133618` (0 fallas) | Hecho | — |
| CAT-04 | 6.3.4 | Catálogo | Instalar el JSON en `storage/app/private/catalogos` (0640, grupo apache) con su huella | Alta | **Instalado (26-nov)** · huella verificada | — | Bajo | `PROPUESTA_CATALOGO_POSGRADOS.md` §0 | Hecho | — |
| CAT-05 | 6.3.4 | Frontend | Compilar y servir la compilación con el catálogo en 8086 | Alta | **Servida (26-nov)** `app-CYBBpN_p.js`; anterior restaurable | — | Bajo | `desplegar_compilacion.sh` | Hecho | — |
| CAT-06 | 6.3.4 | Solicitantes | Activar `ANFECA_CATALOGO_POSGRADOS=true` en 8086 | Alta | **Activ. en ensayo (26-nov)** · pendiente de aceptación | CAT-01 | Medio | Estado efectivo `activo` | Hecho (ensayo) | Aceptación del responsable; reversión por bandera disponible |
| CAT-07 | 6.3.4 | Presentación | Presentador único del título (acuse, expediente y futuros reportes) | Media | **Pend. desarrollo** | — | Bajo | `TRAZABILIDAD_Y_VERIFICACIONES_CATALOGO.md` §2 | No (cambio menor) | Una sola regla; acuse y expediente idénticos |
| CAT-08 | 6.3.4 | Concurrencia | Evaluar la protección de la bandera desactivada con la fila bloqueada | Baja | Pend. desarrollo | — | Bajo | Ídem §6 | No | Prueba de concurrencia en PG desechable |
| CAT-09 | — | PDM | Extender `anfeca_compat` y el modelo PDM a las 4 columnas | Alta **antes de activar el PDM** | Pend. desarrollo | CAT-03 | **Alto** si se activa el PDM sin esto (se perderían los títulos) | Ídem §1 (nota PDM) | Sí | Espejo PDM con título, origen, clave y versión |
| LIC-01 | 6.3.4 | Catálogo | Catálogo preliminar de licenciaturas (ANUIES 2025-2026: 5 271; consolidado 2000-2026: 9 386) | Alta | Impl. (privado) · Prob. · resúmenes por familia listos · **Clasificación pendiente** (1 417 / 2 319) | — | Medio | `PROPUESTA_CATALOGO_LICENCIATURAS.md` §27 | Sí | Decisiones por bloque registradas |
| LIC-02 | 6.3.4 | Catálogo | Denominaciones históricas 2000-2001 a 2024-2025 | Media | **Hecho (privado)**: 25 ciclos (23 MB), consolidado sin colisiones | LIC-01 | Bajo | Ídem §27 | — | — |
| LIC-03 | 6.3.4 | Base de datos | Migración aditiva de licenciatura y declaración (5 columnas y 2 CHECK) | Alta | Impl. · Prob. (PG desechable 18/18) · **Aplicada en ensayo (26-nov, 0 fallas)** | Respaldo < 2 h | Bajo | Ídem §19 | Sí | `aplicar_licenciaturas_posgrado.sh` y verificación sin fallas |
| LIC-04 | 6.3.4 | Solicitantes | Selector de licenciaturas en el origen y casilla de posgrado (2 escenarios) | Alta | Impl. · Prob. (13 + 25 visuales + 8 rollback) · **Desplegado y activo en ensayo (26-nov, a6a79e5, `app-DYxc-NO1.js`)** · aceptación en 8086: 23/23 + 12/12 + 5/5 · banderas `ANFECA_CATALOGO_LICENCIATURAS` y `ANFECA_DECLARACION_POSGRADO` activas en 8086 · aceptación del responsable **pendiente** | LIC-03; D-LIC-1 | Medio | Ídem §18–26 | Sí | Aceptación visual del responsable |
| LIC-05 | — | Normativa | Revisión institucional de la regla 1:1 área ↔ certificación para licenciaturas interdisciplinarias | Media | Pend. decisión de la CNCA | — | Medio | Ídem §8, §11 | Sí | Criterio documentado |
| LIC-06 | 6.3.4 | Normativa | D-LIC-1: título del catálogo clasificado en un área distinta de la que admite la certificación (hoy se detiene con explicación) | Alta | **Pend. decisión de la CNCA** | LIC-01 | Medio | Ídem §24 | Sí | Opción A, B o C registrada |
| LIC-07 | 6.3.4 | Refrendo | Completar el nombre exacto de la licenciatura en refrendos que solo guardan el área | Media | Pend. decisión | LIC-04 | Bajo | Ídem §25 | Sí | — |
| ET2-01 | Etapa 2 | Solicitantes | Multigrado (ver `REQUERIMIENTOS_ETAPA_2.md`) | — | **Fuera de alcance** | Etapa 1 cerrada | — | `REQUERIMIENTOS_ETAPA_2.md` | Sí | — |
| UX-01 | 6.3.4 | Solicitantes | Mostrar `session('error')` en Certificación y en Mis datos (bloqueos sin explicación) | **Alta** | Pend. desarrollo | — | Medio (confusión del solicitante) | `DIAGNOSTICOS_PENDIENTES_20261126.md` §B | Sí | Casos B.5 con mensaje visible |
| UX-02 | 6.3.4 | Sitio | `APP_LOCALE=es` y traducciones completas (mensajes genéricos en inglés) | Alta | Pend. autorización (`.env`) | — | Bajo | Ídem A.2-R3; `TRAZABILIDAD…` §5 | Sí | Ningún mensaje en inglés en los flujos del solicitante |
| UX-03 | 6.3.4 | Certificación | `Rule::in` para tipo de personal y área (alinear con la interfaz) | Media | Pend. desarrollo | — | Bajo | Ídem §B.3 | Sí | POST manipulado rechazado |
| REC-01 | Pendiente previo | Autenticación | Recuperación de contraseñas: el correo no sale (`MAIL_MAILER=array`), `APP_URL` incorrecta y notificación en inglés | **Alta** | Diagnosticado · **Bloq.** (falta configuración de correo) | Decisión de SMTP | Alto (función inoperante) | `DIAGNOSTICOS_PENDIENTES_20261126.md` §A | Sí | Casos A.4 con `Notification::fake()`; prueba manual con `MAIL_MAILER=log` |
| REC-02 | Pendiente previo | Coordinación | Recuperación para cuentas de coordinación | Media | Pend. decisión | REC-01 | Medio | Ídem A.2-R4 | Sí | Procedimiento definido y probado |
| ZON-01 | 6.3.3 | Coordinación | Normalizar zonas (7 filas, `zona_id` en 3 instituciones) | Alta | Impl. (SQL) · **Pend. autorización** | Respaldo | Bajo | `ZONAS_DIAGNOSTICO_Y_NORMALIZACION.md` §4 | Sí | Etiquetas iguales y resolución «normalizada» |
| ZON-02 | 6.3.3 | Coordinación | Asignar coordinadores regionales | Alta | **Pend. autorización** · Bloq. por el directorio oficial | ZON-01 | Medio | Ídem §5 | Sí | Cada regional ve solo su zona |
| REV-01 | 6.3.0–6.3.1 | Coordinación | Activar la revisión documental | Alta | Impl. · Prob. · Despl. · migración **aplicada** · **No activ.** | ZON-01, 02 (alcance regional) | Medio | `PERSISTENCIA_REVISION_DOCUMENTAL_FASE_6_3_1.md` | Sí | Procedimiento §13 sin fallas |
| COO-01 | 6.3.2 | Coordinación | Zonas, visor PDF, seguimiento e indicadores | Media | Impl. · Prob. · Despl. · Activ. · **Aceptación pendiente** | — | Bajo | Docs de 6.3.2 | Sí (aceptación) | Visto bueno |
| MIG-01 | — | Base de datos | Migración ajena `escuela_facultad` (pendiente en 8086) | Media | Pend. decisión | — | Medio | Decisión 9 de `ANALISIS_BRECHAS…` | Sí | Decisión registrada |
| PDM-01 | F1–F5 | PDM | Activación del PDM | — | Impl. (otra rama) · **No activ.** | CAT-09 | Alto | Docs F1–F5 | Sí | Fuera del alcance actual |
| NOR-01 | 6.3.3 | Solicitantes | Inciso 14 (eméritos), 13.1 y 14.4 | Media | Pend. aclaración de la CNCA | — | Medio | `ANALISIS_BRECHAS…` 3 y 4 | Sí | Decisión registrada |
| NOR-02 | Etapa 2 | Solicitantes | Posgrado opcional y reutilización de documentos históricos en refrendos | Media | **No anticipar** (regla de la etapa 1 vigente) | Decisión | — | DOC-02 | Sí | — |
| DES-01 | — | Despliegue | Sustituir las pruebas dentro del sitio en `desplegar_f45.sh` por el árbol aislado | Media | Pend. desarrollo | — | Medio | Decisión 10 | Sí | Despliegue sin pruebas en `/var/www` |
| OPS-01 | — | Servidor | Bloquear la descarga automática de GNOME Software para todos los usuarios (`serverdev`) | Alta | Pend. ejecución (sudo) | — | Alto (llenado del disco) | `MANTENIMIENTO_ALMACENAMIENTO_20261126.md` §3 | Sí | `gsettings` = false para todos; caché de PackageKit en 0 tras 7 días |
| OPS-02 | — | Servidor | Ampliar el disco de 20 a 40 GB y la swap | Media | **Hecho (26-nov)**: disco 50 GiB, p3 48 GiB, root 44 GiB (32 GiB libres), swap 2 GiB, ≈ 2 GiB libres en el VG | — | Bajo | Ídem §7 | — | Cerrado |
| OPS-03 | — | Servidor | Retirar VS Code Server sin uso al actualizar el cliente | Baja | Pendiente operativo | — | Bajo | Ídem §6 | No | Una sola versión |
| P4/P9 | Previo | Pruebas | `PARCHES_PENDIENTES.md`: `/registro` (hoy 302 en 8086) y `ExampleTest` | Baja | Revisar su vigencia | — | Bajo | `PARCHES_PENDIENTES.md` | No | Documento actualizado |
| F64 | 6.4 | Evaluación | Instrumentos, comisiones y evaluadores | — | **No iniciar** | Instrumentos 2027 | — | `ANALISIS_PDM_PREVIO_FASE_6_4.md` | Sí | — |

## 2. Criterios de cierre de la fase 6.3.4

| Criterio | Estado | ¿Bloquea el cierre de 6.3.4? | ¿Bloquea el inicio de 6.4? |
|---|---|---|---|
| Aceptación visual de la nueva Formación (CAT-01) | Disponible en 8086 (la demo 8099 se retiró: el túnel no funcionó desde Windows) | **Sí** | No |
| Decisión sobre la clasificación (CAT-02), al menos los bloques A y B | Pendiente | **Sí** (antes de activar) | No |
| Migración aditiva aplicada y verificada en 8086 (CAT-03) | ✔ Aplicada (26-nov) | **Sí** (si se decide activar en esta fase) | No |
| Instalación del JSON (CAT-04) | ✔ Instalado (26-nov) | Sí (ídem) | No |
| Compilación servida (CAT-05) | ✔ Servida (26-nov) | Sí (ídem) | No |
| Activación de la bandera (CAT-06) | ✔ Activada en ensayo (26-nov) | Sí, si la fase incluye la activación; si no, queda como pendiente independiente | No |
| Normalización de zonas (ZON-01) y asignación de coordinadores (ZON-02) | Pendiente | No (pendientes independientes de 6.3.3) | **Sí**: la evaluación regional requiere el alcance por zona |
| Activación de la revisión documental (REV-01) | Pendiente | No | **Sí**: la evaluación parte de expedientes revisados |
| Seguridad del catálogo (endpoint, roles, manipulación, archivo privado) | ✔ Probada | — | No |
| Regresión | ✔ 182 pruebas SQLite (26-nov); PG revisión 21/21 y PDM 69/69 (25-nov, sin cambios de esquema desde entonces); migración PG 15/15 | Repetir tras cualquier cambio de código | Sí (siempre) |
| Mensajes visibles de error (UX-01) y locale `es` (UX-02) | Pendiente | Recomendado | No |
| Recuperación de contraseñas (REC-01) | Bloqueada por la configuración de correo | No (independiente, pero de prioridad alta) | No |
| Extensión PDM del título (CAT-09) | Pendiente | No | No (solo antes de activar el PDM) |

**Para iniciar 6.4 se requieren, como mínimo:**
- ZON-01 y ZON-02;
- REV-01 activada y aceptada;
- los instrumentos de evaluación 2027.

El catálogo de posgrados **no bloquea** 6.4.
