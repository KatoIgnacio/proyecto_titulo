#!/usr/bin/env bash
set -Eeuo pipefail

usage() {
    echo "Uso: $0 staging|production IMAGEN [ARCHIVO_ENV]" >&2
}

if [[ $# -lt 2 || $# -gt 3 ]]; then
    usage
    exit 64
fi

environment_name=$1
image_reference=$2
script_directory=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
environment_file=${3:-"$script_directory/parra.env"}

case "$environment_name" in
    staging)
        host_port=2004
        container_name=luzparral-staging
        storage_volume=luzparral-staging-storage
        ;;
    production)
        host_port=2003
        container_name=luzparral-production
        storage_volume=luzparral-production-storage
        ;;
    *)
        usage
        exit 64
        ;;
esac

for command_name in podman curl grep; do
    if ! command -v "$command_name" >/dev/null 2>&1; then
        echo "ERROR: $command_name no esta disponible." >&2
        exit 69
    fi
done

if [[ ! -f "$environment_file" ]]; then
    echo "ERROR: no existe el archivo privado $environment_file" >&2
    exit 66
fi

read_setting() {
    local key=$1
    local line
    line=$(grep -E "^${key}=" "$environment_file" | tail -n 1 || true)
    printf '%s' "${line#*=}"
}

for required_key in APP_KEY APP_ENV APP_DEBUG APP_URL DB_HOST DB_DATABASE DB_USERNAME DB_PASSWORD; do
    setting_value=$(read_setting "$required_key")
    if [[ -z "$setting_value" || "$setting_value" == REEMPLAZAR_* ]]; then
        echo "ERROR: $required_key no esta configurada en $environment_file" >&2
        exit 65
    fi
done

if [[ $(read_setting APP_ENV) != production || $(read_setting APP_DEBUG) != false ]]; then
    echo "ERROR: Parra requiere APP_ENV=production y APP_DEBUG=false." >&2
    exit 65
fi

if [[ $(read_setting APP_URL) != *":${host_port}"* ]]; then
    echo "ERROR: APP_URL debe utilizar el puerto $host_port para $environment_name." >&2
    exit 65
fi

file_mode=$(stat -c '%a' "$environment_file")
if [[ "$file_mode" != 600 ]]; then
    echo "ERROR: proteja el archivo con chmod 600 $environment_file" >&2
    exit 77
fi

if ! podman image exists "$image_reference"; then
    echo "ERROR: la imagen $image_reference no esta cargada." >&2
    exit 69
fi

mkdir -p "$script_directory/.state"
previous_image=''

if podman container exists "$container_name"; then
    previous_image=$(podman inspect --format '{{.ImageName}}' "$container_name")
    printf '%s\n' "$previous_image" > "$script_directory/.state/${environment_name}.previous-image"
    podman rm --force "$container_name"
fi

podman volume exists "$storage_volume" || podman volume create "$storage_volume" >/dev/null

start_container() {
    local selected_image=$1
    podman run --detach \
        --name "$container_name" \
        --restart unless-stopped \
        --security-opt no-new-privileges \
        --env-file "$environment_file" \
        --publish "${host_port}:8080" \
        --volume "${storage_volume}:/var/www/html/storage" \
        "$selected_image"
}

wait_for_application() {
    local attempts=0

    until curl --silent --show-error --fail --max-time 5 "http://127.0.0.1:${host_port}/up" >/dev/null; do
        attempts=$((attempts + 1))
        if [[ $attempts -ge 12 ]]; then
            return 1
        fi
        sleep 3
    done
}

start_container "$image_reference"

if wait_for_application; then
    echo "Despliegue saludable: $environment_name en el puerto $host_port con $image_reference"
    exit 0
fi

echo "ERROR: la nueva version no supero la comprobacion de salud." >&2
podman logs --tail 100 "$container_name" >&2 || true
podman rm --force "$container_name" >/dev/null 2>&1 || true

if [[ -n "$previous_image" ]] && podman image exists "$previous_image"; then
    echo "Intentando reversion automatica a $previous_image" >&2
    start_container "$previous_image"

    if wait_for_application; then
        echo "Reversion completada. La version nueva no quedo publicada." >&2
        exit 1
    fi
fi

echo "ERROR CRITICO: el servicio no pudo iniciarse y requiere revision manual." >&2
exit 1
