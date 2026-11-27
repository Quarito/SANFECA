# Laravel y PowerDesigner con PHP 8.5.12

## Resultado de laboratorio

La validación partió de `fe530428714c6595f6be3019e41380ffd0c88d55`, cabeza del PR #15 al iniciar esta fase. Se usaron PHP CLI y PHP-FPM **8.5.12 NTS**, Composer 2.11.3, Laravel 13.42.0, PHPUnit 12.5.40 y PostgreSQL **18.7**. El entorno de trabajo ejecutó los binarios extraídos de la imagen PHP oficial, verificó las huellas de sus capas y compiló las extensiones faltantes contra el código fuente 8.5.12. El workflow reproduce el perfil mediante el Dockerfile fijado al mismo manifiesto Linux amd64 `sha256:128f02f6ea8cd5d272fcbcefe1abbc8ae444469debd47c1b6c10507d018ed64e`.

| Verificación | Resultado |
| --- | --- |
| `composer validate --no-check-publish`, `composer install --no-interaction --prefer-dist --no-progress` y `composer check-platform-reqs --lock` | PASÓ. Se conservó `composer.lock`. |
| `php vendor/bin/phpunit -c phpunit.xml` | 203 aprobadas, 1 omitida, 2 468 aserciones. |
| `php vendor/bin/phpunit -c phpunit.pgsql.xml` | 79 aprobadas, 1 234 aserciones. Incluye migración, modos PDM y exportación con datos sintéticos. |
| `php vendor/bin/phpunit -c phpunit.revision-pg.xml` | 22 aprobadas, 159 aserciones. |
| `php-fpm -t` y `bash scripts/anfeca/pruebas/probar_php_fpm.sh` | PASÓ. Una solicitud FastCGI verificó la versión, el SAPI, las extensiones y PostgreSQL desde Laravel. Una segunda solicitud obtuvo el formulario de `/login`. |
| Migraciones ordinarias, instalación PDM, migraciones PDM, semilla de catálogos y verificador Artisan | PASÓ en `certificacion_anfeca`. Las operaciones protegidas de instalación, extensión, migración de datos y vaciado se rechazaron sin las condiciones de autorización correspondientes. |
| Escritura y lectura Eloquent, rechazo de clave foránea y separación de ambientes | PASÓ. Dos clústeres independientes de PostgreSQL 18.7 alojaron una base `certificacion_anfeca` cada uno. La fila creada con Laravel en el primero no apareció en el segundo. |

Con el límite inicial de 128 MiB, Dompdf agotó memoria al procesar imágenes para el acuse. Se incorporaron los valores que ya exige el preflight de despliegue. El perfil usa `memory_limit=512M`, `upload_max_filesize=6M` y `post_max_size=25M`. El preflight ahora registra como error una versión CLI diferente de la obligatoria.

## Esquema instalado

Se instaló el SQL aprobado con `psql` en una base de referencia y con `php artisan anfeca:modelo-instalar` en `certificacion_anfeca`. `cmp` verificó igualdad tanto en el inventario ordenado de catálogos como en el volcado de `pg_dump -n anfeca_modelo --schema-only` tras retirar las marcas variables `\restrict` y `\unrestrict`. Se verificaron **36 tablas, 173 atributos, 36 PK, 35 FK, 96 índices y 36 secuencias**.

| Evidencia | SHA-256 |
| --- | --- |
| SQL PowerDesigner aprobado | `0963a389842f77f62a42c3ce40ca20a0f7f2b779cfb4881f2b0f7d4c04a37a8d` |
| Volcado normalizado de ambas instalaciones | `aceed2c859f69addc6c1916c1db9fff56b5f4798a975a00b6cdfdf75e9a8ad52` |
| Inventario de catálogos de ambas instalaciones | `3ee2da2ae7ee8b737d33b3c70ec4e797e589a5d84338cc9f4cf55a9f88225211` |

El inventario individual está en [Auditoría de las 36 tablas](AUDITORIA_36_TABLAS_POWERDESIGNER.md).

## Preparación de 8086 y 8008

1. Ejecutar el [preflight de solo lectura](PREFLIGHT_INSTITUCIONAL_8086_8008.md) desde un checkout de revisión en cada host. Confirmar para cada Laravel la conexión efectiva de CLI, HTTP y workers, el identificador de clúster, el rol y el espacio disponible. La topología institucional sigue pendiente de comprobar.
2. Construir la imagen primero en un equipo aislado con `podman build -f scripts/anfeca/runtime/Dockerfile.php8512 -t localhost/anfeca-php:8.5.12 scripts/anfeca/runtime`. Registrar su digest y verificar la composición de la imagen elegida para AlmaLinux. Para un runtime nativo, verificar la misma versión y requisitos con `php -v`, `php-fpm -v`, `php --ini`, `php -m`, `composer check-platform-reqs --lock` y `php-fpm -t`, ejecutados con el binario y la configuración de cada instalación.
3. Crear primero el perfil 8086 y después, de forma independiente, el perfil 8008. Asignar a cada VirtualHost su propio pool PHP-FPM, socket privado, identidad de servicio y almacenamiento privado. Comprobar desde una solicitud FastCGI interna al socket que cada pool realmente use PHP 8.5.12, las extensiones necesarias y los límites indicados. Probar también HTTP desde el VirtualHost candidato. Aplicar la política SELinux y comprobar lectura y escritura únicamente en las rutas que correspondan al pool. Retirar cualquier sonda temporal y conservar su evidencia sin secretos.
4. Si ambos ambientes requieren el nombre físico `certificacion_anfeca`, preparar instancias PostgreSQL independientes con directorios, endpoints, roles, respaldos y planes de reversión propios. Verificar identificadores de sistema distintos y comprobar con los roles de aplicación que una escritura de ensayo no aparece en el otro ambiente. Conservar PDM apagado durante el preflight, la preparación y el cambio de conexiones inicial autorizado.
5. Restaurar y verificar por separado el respaldo de cada fuente en un clúster temporal. Comparar conteos, huellas, documentos privados y catálogos, y ensayar migraciones y reversión con cada origen. La [validación de la copia institucional 8086](VALIDACION_COPIA_INSTITUCIONAL_8086.md), el [plan de despliegue](PLAN_DESPLIEGUE_8086_8008.md) y el [plan de reversión](PLAN_REVERSIÓN_BASE_DATOS.md) detallan esas etapas. Autorizar la instalación de 8086 únicamente después de verificar la copia real, el pool candidato y la reversión. Para 8008 repetir los controles con sus propios datos y su propia autorización.

El dictamen de esta fase aplica al laboratorio. La restauración y equivalencia institucional, los VirtualHosts y los pools efectivos de 8086 y 8008 quedan pendientes de evidencia en sus respectivos entornos.
