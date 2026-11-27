# Despliegue de 8008 a partir de 8086 validado

> **Estado: NO EJECUTABLE TODAVÍA.** Requisito previo: 8086 conmutado y aprobado; ver `INTEGRACION_CERTIFICACION_ANFECA_8086.md` §10. 8008 no se modificó; todos los datos de 8008 citados aquí se obtuvieron en modo de solo lectura.

## Patrón validado en 8086 que se replica (con valores propios de 8008)

| Elemento | 8086 (validado) | 8008 (a crear; verificar que esté libre) |
|---|---|---|
| Clúster PostgreSQL 18 exclusivo | `/var/lib/pgsql/anfeca8086`, `127.0.0.1:5433`, unidad `anfeca-pg8086` | `/var/lib/pgsql/anfeca8008`, `127.0.0.1:<5434 u otro libre>`, unidad `anfeca-pg8008`, con su **propio `initdb`** (identificador distinto de 5432 y de 5433) |
| Roles | `cert8086_propietario` y `cert8086_app` | `cert8008_propietario` y `cert8008_app`, con contraseñas propias en `~/ANFECA_CREDENCIALES/pg8008_certificacion_anfeca.txt` |
| Base | `certificacion_anfeca` (en 5433) | `certificacion_anfeca` (en su propio clúster; el mismo nombre no colisiona) |
| PHP-FPM 8.5.12 | `anfeca8086-fpm`, `127.0.0.1:19086`, usuario 48 | `anfeca8008-fpm`, `127.0.0.1:<19008 u otro libre>`, con el usuario dueño de su storage |
| Release | `/var/www/ANFECA_8086/releases/<commit>` y storage enlazado | `/var/www/ANFECA_8008/releases/<commit>` y storage propio de 8008 |
| Scripts | `scripts/anfeca/despliegue_8086/01…03` y `sincronizar_bd.sh` | Copias parametrizadas (`despliegue_8008/`): rutas, puertos, roles, identificadores esperados y base de origen `bd_anfeca` |

Diferencia esencial: 8086 restauró su propia base porque ya tenía el esquema del artefacto. **8008 (`bd_anfeca`, 15 tablas, 6 migraciones) no lo tiene.** Sobre `certificacion_anfeca` nueva se instalan:
1. las migraciones del artefacto;
2. el PDM aprobado (variante decidida en §2 de la integración de 8086);
3. las extensiones y los catálogos;
4. **después**, el traslado de datos propio de 8008, probado primero en una restauración desechable (§5).

No se copian usuarios, solicitudes, certificados ni documentos de 8086.

## 0. Estado real de 8008 (diagnóstico del 27-nov)

| Elemento | Valor observado | Consecuencia |
|---|---|---|
| Aplicación | `/var/www/anfeca`, rama `main` `227060e`, con cambios sin commit (`RegistroController`, `routes/web.php`, una vista eliminada y una migración sin registrar) | **Es otra aplicación**, anterior al código de 8086. No basta con cambiar la conexión |
| Puerto 8008 | Sin VirtualHost en `/etc/httpd` y sin proceso escuchando; `APP_URL=http://localhost:8000` | Hay que crear un VirtualHost nuevo (cambio en Apache con `sudo`) |
| Laravel y PHP | Laravel 13.37.0 sobre PHP 8.4.27 del sistema | — |
| Base | `bd_anfeca` (propietario `user_anfeca`, UTF8, es_MX.UTF-8), 6 migraciones, 15 tablas en `public`, sin `anfeca_modelo` | Sus datos tienen otra estructura: se requiere una **migración de datos propia** (§5) |
| Clúster | `127.0.0.1:5432`, `system_identifier` 7697381974958512424, **el mismo de 8086** | Si 8086 ocupa `certificacion_anfeca` en un clúster propio (5433), 8008 puede usar `certificacion_anfeca` en 5432. Nunca deben apuntar ambos a la misma base |
| Documentos privados | `storage/app/private` vacío | Confirmar si existen documentos en otra ruta |
| Sesiones y cola | `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database` | Inventariar los workers antes de la ventana |

## 1. Variables por comprobar antes de ejecutar (no copiar valores de 8086)

```bash
export APP8008=/var/www/anfeca          # confirmar con: ls -ld "$APP8008"
export BD_ORIGEN_8008=bd_anfeca         # confirmar con: grep ^DB_DATABASE= "$APP8008/.env"
export ROL_8008=user_anfeca             # confirmar con: grep ^DB_USERNAME= "$APP8008/.env"
export PG_8008_HOST=127.0.0.1 PG_8008_PORT=5432   # el clúster asignado a 8008; distinto del de 8086
export PUERTO_FPM_8008=<puerto local libre, distinto del de 8086>   # ss -lnt | grep ":$PUERTO_FPM_8008 "
export R8008=$HOME/ANFECA_RESPALDOS/8008_$(date +%Y%m%d_%H%M%S)
export ARTEFACTO=<commit aprobado en 8086>        # git rev-parse del commit desplegado en 8086
```

## 2. Diagnóstico y aislamiento (solo lectura)

```bash
apachectl -S 2>/dev/null | grep -E '8008|8086'; ss -lnt | grep -E ':(8008|8086|5432|5433)\b'
git -C "$APP8008" status --short --branch; git -C "$APP8008" rev-parse HEAD
PGPASSWORD=… psql -h "$PG_8008_HOST" -p "$PG_8008_PORT" -U "$ROL_8008" -d "$BD_ORIGEN_8008" -Atc \
  "set default_transaction_read_only=on; select current_database(), version(); select count(*) from migrations"
# El DBA compara el identificador de ambos clústeres; deben ser DISTINTOS si los dos usan certificacion_anfeca:
sudo -u postgres psql -p "$PG_8008_PORT" -Atc 'select system_identifier from pg_control_system()'
sudo -u postgres psql -p 5433 -Atc 'select system_identifier from pg_control_system()'   # clúster de 8086
```

## 3. Respaldo y restauración verificada

```bash
mkdir -m 700 "$R8008"; cd "$APP8008"
cp .env "$R8008/env_8008.env"; git diff > "$R8008/cambios_locales.diff"; git status --short > "$R8008/git_status.txt"
tar czf "$R8008/codigo_8008.tar.gz" --exclude=vendor --exclude=node_modules -C /var/www anfeca
# Base: el script exige que .env apunte a la base esperada y verifica la restauración en un contenedor desechable.
ANFECA_RESPALDOS_DIR="$R8008" bash <checkout_revision>/scripts/anfeca/respaldo_verificado.sh "$BD_ORIGEN_8008" main "$(git rev-parse --short HEAD)"
# Requisito: RESULTADO.txt con «respaldo restaurable y equivalente» y huellas idénticas en todas las tablas.
```

Antes de ejecutar, medir el tamaño de la base (`select pg_database_size(current_database())`). Si supera unos 300 MB, ampliar el tmpfs del script.

## 4. Base `certificacion_anfeca` de 8008 (DBA)

```bash
sudo -u postgres createdb -p "$PG_8008_PORT" --template=template0 --owner="$ROL_8008" \
  --encoding=UTF8 --locale=es_MX.UTF-8 certificacion_anfeca        # falla si ya existe: no sobrescribir
```

Desde el artefacto aprobado en 8086 (un worktree en `$ARTEFACTO`, con `.env` apuntando a `certificacion_anfeca` de 8008):

```bash
php artisan migrate --force                                    # esquema Laravel del artefacto
ANFECA_MODELO_BD_PERMITIDA=certificacion_anfeca ANFECA_PDM_AUTORIZAR_INSTALACION=si php artisan anfeca:modelo-instalar --ejecutar --confirmar
ANFECA_MODELO_BD_PERMITIDA=certificacion_anfeca ANFECA_PDM_AUTORIZAR_EXTENSIONES=si php artisan migrate --path=database/migrations_pdm --force
ANFECA_MODELO_BD_PERMITIDA=certificacion_anfeca ANFECA_PDM_AUTORIZAR_CATALOGOS=si php artisan db:seed --class='Database\Seeders\Pdm\CatalogosPdmSeeder' --force
php artisan anfeca:modelo-verificar
PGPORT=<puerto desechable> bash scripts/anfeca/pruebas/comparar_pdm_original.sh "$R8008/comparacion_pdm"  # 36/173/36/35/96/36
```

## 5. Conservación de los datos propios de 8008

Los datos de 8008 (`bd_anfeca`: 15 tablas, 6 migraciones) **no** tienen la estructura de 8086.

1. Inventario tabla por tabla del origen (`\d+` y conteos) frente al esquema del artefacto.
2. Script de traslado específico, desarrollado en una rama y probado **sobre la restauración del §3** en un clúster desechable, con conteos de origen = destino y huellas por registro.
3. No se copian usuarios, solicitudes, certificados ni documentos de 8086. Cada ambiente conserva los suyos.
4. Decisiones D-11, D-13 y D-14 registradas con `base_de_datos = 'certificacion_anfeca'` **de 8008** y con evidencia propia.
5. Ejecutar `anfeca:pdm-migrar` (plan, `--simular` y `--ejecutar --confirmar`), `anfeca:pdm-conciliar` y la equivalencia: cero diferencias injustificadas. PDM permanece **apagado** en el primer despliegue.

## 6. PHP 8.5.12 y pool exclusivo de 8008 (administrador)

```bash
podman image exists localhost/anfeca-php-fpm:8.5.12 || \
  podman build -f scripts/anfeca/runtime/Dockerfile.php8512 -t localhost/anfeca-php-fpm:8.5.12 scripts/anfeca/runtime
# Unidad del sistema (root), identidad propia de 8008. Ajustar el UID/GID al dueño real de $APP8008/storage:
podman run -d --name anfeca-fpm-8008 --user <uid>:<gid> --network host \
  -v "$APP8008:$APP8008" localhost/anfeca-php-fpm:8.5.12 \
  php-fpm -F -d listen=127.0.0.1:$PUERTO_FPM_8008      # o archivo de pool propio montado de solo lectura
semanage port -a -t http_port_t -p tcp "$PUERTO_FPM_8008"
```

VirtualHost nuevo `/etc/httpd/conf.d/anfeca-8008.conf`:

```apache
Listen 8008
<VirtualHost *:8008>
    DocumentRoot /var/www/anfeca/public
    <Directory /var/www/anfeca/public>
        AllowOverride All
        Require ip <red autorizada>
    </Directory>
    <FilesMatch \.php$>
        SetHandler "proxy:fcgi://127.0.0.1:${PUERTO_FPM_8008}"
    </FilesMatch>
    ErrorLog /var/log/httpd/anfeca-8008-error.log
    CustomLog /var/log/httpd/anfeca-8008-access.log combined
</VirtualHost>
```

Después:
- `apachectl configtest && systemctl reload httpd`;
- `ausearch -m avc -ts recent` sin denegaciones;
- comprobar que 8084, 8085 y 8086 responden igual que antes.

## 7. Artefacto, catálogos y documentos

1. Desplegar `$ARTEFACTO` en una ruta nueva (p. ej., `/var/www/anfeca_8008_<commit>`); conservar `/var/www/anfeca` como punto de retorno.
2. Instalar con `composer install --no-dev` desde el lock, en PHP 8.5.12; `npm ci --ignore-scripts && npm run build`.
3. Catálogos: copiar los JSON de `~/ANFECA_FUENTES` (no los de 8086) y verificar SHA-256, versión, permisos 0640 y directorio 2750.
4. Documentos: conservar la ruta privada propia de 8008 y verificar las huellas antes y después con `huellas_documentos.sh http://<host>:8008`.

## 8. Pruebas posteriores

- `composer check-platform-reqs --lock`;
- `phpunit` (SQLite, pgsql y revision-pg en clústeres desechables);
- `probar_php_fpm.sh`;
- inicio de sesión;
- registro desde cero con un usuario ficticio;
- primera vez y refrendo;
- formación con catálogos;
- documentos;
- compromiso, envío y acuse PDF;
- Coordinación por rol y zona;
- consulta, sin modificar, de expedientes reales;
- logs sin errores;
- conciliación PDM.

## 9. Reversión

1. Restaurar el VirtualHost anterior (o retirar `anfeca-8008.conf`) y recargar Apache.
2. Detener `anfeca-fpm-8008`.
3. Restaurar `$R8008/env_8008.env` y reapuntar a `/var/www/anfeca`.
4. `bd_anfeca` no se toca durante todo el proceso.
5. `certificacion_anfeca` de 8008 se conserva para análisis. Si recibió escrituras nuevas, conciliarlas antes de volver.
