# Operación en 8088 · Fase 9 (SEPOMEX, domicilio en el modelo vigente y control de sesiones)

Rama de integración controlada: **`integracion/fase9-8088`**, commit de integración **`34b04d2`** (merge `--no-ff` de
`desarrollo/catalogo-sepomex` sobre `integracion/anfeca-36-tablas-definitivo` `6c73286`, la versión desplegada).
Scripts: `scripts/anfeca_8088/servidor/fase9/`. **Nada de esto se ha ejecutado en el servidor**: se ensayó en un
entorno aislado equivalente (ver §Ensayo).

## Antes de empezar

Conexión (PowerShell o VS Code «Remote - SSH»):

```powershell
ssh servidorweb@192.168.7.130
```

Archivo oficial SEPOMEX (fuera de GitHub y de `public/`). Desde el equipo donde está el ZIP:

```powershell
scp .\CPdescargaxls.zip servidorweb@192.168.7.130:/tmp/
```

En el servidor:

```bash
sudo install -d -m 750 -o root -g servidorweb /var/lib/anfeca8088/sepomex && sudo unzip -o /tmp/CPdescargaxls.zip -d /var/lib/anfeca8088/sepomex && rm /tmp/CPdescargaxls.zip && sha256sum /var/lib/anfeca8088/sepomex/CPdescarga.xls
```

La huella debe ser `0a8e2611b92635f47c646816b3641354ab4a554f64b058447890d77e8a052821`.

Paquete SQL privado (alternativa B, recomendada): se transfiere por `scp`, nunca por GitHub.

```powershell
scp .\SEPOMEX_ANFECA_21112026.zip .\SEPOMEX_ANFECA_21112026.zip.sha256 servidorweb@192.168.7.130:/tmp/
```

```bash
cd /tmp && sha256sum -c SEPOMEX_ANFECA_21112026.zip.sha256 && sudo unzip -q -o SEPOMEX_ANFECA_21112026.zip -d /var/lib/anfeca8088/sepomex && sudo chown -R root:servidorweb /var/lib/anfeca8088/sepomex/SEPOMEX_ANFECA && sudo chmod -R g+rX,o-rwx /var/lib/anfeca8088/sepomex/SEPOMEX_ANFECA && rm /tmp/SEPOMEX_ANFECA_21112026.zip* && (cd /var/lib/anfeca8088/sepomex/SEPOMEX_ANFECA && sha256sum -c --quiet SHA256SUMS && echo "Paquete íntegro")
```

Obtener los scripts sin tocar el checkout existente:

```bash
git -C /home/servidorweb/anfeca_8088_codigo fetch origin integracion/fase9-8088 && mkdir -p ~/anfeca_fase9_scripts && git -C /home/servidorweb/anfeca_8088_codigo archive FETCH_HEAD scripts/anfeca_8088/servidor/fase9 scripts/anfeca_8088/pruebas | tar -x -C ~/anfeca_fase9_scripts && cd ~/anfeca_fase9_scripts/scripts/anfeca_8088/servidor/fase9 && ls
```

Parámetros en `comun.inc.sh` (rutas, puerto 5488, base, rol, usuario `apache`, archivo SEPOMEX). Si difieren, se exportan
antes de cada bloque (p. ej. `export PG_BIN=/usr/pgsql-18/bin PGPORT=5488`). Las contraseñas se piden sin mostrarse.
Para crear bases nuevas (prueba y restauración) se usa `sudo -u postgres` por socket local; si el clúster del 8088 usa otro
socket: `export PG_SOCKET=/run/postgresql-anfeca8088`.

## Bloques

| Bloque | Comando | Escribe | Detiene si… |
|---|---|---|---|
| 1 · Diagnóstico | `sudo -E bash 01_diagnostico.sh` | Nada | — (solo informa) |
| 2 · Respaldo | `sudo -E bash 02_respaldo.sh` | `/var/backups/anfeca8088/fase9_<fecha>` y una base **nueva** de restauración | volcado ilegible, huellas o conteos distintos |
| 3 · Preparación del código | `bash 03_preparar_codigo.sh` | `/home/servidorweb/anfeca_8088_fase9` (directorio nuevo) | cambios locales, commits no incluidos, código desplegado distinto del repositorio, faltan archivos de sesión/fase 9, falla Vite |
| **4B · Diagnóstico SQL** (alternativa B) | `bash 04b_diagnostico_sql.sh` | Nada (`00_diagnostico_previo.sql`, solo lectura) | paquete alterado, estructura distinta de 36/173/35, «CONFLICTO» |
| **5B · Carga SQL** (alternativa B) | `CONFIRMAR=si RESPALDO=… bash 05b_cargar_sepomex_sql.sh` | Solo filas faltantes en las tres tablas (una transacción; repite la carga para comprobar 0 inserciones) | sin respaldo, error de carga (todo se revierte), repetición con inserciones |
| 4A · Diagnóstico Artisan (alternativa A) | `bash 04_diagnostico_sepomex.sh` | Nada (transacción de solo lectura) | huella distinta, estructura distinta, conflicto de nombres |
| 5A · Aplicación Artisan (alternativa A) | `CONFIRMAR=si RESPALDO=… bash 05_aplicar_sepomex.sh` | Solo filas faltantes en `entidad_federativa`, `alcaldia_municipio`, `colonia` | sin respaldo verificado, pendientes tras aplicar |
| 6 · Despliegue | `CONFIRMAR=si RESPALDO=… sudo -E bash 06_compilar_desplegar.sh` | `/var/www/ANFECA_8088` (conserva `.env` y `storage/`) | commit distinto, sin compilación |
| 7 · Pruebas | `RESPALDO=… sudo -E bash 07_pruebas.sh` | Parte B: base **nueva** `certificacion_anfeca_pruebas_fase9` y copia `/home/servidorweb/anfeca_8088_pruebas` | — (reporta) |
| 8 · Reversión | `RESPALDO=… [MODO=datos] [CONFIRMAR=si] sudo -E bash 08_reversion.sh` | Simula por omisión | — |
| 8B · Reversión del catálogo | `psql … $(cat marcas) -f 05_reversion_catalogos.sql` (y `-v confirmar=si` solo con autorización) | Simula por omisión | — |

**Alternativas de carga equivalentes — basta UNA.** A: importador `anfeca:sepomex` (bloques 4A y 5A; requiere PHP y el
archivo XLS en el servidor). **B (prioritaria): scripts SQL con psql** (bloques 4B y 5B; solo requiere el paquete
`SEPOMEX_ANFECA` y `psql`). Ambas cargan exactamente los mismos registros (comprobado: ver
`evidencias_8088/sepomex_sql/`). Orden con la alternativa B: 1 → 2 → 3 → 4B → 5B → 6 → 7. La carga es independiente del
despliegue del código: el catálogo puede cargarse antes del bloque 6 (la versión anterior no lo consulta).

Revisar el resultado de cada bloque (cada uno deja su registro en `~/anfeca_fase9/<fecha>_<bloque>/`) antes de pasar al siguiente.

### Puntos de control

- **Bloque 1**: `diferencias_desplegado_vs_repo.txt` debe estar vacío. Si no lo está, existen cambios posteriores en
  `/var/www/ANFECA_8088`: se copian a la rama, se integran con commit propio y el bloque 3 se repite con `REVISADO=si`.
  La consulta de `tipo_comunicacion` mostrará datos geográficos que guardó la versión anterior: **se conservan y no se
  usan** (no se eliminan datos).
- **Bloque 2**: «Conteos idénticos en la base restaurada» y la base `certificacion_anfeca_restauracion_<fecha>` creada.
- **Bloque 3**: el bloque comprueba que la rama contiene el commit de integración `34b04d2`, la versión del repositorio
  del servidor y las 8 piezas de sesión/fase 9 (no hay merge ciego: se detiene ante cualquier diferencia). Registra en
  `commits_posteriores_a_integracion.txt` los commits posteriores a `34b04d2` (solo scripts, evidencias y documentación)
  y fija el commit preparado en `.commit_preparado`; el bloque 6 se niega a desplegar si ese directorio cambió.
- **Bloque 4**: entidades existentes conciliadas (sus nombres de INEGI coinciden con SEPOMEX), «Pendientes de insertar»
  esperados en una base sin catálogo: municipios 2 478 y colonias 158 507.
- **Bloque 5A**: repetición con 0 pendientes (idempotencia) y expedientes sin cambios.
- **Bloque 4B**: estructura 36/173/35; sección 5 en 0; sección 6 sin «CONFLICTO»; sección 7 sin conflictos de grafía.
- **Bloque 5B**: «Insertados · entidades: … · municipios: … · colonias: …», «Transacción confirmada», verificación con
  «coincide» en las 32 entidades (base sin catálogo previo) e integridad en 0; repetición con 0 inserciones. La línea
  «Marcas de reversión» queda en `marcas_reversion.txt` y en el directorio del respaldo.

## B · Requisitos de la batería (parte B del bloque 7)

- `python3` con `requests`: `python3 -c "import requests"` (si falta: `sudo dnf install -y python3-requests`).
- Node con Playwright 1.63 y Chromium, en una carpeta de herramientas separada del proyecto:

```bash
mkdir -p ~/anfeca_herramientas && cd ~/anfeca_herramientas && npm install --no-save playwright@1.63.0 && npx playwright install chromium && export NODE_PATH=~/anfeca_herramientas/node_modules
```

  Si Chromium no puede instalarse en AlmaLinux, ejecutar `PARTE=A` en el servidor y la batería desde un equipo con
  Chromium mediante un túnel (`ssh -L 8189:127.0.0.1:8189 servidorweb@192.168.7.130`). En ese caso la verificación de
  «sesión perdida» del navegador no aplica porque requiere acceso a los archivos de sesión de la copia.

## Pruebas adicionales pedidas y dónde se cubren

| Prueba | Cobertura |
|---|---|
| Presentación de Mis datos · modificación antes de autorizar · recuperación tras cerrar sesión · autorización · bloqueo | `prueba_codigo_postal_navegador.cjs` (12) |
| Consulta del domicilio desde Coordinación | Ninguna vista de Coordinación ni el acuse PDF presentan el domicilio (revisión del código); `e2e_8088.py` (16, 19) comprueba expediente y bandeja |
| Registro completo de primera vez y de refrendo | `e2e_8088.py` (1–15 y 8) |
| Sesiones e inactividad | `prueba_control_sesion.py` (22) y `…_navegador.cjs` (13) |
| Integración con catálogos ANUIES | `e2e_catalogos_8088.py` (4) |
| Compilación Vite | Bloque 3 y A3 |
| Permisos y SELinux | A4 |
| Integridad de las 36 tablas | A1, bloque 4, `e2e_8088.py` (18, 20) |
| Conservación de expedientes existentes | A6 (conteos y huellas de archivos contra el respaldo) |
| Rendimiento SEPOMEX en el servidor real | A5 (tiempos HTTP y `EXPLAIN ANALYZE`) |

## Ensayo en entorno aislado (no es el servidor)

Los bloques 4 y 7B se ejecutaron tal cual contra una instancia PostgreSQL 18.6 desechable y una copia de la rama:
ver `docs/anfeca/evidencias_8088/fase9/` (`ensayo_04/`: 0 pendientes sobre una base que ya tenía el catálogo;
`ensayo_07B/`: **81/81** — 30 + 4 + 12 + 22 + 13 — con capturas de Mis datos, modificación y autorización). Los bloques 1, 2, 3, 5, 6 y 8 dependen de rutas, servicios y permisos del
servidor; solo se validó su sintaxis.
