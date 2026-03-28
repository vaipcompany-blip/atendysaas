param(
    [string]$PhpPath = '',
    [string]$RootPath = ''
)

$ErrorActionPreference = 'Stop'

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

$results = New-Object System.Collections.Generic.List[object]
function Add-Check([string]$name, [bool]$ok, [string]$detail='') {
    $results.Add([pscustomobject]@{
        check = $name
        ok = $ok
        detail = $detail
    }) | Out-Null
}

try {
    & $PhpPath 'scripts/migrate.php' '--quiet' 2>$null | Out-Null
    Add-Check 'migrate_quiet' ($LASTEXITCODE -eq 0) "exit=$LASTEXITCODE"

    $healthRaw = & $PhpPath 'scripts/healthcheck.php'
    $health = $healthRaw | ConvertFrom-Json
    $healthOk = (($LASTEXITCODE -eq 0) -and (($health.status -eq 'ok') -or ($health.status -eq 'warn')))
    Add-Check 'healthcheck_cli' $healthOk "status=$($health.status)"

    $pendingMigrations = [int]($health.checks.migrations.pending_count)
    Add-Check 'migrations_pending_zero' ($pendingMigrations -eq 0) "pending=$pendingMigrations"

    $backupRaw = & $PhpPath 'scripts/backup.php'
    $backup = $backupRaw | ConvertFrom-Json
    $backupOk = ($LASTEXITCODE -eq 0) -and ([string]$backup.file_name -match '\.sql$') -and (([int]$backup.size) -gt 0)
    Add-Check 'backup_generated' $backupOk "file=$($backup.file_name);size=$($backup.size)"

    $cronOutput = & $PhpPath 'scripts/cron.php' '--dry-run'
    $cronText = [string]($cronOutput -join "`n")
    $cronOk = ($LASTEXITCODE -eq 0) -and ($cronText -match 'MODO DRY-RUN|dry-run') -and ($cronText -match 'CONCLU')
    Add-Check 'cron_dry_run' $cronOk "exit=$LASTEXITCODE"
}
catch {
    Add-Check 'deploy_validate_exception' $false $_.Exception.Message
}

$failCount = ($results | Where-Object { -not $_.ok }).Count
$summary = [pscustomobject]@{
    generated_at = (Get-Date).ToString('o')
    checks = $results
    fail_count = $failCount
    status = if ($failCount -eq 0) { 'ok' } else { 'fail' }
}

$summary | ConvertTo-Json -Depth 6

if ($failCount -gt 0) {
    exit 2
}

exit 0

