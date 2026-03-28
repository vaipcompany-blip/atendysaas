param(
    [string]$MysqlPath = '',
    [string]$RootPath = '',
    [string]$DbHost = '',
    [int]$DbPort = 0,
    [string]$DbUser = '',
    [string]$DbPassword = '',
    [string]$BackupFile = '',
    [switch]$KeepTempDatabase
)

$ErrorActionPreference = 'Stop'

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
if ([string]::IsNullOrWhiteSpace($RootPath)) {
    $RootPath = (Resolve-Path (Join-Path $scriptDir '..')).Path
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

if ([string]::IsNullOrWhiteSpace($DbUser)) {
    $DbUser = [string]$env:DB_USERNAME
    if ([string]::IsNullOrWhiteSpace($DbUser)) { $DbUser = 'root' }
}

if ([string]::IsNullOrWhiteSpace($DbPassword)) {
    $DbPassword = [string]$env:DB_PASSWORD
}

if (-not (Test-Path $MysqlPath)) {
    throw "Cliente mysql não encontrado em: $MysqlPath"
}

Set-Location $RootPath

if ([string]::IsNullOrWhiteSpace($BackupFile)) {
    $backupsDir = Join-Path $RootPath 'storage/backups'
    if (-not (Test-Path $backupsDir)) {
        throw 'Diretório de backups não encontrado. Gere um backup antes do drill.'
    }

    $latest = Get-ChildItem -Path $backupsDir -Filter *.sql | Sort-Object LastWriteTime -Descending | Select-Object -First 1
    if ($null -eq $latest) {
        throw 'Nenhum arquivo .sql encontrado em storage/backups.'
    }
    $BackupFile = $latest.FullName
}

if (-not (Test-Path $BackupFile)) {
    throw "Arquivo de backup não encontrado: $BackupFile"
}

$tempDb = 'atendy_restore_drill_' + (Get-Date -Format 'yyyyMMdd_HHmmss')
$summary = [ordered]@{
    generated_at = (Get-Date).ToString('o')
    backup_file = $BackupFile
    temp_database = $tempDb
    import_ok = $false
    sanity = @{}
    dropped_temp_database = $false
    status = 'fail'
}

if (-not [string]::IsNullOrWhiteSpace($DbPassword)) {
    $env:MYSQL_PWD = $DbPassword
}

try {
    & $MysqlPath '-h' $DbHost '-P' "$DbPort" '-u' $DbUser '-e' "CREATE DATABASE IF NOT EXISTS $tempDb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>$null | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw 'Falha ao criar database temporário para drill.'
    }

    $mysqlCmd = '"' + $MysqlPath + '" -h ' + $DbHost + ' -P ' + $DbPort + ' -u ' + $DbUser + ' ' + $tempDb + ' < "' + $BackupFile + '"'
    cmd.exe /c $mysqlCmd | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw 'Falha ao importar backup no database temporário.'
    }

    $summary.import_ok = $true

    $tablesCountRaw = & $MysqlPath '-h' $DbHost '-P' "$DbPort" '-u' $DbUser '-N' '-e' "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='$tempDb';" 2>$null
    $usersExistsRaw = & $MysqlPath '-h' $DbHost '-P' "$DbPort" '-u' $DbUser '-N' '-e' "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='$tempDb' AND TABLE_NAME='users';" 2>$null
    $migrationsCountRaw = & $MysqlPath '-h' $DbHost '-P' "$DbPort" '-u' $DbUser '-N' '-e' "SELECT COUNT(*) FROM $tempDb.schema_migrations;" 2>$null

    $tablesCount = [int]([string]$tablesCountRaw).Trim()
    $usersExists = [int]([string]$usersExistsRaw).Trim()
    $migrationsCount = [int]([string]$migrationsCountRaw).Trim()

    $summary.sanity = [ordered]@{
        tables_count = $tablesCount
        users_table_exists = ($usersExists -ge 1)
        schema_migrations_count = $migrationsCount
    }

    if (($tablesCount -gt 0) -and ($usersExists -ge 1) -and ($migrationsCount -ge 1)) {
        $summary.status = 'ok'
    } else {
        $summary.status = 'fail'
    }
}
catch {
    $summary.error = $_.Exception.Message
    $summary.status = 'fail'
}
finally {
    if (-not [string]::IsNullOrWhiteSpace($DbPassword)) {
        Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue
    }

    if (-not $KeepTempDatabase) {
        & $MysqlPath '-h' $DbHost '-P' "$DbPort" '-u' $DbUser '-e' "DROP DATABASE IF EXISTS $tempDb;" 2>$null | Out-Null
        $summary.dropped_temp_database = ($LASTEXITCODE -eq 0)
    }
}

$summary | ConvertTo-Json -Depth 6

if ($summary.status -ne 'ok') {
    exit 2
}

exit 0


