# Auditoría y corrección de los scripts de despliegue de 8086 · 2026-11-27

Auditoría previa a la ejecución de `scripts/anfeca/despliegue_8086/` (`01_preparar_servicios_root.sh`, `02_conmutar_root.sh`, `03_revertir_root.sh` y `sincronizar_bd.sh`, más el nuevo `comun.inc.sh`).

**Resultado:** los scripts quedan corregidos y ensayados sin privilegios. **No se ejecutaron con root**: la cuenta de servicio no tiene `sudo`. 8086 sigue sin cambios.

## 1. Hallazgos y correcciones

| # | Hallazgo | Gravedad | Corrección |
|---|---|---|---|
| 1 | **SELinux impedía el diseño anterior.** Apache solo puede conectarse por TCP a puertos `http_port_t` con `httpd_can_network_relay`, `httpd_graceful_shutdown` o `httpd_can_network_connect`, los tres **desactivados** en este servidor. La conmutación habría respondido 503 | Crítica | PHP-FPM escucha en el **socket UNIX** `/run/anfeca8086-fpm/php-fpm.sock` (`httpd_var_run_t`, vía `semanage fcontext` + `tmpfiles.d`). La política contiene `allow domain spc_t:unix_stream_socket connectto` **sin condición**. Así no se modifica ningún booleano global |
| 2 | Si el respaldo o la sincronización fallaban, `set -e` terminaba antes de existir el `trap`: **8086 quedaba indefinidamente en mantenimiento** | Crítica | Recuperación automática (`trap ERR INT TERM`) **desde el inicio del mantenimiento**: antes de cambiar el VirtualHost, reapertura con la configuración anterior y reinicio del pool; después, `03_revertir_root.sh` |
| 3 | En `protegidos()`, `grep -c ':8008 '` devuelve 1 cuando no hay coincidencias (8008 no escucha); con `set -E`, el `trap` se activaba en la subshell y **una conmutación correcta se habría revertido** (encontrado por el ensayo simulado) | Crítica | `grep -c … || true` dentro de la sustitución |
| 4 | Una reejecución tras una conmutación guardaba como «anterior» el VirtualHost ya modificado, y la reversión restauraba el equivocado | Alta | Se detiene si 8086 ya está conmutado. El VirtualHost y el `.env` previos a la primera conmutación se guardan una sola vez en `/root/anfeca8086_original`. `03` rechaza una copia que apunte al release |
| 5 | Si el segundo renombre de `sincronizar_bd.sh` fallaba, `certificacion_anfeca` quedaba ausente | Alta | Se deshace el primer renombre automáticamente |
| 6 | El respaldo podía tomarse con transacciones aún en curso | Media | Tras el mantenimiento se espera hasta 60 s a que no haya transacciones `active` o `idle in transaction` en el origen; si persisten, se aborta y se recupera |
| 7 | `01` no podía reejecutarse tras una falla parcial: se negaba si existían el release o el destino del clúster | Media | Fases idempotentes. El clúster se detecta en `home` o como servicio; si el traslado falla, se reinicia el clúster original; el release existente se verifica por commit |
| 8 | La revisión del log (`tail -n 200`) podía detectar errores anteriores a la conmutación | Media | Solo se revisan los bytes añadidos desde el inicio de la ventana |
| 9 | La identidad se comprobaba solo por FastCGI | Media | Por FastCGI: `8.5.12 fpm-fcgi 13.42.0 certificacion_anfeca cert8086_app 5433 18.6 apagado`. Por **HTTP real** en el puerto 8086: una sonda efímera de nombre aleatorio (solo versión y SAPI) que se elimina siempre |
| 10 | `podman` sin privilegios de servidorweb invocado desde root requiere `XDG_RUNTIME_DIR` | Media | Se define en `comun.inc.sh` y `01` verifica que exista (ejecutar `sudo` desde una sesión de servidorweb) |
| 11 | El directorio de socket del clúster y el puerto 5433: `postgresql_t` solo puede enlazar `unreserved_port_t` con `nis_enabled` (desactivado) | Alta | `semanage port -a -t postgresql_port_t -p tcp 5433` (ya previsto); se verificó `postgresql_t → var_run_t` para `/run/anfeca-pg8086` |
| 12 | Release con `bootstrap/cache` escribible dentro del código | Baja | Release montado de **solo lectura** en el contenedor; caché en `/var/www/ANFECA_8086/cache/<commit>` (conserva `packages.php` y `services.php`) |
| 13 | Ambientes protegidos verificados solo por HTTP 8084/8085 | Baja | Se agregan: 8008 sin escuchar y huella Git de `/var/www/anfeca` |
| 14 | `session.save_path` y el puerto TCP 19086 innecesarios | Baja | Retirados: Laravel usa sesiones de archivo en `storage` y no se expone ningún puerto TCP para PHP-FPM |

| 15 | **Falla real de `01 --ejecutar` (fase A).** La comparación `H_ANTES`/`H_DESPUES` sumaba `pg_database_size`, que incluye `pg_internal.init` (160 944 bytes por base). PostgreSQL **borra esos archivos al arrancar** y los recrea al primer acceso a cada base; aquí los recreó autovacuum, una base cada ~6.5 s (10:22:31 → 10:23:24 tras el reinicio de las 10:22:24). La huella posterior al arranque siempre difería aunque los datos fueran idénticos. Reproducido en el clúster real: el método anterior da DISTINTO tras un reinicio y la huella lógica da IDÉNTICA (7 bases, 453 líneas) | Crítica | Se sustituye por una **huella lógica del clúster** (identificador, bases, roles y conteo + MD5 del contenido de cada tabla de cada base en una instantánea) y por `pg_checksums --check` de la copia detenida (12 252 bloques, 0 errores en el ensayo). **Reanudación segura:** una copia parcial se conserva como `/var/lib/pgsql/anfeca8086.parcial_<fecha>` (no se borra) y se repite la copia desde el clúster vigente; nunca se inician dos instancias sobre los mismos datos (verificación con `pg_ctl status` antes de arrancar cualquiera de las dos) |
| 16 | `artisan down/up` como servidorweb o como apache **no puede crear** `storage/framework/down`: `storage/framework` es `root:root 755`. La conmutación habría fallado al entrar en mantenimiento | Crítica | `down`/`up` se ejecutan como root (quien ejecuta la ventana). El archivo queda 644, legible por Apache y por ambos pools. No se cambian permisos |

Evidencia SELinux: `sesearch` 4.7.1 de AlmaLinux 10, ejecutado en un contenedor desechable sobre una copia de solo lectura de `/etc/selinux/targeted/policy/policy.35`. No se instaló nada en el servidor.

## 2. Ensayo sin privilegios (`scripts/anfeca/pruebas/probar_conmutacion_simulada.sh`)

Ejecuta los scripts **reales** en `ANFECA_SIMULACION=1`:
- en una caja de arena que rechaza las rutas reales;
- con dobles de `systemctl`, `apachectl`, `podman`, `curl`, `ss`, el respaldo y la sincronización;
- con lectura de solo lectura de las huellas reales de `certificacion_anfeca`.

| Escenario | Resultado |
|---|---|
| 1. Falla del respaldo | ok: reabierto, VirtualHost intacto |
| 2. Falla de la sincronización | ok: reabierto, VirtualHost intacto, pool reiniciado |
| 3. Identidad incorrecta después del VirtualHost | ok: revertido y reabierto |
| 4. Conmutación correcta | ok |
| 5. Reejecución tras el éxito | ok: se detiene sin cambios |
| 6. Reversión con escrituras nuevas | ok: queda en mantenimiento con el VirtualHost original y enumera las tablas |
| 7. Reversión sin escrituras (reejecución) | ok: reabierto |
| 8. Bitácoras | ok: sin contraseñas |

Además, `sincronizar_bd.sh … ensayo` sobre el clúster 5433 real: restauración, 75 tablas idénticas, intercambio por renombre y su reversión correctos.

## 3. Estado verificado antes de la intervención administrativa

| Condición | Evidencia |
|---|---|
| Código activo de 8086 sin cambios desde la línea base de regresión | `diff -r` del árbol vivo frente a la copia comparada: 0 diferencias; mismo `.env` y catálogos |
| Artefacto aprobado | `anfeca8086_0d3ad60.tar.gz`, SHA-256 `83e25d18…` correcto; declara `0d3ad60`; compilación `app-ZPlUBWH0.js`; dependencias instaladas con PHP 8.5.12 (`check-platform-reqs` sin faltantes). Los commits posteriores solo cambian scripts y documentación, por lo que no se requiere un artefacto nuevo |
| Clúster 5433 | PostgreSQL 18.6; identificador 7712865409902226718 (5432: 7697381974958512424) |
| `certificacion_anfeca` | Huellas iguales al origen: 75 tablas y 200 filas; 36 tablas PDM; los 5 nombres literales siguen sin cambios (ni los 3 pendientes ni los 2 autorizados aplicados); `cert8086_app` sin SUPERUSER, CREATEDB ni CREATEROLE, sin objetos propios, sin CREATE en `public`, DML en 85 relaciones |
| Documentos históricos | 20/20 con huella idéntica al respaldo |
| Regresión | 40/40 pantallas y 96/97 pasos idénticos (`REGRESION_FUNCIONAL_VISUAL_8086.md`) |
| Apache frente a nginx (diferencias a revisar) | `public/.htaccess` idéntico. Apache sigue sirviendo los estáticos (incluido `.mjs`) igual que hoy. `LimitRequestBody` de httpd 2.4.63 (1 GiB) no limita antes que PHP (25M). FastCGI por `proxy:unix` con `ProxyFCGIBackendType FPM`. El VirtualHost candidato de `01` (paso D) prueba esta ruta real antes de conmutar |

## 4. Intervención administrativa (en orden)

Ejecutar desde una sesión iniciada de **servidorweb** (por `XDG_RUNTIME_DIR`), en el checkout de revisión:

```bash
cd /home/servidorweb/anfeca_trabajo/certificacion_8086
git log -1 --oneline                      # debe ser el commit de esta auditoría o posterior en correccion/equivalencia-f45
sudo ANFECA_COMMIT=0d3ad60 bash scripts/anfeca/despliegue_8086/01_preparar_servicios_root.sh --verificar
sudo ANFECA_COMMIT=0d3ad60 bash scripts/anfeca/despliegue_8086/01_preparar_servicios_root.sh --ejecutar
```

Después de `01`, verificar el candidato real con Apache, como servidorweb:
1. Ejecutar `regresion_visual.js` con `BASE=http://127.0.0.1:18087`.
2. Compararlo con `visual_actual` mediante `comparar_regresion_visual.js`.
3. Ejecutar `e2e_candidato.py` **solo sobre una copia**. El candidato apunta a `certificacion_anfeca`: no ejecutar pruebas de escritura contra él antes de conmutar.

Ventana autorizada:

```bash
sudo ANFECA_AUTORIZO_CONMUTACION=si bash scripts/anfeca/despliegue_8086/02_conmutar_root.sh
```

Inmediatamente después, la etapa C indicada al final de `02`. Ante cualquier diferencia:

```bash
sudo bash scripts/anfeca/despliegue_8086/03_revertir_root.sh
```

Copias de ensayo que permanecen en el clúster 5433 (contienen datos institucionales; su eliminación queda a criterio del administrador): `cert8086_pruebas_funcionales`, `comparacion_base`, `comparacion_candidato`, `certificacion_anfeca_sinc_*`.
