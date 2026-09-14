[CmdletBinding()]
param(
    [string] $Repository = 'luzparral-app',
    [string] $Tag = '',
    [string] $OutputDirectory = '',
    [switch] $SkipBuild,
    [switch] $AllowDirty
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$repositoryRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path

if ([string]::IsNullOrWhiteSpace($OutputDirectory)) {
    $OutputDirectory = Join-Path $repositoryRoot 'artifacts'
}

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw 'Docker no esta disponible en PATH.'
}

if (-not (Get-Command git -ErrorAction SilentlyContinue)) {
    throw 'Git no esta disponible en PATH.'
}

Push-Location $repositoryRoot

try {
    $revision = (& git rev-parse --short=12 HEAD).Trim()
    if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($revision)) {
        throw 'No fue posible obtener el commit actual.'
    }

    $workingTreeState = (& git status --porcelain | Out-String).Trim()
    $isDirty = -not [string]::IsNullOrWhiteSpace($workingTreeState)

    if ($isDirty -and -not $AllowDirty) {
        throw 'El repositorio tiene cambios sin commit. Confirme los cambios antes de exportar o use -AllowDirty solo para una prueba local.'
    }

    if ([string]::IsNullOrWhiteSpace($Tag)) {
        $Tag = $revision
    }

    if ($Tag -notmatch '^[A-Za-z0-9][A-Za-z0-9_.-]*$') {
        throw 'El tag solo puede contener letras, numeros, puntos, guiones y guion bajo.'
    }

    $imageReference = "${Repository}:$Tag"

    if (-not $SkipBuild) {
        & docker build --pull --tag $imageReference --file Containerfile .
        if ($LASTEXITCODE -ne 0) {
            throw 'La construccion de la imagen fallo.'
        }
    }

    $operatingSystem = (& docker image inspect $imageReference --format '{{.Os}}').Trim()
    $architecture = (& docker image inspect $imageReference --format '{{.Architecture}}').Trim()
    if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($architecture)) {
        throw "La imagen $imageReference no existe o no se puede inspeccionar."
    }

    $safeRepository = $Repository -replace '[^A-Za-z0-9_.-]', '-'
    $fileName = "$safeRepository-$Tag-$operatingSystem-$architecture.tar"
    $resolvedOutputDirectory = [System.IO.Path]::GetFullPath($OutputDirectory)
    New-Item -ItemType Directory -Force -Path $resolvedOutputDirectory | Out-Null

    $archivePath = Join-Path $resolvedOutputDirectory $fileName
    & docker save --output $archivePath $imageReference
    if ($LASTEXITCODE -ne 0) {
        throw 'La exportacion de la imagen fallo.'
    }

    $hash = Get-FileHash -Algorithm SHA256 -LiteralPath $archivePath
    $checksumPath = "$archivePath.sha256"
    $checksumLine = "$($hash.Hash.ToLowerInvariant())  $fileName`n"
    [System.IO.File]::WriteAllText($checksumPath, $checksumLine, [System.Text.Encoding]::ASCII)

    $metadata = [ordered]@{
        image = $imageReference
        commit = $revision
        dirty = $isDirty
        os = $operatingSystem
        architecture = $architecture
        sha256 = $hash.Hash.ToLowerInvariant()
        created_at_utc = [DateTime]::UtcNow.ToString('o')
    }
    $metadata | ConvertTo-Json | Set-Content -Encoding utf8 -Path "$archivePath.metadata.json"

    Write-Output "Imagen: $imageReference"
    Write-Output "Archivo: $archivePath"
    Write-Output "SHA-256: $($hash.Hash.ToLowerInvariant())"
    Write-Output "Plataforma: $operatingSystem/$architecture"
}
finally {
    Pop-Location
}
