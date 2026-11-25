# Módulo de Coordinación · Fase 6.0 · Diagnóstico (solo lectura)

Fecha: 2026-11-24 · Ambiente: `/var/www/ANFECA_ENSAYO` (8086) · Base `bd_anfeca_ensayo_pruebas_pg`
· Rama base `ensayo/principal` @ `ff8f826` · PDM: apagado.

## 1. Ambientes

| Ruta | Situación | Uso |
|---|---|---|
| `/var/www/ANFECA_ENSAYO` (8086) | Sistema vigente, Git independiente | **Ambiente de desarrollo autorizado** |
| `/var/www/anfeca` | Prototipo de octubre (rama `main`, base `bd_anfeca`), no publicado por Apache | No se toca |
| `/var/www/ANFECA` (8085), `/var/www/ANFECA_PRUEBAS` (8084) | Protegidos | No se tocan |

La rama remota `desarrollo/anfeca-pruebas` (`0b9c298`) **no** coincide con `ensayo/principal` (`ff8f826`).

## 2. Datos existentes (conteos, sin datos personales)

| Indicador | Valor |
|---|---|
| Usuarios | 3 (todos `rol = solicitante`) |
| Datos personales / autorizados | 3 / 3 |
| Solicitudes iniciadas (borrador) | 0 |
| Solicitudes enviadas | 3 (1 inicial, 2 refrendos; 1 por art. 14) |
| Certificación | ANFECA = 3 |
| Instituciones afiliadas | 3, con `zona_anfeca` en texto libre y `zona_id` **nulo** |
| `zonas`, `coordinador_zona`, `roles`, `privilegios` | vacías |
| `anfeca_modelo` (PDM) | 44 tablas; convocatoria, evaluación, dictamen, acta y certificado **vacías** |

## 3. Componentes

| Componente | Situación actual | Reutilizable | Cambio propuesto | Riesgo |
|---|---|---|---|---|
| Autenticación | Sesión `web`, login con limitador | Sí | Redirección por rol tras el login | Bajo |
| Roles | `users.rol` (`varchar(20)`, por omisión `solicitante`); `roles`/`privilegios` sin uso | Sí (`users.rol`) | `coordinador_nacional`, `coordinador_zona`, `solicitante`; middleware y Gates | Medio |
| Zonas | `zonas` vacía; `instituciones_afiliadas.zona_id` (FK) nulo | Sí | Cargar catálogo **oficial** y asignar `zona_id` | Alto: sin catálogo no hay aislamiento real |
| Coordinador–zona | `coordinador_zona(user_id, zona_id)` único por par | Sí | Asignaciones por cuenta | Medio |
| Solicitud → zona | solicitud → `formaciones_academicas_solicitud.institucion_afiliada_id` → `instituciones_afiliadas.zona_id` → `zonas` | Sí | Filtro de alcance en el servidor | Medio: sin formación no hay zona |
| Estados de evaluación | Solo `borrador` y `enviado` | No | «Pendiente de implementación» hasta 6.4 | Bajo |
| Convocatoria | Sin relación con la solicitud; PDM vacío | No | Filtro pendiente | Medio |
| Documentos | Verificación por propietario en el módulo de solicitantes | Parcial | Descarga de coordinación con autorización por zona | Alto si no se aísla |
| Evaluación/Dictamen/Acta/Certificado | Solo en el PDM (vacío, apagado) | Por decidir | Análisis comparativo antes de 6.4 | Alto |
| Frontend | Blade + Vue montado por elemento, Vite, estilos institucionales | Sí | Layout y componentes de coordinación | Bajo |
| Policies/Gates | No existen | — | Crearlas | Bajo |

## 4. Catálogo de zonas (búsqueda del 2026-11-24)

Se revisaron scripts SQL (`database/sql/anfeca_modelo/*`), modelo PowerDesigner y su catálogo JSON,
migraciones, documentación (`docs/anfeca`), certificados históricos y las instituciones afiliadas.

**No existe un catálogo oficial de zonas ANFECA en el proyecto.** Únicos valores encontrados:

| Institución (id) | `zona_anfeca` (texto libre) | Catálogo oficial | Correspondencia |
|---|---|---|---|
| 1 | Zona 6 - Sur | no disponible | **sin comprobar** |
| 2 | Zona 4 - Centro Occidente | no disponible | **sin comprobar** |
| 3 | Zona 3 - Centro | no disponible | **sin comprobar** |

`certificados_historicos.zona` está vacío en los 2 registros. Por instrucción, **no se cargó la tabla
`zonas` ni se asignó `zona_id`**; el aislamiento por zona se valida en bases desechables.

## 5. Inconsistencias (no corregidas)

1. Zona en texto en las instituciones, sin `zona_id` ni catálogo.
2. Tablas `roles`/`privilegios` creadas pero sin uso (la lógica real usa `users.rol`).
3. Sin cuentas de coordinación antes de esta intervención.
