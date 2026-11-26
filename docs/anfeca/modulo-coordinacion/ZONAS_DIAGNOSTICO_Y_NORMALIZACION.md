# Zonas ANFECA: diagnóstico, corrección implementada y propuesta de normalización

**Corte:** 2026-11-25. **Base:** `bd_anfeca_ensayo_pruebas_pg` (8086), consultada en **solo lectura**. **Fuente oficial:** directorio de la ANFECA (ver `docs/anfeca/normativa/INVENTARIO_DOCUMENTAL.md` §4).

## 1. Causa del problema

| Hallazgo | Evidencia |
|---|---|
| El catálogo `zonas` está **vacío** (0 filas) | En la fase 6.1 se decidió no crear el catálogo sin una fuente oficial («no inventar») |
| Por lo tanto, `instituciones_afiliadas.zona_id` es **nulo** en las 3 instituciones | `count(zona_id) = 0` |
| Pero `instituciones_afiliadas.zona_anfeca` **sí** tiene el dato institucional cargado: «Zona 6 - Sur», «Zona 4 - Centro Occidente», «Zona 3 - Centro» | Coincide exactamente con la denominación oficial y con la entidad de cada institución |
| `coordinador_zona` está vacío | Los coordinadores regionales (zona3, zona4, zona6) no tienen asignación |
| `certificados_historicos.zona` es nulo en los 2 certificados | La zona histórica no se cargó |
| La interfaz mostraba «Pendiente de clasificación» porque solo leía `zona_id` y `zonas.siglas` | Ignoraba el dato institucional existente |

**No se requiere una nueva carga de información para mostrar la zona.** Para la autorización regional se requiere la normalización (§4) y la asignación de coordinadores (§5).

Las columnas aparecían duplicadas porque existen `public.instituciones_afiliadas` (tabla) y la vista `anfeca_compat.instituciones_afiliadas` (capa del PDM, apagado).

## 2. Expedientes existentes

| Núm. | Folio | Institución | Afiliación | zona_anfeca | zona_id | Zona histórica | Zona actual (resuelta) | Fuente | Corrección propuesta |
|---|---|---|---|---|---|---|---|---|---|
| 1 | 3 | Universidad Autónoma de Chiapas (Campus I, FCA) | 9806001 | Zona 6 - Sur | NULL | No aplica (primera vez) | **Zona 6 - Sur** | `zona_anfeca`; entidad Chiapas = zona 6 oficial | `zona_id` → zona 6 |
| 2 | 4 | Instituto Tecnológico Superior Zacatecas Norte | 0003013 | Zona 3 - Centro | NULL | No registrada (certificado histórico 1, `zona` nulo) | **Zona 3 - Centro** | `zona_anfeca`; Zacatecas = zona 3 | `zona_id` → zona 3 |
| 3 | 5 | Universidad de Estudios Profesionales de Ciencias y Artes | 9804002 | Zona 4 - Centro Occidente | NULL | No registrada (certificado histórico 2, `zona` nulo) | **Zona 4 - Centro Occidente** | `zona_anfeca`; Guanajuato = zona 4 | `zona_id` → zona 4 |

- **Diferencias y ambigüedades:** ninguna. No hay contradicciones entre el texto, el número, el nombre y la entidad.
- **Zona histórica frente a zona actual:**
  - la zona histórica corresponde al certificado previo; la actual, a la institución del trámite;
  - en los refrendos (folios 4 y 5) la histórica **no está registrada**, así que no puede compararse;
  - el expediente muestra ambas por separado.
- **Observación (no usada como fuente):** los dígitos 3–4 del número de afiliación coinciden con la zona (98**06**001, 00**03**013, 98**04**002). Podría servir como verificación cruzada si la CNCA confirma la regla de numeración.
- **Instituciones sin zona:** ninguna de las 3 del ensayo. En general, quedan «Sin clasificar» las instituciones sin `zona_anfeca` reconocible y las solicitudes sin institución en la formación académica.

## 3. Corrección implementada (sin escrituras en la base)

- `config/anfeca_zonas.php`: las 7 zonas oficiales y sus entidades, con fuente y fecha.
- `App\Services\Zonas\ZonasAnfeca`, resolución centralizada con esta prioridad:
  1. `zona_id` válido (relación normalizada);
  2. `zona_anfeca` interpretado contra la lista oficial (número y nombre coherentes);
  3. «Sin clasificar».
- **Contradicciones detectadas y reportadas:**
  - `zona_id` frente a `zona_anfeca`: prevalece `zona_id`, se rotula «(contradicción)» y se muestra la advertencia;
  - número frente a nombre en el texto: queda «Sin clasificar», con advertencia;
  - entidad que pertenece a otra zona: solo advertencia.
- **Pantallas homologadas:** tablero (distribución y solicitudes recientes), bandeja y filtros, expediente (con zona histórica), seguimiento y consulta individual. «Sin clasificar» sustituye a «Pendiente de clasificación». El servicio queda listo para evaluación, dictamen, reportes, actas y certificados.
- **Seguridad:** la autorización regional **no** usa la zona textual. Sigue dependiendo de `zona_id` y `coordinador_zona` (AlcanceZona). Con el catálogo vacío:
  - la coordinación nacional ve todo;
  - los coordinadores regionales no ven nada hasta la normalización y la asignación;
  - los registros sin institución, ambiguos o solo textuales nunca conceden acceso regional.

  Esto se aplica en consultas, rutas, documentos, indicadores, API y JSON (`ZonasAnfecaTest`, `SeguimientoRegistrosTest`).

## 4. Propuesta de normalización (NO ejecutada; requiere autorización)

**Objetivo:** crear el catálogo oficial y asignar `zona_id` solo donde `zona_anfeca` sea **inequívoca**.

**Respaldo previo:**
```sh
cd /var/www/ANFECA_ENSAYO
bash scripts/anfeca/respaldo_verificado.sh bd_anfeca_ensayo_pruebas_pg desarrollo/modulo-coordinacion $(git rev-parse --short HEAD)
```

**SQL propuesto** (PostgreSQL, una transacción):
```sql
BEGIN;
-- 1. Catálogo oficial (ids fijos 1–7 = número de zona oficial).
INSERT INTO public.zonas (id, siglas, descripcion, created_at, updated_at) VALUES
  (1, 'Z1', 'Zona 1 - Noroeste', now(), now()),
  (2, 'Z2', 'Zona 2 - Norte', now(), now()),
  (3, 'Z3', 'Zona 3 - Centro', now(), now()),
  (4, 'Z4', 'Zona 4 - Centro Occidente', now(), now()),
  (5, 'Z5', 'Zona 5 - Centro Sur', now(), now()),
  (6, 'Z6', 'Zona 6 - Sur', now(), now()),
  (7, 'Z7', 'Zona 7 - Ciudad de México', now(), now());
SELECT setval(pg_get_serial_sequence('public.zonas', 'id'), 7);

-- 2. Instituciones con zona_anfeca IDÉNTICA a la denominación oficial y sin zona_id.
CREATE TEMP TABLE normalizacion_zonas AS
  SELECT i.id AS institucion_id, z.id AS zona_id
    FROM public.instituciones_afiliadas i JOIN public.zonas z ON z.descripcion = btrim(i.zona_anfeca)
   WHERE i.zona_id IS NULL;
SELECT * FROM normalizacion_zonas;          -- revisión: se esperan 3 filas (1→6, 2→4, 3→3)
UPDATE public.instituciones_afiliadas i SET zona_id = n.zona_id, updated_at = now()
  FROM normalizacion_zonas n WHERE i.id = n.institucion_id AND i.zona_id IS NULL;
-- 3. Verificación antes del COMMIT.
SELECT id, zona_anfeca, zona_id FROM public.instituciones_afiliadas ORDER BY id;
COMMIT;   -- solo si la verificación coincide; de lo contrario, ROLLBACK
```

**Registros afectados:**
- 7 filas nuevas en `zonas`;
- 3 filas en `instituciones_afiliadas` (ids 1, 2, 3), solo la columna `zona_id`.

No se tocan solicitudes, documentos, certificados ni usuarios.

**Riesgos:**
- Los números de afiliación o los textos de otras instituciones futuras pueden no coincidir exactamente. Solo se normaliza la coincidencia exacta; el resto queda «Sin clasificar» y se reporta.
- **PDM:** la tabla `anfeca_modelo.zona` es independiente y está apagada; no se sincroniza.

**Pruebas posteriores:**
- `ZonasAnfecaTest` en un árbol aislado;
- en 8086: la resolución pasa de «dato institucional» a «normalizada» sin cambiar la etiqueta;
- los indicadores no cambian para la coordinación nacional.

**Reversión:**
```sql
BEGIN;
UPDATE public.instituciones_afiliadas SET zona_id = NULL, updated_at = now() WHERE id IN (1, 2, 3) AND zona_id IN (3, 4, 6);
DELETE FROM public.zonas WHERE id BETWEEN 1 AND 7
   AND NOT EXISTS (SELECT 1 FROM public.instituciones_afiliadas WHERE zona_id = zonas.id)
   AND NOT EXISTS (SELECT 1 FROM public.coordinador_zona WHERE zona_id = zonas.id);
COMMIT;
```

## 5. Asignaciones de coordinadores pendientes (requieren autorización)

| Cuenta | Rol | Asignación propuesta | Estado |
|---|---|---|---|
| coordinador.nacional@anfeca.test | coordinador_nacional | No requiere (todas las zonas) | — |
| coordinador.zona3@anfeca.test | coordinador_zona | Zona 3 - Centro | **Pendiente** |
| coordinador.zona4@anfeca.test | coordinador_zona | Zona 4 - Centro Occidente | **Pendiente** |
| coordinador.zona6@anfeca.test | coordinador_zona | Zona 6 - Sur | **Pendiente** |

```sql
INSERT INTO public.coordinador_zona (user_id, zona_id)
SELECT u.id, z FROM (VALUES ('coordinador.zona3@anfeca.test', 3), ('coordinador.zona4@anfeca.test', 4), ('coordinador.zona6@anfeca.test', 6)) v(correo, z)
JOIN public.users u ON u.email = v.correo AND u.rol = 'coordinador_zona';
```

Solo se aplica después de la normalización (§4) y con autorización. Son cuentas de prueba del ensayo. Las asignaciones reales dependen del directorio oficial de coordinaciones regionales, que hoy responde 404.
