# Aceptación técnica de 8086 · PHP-FPM 8.5.12 + PostgreSQL 18.6 + certificacion_anfeca · 2026-11-27

**Dictamen: APROBADO PARA REVISIÓN INSTITUCIONAL** (Laravel con PDM apagado).

Esta aprobación técnica **no** autoriza:
- los 3 renombres pendientes del SQL de PowerDesigner;
- la decisión D-11;
- la migración definitiva al modelo PowerDesigner.

## 1. Conmutación ejecutada

| Dato | Valor |
|---|---|
| Fecha | 2026-11-27. Segunda ejecución de `02_conmutar_root.sh`, la que concluyó la conmutación, hacia las 10:38 CST (arranque del pool exclusivo según `journalctl`) |
| URL | `http://192.168.7.130:8086` → `/login` 200 |
| VirtualHost | `DocumentRoot /var/www/ANFECA_8086/current/public` → `releases/0d3ad60`; `SetHandler proxy:unix:/run/anfeca8086-fpm/php-fpm.sock` |
| Identidad (FastCGI, conmutación) | `8.5.12 fpm-fcgi 13.42.0 certificacion_anfeca cert8086_app 5433 18.6 apagado` |
| Servicios | `anfeca-pg8086`, `anfeca8086-fpm` y `httpd`: activos; los dos primeros `enabled` |
| Imagen PHP | `e7c752f2…` (PHP 8.5.12 con `php.ini` de producción y los límites de `99-server.ini`) |
| Procesos | PostgreSQL `-D /var/lib/pgsql/anfeca8086/data` (usuario postgres). Pool `anfeca8086` con 3 trabajadores (usuario apache). El pool `www` (8084/8085) no se modificó |

## 2. Resultado por área

| Área | Estado | Evidencia |
|---|---|---|
| PHP-FPM 8.5.12 | APROBADO | Identidad FastCGI y HTTP en la conmutación; imagen `e7c752f2…`. En el pool real, un archivo de 8 MB llega a Laravel y recibe el mismo mensaje que antes (límite efectivo de 20M) |
| PostgreSQL 18.6 | APROBADO | `version()` 18.6; identificador 7712865409902226718 (el 5432 es 7697381974958512424); puerto 5433 |
| `certificacion_anfeca` | APROBADO | 5 consultas autenticadas en 8086 leen **10 671 tuplas en 5433** frente a 261 en 5432 (igual al control sin peticiones). 75 tablas y 200 filas iguales al origen: sin escrituras posteriores a la conmutación. 36 tablas PDM. 43 secuencias con valor. PDM sin registro activo |
| Laravel | APROBADO | 13.42.0. 0 errores `production` en `laravel.log` desde la conmutación (los 14 previos son anteriores) |
| Certificación | APROBADO | Copia aislada `postconmutacion_pruebas` con el mismo release y PHP 8.5.12: **96/97 pasos idénticos** a la etapa A (sistema anterior). El único distinto es la hora y el host en los indicadores |
| Refrendo | APROBADO | En la misma ejecución y en las reglas: certificado histórico, número inexistente, certificado ajeno, artículo 14 (inciso obligatorio), vigencia de 3 años («a partir del año 2028») y no automático |
| Formación académica | APROBADO | Filtro por área, catálogo V2 con acentos, posgrado, D-LIC-1, límite de 5 MB, bloqueo tras autorizar |
| Documentos | APROBADO | PDF, JPG y PNG; tipo no permitido rechazado; sustitución bloqueada tras guardar; acuse PDF como adjunto |
| Coordinación | APROBADO | Dashboard, indicadores, bandeja (folios 3–7 y 9–11 en vivo), filtros (filtro manipulado: 422; zona ajena: 403), expedientes, visor, descargas, seguimiento, revisión documental desactivada (404) |
| Seguridad | APROBADO | El solicitante no accede a Coordinación (403) ni a documentos ajenos (403). Expediente fuera de zona: 404. Cierre de sesión → `/login`. Sesión expirada → `/login` |
| Reglas de negocio específicas | APROBADO | `regresion_reglas.py`: **21/21 idénticos** entre el sistema anterior (PHP 8.4) y el actual. Antes de autorizar solo cambian domicilio y teléfonos; se exige al menos un teléfono; el texto exacto del compromiso está en el componente Vue |
| Regresión visual (40 pantallas) | APROBADO | **Etapa C: 40/40 idénticas** a la línea base previa a la conmutación (`visual_actual`), en escritorio, tableta y móvil. Las 4 con «JS:1» son el estado 404/403 de la propia página, igual que en la base |
| Integridad documental | APROBADO (22/28) · REQUIERE ROOT (6/28) | 20/20 históricos con SHA-256 idéntico y HTTP 200. Folio 11 (2): contenido íntegro. Folios 9 y 10 (6 archivos ficticios): no son accesibles por la aplicación mientras sean borradores; la conmutación los comparó en disco antes y después (sin diferencias). Para verificarlos de nuevo, ver §4 |
| Reversión disponible | APROBADO | `03_revertir_root.sh` (ensayado: 10 escenarios simulados). Se conservan la base de origen `bd_anfeca_ensayo_pruebas_pg` (sin uso, idéntica), `certificacion_anfeca_previa_*`, el VirtualHost original y los respaldos |
| Ambientes protegidos | APROBADO | 8084 y 8085: 403 (igual que antes); 8008 sin proceso escuchando; `/var/www/anfeca` en `227060e` sin cambios |

## 3. Correcciones de la conmutación real (commit `2563e93`)

1. `sincronizar_bd.sh` usaba el socket de `/home`. Causa de fondo: `sudo -u servidorweb` reinicia el entorno y `PG8086_ADMIN` no llegaba. Ahora se pasa de forma explícita y la ruta de credenciales es absoluta.
2. `02_conmutar_root.sh` escribía la evidencia en `/root` desde servidorweb (`PermissionError`). Ahora usa un directorio temporal privado, que se elimina también desde la recuperación.

Ambos tienen pruebas de regresión en `probar_conmutacion_simulada.sh`: fallan con la versión previa (`b36ae1e`) y pasan con la corregida.

## 4. No ejecutado (requiere root o una ventana autorizada)

| Prueba | Motivo | Comando para el administrador |
|---|---|---|
| Denegaciones SELinux desde la conmutación | `ausearch` requiere root | `sudo ausearch -m avc -ts today \| grep -E 'httpd\|php-fpm\|postgres'` |
| Logs de Apache, PHP-FPM y PostgreSQL del servicio | `/var/log/httpd` (700 root), `/var/log/anfeca8086-fpm` (750 apache), `/var/lib/pgsql/anfeca8086` | `sudo tail -50 /var/log/httpd/anfeca-ensayo-error.log /var/log/anfeca8086-fpm/*.log; sudo -u postgres tail -50 /var/lib/pgsql/anfeca8086/data/log/postgresql-*.log` |
| 6 documentos ficticios de los folios 9 y 10 | Directorio privado de apache | `sudo bash -c 'cd /var/www/ANFECA_ENSAYO/storage/app/private && sha256sum -c <(cat /root/anfeca8086_conmutacion_*/documentos_despues.sha256 \| sort -u)' \| grep -vc ': OK$'` (debe dar 0) |
| Persistencia ante reinicio | No se reinician servicios en uso sin una ventana | En una ventana: `sudo systemctl restart anfeca-pg8086 anfeca8086-fpm` y comprobar `/login` |

## 5. Pendientes

**Defectos nuevos:** ninguno pendiente. Los 2 de la conmutación están corregidos (§3).

**Defectos preexistentes** (también en el sistema anterior; sin cambios por falta de autorización):
1. `tipo_personal` y `area_certificacion` se validan solo como texto.
2. Mensajes de validación en inglés.
3. Bandejas de zona vacías mientras `zona_id` sea NULL (normalización de zonas pendiente).
4. Apache sirve `.mjs` sin `Content-Type`.

**Decisiones institucionales:**
1. Renombres `conv_lugar_entrega`, `conv_evento_entrega` y `soli_art13` (SQL de PowerDesigner).
2. D-11: número de afiliación y facultad oficiales de las instituciones 1–3.
3. Migración `escuela_facultad`, que sigue sin autorizar.
4. Activación de PDM (sigue apagado).

**Limpieza a criterio del administrador** (copias con datos institucionales en el clúster 5433, sin uso):
- `cert8086_pruebas_funcionales`, `comparacion_base`, `comparacion_candidato`, `extra_base`, `extra_post`, `postconmutacion_pruebas`, `certificacion_anfeca_sinc_*`;
- `/var/lib/pgsql/anfeca8086.parcial_*` y `/home/servidorweb/anfeca_pg8086` (clúster previo, detenido);
- `certificacion_anfeca_previa_*`, que **se conserva** como punto de reversión.

**Mejoras futuras:** traducción de las validaciones; validación de catálogo para tipo de personal y área; normalización de zonas; tipo MIME `.mjs` en Apache.

Evidencias (privadas, fuera de Git): `~/anfeca_trabajo/evidencias_regresion/{visual_etapa_c,comparacion_etapa_c}` y `~/anfeca_trabajo/evidencias_despliegue/`.
