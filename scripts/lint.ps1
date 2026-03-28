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

Get-ChildItem -Path (Join-Path $RootPath 'public'), (Join-Path $RootPath 'src') -Recurse -Filter *.php |
    ForEach-Object {
        & $PhpPath -l $_.FullName
    }

