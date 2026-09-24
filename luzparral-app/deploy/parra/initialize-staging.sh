#!/usr/bin/env bash
set -Eeuo pipefail

if [[ $# -lt 1 || $# -gt 2 ]]; then
    echo "Uso: $0 APP_ENV [SEED_ENV]" >&2
    exit 64
fi

application_environment_file=$1
script_directory=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
seed_environment_file=${2:-"$script_directory/../config/staging-seed.env"}
container_name=luzparral-staging
network_name=luzparral-private

for file_path in "$application_environment_file" "$seed_environment_file"; do
    if [[ ! -f "$file_path" || $(stat -c '%a' "$file_path") != 600 ]]; then
        echo "ERROR: el archivo debe existir y tener modo 600: $file_path" >&2
        exit 77
    fi
done

demo_password=$(grep -E '^LUZPARRAL_DEMO_PASSWORD=' "$seed_environment_file" | tail -n 1)
demo_password=${demo_password#*=}
if [[ ${#demo_password} -lt 12 || "$demo_password" == REEMPLAZAR_* ]]; then
    echo "ERROR: configure una clave de demostracion temporal de al menos 12 caracteres." >&2
    exit 65
fi

if ! podman container exists "$container_name" \
    || [[ $(podman inspect --format '{{.State.Status}}' "$container_name") != running ]]; then
    echo "ERROR: despliegue staging antes de inicializar sus datos." >&2
    exit 69
fi

image_reference=$(podman inspect --format '{{.ImageName}}' "$container_name")

podman run --rm \
    --name luzparral-staging-initializer \
    --security-opt no-new-privileges \
    --network "$network_name" \
    --env-file "$application_environment_file" \
    --env-file "$seed_environment_file" \
    "$image_reference" \
    php database/synthetic/generate_synthetic.php

podman exec "$container_name" php artisan luzparral:validate-synthetic --require-runtime --no-interaction

echo "Staging contiene exclusivamente el conjunto sintetico validado."
echo "Elimine staging-seed.env cuando termine de entregar la clave por un canal privado."
