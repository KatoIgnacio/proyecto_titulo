#!/usr/bin/env bash
set -Eeuo pipefail

usage() {
    echo "Uso: $0 staging|production [DIRECTORIO_RESPALDOS]" >&2
}

if [[ $# -lt 1 || $# -gt 2 ]]; then
    usage
    exit 64
fi

environment_name=$1
script_directory=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
backup_directory=${2:-"$script_directory/../backups"}
application_environment_file="$script_directory/../config/parra-${environment_name}.env"
mysql_environment_file="$script_directory/../config/mysql.env"
database_container=luzparral-mysql
database_volume=luzparral-mysql-data

case "$environment_name" in
    staging)
        host_port=8004
        application_container=luzparral-staging
        application_volume=luzparral-staging-storage
        ;;
    production)
        host_port=8003
        application_container=luzparral-production
        application_volume=luzparral-production-storage
        ;;
    *)
        usage
        exit 64
        ;;
esac

for command_name in podman grep sha256sum loginctl ss df awk stat date; do
    if ! command -v "$command_name" >/dev/null 2>&1; then
        echo "ERROR: $command_name no esta disponible." >&2
        exit 69
    fi
done

"$script_directory/verify.sh" "$environment_name" --database

for private_file in "$application_environment_file" "$mysql_environment_file"; do
    if [[ ! -f "$private_file" ]]; then
        echo "ERROR: no existe el archivo privado $private_file." >&2
        exit 66
    fi
    if [[ $(stat -c '%a' "$private_file") != 600 ]]; then
        echo "ERROR: el archivo privado debe tener modo 600: $private_file." >&2
        exit 77
    fi
done

rootless=$(podman info --format '{{.Host.Security.Rootless}}')
if [[ "$rootless" != true ]]; then
    echo "ERROR: Podman debe ejecutarse en modo rootless." >&2
    exit 1
fi

linger=$(loginctl show-user "$USER" -p Linger)
if [[ "$linger" != Linger=yes ]]; then
    echo "ERROR: Linger no esta habilitado para $USER." >&2
    exit 1
fi

for container_name in "$database_container" "$application_container"; do
    if ! podman container exists "$container_name"; then
        echo "ERROR: no existe el contenedor $container_name." >&2
        exit 69
    fi

    restart_policy=$(podman container inspect --format '{{.HostConfig.RestartPolicy.Name}}' "$container_name")
    auto_remove=$(podman container inspect --format '{{.HostConfig.AutoRemove}}' "$container_name")

    if [[ "$restart_policy" != unless-stopped ]]; then
        echo "ERROR: $container_name requiere restart=unless-stopped; actual=$restart_policy." >&2
        exit 1
    fi
    if [[ "$auto_remove" != false ]]; then
        echo "ERROR: $container_name no debe usar AutoRemove." >&2
        exit 1
    fi
done

published_port=$(podman port "$application_container" 8080/tcp)
if ! grep --extended-regexp --quiet ":${host_port}$" <<< "$published_port"; then
    echo "ERROR: $application_container no publica 8080/tcp en el puerto asignado $host_port." >&2
    exit 1
fi

if ss -ltn | grep --extended-regexp --quiet ':(2003|2004)([[:space:]]|$)'; then
    echo "ERROR: existe un listener en los puertos no asignados 2003 o 2004." >&2
    exit 1
fi

for volume_name in "$database_volume" "$application_volume"; do
    if ! podman volume exists "$volume_name"; then
        echo "ERROR: no existe el volumen persistente $volume_name." >&2
        exit 69
    fi
done

if [[ ! -d "$backup_directory" ]]; then
    echo "ERROR: no existe el directorio de respaldos $backup_directory." >&2
    exit 66
fi

shopt -s nullglob
backup_candidates=("$backup_directory"/"$environment_name"-*.sql)
if [[ ${#backup_candidates[@]} -eq 0 ]]; then
    echo "ERROR: no existe un respaldo SQL para $environment_name en $backup_directory." >&2
    exit 66
fi

latest_backup=${backup_candidates[0]}
for candidate in "${backup_candidates[@]}"; do
    if [[ "$candidate" -nt "$latest_backup" ]]; then
        latest_backup=$candidate
    fi
done

checksum_path="${latest_backup}.sha256"
metadata_path="${latest_backup}.metadata.json"
if [[ ! -f "$checksum_path" || ! -f "$metadata_path" ]]; then
    echo "ERROR: el respaldo mas reciente no posee SHA-256 y metadatos adyacentes." >&2
    exit 66
fi

(
    cd "$(dirname "$latest_backup")"
    sha256sum --check "$(basename "$checksum_path")" >/dev/null
)
grep --fixed-strings --quiet "\"environment\": \"$environment_name\"" "$metadata_path" \
    || { echo "ERROR: los metadatos del respaldo no corresponden a $environment_name." >&2; exit 65; }

for backup_file in "$latest_backup" "$checksum_path" "$metadata_path"; do
    if [[ $(stat -c '%a' "$backup_file") != 600 ]]; then
        echo "ERROR: el respaldo debe tener modo 600: $backup_file." >&2
        exit 77
    fi
done

backup_age_seconds=$(( $(date +%s) - $(stat -c '%Y' "$latest_backup") ))
if (( backup_age_seconds < 0 )); then
    echo "ADVERTENCIA: el respaldo mas reciente posee una fecha futura." >&2
elif (( backup_age_seconds > 691200 )); then
    echo "ADVERTENCIA: el respaldo mas reciente supera ocho dias." >&2
fi
backup_age_days=$(( backup_age_seconds / 86400 ))

disk_use=$(df -P "$HOME" | awk 'NR == 2 {gsub(/%/, "", $5); print $5}')
if [[ ! "$disk_use" =~ ^[0-9]+$ ]]; then
    echo "ERROR: no fue posible determinar el uso de disco." >&2
    exit 1
fi
if (( disk_use >= 85 )); then
    echo "ADVERTENCIA: el uso de disco de $HOME es ${disk_use}%." >&2
fi

echo "Auditoria operativa: OK"
echo "Entorno: $environment_name; puerto asignado: $host_port"
echo "Podman: rootless=true; Linger=yes; reinicio=unless-stopped"
echo "Persistencia: $database_volume y $application_volume"
echo "Respaldo verificado: $latest_backup (edad: ${backup_age_days} dias)"
echo "Uso de disco: ${disk_use}%"
echo "Puertos no asignados 2003/2004: libres"