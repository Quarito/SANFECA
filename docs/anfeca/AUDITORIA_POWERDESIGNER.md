# Auditoría de PowerDesigner en ANFECA

## Base y referencia

Se revisaron 19 ramas de `Quarito/SANFECA`. La corrección parte de `desarrollo/modulo-coordinacion` en `ddd4c8d`, descendiente de `ensayo/principal`, para incorporar la integración PDM y las pruebas vigentes de Coordinación. El trabajo previo está en los PR [#5](https://github.com/Quarito/SANFECA/pull/5), [#6](https://github.com/Quarito/SANFECA/pull/6), [#7](https://github.com/Quarito/SANFECA/pull/7) y [#8](https://github.com/Quarito/SANFECA/pull/8). El historial atribuye a Claude, entre otros, `4f51210` (migración y conciliación), `d200e37` (espejo), `a834a37` (extensiones), `1cb0359` (compatibilidad) y `e831add` (exportación).

La fuente obligatoria es `anfeca_modelo_powerdesigner_corregido_claude.sql`, con SHA-256 `0963a389842f77f62a42c3ce40ca20a0f7f2b779cfb4881f2b0f7d4c04a37a8d`. El archivo instalable es `database/sql/anfeca_modelo/anfeca_modelo.sql`. Se calificaron los objetos en `anfeca_modelo` y la transacción quedó a cargo del instalador. El DDL base conserva los nombres, el orden de los atributos, los tipos, las restricciones y los índices del adjunto.

| Tabla | Nombre anterior | Nombre exigido por el adjunto |
| --- | --- | --- |
| `comunicacion` | `cumu_id_persona` | `comu_id_persona` |
| `convocatoria` | `con_lugar_entrega` | `conv_lugar_entrega` |
| `convocatoria` | `con_evento_entrega` | `conv_evento_entrega` |
| `solicitud` | `solo_art13` | `soli_art13` |
| `solicitud_documento` | `docu_id_documento` | `socu_id_documento` |

La FK de `solicitud_documento.socu_id_documento` sigue apuntando a `documento.docu_id_documento`. Se conservan las grafías que figuran en el adjunto, entre ellas `pees_id_estuido_profesional` y `esso_id__estado_solicitud`.

## Hallazgos y cambios

| Componente | Resultado |
| --- | --- |
| Instalador y verificador | Se mantuvo el instalador transaccional. El verificador informa el inventario base de 36 tablas y 35 FK aun cuando se han instalado las extensiones. |
| Migraciones | Una migración PDM ordenada antes de F1 actualiza los cinco nombres en las instalaciones anteriores, incluidas las que llegaron a F4/F5. PostgreSQL conserva los datos, las restricciones, los índices y las dependencias de vistas durante el renombre. Las instalaciones nuevas ya crean los nombres canónicos. La migración se detiene ante columnas faltantes o ambiguas. |
| Modelos Eloquent | Se alinearon atributos, casts y relaciones. Para las tres PK compuestas se usa la clave completa al recargar, actualizar o borrar una instancia. Las búsquedas por identificador escalar conservan el contrato de Eloquent. |
| Servicios | Se actualizaron las referencias en planificación, conciliación y espejo. Se conservó la traducción desde las tablas operativas de Laravel. |
| Compatibilidad | La instalación y la actualización comparten un único archivo de funciones para los nombres corregidos. La actualización reutiliza los disparadores, las vistas y las tablas técnicas instalados. |
| Controladores y scripts | Se revisaron los puntos de entrada de datos personales, certificación, refrendo, formación, documentos, compromiso y recuperación. Los controladores usan las APIs existentes del servicio de espejo o los modelos Laravel que resuelven las vistas por conexión. El script de copia F2 aplica las migraciones PDM pendientes aunque la copia ya tenga extensiones. |

Las extensiones operativas existentes siguen en la ruta explícita `database/migrations_pdm`. Las 36 tablas base se definen una sola vez para la instalación. Los nombres corregidos forman parte del contrato del esquema base y se mantienen al retirar las extensiones.

## Evidencia local

Se usaron PHP 8.4.28 y PostgreSQL 17.12. Las bases fueron desechables, con acceso solo por loopback y datos ficticios. El PostgreSQL del servidor queda por validar en la etapa autorizada.

| Verificación | Resultado |
| --- | --- |
| Adjunto frente al instalador Laravel en dos bases nuevas | `pg_dump --schema-only --no-owner --no-privileges -n anfeca_modelo` idéntico al quitar las dos órdenes de restricción variables del volcado. 36 tablas, 173 atributos, 36 PK, 35 FK, 96 índices y 36 secuencias. SHA-256 de ambos volcados normalizados `cb285c0966ac31ea68875c3db1da512bcf590d1eace499a5cb7dc209bddf9b35`. |
| Suite general `php vendor/bin/phpunit` | 195 aprobadas, 1 omitida por una regla exclusiva de PostgreSQL, 2,438 aserciones. |
| Suite PDM `php vendor/bin/phpunit -c phpunit.pgsql.xml` | 73 aprobadas, 1,153 aserciones. Incluye migración de datos, conciliación, espejo, principal, exclusivo y Eloquent. |
| Revisión documental `php vendor/bin/phpunit -c phpunit.revision-pg.xml` | 22 aprobadas, 159 aserciones. Incluye la regla de fecha omitida en SQLite y la concurrencia sobre PostgreSQL. |
| Actualización de una instalación F4/F5 de `ddd4c8d` | Corrió solo la migración de alineación. La segunda ejecución quedó al día. Una función de compatibilidad leyó y actualizó un contacto previamente creado. Los datos de las 25 tablas de `public` distintas de `migrations` conservaron su huella. |
| Compilación `npm ci --ignore-scripts` y `npm run build` | Vite completó la compilación. |

Las suites PostgreSQL requieren bases locales desechables con los nombres fijados en sus archivos XML. La suite PDM reconstruye su base al iniciar, por lo que debe ejecutarse exclusivamente en la instancia local de pruebas.

## Pendientes antes de activar en servidor

1. Con autorización, inventariar `/var/www/ANFECA_ENSAYO`, verificar la base efectiva y el estado de las migraciones, y probar primero sobre una restauración de `pg_dump`. Mantener el PDM apagado mientras se revisan las diferencias de datos.
2. Probar alta, autorización, refrendos de artículos 13 y 14, formación, documentos, envío, acuse y Coordinación sobre la copia. El diagnóstico de mapeo todavía informa ocho entradas con campos que carecen de equivalencia directa. Los catálogos de formación recientes requieren validación adicional con los modos PDM activos, según `catalogos/TRAZABILIDAD_Y_VERIFICACIONES_CATALOGO.md`.
3. Tras validar el ensayo, preparar el procedimiento para `/var/www/anfeca` en el puerto 8008. La activación de modos PDM y la migración de datos institucionales quedan sujetas a su propia validación.
