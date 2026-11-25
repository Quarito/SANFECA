# Configuración de zonas y cuentas de coordinación

Procedimiento reproducible y seguro. **Este documento no contiene credenciales** y ninguna debe
agregarse al repositorio.

## 1. Respaldo previo (obligatorio)

Antes de modificar datos, genere un respaldo verificable de la base y compruebe que puede restaurarse
en una base desechable:

```bash
pg_dump -Fc -d <base> -f /ruta/segura/respaldo_$(date +%F_%H%M).dump
pg_restore --list /ruta/segura/respaldo_*.dump | head      # el archivo es legible
# restauración de prueba en una base o instancia desechable, nunca sobre la base en uso
```

## 2. Catálogo de zonas (solo con fuente oficial)

La tabla `zonas` (`siglas` única, `descripcion`) se carga **únicamente** con el catálogo oficial de
ANFECA. A 2026-11-24 el proyecto no contiene ese catálogo: no invente siglas ni descripciones.

Con el catálogo oficial confirmado, en una sola transacción:

```sql
begin;
insert into public.zonas (siglas, descripcion, created_at, updated_at)
values ('<SIGLAS>', '<Descripción oficial>', now(), now());   -- una fila por zona oficial
-- Asignar la zona a cada institución SOLO si la correspondencia está comprobada
update public.instituciones_afiliadas
   set zona_id = (select id from public.zonas where siglas = '<SIGLAS>'), updated_at = now()
 where id = <id_institucion>;
-- Verificar antes de confirmar
select i.id, i.zona_anfeca, z.siglas from public.instituciones_afiliadas i
  left join public.zonas z on z.id = i.zona_id order by i.id;
commit;   -- o rollback si algo no coincide
```

Los identificadores de la tabla `zonas` **no** equivalen al número de zona: use siempre las siglas.

## 3. Cuentas de coordinación

Comando: `php artisan anfeca:coordinador-alta`.

- Crea la cuenta con contraseña aleatoria de 20 caracteres, guardada con el hashing de Laravel.
- Escribe la contraseña **solo** en el archivo indicado en `--credenciales`, que debe estar fuera del proyecto y queda con permisos 0600. Nunca la muestra en pantalla.
- Se niega a modificar cuentas de solicitantes, o cuentas con datos personales o solicitudes.
- Las zonas se indican por **siglas del catálogo**. Si una sigla no existe, el comando falla sin escribir nada.
- Es idempotente: al repetirlo deja las zonas exactamente como se indican.

```bash
install -d -m 700 ~/ANFECA_CREDENCIALES
php artisan anfeca:coordinador-alta coordinador.nacional@anfeca.test coordinador_nacional \
    --nombre="Coordinación Nacional (prueba)" --credenciales=$HOME/ANFECA_CREDENCIALES/coordinacion.tsv
php artisan anfeca:coordinador-alta coordinador.zona3@anfeca.test coordinador_zona \
    --nombre="Coordinación Zona 3 (prueba)" --zona=<SIGLAS_ZONA_3> --credenciales=$HOME/ANFECA_CREDENCIALES/coordinacion.tsv
```

Sin `--zona`, un coordinador de zona queda **sin asignación** y no ve ningún expediente. Para agregar
su zona más tarde, repita el comando con `--zona`; la contraseña se conserva salvo que use `--restablecer`.

El alta pública (`/registro/datos-personales`) asigna siempre `rol = solicitante` y no acepta el rol
como dato del formulario. Las cuentas de coordinación no pueden usar el flujo de solicitantes:
se redirigen a `/coordinacion`.
