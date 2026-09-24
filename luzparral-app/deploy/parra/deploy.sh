#!/usr/bin/env bash
set -Eeuo pipefail

usage() {
    echo "Uso: $0 staging|production IMAGEN [APP_ENV] [MYSQL_ENV]" >&2
}

if [[ $# -lt 2 || $# -gt 4 ]]; then
    usage
    exit 64
fi

environment_name=$1
image_reference=$2
script_directory=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
application_environment_file=${3:-"$script_directory/../config/parra-${environment_name}.env"}
mysql_environment_file=${4:-"$script_directory/../config/mysql.env"}
network_name=luzparral-private
edge_network_name=luzparral-edge
database_container=luzparral-mysql

case "$environment_name" in
    staging)
        host_port=2004
        container_name=luzparral-staging
        storage_volume=luzparral-staging-storage
        mysql_database_key=MYSQL_STAGING_DATABASE
        mysql_user_key=MYSQL_STAGING_USER
        mysql_password_key=MYSQL_STAGING_PASSWORD
        ;;
    production)
        host_port=2003
        container_name=luzparral-production
        storage_volume=luzparral-production-storage
        mysql_database_key=MYSQL_PRODUCTION_DATABASE
        mysql_user_key=MYSQL_PRODUCTION_USER
        mysql_password_key=MYSQL_PRODUCTION_PASSWORD
        ;;
    *)
        usage
        exit 64
        ;;
esac

for command_name in podman curl grep stat; do
    if ! command -v "$command_name" >/dev/null 2>&1; then
        echo "ERROR: $command_name no esta disponible." >&2
        exit 69
    fi
done

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

require_private_file "$application_environment_file"
require_private_file "$mysql_environment_file"

required_app_keys=(
    APP_KEY APP_ENV APP_DEBUG APP_URL LOG_CHANNEL
    DB_HOST DB_DATABASE DB_USERNAME DB_PASSWORD
    SESSION_DRIVER SESSION_LIFETIME SESSION_EXPIRE_ON_CLOSE SESSION_ENCRYPT SESSION_HTTP_ONLY
    PASSWORD_RESET_ENABLED SECURITY_HEADERS_ENABLED SECURITY_MAX_ACTIVE_USERS
)

for key in "${required_app_keys[@]}"; do
    value=$(read_setting "$application_environment_file" "$key")
    if [[ -z "$value" || "$value" == REEMPLAZAR_* ]]; then
        echo "ERROR: $key no esta configurada en $application_environment_file" >&2
        exit 65
    fi
done

if [[ $(read_setting "$application_environment_file" APP_ENV) != production \
    || $(read_setting "$application_environment_file" APP_DEBUG) != false ]]; then
    echo "ERROR: Parra requiere APP_ENV=production y APP_DEBUG=false." >&2
    exit 65
fi

if [[ $(read_setting "$application_environment_file" LOG_CHANNEL) != stderr_json ]]; then
    echo "ERROR: Parra requiere LOG_CHANNEL=stderr_json para integrar los logs con Podman." >&2
    exit 65
fi

if [[ $(read_setting "$application_environment_file" SESSION_DRIVER) != database \
    || $(read_setting "$application_environment_file" SESSION_EXPIRE_ON_CLOSE) != true \
    || $(read_setting "$application_environment_file" SESSION_ENCRYPT) != true \
    || $(read_setting "$application_environment_file" SESSION_HTTP_ONLY) != true \
    || $(read_setting "$application_environment_file" PASSWORD_RESET_ENABLED) != false \
    || $(read_setting "$application_environment_file" SECURITY_HEADERS_ENABLED) != true \
    || $(read_setting "$application_environment_file" SECURITY_MAX_ACTIVE_USERS) != 10 ]]; then
    echo "ERROR: la configuracion de seguridad operativa de Parra no es valida." >&2
    exit 65
fi

session_lifetime=$(read_setting "$application_environment_file" SESSION_LIFETIME)
if [[ ! "$session_lifetime" =~ ^[0-9]+$ || "$session_lifetime" -gt 60 ]]; then
    echo "ERROR: SESSION_LIFETIME debe ser un numero de hasta 60 minutos." >&2
    exit 65
fi

if [[ $(read_setting "$application_environment_file" APP_URL) != *":${host_port}"* ]]; then
    echo "ERROR: APP_URL debe utilizar el puerto $host_port para $environment_name." >&2
    exit 65
fi

if [[ $(read_setting "$application_environment_file" APP_URL) == https://* \
    && $(read_setting "$application_environment_file" SESSION_SECURE_COOKIE) != true ]]; then
    echo "ERROR: SESSION_SECURE_COOKIE debe ser true cuando APP_URL utiliza HTTPS." >&2
    exit 65
fi

if [[ $(read_setting "$application_environment_file" DB_HOST) != "$database_container" \
    || $(read_setting "$application_environment_file" DB_DATABASE) != $(read_setting "$mysql_environment_file" "$mysql_database_key") \
    || $(read_setting "$application_environment_file" DB_USERNAME) != $(read_setting "$mysql_environment_file" "$mysql_user_key") \
    || $(read_setting "$application_environment_file" DB_PASSWORD) != $(read_setting "$mysql_environment_file" "$mysql_password_key") ]]; then
    echo "ERROR: las credenciales DB_* no coinciden con el entorno $environment_name definido en mysql.env." >&2
    exit 65
fi

if ! podman image exists "$image_reference"; then
    echo "ERROR: la imagen $image_reference no esta cargada." >&2
    exit 69
fi

"$script_directory/database.sh" status >/dev/null
podman volume exists "$storage_volume" || podman volume create "$storage_volume" >/dev/null
mkdir -p "$script_directory/.state"

# El respaldo se realiza antes de detener la versión actual. mysqldump usa una
# transacción consistente y el despliegue se cancela si no puede generarlo.
backup_output=$("$script_directory/backup-database.sh" "$environment_name" "$mysql_environment_file")
echo "$backup_output"

previous_image=''
if podman container exists "$container_name"; then
    previous_image=$(podman inspect --format '{{.ImageName}}' "$container_name")
    printf '%s\n' "$previous_image" > "$script_directory/.state/${environment_name}.previous-image"
    podman stop "$container_name" >/dev/null
fi

if ! podman run --rm \
    --name "${container_name}-migration" \
    --security-opt no-new-privileges \
    --network "$network_name" \
    --env-file "$application_environment_file" \
    --volume "${storage_volume}:/var/www/html/storage" \
    "$image_reference" \
    php artisan migrate --force --no-interaction; then
    echo "ERROR: las migraciones fallaron; la version anterior se reiniciara." >&2
    if [[ -n "$previous_image" ]]; then
        podman start "$container_name" >/dev/null || true
    fi
    exit 1
fi

if podman container exists "$container_name"; then
    podman rm --force "$container_name" >/dev/null
fi

start_container() {
    local selected_image=$1
    podman run --detach \
        --name "$container_name" \
        --restart unless-stopped \
        --security-opt no-new-privileges \
        --network "$network_name" \
        --network "$edge_network_name" \
        --env-file "$application_environment_file" \
        --publish "${host_port}:8080" \
        --volume "${storage_volume}:/var/www/html/storage" \
        "$selected_image" >/dev/null
}

wait_for_application() {
    local attempts=0
    until curl --silent --show-error --fail --max-time 5 "http://127.0.0.1:${host_port}/up" >/dev/null; do
        attempts=$((attempts + 1))
        if [[ $attempts -ge 20 ]]; then
            return 1
        fi
        sleep 3
    done
}

start_container "$image_reference"

if wait_for_application; then
    echo "Despliegue saludable: $environment_name en el puerto $host_port con $image_reference"
    echo "Base privada: $database_container/$network_name (3306 no publicado)"
    exit 0
fi

echo "ERROR: la nueva version no supero la comprobacion de salud." >&2
podman logs --tail 100 "$container_name" >&2 || true
podman rm --force "$container_name" >/dev/null 2>&1 || true

if [[ -n "$previous_image" ]] && podman image exists "$previous_image"; then
    echo "Intentando reversion automatica de la aplicacion a $previous_image" >&2
    start_container "$previous_image"
    if wait_for_application; then
        echo "Reversion de aplicacion completada. Revise si la migracion requiere restaurar el respaldo." >&2
        exit 1
    fi
fi

echo "ERROR CRITICO: el servicio no pudo iniciarse y requiere revision manual." >&2
exit 1
