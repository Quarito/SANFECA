# Guía de seguridad para AlmaLinux — ANFECA_PRUEBAS

> Rama `fix/seguridad-anfeca` · base `desarrollo/anfeca-pruebas` @ `4498f2b` · 20/11/2026.
> Ejecuta los bloques **uno por uno** en `/var/www/ANFECA_PRUEBAS`. No compartas `.env`, contraseñas ni la salida de los volcados.

## Qué cambia en esta rama

| ID | Cambio | Archivos |
|---|---|---|
| V0 | Dejan de versionarse los 5 `.dump`, la captura del acuse, `elect(` y 16 archivos accidentales de la raíz; nuevas reglas en `.gitignore` | `.gitignore` |
| P4 | `/registro` redirige a `/registro/datos-personales` (antes daba 500) | `routes/web.php` |
| P5 | Una cuenta que ya tiene datos personales no puede volver a registrarse (antes daba 500) | `DatosSolicitanteController.php` |
| P6 | La recuperación exige 12 caracteres, igual que el registro | `RecuperacionPasswordController.php`, `NuevaPassword.vue` |
| P9 | `ExampleTest` espera la redirección a login | `tests/Feature/ExampleTest.php` |
| V8 | Límite de intentos por IP (igual que antes) **y además por cuenta**: login 15/h, recuperación 5/h, restablecer 10/h | `AppServiceProvider.php`, `routes/web.php` |

**Pruebas:** 39/39 en verde, incluidas 7 nuevas en `tests/Feature/SeguridadAnfecaTest.php`.

---

## Paso 1 — Copiar fuera de Git los archivos que la rama deja de versionar

> **Importante:** cuando el servidor reciba esta rama, Git **eliminará del disco** los archivos que dejan de estar versionados. Cópialos antes a un directorio protegido.

```bash
cd /var/www/ANFECA_PRUEBAS || exit 1
```

```bash
mkdir -p "$HOME/ANFECA_RESPALDOS/fuera_de_git_$(date +%Y%m%d)" && chmod 700 "$HOME/ANFECA_RESPALDOS" "$HOME/ANFECA_RESPALDOS/fuera_de_git_$(date +%Y%m%d)"
```

```bash
cp -a respaldos_demo respaldos_usuario_k elect\( ":USERPROFILE\\Downloads\\anfeca_acuse_vista_917.png" "$HOME/ANFECA_RESPALDOS/fuera_de_git_$(date +%Y%m%d)/"
```

```bash
ls -la "$HOME/ANFECA_RESPALDOS/fuera_de_git_$(date +%Y%m%d)/"
```

**Esperado:** aparecen las dos carpetas de volcados, la captura y `elect(`.

## Paso 2 — Respaldo, traer la rama y probar

Repite el `pg_dump` y el respaldo del código de `PASOS_SERVIDOR.md` (paso 1). Después:

```bash
git status --short
```

```bash
git fetch origin fix/seguridad-anfeca && git diff --stat HEAD origin/fix/seguridad-anfeca
```

Revisa la rama en un worktree aparte antes de fusionarla, como en `PASOS_SERVIDOR.md`:

```bash
git worktree add "$HOME/anfeca_revision_seguridad" origin/fix/seguridad-anfeca && cd "$HOME/anfeca_revision_seguridad" && composer install --no-interaction && cp .env.example .env && php artisan key:generate && php artisan test
```

**Esperado:** 39 pruebas pasan.

Si todo pasa, fusiona por PR `fix/seguridad-anfeca` → `desarrollo/anfeca-pruebas`. Después, en el servidor:

```bash
cd /var/www/ANFECA_PRUEBAS && git pull --ff-only origin desarrollo/anfeca-pruebas
```

```bash
npm run build && php artisan optimize:clear
```

`npm run build` es necesario por el cambio en `NuevaPassword.vue`.

**Prueba manual rápida:**
1. `/registro` debe redirigir.
2. En la recuperación, una contraseña de 10 caracteres debe rechazarse.
3. El login normal debe funcionar.

## Paso 3 — Hacer privado el repositorio (V0)

Solo puede hacerlo quien administra `Quarito/SANFECA`:

1. Entra a GitHub → **Settings** → **General** → **Danger Zone** → **Change repository visibility** → **Private**.
2. Comprueba en una ventana privada del navegador que `https://github.com/Quarito/SANFECA` responde 404.

> Esta rama deja de versionar los volcados **a partir de ahora**, pero **siguen en el historial**. Si el volcado contiene datos de personas reales, el historial también debe limpiarse (paso 4).

## Paso 4 — Si el volcado contenía datos reales: limpiar el historial

Esto reescribe el historial de **todas** las ramas. Coordínalo con todas las personas que tengan un clon.

```bash
pip install --user git-filter-repo
```

```bash
git clone --mirror https://github.com/Quarito/SANFECA.git "$HOME/SANFECA_espejo.git" && cd "$HOME/SANFECA_espejo.git"
```

```bash
git filter-repo --invert-paths --path-glob '*.dump' --path-glob ':USERPROFILE*' --path 'elect('
```

```bash
git push --force --mirror origin
```

Después, cada clon debe volver a clonarse. Además:
- Fuerza el cambio de contraseña de las cuentas reales afectadas.
- Invalida las sesiones: borra las filas de `sessions` **solo** tras un respaldo y con autorización.
- Valora la notificación conforme a la normativa de datos personales aplicable.

## Paso 5 — Configuración de `.env` (V1), en el servidor

Edita `.env` con tu editor; no lo copies ni lo compartas.

```text
APP_ENV=staging
APP_DEBUG=false
APP_LOCALE=es
```

```bash
php artisan optimize:clear && php artisan about | grep -iE "environment|debug"
```

**Esperado:** `Environment ... staging` y `Debug Mode ... OFF`.

Con `APP_ENV` distinto de `local` se desactivan las rutas `/preview/*`. Compruébalo:

```bash
php artisan route:list | grep -c preview
```

**Esperado:** `0`.

## Paso 6 — HTTPS interno con Apache (V2)

Requiere un certificado: interno de la institución o autofirmado solo para pruebas. Ejemplo con un certificado autofirmado de 1 año.

```bash
sudo dnf install -y mod_ssl
```

```bash
sudo mkdir -p /etc/pki/anfeca && sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout /etc/pki/anfeca/anfeca.key -out /etc/pki/anfeca/anfeca.crt -subj "/CN=192.168.7.130" && sudo chmod 600 /etc/pki/anfeca/anfeca.key
```

En el VirtualHost de ANFECA_PRUEBAS (por ejemplo, `/etc/httpd/conf.d/anfeca_pruebas.conf`), agrega o adapta un bloque con el puerto que uses:

```apache
Listen 8443 https
<VirtualHost *:8443>
    DocumentRoot /var/www/ANFECA_PRUEBAS/public
    SSLEngine on
    SSLCertificateFile /etc/pki/anfeca/anfeca.crt
    SSLCertificateKeyFile /etc/pki/anfeca/anfeca.key
    <Directory /var/www/ANFECA_PRUEBAS/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

```bash
sudo apachectl configtest && sudo firewall-cmd --permanent --add-port=8443/tcp && sudo firewall-cmd --reload && sudo systemctl reload httpd
```

Si SELinux bloquea el puerto:

```bash
sudo semanage port -a -t http_port_t -p tcp 8443
```

Cuando `https://192.168.7.130:8443` funcione, agrega a `.env`:

```text
APP_URL=https://192.168.7.130:8443
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
```

```bash
php artisan optimize:clear
```

Al activar `SESSION_ENCRYPT`, las sesiones abiertas se cierran una vez. No abras el puerto a Internet.

## Reversión

- **Código:** `git revert` del commit de fusión, o volver al commit anterior con `git checkout <commit> -- <archivo>`.
- **Volcados:** se restauran desde la copia del paso 1.
- **`.env` y Apache:** volver a los valores anteriores y ejecutar `php artisan optimize:clear` y `systemctl reload httpd`.
