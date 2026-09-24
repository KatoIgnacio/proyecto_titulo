#!/usr/bin/env bash
set -Eeuo pipefail

if [[ $# -ne 2 ]]; then
    echo "Uso: $0 staging|production RESPALDO.sql" >&2
    exit 64
fi

environment_name=$1
backup_path=$(realpath "$2")
container_name=luzparral-mysql

case "$environment_name" in
    staging|production) ;;
    *) echo "Entorno invalido: $environment_name" >&2; exit 64 ;;
esac

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
grep --fixed-strings --quiet "\"environment\": \"$environment_name\"" "$metadata_path" \
    || { echo "ERROR: los metadatos no corresponden a $environment_name." >&2; exit 65; }

if ! podman container exists "$container_name" \
    || [[ $(podman inspect --format '{{.State.Status}}' "$container_name") != running ]]; then
    echo "ERROR: $container_name no esta en ejecucion." >&2
    exit 69
fi

temporary_database="sigcel_restorecheck_${environment_name}_$$"
cleanup() {
    podman exec "$container_name" sh -ceu '
MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --protocol=socket -uroot \
    --execute="DROP DATABASE IF EXISTS \`$1\`;"
' -- "$temporary_database" >/dev/null 2>&1 || true
}
trap cleanup EXIT

podman exec "$container_name" sh -ceu '
MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --protocol=socket -uroot \
    --execute="CREATE DATABASE \`$1\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
' -- "$temporary_database"

podman exec --interactive "$container_name" sh -ceu '
MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql --protocol=socket -uroot "$1"
' -- "$temporary_database" < "$backup_path"

table_count=$(podman exec "$container_name" sh -ceu '
MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --protocol=socket -uroot --batch --skip-column-names \
    --execute="SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '\''$1'\'';"
' -- "$temporary_database")

if [[ "$table_count" -eq 0 ]]; then
    echo "ERROR: el ensayo de restauracion no creo tablas." >&2
    exit 1
fi

echo "Ensayo de restauracion aprobado: $table_count tablas verificadas en una base temporal."
