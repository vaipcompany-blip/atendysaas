param(
    [string]$BaseUrl = 'http://localhost/Aula-SQL/public/index.php',
    [string]$PhpPath = '',
    [string]$RootPath = ''
)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
if ([string]::IsNullOrWhiteSpace($RootPath)) {
    $RootPath = (Resolve-Path (Join-Path $scriptDir '..')).Path
}

if ([string]::IsNullOrWhiteSpace($PhpPath)) {
    if (-not [string]::IsNullOrWhiteSpace($env:PHP_BIN)) {
        $PhpPath = $env:PHP_BIN
    } else {
        $phpCmd = Get-Command php -ErrorAction SilentlyContinue
        if ($null -ne $phpCmd) {
            $PhpPath = $phpCmd.Source
        } else {
            $PhpPath = 'C:\xampp\php\php.exe'
        }
    }
}

if (-not (Test-Path $PhpPath)) {
    throw "PHP binary não encontrado em: $PhpPath"
}

Set-Location $RootPath

function Add-Result([System.Collections.Generic.List[object]]$results, [string]$name, [bool]$ok, [string]$detail='') {
    $results.Add([pscustomobject]@{
        check = $name
        ok = $ok
        detail = $detail
    }) | Out-Null
}

$results = New-Object System.Collections.Generic.List[object]

try {
    $healthResp = Invoke-WebRequest -Uri ($BaseUrl + '?route=health') -UseBasicParsing
    $healthJson = $healthResp.Content | ConvertFrom-Json
    $healthOk = ($healthResp.StatusCode -eq 200) -and (($healthJson.status -eq 'ok') -or ($healthJson.status -eq 'warn'))
    Add-Result $results 'health_endpoint' $healthOk "status=$($healthResp.StatusCode);overall=$($healthJson.status)"
} catch {
    Add-Result $results 'health_endpoint' $false $_.Exception.Message
}

try {
    $healthCliRaw = & $PhpPath 'scripts/healthcheck.php'
    $healthCliJson = $healthCliRaw | ConvertFrom-Json
    $healthCliOk = ($LASTEXITCODE -eq 0) -and (($healthCliJson.status -eq 'ok') -or ($healthCliJson.status -eq 'warn'))
    Add-Result $results 'healthcheck_cli' $healthCliOk "status=$($healthCliJson.status)"
} catch {
    Add-Result $results 'healthcheck_cli' $false $_.Exception.Message
}

try {
    $gateRaw = powershell -ExecutionPolicy Bypass -File scripts/launch_gate.ps1 -RootPath $RootPath -PhpPath $PhpPath -BaseUrl $BaseUrl -SkipE2E -SkipRestoreDrill
    $gateJson = $gateRaw | ConvertFrom-Json
    $gateOk = ($LASTEXITCODE -eq 0) -and ([string]$gateJson.gate_status -eq 'go')
    Add-Result $results 'launch_gate_fast' $gateOk "status=$($gateJson.gate_status);fails=$($gateJson.failed_checks)"
} catch {
    Add-Result $results 'launch_gate_fast' $false $_.Exception.Message
}

$failed = ($results | Where-Object { -not $_.ok }).Count
$summary = [pscustomobject]@{
    generated_at = (Get-Date).ToString('o')
    status = if ($failed -eq 0) { 'ok' } else { 'fail' }
    fail_count = $failed
    checks = $results
}

$summary | ConvertTo-Json -Depth 6

if ($failed -gt 0) {
    exit 2
}

exit 0

