#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

if [[ $# -lt 1 || $# -gt 3 ]]; then
    echo "Uso: $0 staging|production [MYSQL_ENV] [DIRECTORIO_RESPALDOS]" >&2
    exit 64
fi

environment_name=$1
script_directory=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
environment_file=${2:-"$script_directory/../config/mysql.env"}
backup_directory=${3:-"$script_directory/../backups"}
container_name=luzparral-mysql

case "$environment_name" in
    staging) database_key=MYSQL_STAGING_DATABASE ;;
    production) database_key=MYSQL_PRODUCTION_DATABASE ;;
    *) echo "Entorno invalido: $environment_name" >&2; exit 64 ;;
esac

for command_name in podman sha256sum grep stat date mktemp; do
    command -v "$command_name" >/dev/null 2>&1 || { echo "ERROR: $command_name no esta disponible." >&2; exit 69; }
done

if [[ ! -f "$environment_file" || $(stat -c '%a' "$environment_file") != 600 ]]; then
    echo "ERROR: el archivo MySQL debe existir y tener modo 600: $environment_file" >&2
    exit 77
fi

database=$(grep -E "^${database_key}=" "$environment_file" | tail -n 1)
database=${database#*=}
if [[ -z "$database" || ! "$database" =~ ^[A-Za-z0-9_]+$ ]]; then
    echo "ERROR: $database_key no es valido." >&2
    exit 65
fi

if ! podman container exists "$container_name" \
    || [[ $(podman inspect --format '{{.State.Status}}' "$container_name") != running ]]; then
    echo "ERROR: $container_name no esta en ejecucion." >&2
    exit 69
fi

mkdir -p "$backup_directory"
chmod 700 "$backup_directory"
timestamp=$(date -u +'%Y%m%dT%H%M%SZ')
file_name="${environment_name}-${timestamp}.sql"
final_path="$backup_directory/$file_name"
temporary_path=$(mktemp "$backup_directory/.${environment_name}.XXXXXX.sql")
trap 'rm -f "$temporary_path"' EXIT

podman exec "$container_name" sh -ceu '
case "$1" in
    staging) database=$MYSQL_STAGING_DATABASE ;;
    production) database=$MYSQL_PRODUCTION_DATABASE ;;
    *) exit 64 ;;
esac
MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysqldump \
    --protocol=socket \
    --user=root \
    --single-transaction \
    --quick \
    --routines \
    --triggers \
    --events \
    --set-gtid-purged=OFF \
    --no-tablespaces \
    "$database"
' -- "$environment_name" > "$temporary_path"

if [[ ! -s "$temporary_path" ]]; then
    echo "ERROR: mysqldump genero un archivo vacio." >&2
    exit 1
fi

mv "$temporary_path" "$final_path"
trap - EXIT
(
    cd "$backup_directory"
    sha256sum "$file_name" > "$file_name.sha256"
)

mysql_image=$(podman inspect --format '{{.ImageName}}' "$container_name")
cat > "$final_path.metadata.json" <<JSON
{
  "environment": "$environment_name",
  "database": "$database",
  "mysql_image": "$mysql_image",
  "created_at_utc": "$timestamp"
}
JSON
chmod 600 "$final_path" "$final_path.sha256" "$final_path.metadata.json"

echo "Respaldo creado: $final_path"
echo "SHA-256: $final_path.sha256"
