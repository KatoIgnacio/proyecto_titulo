[CmdletBinding()]
param(
    [string]$BaseUrl = 'http://127.0.0.1:8080',
    [ValidateRange(1, 10)]
    [int]$RequestsPerUser = 2,
    [string]$OutputPath
)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

if ($PSVersionTable.PSVersion.Major -lt 7) {
    throw 'La medición concurrente requiere PowerShell 7 o superior.'
}

$password = [Environment]::GetEnvironmentVariable('LUZPARRAL_PERFORMANCE_PASSWORD')
if ([string]::IsNullOrWhiteSpace($password)) {
    throw 'Defina LUZPARRAL_PERFORMANCE_PASSWORD únicamente durante la ejecución de la prueba.'
}

$email = [Environment]::GetEnvironmentVariable('LUZPARRAL_PERFORMANCE_EMAIL')
if ([string]::IsNullOrWhiteSpace($email)) {
    $email = 'admin@luzparral.example.invalid'
}

$projectRoot = Split-Path -Parent $PSScriptRoot
if ([string]::IsNullOrWhiteSpace($OutputPath)) {
    $OutputPath = Join-Path $projectRoot 'storage/app/quality/segment-13-performance.json'
}

$scenarios = @(
    [pscustomobject]@{ Name = 'Dashboard'; Method = 'GET'; Path = '/dashboard?range=all'; P95LimitMs = 3000; MaximumUsers = 30 },
    [pscustomobject]@{ Name = 'Mapa'; Method = 'GET'; Path = '/contingencias/mapa?range=all&status=active'; P95LimitMs = 3000; MaximumUsers = 30 },
    [pscustomobject]@{ Name = 'Búsqueda'; Method = 'GET'; Path = '/buscador-operacional?category=code&query=SYN-CONT'; P95LimitMs = 3000; MaximumUsers = 30 },
    [pscustomobject]@{ Name = 'Registro terreno'; Method = 'POST'; Path = '/contingencias/1/antecedentes-terreno'; P95LimitMs = 3000; MaximumUsers = 30 },
    [pscustomobject]@{ Name = 'Pronóstico'; Method = 'GET'; Path = '/pronostico-meteorologico'; P95LimitMs = 3000; MaximumUsers = 30 },
    [pscustomobject]@{ Name = 'Informes'; Method = 'GET'; Path = '/informes?range=all'; P95LimitMs = 3000; MaximumUsers = 30 },
    [pscustomobject]@{ Name = 'CSV'; Method = 'GET'; Path = '/informes/contingencias.csv?range=all'; P95LimitMs = 5000; MaximumUsers = 10 },
    [pscustomobject]@{ Name = 'PDF ejecutivo'; Method = 'GET'; Path = '/informes/contingencias.pdf?range=all&report_type=executive'; P95LimitMs = 12000; MaximumUsers = 10 }
)

$summaries = @()

foreach ($concurrentUsers in @(5, 10, 30)) {
    foreach ($scenario in $scenarios) {
        if ($concurrentUsers -gt $scenario.MaximumUsers) {
            continue
        }

        $path = $scenario.Path
        $method = $scenario.Method
        $results = 1..$concurrentUsers | ForEach-Object -Parallel {
            $workerId = $_
            $session = [Microsoft.PowerShell.Commands.WebRequestSession]::new()

            try {
                $loginPage = Invoke-WebRequest `
                    -Uri "$using:BaseUrl/login" `
                    -WebSession $session `
                    -UseBasicParsing `
                    -TimeoutSec 30
                $xsrfCookie = $session.Cookies.GetCookies([Uri]$using:BaseUrl) |
                    Where-Object Name -eq 'XSRF-TOKEN' |
                    Select-Object -First 1

                if ($null -eq $xsrfCookie) {
                    throw 'La aplicación no entregó la cookie CSRF.'
                }

                Invoke-WebRequest `
                    -Uri "$using:BaseUrl/login" `
                    -Method Post `
                    -WebSession $session `
                    -Headers @{
                        'X-XSRF-TOKEN' = [Uri]::UnescapeDataString($xsrfCookie.Value)
                        'X-Requested-With' = 'XMLHttpRequest'
                    } `
                    -Body @{
                        email = $using:email
                        password = $using:password
                    } `
                    -UseBasicParsing `
                    -TimeoutSec 30 | Out-Null

                $xsrfCookie = $session.Cookies.GetCookies([Uri]$using:BaseUrl) |
                    Where-Object Name -eq 'XSRF-TOKEN' |
                    Select-Object -First 1
                if ($null -eq $xsrfCookie) {
                    throw 'La sesión autenticada no conservó la cookie CSRF.'
                }

                foreach ($iteration in 0..$using:RequestsPerUser) {
                    $stopwatch = [System.Diagnostics.Stopwatch]::StartNew()
                    try {
                        $requestParameters = @{
                            Uri = $using:BaseUrl + $using:path
                            Method = $using:method
                            WebSession = $session
                            UseBasicParsing = $true
                            TimeoutSec = 30
                        }
                        if ($using:method -eq 'POST') {
                            $requestParameters.Headers = @{
                                'X-XSRF-TOKEN' = [Uri]::UnescapeDataString($xsrfCookie.Value)
                                'X-Requested-With' = 'XMLHttpRequest'
                                'Referer' = $using:BaseUrl + '/contingencias/1'
                            }
                            $requestParameters.Body = @{
                                progress_status = 'inspection'
                                description = "Registro sintético RNF03 trabajador $workerId intento $iteration"
                                observed_at = [DateTimeOffset]::Now.ToString('yyyy-MM-dd HH:mm:ss')
                            }
                        }

                        $response = Invoke-WebRequest @requestParameters
                        $stopwatch.Stop()

                        if ($iteration -gt 0) {
                            [pscustomobject]@{
                                Worker = $workerId
                                Iteration = $iteration
                                Success = $response.StatusCode -eq 200
                                StatusCode = $response.StatusCode
                                DurationMs = [Math]::Round($stopwatch.Elapsed.TotalMilliseconds, 2)
                                Bytes = $response.RawContentLength
                                Error = $null
                            }
                        }
                    }
                    catch {
                        $stopwatch.Stop()
                        if ($iteration -eq 0) {
                            throw 'Calentamiento fallido: ' + $_.Exception.Message
                        }

                        [pscustomobject]@{
                            Worker = $workerId
                            Iteration = $iteration
                            Success = $false
                            StatusCode = 0
                            DurationMs = [Math]::Round($stopwatch.Elapsed.TotalMilliseconds, 2)
                            Bytes = 0
                            Error = $_.Exception.Message
                        }
                    }
                }
            }
            catch {
                [pscustomobject]@{
                    Worker = $workerId
                    Iteration = 0
                    Success = $false
                    StatusCode = 0
                    DurationMs = 0
                    Bytes = 0
                    Error = 'Autenticación fallida: ' + $_.Exception.Message
                }
            }
        } -ThrottleLimit $concurrentUsers

        $successes = @($results | Where-Object Success)
        $failures = @($results | Where-Object { -not $_.Success })
        $errorSamples = @($failures | ForEach-Object Error | Where-Object { -not [string]::IsNullOrWhiteSpace($_) } | Select-Object -Unique -First 3)
        $orderedTimes = @($successes | ForEach-Object DurationMs | Sort-Object)
        $p95 = if ($orderedTimes.Count -gt 0) {
            $orderedTimes[[Math]::Max(0, [Math]::Ceiling($orderedTimes.Count * 0.95) - 1)]
        }
        else {
            0
        }
        $average = if ($orderedTimes.Count -gt 0) {
            [Math]::Round(($orderedTimes | Measure-Object -Average).Average, 2)
        }
        else {
            0
        }
        $maximum = if ($orderedTimes.Count -gt 0) {
            [Math]::Round(($orderedTimes | Measure-Object -Maximum).Maximum, 2)
        }
        else {
            0
        }

        $summaries += [pscustomobject]@{
            concurrent_users = $concurrentUsers
            scenario = $scenario.Name
            method = $scenario.Method
            measured_requests = $results.Count
            failures = $failures.Count
            average_ms = $average
            p95_ms = [Math]::Round($p95, 2)
            maximum_ms = $maximum
            p95_limit_ms = $scenario.P95LimitMs
            error_samples = $errorSamples
            passed = $failures.Count -eq 0 -and $p95 -le $scenario.P95LimitMs
        }

        if ($errorSamples.Count -gt 0) {
            Write-Warning "$($scenario.Name) con $concurrentUsers sesiones: $($errorSamples -join ' | ')"
        }
    }
}

$report = [ordered]@{
    generated_at = [DateTimeOffset]::Now.ToString('o')
    base_url = $BaseUrl
    requests_per_user = $RequestsPerUser
    synthetic_dataset = $true
    criteria = [ordered]@{
        maximum_concurrent_users = 30
        allowed_http_failures = 0
        statistic = 'percentil 95 de solicitudes medidas; calentamiento excluido'
        rnf01_limit_ms = 3000
        heavy_exports_maximum_users = 10
    }
    results = $summaries
    passed = @($summaries | Where-Object { -not $_.passed }).Count -eq 0
}

$outputDirectory = Split-Path -Parent $OutputPath
New-Item -ItemType Directory -Path $outputDirectory -Force | Out-Null
$report | ConvertTo-Json -Depth 6 | Set-Content -LiteralPath $OutputPath -Encoding utf8NoBOM

$summaries | Format-Table concurrent_users, scenario, measured_requests, failures, average_ms, p95_ms, p95_limit_ms, passed -AutoSize
Write-Host "Resultado guardado en $OutputPath"

if (-not $report.passed) {
    throw 'La aplicación no cumplió los criterios locales de rendimiento.'
}

Write-Host 'PRUEBA LOCAL DE RENDIMIENTO: APROBADA'
