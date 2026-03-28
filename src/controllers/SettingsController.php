<?php

declare(strict_types=1);

final class SettingsController
{
    private WhatsAppService $service;

    public function __construct()
    {
        $this->service = new WhatsAppService();
    }

    public function index(): void
    {
        $userId = (int) (Auth::user()['id'] ?? 0);
        $settings = $this->getOrCreateSettings($userId);

        $isCloudReady = (string) ($settings['whatsapp_mode'] ?? 'mock') === 'cloud'
            && trim((string) ($settings['whatsapp_phone_number_id'] ?? '')) !== ''
            && trim((string) ($settings['token_whatsapp'] ?? '')) !== '';

        $webhookUrl = $this->currentWebhookUrl();

        $checklist = [
            'phone_number_id' => trim((string) ($settings['whatsapp_phone_number_id'] ?? '')) !== '',
            'access_token' => trim((string) ($settings['token_whatsapp'] ?? '')) !== '',
            'verify_token' => trim((string) ($settings['whatsapp_verify_token'] ?? '')) !== '',
        ];
        $legalService = new LegalService();

        $observability = (new ObservabilityService())->buildWorkspaceOverview($userId);
        $observabilityStatus = (new ObservabilityService())->latestWorkspaceStatus($userId);

        View::render('settings/index', [
            'settings'    => $settings,
            'message'     => $_GET['message'] ?? null,
            'isCloudReady'=> $isCloudReady,
            'webhookUrl'  => $webhookUrl,
            'checklist'   => $checklist,
            'autoReplies' => $this->getAutoReplies($userId),
            'blockedDates' => $this->getBlockedDates($userId),
            'recentLoginAttempts' => $this->getRecentLoginAttempts($userId),
            'recentSecurityEvents' => $this->getRecentSecurityEvents($userId),
            'healthReport' => (new HealthReport())->generate(),
            'recentAppErrors' => $this->getRecentAppErrors(),
            'recentBackups' => (new BackupService())->listRecentBackups(),
            'observability' => $observability,
            'observabilityStatus' => $observabilityStatus,
            'legalCompliance' => $legalService->complianceStatus($userId),
            'legalLinks' => $legalService->legalLinks(),
        ]);
    }

    public function acceptLegal(): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            redirect(base_url('route=settings&message=Token inválido'));
        }

        $userId = (int) (Auth::user()['id'] ?? 0);
        if ($userId <= 0) {
            redirect(base_url('route=login'));
        }

        (new LegalService())->registerConsent(
            $userId,
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')
        );
        audit_log_event($userId, 'legal_consent_registered', 'Aceite legal registrado no painel de configurações.');
        redirect(base_url('route=settings&message=' . urlencode('Aceite legal atualizado com sucesso.')));
    }

    public function createBackup(): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            redirect(base_url('route=settings&message=Token inválido'));
        }

        $userId = (int) (Auth::user()['id'] ?? 0);

        try {
            $result = (new BackupService())->createDatabaseBackup();
        } catch (Throwable $e) {
            AppLogger::error('Backup generation failed in settings', ['message' => $e->getMessage()]);
            redirect(base_url('route=settings&message=' . urlencode('Falha ao gerar backup: ' . $e->getMessage())));
        }

        audit_log_event($userId, 'database_backup_created', 'Backup gerado: ' . (string) ($result['file_name'] ?? 'desconhecido') . '.');

        $filePath = (string) ($result['file_path'] ?? '');
        $fileName = (string) ($result['file_name'] ?? 'backup.sql');
        if ($filePath === '' || !is_file($filePath)) {
            redirect(base_url('route=settings&message=' . urlencode('Backup gerado, mas arquivo não foi encontrado para download.')));
        }

        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $fileName) . '"');
        header('Content-Length: ' . (string) filesize($filePath));
        readfile($filePath);
        exit;
    }

    public function save(): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            redirect(base_url('route=settings&message=Token inválido'));
        }

        $userId = (int) (Auth::user()['id'] ?? 0);
        try {
            $this->saveSettings($userId, $_POST);
        } catch (RuntimeException $e) {
            redirect(base_url('route=settings&message=' . urlencode($e->getMessage())));
        }

        audit_log_event($userId, 'settings_updated', 'Configurações gerais atualizadas.');

        redirect(base_url('route=settings&message=Configurações salvas com sucesso'));
    }

    public function previewTemplate(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Token CSRF inválido.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $userId = (int) (Auth::user()['id'] ?? 0);
        if ($userId <= 0) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Usuário não autenticado.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $templateKey = trim((string) ($_POST['template_key'] ?? ''));
        $rawTemplate = trim((string) ($_POST['template_text'] ?? ''));

        $defaults = [
            'mensagem_confirmacao' => 'Olá {{nome}}! Sua consulta será em {{data_hora}}. Responda SIM para confirmar.',
            'template_lembrete_12h' => 'Olá {{nome}}! Lembrete: sua consulta é em cerca de 12 horas. Data: {{data_hora}}',
            'template_lembrete_2h' => 'Olá {{nome}}! Lembrete: sua consulta é em cerca de 2 horas. Data: {{data_hora}}',
            'template_followup_falta' => 'Oi {{nome}}! Sentimos sua falta na consulta. Quer reagendar?',
            'template_followup_cancelamento' => 'Olá {{nome}}! Podemos te ajudar a remarcar sua consulta?',
            'template_followup_inatividade' => 'Oi {{nome}}! Faz um tempo que você não agenda consulta. Quer ver horários disponíveis?',
        ];

        if (!array_key_exists($templateKey, $defaults)) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Template inválido para pré-visualização.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $templateText = $rawTemplate !== '' ? $rawTemplate : $defaults[$templateKey];

        $sampleVars = [
            'nome' => 'Maria Oliveira',
            'data_hora' => date('d/m/Y H:i', strtotime('+1 day 14:30')),
            'procedimento' => 'Avaliação odontológica',
            'status' => 'confirmada',
        ];

        $rendered = $this->service->renderTemplatePreview($userId, $templateText, $sampleVars);

        echo json_encode([
            'success' => true,
            'rendered' => $rendered,
            'sample' => $sampleVars,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function testConnection(): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            redirect(base_url('route=settings&message=Token inválido'));
        }

        $userId = (int) (Auth::user()['id'] ?? 0);
        try {
            $this->saveSettings($userId, $_POST);
        } catch (RuntimeException $e) {
            redirect(base_url('route=settings&message=' . urlencode($e->getMessage())));
        }

        $testNumber = preg_replace('/\D+/', '', (string) ($_POST['test_phone'] ?? ''));
        if ($testNumber === '') {
            redirect(base_url('route=settings&message=Informe um número para teste'));
        }

        if (strlen($testNumber) === 11) {
            $settings = $this->getOrCreateSettings($userId);
            $country = preg_replace('/\D+/', '', (string) ($settings['whatsapp_default_country'] ?? '55')) ?: '55';
            $testNumber = $country . $testNumber;
        }

        $config = $this->service->getWhatsAppConfig($userId);
        $client = new WhatsAppCloudClient();
        $result = $client->sendTextMessage($config, $testNumber, 'Teste de conexão do Atendy: integração ativa.');

        if (($result['success'] ?? false) === true) {
            redirect(base_url('route=settings&message=Teste enviado com sucesso. Verifique seu WhatsApp.'));
        }

        $error = (string) ($result['error'] ?? 'Falha ao testar conexão');
        redirect(base_url('route=settings&message=' . urlencode('Falha no teste: ' . $error)));
    }

    public function changePassword(): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            redirect(base_url('route=settings&message=Token inválido'));
        }

        $userId = (int) (Auth::user()['id'] ?? 0);
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $newPasswordConfirm = (string) ($_POST['new_password_confirm'] ?? '');

        if ($currentPassword === '' || $newPassword === '' || $newPasswordConfirm === '') {
            redirect(base_url('route=settings&message=' . urlencode('Preencha todos os campos de senha.')));
        }

        if (strlen($newPassword) < 8) {
            redirect(base_url('route=settings&message=' . urlencode('A nova senha deve ter no mínimo 8 caracteres.')));
        }

        if (!hash_equals($newPassword, $newPasswordConfirm)) {
            redirect(base_url('route=settings&message=' . urlencode('A confirmação da nova senha não confere.')));
        }

        $db = Database::connection();
        $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($currentPassword, (string) $user['password_hash'])) {
            redirect(base_url('route=settings&message=' . urlencode('Senha atual inválida.')));
        }

        if (password_verify($newPassword, (string) $user['password_hash'])) {
            redirect(base_url('route=settings&message=' . urlencode('A nova senha deve ser diferente da senha atual.')));
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $update = $db->prepare('UPDATE users SET password_hash = :password_hash, updated_at = NOW() WHERE id = :id');
        $update->execute([
            'password_hash' => $newHash,
            'id' => $userId,
        ]);

        // Invalida sessões antigas (outros dispositivos) após troca de senha
        $this->bumpSessionVersion($userId);
        $this->refreshSessionUser($userId);
        $this->logSecurityEvent($userId, 'password_changed', 'Senha alterada no painel de configurações.');
        audit_log_event($userId, 'password_changed_panel', 'Senha alterada pelo painel.');

        redirect(base_url('route=settings&message=' . urlencode('Senha alterada com sucesso.')));
    }

    public function logoutAllSessions(): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            redirect(base_url('route=settings&message=Token inválido'));
        }

        $userId = (int) (Auth::user()['id'] ?? 0);
        if ($userId <= 0) {
            redirect(base_url('route=login'));
        }

        $this->bumpSessionVersion($userId);

        // Mantém a sessão atual ativa (atualiza versão na sessão)
        $this->refreshSessionUser($userId);
        $this->logSecurityEvent($userId, 'logout_all_sessions', 'Usuário encerrou sessões ativas.');
        audit_log_event($userId, 'sessions_invalidated', 'Todas as sessões foram encerradas.');

        redirect(base_url('route=settings&message=' . urlencode('Sessões ativas encerradas com sucesso.')));
    }

    private function getOrCreateSettings(int $userId): array
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM settings WHERE user_id = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $settings = $stmt->fetch();

        if ($settings) {
            return $settings;
        }

        $verifyToken = $this->generateVerifyToken($userId);

        $insert = $db->prepare(
            'INSERT INTO settings (
                user_id, horario_abertura, horario_fechamento, duracao_consulta, intervalo,
                mensagem_confirmacao, whatsapp_mode, whatsapp_api_url, whatsapp_verify_token, whatsapp_default_country,
                template_lembrete_12h, template_lembrete_2h, template_followup_falta, template_followup_cancelamento, template_followup_inatividade,
                created_at
             )
             VALUES (
                :user_id, "08:00:00", "18:00:00", 60, 10,
                :mensagem_confirmacao, "cloud", "https://graph.facebook.com/v20.0", :verify_token, "55",
                :template_lembrete_12h, :template_lembrete_2h, :template_followup_falta, :template_followup_cancelamento, :template_followup_inatividade,
                NOW()
             )'
        );
        $insert->execute([
            'user_id' => $userId,
            'verify_token' => $verifyToken,
            'mensagem_confirmacao' => 'Olá {{nome}}! Sua consulta será em {{data_hora}}. Responda SIM para confirmar.',
            'template_lembrete_12h' => 'Olá {{nome}}! Lembrete: sua consulta é em cerca de 12 horas. Data: {{data_hora}}',
            'template_lembrete_2h' => 'Olá {{nome}}! Lembrete: sua consulta é em cerca de 2 horas. Data: {{data_hora}}',
            'template_followup_falta' => 'Oi {{nome}}! Sentimos sua falta na consulta. Quer reagendar?',
            'template_followup_cancelamento' => 'Olá {{nome}}! Podemos te ajudar a remarcar sua consulta?',
            'template_followup_inatividade' => 'Oi {{nome}}! Faz um tempo que você não agenda consulta. Quer ver horários disponíveis?',
        ]);

        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetch() ?: [];
    }

    private function saveSettings(int $userId, array $input): void
    {
        $current = $this->getOrCreateSettings($userId);

        $mode = (string) ($input['whatsapp_mode'] ?? ($current['whatsapp_mode'] ?? 'cloud'));
        $apiUrl = trim((string) ($input['whatsapp_api_url'] ?? 'https://graph.facebook.com/v20.0'));
        $phoneNumberId = trim((string) ($input['whatsapp_phone_number_id'] ?? ($current['whatsapp_phone_number_id'] ?? '')));
        $submittedAccessToken = array_key_exists('token_whatsapp', $input)
            ? trim((string) $input['token_whatsapp'])
            : null;
        $accessToken = $submittedAccessToken !== null && $submittedAccessToken !== ''
            ? $submittedAccessToken
            : trim((string) ($current['token_whatsapp'] ?? ''));
        $verifyToken = trim((string) ($input['whatsapp_verify_token'] ?? ($current['whatsapp_verify_token'] ?? '')));
        $defaultCountry = preg_replace('/\D+/', '', (string) ($input['whatsapp_default_country'] ?? '55'));

        $horarioAbertura = trim((string) ($input['horario_abertura'] ?? ($current['horario_abertura'] ?? '08:00:00')));
        $horarioFechamento = trim((string) ($input['horario_fechamento'] ?? ($current['horario_fechamento'] ?? '18:00:00')));
        $duracaoConsulta = (int) ($input['duracao_consulta'] ?? ($current['duracao_consulta'] ?? 60));
        $intervalo = (int) ($input['intervalo'] ?? ($current['intervalo'] ?? 10));
        $metaConversaoMensal = (float) ($input['meta_conversao_mensal'] ?? ($current['meta_conversao_mensal'] ?? 60.0));

        $mensagemConfirmacao = trim((string) ($input['mensagem_confirmacao'] ?? ($current['mensagem_confirmacao'] ?? 'Olá {{nome}}! Sua consulta será em {{data_hora}}. Responda SIM para confirmar.')));
        $templateLembrete12h = trim((string) ($input['template_lembrete_12h'] ?? ($current['template_lembrete_12h'] ?? 'Olá {{nome}}! Lembrete: sua consulta é em cerca de 12 horas. Data: {{data_hora}}')));
        $templateLembrete2h = trim((string) ($input['template_lembrete_2h'] ?? ($current['template_lembrete_2h'] ?? 'Olá {{nome}}! Lembrete: sua consulta é em cerca de 2 horas. Data: {{data_hora}}')));
        $templateFollowupFalta = trim((string) ($input['template_followup_falta'] ?? ($current['template_followup_falta'] ?? 'Oi {{nome}}! Sentimos sua falta na consulta. Quer reagendar?')));
        $templateFollowupCancelamento = trim((string) ($input['template_followup_cancelamento'] ?? ($current['template_followup_cancelamento'] ?? 'Olá {{nome}}! Podemos te ajudar a remarcar sua consulta?')));
        $templateFollowupInatividade = trim((string) ($input['template_followup_inatividade'] ?? ($current['template_followup_inatividade'] ?? 'Oi {{nome}}! Faz um tempo que você não agenda consulta. Quer ver horários disponíveis?')));

        if (!in_array($mode, ['mock', 'cloud'], true)) {
            throw new RuntimeException('Modo inválido');
        }

        if ($apiUrl === '') {
            $apiUrl = 'https://graph.facebook.com/v20.0';
        }

        if ($defaultCountry === '') {
            $defaultCountry = '55';
        }

        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $horarioAbertura)) {
            throw new RuntimeException('Horário de abertura inválido');
        }

        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $horarioFechamento)) {
            throw new RuntimeException('Horário de fechamento inválido');
        }

        if ($duracaoConsulta < 10 || $duracaoConsulta > 240) {
            throw new RuntimeException('Duração da consulta deve estar entre 10 e 240 minutos');
        }

        if ($intervalo < 0 || $intervalo > 120) {
            throw new RuntimeException('Intervalo deve estar entre 0 e 120 minutos');
        }

        if ($metaConversaoMensal < 0.1 || $metaConversaoMensal > 200.0) {
            throw new RuntimeException('Meta de conversão deve estar entre 0,1% e 200%');
        }

        $aberturaComparable = strlen($horarioAbertura) === 5 ? ($horarioAbertura . ':00') : $horarioAbertura;
        $fechamentoComparable = strlen($horarioFechamento) === 5 ? ($horarioFechamento . ':00') : $horarioFechamento;
        if ($aberturaComparable >= $fechamentoComparable) {
            throw new RuntimeException('O horário de abertura deve ser anterior ao de fechamento');
        }

        if ($verifyToken === '') {
            $verifyToken = $this->generateVerifyToken($userId);
        }

        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO settings (
                user_id, whatsapp_mode, whatsapp_api_url, whatsapp_phone_number_id, token_whatsapp, whatsapp_verify_token, whatsapp_default_country,
                horario_abertura, horario_fechamento, duracao_consulta, intervalo,
                mensagem_confirmacao, template_lembrete_12h, template_lembrete_2h, template_followup_falta, template_followup_cancelamento, template_followup_inatividade,
                meta_conversao_mensal, updated_at
             )
             VALUES (
                :user_id, :whatsapp_mode, :whatsapp_api_url, :whatsapp_phone_number_id, :token_whatsapp, :whatsapp_verify_token, :whatsapp_default_country,
                :horario_abertura, :horario_fechamento, :duracao_consulta, :intervalo,
                :mensagem_confirmacao, :template_lembrete_12h, :template_lembrete_2h, :template_followup_falta, :template_followup_cancelamento, :template_followup_inatividade,
                :meta_conversao_mensal, NOW()
             )
             ON DUPLICATE KEY UPDATE
                 whatsapp_mode = VALUES(whatsapp_mode),
                 whatsapp_api_url = VALUES(whatsapp_api_url),
                 whatsapp_phone_number_id = VALUES(whatsapp_phone_number_id),
                 token_whatsapp = VALUES(token_whatsapp),
                 whatsapp_verify_token = VALUES(whatsapp_verify_token),
                 whatsapp_default_country = VALUES(whatsapp_default_country),
                 horario_abertura = VALUES(horario_abertura),
                 horario_fechamento = VALUES(horario_fechamento),
                 duracao_consulta = VALUES(duracao_consulta),
                 intervalo = VALUES(intervalo),
                 mensagem_confirmacao = VALUES(mensagem_confirmacao),
                 template_lembrete_12h = VALUES(template_lembrete_12h),
                 template_lembrete_2h = VALUES(template_lembrete_2h),
                 template_followup_falta = VALUES(template_followup_falta),
                 template_followup_cancelamento = VALUES(template_followup_cancelamento),
                 template_followup_inatividade = VALUES(template_followup_inatividade),
                 meta_conversao_mensal = VALUES(meta_conversao_mensal),
                 updated_at = NOW()'
        );

        $stmt->execute([
            'user_id' => $userId,
            'whatsapp_mode' => $mode,
            'whatsapp_api_url' => $apiUrl,
            'whatsapp_phone_number_id' => $phoneNumberId !== '' ? $phoneNumberId : null,
            'token_whatsapp' => $accessToken !== '' ? $accessToken : null,
            'whatsapp_verify_token' => $verifyToken,
            'whatsapp_default_country' => $defaultCountry,
            'horario_abertura' => $horarioAbertura,
            'horario_fechamento' => $horarioFechamento,
            'duracao_consulta' => $duracaoConsulta,
            'intervalo' => $intervalo,
            'mensagem_confirmacao' => $mensagemConfirmacao,
            'template_lembrete_12h' => $templateLembrete12h,
            'template_lembrete_2h' => $templateLembrete2h,
            'template_followup_falta' => $templateFollowupFalta,
            'template_followup_cancelamento' => $templateFollowupCancelamento,
            'template_followup_inatividade' => $templateFollowupInatividade,
            'meta_conversao_mensal' => $metaConversaoMensal,
        ]);
    }

    public function saveAutoReply(): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            redirect(base_url('route=settings&message=Token inválido'));
        }

        $userId  = (int) (Auth::user()['id'] ?? 0);
        $keyword = trim((string) ($_POST['keyword'] ?? ''));
        $reply   = trim((string) ($_POST['reply'] ?? ''));

        if ($keyword === '' || $reply === '') {
            redirect(base_url('route=settings&message=' . urlencode('Preencha a palavra-chave e a resposta.')));
        }

        if (mb_strlen($keyword, 'UTF-8') > 100) {
            redirect(base_url('route=settings&message=' . urlencode('Palavra-chave muito longa (máx 100 caracteres).')));
        }

        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO whatsapp_auto_replies (user_id, keyword, reply, is_active, sort_order, created_at)
             VALUES (:user_id, :keyword, :reply, 1, 0, NOW())
             ON DUPLICATE KEY UPDATE reply = VALUES(reply), is_active = 1, updated_at = NOW()'
        );
        $stmt->execute([
            'user_id' => $userId,
            'keyword' => mb_strtolower($keyword, 'UTF-8'),
            'reply'   => $reply,
        ]);

        audit_log_event($userId, 'auto_reply_saved', 'Resposta automática salva para a palavra-chave ' . mb_strtolower($keyword, 'UTF-8') . '.');

        redirect(base_url('route=settings&message=' . urlencode('Resposta automática salva com sucesso.')));
    }

    public function deleteAutoReply(): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            redirect(base_url('route=settings&message=Token inválido'));
        }

        $userId = (int) (Auth::user()['id'] ?? 0);
        $id     = (int) ($_POST['reply_id'] ?? 0);

        if ($id <= 0) {
            redirect(base_url('route=settings&message=' . urlencode('ID inválido.')));
        }

        $db = Database::connection();
        $stmt = $db->prepare('DELETE FROM whatsapp_auto_replies WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);

        if ($stmt->rowCount() > 0) {
            audit_log_event($userId, 'auto_reply_deleted', 'Resposta automática #' . $id . ' removida.');
        }

        redirect(base_url('route=settings&message=' . urlencode('Resposta removida.')));
    }

    public function addBlockedDate(): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            redirect(base_url('route=settings&message=Token inválido'));
        }

        $userId = (int) (Auth::user()['id'] ?? 0);
        $blockedDate = trim((string) ($_POST['blocked_date'] ?? ''));
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $startTime = trim((string) ($_POST['start_time'] ?? ''));
        $endTime = trim((string) ($_POST['end_time'] ?? ''));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $blockedDate)) {
            redirect(base_url('route=settings&message=' . urlencode('Data bloqueada inválida.')));
        }

        $hasWindow = ($startTime !== '' || $endTime !== '');
        if ($hasWindow) {
            if (!preg_match('/^\d{2}:\d{2}$/', $startTime) || !preg_match('/^\d{2}:\d{2}$/', $endTime)) {
                redirect(base_url('route=settings&message=' . urlencode('Horário de bloqueio inválido.')));
            }

            if (strtotime($startTime) >= strtotime($endTime)) {
                redirect(base_url('route=settings&message=' . urlencode('Horário inicial deve ser menor que o horário final.')));
            }
        }

        $timeColumnsAvailable = $this->hasBlockedTimeColumns();

        if ($timeColumnsAvailable) {
            $stmt = Database::connection()->prepare(
                'INSERT INTO clinic_blocked_dates (user_id, blocked_date, start_time, end_time, reason, is_active, created_at, updated_at)
                 VALUES (:user_id, :blocked_date, :start_time, :end_time, :reason, 1, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE
                    start_time = VALUES(start_time),
                    end_time = VALUES(end_time),
                    reason = VALUES(reason),
                    is_active = 1,
                    updated_at = NOW()'
            );
            $stmt->execute([
                'user_id' => $userId,
                'blocked_date' => $blockedDate,
                'start_time' => $hasWindow ? $startTime . ':00' : null,
                'end_time' => $hasWindow ? $endTime . ':00' : null,
                'reason' => $reason !== '' ? $reason : null,
            ]);

            $msg = $hasWindow ? 'Bloqueio de horário salvo com sucesso.' : 'Data bloqueada salva com sucesso.';
            audit_log_event($userId, 'blocked_date_saved', 'Bloqueio salvo em ' . $blockedDate . ($hasWindow ? ' ' . $startTime . '-' . $endTime : ' dia inteiro') . '.');
            redirect(base_url('route=settings&message=' . urlencode($msg)));
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO clinic_blocked_dates (user_id, blocked_date, reason, is_active, created_at, updated_at)
             VALUES (:user_id, :blocked_date, :reason, 1, NOW(), NOW())
             ON DUPLICATE KEY UPDATE reason = VALUES(reason), is_active = 1, updated_at = NOW()'
        );
        $stmt->execute([
            'user_id' => $userId,
            'blocked_date' => $blockedDate,
            'reason' => $reason !== '' ? $reason : null,
        ]);

        if ($hasWindow) {
            audit_log_event($userId, 'blocked_date_saved', 'Bloqueio salvo em ' . $blockedDate . ' como dia inteiro por ausência de colunas de faixa horária.');
            redirect(base_url('route=settings&message=' . urlencode('Horários por faixa requerem migração do banco. Bloqueio salvo como dia inteiro.')));
        }

        audit_log_event($userId, 'blocked_date_saved', 'Data bloqueada salva em ' . $blockedDate . '.');
        redirect(base_url('route=settings&message=' . urlencode('Data bloqueada salva com sucesso.')));
    }

    public function deleteBlockedDate(): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            redirect(base_url('route=settings&message=Token inválido'));
        }

        $userId = (int) (Auth::user()['id'] ?? 0);
        $id = (int) ($_POST['blocked_date_id'] ?? 0);
        if ($id <= 0) {
            redirect(base_url('route=settings&message=' . urlencode('ID inválido.')));
        }

        $stmt = Database::connection()->prepare(
            'UPDATE clinic_blocked_dates
             SET is_active = 0, updated_at = NOW()
             WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute([
            'id' => $id,
            'user_id' => $userId,
        ]);

        if ($stmt->rowCount() > 0) {
            audit_log_event($userId, 'blocked_date_deleted', 'Bloqueio #' . $id . ' removido.');
        }

        redirect(base_url('route=settings&message=' . urlencode('Data bloqueada removida.')));
    }

    private function getAutoReplies(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, keyword, reply, is_active, sort_order
             FROM whatsapp_auto_replies
             WHERE user_id = :user_id
             ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    private function getBlockedDates(int $userId): array
    {
        $sql = $this->hasBlockedTimeColumns()
            ? 'SELECT id, blocked_date, start_time, end_time, reason, is_active
               FROM clinic_blocked_dates
               WHERE user_id = :user_id AND is_active = 1
               ORDER BY blocked_date ASC'
            : 'SELECT id, blocked_date, NULL AS start_time, NULL AS end_time, reason, is_active
               FROM clinic_blocked_dates
               WHERE user_id = :user_id AND is_active = 1
               ORDER BY blocked_date ASC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    private function hasBlockedTimeColumns(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        try {
            $stmt = Database::connection()->query(
                "SELECT COUNT(*) AS total
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'clinic_blocked_dates'
                   AND COLUMN_NAME IN ('start_time', 'end_time')"
            );
            $row = $stmt ? $stmt->fetch() : null;
            $cached = ((int) ($row['total'] ?? 0)) >= 2;
            return $cached;
        } catch (Throwable $e) {
            $cached = false;
            return false;
        }
    }

    private function getRecentLoginAttempts(int $userId): array
    {
        $db = Database::connection();

        $stmtUser = $db->prepare('SELECT email, cpf FROM users WHERE id = :user_id LIMIT 1');
        $stmtUser->execute(['user_id' => $userId]);
        $user = $stmtUser->fetch();

        if (!$user) {
            return [];
        }

        $email = mb_strtolower((string) ($user['email'] ?? ''), 'UTF-8');
        $cpf = mb_strtolower((string) ($user['cpf'] ?? ''), 'UTF-8');

        $stmt = $db->prepare(
            'SELECT login_identifier, ip_address, success, attempted_at
             FROM login_attempts
             WHERE login_identifier = :email OR login_identifier = :cpf
             ORDER BY attempted_at DESC
             LIMIT 20'
        );
        $stmt->execute([
            'email' => $email,
            'cpf' => $cpf,
        ]);

        return $stmt->fetchAll();
    }

    private function getRecentSecurityEvents(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT event_type, ip_address, details, created_at
             FROM security_events
             WHERE user_id = :user_id
             ORDER BY created_at DESC
             LIMIT 20'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    private function getRecentAppErrors(int $limit = 20): array
    {
        $logFile = dirname(__DIR__, 2) . '/storage/logs/app.log';
        if (!is_file($logFile)) {
            return [];
        }

        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines) || $lines === []) {
            return [];
        }

        $lines = array_reverse($lines);
        $errors = [];

        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            if (!is_array($decoded)) {
                continue;
            }

            $level = (string) ($decoded['level'] ?? '');
            if ($level !== 'error') {
                continue;
            }

            $errors[] = [
                'timestamp' => (string) ($decoded['timestamp'] ?? ''),
                'message' => (string) ($decoded['message'] ?? ''),
                'context' => (array) ($decoded['context'] ?? []),
            ];

            if (count($errors) >= $limit) {
                break;
            }
        }

        return $errors;
    }

    private function generateVerifyToken(int $userId): string
    {
        try {
            $suffix = bin2hex(random_bytes(4));
        } catch (Throwable $e) {
            $suffix = (string) mt_rand(10000000, 99999999);
        }

        return 'atendy_' . $userId . '_' . $suffix;
    }

    private function currentWebhookUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        return $scheme . '://' . $host . base_url('route=whatsapp_webhook');
    }

    private function bumpSessionVersion(int $userId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET session_version = session_version + 1, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['id' => $userId]);
    }

    private function refreshSessionUser(int $userId): void
    {
        if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
            return;
        }

        $stmt = Database::connection()->prepare('SELECT session_version FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch();
        if ($row) {
            $_SESSION['user']['session_version'] = (int) ($row['session_version'] ?? 1);
        }
    }

    private function logSecurityEvent(int $userId, string $eventType, string $details): void
    {
        $ipAddress = trim((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
        $stmt = Database::connection()->prepare(
            'INSERT INTO security_events (user_id, event_type, ip_address, details, created_at)
             VALUES (:user_id, :event_type, :ip_address, :details, NOW())'
        );
        $stmt->execute([
            'user_id' => $userId,
            'event_type' => $eventType,
            'ip_address' => $ipAddress !== '' ? $ipAddress : '0.0.0.0',
            'details' => $details,
        ]);
    }
}


