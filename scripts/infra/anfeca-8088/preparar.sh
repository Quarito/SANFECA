#!/usr/bin/env bash
set -euo pipefail
umask 077
unset PGHOST PGHOSTADDR PGPORT PGDATABASE PGUSER PGPASSWORD PGSERVICE PGOPTIONS LC_ALL
export LC_ALL=C

base=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)
web=/var/www/ANFECA_8088
datos=/var/lib/pgsql/anfeca8088
estado=/etc/anfeca8088
apache=/etc/httpd/conf.d/anfeca8088.conf
pg=/usr/pgsql-18/bin
dir_socket=/run/anfeca8088-pg
usuario=anfeca8088

fatal() { echo "DETENIDO: $*" >&2; exit 1; }

modo=${1:---check}
case "$modo" in
    --check) [[ $# -le 1 ]] || fatal 'Uso: --check';;
    --apply) [[ $# -ge 2 && $# -le 3 ]] || fatal 'Uso: --apply CIDR_CLIENTES [ZONA_FIREWALL]';;
    *) fatal 'Use --check o --apply CIDR_CLIENTES [ZONA_FIREWALL]';;
esac

[[ $EUID == 0 ]] || fatal 'Ejecute con sudo para inspeccionar servicios y archivos.'
source /etc/os-release
[[ "$ID" == almalinux && "${VERSION_ID%%.*}" == 9 ]] || fatal 'Se requiere AlmaLinux 9.'
for cmd in httpd apachectl systemctl systemd-tmpfiles ss ip php node npm curl python3 runuser flock sha256sum getenforce; do
    command -v "$cmd" >/dev/null || fatal "Falta $cmd."
done
for bin in /usr/sbin/php-fpm "$pg"/{postgres,initdb,psql,pg_isready,pg_controldata}; do
    [[ -x "$bin" ]] || fatal "Falta $bin."
done
php -r 'exit(PHP_VERSION === "8.5.12" && extension_loaded("pdo_pgsql") ? 0 : 1);' || fatal 'PHP CLI debe ser 8.5.12 con pdo_pgsql.'
fpm_version=$(/usr/sbin/php-fpm -v)
grep -q '^PHP 8\.5\.12 ' <<< "$fpm_version" || fatal 'PHP-FPM debe ser 8.5.12.'
[[ $("$pg/postgres" --version) =~ PostgreSQL\)\ 18\.6($|[[:space:]]) ]] || fatal 'PostgreSQL debe ser 18.6.'
getent passwd postgres >/dev/null && getent group apache >/dev/null || fatal 'Falta el usuario postgres o el grupo apache.'
ip -o -4 addr | awk '{print $4}' | grep -Eq '^192\.168\.7\.130/' || fatal 'La IP 192.168.7.130 no pertenece a este servidor.'
systemctl is-active --quiet httpd || fatal 'Apache debe estar activo antes de intervenir.'
apachectl configtest
mods=$(apachectl -M)
for m in proxy_module proxy_fcgi_module authz_host_module dir_module; do
    grep -qw "$m" <<< "$mods" || fatal "Falta el módulo Apache $m."
done

selinux=$(getenforce)
systemctl is-active --quiet firewalld && firewall=activo || firewall=inactivo
printf 'OS %s %s\n' "$ID" "$VERSION_ID"
httpd -v
printf '%s\n' "$fpm_version"
"$pg/postgres" --version
node --version
npm --version
printf 'SELinux %s · firewalld %s\n' "$selinux" "$firewall"
ss -ltnp
ss -lxnp | awk '/PGSQL|php.*sock/'
apachectl -S

marcador=$estado/instalacion
if [[ ! -f "$marcador" ]]; then
    for ruta in "$web" "$datos" "$estado" "$apache" /etc/tmpfiles.d/anfeca8088.conf /run/anfeca8088-{pg,fpm} /etc/systemd/system/anfeca8088-{postgresql,php-fpm}.service; do
        [[ ! -e "$ruta" && ! -L "$ruta" ]] || fatal "Ya existe $ruta y no pertenece a esta instalación."
    done
    ! id "$usuario" >/dev/null 2>&1 && ! getent group "$usuario" >/dev/null || fatal "La identidad $usuario ya existe."
fi
if [[ ! -f "$apache" ]] || ! cmp -s "$estado/vhost.expected" "$apache"; then
    [[ -z $(ss -Hlt 'sport = :8088') ]] || fatal 'El puerto 8088 ya está ocupado.'
fi
[[ -z $(ss -Hlt 'sport = :55488') ]] || fatal 'El puerto 55488 ya tiene un listener TCP.'
[[ "$modo" == --apply ]] || exit 0

cidr=$(python3 -c 'import ipaddress,sys; net=ipaddress.IPv4Network(sys.argv[1], strict=True); assert net.subnet_of(ipaddress.IPv4Network("192.168.7.0/24")); print(net)' "$2") ||
    fatal 'Indique una IP/32 o subred autorizada dentro de 192.168.7.0/24.'
zona=${3:-}
if [[ "$firewall" == activo ]]; then
    [[ "$zona" =~ ^[A-Za-z0-9_-]+$ ]] || fatal 'Indique la zona activa de firewalld.'
    zonas=$(firewall-cmd --get-active-zones)
    grep -Fxq "$zona" <<< "$zonas" || fatal 'La zona de firewalld no está activa.'
fi
if [[ "$selinux" != Disabled ]]; then
    command -v semanage >/dev/null && command -v restorecon >/dev/null || fatal 'Faltan semanage o restorecon.'
fi

exec 9>/run/lock/anfeca8088.lock
flock -xn 9 || fatal 'Ya hay otra preparación en curso.'
tmp=$(mktemp -d)
apache_nuevo=no
trap 'rc=$?; if (( rc != 0 )) && [[ "$apache_nuevo" == si ]] && cmp -s "$tmp/vhost" "$apache"; then
    unlink "$apache"
    apachectl configtest && systemctl reload httpd || true
fi
rm -rf -- "$tmp"; exit "$rc"' EXIT

# Se publica por enlace duro sin reemplazo, con permisos ya aplicados.
archivo() {
    local origen=$1 destino=$2 permisos=$3 provisional
    if [[ -e "$destino" || -L "$destino" ]]; then
        [[ ! -L "$destino" ]] && cmp -s "$origen" "$destino" || fatal "Conflicto en $destino."
        return
    fi
    provisional=$(mktemp "${destino}.XXXXXX")
    install -m "$permisos" "$origen" "$provisional"
    ln -T "$provisional" "$destino" || { unlink "$provisional"; fatal "No se pudo crear $destino sin sobrescribir."; }
    unlink "$provisional"
}

huellas_apache() {
    find /etc/httpd -type f ! -path "$apache" -print0 | sort -z | xargs -0 -r sha256sum
}

huella_8086() {
    if [[ -r /var/lib/pgsql/anfeca8086/data/global/pg_control ]]; then
        "$pg/pg_controldata" /var/lib/pgsql/anfeca8086/data | grep -F 'Database system identifier'
        sha256sum /var/lib/pgsql/anfeca8086/data/PG_VERSION
    else
        echo 'Clúster 8086 sin evidencia en la ruta documentada.'
    fi
}
huella_8086 > "$tmp/8086.antes"
huellas_apache > "$tmp/apache.antes"
ss -Hlt 'sport = :8086 or sport = :8008' > "$tmp/puertos.antes"
printf '%s\n%s\n' "$cidr" "$zona" > "$tmp/instalacion"
sed "s|@CIDR@|$cidr|" "$base/vhost.conf.in" > "$tmp/vhost"
if [[ ! -d "$estado" ]]; then mkdir -m 0755 "$estado"; fi
archivo "$tmp/instalacion" "$marcador" 0600
archivo "$tmp/vhost" "$estado/vhost.expected" 0600
if ! id "$usuario" >/dev/null 2>&1; then
    useradd --system --user-group --home-dir "$web" --shell /sbin/nologin "$usuario"
fi

for d in "$web" "$web/public" "$web/bootstrap"; do
    if [[ ! -d "$d" ]]; then mkdir -m 0755 "$d"; fi
    [[ ! -L "$d" && $(stat -c %u "$d") == 0 ]] || fatal "Ruta pública inesperada $d."
done
for d in storage storage/logs storage/tmp storage/framework storage/framework/sessions storage/framework/views storage/framework/cache bootstrap/cache; do
    [[ ! -L "$web/$d" ]] || fatal "Ruta temporal inesperada $web/$d."
    if [[ ! -d "$web/$d" ]]; then install -d -m 0750 -o "$usuario" -g "$usuario" "$web/$d"; fi
done

archivo "$base/index.php" "$web/public/index.php" 0644
archivo "$base/health.php" "$web/public/__infra.php" 0644
archivo "$base/php-fpm.conf" "$estado/php-fpm.conf" 0644
archivo "$base/pg_hba.conf" "$estado/pg_hba.conf" 0644
archivo "$base/tmpfiles.conf" /etc/tmpfiles.d/anfeca8088.conf 0644
for servicio in anfeca8088-postgresql anfeca8088-php-fpm; do
    archivo "$base/$servicio.service" "/etc/systemd/system/$servicio.service" 0644
done

if [[ "$selinux" != Disabled ]]; then
    etiqueta() {
        local tipo=$1 expresion=$2 locales
        locales=$(semanage fcontext -l -C)
        if awk -v x="$expresion" '$1==x {print}' <<< "$locales" | grep -q .; then
            awk -v x="$expresion" '$1==x {print}' <<< "$locales" | grep -q ":$tipo:" || fatal "Etiqueta preexistente incompatible para $expresion."
        else
            semanage fcontext -a -t "$tipo" "$expresion"
        fi
    }
    etiqueta postgresql_db_t "$datos(/.*)?"
    etiqueta postgresql_var_run_t '/run/anfeca8088-pg(/.*)?'
    etiqueta httpd_var_run_t '/run/anfeca8088-fpm(/.*)?'
    etiqueta httpd_sys_rw_content_t "$web/storage(/.*)?"
    etiqueta httpd_sys_rw_content_t "$web/bootstrap/cache(/.*)?"
    tipos=$(semanage port -l)
    tipo=$(awk '$2=="tcp" {for(i=3;i<=NF;i++){gsub(/,/,"",$i); n=split($i,p,"-"); if(p[1]<=8088 && (n==1?p[1]:p[2])>=8088) print $1}}' <<< "$tipos")
    case "$tipo" in
        http_port_t) ;;
        '') semanage port -a -t http_port_t -p tcp 8088 ;;
        *) fatal "8088 ya tiene otra etiqueta SELinux: $tipo.";;
    esac
    restorecon -R "$web"
fi

systemd-tmpfiles --create /etc/tmpfiles.d/anfeca8088.conf
if [[ ! -e "$datos/PG_VERSION" ]]; then
    [[ ! -e "$datos" ]] || fatal "El directorio $datos ya existe sin un clúster verificable."
    install -d -m 0700 -o postgres -g postgres "$datos"
    runuser -u postgres -- "$pg/initdb" -D "$datos" --auth-local=peer --auth-host=scram-sha-256 --encoding=UTF8
    runuser -u postgres -- "$pg/pg_controldata" "$datos" | awk -F ': *' '/Database system identifier/ {print $2}' > "$tmp/cluster"
    [[ -s "$tmp/cluster" ]] || fatal 'No se pudo identificar el clúster.'
    archivo "$tmp/cluster" "$estado/cluster" 0600
fi
[[ ! -L "$datos" && -s "$estado/cluster" ]] || fatal 'Clúster sin identidad registrada.'
if [[ "$selinux" != Disabled ]]; then restorecon -R "$datos" /run/anfeca8088-{pg,fpm} /etc/anfeca8088; fi
systemctl daemon-reload
systemctl start anfeca8088-postgresql
admin() { runuser -u postgres -- "$pg/psql" -XqAt -w -v ON_ERROR_STOP=1 -h "$dir_socket" -p 55488 "$@"; }
for _ in {1..20}; do "$pg/pg_isready" -q -h "$dir_socket" -p 55488 && break; sleep 1; done
identidad=$(admin -d postgres -c "select current_setting('data_directory'), current_setting('server_version_num'), system_identifier from pg_control_system()")
[[ "$identidad" == "$datos|180006|$(cat "$estado/cluster")" ]] || fatal 'Identidad PostgreSQL inesperada.'
admin -d postgres <<'SQL'
SELECT 'CREATE ROLE anfeca8088 LOGIN' WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname='anfeca8088') \gexec
SELECT 'CREATE DATABASE certificacion_anfeca' WHERE NOT EXISTS (SELECT 1 FROM pg_database WHERE datname='certificacion_anfeca') \gexec
REVOKE CONNECT ON DATABASE certificacion_anfeca FROM PUBLIC;
GRANT CONNECT ON DATABASE certificacion_anfeca TO anfeca8088;
SQL
runuser -u "$usuario" -- "$pg/psql" -XqAt -w -h "$dir_socket" -p 55488 -d certificacion_anfeca     -c "select current_database(), current_user, count(*) from information_schema.tables where table_schema='public';"

/usr/sbin/php-fpm -t -y "$estado/php-fpm.conf"
systemctl start anfeca8088-php-fpm
[[ -e "$apache" ]] || apache_nuevo=si
archivo "$tmp/vhost" "$apache" 0644
apachectl configtest
systemctl reload httpd
respuesta=$(curl --noproxy '*' --fail --silent --show-error --max-time 10 --retry 4 http://192.168.7.130:8088/__infra.php)
python3 -c 'import json,sys; r=json.load(sys.stdin); assert r["ok"] and r["php"]=="8.5.12" and r["database"]["pg"]=="180006"; print(json.dumps(r))' <<< "$respuesta"
systemctl enable anfeca8088-postgresql anfeca8088-php-fpm
if [[ "$firewall" == activo ]]; then
    regla="rule family=\"ipv4\" source address=\"$cidr\" destination address=\"192.168.7.130\" port port=\"8088\" protocol=\"tcp\" accept"
    for alcance in permanente activo; do
        opciones=(--zone="$zona")
        if [[ "$alcance" == permanente ]]; then opciones+=(--permanent); fi
        if ! firewall-cmd "${opciones[@]}" --query-rich-rule="$regla"; then
            firewall-cmd "${opciones[@]}" --add-rich-rule="$regla"
        fi
    done
fi
huellas_apache > "$tmp/apache.despues"
cmp "$tmp/apache.antes" "$tmp/apache.despues"
huella_8086 > "$tmp/8086.despues"
cmp "$tmp/8086.antes" "$tmp/8086.despues"
cat "$tmp/8086.despues"
ss -Hlt 'sport = :8086 or sport = :8008' > "$tmp/puertos.despues"
# Compare endpoints, omitting changing socket queues.
diff -u <(awk '{print $1,$4,$5}' "$tmp/puertos.antes" | sort) <(awk '{print $1,$4,$5}' "$tmp/puertos.despues" | sort)
printf '8088 respondió a la sonda PHP-FPM y PostgreSQL. Identidad: %s\n' "$identidad"
