[CmdletBinding()]
param(
    [switch]$KeepContainers,
    [switch]$RunPerformance
)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

$projectRoot = Split-Path -Parent $PSScriptRoot
$composeFile = Join-Path $projectRoot 'compose.integration.yaml'
$baseUrl = 'http://127.0.0.1:8080'
$completed = $false

function New-RandomBase64Secret {
    param([int]$ByteCount = 32)

    $bytes = New-Object byte[] $ByteCount
    $generator = [System.Security.Cryptography.RandomNumberGenerator]::Create()

    try {
        $generator.GetBytes($bytes)

        return [Convert]::ToBase64String($bytes)
    }
    finally {
        $generator.Dispose()
    }
}

function Invoke-Compose {
    param([Parameter(Mandatory)][string[]]$Arguments)

    & docker compose --file $composeFile @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "Docker Compose terminó con código $LASTEXITCODE."
    }
}

function Assert-OkResponse {
    param(
        [Parameter(Mandatory)][string]$Path,
        [Microsoft.PowerShell.Commands.WebRequestSession]$Session
    )

    $parameters = @{
        Uri = $baseUrl + $Path
        UseBasicParsing = $true
        TimeoutSec = 30
    }
    if ($null -ne $Session) {
        $parameters.WebSession = $Session
    }

    $response = Invoke-WebRequest @parameters
    if ($response.StatusCode -ne 200) {
        throw "La ruta $Path respondió HTTP $($response.StatusCode)."
    }

    return $response
}

$appKey = 'base64:' + (New-RandomBase64Secret)
$databasePassword = New-RandomBase64Secret 24
$rootPassword = New-RandomBase64Secret 24
$demoPassword = New-RandomBase64Secret 24

$env:LUZPARRAL_INTEGRATION_APP_KEY = $appKey
$env:LUZPARRAL_INTEGRATION_DB_PASSWORD = $databasePassword
$env:LUZPARRAL_INTEGRATION_ROOT_PASSWORD = $rootPassword

try {
    Push-Location $projectRoot

    Write-Host '[1/7] Comprobando Docker y limpiando el banco de prueba anterior...'
    & docker version --format '{{.Server.Version}}' | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw 'Docker Desktop no está disponible.'
    }
    Invoke-Compose @('down', '--volumes', '--remove-orphans')

    Write-Host '[2/7] Construyendo la imagen e iniciando aplicación y MySQL...'
    Invoke-Compose @('up', '--build', '--detach', '--wait', '--wait-timeout', '240')

    Write-Host '[3/7] Creando el esquema Laravel...'
    Invoke-Compose @('exec', '-T', 'app', 'php', 'artisan', 'migrate', '--force', '--no-interaction')

    Write-Host '[4/7] Generando exclusivamente datos sintéticos...'
    Invoke-Compose @(
        'exec', '-T',
        '-e', 'LUZPARRAL_DB_HOST=db',
        '-e', 'LUZPARRAL_DB_PORT=3306',
        '-e', 'LUZPARRAL_DB_DATABASE=luzparral',
        '-e', 'LUZPARRAL_DB_ALLOWED_DATABASE=luzparral',
        '-e', 'LUZPARRAL_DB_USERNAME=luzparral_app',
        '-e', "LUZPARRAL_DB_PASSWORD=$databasePassword",
        '-e', "LUZPARRAL_DEMO_PASSWORD=$demoPassword",
        'app', 'php', 'database/synthetic/generate_synthetic.php'
    )

    Write-Host '[5/7] Validando conexión, esquema e integridad del conjunto...'
    Invoke-Compose @('exec', '-T', 'app', 'php', 'artisan', 'luzparral:health', '--database', '--json', '--no-interaction')
    Invoke-Compose @('exec', '-T', 'app', 'php', 'artisan', 'luzparral:validate-synthetic', '--require-runtime', '--no-interaction')

    Write-Host '[6/7] Probando autenticación y módulos mediante HTTP...'
    $loginPage = Invoke-WebRequest -Uri "$baseUrl/login" -SessionVariable webSession -UseBasicParsing -TimeoutSec 30
    if ($loginPage.StatusCode -ne 200) {
        throw "La pantalla de acceso respondió HTTP $($loginPage.StatusCode)."
    }

    $xsrfCookie = $webSession.Cookies.GetCookies([Uri]$baseUrl) |
        Where-Object Name -eq 'XSRF-TOKEN' |
        Select-Object -First 1
    if ($null -eq $xsrfCookie) {
        throw 'La aplicación no entregó la cookie de protección CSRF.'
    }

    $loginResponse = Invoke-WebRequest `
        -Uri "$baseUrl/login" `
        -Method Post `
        -WebSession $webSession `
        -Headers @{
            'X-XSRF-TOKEN' = [Uri]::UnescapeDataString($xsrfCookie.Value)
            'X-Requested-With' = 'XMLHttpRequest'
        } `
        -Body @{
            email = 'admin@luzparral.example.invalid'
            password = $demoPassword
        } `
        -UseBasicParsing `
        -TimeoutSec 30

    if ($loginResponse.StatusCode -ne 200) {
        throw "El inicio de sesión no terminó en una respuesta HTTP 200."
    }

    foreach ($path in @(
        '/dashboard',
        '/contingencias/mapa',
        '/pronostico-meteorologico',
        '/buscador-operacional',
        '/contingencias/1',
        '/informes',
        '/importaciones',
        '/importaciones/plantilla'
    )) {
        $response = Assert-OkResponse -Path $path -Session $webSession
        if ($response.Headers['X-Content-Type-Options'] -ne 'nosniff') {
            throw "La ruta $path no incluyó los encabezados de seguridad esperados."
        }
    }

    Write-Host '[7/7] Probando exportaciones CSV y PDF...'
    $csv = Assert-OkResponse -Path '/informes/contingencias.csv?range=12m' -Session $webSession
    if ($csv.RawContentLength -lt 100 -or $csv.Headers['Content-Type'] -notmatch 'text/csv') {
        throw 'La exportación CSV no entregó un archivo válido.'
    }

    $pdf = Assert-OkResponse -Path '/informes/contingencias.pdf?range=12m&report_type=executive' -Session $webSession
    if ($pdf.RawContentLength -lt 1000 -or $pdf.Headers['Content-Type'] -notmatch 'application/pdf') {
        throw 'La exportación PDF no entregó un archivo válido.'
    }

    if ($RunPerformance) {
        Write-Host '[Rendimiento] Probando 5 y 10 sesiones concurrentes...'
        $env:LUZPARRAL_PERFORMANCE_PASSWORD = $demoPassword
        try {
            & (Join-Path $PSScriptRoot 'MEDIR_RENDIMIENTO_LOCAL.ps1') -BaseUrl $baseUrl
            if ($LASTEXITCODE -ne 0) {
                throw "La medición de rendimiento terminó con código $LASTEXITCODE."
            }
        }
        finally {
            Remove-Item Env:LUZPARRAL_PERFORMANCE_PASSWORD -ErrorAction SilentlyContinue
        }
    }

    $completed = $true
    Write-Host 'VALIDACIÓN INTEGRAL LOCAL: APROBADA'
}
finally {
    if ($completed -and -not $KeepContainers) {
        Write-Host 'Eliminando contenedores y volumen sintético de integración...'
        Invoke-Compose @('down', '--volumes', '--remove-orphans')
    }
    elseif (-not $completed) {
        Write-Warning 'La prueba falló. Los contenedores se conservaron para revisar sus logs.'
    }
    elseif ($KeepContainers) {
        Write-Host "Contenedores conservados temporalmente en $baseUrl."
    }

    Remove-Item Env:LUZPARRAL_INTEGRATION_APP_KEY -ErrorAction SilentlyContinue
    Remove-Item Env:LUZPARRAL_INTEGRATION_DB_PASSWORD -ErrorAction SilentlyContinue
    Remove-Item Env:LUZPARRAL_INTEGRATION_ROOT_PASSWORD -ErrorAction SilentlyContinue

    if ((Get-Location).Path -eq $projectRoot) {
        Pop-Location
    }
}
