<?php

declare(strict_types=1);

final class AuthController
{
    private const LOGIN_MAX_ATTEMPTS = 5;
    private const LOGIN_WINDOW_MINUTES = 15;

    public function showLogin(): void
    {
        if (Auth::check()) {
            redirect(base_url('route=dashboard'));
        }

        $error = $_GET['error'] ?? ($_SESSION['auth_error'] ?? null);
        unset($_SESSION['auth_error']);
        View::render('auth/login', ['error' => $error]);
    }

    public function showRegister(): void
    {
        if (Auth::check()) {
            redirect(base_url('route=dashboard'));
        }

        $error = $_GET['error'] ?? null;
        $message = $_GET['message'] ?? null;
        $legal = new LegalService();
        View::render('auth/register', [
            'error' => $error,
            'message' => $message,
            'legalVersions' => $legal->currentVersions(),
            'legalLinks' => $legal->legalLinks(),
        ]);
    }

    public function register(): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            redirect(base_url('route=register&error=Token inválido'));
        }

        $nomeConsultorio = trim((string) ($_POST['nome_consultorio'] ?? ''));
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')), 'UTF-8');
        $cpf = preg_replace('/\D+/', '', (string) ($_POST['cpf'] ?? ''));
        $telefone = preg_replace('/\D+/', '', (string) ($_POST['telefone'] ?? ''));
        $endereco = trim((string) ($_POST['endereco'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');
        $acceptLegal = (string) ($_POST['accept_legal'] ?? '0');

        if ($nomeConsultorio === '' || $email === '' || $cpf === '' || $telefone === '' || $password === '' || $passwordConfirm === '') {
            redirect(base_url('route=register&error=' . urlencode('Preencha todos os campos obrigatórios.')));
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirect(base_url('route=register&error=' . urlencode('E-mail inválido.')));
        }

        if (strlen($cpf) !== 11) {
            redirect(base_url('route=register&error=' . urlencode('CPF deve ter 11 dígitos.')));
        }

        if (strlen($telefone) < 10 || strlen($telefone) > 11) {
            redirect(base_url('route=register&error=' . urlencode('Telefone deve ter 10 ou 11 dígitos.')));
        }

        if (strlen($password) < 8) {
            redirect(base_url('route=register&error=' . urlencode('A senha deve ter no mínimo 8 caracteres.')));
        }

        if (!hash_equals($password, $passwordConfirm)) {
            redirect(base_url('route=register&error=' . urlencode('As senhas não conferem.')));
        }

        if ($acceptLegal !== '1') {
            redirect(base_url('route=register&error=' . urlencode('Você precisa aceitar os Termos de Uso e a Política de Privacidade.')));
        }

        try {
            $db = Database::connection();

            $exists = $db->prepare('SELECT id FROM users WHERE email = :email OR cpf = :cpf LIMIT 1');
            $exists->execute([
                'email' => $email,
                'cpf' => $cpf,
            ]);
            if ($exists->fetch()) {
                redirect(base_url('route=register&error=' . urlencode('Já existe uma conta com este e-mail ou CPF.')));
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);

            $db->beginTransaction();
            $insertUser = $db->prepare(
                'INSERT INTO users (email, cpf, password_hash, nome_consultorio, telefone, endereco, ativo, created_at, updated_at)
                 VALUES (:email, :cpf, :password_hash, :nome_consultorio, :telefone, :endereco, 1, NOW(), NOW())'
            );
            $insertUser->execute([
                'email' => $email,
                'cpf' => $cpf,
                'password_hash' => $hash,
                'nome_consultorio' => $nomeConsultorio,
                'telefone' => $telefone,
                'endereco' => $endereco !== '' ? $endereco : null,
            ]);

            $userId = (int) $db->lastInsertId();

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
                'user_id' => $userId,
                'mensagem_confirmacao' => 'Olá {{nome}}! Sua consulta será em {{data_hora}}. Responda SIM para confirmar.',
                'whatsapp_verify_token' => $verifyToken,
            ]);

            // Optional columns from later migrations: apply only if available.
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
                    't12' => 'Olá {{nome}}! Lembrete: sua consulta é em cerca de 12 horas. Data: {{data_hora}}',
                    't2' => 'Olá {{nome}}! Lembrete: sua consulta é em cerca de 2 horas. Data: {{data_hora}}',
                    'tf' => 'Oi {{nome}}! Sentimos sua falta na consulta. Quer reagendar?',
                    'tc' => 'Olá {{nome}}! Podemos te ajudar a remarcar sua consulta?',
                    'ti' => 'Oi {{nome}}! Faz um tempo que você não agenda consulta. Quer ver horários disponíveis?',
                ]);
            } catch (Throwable $e) {
                // Ignore when template columns are not present yet.
            }

            $db->commit();

            // Optional modules must not block account creation.
            try {
                (new BillingService())->ensureWorkspaceSubscription($userId);
            } catch (Throwable $e) {
            }

            try {
                (new LegalService())->registerConsent(
                    $userId,
                    (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                    (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')
                );
            } catch (Throwable $e) {
            }
        } catch (Throwable $e) {
            if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
                $db->rollBack();
            }
            redirect(base_url('route=register&error=' . urlencode('Não foi possível criar a conta agora. Tente novamente em instantes.')));
        }

        redirect(base_url('route=login&error=' . urlencode('Conta criada com sucesso! Faça login.')));
    }

    public function login(): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            redirect(base_url('route=login&error=Token inválido'));
        }

        $login = trim((string) ($_POST['login'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $ipAddress = $this->clientIp();

        if ($login === '' || $password === '') {
            redirect(base_url('route=login&error=Informe login e senha'));
        }

        try {
            $blockInfo = $this->rateLimitStatus($login, $ipAddress);
            if (($blockInfo['blocked'] ?? false) === true) {
                $minutes = (int) ($blockInfo['minutes_left'] ?? self::LOGIN_WINDOW_MINUTES);
                $this->logSecurityEventByLogin($login, 'login_blocked_rate_limit', $ipAddress, 'Bloqueado por excesso de tentativas.');
                redirect(base_url('route=login&error=' . urlencode('Muitas tentativas. Tente novamente em ' . $minutes . ' minuto(s).')));
            }

            if (!Auth::attempt($login, $password)) {
                $this->registerLoginAttempt($login, $ipAddress, false);
                $this->logSecurityEventByLogin($login, 'login_failed', $ipAddress, 'Credenciais inválidas.');
                redirect(base_url('route=login&error=Credenciais inválidas'));
            }

            $this->registerLoginAttempt($login, $ipAddress, true);
            $this->clearFailedAttempts($login, $ipAddress);
            $this->logSecurityEventByLogin($login, 'login_success', $ipAddress, 'Login efetuado com sucesso.');

            redirect(base_url('route=dashboard'));
        } catch (Throwable $e) {
            redirect(base_url('route=login&error=' . urlencode('Serviço indisponível no momento. Tente novamente em 1 minuto.')));
        }
    }

    public function logout(): void
    {
        Auth::logout();
        redirect(base_url('route=login'));
    }

    public function showForgotPassword(): void
    {
        $message = $_GET['message'] ?? null;
        $error = $_GET['error'] ?? null;
        View::render('auth/forgot_password', [
            'message' => $message,
            'error' => $error,
        ]);
    }

    public function requestPasswordReset(): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            redirect(base_url('route=forgot_password&error=Token inválido'));
        }

        $login = trim((string) ($_POST['login'] ?? ''));
        if ($login === '') {
            redirect(base_url('route=forgot_password&error=Informe e-mail ou CPF'));
        }

        $db = Database::connection();
        $stmt = $db->prepare('SELECT id, email FROM users WHERE email = :login OR cpf = :login LIMIT 1');
        $stmt->execute(['login' => $login]);
        $user = $stmt->fetch();

        if (!$user) {
            redirect(base_url('route=forgot_password&message=' . urlencode('Se o login existir, enviaremos as instruções de recuperação.')));
        }

        $userId = (int) $user['id'];
        $tokenPlain = $this->generateToken();
        $tokenHash = password_hash($tokenPlain, PASSWORD_DEFAULT);

        $cleanup = $db->prepare('DELETE FROM password_resets WHERE user_id = :user_id OR expires_at < NOW() OR used_at IS NOT NULL');
        $cleanup->execute(['user_id' => $userId]);

        $insert = $db->prepare(
            'INSERT INTO password_resets (user_id, token_hash, expires_at, created_at)
             VALUES (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL 30 MINUTE), NOW())'
        );
        $insert->execute([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
        ]);

        $resetUrl = base_url('route=reset_password&token=' . urlencode($tokenPlain));

        $mailer = new MailerService();
        $clinicName = (string) env('APP_NAME', 'Atendy');
        $toEmail = (string) ($user['email'] ?? '');
        $mailSent = false;

        if ($toEmail !== '' && $mailer->isEnabled()) {
            $subject = 'Recuperação de senha - ' . $clinicName;
            $htmlBody =
                '<p>Olá,</p>' .
                '<p>Recebemos uma solicitação para redefinir sua senha no <strong>' . e($clinicName) . '</strong>.</p>' .
                '<p><a href="' . e($resetUrl) . '" style="display:inline-block;padding:10px 14px;background:#2563eb;color:#fff;text-decoration:none;border-radius:8px;">Redefinir senha</a></p>' .
                '<p>Este link expira em 30 minutos.</p>' .
                '<p>Se você não solicitou, ignore este e-mail.</p>';

            $textBody = "Olá,\n\n" .
                "Recebemos uma solicitação para redefinir sua senha no {$clinicName}.\n" .
                "Acesse: {$resetUrl}\n\n" .
                "Este link expira em 30 minutos.\n" .
                "Se você não solicitou, ignore este e-mail.";

            $result = $mailer->send($toEmail, $subject, $htmlBody, $textBody);
            $mailSent = (bool) ($result['success'] ?? false);
        }

        if ($mailSent) {
            redirect(base_url('route=forgot_password&message=' . urlencode('Se o login existir, enviaremos as instruções de recuperação.')));
        }

        if ((string) env('APP_ENV', 'local') === 'local') {
            redirect(base_url('route=forgot_password&message=' . urlencode('SMTP não configurado. Link local de recuperação: ' . $resetUrl)));
        }

        redirect(base_url('route=forgot_password&message=' . urlencode('Se o login existir, enviaremos as instruções de recuperação.')));
    }

    public function showResetPassword(): void
    {
        $token = trim((string) ($_GET['token'] ?? ''));
        if ($token === '') {
            redirect(base_url('route=forgot_password&error=Token ausente'));
        }

        $reset = $this->findValidResetByToken($token);
        if ($reset === null) {
            redirect(base_url('route=forgot_password&error=Token inválido ou expirado'));
        }

        $error = $_GET['error'] ?? null;
        $message = $_GET['message'] ?? null;
        View::render('auth/reset_password', [
            'token' => $token,
            'error' => $error,
            'message' => $message,
        ]);
    }

    public function resetPassword(): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            redirect(base_url('route=forgot_password&error=Token inválido'));
        }

        $token = trim((string) ($_POST['token'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

        if ($token === '') {
            redirect(base_url('route=forgot_password&error=Token ausente'));
        }

        if (strlen($password) < 8) {
            redirect(base_url('route=reset_password&token=' . urlencode($token) . '&error=' . urlencode('A senha deve ter no mínimo 8 caracteres')));
        }

        if (!hash_equals($password, $passwordConfirm)) {
            redirect(base_url('route=reset_password&token=' . urlencode($token) . '&error=' . urlencode('As senhas não conferem')));
        }

        $reset = $this->findValidResetByToken($token);
        if ($reset === null) {
            redirect(base_url('route=forgot_password&error=Token inválido ou expirado'));
        }

        $db = Database::connection();
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $updUser = $db->prepare('UPDATE users SET password_hash = :password_hash, updated_at = NOW() WHERE id = :id');
        $updUser->execute([
            'password_hash' => $hash,
            'id' => (int) $reset['user_id'],
        ]);

        $updReset = $db->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = :id');
        $updReset->execute(['id' => (int) $reset['id']]);

        redirect(base_url('route=login&error=' . urlencode('Senha alterada com sucesso. Faça login.')));
    }

    public function showTeamAccept(): void
    {
        if (Auth::check()) {
            redirect(base_url('route=dashboard'));
        }

        $token = trim((string) ($_GET['token'] ?? ''));
        if ($token === '') {
            redirect(base_url('route=login&error=' . urlencode('Token de convite ausente.')));
        }

        $invite = $this->findPendingInviteByToken($token);
        if ($invite === null) {
            redirect(base_url('route=login&error=' . urlencode('Convite inválido ou expirado.')));
        }

        $error = $_GET['error'] ?? null;
        $message = $_GET['message'] ?? null;

        View::render('auth/team_accept', [
            'token' => $token,
            'invite' => $invite,
            'error' => $error,
            'message' => $message,
        ]);
    }

    public function acceptTeamInvite(): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            redirect(base_url('route=login&error=' . urlencode('Token inválido.')));
        }

        $token = trim((string) ($_POST['token'] ?? ''));
        $nomeCompleto = trim((string) ($_POST['nome_completo'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

        if ($token === '') {
            redirect(base_url('route=login&error=' . urlencode('Token de convite ausente.')));
        }

        if ($nomeCompleto === '') {
            redirect(base_url('route=team_accept&token=' . urlencode($token) . '&error=' . urlencode('Informe seu nome completo.')));
        }

        if (strlen($password) < 8) {
            redirect(base_url('route=team_accept&token=' . urlencode($token) . '&error=' . urlencode('A senha deve ter no mínimo 8 caracteres.')));
        }

        if (!hash_equals($password, $passwordConfirm)) {
            redirect(base_url('route=team_accept&token=' . urlencode($token) . '&error=' . urlencode('As senhas não conferem.')));
        }

        $invite = $this->findPendingInviteByToken($token);
        if ($invite === null) {
            redirect(base_url('route=login&error=' . urlencode('Convite inválido ou expirado.')));
        }

        $db = Database::connection();
        $stmt = $db->prepare(
            'UPDATE team_members
             SET nome_completo = :nome_completo,
                 password_hash = :password_hash,
                 status = "active",
                 invitation_token = NULL,
                 token_created_at = NULL,
                 updated_at = NOW()
             WHERE id = :id
               AND status = "pending"
               AND deleted_at IS NULL'
        );
        $stmt->execute([
            'id' => (int) $invite['id'],
            'nome_completo' => $nomeCompleto,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);

        if ($stmt->rowCount() <= 0) {
            redirect(base_url('route=login&error=' . urlencode('Não foi possível ativar o convite.')));
        }

        redirect(base_url('route=login&error=' . urlencode('Convite aceito com sucesso. Faça login com seu e-mail e senha.')));
    }

    private function findValidResetByToken(string $tokenPlain): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, user_id, token_hash
             FROM password_resets
             WHERE used_at IS NULL AND expires_at > NOW()'
        );
        $stmt->execute();

        foreach ($stmt->fetchAll() as $row) {
            if (password_verify($tokenPlain, (string) $row['token_hash'])) {
                return $row;
            }
        }

        return null;
    }

    private function findPendingInviteByToken(string $token): ?array
    {
                $inviteLifetimeHours = $this->inviteLifetimeHours();
        $stmt = Database::connection()->prepare(
                        'SELECT tm.id, tm.email, tm.nome_completo, tm.role, tm.workspace_id, tm.token_created_at,
                                        DATE_ADD(tm.token_created_at, INTERVAL ' . $inviteLifetimeHours . ' HOUR) AS expires_at,
                                        u.nome_consultorio
             FROM team_members tm
             INNER JOIN users u ON u.id = tm.workspace_id
             WHERE tm.invitation_token = :token
               AND tm.status = "pending"
               AND tm.deleted_at IS NULL
               AND u.ativo = 1
                             AND (tm.token_created_at IS NULL OR tm.token_created_at >= DATE_SUB(NOW(), INTERVAL ' . $inviteLifetimeHours . ' HOUR))
             LIMIT 1'
        );
        $stmt->execute(['token' => $token]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function inviteLifetimeHours(): int
    {
        $hours = (int) env('TEAM_INVITE_EXPIRATION_HOURS', '168');
        return $hours >= 1 ? $hours : 168;
    }

    private function generateToken(): string
    {
        try {
            return bin2hex(random_bytes(24));
        } catch (Throwable $e) {
            return sha1((string) microtime(true) . (string) mt_rand());
        }
    }

    private function clientIp(): string
    {
        $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
        return $ip !== '' ? $ip : '0.0.0.0';
    }

    private function registerLoginAttempt(string $login, string $ipAddress, bool $success): void
    {
        try {
            $stmt = Database::connection()->prepare(
                'INSERT INTO login_attempts (login_identifier, ip_address, success, attempted_at)
                 VALUES (:login_identifier, :ip_address, :success, NOW())'
            );
            $stmt->execute([
                'login_identifier' => mb_strtolower($login, 'UTF-8'),
                'ip_address' => $ipAddress,
                'success' => $success ? 1 : 0,
            ]);

            $cleanup = Database::connection()->prepare(
                'DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 2 DAY)'
            );
            $cleanup->execute();
        } catch (Throwable $e) {
            // Non-critical telemetry must not block login.
        }
    }

    private function clearFailedAttempts(string $login, string $ipAddress): void
    {
        try {
            $stmt = Database::connection()->prepare(
                'DELETE FROM login_attempts
                 WHERE login_identifier = :login_identifier
                   AND ip_address = :ip_address
                   AND success = 0'
            );
            $stmt->execute([
                'login_identifier' => mb_strtolower($login, 'UTF-8'),
                'ip_address' => $ipAddress,
            ]);
        } catch (Throwable $e) {
            // Non-critical telemetry must not block login.
        }
    }

    private function rateLimitStatus(string $login, string $ipAddress): array
    {
        try {
            $stmt = Database::connection()->prepare(
                'SELECT COUNT(*) AS total,
                                            TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(MIN(attempted_at), INTERVAL 15 MINUTE)) AS seconds_left
                 FROM login_attempts
                 WHERE login_identifier = :login_identifier
                   AND ip_address = :ip_address
                   AND success = 0
                 AND attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)'
            );
            $stmt->bindValue('login_identifier', mb_strtolower($login, 'UTF-8'), PDO::PARAM_STR);
            $stmt->bindValue('ip_address', $ipAddress, PDO::PARAM_STR);
            $stmt->execute();

            $row = $stmt->fetch() ?: ['total' => 0, 'seconds_left' => 0];
            $total = (int) ($row['total'] ?? 0);

            if ($total < self::LOGIN_MAX_ATTEMPTS) {
                return ['blocked' => false, 'minutes_left' => 0];
            }

            $secondsLeft = (int) ($row['seconds_left'] ?? 0);
            if ($secondsLeft <= 0) {
                return ['blocked' => false, 'minutes_left' => 0];
            }

            $minutesLeft = (int) ceil($secondsLeft / 60);

            return ['blocked' => true, 'minutes_left' => $minutesLeft];
        } catch (Throwable $e) {
            // If rate-limit tables are not available yet, allow login flow.
            return ['blocked' => false, 'minutes_left' => 0];
        }
    }

    private function logSecurityEventByLogin(string $login, string $eventType, string $ipAddress, string $details): void
    {
        try {
            $db = Database::connection();

            $stmtUser = $db->prepare('SELECT id FROM users WHERE email = :login OR cpf = :login LIMIT 1');
            $stmtUser->execute(['login' => $login]);
            $userRow = $stmtUser->fetch();
            $userId = $userRow ? (int) $userRow['id'] : null;

            $stmt = $db->prepare(
                'INSERT INTO security_events (user_id, event_type, ip_address, details, created_at)
                 VALUES (:user_id, :event_type, :ip_address, :details, NOW())'
            );
            $stmt->execute([
                'user_id' => $userId,
                'event_type' => $eventType,
                'ip_address' => $ipAddress,
                'details' => $details,
            ]);
        } catch (Throwable $e) {
            // Non-critical telemetry must not block login.
        }
    }
}


