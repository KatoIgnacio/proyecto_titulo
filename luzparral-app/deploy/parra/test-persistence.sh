#!/usr/bin/env bash
set -Eeuo pipefail

usage() {
    echo "Uso: $0 staging|production --confirm-restart [MYSQL_ENV] [DIRECTORIO_RESPALDOS]" >&2
}

if [[ $# -lt 2 || $# -gt 4 ]]; then
    usage
    exit 64
fi

environment_name=$1
confirmation=$2
script_directory=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
mysql_environment_file=${3:-"$script_directory/../config/mysql.env"}
backup_directory=${4:-"$script_directory/../backups"}
database_container=luzparral-mysql

case "$environment_name" in
    staging) application_container=luzparral-staging ;;
    production) application_container=luzparral-production ;;
    *) usage; exit 64 ;;
esac

if [[ "$confirmation" != --confirm-restart ]]; then
    echo "ERROR: la prueba reinicia contenedores; use --confirm-restart de forma explicita." >&2
    exit 64
fi

if ! command -v podman >/dev/null 2>&1; then
    echo "ERROR: podman no esta disponible." >&2
    exit 69
fi

for container_name in "$database_container" "$application_container"; do
    if ! podman container exists "$container_name"; then
        echo "ERROR: no existe el contenedor $container_name." >&2
        exit 69
    fi
done

backup_output=$("$script_directory/backup-database.sh" \
    "$environment_name" \
    "$mysql_environment_file" \
    "$backup_directory")
echo "$backup_output"

podman restart "$database_container" >/dev/null

attempts=0
until [[ $(podman container inspect --format '{{.State.Health.Status}}' "$database_container") == healthy ]]; do
    attempts=$((attempts + 1))
    if [[ $attempts -ge 48 ]]; then
        echo "ERROR: MySQL no recupero el estado saludable despues del reinicio." >&2
        podman logs --tail 100 "$database_container" >&2 || true
        exit 1
    fi
    sleep 3
done

podman restart "$application_container" >/dev/null

attempts=0
until podman healthcheck run "$application_container" >/dev/null 2>&1; do
    attempts=$((attempts + 1))
    if [[ $attempts -ge 20 ]]; then
        echo "ERROR: la aplicacion no recupero el estado saludable despues del reinicio." >&2
        podman logs --tail 100 "$application_container" >&2 || true
        exit 1
    fi
    sleep 3
done

"$script_directory/operational-check.sh" "$environment_name" "$backup_directory"
echo "Prueba de persistencia de contenedores: OK"
echo "El reinicio completo del servidor requiere coordinacion institucional."