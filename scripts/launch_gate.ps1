param(
    [string]$RootPath = '',
    [string]$PhpPath = '',
    [string]$MysqlPath = '',
    [string]$BaseUrl = 'http://localhost/Aula-SQL/public/index.php',
    [string]$DbHost = '',
    [int]$DbPort = 0,
    [string]$DbName = '',
    [string]$DbUser = '',
    [string]$DbPassword = '',
    [switch]$SkipE2E,
    [switch]$SkipRestoreDrill
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

if ([string]::IsNullOrWhiteSpace($MysqlPath)) {
    if (-not [string]::IsNullOrWhiteSpace($env:MYSQL_BIN)) {
        $MysqlPath = $env:MYSQL_BIN
    } else {
        $mysqlCmd = Get-Command mysql -ErrorAction SilentlyContinue
        if ($null -ne $mysqlCmd) {
            $MysqlPath = $mysqlCmd.Source
        } else {
            $MysqlPath = 'C:\xampp\mysql\bin\mysql.exe'
        }
    }
}

if ([string]::IsNullOrWhiteSpace($DbHost)) {
    $DbHost = [string]$env:DB_HOST
    if ([string]::IsNullOrWhiteSpace($DbHost)) { $DbHost = '127.0.0.1' }
}

if ($DbPort -le 0) {
    $DbPort = 3306
    if (-not [string]::IsNullOrWhiteSpace($env:DB_PORT)) {
        $DbPort = [int]$env:DB_PORT
    }
}

if ([string]::IsNullOrWhiteSpace($DbName)) {
    $DbName = [string]$env:DB_DATABASE
    if ([string]::IsNullOrWhiteSpace($DbName)) { $DbName = 'atendy' }
}

if ([string]::IsNullOrWhiteSpace($DbUser)) {
    $DbUser = [string]$env:DB_USERNAME
    if ([string]::IsNullOrWhiteSpace($DbUser)) { $DbUser = 'root' }
}

if ([string]::IsNullOrWhiteSpace($DbPassword)) {
    $DbPassword = [string]$env:DB_PASSWORD
}

Set-Location $RootPath

$checks = New-Object System.Collections.Generic.List[object]

function Add-Check([string]$name, [bool]$ok, [int]$exitCode, [string]$summary, [string]$output) {
    $checks.Add([pscustomobject]@{
        check = $name
        ok = $ok
        exit_code = $exitCode
        summary = $summary
        output = $output
    }) | Out-Null
}

function Run-Command([string]$name, [scriptblock]$command, [scriptblock]$validator) {
    $text = ''
    $exitCode = 0
    try {
        $raw = & $command 2>&1
        $text = [string]($raw -join "`n")
        $exitCode = $LASTEXITCODE
    } catch {
        $text = $_.Exception.Message
        $exitCode = 1
    }

    $ok = $false
    $summary = 'falhou'
    try {
        $result = & $validator $text $exitCode
        $ok = [bool]($result.ok)
        $summary = [string]($result.summary)
    } catch {
        $ok = $false
        $summary = 'validação interna falhou'
        $text += "`nvalidator_error=" + $_.Exception.Message
    }

    Add-Check $name $ok $exitCode $summary $text
}

Run-Command 'lint_ps1' {
    powershell -ExecutionPolicy Bypass -File scripts/lint.ps1 -PhpPath $PhpPath -RootPath $RootPath
} {
    param($output, $exitCode)
    [pscustomobject]@{ ok = ($exitCode -eq 0 -and $output -match 'No syntax errors detected'); summary = 'lint concluído' }
}

Run-Command 'deploy_validate' {
    powershell -ExecutionPolicy Bypass -File scripts/deploy_validate.ps1 -PhpPath $PhpPath -RootPath $RootPath
} {
    param($output, $exitCode)
    $ok = $false
    $summary = 'falha no checklist pós-deploy'
    try {
        $json = $output | ConvertFrom-Json
        $ok = ($exitCode -eq 0) -and ([string]$json.status -eq 'ok')
        $summary = 'status=' + [string]$json.status + ';fails=' + [string]$json.fail_count
    } catch {
        $ok = ($exitCode -eq 0)
    }
    [pscustomobject]@{ ok = $ok; summary = $summary }
}

if (-not $SkipRestoreDrill) {
    Run-Command 'restore_drill' {
        $args = @(
            '-ExecutionPolicy', 'Bypass', '-File', 'scripts/restore_drill.ps1',
            '-MysqlPath', $MysqlPath,
            '-RootPath', $RootPath,
            '-DbHost', $DbHost,
            '-DbPort', $DbPort,
            '-DbName', $DbName,
            '-DbUser', $DbUser
        )

        if (-not [string]::IsNullOrWhiteSpace($DbPassword)) {
            $args += @('-DbPassword', $DbPassword)
        }

        powershell @args
    } {
        param($output, $exitCode)
        $ok = $false
        $summary = 'drill de restore falhou'
        try {
            $json = $output | ConvertFrom-Json
            $ok = ($exitCode -eq 0) -and ([string]$json.status -eq 'ok')
            $summary = 'status=' + [string]$json.status + ';tables=' + [string]$json.sanity.tables_count
        } catch {
            $ok = ($exitCode -eq 0)
        }
        [pscustomobject]@{ ok = $ok; summary = $summary }
    }
}

if (-not $SkipE2E) {
    Run-Command 'e2e_validate' {
        $args = @(
            '-ExecutionPolicy', 'Bypass', '-File', 'scripts/e2e_validate.ps1',
            '-BaseUrl', $BaseUrl,
            '-PhpPath', $PhpPath,
            '-MysqlPath', $MysqlPath,
            '-DbHost', $DbHost,
            '-DbPort', $DbPort,
            '-DbName', $DbName,
            '-DbUser', $DbUser
        )

        if (-not [string]::IsNullOrWhiteSpace($DbPassword)) {
            $args += @('-DbPassword', $DbPassword)
        }

        powershell @args
    } {
        param($output, $exitCode)
        $ok = ($exitCode -eq 0) -and ($output -match 'SUMMARY: total=') -and ($output -match 'fail=0')
        $summary = 'E2E concluído'
        if ($output -match 'SUMMARY: total=\d+ fail=\d+') {
            $summary = $Matches[0]
        }
        [pscustomobject]@{ ok = $ok; summary = $summary }
    }
}

$failed = ($checks | Where-Object { -not $_.ok }).Count
$gateStatus = if ($failed -eq 0) { 'go' } else { 'no-go' }

$report = [pscustomobject]@{
    generated_at = (Get-Date).ToString('o')
    gate_status = $gateStatus
    failed_checks = $failed
    checks = $checks
}

$report | ConvertTo-Json -Depth 8

if ($failed -gt 0) {
    exit 2
}

exit 0


