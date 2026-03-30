<?php
class TeamController
{
    public function index(): void
    {
        $userId = (int) (Auth::user()['id'] ?? 0);

        $db = Database::connection();
        
        // Buscar todos os membros do workspace (usuário logado é o owner)
        $stmt = $db->prepare(
            'SELECT id, email, nome_completo, role, status, last_login, created_at
             FROM team_members
             WHERE workspace_id = :workspace_id AND deleted_at IS NULL
             ORDER BY role DESC, created_at ASC'
        );
        $stmt->execute(['workspace_id' => $userId]);
        $members = $stmt->fetchAll();

        $message = $_GET['message'] ?? null;

        View::render('team/index', [
            'members' => $members,
            'message' => $message,
        ]);
    }

    public function invite(): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            redirect(base_url('route=team&message=Token inválido'));
        }

        $userId = (int) (Auth::user()['id'] ?? 0);
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')), 'UTF-8');
        $nomeCompleto = trim((string) ($_POST['nome_completo'] ?? ''));
        $role = (string) ($_POST['role'] ?? 'staff');

        if ($email === '' || $nomeCompleto === '') {
            redirect(base_url('route=team&message=E-mail e nome são obrigatórios'));
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirect(base_url('route=team&message=E-mail inválido'));
        }

        $rolesValidas = ['owner', 'admin', 'staff'];
        if (!in_array($role, $rolesValidas, true)) {
            $role = 'staff';
        }

        $db = Database::connection();

        // Verificar se já existe convite
        $check = $db->prepare(
            'SELECT id, status, token_created_at FROM team_members 
             WHERE workspace_id = :workspace_id AND email = :email AND deleted_at IS NULL LIMIT 1'
        );
        $check->execute(['workspace_id' => $userId, 'email' => $email]);
        $existingMember = $check->fetch();
        if ($existingMember) {
            $existingStatus = (string) ($existingMember['status'] ?? 'pending');
            if ($existingStatus !== 'pending') {
                redirect(base_url('route=team&message=Este e-mail já foi convidado'));
            }

            if (!$this->isInviteExpired((string) ($existingMember['token_created_at'] ?? ''))) {
                redirect(base_url('route=team&message=Este e-mail já possui um convite pendente'));
            }
        }

        // Gerar token de convite
        $invitationToken = bin2hex(random_bytes(32));

        if ($existingMember) {
            $stmt = $db->prepare(
                'UPDATE team_members
                 SET nome_completo = :nome_completo,
                     role = :role,
                     status = "pending",
                     invitation_token = :token,
                     token_created_at = NOW(),
                     updated_at = NOW()
                 WHERE id = :id
                   AND workspace_id = :workspace_id'
            );
            $stmt->execute([
                'id' => (int) $existingMember['id'],
                'workspace_id' => $userId,
                'nome_completo' => $nomeCompleto,
                'role' => $role,
                'token' => $invitationToken,
            ]);
        } else {
            $stmt = $db->prepare(
                'INSERT INTO team_members (workspace_id, email, nome_completo, role, status, invitation_token, token_created_at, created_at, updated_at)
                 VALUES (:workspace_id, :email, :nome_completo, :role, "pending", :token, NOW(), NOW(), NOW())'
            );
            $stmt->execute([
                'workspace_id' => $userId,
                'email' => $email,
                'nome_completo' => $nomeCompleto,
                'role' => $role,
                'token' => $invitationToken,
            ]);
        }

        $inviteUrl = base_url('route=team_accept&token=' . urlencode($invitationToken));
        $inviteHours = $this->inviteLifetimeHours();
        $mailer = new MailerService();
        $mailSent = false;

        if ($mailer->isEnabled()) {
            $clinicName = (string) env('APP_NAME', 'Atendy');
            $subject = 'Convite para acessar ' . $clinicName;

            $htmlBody =
                '<p>Olá ' . e($nomeCompleto) . ',</p>' .
                '<p>Você recebeu um convite para acessar o ambiente da clínica no <strong>' . e($clinicName) . '</strong>.</p>' .
                '<p>Este link expira em ' . e((string) $inviteHours) . ' horas.</p>' .
                '<p><a href="' . e($inviteUrl) . '" style="display:inline-block;padding:10px 14px;background:#2563eb;color:#fff;text-decoration:none;border-radius:8px;">Aceitar convite</a></p>' .
                '<p>Se o botão não funcionar, use este link:</p>' .
                '<p><a href="' . e($inviteUrl) . '">' . e($inviteUrl) . '</a></p>';

            $textBody = "Olá {$nomeCompleto},\n\n" .
                "Você recebeu um convite para acessar o ambiente no {$clinicName}.\n" .
                "Aceite aqui: {$inviteUrl}\n" .
                "Este link expira em {$inviteHours} horas.";

            $sendResult = $mailer->send($email, $subject, $htmlBody, $textBody);
            $mailSent = (bool) ($sendResult['success'] ?? false);
        }

        $message = 'Convite enviado com sucesso.';
        if (!$mailSent && (string) env('APP_ENV', 'local') === 'local') {
            $message .= ' Link de convite: ' . $inviteUrl;
        }

        audit_log_event($userId, 'team_member_invited', 'Convite enviado para ' . $email . ' com função ' . $role . '.');

        redirect(base_url('route=team&message=' . urlencode($message)));
    }

    public function updateRole(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token CSRF inválido.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $userId = (int) (Auth::user()['id'] ?? 0);
        $memberId = (int) ($_POST['member_id'] ?? 0);
        $newRole = (string) ($_POST['role'] ?? 'staff');

        if ($memberId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID inválido.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $rolesValidas = ['owner', 'admin', 'staff'];
        if (!in_array($newRole, $rolesValidas, true)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Função inválida.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $db = Database::connection();

        // Verificar que o membro pertence ao workspace
        $check = $db->prepare(
            'SELECT id, role FROM team_members 
             WHERE id = :id AND workspace_id = :workspace_id LIMIT 1'
        );
        $check->execute(['id' => $memberId, 'workspace_id' => $userId]);
        $member = $check->fetch();

        if (!$member) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Membro não encontrado.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Não permitir remover o último owner
        if ($member['role'] === 'owner' && $newRole !== 'owner') {
            $ownerCount = $db->prepare(
                'SELECT COUNT(*) as cnt FROM team_members 
                 WHERE workspace_id = :workspace_id AND role = "owner" AND deleted_at IS NULL'
            );
            $ownerCount->execute(['workspace_id' => $userId]);
            $result = $ownerCount->fetch();
            if ($result['cnt'] <= 1) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Não é possível remover o último proprietário.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        $stmt = $db->prepare(
            'UPDATE team_members SET role = :role, updated_at = NOW() 
             WHERE id = :id AND workspace_id = :workspace_id'
        );
        $stmt->execute(['role' => $newRole, 'id' => $memberId, 'workspace_id' => $userId]);

        if ($stmt->rowCount() > 0) {
            audit_log_event($userId, 'team_member_role_updated', 'Membro #' . $memberId . ' alterado para função ' . $newRole . '.');
        }

        echo json_encode(['success' => true, 'message' => 'Função atualizada.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function remove(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token CSRF inválido.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $userId = (int) (Auth::user()['id'] ?? 0);
        $memberId = (int) ($_POST['member_id'] ?? 0);

        if ($memberId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID inválido.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $db = Database::connection();

        // Verificar que o membro pertence ao workspace
        $check = $db->prepare(
            'SELECT id, role FROM team_members 
             WHERE id = :id AND workspace_id = :workspace_id LIMIT 1'
        );
        $check->execute(['id' => $memberId, 'workspace_id' => $userId]);
        $member = $check->fetch();

        if (!$member) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Membro não encontrado.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Não permitir remover o último owner
        if ($member['role'] === 'owner') {
            $ownerCount = $db->prepare(
                'SELECT COUNT(*) as cnt FROM team_members 
                 WHERE workspace_id = :workspace_id AND role = "owner" AND deleted_at IS NULL'
            );
            $ownerCount->execute(['workspace_id' => $userId]);
            $result = $ownerCount->fetch();
            if ($result['cnt'] <= 1) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Não é possível remover o único proprietário.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        // Soft delete
        $stmt = $db->prepare(
            'UPDATE team_members SET deleted_at = NOW(), updated_at = NOW() 
             WHERE id = :id AND workspace_id = :workspace_id'
        );
        $stmt->execute(['id' => $memberId, 'workspace_id' => $userId]);

        if ($stmt->rowCount() > 0) {
            audit_log_event($userId, 'team_member_removed', 'Membro #' . $memberId . ' removido da equipe.');
        }

        echo json_encode(['success' => true, 'message' => 'Membro removido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function inviteLifetimeHours(): int
    {
        $hours = (int) env('TEAM_INVITE_EXPIRATION_HOURS', '168');
        return $hours >= 1 ? $hours : 168;
    }

    private function isInviteExpired(string $tokenCreatedAt): bool
    {
        if ($tokenCreatedAt === '') {
            return false;
        }

        $createdAt = strtotime($tokenCreatedAt);
        if ($createdAt === false) {
            return true;
        }

        return (time() - $createdAt) > ($this->inviteLifetimeHours() * 3600);
    }
}




