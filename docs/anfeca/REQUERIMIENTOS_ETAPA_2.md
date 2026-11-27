# Requerimientos reservados para la etapa 2 (formación multigrado)

**Registrados:** 2026-11-26. **Estado: FUERA DEL ALCANCE de la etapa 1.** No se implementan ni se preparan estructuras para ellos.

La etapa 1 almacena **solo dos escenarios**:
1. Solo licenciatura.
2. Licenciatura con **un** posgrado (el último grado obtenido).

Ver `catalogos/PROPUESTA_CATALOGO_LICENCIATURAS.md`, Parte II.

| ID | Requerimiento | Notas para el diseño futuro |
|---|---|---|
| ET2-01 | Segunda licenciatura y licenciaturas adicionales | Hoy el último grado no admite «Licenciatura» (validación y CHECK) |
| ET2-02 | Varias especialidades, maestrías y doctorados | Hoy solo se registra el último grado |
| ET2-03 | Modelo multigrado | Tabla de grados 1:N por formación, con nivel, área, nombre exacto, clave y versión del catálogo correspondiente |
| ET2-04 | Documentos por grado adicional | Título y cédula por grado, con su propio historial y su revisión documental |
| ET2-05 | Menú flotante «Otros grados (N)» | Aprobado conceptualmente. Panel flotante que **no** incrementa la altura de la pantalla ni desplaza las fichas; desplazamiento interno; consulta y acciones autorizadas; responsivo (mismo patrón de `SelectorBuscable`: posición fija, ≈ 200–300 px) |
| ET2-06 | Reutilización de documentos históricos en refrendos | Pendiente de la decisión normativa de la etapa 2 (no se anticipa) |

**Compatibilidad prevista:**
- Las columnas de la etapa 1 (`*_especifico`, `origen_*`, `clave_*`, `version_*`, `cuenta_con_posgrado`) pueden migrarse al modelo 1:N sin pérdida:
  - el origen es el grado de licenciatura;
  - el último grado es el posgrado vigente cuando `cuenta_con_posgrado` es true.
- Se requiere la extensión PDM pendiente.
