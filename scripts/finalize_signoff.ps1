param(
    [string]$RootPath = '',
    [string]$TechnicalOwner = '',
    [string]$BusinessOwner = '',
    [string]$ApprovedBy = '',
    [switch]$AnnouncementSent,
    [string]$ReviewWindow = '',
    [switch]$Apply
)

$ErrorActionPreference = 'Stop'

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
if ([string]::IsNullOrWhiteSpace($RootPath)) {
    $RootPath = (Resolve-Path (Join-Path $scriptDir '..')).Path
}

$checklistPath = Join-Path $RootPath 'GO_LIVE_CHECKLIST.md'
if (-not (Test-Path $checklistPath)) {
    throw "Checklist não encontrado: $checklistPath"
}

$content = Get-Content -Path $checklistPath -Raw -Encoding UTF8

if (-not [string]::IsNullOrWhiteSpace($TechnicalOwner)) {
    $content = $content -replace 'Responsável técnico: Pendente preenchimento', ('Responsável técnico: ' + $TechnicalOwner)
}

if (-not [string]::IsNullOrWhiteSpace($BusinessOwner)) {
    $content = $content -replace 'Responsável negócio: Pendente preenchimento', ('Responsável negócio: ' + $BusinessOwner)
}

if (-not [string]::IsNullOrWhiteSpace($ApprovedBy)) {
    $content = $content -replace 'Aprovado por: Pendente assinatura formal', ('Aprovado por: ' + $ApprovedBy)
}

if ($AnnouncementSent) {
    $content = $content -replace '- \[ \] Comunicado de release enviado\.', '- [x] Comunicado de release enviado.'
}

if (-not [string]::IsNullOrWhiteSpace($ReviewWindow)) {
    $content = $content -replace '- \[ \] Próxima janela de revisão operacional agendada\.', ('- [x] Próxima janela de revisão operacional agendada. (' + $ReviewWindow + ')')
}

$preview = [pscustomobject]@{
    checklist = $checklistPath
    will_apply = [bool]$Apply
    technical_owner = $TechnicalOwner
    business_owner = $BusinessOwner
    approved_by = $ApprovedBy
    announcement_sent = [bool]$AnnouncementSent
    review_window = $ReviewWindow
}

if ($Apply) {
    Set-Content -Path $checklistPath -Value $content -Encoding UTF8
    $preview | Add-Member -NotePropertyName status -NotePropertyValue 'updated' -Force
} else {
    $preview | Add-Member -NotePropertyName status -NotePropertyValue 'preview_only' -Force
}

$preview | ConvertTo-Json -Depth 4

exit 0

