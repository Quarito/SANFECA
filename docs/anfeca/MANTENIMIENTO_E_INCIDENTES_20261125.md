# Mantenimiento de almacenamiento, incidentes y credenciales (2026-11-25)

Servidor: VMware Virtual Platform, AlmaLinux 10.2. Sin sudo sin contraseña para la cuenta `servidorweb`.

## 1. Almacenamiento

### 1.1 Antes y después (`df -h /`)

| Momento | Tamaño | Usado | Libre | Uso |
|---|---|---|---|---|
| Antes del mantenimiento | 16G | 15G | **1.4G** | 92 % |
| Después del mantenimiento | 16G | 12G | **4.0G** | 75 % |
| Al cierre de la intervención | 16G | 12G | 4.0G | 76 % |

### 1.2 Elementos eliminados

| Elemento | Verificación previa | Recuperado |
|---|---|---|
| VS Code Server `Stable-261c84cb…` (03-nov) | Sin procesos (`ps`) | 639 MB |
| VS Code Server `Stable-69047191…` (11-nov) | Sin procesos | 673 MB |
| VS Code Server `Stable-8a14f0cc…` (18-nov) | Sin procesos | 685 MB |
| Claude Code 2.1.312 | No es la versión activa (enlace → 2.1.330) y sin procesos | 280 MB |
| Caché npm (`npm cache clean --force`) | Caché regenerable | 394 MB |
| Caché Composer (`composer clear-cache`) | Caché regenerable | 63 MB |
| **Total** | | **2738 MB** (≈ 2.7 GB) |

Se conservaron:
- VS Code Server `Stable-2c33b50b…`: **en uso**, con 22 procesos activos. Aunque es la versión más antigua por fecha, es la que usa el cliente conectado.
- VS Code Server `Stable-661626d0…`: la más reciente.
- Claude Code 2.1.330 (activa) y 2.1.329 (con un proceso en ejecución).

No se tocaron respaldos, bases de datos, imágenes de Podman, `vendor` ni `node_modules`, evidencias, proyectos anteriores, worktrees del PDM, configuraciones ni documentos de solicitantes.

El registro de la operación está en `~/ANFECA_RESPALDOS/mantenimiento_almacenamiento_20261125.log`.

### 1.3 Pendiente: requiere sudo

**No se alcanzaron los 5 GB libres** del criterio preventivo. Las cachés restantes requieren privilegios de administrador:

```sh
sudo du -sh /var/cache/PackageKit /var/cache/dnf        # 1.2 GB y 74 MB
sudo dnf clean all                                       # caché DNF
sudo systemctl stop packagekit && sudo rm -rf /var/cache/PackageKit/* && sudo systemctl start packagekit
df -h /                                                  # esperado: ≈ 5.3 GB libres
```

Consecuencia aplicada: siguiendo el criterio autorizado, **no se modificó PostgreSQL** en esta intervención; eso incluye la rotación de credenciales (§4). Las pruebas pesadas se ejecutaron en PostgreSQL desechable **en memoria** (tmpfs), sin consumir disco.

## 2. Procedimiento de ampliación del disco VMware: de 20 GB a 40 GB (NO ejecutado)

### 2.1 Identificación (solo lectura)

| Elemento | Valor |
|---|---|
| Disco virtual | `VMware Virtual NVMe Disk`, dispositivo `/dev/nvme0n1`, 20 GiB (41 943 040 sectores de 512 B), tabla **GPT** |
| Particiones | `nvme0n1p1` 1 MiB (BIOS boot) · `nvme0n1p2` 2 GiB XFS `/boot` · `nvme0n1p3` 18 GiB **LVM2_member** |
| Posición de p3 | Sectores 4 198 400–41 940 991: es la **última** partición, así que puede crecer de forma contigua |
| Physical Volume | `/dev/nvme0n1p3` |
| Volume Group | `almalinux` |
| Logical Volumes | `almalinux-root` 16 GiB (XFS, `/`) · `almalinux-swap` 2 GiB |
| Sistema de archivos | XFS en `/dev/mapper/almalinux-root`. Crece en línea con `xfs_growfs`; **no se puede reducir** |
| Herramientas | `parted` 3.6 y `pvresize`, `lvextend`, `xfs_growfs` disponibles. `growpart` **no instalado** (`cloud-utils-growpart`) |
| VMware Tools | `vmtoolsd` 13.0.10 activo |
| Por verificar **en el anfitrión** | Tipo de aprovisionamiento (thin o thick), instantáneas existentes, espacio libre del datastore y versión de vSphere/Workstation. No es visible desde el sistema invitado |

### 2.2 Procedimiento propuesto (requiere autorización específica)

0. **Preparación:**
   - ventana de mantenimiento;
   - **respaldo completo de la VM** (clon o copia de seguridad, no solo una instantánea);
   - consolidar o eliminar las instantáneas, porque VMware no amplía discos con instantáneas activas;
   - datastore con al menos 20 GB libres, más margen;
   - preferentemente con la VM **apagada**, ya que la ampliación en caliente de NVMe depende de la versión del anfitrión.
1. **Anfitrión:** ampliar «Hard disk 1» de 20 GB a 40 GB.
2. **Invitado:**
   - `lsblk`; si no se ve el tamaño nuevo: `echo 1 | sudo tee /sys/class/nvme/nvme0/rescan_controller` o reiniciar;
   - `sudo parted /dev/nvme0n1 print` (aceptar «Fix» para mover la cabecera GPT de respaldo al final del disco).
3. **Partición:**
   - `sudo parted /dev/nvme0n1 resizepart 3 100%`;
   - alternativa: `sudo dnf install cloud-utils-growpart && sudo growpart /dev/nvme0n1 3`.
4. **PV:**
   - `sudo pvresize /dev/nvme0n1p3`;
   - `sudo vgs almalinux` debe mostrar ≈ 20 GiB libres.
5. **LV y sistema de archivos:** `sudo lvextend -r -l +100%FREE /dev/almalinux/root` (`-r` ejecuta `xfs_growfs`).
6. **Verificación:**
   - `df -h /` ≈ 36 GiB;
   - `sudo xfs_info /`;
   - reinicio de prueba;
   - comprobar los sitios 8084, 8085 y 8086.

**Reversión:** XFS no se reduce. Ante una falla se restaura el respaldo de la VM del paso 0. **Riesgo:** bajo si los pasos 0 a 2 se cumplen; el principal es ampliar con instantáneas activas o sin respaldo.

## 3. Incidente: escritura accidental en `/var/www/ANFECA_ENSAYO`

**Qué ocurrió (2026-11-25 12:32 CST):**
- Una ejecución de pruebas en una copia usó un **enlace simbólico a `vendor`**.
- Composer resolvió la raíz real (`/var/www/ANFECA_ENSAYO`) y Laravel tomó ese sitio como `base_path`.

**Efectos:**
- 5 PDF ficticios del disco falso de pruebas;
- 1 vista compilada;
- 2 entradas en `laravel.log`.

La **base de datos no se afectó**: `phpunit.xml` fuerza SQLite en memoria y la prueba se niega a ejecutarse con otra base.

**Archivos eliminados** (uno por uno con `rm -f`, sin recursividad; directorios vacíos conservados):

| Ruta (`storage/framework/testing/disks/local/anfeca/documentos/solicitudes/`) | Tamaño | Contenido | SHA-256 |
|---|---|---|---|
| `1/titulo.pdf` | 21 B | `%PDF-1.4 ficticio ZF3` | `3084a002…` |
| `2/titulo.pdf` | 21 B | `%PDF-1.4 ficticio ZF4` | `82100473…` |
| `3/titulo.pdf` | 21 B | `%PDF-1.4 ficticio ZF6` | `20e5571e…` |
| `4/titulo.pdf` | 21 B | `%PDF-1.4 ficticio SIN` | `4b48df23…` |
| `6/titulo.pdf` | 22 B | `%PDF-1.4 ficticio ZF3B` | `43da317d…` |

Por qué eran ficticios y no documentos de solicitantes:
- su contenido coincide con el fixture de `CoordinacionTest`, que escribe `"%PDF-1.4 ficticio {$clave}"`;
- están en el disco **falso** de pruebas (`storage/framework/testing`);
- los documentos reales están en `storage/app/private`, propiedad de apache y sin acceso para esta cuenta.

El registro (sin contenido sensible) está en `~/ANFECA_RESPALDOS/incidente_storage_20261125.log`.

**Vista compilada** `storage/framework/views/d32151bf….php`: es la caché de `coordinacion/prototipos/revision-documental.blade.php`, compilada desde la **misma fuente** que sirve 8086. Es legítima y equivalente a la que Laravel genera por sí mismo, así que **no requiere limpieza** y se conservó.

**Log:** las 2 entradas (18:32 UTC, `touch(): Utime failed`) **se conservan como evidencia**.

**Medidas correctivas:**
1. `tests/TestCase.php` se niega a ejecutar pruebas en estos casos:
   - si la raíz de Laravel no coincide con el directorio desde el que se ejecutan;
   - si la raíz está bajo `/var/www/`, salvo `ANFECA_PRUEBAS_EN_SITIO=autorizado`.
2. `scripts/anfeca/pruebas/arbol_aislado.sh` crea el árbol de pruebas con `git archive` y **copias** de `vendor` y `node_modules`. Antes de ejecutar verifica que no hay enlaces simbólicos, que la raíz de Laravel es la correcta y que `phpunit.xml` fuerza SQLite en memoria.
3. `RevisionDocumentalTest::test_aislamiento…` comprueba que el disco falso queda dentro del árbol aislado.
4. `scripts/anfeca/desplegar_f45.sh` (paso 7, pruebas dentro del sitio desplegado) declara ahora `ANFECA_PRUEBAS_EN_SITIO=autorizado`. **Pendiente de decisión:** migrar ese paso al árbol aislado.

**Prevención adicional detectada en esta intervención:** los scripts nunca montan `/var/www/...` en contenedores con `:z` o `:Z`, porque eso **re-etiquetaría SELinux** del sitio. La etiqueta del sitio se verificó intacta (`httpd_sys_content_t`).

## 4. Rotación de la contraseña de `coordinador.nacional@anfeca.test`

**Estado: PREPARADA Y PROBADA; NO EJECUTADA en 8086.** El criterio de 5 GB libres no se alcanzó y la instrucción indica detenerse antes de modificar PostgreSQL. Se ejecuta con una sola orden en cuanto se autorice o se libere el espacio:

```sh
cd /var/www/ANFECA_ENSAYO
ANFECA_AUTORIZO_ROTACION=coordinador.nacional@anfeca.test \
  php scripts/anfeca/rotar_clave_coordinador.php coordinador.nacional@anfeca.test \
  /home/servidorweb/ANFECA_CREDENCIALES/coordinacion_ensayo_8086.tsv
```

Qué hace `scripts/anfeca/rotar_clave_coordinador.php`:
- Exige la autorización explícita, que el archivo tenga permisos 600 y que la cuenta sea de coordinación.
- Genera **localmente** una contraseña de 24 caracteres (`random_int`, todas las clases) y la guarda con `Hash::make()`, verificando después el hash.
- Renueva `remember_token` y verifica que **ningún otro usuario** cambió.
- Invalida las sesiones activas de esa cuenta según la configuración vigente (`SESSION_DRIVER=file`, sesiones en JSON). Borra **solo** los archivos de sesión cuyo usuario autenticado es esa cuenta.
- Actualiza el TSV de forma atómica, con una copia previa, ambos con permisos 600. **Nunca imprime la contraseña.**
- Registra la operación, sin secretos, en `~/ANFECA_RESPALDOS/rotacion_credenciales.log`.

**Prueba en la demostración desechable:**
- la sesión abierta antes de la rotación pasa a `/login`;
- la otra cuenta mantiene su sesión;
- la contraseña anterior se rechaza y la nueva (24 caracteres) funciona;
- la salida no contiene la contraseña;
- el TSV y su copia quedan con permisos 600.

**Consulta personal** de la nueva contraseña, en una terminal privada:

```sh
awk -F'\t' '$1=="coordinador.nacional@anfeca.test"{print $3}' ~/ANFECA_CREDENCIALES/coordinacion_ensayo_8086.tsv
```
