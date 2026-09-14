[CmdletBinding()]
param(
    [Parameter(Mandatory)]
    [ValidatePattern('^[A-Za-z0-9_][A-Za-z0-9_$-]{0,63}$')]
    [string] $Database,

    [ValidatePattern('^[A-Za-z0-9_.-]+$')]
    [string] $HostName = '127.0.0.1',

    [ValidateRange(1, 65535)]
    [int] $Port = 3306,

    [ValidatePattern('^[A-Za-z0-9_.-]+$')]
    [string] $Username = 'luzparral_app',

    [string] $OutputDirectory = '',
    [string] $MySqlDumpPath = ''
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Resolve-MySqlTool {
    param(
        [string] $Name,
        [string] $ExplicitPath
    )

    if (-not [string]::IsNullOrWhiteSpace($ExplicitPath)) {
        if (-not (Test-Path -LiteralPath $ExplicitPath -PathType Leaf)) {
            throw "No existe $ExplicitPath"
        }

        return (Resolve-Path -LiteralPath $ExplicitPath).Path
    }

    $command = Get-Command $Name -ErrorAction SilentlyContinue
    if ($null -ne $command) {
        return $command.Source
    }

    $standardPath = "C:\Program Files\MySQL\MySQL Server 8.4\bin\$Name.exe"
    if (Test-Path -LiteralPath $standardPath -PathType Leaf) {
        return $standardPath
    }

    throw "$Name no esta disponible. Instale MySQL Client o indique su ruta explicitamente."
}

$allowedDatabase = $env:LUZPARRAL_DB_ALLOWED_DATABASE
$password = $env:LUZPARRAL_DB_PASSWORD

if ([string]::IsNullOrWhiteSpace($allowedDatabase) -or $allowedDatabase -ne $Database) {
    throw 'LUZPARRAL_DB_ALLOWED_DATABASE debe coincidir exactamente con -Database.'
}

if ([string]::IsNullOrWhiteSpace($password)) {
    throw 'Defina LUZPARRAL_DB_PASSWORD en la sesion actual.'
}

$repositoryRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
if ([string]::IsNullOrWhiteSpace($OutputDirectory)) {
    $OutputDirectory = Join-Path $repositoryRoot 'backups'
}

$resolvedOutputDirectory = [System.IO.Path]::GetFullPath($OutputDirectory)
New-Item -ItemType Directory -Force -Path $resolvedOutputDirectory | Out-Null

$timestamp = [DateTime]::UtcNow.ToString('yyyyMMddTHHmmssZ')
$backupPath = Join-Path $resolvedOutputDirectory "$Database-$timestamp.sql"
$dumpBinary = Resolve-MySqlTool -Name 'mysqldump' -ExplicitPath $MySqlDumpPath
$arguments = @(
    "--host=$HostName",
    "--port=$Port",
    "--user=$Username",
    '--single-transaction',
    '--quick',
    '--hex-blob',
    '--default-character-set=utf8mb4',
    '--set-gtid-purged=OFF',
    '--no-tablespaces',
    "--result-file=$backupPath",
    $Database
)

$previousPassword = [Environment]::GetEnvironmentVariable('MYSQL_PWD', 'Process')

try {
    [Environment]::SetEnvironmentVariable('MYSQL_PWD', $password, 'Process')
    & $dumpBinary @arguments
    if ($LASTEXITCODE -ne 0) {
        throw "mysqldump termino con codigo $LASTEXITCODE."
    }
}
finally {
    [Environment]::SetEnvironmentVariable('MYSQL_PWD', $previousPassword, 'Process')
}

$backupFile = Get-Item -LiteralPath $backupPath
if ($backupFile.Length -lt 100) {
    throw 'El respaldo generado esta vacio o incompleto.'
}

$hash = Get-FileHash -Algorithm SHA256 -LiteralPath $backupPath
$checksumLine = "$($hash.Hash.ToLowerInvariant())  $($backupFile.Name)`n"
[System.IO.File]::WriteAllText("$backupPath.sha256", $checksumLine, [System.Text.Encoding]::ASCII)

$metadata = [ordered]@{
    database = $Database
    created_at_utc = [DateTime]::UtcNow.ToString('o')
    sha256 = $hash.Hash.ToLowerInvariant()
    bytes = $backupFile.Length
    transaction_consistent = $true
}
$metadata | ConvertTo-Json | Set-Content -Encoding utf8 -Path "$backupPath.metadata.json"

Write-Output "Respaldo: $backupPath"
Write-Output "SHA-256: $($hash.Hash.ToLowerInvariant())"
Write-Output "Bytes: $($backupFile.Length)"
