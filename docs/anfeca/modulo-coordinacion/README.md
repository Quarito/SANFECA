# ANFECA · Módulo de Coordinación de Certificación Académica

| Documento | Contenido |
|---|---|
| [DIAGNOSTICO_FASE_6_0.md](DIAGNOSTICO_FASE_6_0.md) | Diagnóstico de solo lectura y búsqueda del catálogo de zonas |
| [ARQUITECTURA_Y_SEGURIDAD.md](ARQUITECTURA_Y_SEGURIDAD.md) | Fases 6.1 y 6.2: arquitectura, roles, rutas, alcance por zona e indicadores |
| [CONFIGURACION_ZONAS_Y_COORDINADORES.md](CONFIGURACION_ZONAS_Y_COORDINADORES.md) | Procedimiento reproducible para zonas y cuentas de coordinación (sin credenciales) |
| [ANALISIS_PDM_PREVIO_FASE_6_4.md](ANALISIS_PDM_PREVIO_FASE_6_4.md) | Comparativo del PDM para evaluación, dictamen, acta, certificado, convocatoria y roles |

## Estado

- **6.1 Acceso y seguridad:** implementada y probada.
- **6.2 Dashboard y bandeja:** implementada y probada, con datos reales de PostgreSQL.
- **Homologación visual (2026-11-25):** layout administrativo común con el encabezado, el pie, la barra de usuario y las hojas institucionales del módulo de solicitantes. La consulta de solicitud tiene dos fichas y una cuadrícula de documentos. Ver `ARQUITECTURA_Y_SEGURIDAD.md` §1.1.
- **Pendientes de datos:** el catálogo oficial de zonas, la asignación de `zona_id` a las instituciones y la asignación de zonas a los coordinadores.
- **Fases 6.3 a 6.8:** no implementadas. Las opciones del menú aparecen como «Pendiente».

## Acceso

Ingrese por `/login` con una cuenta de coordinación; tras iniciar sesión se redirige a `/coordinacion`.
Las cuentas se crean con `php artisan anfeca:coordinador-alta`; las credenciales se entregan fuera del repositorio.
