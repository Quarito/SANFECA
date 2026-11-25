# ANFECA · Módulo de Coordinación de Certificación Académica

| Documento | Contenido |
|---|---|
| [DIAGNOSTICO_FASE_6_0.md](DIAGNOSTICO_FASE_6_0.md) | Diagnóstico de solo lectura y búsqueda del catálogo de zonas |
| [ARQUITECTURA_Y_SEGURIDAD.md](ARQUITECTURA_Y_SEGURIDAD.md) | Fases 6.1 y 6.2: arquitectura, roles, rutas, alcance por zona e indicadores |
| [CONFIGURACION_ZONAS_Y_COORDINADORES.md](CONFIGURACION_ZONAS_Y_COORDINADORES.md) | Procedimiento reproducible para zonas y cuentas de coordinación (sin credenciales) |
| [ANALISIS_PDM_PREVIO_FASE_6_4.md](ANALISIS_PDM_PREVIO_FASE_6_4.md) | Comparativo del PDM para evaluación, dictamen, acta, certificado, convocatoria y roles |
| [REVISION_DOCUMENTAL_FASE_6_3_0.md](REVISION_DOCUMENTAL_FASE_6_3_0.md) | Fase 6.3.0: estados y transiciones, análisis de `ESTADO_SOLICITUD_DOCUMENTO`, alternativas de persistencia, rutas propuestas, prototipo aislado y matriz de pruebas |
| [PERSISTENCIA_REVISION_DOCUMENTAL_FASE_6_3_1.md](PERSISTENCIA_REVISION_DOCUMENTAL_FASE_6_3_1.md) | Fase 6.3.1 (propuesta): definición exacta de `revisiones_documentales`, restricciones, concurrencia 409, respaldo y recuperación, visualización del prototipo y matriz de 27 casos |
| [../MANTENIMIENTO_E_INCIDENTES_20261125.md](../MANTENIMIENTO_E_INCIDENTES_20261125.md) | Almacenamiento antes y después, ampliación VMware (procedimiento), incidente de pruebas y rotación de credenciales |
| [../ESTANDARES_UI_UX.md](../ESTANDARES_UI_UX.md) | Estándares obligatorios de interfaz para todos los módulos: layout, fichas, numeración «Núm.», filtros, estados y exportaciones |

## Estado

- **6.1 Acceso y seguridad:** implementada y probada.
- **6.2 Dashboard y bandeja:** implementada y probada, con datos reales de PostgreSQL.
- **Homologación visual (2026-11-25):** layout administrativo común con el encabezado, el pie, la barra de usuario y las hojas institucionales del módulo de solicitantes. La consulta de solicitud tiene dos fichas y una cuadrícula de documentos. Ver `ARQUITECTURA_Y_SEGURIDAD.md` §1.1.
- **6.2.4 Compactación del expediente (2026-11-25):** fichas 50/50 con la misma altura, documentos compactos de unos 58 px y adicionales del art. 13/14 en horizontal.
- **Numeración consecutiva (2026-11-25):** primera columna «Núm.» en la bandeja y en «Solicitudes recientes». Es continua entre páginas, se recalcula con filtros y orden, y la calcula el servidor (`ESTANDARES_UI_UX.md` §5).
- **6.3.0 Revisión documental:** análisis y prototipo aislado. Sin persistencia; la implementación funcional está pendiente de autorización.
- **6.3.1 Revisión documental:** implementada y probada en entornos aislados, incluida la concurrencia real en PostgreSQL desechable. Está **desactivada** (`ANFECA_COORDINACION_REVISION=false`). La migración sigue en `database/migrations_propuestas/` y **no está aplicada en 8086**: requiere autorización definitiva.
- **Pendientes de datos:** el catálogo oficial de zonas, la asignación de `zona_id` a las instituciones y la asignación de zonas a los coordinadores.
- **Fases 6.3 a 6.8:** no implementadas. Las opciones del menú aparecen como «Pendiente».

## Acceso

Ingrese por `/login` con una cuenta de coordinación; tras iniciar sesión se redirige a `/coordinacion`.
Las cuentas se crean con `php artisan anfeca:coordinador-alta`; las credenciales se entregan fuera del repositorio.
