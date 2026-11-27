# ANFECA 8088 en AlmaLinux 9

## Estado

**Preparado en el repositorio. Pendiente de ejecutar y comprobar en el servidor.** En Work, SSH a `192.168.7.130` respondió `Network is unreachable` y HTTP a 8088 no estableció conexión. La preparación local verificó la sintaxis de Bash y de las sondas con PHP 8.5.12. En PostgreSQL 18.6, un clúster temporal de laboratorio sobre loopback comprobó la creación repetible de la base vacía y el rol. Work impidió crear el socket Unix, por lo que quedan por validar en AlmaLinux el socket nativo, la autenticación peer, los servicios systemd, Apache, SELinux, el firewall y el estado en vivo de los ambientes existentes.

La rama de aplicación de Claude estaba en `59333f7c409801db4a4a07cd5bd27ecba4dccacc` al verificarla. En ese commit, `database/sql/oficial/anfeca_modelo_powerdesigner_certificacion_anfeca_sin_esquema.sql` coincide byte por byte con el adjunto `anfeca_modelo_powerdesigner_certificacion_anfeca_sin_esquema.sql`.

```text
SHA-256 5798ce839af01339018536155ab17698c7cb953c7f150e2b9c6b268aab978285
```

El adjunto fija `search_path` en `public`. Se verificó el archivo por lectura y comparación de bytes. Su instalación y la del código de Claude quedan para la fase siguiente.

## Infraestructura

| Recurso | Configuración prevista |
|---|---|
| Apache | VirtualHost aislado en `192.168.7.130:8088`, `DocumentRoot /var/www/ANFECA_8088/public`, logs propios. |
| PHP-FPM | Unidad `anfeca8088-php-fpm`, binario nativo `/usr/sbin/php-fpm` 8.5.12, worker dedicado `anfeca8088` y socket exclusivo `/run/anfeca8088-fpm/php.sock`. |
| PostgreSQL | Unidad `anfeca8088-postgresql`, binario `/usr/pgsql-18/bin/postgres` 18.6, datos `/var/lib/pgsql/anfeca8088`, socket `/run/anfeca8088-pg` e identificador de puerto 55488 **sin listener TCP**. |
| Acceso a PostgreSQL | Base nueva `certificacion_anfeca`, propiedad de `postgres`. Rol de aplicación `anfeca8088` por autenticación peer desde el mismo usuario del sistema. El acceso público a la base queda revocado. Los permisos sobre las futuras tablas y secuencias se asignan después de revisar el candidato. |
| Separación | Sin archivos `.env` de otros ambientes. Configuración en `/etc/anfeca8088`, sesiones, caché, temporales y logs bajo la nueva ruta. Regla de firewalld para el CIDR autorizado y únicamente el destino 8088. |
| SELinux | Etiquetas solo para las rutas, sockets y puerto propios. La prueba por Apache debe pasar con el modo efectivo del servidor. |

## Preparación en el host

Ejecutar desde un checkout separado de la rama de infraestructura, fijado al commit revisado. El script verifica AlmaLinux 9, las versiones exactas vigentes, las extensiones de la sonda, los módulos Apache, la interfaz, los listeners y las rutas. Ante archivos distintos en su destino o una etiqueta de puerto incompatible, se detiene.

```bash
sudo bash scripts/infra/anfeca-8088/preparar.sh --check
```

Usar el CIDR autorizado para los clientes de pruebas, preferentemente un `/32` si hay una sola estación. Si firewalld está activo, identificar la zona de la interfaz con `sudo firewall-cmd --get-active-zones`. Guardar la salida en almacenamiento institucional.

```bash
sudo bash scripts/infra/anfeca-8088/preparar.sh --apply "$CIDR_CLIENTES" "$ZONA_FIREWALL"
```

Se puede repetir con el mismo CIDR y zona. Se reutilizan los archivos idénticos y la identidad registrada del clúster. Una inicialización PostgreSQL incompleta sin identidad registrada requiere revisar el directorio antes de reanudar. Si falla la sonda después de publicar un VirtualHost nuevo, el script retira ese archivo y recarga Apache. Los recursos de 8088 ya creados quedan disponibles para diagnóstico y reintento. Si SELinux bloquea la comunicación, revisar el AVC de los sockets dedicados y comprobar sus contextos con `ls -Zd /run/anfeca8088-{fpm,pg}`. Evaluar cualquier ajuste de política como cambio separado.

La salida de aplicación debe incluir HTTP 200 de la sonda con `ok:true`, `php:8.5.12`, `sapi:fpm-fcgi`, la base y el rol previstos, y la identidad del clúster. El script compara huellas de configuración de Apache, listeners de 8086 y 8008 y, si la ruta documentada está disponible, la identidad del clúster 8086 antes y después. Estas comparaciones son evidencia de infraestructura, sin volcar ni inspeccionar expedientes. A continuación, abrir `http://192.168.7.130:8088/` desde una estación del CIDR autorizado y comprobar también desde fuera de ese CIDR que el acceso está restringido.

## Recepción del código terminado

1. Confirmar con el responsable que la rama de Claude terminó. Fijar SHA exacto y verificar de nuevo el SQL de ese commit contra el adjunto con el SHA-256 anterior. Revisar el Vite del commit y `composer check-platform-reqs` con PHP 8.5.12. Ejecutar Composer y la compilación con una cuenta de despliegue sin privilegios. El script `composer setup` visible en el commit inspeccionado ejecuta `artisan migrate`, incompatible con esta instalación inicial.
2. Comprobar la identidad del clúster en `/etc/anfeca8088/cluster` y que `certificacion_anfeca` está vacía. Preparar el SQL aprobado en ese clúster de 8088 y en una base de referencia independiente con nombre distinto. Conceder al rol de aplicación solo el acceso a las tablas y secuencias requeridas. Usar sesiones y caché en archivos. Laravel con este PostgreSQL debe conectar por su socket propio y debe usar `public` como esquema.
3. Mantener un release separado y versionado, con configuración nueva y clave generada para 8088. La ruta `public/` ahora contiene una sonda y una portada de infraestructura. Sustituirlas en el paso de despliegue revisado y conservar el estado previo para rollback. Esta preparación no sustituye el código automáticamente ni cambia rutas ya existentes.
4. Ejecutar el verificador físico. Comprueba las 36 tablas, 173 columnas, 36 PK, 35 FK, 96 índices, 36 secuencias y esquema `public` exclusivo. Compara además el DDL de todo el candidato con la base de referencia. El volcado excluye propietarios, ACL y las marcas variables del cliente. Los privilegios se prueban por separado con el rol de aplicación.

```bash
sudo bash scripts/infra/anfeca-8088/verificar-modelo.sh --referencia "$BD_REFERENCIA" "$EVIDENCIA_NUEVA"
```

## Validación integral posterior

| Prueba | Evidencia de aceptación |
|---|---|
| HTTP, PHP-FPM y Laravel | Sonda de infraestructura, respuesta 200 del release fijado, carga de recursos Vite, cookies de sesión y persistencia entre peticiones. |
| Alta y certificación inicial | Persona ficticia desde cero, alta y reingreso, confirmación de datos, certificación sin antecedente, selección y persistencia comprobadas en la base 8088. |
| Refrendo | Cuenta ficticia y antecedente mínimo, identificación por certificado, rechazo de certificado ajeno y de duplicado. |
| Formación y documentos | Carga, recuperación y sustitución controladas. Comparar SHA-256 de archivos y sus versiones, incluidas las de refrendo por artículo 14. |
| Compromiso, envío y acuse | Rechazo sin aceptación, envío único al aceptar, PDF legible con folio y acceso restringido al titular o rol autorizado. |
| Coordinación | Bandeja, seguimiento, expediente y visor con estados correctos. Probar rol nacional, zona propia, zonas ajenas y solicitante. |
| Interfaz y aislamiento | Capturas de Vue/Blade en escritorio y móvil. Revisar consultas de las pruebas para excluir dependencias operativas de relaciones antiguas. Ejecutar con la referencia física de 36 tablas y revisar nuevamente el catálogo después de probar. |

Usar exclusivamente datos ficticios. 8088 se acepta cuando infraestructura, estructura y flujos estén comprobados. El eventual 8008 requiere su propio respaldo, clúster, configuración, reversión y autorización.
