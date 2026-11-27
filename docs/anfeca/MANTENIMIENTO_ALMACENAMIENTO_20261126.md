# Mantenimiento preventivo de almacenamiento · 2026-11-26

Continúa `MANTENIMIENTO_E_INCIDENTES_20261125.md`. Disco raíz de 16 GiB (LV `almalinux-root`, XFS). Umbral preventivo: **5 GB libres**.

## 1. Causa de la nueva reducción

Después de la limpieza del 25-nov (15:59) quedaron entre 5.1 y 5.2 GB libres. El 26-nov por la mañana había 3.86 GB libres.

| Origen | Escrito después del 25-nov 15:59 | Diagnóstico |
|---|---:|---|
| `/var/cache/PackageKit` | **1164 MB** | Directorio recreado el 25-nov a las 16:59. **GNOME Software** (`gnome-software --gapplication-service`, en ejecución desde el 20-nov) descargó 475 RPM y metadatos para actualizaciones automáticas; la última escritura fue el 26-nov a las 07:25. **Causa principal** |
| `/var/cache/dnf` | 67 MB | Metadatos de `dnf-makecache.timer` |
| `~/.claude` | 42 MB | Transcripciones de sesiones |
| Temporales de pruebas | 33 MB netos | Los árboles aislados (`cp -a` en XFS con `reflink=1`) comparten bloques con el original: eliminar uno de 274 MB liberó 21 MB |

**Recursos que existían antes de la limpieza anterior** (no explican la reducción):
- VS Code Server `Stable-661626…` (692 MB, 25-nov 09:00);
- navegadores de Playwright (314 MB, 25-nov 12:13).

## 2. Limpieza realizada

| Elemento eliminado | Ruta | Tamaño previo | Justificación | Espacio recuperado |
|---|---|---:|---|---:|
| Árbol aislado de pruebas concluidas | temporal de la sesión, `arbol_cat_final` | 274 MB | Commit `9faa625` ya publicado; sin procesos | 21 MB (reflink) |
| Copia intermedia del catálogo derivado | temporal de la sesión, `catalogo_prueba` | 0.9 MB | Superada por `~/ANFECA_FUENTES`; tenía permisos 664 | 1 MB |
| CLI antigua de VS Code y su registro | `~/.vscode-server/code-16507…` | 33 MB | Del 14-oct, sin procesos; la CLI activa es `code-2c33…` | 33 MB |
| Caché npm | `~/.npm/_cacache` | 46 MB | Regenerable (`npm cache clean --force`) | 46 MB |
| Caché de Firefox | `~/.cache/mozilla/firefox` | 74 MB | Regenerable; Firefox no estaba en ejecución; el perfil no se tocó | 72 MB |
| **Caché de PackageKit** (ejecutado por el responsable, con sudo) | `/var/cache/PackageKit` | 1164 MB | Paquetes descargados automáticamente | ≈ 1.16 GB |

Resultado: 3.86 GB → **5.2 GB libres** (68 % de ocupación).

Registro detallado: `~/ANFECA_EVIDENCIAS/mantenimiento_almacenamiento_20261126/registro_limpieza.tsv`.

**Conservados:**
- VS Code `2c33…` (en ejecución) y `661626…` (versión usada más recientemente);
- Claude 2.1.329 (en ejecución) y 2.1.330 (predeterminada);
- las 3 imágenes de Podman: `php:8.5.12-cli` es la base de `anfeca-php` y eliminarla liberaría solo 24 kB;
- respaldos, evidencias, fuentes, credenciales y proyectos.

## 3. Estado de PackageKit y fuentes que pueden regenerar la caché

| Elemento | Estado (26-nov, 12:30) |
|---|---|
| `packagekit.service` | `inactive (dead)`, `static`. No arranca solo: lo activa D-Bus cuando un cliente (GNOME Software, `pkcon`) lo solicita |
| `/var/cache/PackageKit` | 0 bytes |
| `org.gnome.software download-updates` (usuario servidorweb) | `false`. GSettings notifica el cambio al proceso en ejecución; no requiere reinicio |
| `gnome-software --gapplication-service` | En ejecución (sesión gráfica `tty2` de servidorweb). Se inicia en cada sesión GNOME por `/etc/xdg/autostart/org.gnome.Software.desktop` |
| Otra cuenta interactiva | **`serverdev`** (`/bin/bash`). Si inicia una sesión gráfica, su configuración propia mantiene `download-updates=true` por omisión y **volvería a llenar la caché** |
| `dnf-makecache.timer` | Habilitado. Mantiene `/var/cache/dnf` (≈ 68 MB de metadatos, sin paquetes). Aceptable |
| `dnf-automatic.timer` | No instalado |

**Medida recomendada (requiere sudo; no ejecutada).** Fija la preferencia para **todos** los usuarios y la bloquea:

```sh
sudo mkdir -p /etc/dconf/db/local.d/locks
printf '[org/gnome/software]\ndownload-updates=false\n' | sudo tee /etc/dconf/db/local.d/00-anfeca-gnome-software
printf '/org/gnome/software/download-updates\n' | sudo tee /etc/dconf/db/local.d/locks/00-anfeca-gnome-software
sudo dconf update
# Verificación (como cualquier usuario):  gsettings get org.gnome.software download-updates   → false
```

**Vigilancia periódica:**

```sh
df -h /
sudo du -sh /var/cache/PackageKit /var/cache/dnf
```

## 4. Diagnóstico para la ampliación futura del disco VMware (20 → 40 GB) — NO ejecutar

Las particiones y los volúmenes **no se modificaron**. La disposición es **idéntica** a la de `MANTENIMIENTO_E_INCIDENTES_20261125.md` §2:
- `nvme0n1` de 20 GiB, con GPT: p1 de 1 MiB, p2 `/boot` de 2 GiB y p3 LVM de 18 GiB como **última** partición;
- VG `almalinux`: `root` de 16 GiB (XFS) y `swap` de 2 GiB.

El procedimiento de esa sección sigue vigente. Datos nuevos:

| Observación | Implicación |
|---|---|
| El disco es **NVMe virtual** (no SCSI) | Para detectar el tamaño nuevo en caliente: `sudo nvme ns-rescan /dev/nvme0` (`nvme-cli` 2.16 instalado) o `echo 1 \| sudo tee /sys/class/nvme/nvme0/rescan_controller`; si no basta, reiniciar |
| `parted` 3.6, `pvresize` y `xfs_growfs` disponibles; `growpart` no instalado | Usar `parted resizepart 3 100%` o instalar `cloud-utils-growpart` en la ventana |
| XFS con `reflink=1` | Crece en línea; **no se puede reducir** (la reversión es restaurar la VM) |
| Swap de 2 GiB con 1.5 GiB en uso; RAM de 3.6 GiB | Presión de memoria. Al ampliar, considerar `lvextend -L +2G /dev/almalinux/swap` y `swapoff`/`mkswap`/`swapon` en la ventana, o ampliar la RAM de la VM |
| ISO de AlmaLinux 10.2 montada en `sr0` | Desmontarla o desconectarla en el anfitrión antes de la ventana |
| `open-vm-tools` 13.0.10 activo | Correcto para la coordinación con el anfitrión |

**Distribución sugerida de los +20 GiB:** +2 GiB de swap y el resto para `root` (≈ 34 GiB).

**Requisitos en el anfitrión** (no visibles desde el invitado):
- sin instantáneas activas;
- datastore con ≥ 20 GB libres;
- respaldo completo de la VM.

## 5. Control de espacio durante las actividades del 26-nov

| Momento | Libre en / |
|---|---:|
| Después del mantenimiento con sudo | 5236 MB |
| Demostración del catálogo iniciada (reflink de `vendor` y `node_modules`; compilación propia) | 5215 MB |
| Prueba integral de la migración en PostgreSQL desechable (inicio → durante → final) | 5217 → 5204 → 5216 MB |

## 6. Medidas para no repetir el problema

1. Bloquear la descarga automática de GNOME Software para todos los usuarios (§3).
2. Después de actualizar el cliente VS Code y cerrar la ventana antigua, eliminar la versión de servidor sin procesos (≈ 650–700 MB cada una).
3. Conservar solo dos versiones de Claude: la que está en ejecución y la predeterminada.
4. Al concluir cada verificación visual, eliminar los navegadores de Playwright y los árboles temporales.
5. Antes de pruebas pesadas, comprobar que hay ≥ 5 GB libres. Los scripts de demostración y de migración ya lo verifican y se detienen si no se cumple.

## 7. Ampliación ejecutada (OPS-02 cerrado) · 2026-11-26

Ejecutada por el responsable con sudo, siguiendo §4. Verificación posterior (solo lectura):

| Elemento | Resultado |
|---|---|
| Disco VMware (`nvme0n1`) | 50 GiB |
| Partición `nvme0n1p3` (LVM) | 48 GiB |
| `root` (XFS, ampliado en línea) | 44 GiB · 32 GiB libres · 28 % de ocupación |
| `swap` | 2 GiB (sin cambio) |
| Reserva libre en el VG `almalinux` | ≈ 2 GiB (disponible para swap o `root` en una ventana futura) |
| Apache, PHP-FPM y PostgreSQL 18 | Activos |

El umbral preventivo de 5 GB se conserva en los scripts. No se repite el mantenimiento. Con este margen se desplegó el catálogo de licenciaturas en 8086 (`catalogos/DESPLIEGUE_LICENCIATURAS_POSGRADO_8086.md`): libre antes 32 722 MB, después ≈ 32 700 MB.
