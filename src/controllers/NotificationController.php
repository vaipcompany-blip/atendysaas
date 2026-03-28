<?php

declare(strict_types=1);

final class NotificationController
{
    // �"?�"? Listar notificações do usuário �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
    public function index(): void
    {
        $userId = (int) (Auth::user()['id'] ?? 0);
        $page = (int) ($_GET['page'] ?? 1);
        $page = max(1, $page);
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $db = Database::connection();

        // Total de notificações
        $countStmt = $db->prepare('SELECT COUNT(*) as cnt FROM notifications WHERE user_id = :uid');
        $countStmt->execute(['uid' => $userId]);
        $totalRows = (int) ($countStmt->fetch()['cnt'] ?? 0);
        $totalPages = (int) ceil($totalRows / $perPage);

        // Notificações paginadas (mais recentes primeiro)
        $stmt = $db->prepare(
            'SELECT id, type, title, message, related_type, related_id, is_read, created_at
             FROM notifications
             WHERE user_id = :uid
             ORDER BY created_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $notifications = $stmt->fetchAll();

        // Total de não-lidas
        $unreadStmt = $db->prepare('SELECT COUNT(*) as cnt FROM notifications WHERE user_id = :uid AND is_read = 0');
        $unreadStmt->execute(['uid' => $userId]);
        $unreadCount = (int) ($unreadStmt->fetch()['cnt'] ?? 0);

        $data = [
            'notifications' => $notifications,
            'currentPage'   => $page,
            'totalPages'    => $totalPages,
            'totalRows'     => $totalRows,
            'perPage'       => $perPage,
            'unreadCount'   => $unreadCount,
        ];

        View::render('notifications/index', $data);
    }

    // �"?�"? Marcar uma notificação como lida (AJAX) �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
    public function markRead(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        $userId = (int) (Auth::user()['id'] ?? 0);
        $notifId = (int) ($_POST['notification_id'] ?? 0);

        if ($notifId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID obrigatório.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $db = Database::connection();

        // Verificar propriedade (notificação pertence ao user)
        $checkStmt = $db->prepare('SELECT user_id FROM notifications WHERE id = :id LIMIT 1');
        $checkStmt->execute(['id' => $notifId]);
        $row = $checkStmt->fetch();

        if (!$row || (int)$row['user_id'] !== $userId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Marcar como lida
        $updateStmt = $db->prepare(
            'UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = :id'
        );
        $updateStmt->execute(['id' => $notifId]);

        echo json_encode(['success' => true, 'message' => 'Marcado como lido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // �"?�"? Marcar todos como lido �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
    public function markAllRead(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $userId = (int) (Auth::user()['id'] ?? 0);

        $db = Database::connection();
        $stmt = $db->prepare(
            'UPDATE notifications SET is_read = 1, read_at = NOW() 
             WHERE user_id = :uid AND is_read = 0'
        );
        $stmt->execute(['uid' => $userId]);

        echo json_encode(['success' => true, 'message' => 'Todas marcadas como lidas.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // �"?�"? Obter notificações não-lidas (AJAX, para badge do sino) �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
    public function getUnreadCount(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $userId = (int) (Auth::user()['id'] ?? 0);
        $db = Database::connection();

        $stmt = $db->prepare('SELECT COUNT(*) as cnt FROM notifications WHERE user_id = :uid AND is_read = 0');
        $stmt->execute(['uid' => $userId]);
        $count = (int) ($stmt->fetch()['cnt'] ?? 0);

        echo json_encode(['success' => true, 'unread_count' => $count], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // �"?�"? Obter últimas 5 notificações (para preview no header) �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
    public function getLatest(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $userId = (int) (Auth::user()['id'] ?? 0);
        $db = Database::connection();

        $stmt = $db->prepare(
            'SELECT id, type, title, message, is_read, created_at
             FROM notifications
             WHERE user_id = :uid
             ORDER BY created_at DESC
             LIMIT 5'
        );
        $stmt->execute(['uid' => $userId]);
        $notifs = $stmt->fetchAll();

        echo json_encode(['success' => true, 'notifications' => $notifs], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // �"?�"? Helper: criar notificação (use internamente) �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
    public static function create(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?string $relatedType = null,
        ?int $relatedId = null
    ): int {
        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO notifications (user_id, type, title, message, related_type, related_id)
             VALUES (:uid, :type, :title, :message, :rel_type, :rel_id)'
        );
        $stmt->execute([
            'uid'      => $userId,
            'type'     => $type,
            'title'    => $title,
            'message'  => $message,
            'rel_type' => $relatedType,
            'rel_id'   => $relatedId,
        ]);
        return (int) $db->lastInsertId();
    }
}

