#!/usr/bin/env bash
set -Eeuo pipefail

if [[ $# -ne 1 ]]; then
    echo "Uso: $0 RUTA_ARCHIVO_TAR" >&2
    exit 64
fi

for command_name in podman sha256sum; do
    if ! command -v "$command_name" >/dev/null 2>&1; then
        echo "ERROR: $command_name no esta disponible." >&2
        exit 69
    fi
done

if [[ ! -f "$1" ]]; then
    echo "ERROR: no existe $1" >&2
    exit 66
fi

archive_path=$(realpath "$1")
checksum_path="${archive_path}.sha256"

if [[ ! -f "$checksum_path" ]]; then
    echo "ERROR: falta $checksum_path" >&2
    exit 66
fi

archive_directory=$(dirname "$archive_path")
checksum_name=$(basename "$checksum_path")

(
    cd "$archive_directory"
    sha256sum --check "$checksum_name"
)

podman load --input "$archive_path"

echo "Imagen cargada. Confirme su nombre con: podman images luzparral-app"
