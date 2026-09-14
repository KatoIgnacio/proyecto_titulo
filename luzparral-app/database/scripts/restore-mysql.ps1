[CmdletBinding()]
param(
    [Parameter(Mandatory)]
    [string] $BackupPath,

    [Parameter(Mandatory)]
    [ValidatePattern('^[A-Za-z0-9_][A-Za-z0-9_$-]{0,63}$')]
    [string] $Database,

    [Parameter(Mandatory)]
    [switch] $ConfirmEmptyTarget,

    [ValidatePattern('^[A-Za-z0-9_.-]+$')]
    [string] $HostName = '127.0.0.1',

    [ValidateRange(1, 65535)]
    [int] $Port = 3306,

    [ValidatePattern('^[A-Za-z0-9_.-]+$')]
    [string] $Username = 'luzparral_app',

    [string] $MySqlPath = ''
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Resolve-MySqlTool {
    param(
        [string] $ExplicitPath
    )

    if (-not [string]::IsNullOrWhiteSpace($ExplicitPath)) {
        if (-not (Test-Path -LiteralPath $ExplicitPath -PathType Leaf)) {
            throw "No existe $ExplicitPath"
        }

        return (Resolve-Path -LiteralPath $ExplicitPath).Path
    }

    $command = Get-Command mysql -ErrorAction SilentlyContinue
    if ($null -ne $command) {
        return $command.Source
    }

    $standardPath = 'C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe'
    if (Test-Path -LiteralPath $standardPath -PathType Leaf) {
        return $standardPath
    }

    throw 'mysql no esta disponible. Instale MySQL Client o indique su ruta explicitamente.'
}

if (-not $ConfirmEmptyTarget) {
    throw 'La restauracion requiere -ConfirmEmptyTarget.'
}

$allowedDatabase = $env:LUZPARRAL_DB_ALLOWED_DATABASE
$password = $env:LUZPARRAL_DB_PASSWORD

if ([string]::IsNullOrWhiteSpace($allowedDatabase) -or $allowedDatabase -ne $Database) {
    throw 'LUZPARRAL_DB_ALLOWED_DATABASE debe coincidir exactamente con -Database.'
}

if ([string]::IsNullOrWhiteSpace($password)) {
    throw 'Defina LUZPARRAL_DB_PASSWORD en la sesion actual.'
}

$resolvedBackupPath = (Resolve-Path -LiteralPath $BackupPath).Path
$checksumPath = "$resolvedBackupPath.sha256"
if (-not (Test-Path -LiteralPath $checksumPath -PathType Leaf)) {
    throw "Falta el archivo de integridad $checksumPath"
}

$checksumContents = (Get-Content -Raw -LiteralPath $checksumPath).Trim()
if ($checksumContents -notmatch '^([a-fA-F0-9]{64})\s{2}(.+)$') {
    throw 'El archivo SHA-256 no tiene el formato esperado.'
}

$expectedHash = $Matches[1].ToLowerInvariant()
$expectedFileName = $Matches[2]
$backupFile = Get-Item -LiteralPath $resolvedBackupPath
if ($expectedFileName -ne $backupFile.Name) {
    throw 'El archivo indicado no coincide con el nombre registrado en su SHA-256.'
}

$actualHash = (Get-FileHash -Algorithm SHA256 -LiteralPath $resolvedBackupPath).Hash.ToLowerInvariant()
if ($actualHash -ne $expectedHash) {
    throw 'El respaldo no supero la comprobacion SHA-256.'
}

$mysqlBinary = Resolve-MySqlTool -ExplicitPath $MySqlPath
$baseArguments = @(
    "--host=$HostName",
    "--port=$Port",
    "--user=$Username",
    '--batch',
    '--skip-column-names',
    '--default-character-set=utf8mb4'
)
$previousPassword = [Environment]::GetEnvironmentVariable('MYSQL_PWD', 'Process')

try {
    [Environment]::SetEnvironmentVariable('MYSQL_PWD', $password, 'Process')

    $databaseExists = (& $mysqlBinary @baseArguments "--execute=SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '$Database';" | Out-String).Trim()
    if ($LASTEXITCODE -ne 0 -or $databaseExists -ne '1') {
        throw "La base destino $Database no existe o no es accesible."
    }

    $tableCount = (& $mysqlBinary @baseArguments "--execute=SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$Database';" | Out-String).Trim()
    if ($LASTEXITCODE -ne 0) {
        throw 'No fue posible inspeccionar la base destino.'
    }

    if ([int] $tableCount -ne 0) {
        throw "La restauracion solo se permite sobre una base vacia; se encontraron $tableCount tablas."
    }

    $startInfo = [System.Diagnostics.ProcessStartInfo]::new()
    $startInfo.FileName = $mysqlBinary
    $startInfo.Arguments = (@(
        "--host=$HostName",
        "--port=$Port",
        "--user=$Username",
        '--default-character-set=utf8mb4',
        '--binary-mode',
        $Database
    ) -join ' ')
    $startInfo.UseShellExecute = $false
    $startInfo.CreateNoWindow = $true
    $startInfo.RedirectStandardInput = $true
    $startInfo.RedirectStandardError = $true
    $startInfo.EnvironmentVariables['MYSQL_PWD'] = $password

    $process = [System.Diagnostics.Process]::new()
    $process.StartInfo = $startInfo
    if (-not $process.Start()) {
        throw 'No fue posible iniciar mysql para restaurar el respaldo.'
    }

    $errorTask = $process.StandardError.ReadToEndAsync()
    $backupStream = [System.IO.File]::OpenRead($resolvedBackupPath)
    try {
        $backupStream.CopyTo($process.StandardInput.BaseStream)
        $process.StandardInput.Close()
    }
    finally {
        $backupStream.Dispose()
    }

    $process.WaitForExit()
    $errorOutput = $errorTask.GetAwaiter().GetResult()
    if ($process.ExitCode -ne 0) {
        throw "mysql termino con codigo $($process.ExitCode): $errorOutput"
    }

    $restoredTableCount = (& $mysqlBinary @baseArguments "--execute=SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$Database';" | Out-String).Trim()
    if ($LASTEXITCODE -ne 0 -or [int] $restoredTableCount -eq 0) {
        throw 'La restauracion termino sin tablas verificables.'
    }
}
finally {
    [Environment]::SetEnvironmentVariable('MYSQL_PWD', $previousPassword, 'Process')
}

Write-Output "Restauracion completada en la base vacia: $Database"
Write-Output "SHA-256 verificado: $actualHash"
Write-Output "Tablas restauradas: $restoredTableCount"
