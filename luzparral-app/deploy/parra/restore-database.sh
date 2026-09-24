#!/usr/bin/env bash
set -Eeuo pipefail

if [[ $# -lt 3 || $# -gt 4 || ${3:-} != --confirm-empty-target ]]; then
    echo "Uso: $0 staging|production RESPALDO.sql --confirm-empty-target [MYSQL_ENV]" >&2
    exit 64
fi

environment_name=$1
backup_path=$2
script_directory=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
environment_file=${4:-"$script_directory/../config/mysql.env"}
container_name=luzparral-mysql
app_container="luzparral-$environment_name"

case "$environment_name" in
    staging) database_key=MYSQL_STAGING_DATABASE ;;
    production) database_key=MYSQL_PRODUCTION_DATABASE ;;
    *) echo "Entorno invalido: $environment_name" >&2; exit 64 ;;
esac

for command_name in podman sha256sum grep stat realpath; do
    command -v "$command_name" >/dev/null 2>&1 || { echo "ERROR: $command_name no esta disponible." >&2; exit 69; }
done

if [[ ! -f "$environment_file" || $(stat -c '%a' "$environment_file") != 600 ]]; then
    echo "ERROR: el archivo MySQL debe existir y tener modo 600: $environment_file" >&2
    exit 77
fi

backup_path=$(realpath "$backup_path")
checksum_path="$backup_path.sha256"
metadata_path="$backup_path.metadata.json"
if [[ ! -f "$backup_path" || ! -f "$checksum_path" || ! -f "$metadata_path" ]]; then
    echo "ERROR: el respaldo requiere SQL, SHA-256 y metadatos adyacentes." >&2
    exit 66
fi

(
    cd "$(dirname "$backup_path")"
    sha256sum --check "$(basename "$checksum_path")"
)

database=$(grep -E "^${database_key}=" "$environment_file" | tail -n 1)
database=${database#*=}
grep --fixed-strings --quiet "\"environment\": \"$environment_name\"" "$metadata_path" \
    || { echo "ERROR: el respaldo no corresponde a $environment_name." >&2; exit 65; }
grep --fixed-strings --quiet "\"database\": \"$database\"" "$metadata_path" \
    || { echo "ERROR: el respaldo no corresponde a la base $database." >&2; exit 65; }

if podman container exists "$app_container" \
    && [[ $(podman inspect --format '{{.State.Status}}' "$app_container") == running ]]; then
    echo "ERROR: detenga $app_container antes de restaurar para evitar escrituras concurrentes." >&2
    exit 1
fi

if ! podman container exists "$container_name" \
    || [[ $(podman inspect --format '{{.State.Status}}' "$container_name") != running ]]; then
    echo "ERROR: $container_name no esta en ejecucion." >&2
    exit 69
fi

table_count=$(podman exec "$container_name" sh -ceu '
case "$1" in
    staging) database=$MYSQL_STAGING_DATABASE ;;
    production) database=$MYSQL_PRODUCTION_DATABASE ;;
    *) exit 64 ;;
esac
MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --protocol=socket -uroot --batch --skip-column-names \
    --execute="SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '\''$database'\'';"
' -- "$environment_name")

if [[ "$table_count" != 0 ]]; then
    echo "ERROR: $database contiene $table_count tablas. La restauracion solo admite un destino vacio." >&2
    exit 1
fi

podman exec --interactive "$container_name" sh -ceu '
case "$1" in
    staging) database=$MYSQL_STAGING_DATABASE ;;
    production) database=$MYSQL_PRODUCTION_DATABASE ;;
esac
MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql --protocol=socket -uroot "$database"
' -- "$environment_name" < "$backup_path"

restored_count=$(podman exec "$container_name" sh -ceu '
case "$1" in
    staging) database=$MYSQL_STAGING_DATABASE ;;
    production) database=$MYSQL_PRODUCTION_DATABASE ;;
esac
MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --protocol=socket -uroot --batch --skip-column-names \
    --execute="SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '\''$database'\'';"
' -- "$environment_name")

if [[ "$restored_count" -eq 0 ]]; then
    echo "ERROR: la restauracion no creo tablas en $database." >&2
    exit 1
fi

echo "Restauracion completada en $database ($restored_count tablas)."
echo "Ejecute verify.sh $environment_name --database despues de iniciar la aplicacion."
