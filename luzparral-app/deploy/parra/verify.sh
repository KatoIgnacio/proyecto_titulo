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

script_directory=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
network_name=luzparral-private
edge_network_name=luzparral-edge

database_check=${2:-}
if [[ -n "$database_check" && "$database_check" != --database ]]; then
    echo "Opcion invalida: $database_check" >&2
    exit 64
fi

if ! podman container exists "$container_name"; then
    echo "ERROR: no existe el contenedor $container_name" >&2
    exit 69
fi

for command_name in curl grep; do
    if ! command -v "$command_name" >/dev/null 2>&1; then
        echo "ERROR: $command_name no esta disponible." >&2
        exit 69
    fi
done

"$script_directory/database.sh" status >/dev/null

application_networks=$(podman inspect --format '{{range $name, $_ := .NetworkSettings.Networks}}{{println $name}}{{end}}' "$container_name")
if ! grep --fixed-strings --line-regexp --quiet "$network_name" <<< "$application_networks"; then
    echo "ERROR: $container_name no esta conectado a la red privada $network_name." >&2
    exit 1
fi
if ! grep --fixed-strings --line-regexp --quiet "$edge_network_name" <<< "$application_networks"; then
    echo "ERROR: $container_name no esta conectado a la red de entrada $edge_network_name." >&2
    exit 1
fi

container_state=$(podman inspect --format '{{.State.Status}}' "$container_name")
container_health=$(podman inspect --format '{{.State.Health.Status}}' "$container_name")

if [[ "$container_state" != running || "$container_health" != healthy ]]; then
    echo "ERROR: estado=$container_state salud=$container_health" >&2
    podman logs --tail 100 "$container_name" >&2
    exit 1
fi

curl --silent --show-error --fail --max-time 10 "http://127.0.0.1:${host_port}/up" >/dev/null
login_headers=$(curl --silent --show-error --fail --max-time 10 --dump-header - --output /dev/null "http://127.0.0.1:${host_port}/login")

for expected_header in 'X-Content-Type-Options: nosniff' 'X-Frame-Options: DENY' 'Content-Security-Policy:'; do
    if ! grep --ignore-case --quiet "^${expected_header}" <<< "$login_headers"; then
        echo "ERROR: falta el encabezado de seguridad $expected_header" >&2
        exit 1
    fi
done

password_reset_status=$(curl --silent --show-error --max-time 10 --output /dev/null --write-out '%{http_code}' "http://127.0.0.1:${host_port}/forgot-password")
if [[ "$password_reset_status" != 404 ]]; then
    echo "ERROR: la recuperacion web sin SMTP responde HTTP $password_reset_status; se esperaba 404." >&2
    exit 1
fi

echo "Aplicacion: OK"
echo "Contenedor: $container_state / $container_health"
echo "Puerto: $host_port -> 8080"
echo "MySQL: red privada verificada; 3306 no publicado"
echo "Encabezados y recuperacion web: OK"

if [[ "$database_check" == --database ]]; then
    podman exec "$container_name" php artisan luzparral:health --database --json --no-interaction
    echo "Conexion y esquema de base de datos: OK"
fi
