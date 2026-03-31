<?php

declare(strict_types=1);

final class WhatsAppController
{
    private WhatsAppService $service;

    public function __construct()
    {
        $this->service = new WhatsAppService();
    }

    public function index(): void
    {
        $userId = (int) (Auth::user()['id'] ?? 0);
        $filters = $this->normalizeFilters($_GET);
        $message = $_GET['message'] ?? null;

        $patients = [];
        $messages = [];
        $summary = [
            'total' => 0,
            'outbound_total' => 0,
            'inbound_total' => 0,
            'delivered_or_read_total' => 0,
            'failed_total' => 0,
            'unique_patients' => 0,
            'delivery_rate' => 0.0,
        ];
        $dailyTrend = [];
        $statusBreakdown = [];
        $statusInsight = [
            'hasData' => false,
            'message' => 'Sem dados de status no período filtrado.',
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 30;
        $totalMessages = 0;
        $totalPages = 1;

        try {
            $db = Database::connection();
            $this->ensureWhatsAppInfrastructure($db);

            try {
                $patientActiveClause = $this->columnExists($db, 'patients', 'deleted_at') ? ' AND deleted_at IS NULL' : '';
                $stmtPatients = $db->prepare('SELECT id, nome, whatsapp FROM patients WHERE user_id = :user_id' . $patientActiveClause . ' ORDER BY nome ASC');
                $stmtPatients->execute(['user_id' => $userId]);
                $patients = $stmtPatients->fetchAll();
            } catch (Throwable $patientError) {
                AppLogger::error('WhatsApp patient list failed', [
                    'user_id' => $userId,
                    'error' => $patientError->getMessage(),
                ]);
            }

            if ($this->tableExists($db, 'whatsapp_messages')) {
                [$whereSql, $params] = $this->buildMessageFiltersSql($userId, $filters);
                $orderBySql = $this->buildOrderBySql($filters);

                try {
                    $summary = $this->buildSummary($db, $whereSql, $params);
                } catch (Throwable $summaryError) {
                    AppLogger::error('WhatsApp summary failed', [
                        'user_id' => $userId,
                        'error' => $summaryError->getMessage(),
                    ]);
                }

                try {
                    $dailyTrend = $this->buildDailyTrend($db, $whereSql, $params, $filters);
                } catch (Throwable $trendError) {
                    AppLogger::error('WhatsApp daily trend failed', [
                        'user_id' => $userId,
                        'error' => $trendError->getMessage(),
                    ]);
                }

                try {
                    $statusBreakdown = $this->buildStatusBreakdown($db, $whereSql, $params);
                    $statusInsight = $this->buildStatusInsight($statusBreakdown);
                } catch (Throwable $statusError) {
                    AppLogger::error('WhatsApp status breakdown failed', [
                        'user_id' => $userId,
                        'error' => $statusError->getMessage(),
                    ]);
                }

                try {
                    $stmtCount = $db->prepare(
                        'SELECT COUNT(*) AS total
                         FROM whatsapp_messages wm
                         LEFT JOIN patients p ON p.id = wm.patient_id
                         ' . $whereSql
                    );
                    $stmtCount->execute($params);
                    $totalMessages = (int) ($stmtCount->fetch()['total'] ?? 0);

                    $totalPages = max(1, (int) ceil($totalMessages / $perPage));
                    if ($page > $totalPages) {
                        $page = $totalPages;
                    }
                    $offset = ($page - 1) * $perPage;

                    $stmtMessages = $db->prepare(
                        'SELECT wm.timestamp, wm.direction, wm.texto, wm.status, p.nome AS paciente_nome
                         FROM whatsapp_messages wm
                         LEFT JOIN patients p ON p.id = wm.patient_id
                         ' . $whereSql . '
                           ' . $orderBySql . '
                         LIMIT :limit OFFSET :offset'
                    );
                    foreach ($params as $key => $value) {
                        $stmtMessages->bindValue(':' . $key, $value);
                    }
                    $stmtMessages->bindValue(':limit', $perPage, PDO::PARAM_INT);
                    $stmtMessages->bindValue(':offset', $offset, PDO::PARAM_INT);
                    $stmtMessages->execute();
                    $messages = $stmtMessages->fetchAll();
                } catch (Throwable $historyError) {
                    AppLogger::error('WhatsApp history failed', [
                        'user_id' => $userId,
                        'error' => $historyError->getMessage(),
                    ]);
                    $messages = [];
                    $totalMessages = 0;
                    $totalPages = 1;
                }
            }
        } catch (Throwable $e) {
            AppLogger::error('WhatsApp index failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }

        View::render('whatsapp/index', [
            'patients' => $patients,
            'messages' => $messages,
            'message' => $message,
            'filters' => $filters,
            'summary' => $summary,
            'dailyTrend' => $dailyTrend,
            'statusBreakdown' => $statusBreakdown,
            'statusInsight' => $statusInsight,
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $totalMessages,
                'totalPages' => $totalPages,
            ],
        ]);
    }

    public function exportCsv(): void
    {
        try {
            $userId = (int) (Auth::user()['id'] ?? 0);
            $db = Database::connection();
            $this->ensureWhatsAppInfrastructure($db);

            $filters = $this->normalizeFilters($_GET);
            [$whereSql, $params] = $this->buildMessageFiltersSql($userId, $filters);
            $orderBySql = $this->buildOrderBySql($filters);

            $stmt = $db->prepare(
                'SELECT wm.timestamp, p.nome AS paciente_nome, wm.direction, wm.status, wm.texto
                 FROM whatsapp_messages wm
                 LEFT JOIN patients p ON p.id = wm.patient_id
                 ' . $whereSql . '
                   ' . $orderBySql
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="whatsapp_mensagens.csv"');

            $output = fopen('php://output', 'w');
            if ($output === false) {
                return;
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Data/Hora', 'Paciente', 'Direção', 'Status', 'Mensagem'], ';');

            foreach ($rows as $row) {
                fputcsv($output, [
                    (string) ($row['timestamp'] ?? ''),
                    (string) ($row['paciente_nome'] ?? ''),
                    (string) ($row['direction'] ?? ''),
                    (string) ($row['status'] ?? ''),
                    (string) ($row['texto'] ?? ''),
                ], ';');
            }

            fclose($output);
        } catch (Throwable $e) {
            AppLogger::error('WhatsApp CSV export failed', [
                'user_id' => (int) (Auth::user()['id'] ?? 0),
                'error' => $e->getMessage(),
            ]);
            redirect(base_url('route=whatsapp&message=' . urlencode('Exportacao indisponivel no momento.')));
        }
    }

    public function sendManual(): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            redirect(base_url('route=whatsapp&message=Token inválido'));
        }

        $userId = (int) (Auth::user()['id'] ?? 0);
        $patientId = (int) ($_POST['patient_id'] ?? 0);
        $text = trim((string) ($_POST['text'] ?? ''));

        if ($patientId <= 0 || $text === '') {
            redirect(base_url('route=whatsapp&message=Selecione paciente e mensagem'));
        }

        try {
            $db = Database::connection();
            $this->ensureWhatsAppInfrastructure($db);

            $this->service->sendManualMessage($userId, $patientId, $text, null);
            audit_log_event($userId, 'whatsapp_manual_sent', 'Mensagem manual enviada para paciente #' . $patientId . '.');
            redirect(base_url('route=whatsapp&message=Mensagem enviada (simulada)'));
        } catch (Throwable $e) {
            AppLogger::error('WhatsApp manual send failed', [
                'user_id' => $userId,
                'patient_id' => $patientId,
                'error' => $e->getMessage(),
            ]);
            redirect(base_url('route=whatsapp&message=' . urlencode('Falha ao enviar mensagem.')));
        }
    }

    public function receiveSimulated(): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            redirect(base_url('route=whatsapp&message=Token inválido'));
        }

        $userId = (int) (Auth::user()['id'] ?? 0);
        $patientId = (int) ($_POST['patient_id'] ?? 0);
        $text = trim((string) ($_POST['text'] ?? ''));

        if ($patientId <= 0 || $text === '') {
            redirect(base_url('route=whatsapp&message=Selecione paciente e mensagem'));
        }

        try {
            $db = Database::connection();
            $this->ensureWhatsAppInfrastructure($db);

            $confirmed = $this->service->receiveSimulatedInbound($userId, $patientId, $text);
            audit_log_event($userId, 'whatsapp_inbound_simulated', 'Mensagem simulada recebida para paciente #' . $patientId . '.');
            if ($confirmed) {
                redirect(base_url('route=whatsapp&message=Mensagem recebida e consulta confirmada automaticamente'));
            }

            redirect(base_url('route=whatsapp&message=Mensagem recebida (sem confirmação automática)'));
        } catch (Throwable $e) {
            AppLogger::error('WhatsApp simulated receive failed', [
                'user_id' => $userId,
                'patient_id' => $patientId,
                'error' => $e->getMessage(),
            ]);
            redirect(base_url('route=whatsapp&message=' . urlencode('Falha ao processar mensagem recebida.')));
        }
    }

    public function runAutomations(): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            redirect(base_url('route=whatsapp&message=Token inválido'));
        }

        $userId = (int) (Auth::user()['id'] ?? 0);
        try {
            $db = Database::connection();
            $this->ensureWhatsAppInfrastructure($db);

            $runner = new AutomationJobRunner();
            $execution = $runner->runUserAutomation($userId, false, function () use ($userId): array {
                return $this->service->runAllAutomations($userId);
            });
            $result = (array) ($execution['result'] ?? []);

            if (($execution['status'] ?? '') === 'skipped') {
                redirect(base_url('route=whatsapp&message=' . urlencode((string) ($execution['message'] ?? 'Automações em execução.'))));
            }

            if (($execution['status'] ?? '') === 'failed') {
                redirect(base_url('route=whatsapp&message=' . urlencode('Falha ao executar automações: ' . (string) ($execution['message'] ?? 'erro interno'))));
            }

            $text = sprintf(
                'Automações executadas. Confirmações: %d | Lembretes: %d | Follow-up: %d | Total: %d',
                (int) $result['confirmations'],
                (int) $result['reminders'],
                (int) $result['followups'],
                (int) $result['total']
            );
            audit_log_event($userId, 'whatsapp_automations_run', 'Automações executadas. Total=' . (int) $result['total'] . '.');

            redirect(base_url('route=whatsapp&message=' . urlencode($text)));
        } catch (Throwable $e) {
            AppLogger::error('WhatsApp automations failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            redirect(base_url('route=whatsapp&message=' . urlencode('Falha ao executar automações.')));
        }
    }

    public function runReminders(): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            redirect(base_url('route=whatsapp&message=Token inválido'));
        }

        $userId = (int) (Auth::user()['id'] ?? 0);
        try {
            $db = Database::connection();
            $this->ensureWhatsAppInfrastructure($db);

            $processed = $this->service->runReminders($userId);
            audit_log_event($userId, 'whatsapp_reminders_run', 'Lembretes executados: ' . $processed . '.');

            redirect(base_url('route=whatsapp&message=Lembretes executados: ' . $processed));
        } catch (Throwable $e) {
            AppLogger::error('WhatsApp reminders failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            redirect(base_url('route=whatsapp&message=' . urlencode('Falha ao executar lembretes.')));
        }
    }

    public function runFollowUps(): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            redirect(base_url('route=whatsapp&message=Token inválido'));
        }

        $userId = (int) (Auth::user()['id'] ?? 0);
        try {
            $db = Database::connection();
            $this->ensureWhatsAppInfrastructure($db);

            $processed = $this->service->runFollowUps($userId);
            audit_log_event($userId, 'whatsapp_followups_run', 'Follow-ups executados: ' . $processed . '.');

            redirect(base_url('route=whatsapp&message=Follow-up executado: ' . $processed));
        } catch (Throwable $e) {
            AppLogger::error('WhatsApp followups failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            redirect(base_url('route=whatsapp&message=' . urlencode('Falha ao executar follow-up.')));
        }
    }

    private function normalizeFilters(array $input): array
    {
        $direction = mb_strtolower(trim((string) ($input['direction'] ?? '')), 'UTF-8');
        if (!in_array($direction, ['inbound', 'outbound'], true)) {
            $direction = '';
        }

        $status = mb_strtolower(trim((string) ($input['status'] ?? '')), 'UTF-8');
        $allowedStatus = ['queued', 'sent', 'delivered', 'read', 'failed', 'received'];
        if (!in_array($status, $allowedStatus, true)) {
            $status = '';
        }

        $dateFrom = trim((string) ($input['date_from'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $dateFrom = '';
        }

        $dateTo = trim((string) ($input['date_to'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $dateTo = '';
        }

        return [
            'patient_id' => (int) ($input['patient_id'] ?? 0),
            'direction' => $direction,
            'status' => $status,
            'q' => trim((string) ($input['q'] ?? '')),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'sort_by' => $this->normalizeSortBy((string) ($input['sort_by'] ?? 'timestamp')),
            'sort_dir' => $this->normalizeSortDir((string) ($input['sort_dir'] ?? 'desc')),
        ];
    }

    private function normalizeSortBy(string $sortBy): string
    {
        $value = mb_strtolower(trim($sortBy), 'UTF-8');
        $allowed = ['timestamp', 'direction', 'status', 'patient'];
        return in_array($value, $allowed, true) ? $value : 'timestamp';
    }

    private function normalizeSortDir(string $sortDir): string
    {
        $value = mb_strtolower(trim($sortDir), 'UTF-8');
        return in_array($value, ['asc', 'desc'], true) ? $value : 'desc';
    }

    private function buildOrderBySql(array $filters): string
    {
        $sortBy = (string) ($filters['sort_by'] ?? 'timestamp');
        $sortDir = (string) ($filters['sort_dir'] ?? 'desc');

        $column = match ($sortBy) {
            'direction' => 'wm.direction',
            'status' => 'wm.status',
            'patient' => 'p.nome',
            default => 'wm.timestamp',
        };

        $direction = $sortDir === 'asc' ? 'ASC' : 'DESC';
        return 'ORDER BY ' . $column . ' ' . $direction . ', wm.timestamp DESC';
    }

    private function buildMessageFiltersSql(int $userId, array $filters): array
    {
        $where = ['wm.user_id = :user_id'];
        $params = ['user_id' => $userId];

        $patientId = (int) ($filters['patient_id'] ?? 0);
        if ($patientId > 0) {
            $where[] = 'wm.patient_id = :patient_id';
            $params['patient_id'] = $patientId;
        }

        $direction = (string) ($filters['direction'] ?? '');
        if ($direction !== '') {
            $where[] = 'wm.direction = :direction';
            $params['direction'] = $direction;
        }

        $status = (string) ($filters['status'] ?? '');
        if ($status !== '') {
            $where[] = 'wm.status = :status';
            $params['status'] = $status;
        }

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where[] = '(wm.texto LIKE :q OR p.nome LIKE :q)';
            $params['q'] = '%' . $query . '%';
        }

        $dateFrom = (string) ($filters['date_from'] ?? '');
        if ($dateFrom !== '') {
            $where[] = 'wm.timestamp >= :date_from';
            $params['date_from'] = $dateFrom . ' 00:00:00';
        }

        $dateTo = (string) ($filters['date_to'] ?? '');
        if ($dateTo !== '') {
            $where[] = 'wm.timestamp <= :date_to';
            $params['date_to'] = $dateTo . ' 23:59:59';
        }

        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    private function buildSummary(PDO $db, string $whereSql, array $params): array
    {
        $stmt = $db->prepare(
            'SELECT
                COUNT(*) AS total,
                SUM(wm.direction = "outbound") AS outbound_total,
                SUM(wm.direction = "inbound") AS inbound_total,
                SUM(wm.status IN ("delivered", "read")) AS delivered_or_read_total,
                SUM(wm.status = "failed") AS failed_total,
                COUNT(DISTINCT wm.patient_id) AS unique_patients
             FROM whatsapp_messages wm
             LEFT JOIN patients p ON p.id = wm.patient_id
             ' . $whereSql
        );
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];

        $outbound = (int) ($row['outbound_total'] ?? 0);
        $deliveredOrRead = (int) ($row['delivered_or_read_total'] ?? 0);

        return [
            'total' => (int) ($row['total'] ?? 0),
            'outbound_total' => $outbound,
            'inbound_total' => (int) ($row['inbound_total'] ?? 0),
            'delivered_or_read_total' => $deliveredOrRead,
            'failed_total' => (int) ($row['failed_total'] ?? 0),
            'unique_patients' => (int) ($row['unique_patients'] ?? 0),
            'delivery_rate' => $outbound > 0 ? round(($deliveredOrRead / $outbound) * 100, 1) : 0.0,
        ];
    }

    private function buildDailyTrend(PDO $db, string $whereSql, array $params, array $filters): array
    {
        $extraWhere = '';
        $trendParams = $params;

        $dateFrom = (string) ($filters['date_from'] ?? '');
        $dateTo = (string) ($filters['date_to'] ?? '');
        if ($dateFrom === '' && $dateTo === '') {
            $extraWhere = ' AND wm.timestamp >= :trend_start';
            $trendParams['trend_start'] = date('Y-m-d 00:00:00', strtotime('-30 days'));
        }

        $stmt = $db->prepare(
            'SELECT DATE(wm.timestamp) AS day_date,
                    DATE_FORMAT(wm.timestamp, "%d/%m") AS day_label,
                    SUM(wm.direction = "outbound") AS outbound_total,
                    SUM(wm.direction = "inbound") AS inbound_total
             FROM whatsapp_messages wm
             LEFT JOIN patients p ON p.id = wm.patient_id
             ' . $whereSql . $extraWhere . '
             GROUP BY DATE(wm.timestamp)
             ORDER BY DATE(wm.timestamp) ASC'
        );
        $stmt->execute($trendParams);

        $rows = $stmt->fetchAll();
        return array_map(static function (array $row): array {
            return [
                'day' => (string) ($row['day_label'] ?? ''),
                'outbound' => (int) ($row['outbound_total'] ?? 0),
                'inbound' => (int) ($row['inbound_total'] ?? 0),
            ];
        }, $rows);
    }

    private function buildStatusBreakdown(PDO $db, string $whereSql, array $params): array
    {
        $stmt = $db->prepare(
            'SELECT wm.status, COUNT(*) AS total
             FROM whatsapp_messages wm
             LEFT JOIN patients p ON p.id = wm.patient_id
             ' . $whereSql . '
             GROUP BY wm.status
             ORDER BY total DESC, wm.status ASC'
        );
        $stmt->execute($params);

        $rows = $stmt->fetchAll();
        $result = [];
        foreach ($rows as $row) {
            $status = trim((string) ($row['status'] ?? ''));
            if ($status === '') {
                $status = 'unknown';
            }

            $result[$status] = (int) ($row['total'] ?? 0);
        }

        return $result;
    }

    private function buildStatusInsight(array $statusBreakdown): array
    {
        if (empty($statusBreakdown)) {
            return [
                'hasData' => false,
                'message' => 'Sem dados de status no período filtrado.',
            ];
        }

        $total = array_sum($statusBreakdown);
        $topStatus = '';
        $topCount = 0;
        foreach ($statusBreakdown as $status => $count) {
            if ($count > $topCount) {
                $topStatus = (string) $status;
                $topCount = (int) $count;
            }
        }

        $share = $total > 0 ? round(($topCount / $total) * 100, 1) : 0.0;
        return [
            'hasData' => true,
            'topStatus' => $topStatus,
            'topCount' => $topCount,
            'total' => $total,
            'topShare' => $share,
            'message' => sprintf('Status mais frequente: %s (%d de %d | %.1f%%).', $topStatus, $topCount, $total, $share),
        ];
    }

    private function columnExists(PDO $db, string $table, string $column): bool
    {
        try {
            $stmt = $db->prepare(
                'SELECT COUNT(*) AS total
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table_name
                   AND COLUMN_NAME = :column_name'
            );
            $stmt->execute([
                'table_name' => $table,
                'column_name' => $column,
            ]);

            return (int) ($stmt->fetch()['total'] ?? 0) > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function tableExists(PDO $db, string $table): bool
    {
        try {
            $stmt = $db->prepare(
                'SELECT COUNT(*) AS total
                 FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table_name'
            );
            $stmt->execute(['table_name' => $table]);
            return (int) ($stmt->fetch()['total'] ?? 0) > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function ensureWhatsAppInfrastructure(PDO $db): void
    {
        $definitions = [
            'whatsapp_messages' =>
                'CREATE TABLE IF NOT EXISTS whatsapp_messages (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED NOT NULL,
                    patient_id INT UNSIGNED NULL,
                    appointment_id INT UNSIGNED NULL,
                    direction VARCHAR(20) NOT NULL,
                    texto TEXT NOT NULL,
                    status VARCHAR(30) NOT NULL DEFAULT "sent",
                    external_message_id VARCHAR(100) NULL,
                    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_messages_user (user_id),
                    KEY idx_messages_patient (patient_id),
                    KEY idx_messages_appointment (appointment_id),
                    KEY idx_messages_ext (external_message_id)
                ) ENGINE=InnoDB',
            'automation_logs' =>
                'CREATE TABLE IF NOT EXISTS automation_logs (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED NOT NULL,
                    appointment_id INT UNSIGNED NULL,
                    tipo_automacao VARCHAR(50) NOT NULL,
                    status_envio VARCHAR(30) NOT NULL,
                    detalhes VARCHAR(255) NULL,
                    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_automation_user (user_id),
                    KEY idx_automation_appointment (appointment_id)
                ) ENGINE=InnoDB',
            'whatsapp_auto_replies' =>
                'CREATE TABLE IF NOT EXISTS whatsapp_auto_replies (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED NOT NULL,
                    keyword VARCHAR(100) NOT NULL,
                    reply TEXT NOT NULL,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    sort_order INT NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NULL,
                    KEY idx_auto_replies_user (user_id, is_active)
                ) ENGINE=InnoDB',
            'whatsapp_conversation_state' =>
                'CREATE TABLE IF NOT EXISTS whatsapp_conversation_state (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED NOT NULL,
                    patient_id INT UNSIGNED NOT NULL,
                    state VARCHAR(40) NOT NULL,
                    payload TEXT NULL,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uniq_conversation_state (user_id, patient_id),
                    KEY idx_conversation_user (user_id)
                ) ENGINE=InnoDB',
        ];

        foreach ($definitions as $table => $sql) {
            if ($this->tableExists($db, $table)) {
                continue;
            }

            try {
                $db->exec($sql);
            } catch (Throwable $e) {
                // DDL failure (e.g., permission denied) must not make the module unavailable.
                AppLogger::error('WhatsApp infrastructure ensure failed', [
                    'table' => $table,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}

