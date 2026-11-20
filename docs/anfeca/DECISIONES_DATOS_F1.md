# Decisiones de datos con los resultados del servidor (F1)

> Rama `integracion/pdm-f1` · 21/11/2026.
> Fuente: consultas A–F de `PLAN_INTEGRACION_FUNCIONAL_PDM.md`, ejecutadas en modo de solo lectura sobre `bd_laravelanfeca_pruebas`.
> **Nada de lo descrito aquí se ha aplicado a esa base.** Las reglas están implementadas y probadas en código (`App\Services\Pdm\NormalizadorCatalogos`, `config/anfeca_pdm.php`). Se usarán en la migración de F2, que primero corre en seco y reporta.

---

## 1. Decisiones actualizadas

| ID | Dato del servidor | Decisión |
|---|---|---|
| **D-4** Longitudes | Calle 20 (PDM 170) · núm. ext. 4 (6) · núm. int. sin valores (4) · ap. paterno 8 (60) · ap. materno 15 (60) · nombres 11 (150) | **No se amplía el PDM**: todos los datos actuales caben. En F3 la validación de Laravel se alineará a los límites del PDM: calle 170, número exterior 6, número interior 4, apellidos 60. Hoy los límites son mayores (200, 20, 20, 100), así que el cambio **endurece** la captura y requiere tu visto bueno antes de F3. Mientras tanto, la migración de F2 **aborta** si encuentra un valor más largo, en lugar de truncarlo. |
| **D-5** CURP | 0 expedientes sin CURP | Sin acción. Se agregó un índice único en `PERSONA.PERS_CURP`; la migración aborta si aparece una CURP nula o duplicada. |
| **D-1 / usuarios** | 5 usuarios sin datos personales; único rol: `solicitante` | Ver sección 3 |
| **D-11** Instituciones | 1 institución, con número de afiliación nulo o de más de 7 caracteres; con nombre y con zona | **No se migra hasta confirmar sus datos con la fuente oficial** (sección 4). Mientras tanto `PERS_ID_INSTITUCION` queda nulo (el PDM lo permite) y la formación conserva la institución en `public`; no se pierde funcionalidad. |

---

## 2. Reglas de normalización de catálogos

**Principio:** solo se aplican equivalencias **verificables**. Todo lo demás queda **en revisión**, con el valor histórico intacto en `public` (y, en F2, en el informe de la migración). **Ningún dato se reemplaza.**

| Regla | Qué hace |
|---|---|
| **N-1** | Igualdad sin distinguir mayúsculas, acentos ni espacios repetidos |
| **N-2** | Alias explícitos: abreviaturas y nombres cortos oficiales de la **misma** entidad (`cdmx` → Ciudad de México, `coahuila` → Coahuila de Zaragoza, `michoacan`, `veracruz`, `estado de mexico`). Lista cerrada en `config/anfeca_pdm.php`. |
| **N-3** | Valores conocidos que **no** pertenecen al catálogo → revisión, con motivo |
| **N-4** | Vacío → nulo si la columna PDM lo admite; si es obligatoria → revisión |
| **N-5** | Formato: número de certificado `^[0-9]{1,7}$`, año `^[0-9]{4}$` |
| **N-6** | Área o tipo de personal vacíos en una solicitud → se toman de `selecciones_certificacion` del **mismo usuario y la misma certificación**. Es la misma regla que ya aplica hoy `RegistroCompletadoController::confirmar` al enviar. Si no coincide → revisión. |

### Resultado sobre los valores reales

| Campo | Valor (registros) | Resultado | Regla |
|---|---|---|---|
| Entidad | `cdmx` (6), `CDMX` (2) | Ciudad de México (clave INEGI 09) | N-2 |
| Entidad | `tlaxcala` (1) | Tlaxcala (29) | N-1 |
| Entidad | `quintana roo` (1) | Quintana Roo (23) | N-1 |
| Entidad | `Monterrey` (1), `monterrey` (1) | **Revisión:** Monterrey es un municipio de Nuevo León. No se infiere la entidad sin confirmar cada registro. | N-3 |
| Entidad | `Entidad de Prueba` (5) | **Revisión:** valor de prueba | N-3 |
| Género | Masculino (7) / Femenino (6) | `M` / `F` | N-1 |
| Género | vacío (4) | Nulo; no se supone un género | N-4 |
| Área | Administración (8), Informática Administrativa (4), Disciplinas vinculadas… (3) | Catálogo `AREA_CERTIFICACION` | N-1 |
| Área | vacío (1) | Se toma de la selección del usuario, o revisión | N-6 |
| Tipo de personal | Tiempo Completo (15) | Catálogo `TIPO_PERSONAL_ACADEMICO` | N-1 |
| Tipo de personal | vacío (1) | Se toma de la selección del usuario, o revisión | N-6 |

**Balance de entidades:** 10 de 17 domicilios se normalizan automáticamente y 7 quedan en revisión. `DOMICILIO.DOMI_ID_ENTIDAD_FEDERATIVA` es obligatoria, así que esos 7 domicilios **no se migran** hasta tu decisión. La persona y su solicitud sí se migran; el domicilio se completa al resolverse.

**Decisión pendiente (D-13):** para los 5 registros «Entidad de Prueba» y los 2 «Monterrey», ¿se corrige el dato en origen, se asigna una entidad confirmada registro por registro, o se dejan sin domicilio en el PDM?

---

## 3. Los 5 usuarios sin datos personales

- `USUARIO.USUA_ID_PERSONA` es **obligatorio** en el PDM, y crear una `PERSONA` sin datos sería inventarla.
- **Se conservan en `public.users`**, que sigue siendo la tabla de autenticación (D-1 A): pueden iniciar sesión igual que hoy.
- **No se crea** `USUARIO` ni `PERSONA` para ellos. La migración de F2 los lista como «pendientes de datos personales».
- Cuando uno complete su registro, la escritura en espejo (F3) creará su `PERSONA` y su `USUARIO`, con el mismo ID que en `users`.

**Consulta G (solo lectura, sin correos ni nombres)**, para saber quiénes son y si tienen trámites:

```sql
SELECT u.id, u.rol, u.created_at::date AS alta,
       (SELECT count(*) FROM public.solicitudes_certificacion s WHERE s.user_id = u.id) AS solicitudes
FROM public.users u
WHERE NOT EXISTS (SELECT 1 FROM public.datos_solicitantes d WHERE d.user_id = u.id)
ORDER BY u.id;
```

Si alguno tiene solicitudes, su solicitud **no puede migrarse**, porque `SOLICITUD.SOLI_ID_PERSONA` es obligatoria. Se reportará.

---

## 4. Datos institucionales que debes confirmar con la fuente oficial

Para la institución registrada (y las que se agreguen), según el padrón oficial de instituciones afiliadas a ANFECA:

| Columna PDM | Obligatoria | Dato a confirmar | Situación actual en Laravel |
|---|---|---|---|
| `INST_NUMERO_AFILIACION` VARCHAR(7) | Sí, única | Número de afiliación oficial, **máximo 7 caracteres** | Nulo o más largo de 7 → **no cumple** |
| `INST_NOMBRE` VARCHAR(300) | Sí | Nombre oficial de la institución | Presente |
| `INST_FACULTAD` VARCHAR(500) | Sí | Facultad, escuela o unidad académica afiliada | **No existe** en Laravel; el campus **no** se usa como facultad |
| `INST_ZONA` VARCHAR(80) | Sí | Zona ANFECA (siglas) | Presente (`zona_id`) |
| `INST_ID_ZONA` (ext.) | No | Se llena desde `zonas` | Presente |
| `INST_CAMPUS` (ext.) | No | Campus o sede | `campus` |
| `INS_COORD_REGIONAL_CERT_ACADEMI` | No | Coordinación regional de certificación académica | No existe; opcional |

**Consulta H (solo lectura; datos institucionales, no personales):**

```sql
SELECT i.id, i.numero_afiliacion, length(i.numero_afiliacion) AS largo, i.nombre, i.campus, i.sede,
       z.siglas AS zona
FROM public.instituciones_afiliadas i LEFT JOIN public.zonas z ON z.id = i.zona_id;
```

---

## 5. Certificados históricos con formatos distintos

**Situación (consulta F):** 16 filas.
- `primera_vez`: 16 con año de 4 dígitos.
- `refrendo_1`: 4 con año válido; el resto vacío.
- `numero_certificado`: 8 válidos (`^[0-9]{1,7}$`) y **8 que no cumplen**.

**Cómo se conservan:**
1. **Las 16 filas completas y sin cambios** van a `HISTORICO_CERTIFICADO_IMPORTACION` (extensión), con el **mismo ID**, todas sus columnas y su vínculo de usuario. No se descarta ninguna fila.
2. **Eventos normalizados** en `HISTORICO_CERTIFICADO`: solo para filas con número válido. Uno por año válido (`PRIMERA_VEZ`, `REFRENDO_1`…), con folio = número de certificado. Esas filas quedan `HCIM_ESTADO_NORMALIZACION = 'NORMALIZADO'`.
3. **Las 8 filas con número no válido** quedan `REVISION`, con el motivo, y sin eventos. `HICE_FOLIO_CERTIFICADO` es obligatorio y no se inventa un folio.
4. **Sin cambio funcional:** hoy el formulario de refrendo tampoco valida esos 8 números, porque exige el mismo patrón.

**Consulta J (solo lectura; muestra el formato, no el número):** cada dígito se ve como `9` y cada letra como `A`.

```sql
SELECT regexp_replace(regexp_replace(coalesce(numero_certificado, '(nulo)'), '[0-9]', '9', 'g'), '[A-Za-z]', 'A', 'g') AS formato,
       count(*)
FROM public.certificados_historicos
WHERE numero_certificado IS NULL OR numero_certificado !~ '^[0-9]{1,7}$'
GROUP BY 1 ORDER BY 2 DESC;
```

Con ese resultado se decide (**D-14**) si existe una transformación verificable, por ejemplo quitar un prefijo fijo o ceros a la izquierda, o si esos registros requieren corrección en la fuente.

---

## 6. Decisiones abiertas

| ID | Tema | Quién |
|---|---|---|
| D-4 (F3) | Endurecer la validación a los límites del PDM | Tú, antes de F3 |
| D-11 | Datos oficiales de la institución | Tú, con el padrón ANFECA |
| D-13 | 7 domicilios con entidad no válida | Tú |
| D-14 | 8 números de certificado con formato distinto (consulta J) | Tú |
| — | Descripciones de `ARTICULO_SUBINDICE`: provisionales («Artículo 14, inciso 14.1»), tomadas del código; confirmar con la normativa | Tú |
