[CmdletBinding()]
param(
    [string] $Repository = 'luzparral-app',
    [string] $Tag = '',
    [string] $OutputDirectory = '',
    [string] $DatabaseImage = 'mysql:8.4.11',
    [switch] $SkipBuild,
    [switch] $SkipDatabaseExport,
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

    if (-not $SkipDatabaseExport) {
        & docker image inspect $DatabaseImage *> $null
        if ($LASTEXITCODE -ne 0) {
            Write-Output "Descargando la imagen de base de datos $DatabaseImage..."
            & docker pull $DatabaseImage
            if ($LASTEXITCODE -ne 0) {
                throw "No fue posible descargar $DatabaseImage."
            }
        }

        $databaseOperatingSystem = (& docker image inspect $DatabaseImage --format '{{.Os}}').Trim()
        $databaseArchitecture = (& docker image inspect $DatabaseImage --format '{{.Architecture}}').Trim()
        if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($databaseArchitecture)) {
            throw "La imagen $DatabaseImage no se puede inspeccionar."
        }

        $safeDatabaseReference = $DatabaseImage -replace '[^A-Za-z0-9_.-]', '-'
        $databaseFileName = "$safeDatabaseReference-$databaseOperatingSystem-$databaseArchitecture.tar"
        $databaseArchivePath = Join-Path $resolvedOutputDirectory $databaseFileName
        & docker save --output $databaseArchivePath $DatabaseImage
        if ($LASTEXITCODE -ne 0) {
            throw 'La exportacion de la imagen MySQL fallo.'
        }

        $databaseHash = Get-FileHash -Algorithm SHA256 -LiteralPath $databaseArchivePath
        $databaseChecksumPath = "$databaseArchivePath.sha256"
        $databaseChecksumLine = "$($databaseHash.Hash.ToLowerInvariant())  $databaseFileName`n"
        [System.IO.File]::WriteAllText($databaseChecksumPath, $databaseChecksumLine, [System.Text.Encoding]::ASCII)

        $databaseMetadata = [ordered]@{
            image = $DatabaseImage
            role = 'private-database'
            os = $databaseOperatingSystem
            architecture = $databaseArchitecture
            sha256 = $databaseHash.Hash.ToLowerInvariant()
            created_at_utc = [DateTime]::UtcNow.ToString('o')
        }
        $databaseMetadata | ConvertTo-Json | Set-Content -Encoding utf8 -Path "$databaseArchivePath.metadata.json"

        Write-Output "Imagen MySQL: $DatabaseImage"
        Write-Output "Archivo MySQL: $databaseArchivePath"
        Write-Output "SHA-256 MySQL: $($databaseHash.Hash.ToLowerInvariant())"
        Write-Output "Plataforma MySQL: $databaseOperatingSystem/$databaseArchitecture"
    }
}
finally {
    Pop-Location
}
