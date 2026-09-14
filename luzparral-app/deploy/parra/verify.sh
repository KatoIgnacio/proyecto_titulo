#!/usr/bin/env bash
set -Eeuo pipefail

if [[ $# -lt 1 || $# -gt 2 ]]; then
    echo "Uso: $0 staging|production [--database]" >&2
    exit 64
fi

case "$1" in
    staging)
        host_port=2004
        container_name=luzparral-staging
        ;;
    production)
        host_port=2003
        container_name=luzparral-production
        ;;
    *)
        echo "Entorno invalido: $1" >&2
        exit 64
        ;;
esac

database_check=${2:-}
if [[ -n "$database_check" && "$database_check" != --database ]]; then
    echo "Opcion invalida: $database_check" >&2
    exit 64
fi

if ! podman container exists "$container_name"; then
    echo "ERROR: no existe el contenedor $container_name" >&2
    exit 69
fi

container_state=$(podman inspect --format '{{.State.Status}}' "$container_name")
container_health=$(podman inspect --format '{{.State.Health.Status}}' "$container_name")

if [[ "$container_state" != running || "$container_health" != healthy ]]; then
    echo "ERROR: estado=$container_state salud=$container_health" >&2
    podman logs --tail 100 "$container_name" >&2
    exit 1
fi

curl --silent --show-error --fail --max-time 10 "http://127.0.0.1:${host_port}/up" >/dev/null
curl --silent --show-error --fail --max-time 10 "http://127.0.0.1:${host_port}/login" >/dev/null

echo "Aplicacion: OK"
echo "Contenedor: $container_state / $container_health"
echo "Puerto: $host_port -> 8080"

if [[ "$database_check" == --database ]]; then
    podman exec "$container_name" php artisan migrate:status --no-interaction
    echo "Conexion y estado de migraciones: OK"
fi
