#!/usr/bin/env bash
set -Eeuo pipefail

script_directory=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
network_name=luzparral-private
edge_network_name=luzparral-edge
container_name=luzparral-mysql
volume_name=luzparral-mysql-data
default_environment_file="$script_directory/../config/mysql.env"

usage() {
    echo "Uso: $0 start IMAGEN_MYSQL [MYSQL_ENV] | status" >&2
}

require_private_file() {
    local file_path=$1

    if [[ ! -f "$file_path" ]]; then
        echo "ERROR: no existe el archivo privado $file_path" >&2
        exit 66
    fi

    if [[ $(stat -c '%a' "$file_path") != 600 ]]; then
        echo "ERROR: proteja el archivo con chmod 600 $file_path" >&2
        exit 77
    fi
}

read_setting() {
    local file_path=$1
    local key=$2
    local line
    line=$(grep -E "^${key}=" "$file_path" | tail -n 1 || true)
    printf '%s' "${line#*=}"
}

database_status() {
    if ! podman container exists "$container_name"; then
        echo "ERROR: no existe el contenedor $container_name" >&2
        exit 69
    fi

    local state health published_ports internal_network edge_internal database_networks
    state=$(podman inspect --format '{{.State.Status}}' "$container_name")
    health=$(podman inspect --format '{{.State.Health.Status}}' "$container_name")
    published_ports=$(podman port "$container_name" 2>/dev/null || true)
    internal_network=$(podman network inspect --format '{{.Internal}}' "$network_name" 2>/dev/null || true)
    edge_internal=$(podman network inspect --format '{{.Internal}}' "$edge_network_name" 2>/dev/null || true)
    database_networks=$(podman inspect --format '{{range $name, $_ := .NetworkSettings.Networks}}{{println $name}}{{end}}' "$container_name")

    if [[ "$state" != running || "$health" != healthy ]]; then
        echo "ERROR: MySQL estado=$state salud=$health" >&2
        podman logs --tail 100 "$container_name" >&2 || true
        exit 1
    fi

    if [[ -n "$published_ports" ]]; then
        echo "ERROR: MySQL no debe publicar puertos en el host: $published_ports" >&2
        exit 1
    fi

    if [[ "$internal_network" != true ]]; then
        echo "ERROR: la red $network_name no es interna." >&2
        exit 1
    fi

    if [[ "$edge_internal" != false ]]; then
        echo "ERROR: la red $edge_network_name no esta disponible como red de entrada." >&2
        exit 1
    fi

    if grep --fixed-strings --line-regexp --quiet "$edge_network_name" <<< "$database_networks"; then
        echo "ERROR: MySQL no debe conectarse a la red de entrada $edge_network_name." >&2
        exit 1
    fi

    echo "MySQL: $state / $health"
    echo "Red privada: $network_name (internal=true)"
    echo "Red de entrada: $edge_network_name (MySQL no conectado)"
    echo "Puerto 3306: no publicado"
    echo "Volumen persistente: $volume_name"
}

action=${1:-}

for command_name in podman grep stat; do
    if ! command -v "$command_name" >/dev/null 2>&1; then
        echo "ERROR: $command_name no esta disponible." >&2
        exit 69
    fi
done

if [[ "$action" == status ]]; then
    [[ $# -eq 1 ]] || { usage; exit 64; }
    database_status
    exit 0
fi

if [[ "$action" != start || $# -lt 2 || $# -gt 3 ]]; then
    usage
    exit 64
fi

image_reference=$2
environment_file=${3:-$default_environment_file}
require_private_file "$environment_file"

required_keys=(
    MYSQL_ROOT_PASSWORD
    MYSQL_STAGING_DATABASE
    MYSQL_STAGING_USER
    MYSQL_STAGING_PASSWORD
    MYSQL_PRODUCTION_DATABASE
    MYSQL_PRODUCTION_USER
    MYSQL_PRODUCTION_PASSWORD
)

for key in "${required_keys[@]}"; do
    value=$(read_setting "$environment_file" "$key")
    if [[ -z "$value" || "$value" == REEMPLAZAR_* ]]; then
        echo "ERROR: $key no esta configurada en $environment_file" >&2
        exit 65
    fi
done

for key in MYSQL_STAGING_DATABASE MYSQL_STAGING_USER MYSQL_PRODUCTION_DATABASE MYSQL_PRODUCTION_USER; do
    value=$(read_setting "$environment_file" "$key")
    if [[ ! "$value" =~ ^[A-Za-z0-9_]+$ ]]; then
        echo "ERROR: $key solo puede contener letras, numeros y guion bajo." >&2
        exit 65
    fi
done

for key in MYSQL_ROOT_PASSWORD MYSQL_STAGING_PASSWORD MYSQL_PRODUCTION_PASSWORD; do
    value=$(read_setting "$environment_file" "$key")
    if [[ ! "$value" =~ ^[A-Za-z0-9._~-]{24,}$ ]]; then
        echo "ERROR: $key debe tener al menos 24 caracteres seguros (A-Z, a-z, 0-9, punto, guion, guion bajo o virgulilla)." >&2
        exit 65
    fi
done

if [[ $(read_setting "$environment_file" MYSQL_STAGING_DATABASE) == $(read_setting "$environment_file" MYSQL_PRODUCTION_DATABASE) \
    || $(read_setting "$environment_file" MYSQL_STAGING_USER) == $(read_setting "$environment_file" MYSQL_PRODUCTION_USER) ]]; then
    echo "ERROR: staging y produccion requieren bases y usuarios diferentes." >&2
    exit 65
fi

root_password=$(read_setting "$environment_file" MYSQL_ROOT_PASSWORD)
staging_password=$(read_setting "$environment_file" MYSQL_STAGING_PASSWORD)
production_password=$(read_setting "$environment_file" MYSQL_PRODUCTION_PASSWORD)
if [[ "$root_password" == "$staging_password" || "$root_password" == "$production_password" \
    || "$staging_password" == "$production_password" ]]; then
    echo "ERROR: root, staging y produccion requieren claves diferentes." >&2
    exit 65
fi

if ! podman image exists "$image_reference"; then
    echo "ERROR: la imagen $image_reference no esta cargada." >&2
    exit 69
fi

if podman network exists "$network_name"; then
    if [[ $(podman network inspect --format '{{.Internal}}' "$network_name") != true ]]; then
        echo "ERROR: $network_name ya existe, pero no es una red interna." >&2
        exit 1
    fi
else
    podman network create --internal "$network_name" >/dev/null
fi

if podman network exists "$edge_network_name"; then
    if [[ $(podman network inspect --format '{{.Internal}}' "$edge_network_name") != false ]]; then
        echo "ERROR: $edge_network_name ya existe, pero no admite entrada publicada." >&2
        exit 1
    fi
else
    podman network create "$edge_network_name" >/dev/null
fi

podman volume exists "$volume_name" || podman volume create "$volume_name" >/dev/null

if podman container exists "$container_name"; then
    podman rm --force "$container_name" >/dev/null
fi

podman run --detach \
    --name "$container_name" \
    --restart unless-stopped \
    --security-opt no-new-privileges \
    --network "$network_name" \
    --network-alias "$container_name" \
    --env-file "$environment_file" \
    --volume "${volume_name}:/var/lib/mysql" \
    --health-cmd 'mysqladmin ping -h 127.0.0.1 --silent' \
    --health-interval 5s \
    --health-timeout 5s \
    --health-start-period 20s \
    --health-retries 24 \
    "$image_reference" \
    --character-set-server=utf8mb4 \
    --collation-server=utf8mb4_unicode_ci >/dev/null

attempts=0
until [[ $(podman inspect --format '{{.State.Health.Status}}' "$container_name") == healthy ]]; do
    attempts=$((attempts + 1))
    if [[ $attempts -ge 48 ]]; then
        echo "ERROR: MySQL no alcanzo estado saludable." >&2
        podman logs --tail 100 "$container_name" >&2 || true
        exit 1
    fi
    sleep 3
done

# Las variables se expanden dentro del contenedor. Las claves no se incluyen
# como argumentos del proceso del host ni se imprimen en la salida.
podman exec "$container_name" sh -ceu '
MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --protocol=socket -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \`$MYSQL_STAGING_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS \`$MYSQL_PRODUCTION_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '\''$MYSQL_STAGING_USER'\''@'\''%'\'' IDENTIFIED BY '\''$MYSQL_STAGING_PASSWORD'\'';
ALTER USER '\''$MYSQL_STAGING_USER'\''@'\''%'\'' IDENTIFIED BY '\''$MYSQL_STAGING_PASSWORD'\'';
GRANT ALL PRIVILEGES ON \`$MYSQL_STAGING_DATABASE\`.* TO '\''$MYSQL_STAGING_USER'\''@'\''%'\'';
CREATE USER IF NOT EXISTS '\''$MYSQL_PRODUCTION_USER'\''@'\''%'\'' IDENTIFIED BY '\''$MYSQL_PRODUCTION_PASSWORD'\'';
ALTER USER '\''$MYSQL_PRODUCTION_USER'\''@'\''%'\'' IDENTIFIED BY '\''$MYSQL_PRODUCTION_PASSWORD'\'';
GRANT ALL PRIVILEGES ON \`$MYSQL_PRODUCTION_DATABASE\`.* TO '\''$MYSQL_PRODUCTION_USER'\''@'\''%'\'';
FLUSH PRIVILEGES;
SQL
'

database_status
