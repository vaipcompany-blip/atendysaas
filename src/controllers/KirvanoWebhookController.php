<?php

declare(strict_types=1);

final class KirvanoWebhookController
{
    public function handle(): void
    {
        $rawBody = (string) file_get_contents('php://input');

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            http_response_code(400);
            echo 'Invalid payload';
            return;
        }

        // Validate token
        $expectedToken = trim((string) env('KIRVANO_WEBHOOK_TOKEN', ''));
        if ($expectedToken !== '') {
            $receivedToken = trim((string) ($payload['token'] ?? ''));
            if (!hash_equals($expectedToken, $receivedToken)) {
                AppLogger::warning('Kirvano webhook: token mismatch', [
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                ]);
                http_response_code(401);
                echo 'Unauthorized';
                return;
            }
        }

        $event = (string) ($payload['event'] ?? '');

        // Only process SALE_APPROVED
        if ($event !== 'SALE_APPROVED') {
            http_response_code(200);
            echo 'OK';
            return;
        }

        $customer = is_array($payload['customer'] ?? null) ? (array) $payload['customer'] : [];
        $email    = mb_strtolower(trim((string) ($customer['email'] ?? '')), 'UTF-8');
        $name     = trim((string) ($customer['name'] ?? ''));
        $phone    = preg_replace('/\D+/', '', (string) ($customer['phone_number'] ?? ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            AppLogger::error('Kirvano webhook: invalid customer email', ['payload_event' => $event]);
            http_response_code(422);
            echo 'Invalid email';
            return;
        }

        // Map charge_frequency to plan_type
        $plan            = is_array($payload['plan'] ?? null) ? (array) $payload['plan'] : [];
        $chargeFrequency = mb_strtoupper(trim((string) ($plan['charge_frequency'] ?? 'MONTHLY')), 'UTF-8');
        $planType        = match ($chargeFrequency) {
            'QUARTERLY' => 'quarterly',
            'ANNUALLY'  => 'annual',
            default     => 'monthly',
        };

        // Find or create user
        $db   = Database::connection();
        $stmt = $db->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $existingUser = $stmt->fetch();

        $isNewUser     = false;
        $plainPassword = '';

        if (!$existingUser) {
            $isNewUser     = true;
            $plainPassword = $this->generatePassword();
            $hash          = password_hash($plainPassword, PASSWORD_DEFAULT);

            $nomeConsultorio = $name !== '' ? $name : 'Meu Consultório';
            $cpfPlaceholder  = '00000000000';
            $phoneNormalized = ($phone !== '' && strlen($phone) >= 10) ? $phone : '00000000000';

            $db->beginTransaction();
            try {
                $insertUser = $db->prepare(
                    'INSERT INTO users (email, cpf, password_hash, nome_consultorio, telefone, ativo, created_at, updated_at)
                     VALUES (:email, :cpf, :password_hash, :nome_consultorio, :telefone, 1, NOW(), NOW())'
                );
                $insertUser->execute([
                    'email'           => $email,
                    'cpf'             => $cpfPlaceholder,
                    'password_hash'   => $hash,
                    'nome_consultorio' => $nomeConsultorio,
                    'telefone'        => $phoneNormalized,
                ]);

                $userId      = (int) $db->lastInsertId();
                $verifyToken = 'atendy_' . $userId . '_' . substr(bin2hex(random_bytes(4)), 0, 8);

                $insertSettings = $db->prepare(
                    'INSERT INTO settings (
                        user_id, horario_abertura, horario_fechamento, duracao_consulta, intervalo,
                        mensagem_confirmacao, whatsapp_mode, whatsapp_api_url, whatsapp_verify_token, whatsapp_default_country,
                        meta_conversao_mensal,
                        created_at, updated_at
                     ) VALUES (
                        :user_id, "08:00:00", "18:00:00", 60, 10,
                        :mensagem_confirmacao, "cloud", "https://graph.facebook.com/v20.0", :whatsapp_verify_token, "55",
                        60.00,
                        NOW(), NOW()
                     )'
                );
                $insertSettings->execute([
                    'user_id'                => $userId,
                    'mensagem_confirmacao'   => 'Olá {{nome}}! Sua consulta será em {{data_hora}}. Responda SIM para confirmar.',
                    'whatsapp_verify_token'  => $verifyToken,
                ]);

                // Optional automation templates
                try {
                    $db->prepare(
                        'UPDATE settings
                         SET template_lembrete_12h = :t12,
                             template_lembrete_2h = :t2,
                             template_followup_falta = :tf,
                             template_followup_cancelamento = :tc,
                             template_followup_inatividade = :ti,
                             updated_at = NOW()
                         WHERE user_id = :user_id'
                    )->execute([
                        'user_id' => $userId,
                        't12'     => 'Olá {{nome}}! Lembrete: sua consulta é em cerca de 12 horas. Data: {{data_hora}}',
                        't2'      => 'Olá {{nome}}! Lembrete: sua consulta é em cerca de 2 horas. Data: {{data_hora}}',
                        'tf'      => 'Oi {{nome}}! Sentimos sua falta na consulta. Quer reagendar?',
                        'tc'      => 'Olá {{nome}}! Podemos te ajudar a remarcar sua consulta?',
                        'ti'      => 'Oi {{nome}}! Faz um tempo que você não agenda consulta. Quer ver horários disponíveis?',
                    ]);
                } catch (Throwable $e) {
                    // Optional columns — ignore if not present
                }

                $db->commit();
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                AppLogger::error('Kirvano webhook: user creation failed', [
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
                http_response_code(500);
                echo 'Internal error';
                return;
            }
        } else {
            $userId = (int) $existingUser['id'];
        }

        // Activate subscription
        try {
            $billing = new BillingService();
            $billing->activatePlan($userId, $planType, 'kirvano');
        } catch (Throwable $e) {
            AppLogger::error('Kirvano webhook: plan activation failed', [
                'user_id'   => $userId,
                'plan_type' => $planType,
                'error'     => $e->getMessage(),
            ]);
            http_response_code(500);
            echo 'Internal error';
            return;
        }

        // Send welcome email with credentials (new users only)
        if ($isNewUser && $plainPassword !== '') {
            try {
                $mailer = new MailerService();
                if ($mailer->isEnabled()) {
                    $appUrl  = rtrim((string) env('APP_URL', ''), '/');
                    $loginUrl = $appUrl . '/?route=login';
                    $subject = 'Bem-vindo ao Atendy - seus dados de acesso';
                    $planLabel = match ($planType) {
                        'annual' => 'Anual',
                        'quarterly' => 'Trimestral',
                        default => 'Mensal',
                    };

                    $brevoSent = false;
                    if ($mailer->isBrevoTemplateEnabled()) {
                        $templateId = (int) env('BREVO_TEMPLATE_ID', '0');
                        $brevoResult = $mailer->sendBrevoTemplate($email, $templateId, [
                            'nome' => $name !== '' ? $name : 'Cliente',
                            'url_acesso' => $loginUrl,
                            'usuario' => $email,
                            'senha' => $plainPassword,
                            'plano' => $planLabel,
                            'suporte_email' => (string) env('MAIL_FROM_ADDRESS', 'suporte@atendy.com'),
                            'whatsapp_suporte' => (string) env('SUPPORT_WHATSAPP', ''),
                            'ano' => date('Y'),
                        ]);

                        if (($brevoResult['success'] ?? false) === true) {
                            $brevoSent = true;
                        } else {
                            AppLogger::error('Kirvano webhook: Brevo template send failed', [
                                'user_id' => $userId,
                                'email' => $email,
                                'error' => (string) ($brevoResult['error'] ?? 'unknown error'),
                            ]);
                        }
                    }

                    if ($brevoSent) {
                        AppLogger::info('Kirvano webhook: welcome email sent via Brevo template', [
                            'user_id' => $userId,
                            'email' => $email,
                        ]);
                    }

                    if (!$brevoSent) {
                        $html = '
<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;padding:24px;border:1px solid #e2e8f0;border-radius:12px">
  <h2 style="color:#0f766e;margin-top:0">Bem-vindo ao Atendy! 🎉</h2>
  <p>Sua compra foi aprovada. Sua conta já está ativa.</p>
  <table style="width:100%;border-collapse:collapse;margin:16px 0">
    <tr><td style="padding:8px;background:#f0fdf4;border-radius:6px 0 0 6px;font-weight:bold;width:120px">Login</td>
        <td style="padding:8px;background:#f0fdf4;border-radius:0 6px 6px 0">' . htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td></tr>
    <tr><td style="padding:8px;background:#f8fafc;border-radius:6px 0 0 6px;font-weight:bold">Senha</td>
        <td style="padding:8px;background:#f8fafc;border-radius:0 6px 6px 0;font-family:monospace">' . htmlspecialchars($plainPassword, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td></tr>
  </table>
    <p style="margin:20px 0">
        <a href="' . htmlspecialchars($loginUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"
       style="background:#0f766e;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:bold;display:inline-block">
      Acessar o Atendy
    </a>
  </p>
  <p style="font-size:13px;color:#64748b">Recomendamos trocar a senha após o primeiro acesso em Configurações → Segurança.</p>
</div>';
                        $mailer->send($email, $subject, $html);
                    }
                }
            } catch (Throwable $e) {
                AppLogger::error('Kirvano webhook: welcome email failed', [
                    'user_id' => $userId,
                    'email'   => $email,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        AppLogger::info('Kirvano SALE_APPROVED processado', [
            'user_id'      => $userId,
            'email'        => $email,
            'plan_type'    => $planType,
            'is_new_user'  => $isNewUser,
            'charge_freq'  => $chargeFrequency,
        ]);

        http_response_code(200);
        echo 'OK';
    }

    private function generatePassword(): string
    {
        $chars    = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#';
        $password = '';
        for ($i = 0; $i < 12; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
    }
}
