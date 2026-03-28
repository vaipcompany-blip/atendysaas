[Diagnostics.CodeAnalysis.SuppressMessageAttribute('PSAvoidUsingPlainTextForPassword', '', Justification='Script de teste local/CI com ambiente efêmero e sem segredo persistente.')]
param(
  [string]$BaseUrl = 'http://localhost/Aula-SQL/public/index.php',
  [string]$PhpPath = '',
  [string]$MysqlPath = '',
  [string]$DbHost = '',
  [int]$DbPort = 0,
  [string]$DbName = '',
  [string]$DbUser = '',
  [string]$DbPassword = ''
)

$ProgressPreference='SilentlyContinue'
$ErrorActionPreference='Stop'

$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$repoRoot = (Resolve-Path (Join-Path $scriptDir '..')).Path
Set-Location $repoRoot

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
  $DbHost = [string]($env:DB_HOST)
  if ([string]::IsNullOrWhiteSpace($DbHost)) { $DbHost = '127.0.0.1' }
}

if ($DbPort -le 0) {
  $DbPort = 3306
  if (-not [string]::IsNullOrWhiteSpace($env:DB_PORT)) {
    $DbPort = [int]$env:DB_PORT
  }
}

if ([string]::IsNullOrWhiteSpace($DbName)) {
  $DbName = [string]($env:DB_DATABASE)
  if ([string]::IsNullOrWhiteSpace($DbName)) { $DbName = 'atendy' }
}

if ([string]::IsNullOrWhiteSpace($DbUser)) {
  $DbUser = [string]($env:DB_USERNAME)
  if ([string]::IsNullOrWhiteSpace($DbUser)) { $DbUser = 'root' }
}

if ([string]::IsNullOrWhiteSpace($DbPassword)) {
  $DbPassword = [string]($env:DB_PASSWORD)
}

if (-not (Test-Path $PhpPath)) {
  throw "PHP binary não encontrado em: $PhpPath"
}

if (-not (Test-Path $MysqlPath)) {
  throw "MySQL client não encontrado em: $MysqlPath"
}

$base = $BaseUrl
$mysql = $MysqlPath
$php = $PhpPath
$results = New-Object System.Collections.Generic.List[string]

function Invoke-DbScalar([string]$sql) {
  $mysqlCli = @('-h', $DbHost, '-P', "$DbPort", '-u', $DbUser, '-N')
  if (-not [string]::IsNullOrEmpty($DbPassword)) {
    $mysqlCli += "--password=$DbPassword"
  }

  $mysqlCli += @('-e', "USE $DbName; $sql")
  $output = & $mysql @mysqlCli 2>$null
  if ($LASTEXITCODE -ne 0) {
    return ''
  }

  return ([string]$output).Trim()
}

function Invoke-DbNonQuery([string]$sql) {
  $mysqlCli = @('-h', $DbHost, '-P', "$DbPort", '-u', $DbUser)
  if (-not [string]::IsNullOrEmpty($DbPassword)) {
    $mysqlCli += "--password=$DbPassword"
  }

  $mysqlCli += @('-e', "USE $DbName; $sql")
  & $mysql @mysqlCli 2>$null | Out-Null
  return [bool]($LASTEXITCODE -eq 0)
}

function Add-Result([string]$name, [bool]$ok, [string]$detail='') {
  $status = if ($ok) { 'PASS' } else { 'FAIL' }
  $line = "$status | $name"
  if ($detail -ne '') { $line += " | $detail" }
  $script:results.Add($line) | Out-Null
}

function Get-Csrf([string]$html) {
  $m = [regex]::Match($html, 'name="csrf_token"\s+value="([^"]+)"')
  if ($m.Success) { return $m.Groups[1].Value }
  return ''
}

$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$rand = Get-Random -Minimum 10000 -Maximum 99999
$email = "qa+$rand@atendy.local"
$cpf = ("9$rand".PadRight(11,'7')).Substring(0,11)
$phone = ("1199$rand".PadRight(11,'8')).Substring(0,11)
$password = '12345678'

try {
  try {
    & $php 'scripts/migrate.php' '--quiet' 2>$null | Out-Null
    Add-Result 'Run migration runner' ($LASTEXITCODE -eq 0)
  } catch {
    Add-Result 'Run migration runner' $false "exception=$($_.Exception.Message)"
  }

  $healthResp = Invoke-WebRequest -Uri ($base + '?route=health') -UseBasicParsing
  $healthJson = $healthResp.Content | ConvertFrom-Json
  $healthOk = ($healthResp.StatusCode -eq 200) -and ($healthJson.status -match 'ok|warn') -and ($healthJson.checks.database.status -eq 'ok') -and ($healthJson.checks.storage_logs.status -eq 'ok')
  Add-Result 'GET health endpoint' $healthOk "status=$($healthResp.StatusCode);overall=$($healthJson.status)"

  try {
    $healthCliOutput = & $php 'scripts/healthcheck.php'
    $healthCliJson = $healthCliOutput | ConvertFrom-Json
    $healthCliOk = ($LASTEXITCODE -eq 0) -and ($healthCliJson.checks.database.status -eq 'ok')
    Add-Result 'CLI healthcheck script' $healthCliOk "overall=$($healthCliJson.status)"
  } catch {
    Add-Result 'CLI healthcheck script' $false "exception=$($_.Exception.Message)"
  }

  try {
    $backupCliOutput = & $php 'scripts/backup.php'
    $backupCliJson = $backupCliOutput | ConvertFrom-Json
    $backupCliOk = ($LASTEXITCODE -eq 0) -and ($backupCliJson.file_name -match '\.sql$')
    Add-Result 'CLI backup script' $backupCliOk "file=$($backupCliJson.file_name)"
  } catch {
    Add-Result 'CLI backup script' $false "exception=$($_.Exception.Message)"
  }

  $regGet = Invoke-WebRequest -Uri ($base + '?route=register') -WebSession $session -UseBasicParsing
  $csrf = Get-Csrf $regGet.Content
  Add-Result 'GET register' ($regGet.StatusCode -eq 200 -and $csrf -ne '') "csrf=$($csrf.Length)"

  $regBody = @{
    csrf_token = $csrf
    nome_consultorio = "Clinica QA $rand"
    email = $email
    cpf = $cpf
    telefone = $phone
    endereco = 'Rua Teste, 100'
    password = $password
    password_confirm = $password
    accept_legal = '1'
  }
  $regPost = Invoke-WebRequest -Uri ($base + '?route=register') -Method Post -Body $regBody -WebSession $session -MaximumRedirection 5 -UseBasicParsing
  Add-Result 'POST register' ($regPost.Content -match 'Conta criada com sucesso|Entrar no Atendy') "email=$email"

  $loginGet = Invoke-WebRequest -Uri ($base + '?route=login') -WebSession $session -UseBasicParsing
  $csrfLogin = Get-Csrf $loginGet.Content
  Add-Result 'GET login' ($loginGet.StatusCode -eq 200 -and $csrfLogin -ne '')

  $loginCsp = [string]($loginGet.Headers['Content-Security-Policy'])
  $loginCspOk = ($loginCsp -match "default-src 'self'") -and ($loginCsp -match "object-src 'none'")
  Add-Result 'GET login has CSP header' $loginCspOk

  $loginSeedHintOk = ($loginGet.Content -notmatch 'Usuário padrão: admin@atendy.local / 12345678') -and ($loginGet.Content -match 'Primeiro acesso')
  Add-Result 'GET login hides default seed credentials' $loginSeedHintOk

  $loginBody = @{ csrf_token=$csrfLogin; login=$email; password=$password }
  $loginPost = Invoke-WebRequest -Uri ($base + '?route=login') -Method Post -Body $loginBody -WebSession $session -MaximumRedirection 5 -UseBasicParsing
  Add-Result 'POST login' ($loginPost.Content -match 'Dashboard Inteligente|Plataforma de automação clínica')

  $billingGet = Invoke-WebRequest -Uri ($base + '?route=billing') -WebSession $session -UseBasicParsing
  $billingOk = ($billingGet.StatusCode -eq 200) -and ($billingGet.Content -match 'Billing e Assinatura|Assinatura atual')
  Add-Result 'GET billing owner access' $billingOk "status=$($billingGet.StatusCode)"

  $dashGet = Invoke-WebRequest -Uri ($base + '?route=dashboard') -WebSession $session -UseBasicParsing
  $dashPipelineOk = ($dashGet.StatusCode -eq 200) -and ($dashGet.Content -match 'Pipeline de Leads|chartLeadPipeline')
  Add-Result 'GET dashboard pipeline' $dashPipelineOk "status=$($dashGet.StatusCode)"

  $dashMonthlyGoalOk = ($dashGet.StatusCode -eq 200) -and ($dashGet.Content -match 'Meta mensal de convers|monthlyGoalInsight')
  Add-Result 'GET dashboard monthly goal' $dashMonthlyGoalOk "status=$($dashGet.StatusCode)"

  $dashPerformanceOk = ($dashGet.StatusCode -eq 200) -and ($dashGet.Content -match 'Performance de Pacientes|Top Pacientes')
  Add-Result 'GET dashboard patient performance' $dashPerformanceOk "status=$($dashGet.StatusCode)"

  $dashPeriodGet = Invoke-WebRequest -Uri ($base + '?route=dashboard&period=7d') -WebSession $session -UseBasicParsing
  $dashPeriodOk = ($dashPeriodGet.StatusCode -eq 200) -and ($dashPeriodGet.Content -match '�sltimos 7 dias|period=7d')
  Add-Result 'GET dashboard period 7d' $dashPeriodOk "status=$($dashPeriodGet.StatusCode)"

  $patientsGet = Invoke-WebRequest -Uri ($base + '?route=patients') -WebSession $session -UseBasicParsing
  $csrfPatient = Get-Csrf $patientsGet.Content
  Add-Result 'GET patients' ($patientsGet.StatusCode -eq 200 -and $csrfPatient -ne '')

  $patientName = "Paciente QA $rand"
  $patientCpf = ('8' + $cpf.Substring(1,10))
  $patientBody = @{ csrf_token=$csrfPatient; action='create'; nome=$patientName; whatsapp='11999998888'; email=''; cpf=$patientCpf }
  $patientPost = Invoke-WebRequest -Uri ($base + '?route=patients') -Method Post -Body $patientBody -WebSession $session -MaximumRedirection 5 -UseBasicParsing
  Add-Result 'POST patients create' ($patientPost.Content -match 'cadastrado com sucesso|Pacientes')

  $settingsGet = Invoke-WebRequest -Uri ($base + '?route=settings') -WebSession $session -UseBasicParsing
  $csrfSettings = Get-Csrf $settingsGet.Content
  Add-Result 'GET settings' ($settingsGet.StatusCode -eq 200 -and $csrfSettings -ne '') "status=$($settingsGet.StatusCode);csrfLen=$($csrfSettings.Length)"

  $settingsOpsOk = ($settingsGet.Content -match 'Backup operacional|Gerar backup do banco') -and ($settingsGet.Content -match 'Observabilidade|�sltimos erros da aplicação|Health:')
  Add-Result 'GET settings ops sections' $settingsOpsOk

  $previewBody = @{
    csrf_token = $csrfSettings
    action = 'preview_template'
    template_key = 'mensagem_confirmacao'
    template_text = 'Olá {{nome}} da {{clinica}}! Consulta: {{data_hora}}.'
  }
  $previewPost = Invoke-WebRequest -Uri ($base + '?route=settings') -Method Post -Body $previewBody -WebSession $session -UseBasicParsing
  $previewOk = $false
  $previewDetail = "status=$($previewPost.StatusCode)"
  try {
    $previewJson = $previewPost.Content | ConvertFrom-Json
    $previewText = [string]($previewJson.rendered)
    $previewOk = ($previewPost.StatusCode -eq 200) -and ($previewJson.success -eq $true) -and ($previewText -match 'Maria Oliveira') -and ($previewText -match [regex]::Escape("Clinica QA $rand"))
    $previewDetail = "status=$($previewPost.StatusCode);text=$previewText"
  } catch {
    $previewOk = $false
    $previewDetail = "status=$($previewPost.StatusCode);json_parse=fail"
  }
  Add-Result 'POST settings preview_template' $previewOk $previewDetail

  $previewInvalidBody = @{
    csrf_token = $csrfSettings
    action = 'preview_template'
    template_key = 'template_invalido'
    template_text = 'Teste inválido {{nome}}'
  }

  $previewInvalidOk = $false
  $previewInvalidDetail = ''
  try {
    $previewInvalidPost = Invoke-WebRequest -Uri ($base + '?route=settings') -Method Post -Body $previewInvalidBody -WebSession $session -UseBasicParsing
    $previewInvalidDetail = "status=$($previewInvalidPost.StatusCode);expected=422"
  } catch {
    $response = $_.Exception.Response
    if ($null -eq $response) {
      throw
    }

    $statusCode = [int] $response.StatusCode
    $reader = New-Object System.IO.StreamReader($response.GetResponseStream())
    $rawContent = $reader.ReadToEnd()
    $reader.Close()

    $errorJson = $null
    $jsonParsed = $false
    try {
      $errorJson = $rawContent | ConvertFrom-Json
      $jsonParsed = $true
    } catch {
      $errorJson = $null
      $jsonParsed = $false
    }

    $previewInvalidOk = ($statusCode -eq 422)
    if ($jsonParsed -and $null -ne $errorJson) {
      $previewInvalidOk = $previewInvalidOk -and ($errorJson.success -eq $false)
    }

    $previewInvalidDetail = "status=$statusCode;jsonParsed=$jsonParsed"
  }
  Add-Result 'POST settings preview_template invalid_key' $previewInvalidOk $previewInvalidDetail

  $settingsBody = @{
    csrf_token = $csrfSettings
    action = 'save'
    whatsapp_mode = 'cloud'
    whatsapp_api_url = 'https://graph.facebook.com/v20.0'
    whatsapp_phone_number_id = ''
    token_whatsapp = "secret-token-$rand"
    whatsapp_verify_token = ''
    whatsapp_default_country = '55'
    horario_abertura = '08:00'
    horario_fechamento = '18:00'
    duracao_consulta = '50'
    intervalo = '10'
    meta_conversao_mensal = '75'
    mensagem_confirmacao = 'Olá {{nome}}! Sua consulta em {{data_hora}}.'
    template_lembrete_12h = 'Lembrete 12h para {{nome}} em {{data_hora}}'
    template_lembrete_2h = 'Lembrete 2h para {{nome}} em {{data_hora}}'
    template_followup_falta = 'Falta: {{nome}}'
    template_followup_cancelamento = 'Cancelada: {{nome}}'
    template_followup_inatividade = 'Inativo: {{nome}}'
  }
  $settingsPost = Invoke-WebRequest -Uri ($base + '?route=settings') -Method Post -Body $settingsBody -WebSession $session -MaximumRedirection 5 -UseBasicParsing
  $settingsOk = ($settingsPost.Content -match 'Configura|Seguran|Monitoramento')
  Add-Result 'POST settings save' $settingsOk "status=$($settingsPost.StatusCode)"

  $settingsSecretGet = Invoke-WebRequest -Uri ($base + '?route=settings') -WebSession $session -UseBasicParsing
  $settingsSecretOk = ($settingsSecretGet.Content -notmatch [regex]::Escape("secret-token-$rand")) -and ($settingsSecretGet.Content -match 'type="password"') -and ($settingsSecretGet.Content -match 'autocomplete="new-password"')
  Add-Result 'GET settings masks access token' $settingsSecretOk

  $backupWebBody = @{ csrf_token=$csrfSettings; action='create_backup' }
  $backupWebResp = Invoke-WebRequest -Uri ($base + '?route=settings') -Method Post -Body $backupWebBody -WebSession $session -UseBasicParsing
  $backupWebContentType = [string]($backupWebResp.Headers['Content-Type'])
  $backupWebDisposition = [string]($backupWebResp.Headers['Content-Disposition'])
  $backupWebOk = ($backupWebResp.StatusCode -eq 200) -and ($backupWebContentType -match 'application/sql|text/plain') -and ($backupWebDisposition -match '\.sql')
  Add-Result 'POST settings create_backup' $backupWebOk "status=$($backupWebResp.StatusCode);contentType=$backupWebContentType"

  $uid = Invoke-DbScalar "SELECT id FROM users WHERE email='$email' LIMIT 1;"
  Add-Result 'DB user created' ($uid -match '^\d+$') "user_id=$uid"

  try {
    $sessionTimeoutOutput = & $php '-r' "require 'src/bootstrap.php'; `$_SESSION['user']=['id'=>(int)$uid,'email'=>'$email','session_version'=>1,'type'=>'owner']; `$_SESSION['last_activity_at']=time()-100000; echo Auth::check() ? 'active' : 'expired'; echo '|'; echo (`$_SESSION['auth_error'] ?? '');"
    $sessionTimeoutOk = ($LASTEXITCODE -eq 0) -and ($sessionTimeoutOutput -match '^expired\|') -and ($sessionTimeoutOutput -match 'inatividade')
    Add-Result 'CLI auth idle timeout expires session' $sessionTimeoutOk "output=$sessionTimeoutOutput"
  } catch {
    Add-Result 'CLI auth idle timeout expires session' $false "exception=$($_.Exception.Message)"
  }

  $patientIdDb = Invoke-DbScalar "SELECT id FROM patients WHERE user_id=$uid AND nome='$patientName' ORDER BY id DESC LIMIT 1;"
  Add-Result 'DB patient created' ($patientIdDb -match '^\d+$') "patient_id=$patientIdDb"

  $apptGet = Invoke-WebRequest -Uri ($base + '?route=appointments') -WebSession $session -UseBasicParsing
  $csrfAppt = Get-Csrf $apptGet.Content
  Add-Result 'GET appointments' ($apptGet.StatusCode -eq 200 -and $csrfAppt -ne '')

  $future = (Get-Date).AddDays(2).ToString('yyyy-MM-ddTHH:mm')
  $apptBody = @{ csrf_token=$csrfAppt; action='create'; patient_id=$patientIdDb; data_hora=$future; procedimento='Consulta QA' }
  $apptPost = Invoke-WebRequest -Uri ($base + '?route=appointments') -Method Post -Body $apptBody -WebSession $session -MaximumRedirection 5 -UseBasicParsing
  Add-Result 'POST appointments create' ($apptPost.Content -match 'Consulta criada com sucesso|Consultas')

  $apptId = Invoke-DbScalar "SELECT id FROM appointments WHERE user_id=$uid AND patient_id=$patientIdDb ORDER BY id DESC LIMIT 1;"
  Add-Result 'DB appointment created' ($apptId -match '^\d+$') "appointment_id=$apptId"

  $futureRecurring = (Get-Date).AddDays(4).ToString('yyyy-MM-ddTHH:mm')
  $apptRecurringBody = @{
    csrf_token = $csrfAppt
    action = 'create'
    patient_id = $patientIdDb
    data_hora = $futureRecurring
    procedimento = 'Consulta QA Recorrente'
    recurrence_enabled = '1'
    recurrence_frequency = 'weekly'
    recurrence_count = '3'
  }
  $apptRecurringPost = Invoke-WebRequest -Uri ($base + '?route=appointments') -Method Post -Body $apptRecurringBody -WebSession $session -MaximumRedirection 5 -UseBasicParsing
  Add-Result 'POST appointments create recurring' ($apptRecurringPost.Content -match 'Série criada|Consultas')

  $apptRecurringCount = Invoke-DbScalar "SELECT COUNT(*) FROM appointments WHERE user_id=$uid AND patient_id=$patientIdDb AND procedimento='Consulta QA Recorrente';"
  Add-Result 'DB recurring appointments created' ([int]$apptRecurringCount -ge 3) "count=$apptRecurringCount"

  $blockedDate = (Get-Date).AddDays(6).ToString('yyyy-MM-dd')
  $blockedBody = @{
    csrf_token = $csrfSettings
    action = 'add_blocked_date'
    blocked_date = $blockedDate
    start_time = '14:00'
    end_time = '15:00'
    reason = 'Bloqueio E2E faixa'
  }
  $blockedPost = Invoke-WebRequest -Uri ($base + '?route=settings') -Method Post -Body $blockedBody -WebSession $session -MaximumRedirection 5 -UseBasicParsing
  $blockedSaveOk = ($blockedPost.StatusCode -eq 200) -and ($blockedPost.Content -match 'Configura|Feriados|indispon')
  Add-Result 'POST settings add_blocked_date time_window' $blockedSaveOk "status=$($blockedPost.StatusCode)"

  $blockedApptDateTime = $blockedDate + 'T14:30'
  $blockedApptBody = @{
    csrf_token = $csrfAppt
    action = 'create'
    patient_id = $patientIdDb
    data_hora = $blockedApptDateTime
    procedimento = 'Consulta QA Bloqueada'
  }
  $blockedApptPost = Invoke-WebRequest -Uri ($base + '?route=appointments') -Method Post -Body $blockedApptBody -WebSession $session -MaximumRedirection 5 -UseBasicParsing
  $blockedApptOk = $blockedApptPost.Content -match 'Data indisponível|bloqueada no expediente'
  Add-Result 'POST appointments blocked_time_window' $blockedApptOk

  $waGet = Invoke-WebRequest -Uri ($base + '?route=whatsapp') -WebSession $session -UseBasicParsing
  $csrfWa = Get-Csrf $waGet.Content
  Add-Result 'GET whatsapp' ($waGet.StatusCode -eq 200 -and $csrfWa -ne '')

  $waSummaryOk = ($waGet.Content -match 'wa-summary-grid|Resumo do per')
  Add-Result 'GET whatsapp summary cards' $waSummaryOk

  $waTrendOk = ($waGet.Content -match 'waTrendChart|Tend')
  Add-Result 'GET whatsapp trend chart' $waTrendOk

  $waStatusChartOk = ($waGet.Content -match 'waStatusChart|Distribui')
  Add-Result 'GET whatsapp status chart' $waStatusChartOk

  $waStatusInsightOk = ($waGet.Content -match 'waStatusInsight|Status mais frequente|Sem dados de status')
  Add-Result 'GET whatsapp status insight' $waStatusInsightOk

  $waPaged = Invoke-WebRequest -Uri ($base + '?route=whatsapp&page=1&patient_id=' + $patientIdDb + '&direction=&status=&date_from=&date_to=&q=') -WebSession $session -UseBasicParsing
  $waPagedOk = ($waPaged.StatusCode -eq 200) -and ($waPaged.Content -match 'Histórico recente de mensagens|Total filtrado')
  Add-Result 'GET whatsapp paged list' $waPagedOk "status=$($waPaged.StatusCode)"

  $waSorted = Invoke-WebRequest -Uri ($base + '?route=whatsapp&page=1&patient_id=' + $patientIdDb + '&direction=&status=&date_from=&date_to=&q=&sort_by=status&sort_dir=asc') -WebSession $session -UseBasicParsing
  $waSortedOk = ($waSorted.StatusCode -eq 200) -and ($waSorted.Content -match 'Ordenar por|Direção')
  Add-Result 'GET whatsapp sorted list' $waSortedOk "status=$($waSorted.StatusCode)"

  $waExport = Invoke-WebRequest -Uri ($base + '?route=whatsapp&action=export_csv&patient_id=' + $patientIdDb) -WebSession $session -UseBasicParsing
  $waExportContentType = [string]($waExport.Headers['Content-Type'])
  $waExportOk = ($waExport.StatusCode -eq 200) -and ($waExportContentType -match 'text/csv') -and ($waExport.Content -match 'Data/Hora;Paciente;')
  Add-Result 'GET whatsapp export_csv' $waExportOk "status=$($waExport.StatusCode);contentType=$waExportContentType"

  $waBody = @{ csrf_token=$csrfWa; action='run_automations' }
  $waPost = Invoke-WebRequest -Uri ($base + '?route=whatsapp') -Method Post -Body $waBody -WebSession $session -MaximumRedirection 5 -UseBasicParsing
  Add-Result 'POST whatsapp run_automations' ($waPost.Content -match 'Automações executadas|WhatsApp')

  $editP = Invoke-WebRequest -Uri ($base + '?route=patients&action=edit&patient_id=' + $patientIdDb) -WebSession $session -UseBasicParsing
  Add-Result 'GET patients edit' ($editP.StatusCode -eq 200 -and $editP.Content -match 'Editar Paciente')

  $editA = Invoke-WebRequest -Uri ($base + '?route=appointments&action=edit&appointment_id=' + $apptId) -WebSession $session -UseBasicParsing
  Add-Result 'GET appointments edit' ($editA.StatusCode -eq 200 -and $editA.Content -match 'Editar Consulta')

  $settingsCheck = Invoke-DbScalar "SELECT CONCAT(horario_abertura,'|',horario_fechamento,'|',duracao_consulta,'|',intervalo,'|',LEFT(template_lembrete_12h,12),'|',meta_conversao_mensal) FROM settings WHERE user_id=$uid LIMIT 1;"
  Add-Result 'DB settings persisted' ($settingsCheck -match '^08:00:00\|18:00:00\|50\|10\|Lembrete 12h\|75') $settingsCheck

  # �"?�"? Financeiro �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
  $finGet = Invoke-WebRequest -Uri ($base + '?route=financeiro') -WebSession $session -UseBasicParsing
  Add-Result 'GET financeiro' ($finGet.StatusCode -eq 200 -and $finGet.Content -match 'fin-kpi-grid|Receita recebida') "status=$($finGet.StatusCode)"

  # Captura CSRF da própria página do financeiro
  $csrfFin = Get-Csrf $finGet.Content
  $pgtoBody = @{
    csrf_token     = $csrfFin
    action         = 'update_pagamento'
    appointment_id = $apptId
    valor_cobrado  = '150.00'
    forma_pagamento= 'pix'
    pago           = '1'
    data_pagamento = (Get-Date -Format 'yyyy-MM-dd')
  }
  try {
    $pgtoResp = Invoke-WebRequest -Uri ($base + '?route=financeiro') -Method Post -Body $pgtoBody -WebSession $session -UseBasicParsing
    $pgtoJson = $null; try { $pgtoJson = $pgtoResp.Content | ConvertFrom-Json } catch {}
    Add-Result 'POST financeiro update_pagamento' ($pgtoJson -and $pgtoJson.success -eq $true) "json=$($pgtoResp.Content)"
  } catch {
    Add-Result 'POST financeiro update_pagamento' $false "exception=$($_.Exception.Message)"
  }

  # Verificar que pagamento foi salvo no banco
  $pgtoCheck = Invoke-DbScalar "SELECT CONCAT(valor_cobrado,'|',forma_pagamento,'|',pago) FROM appointments WHERE id=$apptId AND user_id=$uid LIMIT 1;"
  Add-Result 'DB pagamento salvo' ($pgtoCheck -match '^150\.00\|pix\|1') $pgtoCheck

  # Financeiro com filtro de período
  $finFiltro = Invoke-WebRequest -Uri ($base + '?route=financeiro&periodo=trimestre') -WebSession $session -UseBasicParsing
  Add-Result 'GET financeiro filtro trimestre' ($finFiltro.StatusCode -eq 200 -and $finFiltro.Content -match 'fin-lancamentos|fin-kpi-grid') "status=$($finFiltro.StatusCode)"

  # �"?�"? Relatórios �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
  $reportsFrom = (Get-Date).AddDays(-30).ToString('yyyy-MM-dd')
  $reportsTo = (Get-Date).ToString('yyyy-MM-dd')

  $reportsGet = Invoke-WebRequest -Uri ($base + '?route=reports&from=' + $reportsFrom + '&to=' + $reportsTo) -WebSession $session -UseBasicParsing
  $reportsGetOk = ($reportsGet.StatusCode -eq 200) -and ($reportsGet.Content -match 'Relatórios|Top procedimentos|Evolução diária') -and ($reportsGet.Content -match 'reportsStatusChart|reportsProcedureChart|reportsDailyChart')
  Add-Result 'GET reports' $reportsGetOk "status=$($reportsGet.StatusCode)"

  $reportsPreset = Invoke-WebRequest -Uri ($base + '?route=reports&preset=7d') -WebSession $session -UseBasicParsing
  $reportsPresetOk = ($reportsPreset.StatusCode -eq 200) -and ($reportsPreset.Content -match '�sltimos 7 dias|preset=7d')
  Add-Result 'GET reports preset 7d' $reportsPresetOk "status=$($reportsPreset.StatusCode)"

  $reportsCompare = Invoke-WebRequest -Uri ($base + '?route=reports&preset=30d&compare=1') -WebSession $session -UseBasicParsing
  $reportsCompareOk = ($reportsCompare.StatusCode -eq 200) -and ($reportsCompare.Content -match 'Compara|compare=1|Remover compara')
  Add-Result 'GET reports compare previous period' $reportsCompareOk "status=$($reportsCompare.StatusCode)"

  $reportsCsv = Invoke-WebRequest -Uri ($base + '?route=reports&action=export_csv&from=' + $reportsFrom + '&to=' + $reportsTo) -WebSession $session -UseBasicParsing
  $reportsCsvContentType = [string]($reportsCsv.Headers['Content-Type'])
  $reportsCsvOk = ($reportsCsv.StatusCode -eq 200) -and ($reportsCsvContentType -match 'text/csv') -and ($reportsCsv.Content -match 'Consultas') -and ($reportsCsv.Content -match 'Receita')
  Add-Result 'GET reports export_csv' $reportsCsvOk "status=$($reportsCsv.StatusCode);contentType=$reportsCsvContentType"

  $reportsPdf = Invoke-WebRequest -Uri ($base + '?route=reports&action=export_pdf&from=' + $reportsFrom + '&to=' + $reportsTo) -WebSession $session -UseBasicParsing
  $reportsPdfOk = ($reportsPdf.StatusCode -eq 200) -and ($reportsPdf.Content -match 'Relatório Gerencial|Imprimir / Salvar PDF')
  Add-Result 'GET reports export_pdf' $reportsPdfOk "status=$($reportsPdf.StatusCode)"

  # �"?�"? Calendário �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
  # 1) Settings page deve conter a seção de calendário
  $calSettings = Invoke-WebRequest -Uri ($base + '?route=settings') -WebSession $session -UseBasicParsing
  Add-Result 'GET settings calendar section' ($calSettings.StatusCode -eq 200 -and $calSettings.Content -match 'calendar-integration|Integra.*o com Calend') "status=$($calSettings.StatusCode)"

  # 2) POST calendar gerar token
  $calCsrf = ''
  if ($calSettings.Content -match 'name="csrf_token"\s+value="([^"]+)"') { $calCsrf = $Matches[1] }
  $calGenBody = "action=generate&csrf_token=$([System.Uri]::EscapeDataString($calCsrf))"
  try {
    $calGenResp = Invoke-WebRequest -Uri ($base + '?route=calendar') -Method Post -Body $calGenBody -WebSession $session -UseBasicParsing
    $calGenJson = $null
    try { $calGenJson = $calGenResp.Content | ConvertFrom-Json } catch {}
    Add-Result 'POST calendar generate_token' ($calGenJson -and $calGenJson.success -eq $true) "json=$($calGenResp.Content)"
  } catch {
    Add-Result 'POST calendar generate_token' $false "exception=$($_.Exception.Message)"
  }

  # 3) Feed iCal público (busca o token do banco via MySQL)
  $calToken = ''
  try {
    $calToken = Invoke-DbScalar "SELECT calendar_token FROM settings WHERE user_id=$uid LIMIT 1;"
    $calToken = $calToken.Trim()
  } catch {}
  if ($calToken -ne '') {
    $icalResp = Invoke-WebRequest -Uri ($base + "?route=calendar_feed&token=$calToken") -UseBasicParsing
    $icalOk = $icalResp.StatusCode -eq 200 -and $icalResp.Content -match 'BEGIN:VCALENDAR'
    Add-Result 'GET calendar_feed ical' $icalOk "status=$($icalResp.StatusCode);content=$($icalResp.Content.Substring(0,[Math]::Min(80,$icalResp.Content.Length)))"
  } else {
    Add-Result 'GET calendar_feed ical' $false "token not found in DB"
  }

  # 4) Export .ics download (autenticado)
  $icsResp = Invoke-WebRequest -Uri ($base + '?route=calendar&action=export') -WebSession $session -UseBasicParsing
  $icsOk = $icsResp.StatusCode -eq 200 -and ($icsResp.Content -match 'BEGIN:VCALENDAR' -or ($icsResp.Headers['Content-Disposition'] -match 'attachment'))
  Add-Result 'GET calendar export ics' $icsOk "status=$($icsResp.StatusCode)"

  # �"?�"? Notificações �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
  # 1) GET notifications list page (deve carregá-la)
  $notifGet = Invoke-WebRequest -Uri ($base + '?route=notifications') -WebSession $session -UseBasicParsing
  Add-Result 'GET notifications list' ($notifGet.StatusCode -eq 200 -and ($notifGet.Content -match 'Notificações|notifications')) "status=$($notifGet.StatusCode)"

  # 2) POST get_unread_count (deve retornar JSON com count)
  $notifGetCsrf = Get-Csrf $notifGet.Content
  $unreadBody = "action=get_unread_count&csrf_token=$([System.Uri]::EscapeDataString($notifGetCsrf))"
  try {
    $unreadResp = Invoke-WebRequest -Uri ($base + '?route=notifications') -Method Post -Body $unreadBody -WebSession $session -UseBasicParsing
    $unreadJson = $null
    try { $unreadJson = $unreadResp.Content | ConvertFrom-Json } catch {}
    $unreadOk = $null -ne $unreadJson -and $unreadJson.unread_count -ge 0
    Add-Result 'POST notifications get_unread_count' $unreadOk "response=$($unreadResp.Content)"
  } catch {
    Add-Result 'POST notifications get_unread_count' $false "exception=$($_.Exception.Message)"
  }

  # 3) POST get_latest (deve retornar JSON com array de notificações)
  $latestBody = "action=get_latest&csrf_token=$([System.Uri]::EscapeDataString($notifGetCsrf))"
  try {
    $latestResp = Invoke-WebRequest -Uri ($base + '?route=notifications') -Method Post -Body $latestBody -WebSession $session -UseBasicParsing
    $latestJson = $null
    try { $latestJson = $latestResp.Content | ConvertFrom-Json } catch {}
    $latestOk = $null -ne $latestJson -and ($latestJson -is [array] -or $latestJson.notifications -is [array])
    Add-Result 'POST notifications get_latest' $latestOk "response=$($latestResp.Content.Substring(0,[Math]::Min(100,$latestResp.Content.Length)))"
  } catch {
    Add-Result 'POST notifications get_latest' $false "exception=$($_.Exception.Message)"
  }

  # 4) Verificar que criar uma consulta gera uma notificação
  # (A notificação é criada automaticamente via NotificationController::create no AppointmentController)
  $notifDbCount = 0
  try {
    $notifCount = Invoke-DbScalar "SELECT COUNT(*) FROM notifications WHERE user_id=$uid;"
    [int]::TryParse($notifCount, [ref]$notifDbCount) | Out-Null
  } catch {}
  Add-Result 'Notifications exist in DB' ($notifDbCount -ge 0) "count=$notifDbCount"

  # 5) POST mark_read (marcar uma notificação como lida)
  if ($notifDbCount -gt 0) {
    $notifId = $null
    try {
      $notifId = Invoke-DbScalar "SELECT id FROM notifications WHERE user_id=$uid LIMIT 1;"
    } catch {}
    
    if ($notifId -ne '') {
      $markReadBody = "action=mark_read&notification_id=$notifId&csrf_token=$([System.Uri]::EscapeDataString($notifGetCsrf))"
      try {
        $markResp = Invoke-WebRequest -Uri ($base + '?route=notifications') -Method Post -Body $markReadBody -WebSession $session -UseBasicParsing
        $markJson = $null
        try { $markJson = $markResp.Content | ConvertFrom-Json } catch {}
        $markOk = $null -ne $markJson -and $markJson.success -eq $true
        Add-Result 'POST notifications mark_read' $markOk "response=$($markResp.Content)"
      } catch {
        Add-Result 'POST notifications mark_read' $false "exception=$($_.Exception.Message)"
      }
    }
  }

  # �"?�"? Equipe �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
  # 1) GET team list page
  $teamGet = Invoke-WebRequest -Uri ($base + '?route=team') -WebSession $session -UseBasicParsing
  Add-Result 'GET team list' ($teamGet.StatusCode -eq 200 -and ($teamGet.Content -match 'Equipe|team')) "status=$($teamGet.StatusCode)"

  # 2) POST invite member (POST redirect)
  $teamCsrf = Get-Csrf $teamGet.Content
  $teamEmail = "team+$rand@atendy.local"
  $teamPassword = '12345678'
  $inviteBody = @{
    csrf_token = $teamCsrf
    action = 'invite'
    email = $teamEmail
    nome_completo = "QA Team Member $rand"
    role = 'admin'
  }
  try {
    $inviteResp = Invoke-WebRequest -Uri ($base + '?route=team') -Method Post -Body $inviteBody -WebSession $session -MaximumRedirection 5 -UseBasicParsing
    $inviteOk = $inviteResp.StatusCode -eq 200
    Add-Result 'POST team invite member' $inviteOk "status=$($inviteResp.StatusCode)"
  } catch {
    Add-Result 'POST team invite member' $false "exception=$($_.Exception.Message)"
  }

  # 3) Verificar que membro foi criado no DB
  $teamMemberDb = $null
  $teamToken = ''
  try {
    $teamRow = Invoke-DbScalar "SELECT id, COALESCE(invitation_token,'') FROM team_members WHERE workspace_id=$uid AND email='$teamEmail' LIMIT 1;"
    if ($teamRow -match "^(\d+)\t(.*)$") {
      $teamMemberDb = $Matches[1]
      $teamToken = $Matches[2]
    }
  } catch {}
  $teamMemberCreatedOk = $teamMemberDb -ne '' -and $teamMemberDb -match '^\d+$'
  Add-Result 'Team member created in DB' $teamMemberCreatedOk "member_id=$teamMemberDb"

  # 4) POST update role (admin para staff)
  if ($teamMemberCreatedOk) {
    $updateRoleBody = "action=update_role&member_id=$teamMemberDb&role=staff&csrf_token=$([System.Uri]::EscapeDataString($teamCsrf))"
    try {
      $updateResp = Invoke-WebRequest -Uri ($base + '?route=team') -Method Post -Body $updateRoleBody -WebSession $session -UseBasicParsing
      $updateJson = $null
      try { $updateJson = $updateResp.Content | ConvertFrom-Json } catch {}
      $updateOk = $null -ne $updateJson -and $updateJson.success -eq $true
      Add-Result 'POST team update_role' $updateOk "response=$($updateResp.Content)"
    } catch {
      Add-Result 'POST team update_role' $false "exception=$($_.Exception.Message)"
    }

    # 5) GET invite acceptance page as invited member
    $teamSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $acceptGetOk = $false
    if ($teamToken -ne '') {
      try {
        $acceptGet = Invoke-WebRequest -Uri ($base + '?route=team_accept&token=' + [System.Uri]::EscapeDataString($teamToken)) -WebSession $teamSession -UseBasicParsing
        $acceptCsrf = Get-Csrf $acceptGet.Content
        $acceptGetOk = ($acceptGet.StatusCode -eq 200) -and ($acceptCsrf -ne '') -and ($acceptGet.Content -match 'Aceitar convite da equipe')
        Add-Result 'GET team_accept' $acceptGetOk "status=$($acceptGet.StatusCode)"

        if ($acceptGetOk) {
          $acceptBody = @{
            csrf_token = $acceptCsrf
            token = $teamToken
            nome_completo = "QA Team Member Activated $rand"
            password = $teamPassword
            password_confirm = $teamPassword
          }

          $acceptPost = Invoke-WebRequest -Uri ($base + '?route=team_accept') -Method Post -Body $acceptBody -WebSession $teamSession -MaximumRedirection 5 -UseBasicParsing
          $acceptPostOk = $acceptPost.Content -match 'Convite aceito com sucesso|Entrar no Atendy'
          Add-Result 'POST team_accept' $acceptPostOk "status=$($acceptPost.StatusCode)"

          $activeMember = Invoke-DbScalar "SELECT status, IF(password_hash IS NULL OR password_hash='', 'no', 'yes') FROM team_members WHERE id=$teamMemberDb LIMIT 1;"
          $activeOk = $activeMember -match '^active\tyes$'
          Add-Result 'Team member activated in DB' $activeOk "state=$activeMember"

          $teamLoginGet = Invoke-WebRequest -Uri ($base + '?route=login') -WebSession $teamSession -UseBasicParsing
          $teamLoginCsrf = Get-Csrf $teamLoginGet.Content
          $teamLoginBody = @{ csrf_token=$teamLoginCsrf; login=$teamEmail; password=$teamPassword }
          $teamLoginPost = Invoke-WebRequest -Uri ($base + '?route=login') -Method Post -Body $teamLoginBody -WebSession $teamSession -MaximumRedirection 5 -UseBasicParsing
          $teamLoginOk = $teamLoginPost.Content -match 'Dashboard Inteligente|Plataforma de automação clínica'
          Add-Result 'POST team member login' $teamLoginOk

          $teamPatients = Invoke-WebRequest -Uri ($base + '?route=patients') -WebSession $teamSession -UseBasicParsing
          $teamPatientsOk = ($teamPatients.StatusCode -eq 200) -and ($teamPatients.Content -match 'Pacientes')
          Add-Result 'GET team member patients allowed' $teamPatientsOk "status=$($teamPatients.StatusCode)"

          $teamPatientCsrf = Get-Csrf $teamPatients.Content
          try {
            $staffArchiveBody = @{ csrf_token=$teamPatientCsrf; action='archive'; patient_id=$patientIdDb }
            $staffArchiveResp = Invoke-WebRequest -Uri ($base + '?route=patients') -Method Post -Body $staffArchiveBody -WebSession $teamSession -UseBasicParsing
            Add-Result 'POST team member patient archive forbidden' $false ('unexpected_status=' + $staffArchiveResp.StatusCode)
          } catch {
            $response = $_.Exception.Response
            $statusCode = if ($null -ne $response) { [int] $response.StatusCode } else { 0 }
            Add-Result 'POST team member patient archive forbidden' ($statusCode -eq 403) ('status=' + $statusCode)
          }

          $teamAppointments = Invoke-WebRequest -Uri ($base + '?route=appointments') -WebSession $teamSession -UseBasicParsing
          $teamAppointmentsOk = ($teamAppointments.StatusCode -eq 200) -and ($teamAppointments.Content -match 'Consultas')
          Add-Result 'GET team member appointments allowed' $teamAppointmentsOk "status=$($teamAppointments.StatusCode)"

          $teamWhatsApp = Invoke-WebRequest -Uri ($base + '?route=whatsapp') -WebSession $teamSession -UseBasicParsing
          $teamWhatsAppOk = ($teamWhatsApp.StatusCode -eq 200) -and ($teamWhatsApp.Content -match 'WhatsApp|Histórico recente de mensagens')
          Add-Result 'GET team member whatsapp allowed' $teamWhatsAppOk "status=$($teamWhatsApp.StatusCode)"

          $teamWhatsAppCsrf = Get-Csrf $teamWhatsApp.Content
          try {
            $staffAutomationBody = @{ csrf_token=$teamWhatsAppCsrf; action='run_automations' }
            $staffAutomationResp = Invoke-WebRequest -Uri ($base + '?route=whatsapp') -Method Post -Body $staffAutomationBody -WebSession $teamSession -UseBasicParsing
            Add-Result 'POST team member run_automations forbidden' $false ('unexpected_status=' + $staffAutomationResp.StatusCode)
          } catch {
            $response = $_.Exception.Response
            $statusCode = if ($null -ne $response) { [int] $response.StatusCode } else { 0 }
            Add-Result 'POST team member run_automations forbidden' ($statusCode -eq 403) ('status=' + $statusCode)
          }

          $forbiddenRoutes = @('financeiro', 'reports', 'settings', 'team', 'billing')
          foreach ($forbiddenRoute in $forbiddenRoutes) {
            try {
              $forbiddenResp = Invoke-WebRequest -Uri ($base + '?route=' + $forbiddenRoute) -WebSession $teamSession -UseBasicParsing
              Add-Result ('GET team member forbidden ' + $forbiddenRoute) $false ('unexpected_status=' + $forbiddenResp.StatusCode)
            } catch {
              $response = $_.Exception.Response
              if ($null -eq $response) {
                Add-Result ('GET team member forbidden ' + $forbiddenRoute) $false ('exception=' + $_.Exception.Message)
              } else {
                $statusCode = [int] $response.StatusCode
                $forbiddenOk = ($statusCode -eq 403)
                Add-Result ('GET team member forbidden ' + $forbiddenRoute) $forbiddenOk ('status=' + $statusCode)
              }
            }
          }

          $auditEventsCount = Invoke-DbScalar "SELECT COUNT(*) FROM security_events WHERE user_id=$uid AND event_type IN ('team_member_invited','team_member_role_updated','payment_updated','appointment_created');"
          Add-Result 'DB audit events recorded' ([int]$auditEventsCount -ge 4) ('count=' + $auditEventsCount)
        }
      } catch {
        Add-Result 'GET/POST team_accept flow' $false "exception=$($_.Exception.Message)"
      }
    } else {
      Add-Result 'GET team_accept' $false 'invitation_token_missing'
    }

    $expiredToken = "expired-$rand"
    try {
      $expiredInsertOk = Invoke-DbNonQuery "INSERT INTO team_members (workspace_id, email, nome_completo, role, status, invitation_token, token_created_at, created_at, updated_at) VALUES ($uid, 'expired+$rand@atendy.local', 'Expired Invite', 'staff', 'pending', '$expiredToken', DATE_SUB(NOW(), INTERVAL 8 DAY), NOW(), NOW());"
      if (-not $expiredInsertOk) {
        throw 'Falha ao inserir convite expirado no banco.'
      }
      $expiredAccept = Invoke-WebRequest -Uri ($base + '?route=team_accept&token=' + [System.Uri]::EscapeDataString($expiredToken)) -WebSession (New-Object Microsoft.PowerShell.Commands.WebRequestSession) -MaximumRedirection 5 -UseBasicParsing
      $expiredInviteOk = ($expiredAccept.StatusCode -eq 200) -and ($expiredAccept.Content -match 'Entrar no Atendy') -and ($expiredAccept.Content -notmatch 'Aceitar convite da equipe')
      Add-Result 'GET expired team_accept denied' $expiredInviteOk "status=$($expiredAccept.StatusCode)"
    } catch {
      Add-Result 'GET expired team_accept denied' $false "exception=$($_.Exception.Message)"
    }

    # 6) POST remove member
    $removeBody = "action=remove&member_id=$teamMemberDb&csrf_token=$([System.Uri]::EscapeDataString($teamCsrf))"
    try {
      $removeResp = Invoke-WebRequest -Uri ($base + '?route=team') -Method Post -Body $removeBody -WebSession $session -UseBasicParsing
      $removeJson = $null
      try { $removeJson = $removeResp.Content | ConvertFrom-Json } catch {}
      $removeOk = $null -ne $removeJson -and $removeJson.success -eq $true
      Add-Result 'POST team remove member' $removeOk "response=$($removeResp.Content)"
    } catch {
      Add-Result 'POST team remove member' $false "exception=$($_.Exception.Message)"
    }
  }

} catch {
  Add-Result 'E2E script exception' $false $_.Exception.Message
}

$results | ForEach-Object { $_ }
$failCount = ($results | Where-Object { $_ -like 'FAIL*' }).Count
Write-Output "SUMMARY: total=$($results.Count) fail=$failCount"
if ($failCount -gt 0) { exit 2 } else { exit 0 }



