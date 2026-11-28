# ANUIES y áreas de certificación en 8090

Estado: análisis del modelo y del código **verificado en el repositorio y en laboratorio**. Los conteos reales de 8088
están **pendientes**: se obtienen con `scripts/anfeca_8090/servidor/10_8_diagnostico_anuies_areas.sql` (solo lectura).
No hay acceso SSH desde esta sesión, así que ninguna cifra de este documento es de 8088.

## 1. `area_certificacion` en el modelo oficial

| Dato | Referencia vigente (`anfeca_modelo_36_tablas_vigente.sql`) |
|---|---|
| Existencia | Sí, una de las 36 tablas |
| Llave primaria | `pk_area_certificacion` (`area_id_area_certificacion`, SERIAL) |
| Columnas | `area_id_area_certificacion integer not null`, `area_descripcion varchar(200) not null` |
| Relaciones oficiales | **Solo** `solicitud.soli_id_area_certificacion` (`fk_solicitu_pertenece_area_cer`) |
| Otras referencias | `historico_certificado.hice_area_certificacion` es texto libre (sin llave) |
| Uso en el código | Selección de certificación (`Tramites::idArea` → `SOLICITUD`), reglas de posgrado obligatorio y equivalencias (`RequisitosFormacion`, `anfeca_equivalencias`), refrendo por área (`ConsultaRefrendo`), Coordinación (bandeja, filtros) |

IDs y descripciones reales en 8088, cantidad y duplicados: **pendientes** (secciones 1–3 del diagnóstico). El diagnóstico
compara contra las cinco descripciones esperadas e informa «idéntica», «equivalente» o «FALTA». Si falta alguna se
reporta: no se inserta nada.

Corrección hecha (código, no estructura): el código buscaba las áreas por texto exacto y el área 5 del código
(«Disciplinas vinculadas al ámbito económico-administrativo») difiere de la esperada en 8088 («Otras Disciplinas
Vinculadas al Ámbito Económico Administrativo»). Ahora cada área se resuelve a la fila existente por su descripción exacta
o equivalente; `anfeca:catalogos` ya no crearía un duplicado; reglas y refrendo funcionan con la descripción real
(`prueba_areas_certificacion.php` 24/24).

## 2. ¿Hay relación oficial entre programas ANUIES y áreas?

**No.** `estudio_profesional` (id, nivel, nombre, estado) solo se relaciona con `nivel_profesional` y es referenciada por
`persona_estudio_profesional`. La única ruta oficial hacia `area_certificacion` es indirecta y **por persona**:
`estudio ← persona_estudio_profesional → persona ← solicitud → area_certificacion`. Esa ruta dice en qué área *solicitó*
certificarse quien tiene ese estudio; **no clasifica el programa** (un mismo programa puede quedar en varias áreas; el
diagnóstico §6 lo cuantifica). Usarla como clasificación del catálogo sería incorrecto.

La clasificación por área que usa hoy la pantalla viene del **JSON** (`area` de cada título). En 8088 existe además la
columna no autorizada P-03 `estudio_profesional.espr_id_area_certificacion` (con la FK P-13 a `area_certificacion`),
que muy probablemente guarda esa clasificación (§5 del diagnóstico).

## 3. Matriz de programas por área y nivel

Se obtiene con el diagnóstico (§5). Con P-03 presente produce exactamente esta tabla con conteos reales:

| Área de certificación | ID oficial | Licenciaturas | Especialidades | Maestrías | Doctorados | Total |
|---|---|---|---|---|---|---|
| Contaduría Pública | por medir | por medir | por medir | por medir | por medir | por medir |
| Administración | por medir | … | … | … | … | … |
| Informática Administrativa | por medir | … | … | … | … | … |
| Negocios Internacionales | por medir | … | … | … | … | … |
| Otras Disciplinas Vinculadas al Ámbito Económico Administrativo | por medir | … | … | … | … | … |
| Sin clasificación | — | … | … | … | … | … |

**Sin P-03, la clasificación no puede calcularse con el modelo oficial**: el diagnóstico solo da totales por nivel.
También entrega: total de programas, por nivel y estado (ACT/otros), con y sin área, identificadores usados e
inconsistentes. La correspondencia con los metadatos JSON (punto 13) requiere los archivos JSON de 8086/8088; se
completará con su huella cuando se tengan.

Ensayo del diagnóstico en laboratorio (datos ficticios): con P-03 produjo la matriz (6 con área, 2 sin clasificar,
0 inconsistentes); sin P-03 informó «no calculable» y los totales por nivel.

## 4. Alternativas

| | A · Solo estructura oficial (preferente) | B · Nivel + texto; área independiente | C · P-03/P-13 indispensables |
|---|---|---|---|
| Búsqueda por área de los programas | **No es posible**: no existe relación oficial programa–área (la ruta vía SOLICITUD no clasifica programas) | No se filtra por área | Se conservaría con P-03 |
| Consulta | — | `ProgramasAnuiesPostgres::buscar(niveles, texto)` sobre `estudio_profesional` ACT (probada 8/8) | Igual que B + `espr_id_area_certificacion` |
| Área de certificación | Se elige en la solicitud (`SOLICITUD.soli_id_area_certificacion`) | Igual: funcionalidad independiente de la búsqueda | Igual |
| Reglas de elegibilidad (posgrado obligatorio en «Otras Disciplinas…», equivalencias) | Usan el área **de la solicitud**: sin cambios | Sin cambios: un filtro de búsqueda no es una regla de elegibilidad | Sin cambios |
| Cambio estructural | Ninguno | Ninguno | Requiere autorizar P-03/P-13 (no autorizado) |

Conclusión: la Alternativa A, entendida como clasificación de programas por área, **no tiene soporte en el modelo
oficial**. La Alternativa B conserva todas las reglas de certificación: el área sigue eligiéndose en la solicitud y es la
que gobierna posgrado y equivalencias; solo cambia que la búsqueda de un programa no se acota por área. La Alternativa C
se documentará con datos solo si el diagnóstico muestra información en P-03 que no pueda representarse.

Consulta de la Alternativa B (equivalente SQL de `ProgramasAnuiesPostgres`):

```sql
SELECT e.espr_id_estudio_profesional AS id, e.espr_nombre AS nombre, n.nipr_nivel_profesional AS nivel
  FROM estudio_profesional e JOIN nivel_profesional n ON n.nipr_id_nivel_profesional = e.espr_id_nivel_profesional
 WHERE e.espr_estado = 'ACT'
   AND lower(translate(n.nipr_nivel_profesional, 'ÁÉÍÓÚáéíóú', 'AEIOUaeiou')) IN ('maestria')
   AND lower(translate(e.espr_nombre, 'ÁÉÍÓÚÑáéíóúñ', 'AEIOUNaeioun')) LIKE '%negocios%'
 ORDER BY e.espr_nombre LIMIT 60;
```

## 5. Pendiente para cerrar el módulo

1. Ejecutar el diagnóstico en 8088 y completar la matriz con conteos reales.
2. Decidir B (o C si los datos lo justifican).
3. Conectar `FormacionAcademica.vue` y `FormacionPdm` a la consulta directa (IDs de `estudio_profesional` en lugar de
   claves `LIC-…` del JSON) y retirar la dependencia JSON. **Hasta entonces la búsqueda ANUIES en 8090 está
   desactivada y el módulo no se declara concluido.**
