#!/bin/bash
# BLOQUE 1 · DIAGNÓSTICO DE SOLO LECTURA. No escribe en Git, archivos de la aplicación ni PostgreSQL.
# Uso: bash 01_diagnostico.sh        (pide la contraseña del rol de la aplicación para las consultas; usar ~/.pgpass si se prefiere)
source "$(dirname "$0")/comun.inc.sh"

paso "Sistema"; hostnamectl 2>/dev/null | grep -E 'Operating|Kernel'; date; df -h / /var "$HOME" | sort -u
paso "Git del código ($REPO)"
git -C "$REPO" branch --show-current; git -C "$REPO" log -1 --oneline; git -C "$REPO" status --short | head -50
git -C "$REPO" fetch --dry-run origin 2>&1 | tail -3
git -C "$REPO" ls-remote origin "$RAMA" refs/heads/integracion/anfeca-36-tablas-definitivo
paso "Aplicación desplegada ($APP)"
ls -ld "$APP"; [ -d "$APP/.git" ] && { git -C "$APP" log -1 --oneline; git -C "$APP" status --short | head; } || echo "(sin .git: copia desplegada)"
paso "Diferencias entre el código desplegado y $REPO (cambios posteriores que deben protegerse)"
diff -rq --exclude=.git --exclude=vendor --exclude=node_modules --exclude=storage --exclude=.env --exclude=build \
    --exclude=bootstrap/cache "$REPO" "$APP" | tee "$SALIDA/diferencias_desplegado_vs_repo.txt" | head -60
echo "Total de diferencias: $(wc -l < "$SALIDA/diferencias_desplegado_vs_repo.txt")"
paso "Configuración (.env sin secretos)"
grep -E '^(APP_ENV|APP_DEBUG|APP_URL|DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME|SESSION_|CACHE_STORE|QUEUE_CONNECTION|ANFECA_)' "$APP/.env"
paso "Servicios y puertos"
systemctl list-units --type=service --all | grep -Ei 'httpd|php-fpm|postgres' ; ss -ltnp | grep -E ':(8088|8086|8008|5432|5433|5488)\b'
grep -rl "8088" /etc/httpd/conf.d/ 2>/dev/null; grep -rhE 'SetHandler|DocumentRoot' /etc/httpd/conf.d/*8088* 2>/dev/null
ps -eo user,args | grep -E '[p]hp-fpm: master' ; "$PHP" -v | head -1
paso "SELinux y permisos"
getenforce; ls -ldZ "$APP" "$APP/storage" "$APP/bootstrap/cache" 2>/dev/null; semanage port -l 2>/dev/null | grep -E '\b(8088|5488)\b'
pedir_clave
paso "PostgreSQL (consultas de solo lectura)"
leer "select version(), current_setting('data_directory'), inet_server_port(), (select system_identifier from pg_control_system())"
paso "Estructura oficial (36 tablas)"; DIR_APP="$APP" artisan anfeca:verificar-esquema || echo "(el comando no existe en la versión desplegada; se repite en el bloque 4)"
leer "select count(*) from information_schema.tables where table_schema='public' and table_type='BASE TABLE'"
paso "Registros geográficos existentes"
leer "select 'entidad_federativa', count(*), min(enfe_id_entidad_federativa), max(enfe_id_entidad_federativa) from entidad_federativa
      union all select 'alcaldia_municipio', count(*), min(almu_id_alcaldia), max(almu_id_alcaldia) from alcaldia_municipio
      union all select 'colonia', count(*), min(colo_id_colonia), max(colo_id_colonia) from colonia"
leer "select enfe_id_entidad_federativa, enfe_nombre from entidad_federativa order by 1" | tee "$SALIDA/entidades.txt"
leer "select e.enfe_nombre, count(*) from domicilio d join entidad_federativa e on e.enfe_id_entidad_federativa = d.domi_id_entidad_federativa group by 1 order by 2 desc"
paso "Datos geográficos que una versión anterior guardó en COMUNICACION (se conservan; ya no se usan)"
leer "select t.tico_tipo_comunicacion, count(c.*) from tipo_comunicacion t left join comunicacion c on c.comu_id_tipo_comunicacion = t.tico_id_tipo_comunicacion
      group by 1 order by 1"
paso "Expedientes existentes (referencia para comprobar que se conservan)"
leer "select 'persona', count(*) from persona union all select 'usuario', count(*) from usuario union all select 'domicilio', count(*) from domicilio
      union all select 'solicitud', count(*) from solicitud union all select 'estado_solicitud', count(*) from estado_solicitud
      union all select 'solicitud_documento', count(*) from solicitud_documento union all select 'persona_estudio_profesional', count(*) from persona_estudio_profesional" \
      | tee "$SALIDA/conteos_expedientes.txt"
find "$APP/storage/app/private" -type f 2>/dev/null | wc -l | sed 's/^/Archivos de expedientes: /'
paso "Archivo SEPOMEX"; ls -l "$SEPOMEX" 2>/dev/null && sha256sum "$SEPOMEX" || echo "No se encontró $SEPOMEX"
echo; echo "Diagnóstico terminado (sin cambios). Revise $SALIDA antes de continuar."
