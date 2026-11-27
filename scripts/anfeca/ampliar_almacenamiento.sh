#!/usr/bin/env bash
# =============================================================================
# ANFECA · Ampliación CONTROLADA del almacenamiento de AlmaLinux (disco VMware de 20 → 50 GiB).
#
#   sudo bash scripts/anfeca/ampliar_almacenamiento.sh diagnosticar
#   sudo bash scripts/anfeca/ampliar_almacenamiento.sh simular
#   sudo ANFECA_SHA_COPIA_EXTERNA=<sha256 calculado en el equipo externo> \
#        ANFECA_RESPALDO_VM=confirmado bash scripts/anfeca/ampliar_almacenamiento.sh aplicar
#
# Objetivo: nvme0n1p3 hasta el final del disco (≈ 48 GiB), PV ampliado, almalinux-root de 16 → 44 GiB (XFS en
# línea), swap SIN cambios (2 GiB) y ≈ 2 GiB libres en el VG como reserva.
#
# Seguridad:
#  - diagnosticar y simular NO escriben nada (growpart --dry-run, pvresize/lvextend --test).
#  - aplicar exige: (1) la huella SHA-256 de la copia externa calculada FUERA de esta VM e idéntica a la local;
#    (2) la confirmación explícita del respaldo completo de la VM en el anfitrión (no visible desde el invitado);
#    (3) disposición idéntica a la diagnosticada; (4) ISO desmontada. Se detiene en el primer paso que no verifica.
#  - XFS NO se puede reducir: la reversión de la ampliación es restaurar la VM o el respaldo.
#  - Respaldo de la tabla de particiones (sfdisk -d) y de la metadata LVM (vgcfgbackup) antes de escribir.
# =============================================================================
set -euo pipefail

MODO="${1:?Modo: diagnosticar | simular | aplicar}"
DISCO=/dev/nvme0n1
PART=${DISCO}p3
VG=almalinux
LV_ROOT=/dev/$VG/root
LV_SWAP=/dev/$VG/swap
ROOT_OBJETIVO_GIB=44
SWAP_GIB=2
RESERVA_MINIMA_MIB=1536
COPIA=/home/servidorweb/ANFECA_COPIA_EXTERNA_20261126/anfeca_copia_externa_20261126.tar
REGISTRO_DIR=/root/anfeca_ampliacion_almacenamiento
detener() { echo "DETENIDO: $*" >&2; exit 1; }
paso() { echo; echo "== $*"; }

[ "$(id -u)" = 0 ] || detener "ejecute con sudo"
command -v growpart >/dev/null || detener "falta growpart: sudo dnf install -y cloud-utils-growpart (no actualiza ningún otro paquete)"

mib() { echo $(( $(blockdev --getsize64 "$1") / 1048576 )); }

diagnostico() {
    paso "Disposición actual"
    lsblk -o NAME,SIZE,TYPE,FSTYPE,MOUNTPOINT "$DISCO"
    parted -s "$DISCO" unit s print free 2>&1 || true
    pvs --units m "$PART"; vgs --units m "$VG"; lvs --units m "$VG"
    swapon --show
    df -h /
    xfs_info / | grep -E 'bsize|reflink' | head -2

    paso "Comprobaciones"
    [ "$(mib "$DISCO")" -ge 51000 ] || detener "el disco mide $(mib "$DISCO") MiB; el sistema aún no detecta los 50 GiB (sudo nvme ns-rescan /dev/nvme0)"
    local ultima
    ultima=$(lsblk -nro NAME "$DISCO" | grep -E '^nvme0n1p[0-9]+$' | sort -V | tail -1)
    [ "$ultima" = nvme0n1p3 ] || detener "nvme0n1p3 no es la última partición ($ultima)"
    [ "$(lsblk -nro FSTYPE "$PART")" = LVM2_member ] || detener "$PART no es un PV de LVM"
    [ "$(findmnt -nro SOURCE /)" = /dev/mapper/$VG-root ] || detener "/ no está en $VG-root"
    [ "$(findmnt -nro FSTYPE /)" = xfs ] || detener "/ no es XFS"
    [ "$(pvs --noheadings -o pv_name | wc -l)" = 1 ] || detener "hay más de un PV: revise manualmente"
    [ "$(lvs --noheadings -o lv_name "$VG" | wc -l)" = 2 ] || detener "el VG no tiene exactamente root y swap"
    if findmnt -n -S /dev/sr0 >/dev/null 2>&1; then
        echo "AVISO: la ISO de AlmaLinux sigue montada en sr0. Antes de aplicar: sudo umount /dev/sr0 (o desconéctela en VMware)."
        ISO_MONTADA=1
    else
        ISO_MONTADA=0
    fi
    lvs --noheadings -o lv_attr "$VG" | grep -q '^ *s' && detener "hay instantáneas LVM"
    echo "Disco: $(mib "$DISCO") MiB · p3: $(mib "$PART") MiB · root: $(mib "$LV_ROOT") MiB · swap: $(mib "$LV_SWAP") MiB"
    echo "Diagnóstico correcto."
}

case "$MODO" in
diagnosticar)
    diagnostico
    ;;
simular)
    diagnostico
    paso "Simulación de la partición (growpart --dry-run; no escribe)"
    growpart --dry-run "$DISCO" 3
    paso "Simulación LVM (--test; no escribe)"
    pvresize --test "$PART" 2>&1 | grep -v 'TEST MODE' || true
    echo "Nota: con el PV aún de 18 GiB, lvextend --test a ${ROOT_OBJETIVO_GIB} GiB DEBE fallar por falta de espacio; tras pvresize se repite en «aplicar»."
    lvextend --test -L "${ROOT_OBJETIVO_GIB}G" "$LV_ROOT" 2>&1 | grep -v 'TEST MODE' || true
    paso "Cálculo esperado"
    TOTAL=$(mib "$DISCO")
    echo "p3 ≈ $(( TOTAL - 2050 - 1 )) MiB · root ${ROOT_OBJETIVO_GIB} GiB · swap ${SWAP_GIB} GiB · reserva ≈ $(( TOTAL - 2050 - 2 - ROOT_OBJETIVO_GIB*1024 - SWAP_GIB*1024 - 4 )) MiB"
    echo "Simulación concluida sin escrituras."
    ;;
aplicar)
    diagnostico
    [ "$ISO_MONTADA" = 0 ] || detener "desmonte la ISO de sr0 antes de aplicar"
    [ "${ANFECA_RESPALDO_VM:-}" = confirmado ] || detener "confirme el respaldo completo de la VM en el anfitrión: ANFECA_RESPALDO_VM=confirmado"
    [ -f "$COPIA" ] || detener "no existe la copia $COPIA"
    LOCAL=$(sha256sum "$COPIA" | cut -d' ' -f1)
    [ -n "${ANFECA_SHA_COPIA_EXTERNA:-}" ] || detener "indique ANFECA_SHA_COPIA_EXTERNA (SHA-256 calculado en el equipo externo tras copiar el archivo)"
    [ "${ANFECA_SHA_COPIA_EXTERNA,,}" = "$LOCAL" ] || detener "la huella de la copia externa no coincide con la local ($LOCAL)"
    [ "$(mib "$LV_ROOT")" -lt $(( ROOT_OBJETIVO_GIB * 1024 )) ] || detener "root ya mide $(mib "$LV_ROOT") MiB"

    umask 077; mkdir -p "$REGISTRO_DIR"; R="$REGISTRO_DIR/$(date +%Y%m%d_%H%M%S)"; mkdir -p "$R"
    exec > >(tee -a "$R/registro.txt") 2>&1
    paso "Respaldo de metadatos en $R"
    sfdisk -d "$DISCO" > "$R/particiones_antes.sfdisk"
    vgcfgbackup -f "$R/lvm_antes.vg" "$VG"
    cp -a "$R/particiones_antes.sfdisk" "$R/lvm_antes.vg" /home/servidorweb/ANFECA_COPIA_EXTERNA_20261126/ 2>/dev/null || true
    lsblk -b -o NAME,SIZE,TYPE,FSTYPE,MOUNTPOINT "$DISCO" > "$R/lsblk_antes.txt"

    paso "1/4 Partición 3 hasta el final del disco"
    growpart "$DISCO" 3
    partx -u "$DISCO" 2>/dev/null || true
    udevadm settle
    [ "$(mib "$PART")" -ge 48000 ] || detener "p3 mide $(mib "$PART") MiB tras growpart (se esperaba ≈ 49 000). Revise $R antes de continuar."

    paso "2/4 PV"
    pvresize "$PART"
    pvs --units m "$PART"

    paso "3/4 LV root a ${ROOT_OBJETIVO_GIB} GiB (swap sin cambios)"
    lvextend --test -L "${ROOT_OBJETIVO_GIB}G" "$LV_ROOT"
    lvextend -L "${ROOT_OBJETIVO_GIB}G" "$LV_ROOT"

    paso "4/4 XFS en línea"
    xfs_growfs /

    paso "Verificación"
    lsblk -o NAME,SIZE,TYPE,FSTYPE,MOUNTPOINT "$DISCO"
    vgs --units m "$VG"; swapon --show; df -h /
    LIBRE_VG=$(vgs --noheadings --units m --nosuffix -o vg_free "$VG" | tr -d ' ' | cut -d. -f1)
    [ "$(mib "$LV_SWAP")" = $(( SWAP_GIB * 1024 )) ] || detener "la swap cambió de tamaño"
    [ "$LIBRE_VG" -ge "$RESERVA_MINIMA_MIB" ] || detener "reserva LVM de solo ${LIBRE_VG} MiB"
    [ "$(df -Pm / | awk 'NR==2 {print $2}')" -ge 44000 ] || detener "/ no alcanzó ≈ 44 GiB"
    xfs_info / > "$R/xfs_despues.txt"
    lsblk -b -o NAME,SIZE,TYPE,FSTYPE,MOUNTPOINT "$DISCO" > "$R/lsblk_despues.txt"
    echo "Ampliación concluida: / ≈ ${ROOT_OBJETIVO_GIB} GiB · swap ${SWAP_GIB} GiB · reserva VG ${LIBRE_VG} MiB · registro $R"
    ;;
*)
    detener "modo desconocido: $MODO"
    ;;
esac
