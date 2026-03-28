<?php

declare(strict_types=1);

final class WhatsAppService
{
    private WhatsAppCloudClient $cloudClient;
    private array $clinicTemplateDataCache = [];

    public function __construct()
    {
        $this->cloudClient = new WhatsAppCloudClient();
    }

    public function sendManualMessage(int $userId, int $patientId, string $text, ?int $appointmentId = null): void
    {
        $status = 'sent';
        $externalId = null;
        $config = $this->getWhatsAppConfig($userId);

        $phone = $this->getPatientWhatsApp($userId, $patientId);
        if ($phone !== null && $this->cloudClient->isEnabled($config)) {
            $result = $this->cloudClient->sendTextMessage($config, $phone, $text);
            $status = (string) ($result['status'] ?? 'failed');
            $externalId = $result['external_id'] ?? null;
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO whatsapp_messages (user_id, patient_id, appointment_id, direction, texto, status, external_message_id, timestamp)
             VALUES (:user_id, :patient_id, :appointment_id, "outbound", :texto, :status, :external_message_id, NOW())'
        );

        $stmt->execute([
            'user_id' => $userId,
            'patient_id' => $patientId,
            'appointment_id' => $appointmentId,
            'texto' => $text,
            'status' => $status,
            'external_message_id' => $externalId,
        ]);
    }

    public function receiveSimulatedInbound(int $userId, int $patientId, string $text): bool
    {
        $this->storeInboundMessage($userId, $patientId, $text, null);

        // Estado conversacional tem prioridade (ex: paciente escolhendo slot)
        if ($this->processConversationState($userId, $patientId, $text)) {
            return false;
        }

        $this->processLeadAutoReply($userId, $patientId, $text);
        return $this->tryConfirmAppointmentByReply($userId, $patientId, $text);
    }

    public function receiveCloudInbound(int $userId, int $patientId, string $text, ?string $externalMessageId): bool
    {
        $this->storeInboundMessage($userId, $patientId, $text, $externalMessageId);

        // Estado conversacional tem prioridade (ex: paciente escolhendo slot)
        if ($this->processConversationState($userId, $patientId, $text)) {
            return false;
        }

        $this->processLeadAutoReply($userId, $patientId, $text);
        return $this->tryConfirmAppointmentByReply($userId, $patientId, $text);
    }

    public function ensurePatientByPhone(int $userId, string $rawPhone): int
    {
        $existing = $this->findPatientIdByPhone($userId, $rawPhone);
        if ($existing !== null) {
            return $existing;
        }

        $normalized = preg_replace('/\D+/', '', $rawPhone) ?? '';
        $last11 = substr($normalized, -11);
        if ($last11 === '') {
            $last11 = '00000000000';
        }

        $name = 'Novo Lead ' . $last11;
        $cpf = $this->generateLeadCpf($userId, $last11);

        $stmt = Database::connection()->prepare(
            'INSERT INTO patients (user_id, nome, whatsapp, email, cpf, status, created_at, updated_at)
             VALUES (:user_id, :nome, :whatsapp, NULL, :cpf, "lead", NOW(), NOW())'
        );

        $stmt->execute([
            'user_id' => $userId,
            'nome' => $name,
            'whatsapp' => $last11,
            'cpf' => $cpf,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public function processCloudStatus(int $userId, string $externalMessageId, string $status): bool
    {
        if ($externalMessageId === '') {
            return false;
        }

        $normalizedStatus = $this->normalizeCloudStatus($status);
        if ($normalizedStatus === null) {
            return false;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE whatsapp_messages
             SET status = :status
             WHERE user_id = :user_id
               AND external_message_id = :external_message_id'
        );

        $stmt->execute([
            'status' => $normalizedStatus,
            'user_id' => $userId,
            'external_message_id' => $externalMessageId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function findPatientIdByPhone(int $userId, string $rawPhone): ?int
    {
        $normalized = preg_replace('/\D+/', '', $rawPhone) ?? '';
        if ($normalized === '') {
            return null;
        }

        $last11 = substr($normalized, -11);
        $stmt = Database::connection()->prepare(
            'SELECT id FROM patients
             WHERE user_id = :user_id
               AND deleted_at IS NULL
               AND (
                   REPLACE(REPLACE(REPLACE(REPLACE(whatsapp, "(", ""), ")", ""), "-", ""), " ", "") = :full_phone
                   OR RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(whatsapp, "(", ""), ")", ""), "-", ""), " ", ""), 11) = :last_11
               )
             LIMIT 1'
        );

        $stmt->execute([
            'user_id' => $userId,
            'full_phone' => $normalized,
            'last_11' => $last11,
        ]);

        $row = $stmt->fetch();
        return $row ? (int) $row['id'] : null;
    }

    public function resolveWebhookUserId(?string $phoneNumberId = null): int
    {
        if ($phoneNumberId !== null && $phoneNumberId !== '') {
            $stmt = Database::connection()->prepare(
                'SELECT user_id FROM settings WHERE whatsapp_phone_number_id = :phone_number_id LIMIT 1'
            );
            $stmt->execute(['phone_number_id' => $phoneNumberId]);
            $row = $stmt->fetch();
            if ($row) {
                return (int) $row['user_id'];
            }
        }

        $configured = (int) env('WHATSAPP_USER_ID', '0');
        if ($configured > 0) {
            return $configured;
        }

        $row = Database::connection()->query('SELECT id FROM users WHERE ativo = 1 ORDER BY id ASC LIMIT 1')->fetch();
        return (int) ($row['id'] ?? 1);
    }

    public function isWebhookVerifyTokenValid(string $token): bool
    {
        $global = (string) env('WHATSAPP_VERIFY_TOKEN', '');
        if ($global !== '' && hash_equals($global, $token)) {
            return true;
        }

        $stmt = Database::connection()->prepare('SELECT user_id FROM settings WHERE whatsapp_verify_token = :token LIMIT 1');
        $stmt->execute(['token' => $token]);
        return (bool) $stmt->fetch();
    }

    public function run24hConfirmations(int $userId): int
    {
        $template = $this->getAutomationTemplate(
            $userId,
            'mensagem_confirmacao',
            'Olá {{nome}}! Sua consulta é em {{data_hora}}. Responda: Sim para confirmar.'
        );

        $db = Database::connection();
        $sql = 'SELECT a.id, a.data_hora, a.patient_id, p.nome
                FROM appointments a
                INNER JOIN patients p ON p.id = a.patient_id
                WHERE a.user_id = :user_id
                  AND a.status = "agendada"
                  AND a.data_hora BETWEEN DATE_ADD(NOW(), INTERVAL 23 HOUR) AND DATE_ADD(NOW(), INTERVAL 25 HOUR)
                  AND NOT EXISTS (
                        SELECT 1 FROM automation_logs al
                        WHERE al.appointment_id = a.id
                          AND al.tipo_automacao = "confirmacao_24h"
                  )';

        $stmt = $db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $appointments = $stmt->fetchAll();

        $count = 0;
        foreach ($appointments as $appointment) {
            $message = $this->renderTemplate($userId, $template, [
                'nome' => (string) $appointment['nome'],
                'data_hora' => date('d/m/Y H:i', strtotime((string) $appointment['data_hora'])),
                'procedimento' => '',
                'status' => 'agendada',
            ]);

            $this->sendManualMessage(
                $userId,
                (int) $appointment['patient_id'],
                $message,
                (int) $appointment['id']
            );

            $log = $db->prepare(
                'INSERT INTO automation_logs (user_id, appointment_id, tipo_automacao, status_envio, detalhes, timestamp)
                 VALUES (:user_id, :appointment_id, "confirmacao_24h", "enviado", :detalhes, NOW())'
            );
            $log->execute([
                'user_id' => $userId,
                'appointment_id' => (int) $appointment['id'],
                'detalhes' => 'Confirmação automática enviada com sucesso.',
            ]);

            $count++;
        }

        return $count;
    }

    public function runReminders(int $userId): int
    {
        $template12h = $this->getAutomationTemplate(
            $userId,
            'template_lembrete_12h',
            'Olá {{nome}}! Lembrete: sua consulta é em cerca de 12 horas. Data: {{data_hora}}'
        );
        $template2h = $this->getAutomationTemplate(
            $userId,
            'template_lembrete_2h',
            'Olá {{nome}}! Lembrete: sua consulta é em cerca de 2 horas. Data: {{data_hora}}'
        );

        $count12h = $this->runReminderByWindow($userId, 11, 13, 'lembrete_12h', $template12h);
        $count2h = $this->runReminderByWindow($userId, 1, 3, 'lembrete_2h', $template2h);

        return $count12h + $count2h;
    }

    public function runFollowUps(int $userId): int
    {
        $db = Database::connection();
        $total = 0;

        $templateFalta = $this->getAutomationTemplate(
            $userId,
            'template_followup_falta',
            'Oi {{nome}}! Sentimos sua falta na consulta. Quer reagendar?'
        );
        $templateCancelamento = $this->getAutomationTemplate(
            $userId,
            'template_followup_cancelamento',
            'Olá {{nome}}! Podemos te ajudar a remarcar sua consulta?'
        );
        $templateInatividade = $this->getAutomationTemplate(
            $userId,
            'template_followup_inatividade',
            'Oi {{nome}}! Faz um tempo que você não agenda consulta. Quer ver horários disponíveis?'
        );

        $sqlNoShow = 'SELECT a.id, a.patient_id, p.nome, a.data_hora
                      FROM appointments a
                      INNER JOIN patients p ON p.id = a.patient_id
                      WHERE a.user_id = :user_id
                        AND a.status = "faltou"
                        AND a.data_hora BETWEEN DATE_SUB(NOW(), INTERVAL 2 DAY) AND DATE_SUB(NOW(), INTERVAL 1 DAY)
                        AND NOT EXISTS (
                            SELECT 1 FROM automation_logs al
                            WHERE al.appointment_id = a.id
                              AND al.tipo_automacao = "followup_falta_d1"
                        )';

        $stmtNoShow = $db->prepare($sqlNoShow);
        $stmtNoShow->execute(['user_id' => $userId]);
        $noShowAppointments = $stmtNoShow->fetchAll();

        foreach ($noShowAppointments as $appointment) {
            $text = $this->renderTemplate($userId, $templateFalta, [
                'nome' => (string) $appointment['nome'],
                'data_hora' => date('d/m/Y H:i', strtotime((string) ($appointment['data_hora'] ?? 'now'))),
                'procedimento' => '',
                'status' => 'faltou',
            ]);
            $this->sendManualMessage($userId, (int) $appointment['patient_id'], $text, (int) $appointment['id']);

            $this->logAutomation(
                $userId,
                (int) $appointment['id'],
                'followup_falta_d1',
                'enviado',
                'Follow-up de falta enviado (+1 dia).'
            );
            $total++;
        }

        $sqlCancelled = 'SELECT a.id, a.patient_id, p.nome
                         FROM appointments a
                         INNER JOIN patients p ON p.id = a.patient_id
                         WHERE a.user_id = :user_id
                           AND a.status = "cancelada"
                           AND a.updated_at BETWEEN DATE_SUB(NOW(), INTERVAL 24 HOUR) AND NOW()
                           AND NOT EXISTS (
                               SELECT 1 FROM automation_logs al
                               WHERE al.appointment_id = a.id
                                 AND al.tipo_automacao = "followup_cancelamento_d1"
                           )';

        $stmtCancelled = $db->prepare($sqlCancelled);
        $stmtCancelled->execute(['user_id' => $userId]);
        $cancelledAppointments = $stmtCancelled->fetchAll();

        foreach ($cancelledAppointments as $appointment) {
            $text = $this->renderTemplate($userId, $templateCancelamento, [
                'nome' => (string) $appointment['nome'],
                'data_hora' => '',
                'procedimento' => '',
                'status' => 'cancelada',
            ]);
            $this->sendManualMessage($userId, (int) $appointment['patient_id'], $text, (int) $appointment['id']);

            $this->logAutomation(
                $userId,
                (int) $appointment['id'],
                'followup_cancelamento_d1',
                'enviado',
                'Follow-up de cancelamento enviado (+1 dia).'
            );
            $total++;
        }

        $sqlInactive = 'SELECT p.id, p.nome
                        FROM patients p
                        LEFT JOIN appointments a ON a.patient_id = p.id AND a.user_id = p.user_id
                        WHERE p.user_id = :user_id AND p.deleted_at IS NULL
                        GROUP BY p.id, p.nome
                        HAVING (MAX(a.data_hora) IS NULL OR MAX(a.data_hora) < DATE_SUB(NOW(), INTERVAL 60 DAY))
                           AND NOT EXISTS (
                               SELECT 1 FROM automation_logs al
                               WHERE al.user_id = :user_id_log
                                 AND al.tipo_automacao = "followup_inatividade_60d"
                                 AND al.detalhes = CONCAT("patient_id:", p.id)
                                 AND al.timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                           )';

        $stmtInactive = $db->prepare($sqlInactive);
        $stmtInactive->execute([
            'user_id' => $userId,
            'user_id_log' => $userId,
        ]);
        $inactivePatients = $stmtInactive->fetchAll();

        foreach ($inactivePatients as $patient) {
            $text = $this->renderTemplate($userId, $templateInatividade, [
                'nome' => (string) $patient['nome'],
                'data_hora' => '',
                'procedimento' => '',
                'status' => 'inativo',
            ]);
            $this->sendManualMessage($userId, (int) $patient['id'], $text, null);

            $this->logAutomation(
                $userId,
                null,
                'followup_inatividade_60d',
                'enviado',
                'patient_id:' . (int) $patient['id']
            );
            $total++;
        }

        return $total;
    }

    public function runAllAutomations(int $userId): array
    {
        $confirmations = $this->run24hConfirmations($userId);
        $reminders = $this->runReminders($userId);
        $followUps = $this->runFollowUps($userId);

        return [
            'confirmations' => $confirmations,
            'reminders' => $reminders,
            'followups' => $followUps,
            'total' => $confirmations + $reminders + $followUps,
        ];
    }

    private function runReminderByWindow(int $userId, int $minHours, int $maxHours, string $automationType, string $template): int
    {
        $db = Database::connection();
        $start = (new DateTime())->modify('+' . $minHours . ' hours')->format('Y-m-d H:i:s');
        $end = (new DateTime())->modify('+' . $maxHours . ' hours')->format('Y-m-d H:i:s');

        $sql = 'SELECT a.id, a.patient_id, a.data_hora, p.nome
                FROM appointments a
                INNER JOIN patients p ON p.id = a.patient_id
                WHERE a.user_id = :user_id
                  AND a.status IN ("agendada", "confirmada")
                                    AND a.data_hora BETWEEN :start_date AND :end_date
                  AND NOT EXISTS (
                      SELECT 1 FROM automation_logs al
                      WHERE al.appointment_id = a.id
                        AND al.tipo_automacao = :tipo_automacao
                  )';

        $stmt = $db->prepare($sql);
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('start_date', $start, PDO::PARAM_STR);
        $stmt->bindValue('end_date', $end, PDO::PARAM_STR);
        $stmt->bindValue('tipo_automacao', $automationType, PDO::PARAM_STR);
        $stmt->execute();
        $appointments = $stmt->fetchAll();

        $count = 0;
        foreach ($appointments as $appointment) {
            $text = $this->renderTemplate($userId, $template, [
                'nome' => (string) $appointment['nome'],
                'data_hora' => date('d/m/Y H:i', strtotime((string) $appointment['data_hora'])),
                'procedimento' => '',
                'status' => 'agendada',
            ]);

            $this->sendManualMessage(
                $userId,
                (int) $appointment['patient_id'],
                $text,
                (int) $appointment['id']
            );

            $this->logAutomation(
                $userId,
                (int) $appointment['id'],
                $automationType,
                'enviado',
                'Lembrete automático enviado.'
            );

            $count++;
        }

        return $count;
    }

    private function logAutomation(int $userId, ?int $appointmentId, string $type, string $status, string $details): void
    {
        $log = Database::connection()->prepare(
            'INSERT INTO automation_logs (user_id, appointment_id, tipo_automacao, status_envio, detalhes, timestamp)
             VALUES (:user_id, :appointment_id, :tipo_automacao, :status_envio, :detalhes, NOW())'
        );

        $log->execute([
            'user_id' => $userId,
            'appointment_id' => $appointmentId,
            'tipo_automacao' => $type,
            'status_envio' => $status,
            'detalhes' => $details,
        ]);
    }

    private function tryConfirmAppointmentByReply(int $userId, int $patientId, string $text): bool
    {
        if (!$this->isPositiveIntent($text)) {
            return false;
        }

        $db = Database::connection();
        $query = 'SELECT id
                  FROM appointments
                  WHERE user_id = :user_id
                    AND patient_id = :patient_id
                    AND status = "agendada"
                    AND data_hora >= NOW()
                  ORDER BY data_hora ASC
                  LIMIT 1';

        $stmt = $db->prepare($query);
        $stmt->execute([
            'user_id' => $userId,
            'patient_id' => $patientId,
        ]);

        $appointment = $stmt->fetch();
        if (!$appointment) {
            return false;
        }

        $update = $db->prepare('UPDATE appointments SET status = "confirmada", confirmacao_timestamp = NOW(), updated_at = NOW() WHERE id = :id');
        $update->execute(['id' => (int) $appointment['id']]);

        $log = $db->prepare(
            'INSERT INTO automation_logs (user_id, appointment_id, tipo_automacao, status_envio, detalhes, timestamp)
             VALUES (:user_id, :appointment_id, "confirmacao_resposta", "processado", :detalhes, NOW())'
        );
        $log->execute([
            'user_id' => $userId,
            'appointment_id' => (int) $appointment['id'],
            'detalhes' => 'Paciente confirmou consulta via mensagem.',
        ]);

        return true;
    }

    private function isPositiveIntent(string $text): bool
    {
        $normalized = mb_strtolower(trim($text), 'UTF-8');
        $keywords = ['sim', 'confirmo', 'confirma', 'ok', 'blz', 'beleza', 'certo', 'positivo'];

        foreach ($keywords as $keyword) {
            if (mb_strpos($normalized, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    private function storeInboundMessage(int $userId, int $patientId, string $text, ?string $externalMessageId): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO whatsapp_messages (user_id, patient_id, direction, texto, status, external_message_id, timestamp)
             VALUES (:user_id, :patient_id, "inbound", :texto, "received", :external_message_id, NOW())'
        );

        $stmt->execute([
            'user_id' => $userId,
            'patient_id' => $patientId,
            'texto' => $text,
            'external_message_id' => $externalMessageId,
        ]);
    }

    private function getPatientWhatsApp(int $userId, int $patientId): ?string
    {
        $stmt = Database::connection()->prepare('SELECT whatsapp FROM patients WHERE id = :id AND user_id = :user_id LIMIT 1');
        $stmt->execute([
            'id' => $patientId,
            'user_id' => $userId,
        ]);

        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $phone = preg_replace('/\D+/', '', (string) $row['whatsapp']) ?? '';
        if ($phone === '') {
            return null;
        }

        if (strlen($phone) === 11) {
            $config = $this->getWhatsAppConfig($userId);
            $country = preg_replace('/\D+/', '', (string) ($config['default_country'] ?? '55')) ?: '55';
            return $country . $phone;
        }

        return $phone;
    }

    public function getWhatsAppConfig(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT whatsapp_mode, whatsapp_api_url, whatsapp_phone_number_id, token_whatsapp, whatsapp_verify_token, whatsapp_default_country
             FROM settings
             WHERE user_id = :user_id
             LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        $settings = $stmt->fetch() ?: [];

        return [
            'mode' => (string) ($settings['whatsapp_mode'] ?? env('WHATSAPP_MODE', 'mock')),
            'api_url' => (string) ($settings['whatsapp_api_url'] ?? env('WHATSAPP_API_URL', 'https://graph.facebook.com/v20.0')),
            'phone_number_id' => (string) ($settings['whatsapp_phone_number_id'] ?? env('WHATSAPP_PHONE_NUMBER_ID', '')),
            'access_token' => (string) ($settings['token_whatsapp'] ?? env('WHATSAPP_ACCESS_TOKEN', '')),
            'verify_token' => (string) ($settings['whatsapp_verify_token'] ?? env('WHATSAPP_VERIFY_TOKEN', '')),
            'default_country' => (string) ($settings['whatsapp_default_country'] ?? env('WHATSAPP_DEFAULT_COUNTRY', '55')),
        ];
    }

    public function renderTemplatePreview(int $userId, string $template, array $vars = []): string
    {
        return $this->renderTemplate($userId, $template, $vars);
    }

    private function normalizeCloudStatus(string $status): ?string
    {
        $value = mb_strtolower(trim($status), 'UTF-8');
        $allowed = ['sent', 'delivered', 'read', 'failed'];

        return in_array($value, $allowed, true) ? $value : null;
    }

    private function getAutomationTemplate(int $userId, string $column, string $default): string
    {
        $allowed = [
            'mensagem_confirmacao',
            'template_lembrete_12h',
            'template_lembrete_2h',
            'template_followup_falta',
            'template_followup_cancelamento',
            'template_followup_inatividade',
        ];

        if (!in_array($column, $allowed, true)) {
            return $default;
        }

        $sql = 'SELECT ' . $column . ' AS template FROM settings WHERE user_id = :user_id LIMIT 1';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();

        $template = trim((string) ($row['template'] ?? ''));
        return $template !== '' ? $template : $default;
    }

    private function renderTemplate(int $userId, string $template, array $vars): string
    {
        $clinicData = $this->getClinicTemplateData($userId);

        $map = [
            '{{nome}}' => (string) ($vars['nome'] ?? ''),
            '{{data_hora}}' => (string) ($vars['data_hora'] ?? ''),
            '{{procedimento}}' => (string) ($vars['procedimento'] ?? ''),
            '{{status}}' => (string) ($vars['status'] ?? ''),
            '{{clinica}}' => (string) ($clinicData['clinica'] ?? ''),
            '{{telefone_clinica}}' => (string) ($clinicData['telefone_clinica'] ?? ''),
            '{{telefone}}' => (string) ($clinicData['telefone_clinica'] ?? ''),
            '{{endereco_clinica}}' => (string) ($clinicData['endereco_clinica'] ?? ''),
            '{{endereco}}' => (string) ($clinicData['endereco_clinica'] ?? ''),
            '{{email_clinica}}' => (string) ($clinicData['email_clinica'] ?? ''),
        ];

        $text = strtr($template, $map);
        return trim($text) !== '' ? $text : $template;
    }

    private function getClinicTemplateData(int $userId): array
    {
        if (isset($this->clinicTemplateDataCache[$userId])) {
            return $this->clinicTemplateDataCache[$userId];
        }

        $stmt = Database::connection()->prepare(
            'SELECT nome_consultorio, telefone, endereco, email
             FROM users
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch() ?: [];

        $data = [
            'clinica' => (string) ($row['nome_consultorio'] ?? ''),
            'telefone_clinica' => (string) ($row['telefone'] ?? ''),
            'endereco_clinica' => (string) ($row['endereco'] ?? ''),
            'email_clinica' => (string) ($row['email'] ?? ''),
        ];

        $this->clinicTemplateDataCache[$userId] = $data;
        return $data;
    }

    private function processLeadAutoReply(int $userId, int $patientId, string $text): void
    {
        if (!$this->isLeadPatient($userId, $patientId)) {
            return;
        }

        $normalized = mb_strtolower(trim($text), 'UTF-8');

        // First inbound: send welcome/menu message.
        if ($this->isLeadFirstInbound($userId, $patientId)) {
            $welcome = $this->getCustomReply($userId, '__welcome__');
            if ($welcome === null) {
                $welcome = 'Ola! Bem-vindo a clinica. Escolha uma opcao: 1) Agendar consulta 2) Tirar duvidas 3) Falar com atendente';
            }
            $this->sendManualMessage($userId, $patientId, $welcome, null);
            return;
        }

        // Check user-defined keyword replies first.
        $customReply = $this->matchCustomReply($userId, $normalized);
        if ($customReply !== null) {
            $this->sendManualMessage($userId, $patientId, $customReply, null);
            return;
        }

        // Built-in fallback replies.
        if (in_array($normalized, ['1', 'agendar', 'consulta'], true)) {
            // Inicia o fluxo de agendamento com slots reais da agenda
            $this->startSchedulingFlow($userId, $patientId);
            return;
        }

        if (in_array($normalized, ['2', 'duvida', 'dúvida', 'informacao', 'informação'], true)) {
            $this->sendManualMessage(
                $userId,
                $patientId,
                'Claro! Pode enviar sua dúvida que vamos te ajudar agora mesmo.',
                null
            );
            return;
        }

        if (in_array($normalized, ['3', 'atendente', 'humano', 'contato'], true)) {
            $this->sendManualMessage(
                $userId,
                $patientId,
                'Perfeito. Vou encaminhar você para nosso atendimento humano agora.',
                null
            );
        }
    }

    /** Return the reply text for a specific keyword stored in the DB, or null if not found. */
    private function getCustomReply(int $userId, string $keyword): ?string
    {
        $stmt = Database::connection()->prepare(
            'SELECT reply FROM whatsapp_auto_replies
             WHERE user_id = :user_id AND keyword = :keyword AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId, 'keyword' => $keyword]);
        $row = $stmt->fetch();
        return $row ? (string) $row['reply'] : null;
    }

    /**
     * Match the incoming message against all active custom keywords.
     * Keyword matching is case-insensitive exact match.
     * Returns the reply text or null if no match.
     */
    private function matchCustomReply(int $userId, string $normalizedText): ?string
    {
        $stmt = Database::connection()->prepare(
            'SELECT keyword, reply FROM whatsapp_auto_replies
             WHERE user_id = :user_id AND is_active = 1
             ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute(['user_id' => $userId]);
        $rows = $stmt->fetchAll();

        foreach ($rows as $row) {
            $kw = mb_strtolower(trim((string) $row['keyword']), 'UTF-8');
            // skip reserved internal keyword
            if ($kw === '__welcome__') {
                continue;
            }
            if ($kw === $normalizedText) {
                return (string) $row['reply'];
            }
        }

        return null;
    }

    // Fluxo de agendamento via WhatsApp
    // Fluxo de agendamento via WhatsApp

    /**
     * Inicia o fluxo de agendamento: busca 3 slots livres e envia ao paciente.
     * Salva o estado conversacional aguardando a escolha (1/2/3).
     */
    public function startSchedulingFlow(int $userId, int $patientId): void
    {
        $slotFinder = new SlotFinderService();
        $slots = $slotFinder->findNext($userId, 3);

        if (empty($slots)) {
            $this->sendManualMessage(
                $userId,
                $patientId,
                'Não encontrei horários disponíveis nos próximos 30 dias. Por favor, entre em contato diretamente com a clínica.',
                null
            );
            return;
        }

        // Monta a mensagem com as opções
        $lines = ['Ótimo! Temos estes horários disponíveis:'];
        foreach ($slots as $i => $slot) {
            $lines[] = ($i + 1) . ') ' . $slot['label'];
        }
        $lines[] = '';
        $lines[] = 'Responda com 1, 2 ou 3 para confirmar seu horário.';

        $this->sendManualMessage($userId, $patientId, implode("\n", $lines), null);

        // Salva o estado: aguardando escolha do slot
        $this->saveConversationState($userId, $patientId, 'awaiting_slot_choice', [
            'slots' => $slots,
        ]);
    }

    /**
     * Processa a resposta do paciente quando há um estado conversacional ativo.
     * Retorna true se a mensagem foi consumida pelo fluxo de estado.
     */
    public function processConversationState(int $userId, int $patientId, string $text): bool
    {
        $stateRow = $this->loadConversationState($userId, $patientId);
        if ($stateRow === null) {
            return false;
        }

        $state   = (string) $stateRow['state'];
        $payload = json_decode((string) ($stateRow['payload'] ?? '{}'), true) ?: [];

        if ($state === 'awaiting_slot_choice') {
            return $this->handleSlotChoice($userId, $patientId, $text, $payload);
        }

        return false;
    }

    /**
     * Processa a escolha de slot (1/2/3) e cria o agendamento.
     */
    private function handleSlotChoice(int $userId, int $patientId, string $text, array $payload): bool
    {
        $normalized = trim($text);

        // Aceita dígito simples ou emoji de número
        $emojiMap = ['1️⃣' => '1', '2️⃣' => '2', '3️⃣' => '3'];
        $normalized = $emojiMap[$normalized] ?? $normalized;

        $slots = $payload['slots'] ?? [];
        $choice = null;

        if ($normalized === '1' && isset($slots[0])) {
            $choice = $slots[0];
        } elseif ($normalized === '2' && isset($slots[1])) {
            $choice = $slots[1];
        } elseif ($normalized === '3' && isset($slots[2])) {
            $choice = $slots[2];
        }

        if ($choice === null) {
            // Resposta inválida: reapresenta as opções.
            $lines = ['Não entendi. Por favor, responda apenas com o número do horário:'];
            foreach ($slots as $i => $slot) {
                $lines[] = ($i + 1) . ') ' . $slot['label'];
            }
            $this->sendManualMessage($userId, $patientId, implode("\n", $lines), null);
            return true; // mensagem consumida pelo fluxo
        }

        // Cria o agendamento
        $appointmentId = $this->createAppointmentFromSlot($userId, $patientId, (string) $choice['datetime']);

        if ($appointmentId === null) {
            // Slot foi tomado por outro paciente entre o envio e a resposta
            $this->clearConversationState($userId, $patientId);
            $this->sendManualMessage(
                $userId,
                $patientId,
                'Ops! Esse horário acabou de ser ocupado. Vou buscar novos horários para você.',
                null
            );
            $this->startSchedulingFlow($userId, $patientId);
            return true;
        }

        // Promove lead para ativo
        $this->promoteLeadToActive($userId, $patientId);

        // Limpa o estado conversacional
        $this->clearConversationState($userId, $patientId);

        $this->sendManualMessage(
            $userId,
            $patientId,
            'Consulta agendada com sucesso! ' . $choice['label'] . '. Te esperamos!',
            $appointmentId
        );

        return true;
    }

    /**
     * Insere o agendamento no banco. Retorna o ID criado ou null em caso de conflito.
     */
    private function createAppointmentFromSlot(int $userId, int $patientId, string $datetime): ?int
    {
        $db = Database::connection();

        // Verifica conflito de horário
        $check = $db->prepare(
            "SELECT id FROM appointments
             WHERE user_id = :user_id
               AND data_hora = :data_hora
               AND status IN ('agendada','confirmada')
             LIMIT 1"
        );
        $check->execute(['user_id' => $userId, 'data_hora' => $datetime]);
        if ($check->fetch()) {
            return null; // horário ocupado
        }

        $stmt = $db->prepare(
            'INSERT INTO appointments (user_id, patient_id, data_hora, status, procedimento, created_at, updated_at)
             VALUES (:user_id, :patient_id, :data_hora, "agendada", "Consulta", NOW(), NOW())'
        );
        $stmt->execute([
            'user_id'    => $userId,
            'patient_id' => $patientId,
            'data_hora'  => $datetime,
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Promove um paciente de status "lead" para "ativo".
     */
    private function promoteLeadToActive(int $userId, int $patientId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE patients SET status = "ativo", updated_at = NOW()
             WHERE id = :id AND user_id = :user_id AND status = "lead"'
        );
        $stmt->execute(['id' => $patientId, 'user_id' => $userId]);
    }

    // -----------------------------------------------------------------------------
    // Gestão do estado conversacional
    // -----------------------------------------------------------------------------

    private function saveConversationState(int $userId, int $patientId, string $state, array $payload): void
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO whatsapp_conversation_state
                 (user_id, patient_id, state, payload, expires_at, created_at, updated_at)
             VALUES
                 (:user_id, :patient_id, :state, :payload, DATE_ADD(NOW(), INTERVAL 30 MINUTE), NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                 state      = VALUES(state),
                 payload    = VALUES(payload),
                 expires_at = VALUES(expires_at),
                 updated_at = NOW()'
        );
        $stmt->execute([
            'user_id'    => $userId,
            'patient_id' => $patientId,
            'state'      => $state,
            'payload'    => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function loadConversationState(int $userId, int $patientId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT state, payload FROM whatsapp_conversation_state
             WHERE user_id = :user_id AND patient_id = :patient_id AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId, 'patient_id' => $patientId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function clearConversationState(int $userId, int $patientId): void
    {
        $stmt = Database::connection()->prepare(
            'DELETE FROM whatsapp_conversation_state WHERE user_id = :user_id AND patient_id = :patient_id'
        );
        $stmt->execute(['user_id' => $userId, 'patient_id' => $patientId]);
    }

    private function isLeadPatient(int $userId, int $patientId): bool
    {
        $stmt = Database::connection()->prepare('SELECT id FROM patients WHERE id = :id AND user_id = :user_id AND status = "lead" LIMIT 1');
        $stmt->execute([
            'id' => $patientId,
            'user_id' => $userId,
        ]);

        return (bool) $stmt->fetch();
    }

    private function isLeadFirstInbound(int $userId, int $patientId): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) AS total
             FROM whatsapp_messages
             WHERE user_id = :user_id
               AND patient_id = :patient_id
               AND direction = "inbound"'
        );
        $stmt->execute([
            'user_id' => $userId,
            'patient_id' => $patientId,
        ]);

        $total = (int) (($stmt->fetch()['total'] ?? 0));
        return $total <= 1;
    }

    private function generateLeadCpf(int $userId, string $seed): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $raw = (string) abs(crc32($userId . '|' . $seed . '|' . microtime(true) . '|' . $attempt));
            $candidate = substr(str_pad($raw, 11, '0', STR_PAD_LEFT), 0, 11);

            $stmt = Database::connection()->prepare('SELECT id FROM patients WHERE user_id = :user_id AND cpf = :cpf LIMIT 1');
            $stmt->execute([
                'user_id' => $userId,
                'cpf' => $candidate,
            ]);

            if (!$stmt->fetch()) {
                return $candidate;
            }
        }

        return (string) random_int(10000000000, 99999999999);
    }
}

