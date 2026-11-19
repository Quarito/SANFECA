# Pasos en el servidor — integrar el esquema aislado del PDM

> Aplica **solo** en `/var/www/ANFECA_PRUEBAS` con la BD `bd_laravelanfeca_pruebas`. **No** en `/var/www/ANFECA`.
> Ejecuta los bloques **uno por uno**. Comparte la salida (sin contraseñas ni `.env`) antes de pasar al siguiente.
> Nada de esto modifica tablas de `public` ni el código existente: solo agrega archivos nuevos y un esquema nuevo.

## Paso 0 — Diagnóstico (solo lectura)

```bash
cd /var/www/ANFECA_PRUEBAS || exit 1
```

```bash
php -v | head -1 && php artisan --version
```

```bash
git status --short && git branch --show-current
```

```bash
php artisan migrate:status | tail -5
```

**Esperado:**
- PHP **≥ 8.4.1**. El `composer.lock` del repo exige Symfony 8.1, que no instala con PHP 8.3.
- Laravel 13.x.
- La lista de cambios locales sin commit, que **no se deben perder**.

## Paso 1 — Respaldo verificable de BD y código

```bash
mkdir -p "$HOME/ANFECA_RESPALDOS" && chmod 700 "$HOME/ANFECA_RESPALDOS"
```

```bash
pg_dump -h 127.0.0.1 -p 5432 -U rol_anfecalaravel -d bd_laravelanfeca_pruebas -Fc -f "$HOME/ANFECA_RESPALDOS/anfeca_pruebas_$(date +%Y%m%d_%H%M%S).dump"
```

```bash
ls -lh "$HOME"/ANFECA_RESPALDOS/*.dump | tail -1 && pg_restore -l "$(ls -t "$HOME"/ANFECA_RESPALDOS/*.dump | head -1)" | head -5
```

Prueba de restauración en una BD **separada**. Requiere permiso `CREATEDB`; si no lo tienes, pídelo al DBA.

```bash
createdb -h 127.0.0.1 -U rol_anfecalaravel bd_anfeca_restore_prueba && pg_restore -h 127.0.0.1 -U rol_anfecalaravel -d bd_anfeca_restore_prueba --no-owner "$(ls -t "$HOME"/ANFECA_RESPALDOS/*.dump | head -1)" && psql -h 127.0.0.1 -U rol_anfecalaravel -d bd_anfeca_restore_prueba -Atc "select count(*) from users"
```

Respaldo del código, incluidos los archivos sin seguimiento:

```bash
tar --exclude=vendor --exclude=node_modules -czf "$HOME/ANFECA_RESPALDOS/codigo_pruebas_$(date +%Y%m%d_%H%M%S).tgz" -C /var/www ANFECA_PRUEBAS
```

**Esperado:**
- El `.dump` con un tamaño mayor a 0.
- `pg_restore -l` lista objetos.
- El conteo de `users` en la BD restaurada coincide con el de la BD de pruebas.

## Paso 2 — Traer solo los archivos nuevos de la rama, sin tocar tu trabajo local

No uses `git checkout ANFECA_BD_final`, `git stash`, `reset` ni `clean`: tienes cambios sin commit. Trae únicamente los archivos nuevos.

```bash
git fetch origin ANFECA_BD_final
```

```bash
git diff --stat HEAD origin/ANFECA_BD_final
```

```bash
git checkout origin/ANFECA_BD_final -- config/anfeca_modelo.php app/Services/AnfecaModeloInspector.php app/Console/Commands/AnfecaModeloInstalar.php app/Console/Commands/AnfecaModeloVerificar.php database/sql/anfeca_modelo tests/Feature/AnfecaModeloSqlTest.php docs/anfeca
```

```bash
git status --short | grep -i anfeca_modelo
```

**Esperado:** solo aparecen archivos **nuevos** (`A`). Si alguno sale como modificado (`M`), detente y comparte la salida.

## Paso 3 — Comprobaciones sin BD

```bash
php -l app/Console/Commands/AnfecaModeloInstalar.php && php -l app/Console/Commands/AnfecaModeloVerificar.php && php -l app/Services/AnfecaModeloInspector.php
```

```bash
php artisan test --filter=AnfecaModeloSqlTest
```

```bash
php artisan list anfeca
```

**Esperado:** las 10 pruebas pasan y se listan `anfeca:modelo-instalar` y `anfeca:modelo-verificar`.

## Paso 4 — Instalación en seco (no modifica nada)

```bash
php artisan anfeca:modelo-instalar
```

**Esperado:**
- `revisión estática correcta`.
- `Base de datos: bd_laravelanfeca_pruebas — permitida`.
- `Comprobaciones correctas`.

Si dice que el esquema ya existe, **no sigas**: ejecuta el paso 6.

## Paso 5 — Instalación real (solo después del paso 1 confirmado)

```bash
php artisan anfeca:modelo-instalar --ejecutar
```

Responde `yes` a la confirmación.

**Esperado:** `Esquema anfeca_modelo creado: 36 tablas, 35 FK. public sin cambios.`

Si algo falla, la transacción se revierte completa y no queda nada a medias.

## Paso 6 — Verificación (solo lectura)

```bash
php artisan anfeca:modelo-verificar
```

```bash
psql -h 127.0.0.1 -U rol_anfecalaravel -d bd_laravelanfeca_pruebas -Atc "select count(*) from pg_tables where schemaname='anfeca_modelo'"
```

**Esperado:**
- 36/36 tablas y 35/35 FK.
- La tabla de divergencias PDM ↔ Laravel. Compártela: es la base de la fase 1 del mapeo.

Después, repite el protocolo de usuario de prueba (registro, login y mis datos) para confirmar que la aplicación sigue igual.

## Reversión (manual, solo si se decide retirar el esquema)

1. Confirmar que **todas** las tablas del esquema están vacías:

```bash
php artisan anfeca:modelo-verificar | grep -i "vacías\|con datos"
```

2. Solo si la salida dice `Todas las tablas del PDM están vacías.`, abre una sesión interactiva:

```bash
psql -h 127.0.0.1 -U rol_anfecalaravel -d bd_laravelanfeca_pruebas
```

Dentro de `psql`, ejecuta la transacción. Nada se aplica hasta el `COMMIT`.

```sql
BEGIN;
-- Aborta si alguna tabla del esquema tiene filas.
DO $$
DECLARE r record; n bigint;
BEGIN
  FOR r IN SELECT tablename FROM pg_tables WHERE schemaname = 'anfeca_modelo' LOOP
    EXECUTE format('SELECT count(*) FROM anfeca_modelo.%I', r.tablename) INTO n;
    IF n > 0 THEN RAISE EXCEPTION 'La tabla % tiene % filas: no se retira', r.tablename, n; END IF;
  END LOOP;
END $$;
-- Aborta si algún objeto de OTRO esquema depende de anfeca_modelo (vistas, FK desde public, etc.).
DO $$
BEGIN
  IF EXISTS (
    SELECT 1
    FROM pg_depend d, pg_identify_object(d.classid, d.objid, d.objsubid) o
    WHERE d.refobjid IN (SELECT oid FROM pg_class WHERE relnamespace = 'anfeca_modelo'::regnamespace)
      AND coalesce(o.schema, '') <> 'pg_toast'
      AND o.identity NOT LIKE '%anfeca_modelo.%'
  ) THEN RAISE EXCEPTION 'Hay objetos fuera del esquema que dependen de él: no se retira'; END IF;
END $$;
DROP SCHEMA anfeca_modelo CASCADE;   -- el NOTICE debe listar solo objetos anfeca_modelo.*
```

Revisa el `NOTICE`. Si solo aparecen objetos `anfeca_modelo.*`, confirma:

```sql
COMMIT;
```

Si aparece cualquier otro objeto, cancela y no se habrá borrado nada:

```sql
ROLLBACK;
```

Si cualquiera de los `DO` falla, la transacción queda abortada; en ese caso, ejecuta `ROLLBACK;`. Esto no afecta a `public`.

Si algo de `public` se viera afectado, cosa que el comando impide, restaura desde el `.dump` del paso 1 en una BD nueva y compara antes de sustituir.
