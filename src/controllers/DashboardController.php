<?php

declare(strict_types=1);

final class DashboardController
{
    private const MONTHLY_CONVERSION_TARGET = 60.0;

    /** Períodos válidos e seus rótulos para exibição. */
    private const PERIODS = [
        '7d'  => '�sltimos 7 dias',
        '30d' => '�sltimos 30 dias',
        '3m'  => '�sltimos 3 meses',
        '12m' => '�sltimos 12 meses',
    ];

    public function index(): void
    {
        $user = Auth::user();
        $userId = (int) ($user['id'] ?? 0);
        $billing = new BillingService();
        $subscriptionStatus = $billing->getSubscriptionApiPayload($userId);

        try {
            $db = Database::connection();
            $patientActiveClause = $this->columnExists($db, 'patients', 'deleted_at') ? ' AND deleted_at IS NULL' : '';

        // �"?�"? Período selecionado �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
        $period = (string) ($_GET['period'] ?? '30d');
        if (!array_key_exists($period, self::PERIODS)) {
            $period = '30d';
        }

        [$dateFrom, $dateTo] = $this->periodRange($period);

        // �"?�"? Total de pacientes (sem filtro de período) �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
        $stmt = $db->prepare('SELECT COUNT(*) AS total FROM patients WHERE user_id = :uid' . $patientActiveClause);
        $stmt->execute(['uid' => $userId]);
        $totalPatients = (int) ($stmt->fetch()['total'] ?? 0);

        // �"?�"? Leads ativos no período �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
        $stmt = $db->prepare('SELECT COUNT(*) AS total FROM patients WHERE user_id = :uid AND status = "lead"' . $patientActiveClause . ' AND created_at BETWEEN :from AND :to');
        $stmt->execute(['uid' => $userId, 'from' => $dateFrom, 'to' => $dateTo]);
        $newLeads = (int) ($stmt->fetch()['total'] ?? 0);

        // �"?�"? Consultas no período �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
        $stmt = $db->prepare('SELECT COUNT(*) AS total FROM appointments WHERE user_id = :uid AND data_hora BETWEEN :from AND :to');
        $stmt->execute(['uid' => $userId, 'from' => $dateFrom, 'to' => $dateTo]);
        $totalAppointments = (int) ($stmt->fetch()['total'] ?? 0);

        $stmt = $db->prepare('SELECT COUNT(*) AS total FROM appointments WHERE user_id = :uid AND status = "confirmada" AND data_hora BETWEEN :from AND :to');
        $stmt->execute(['uid' => $userId, 'from' => $dateFrom, 'to' => $dateTo]);
        $confirmed = (int) ($stmt->fetch()['total'] ?? 0);

        $stmt = $db->prepare('SELECT COUNT(*) AS total FROM appointments WHERE user_id = :uid AND status = "faltou" AND data_hora BETWEEN :from AND :to');
        $stmt->execute(['uid' => $userId, 'from' => $dateFrom, 'to' => $dateTo]);
        $noShow = (int) ($stmt->fetch()['total'] ?? 0);

        $stmt = $db->prepare('SELECT COUNT(*) AS total FROM appointments WHERE user_id = :uid AND status = "cancelada" AND data_hora BETWEEN :from AND :to');
        $stmt->execute(['uid' => $userId, 'from' => $dateFrom, 'to' => $dateTo]);
        $cancelled = (int) ($stmt->fetch()['total'] ?? 0);

        // �"?�"? Mensagens no período �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
        $stmt = $db->prepare('SELECT COUNT(*) AS total FROM whatsapp_messages WHERE user_id = :uid AND direction = "outbound" AND timestamp BETWEEN :from AND :to');
        $stmt->execute(['uid' => $userId, 'from' => $dateFrom, 'to' => $dateTo]);
        $messagesSent = (int) ($stmt->fetch()['total'] ?? 0);

        $stmt = $db->prepare('SELECT COUNT(*) AS total FROM whatsapp_messages WHERE user_id = :uid AND direction = "inbound" AND timestamp BETWEEN :from AND :to');
        $stmt->execute(['uid' => $userId, 'from' => $dateFrom, 'to' => $dateTo]);
        $messagesReceived = (int) ($stmt->fetch()['total'] ?? 0);

        $stmt = $db->prepare('SELECT COUNT(*) AS total FROM whatsapp_messages WHERE user_id = :uid AND direction = "outbound" AND status IN ("delivered","read") AND timestamp BETWEEN :from AND :to');
        $stmt->execute(['uid' => $userId, 'from' => $dateFrom, 'to' => $dateTo]);
        $deliveredOrRead = (int) ($stmt->fetch()['total'] ?? 0);

        // �"?�"? Taxas �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
        $confirmationRate = $totalAppointments > 0 ? round(($confirmed  / $totalAppointments) * 100, 1) : 0;
        $noShowRate       = $totalAppointments > 0 ? round(($noShow     / $totalAppointments) * 100, 1) : 0;
        $cancellationRate = $totalAppointments > 0 ? round(($cancelled  / $totalAppointments) * 100, 1) : 0;
        $deliveryRate     = $messagesSent      > 0 ? round(($deliveredOrRead / $messagesSent) * 100, 1) : 0;

        // �"?�"? Séries temporais para gráficos �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
        $weeklyData        = $this->weeklyAppointments($userId, $dateFrom, $dateTo, $db);
        $statusDistribution= $this->statusDistribution($userId, $dateFrom, $dateTo, $db);
        $dailyMessages     = $this->dailyMessages($userId, $dateFrom, $dateTo, $db);
        $leadPipeline      = $this->leadPipeline($userId, $dateFrom, $dateTo, $db, $patientActiveClause);
        $patientPerformance = $this->patientPerformance($userId, $dateFrom, $dateTo, $db);
        $monthlyConversionGoal = $this->monthlyConversionGoal($userId, $db, $patientActiveClause);

        // Próximas consultas (agenda do dia e amanhã)
        $upcomingAppointments = $this->upcomingAppointments($userId, $db);

        View::render('dashboard', [
            'user'                 => $user,
            'period'               => $period,
            'periods'              => self::PERIODS,
            'dateFrom'             => $dateFrom,
            'dateTo'               => $dateTo,
            'totalPatients'        => $totalPatients,
            'newLeads'             => $newLeads,
            'totalAppointments'    => $totalAppointments,
            'confirmed'            => $confirmed,
            'noShow'               => $noShow,
            'cancelled'            => $cancelled,
            'confirmationRate'     => $confirmationRate,
            'noShowRate'           => $noShowRate,
            'cancellationRate'     => $cancellationRate,
            'messagesSent'         => $messagesSent,
            'messagesReceived'     => $messagesReceived,
            'deliveryRate'         => $deliveryRate,
            'weeklyData'           => $weeklyData,
            'statusDistribution'   => $statusDistribution,
            'dailyMessages'        => $dailyMessages,
            'leadPipeline'         => $leadPipeline,
            'patientPerformance'   => $patientPerformance,
            'monthlyConversionGoal'=> $monthlyConversionGoal,
            'upcomingAppointments' => $upcomingAppointments,
            'subscriptionStatus'   => $subscriptionStatus,
        ]);
        } catch (Throwable $e) {
            AppLogger::error('Dashboard load failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $period = (string) ($_GET['period'] ?? '30d');
            if (!array_key_exists($period, self::PERIODS)) {
                $period = '30d';
            }
            [$dateFrom, $dateTo] = $this->periodRange($period);

            View::render('dashboard', [
                'user'                 => $user,
                'period'               => $period,
                'periods'              => self::PERIODS,
                'dateFrom'             => $dateFrom,
                'dateTo'               => $dateTo,
                'totalPatients'        => 0,
                'newLeads'             => 0,
                'totalAppointments'    => 0,
                'confirmed'            => 0,
                'noShow'               => 0,
                'cancelled'            => 0,
                'confirmationRate'     => 0.0,
                'noShowRate'           => 0.0,
                'cancellationRate'     => 0.0,
                'messagesSent'         => 0,
                'messagesReceived'     => 0,
                'deliveryRate'         => 0.0,
                'weeklyData'           => [],
                'statusDistribution'   => [],
                'dailyMessages'        => [],
                'leadPipeline'         => [
                    'leads' => 0,
                    'with_appointment' => 0,
                    'with_confirmation' => 0,
                    'schedule_rate' => 0.0,
                    'confirmation_rate' => 0.0,
                ],
                'patientPerformance'   => [
                    'patients_with_appointments' => 0,
                    'returning_patients' => 0,
                    'recurrence_rate' => 0.0,
                    'avg_appointments_per_patient' => 0.0,
                    'avg_revenue_per_patient' => 0.0,
                    'top_patients' => [],
                ],
                'monthlyConversionGoal'=> [
                    'month_label' => date('m/Y'),
                    'leads' => 0,
                    'confirmed' => 0,
                    'conversion_rate' => 0.0,
                    'target_rate' => self::MONTHLY_CONVERSION_TARGET,
                    'achievement_rate' => 0.0,
                ],
                'upcomingAppointments' => [],
                'subscriptionStatus'   => null,
            ]);
        }
    }

    // �"?�"? Helpers privados �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?

    /**
     * Retorna [dateFrom, dateTo] no formato 'Y-m-d H:i:s' para o período.
     * @return array{string, string}
     */
    private function periodRange(string $period): array
    {
        $to   = (new DateTime())->format('Y-m-d 23:59:59');
        $from = match ($period) {
            '7d'  => (new DateTime())->modify('-6 days')->format('Y-m-d 00:00:00'),
            '3m'  => (new DateTime())->modify('-3 months')->format('Y-m-d 00:00:00'),
            '12m' => (new DateTime())->modify('-12 months')->format('Y-m-d 00:00:00'),
            default => (new DateTime())->modify('-29 days')->format('Y-m-d 00:00:00'),
        };
        return [$from, $to];
    }

    /**
     * Retorna contagem de consultas agrupadas por semana dentro do período.
     * @return array<int, array{week: string, total: int}>
     */
    private function weeklyAppointments(int $userId, string $from, string $to, \PDO $db): array
    {
        $stmt = $db->prepare(
            "SELECT DATE_FORMAT(data_hora, '%Y-%u') AS week_key,
                    MIN(DATE_FORMAT(data_hora, '%d/%m'))  AS week_label,
                    COUNT(*) AS total
             FROM appointments
             WHERE user_id = :uid AND data_hora BETWEEN :from AND :to
             GROUP BY week_key
             ORDER BY week_key ASC"
        );
        $stmt->execute(['uid' => $userId, 'from' => $from, 'to' => $to]);
        return array_map(static function (array $row): array {
            return ['week' => (string) $row['week_label'], 'total' => (int) $row['total']];
        }, $stmt->fetchAll());
    }

    /**
     * Distribuição de status das consultas no período.
     * @return array<string, int>
     */
    private function statusDistribution(int $userId, string $from, string $to, \PDO $db): array
    {
        $stmt = $db->prepare(
            'SELECT status, COUNT(*) AS total
             FROM appointments
             WHERE user_id = :uid AND data_hora BETWEEN :from AND :to
             GROUP BY status'
        );
        $stmt->execute(['uid' => $userId, 'from' => $from, 'to' => $to]);
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[(string) $row['status']] = (int) $row['total'];
        }
        return $result;
    }

    /**
     * Mensagens enviadas e recebidas por dia no período.
     * @return array<int, array{day: string, sent: int, received: int}>
     */
    private function dailyMessages(int $userId, string $from, string $to, \PDO $db): array
    {
        $stmt = $db->prepare(
            "SELECT DATE_FORMAT(timestamp,'%d/%m') AS day,
                    SUM(direction='outbound') AS sent,
                    SUM(direction='inbound')  AS received
             FROM whatsapp_messages
             WHERE user_id = :uid AND timestamp BETWEEN :from AND :to
             GROUP BY DATE(timestamp)
             ORDER BY DATE(timestamp) ASC"
        );
        $stmt->execute(['uid' => $userId, 'from' => $from, 'to' => $to]);
        return array_map(static fn(array $r): array => [
            'day'      => (string) $r['day'],
            'sent'     => (int) $r['sent'],
            'received' => (int) $r['received'],
        ], $stmt->fetchAll());
    }

    /**
     * Próximas consultas (hoje + 2 dias).
     * @return array<int, array{data_hora: string, paciente_nome: string, procedimento: string, status: string}>
     */
    private function upcomingAppointments(int $userId, \PDO $db): array
    {
        $stmt = $db->prepare(
            "SELECT a.data_hora, p.nome AS paciente_nome, a.procedimento, a.status
             FROM appointments a
             INNER JOIN patients p ON p.id = a.patient_id
             WHERE a.user_id = :uid
               AND a.status IN ('agendada','confirmada')
               AND a.data_hora BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 2 DAY)
             ORDER BY a.data_hora ASC
             LIMIT 8"
        );
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Funil comercial de leads no período selecionado.
     * @return array{leads:int, with_appointment:int, with_confirmation:int, schedule_rate:float, confirmation_rate:float}
     */
    private function leadPipeline(int $userId, string $from, string $to, \PDO $db, string $patientActiveClause): array
    {
        $stmt = $db->prepare(
            'SELECT COUNT(*) AS total
             FROM patients
             WHERE user_id = :uid
               ' . $patientActiveClause . '
               AND status = "lead"
               AND created_at BETWEEN :from AND :to'
        );
        $stmt->execute(['uid' => $userId, 'from' => $from, 'to' => $to]);
        $leads = (int) ($stmt->fetch()['total'] ?? 0);

        $stmt = $db->prepare(
            'SELECT COUNT(DISTINCT p.id) AS total
             FROM patients p
             INNER JOIN appointments a ON a.patient_id = p.id AND a.user_id = :uid
             WHERE p.user_id = :uid
                             ' . str_replace('deleted_at', 'p.deleted_at', $patientActiveClause) . '
               AND p.status = "lead"
               AND p.created_at BETWEEN :from AND :to'
        );
        $stmt->execute(['uid' => $userId, 'from' => $from, 'to' => $to]);
        $withAppointment = (int) ($stmt->fetch()['total'] ?? 0);

        $stmt = $db->prepare(
            'SELECT COUNT(DISTINCT p.id) AS total
             FROM patients p
             INNER JOIN appointments a ON a.patient_id = p.id AND a.user_id = :uid
             WHERE p.user_id = :uid
                             ' . str_replace('deleted_at', 'p.deleted_at', $patientActiveClause) . '
               AND p.status = "lead"
               AND p.created_at BETWEEN :from AND :to
               AND a.status IN ("confirmada", "realizada")'
        );
        $stmt->execute(['uid' => $userId, 'from' => $from, 'to' => $to]);
        $withConfirmation = (int) ($stmt->fetch()['total'] ?? 0);

        $scheduleRate = $leads > 0 ? round(($withAppointment / $leads) * 100, 1) : 0.0;
        $confirmationRate = $leads > 0 ? round(($withConfirmation / $leads) * 100, 1) : 0.0;

        return [
            'leads' => $leads,
            'with_appointment' => $withAppointment,
            'with_confirmation' => $withConfirmation,
            'schedule_rate' => $scheduleRate,
            'confirmation_rate' => $confirmationRate,
        ];
    }

    /**
     * Performance de pacientes no período selecionado.
     * @return array{patients_with_appointments:int, returning_patients:int, recurrence_rate:float, avg_appointments_per_patient:float, avg_revenue_per_patient:float, top_patients:array<int, array{patient_name:string, appointments_count:int, confirmed_count:int, paid_total:float}>}
     */
    private function patientPerformance(int $userId, string $from, string $to, \PDO $db): array
    {
        $stmt = $db->prepare(
            'SELECT COUNT(DISTINCT patient_id) AS total
             FROM appointments
             WHERE user_id = :uid
               AND data_hora BETWEEN :from AND :to'
        );
        $stmt->execute(['uid' => $userId, 'from' => $from, 'to' => $to]);
        $patientsWithAppointments = (int) ($stmt->fetch()['total'] ?? 0);

        $stmt = $db->prepare(
            'SELECT COUNT(*) AS total
             FROM (
                SELECT patient_id
                FROM appointments
                WHERE user_id = :uid
                  AND data_hora BETWEEN :from AND :to
                GROUP BY patient_id
                HAVING COUNT(*) >= 2
             ) recurring'
        );
        $stmt->execute(['uid' => $userId, 'from' => $from, 'to' => $to]);
        $returningPatients = (int) ($stmt->fetch()['total'] ?? 0);

        $recurrenceRate = $patientsWithAppointments > 0
            ? round(($returningPatients / $patientsWithAppointments) * 100, 1)
            : 0.0;

        $totalAppointments = $this->countAppointments($userId, $from, $to, $db);
        $avgAppointmentsPerPatient = $patientsWithAppointments > 0
            ? round($totalAppointments / $patientsWithAppointments, 2)
            : 0.0;

        $stmt = $db->prepare(
            'SELECT COALESCE(SUM(valor_cobrado),0) AS total
             FROM appointments
             WHERE user_id = :uid
               AND pago = 1
               AND valor_cobrado IS NOT NULL
               AND data_hora BETWEEN :from AND :to'
        );
        $stmt->execute(['uid' => $userId, 'from' => $from, 'to' => $to]);
        $paidRevenue = (float) ($stmt->fetch()['total'] ?? 0);
        $avgRevenuePerPatient = $patientsWithAppointments > 0 ? round($paidRevenue / $patientsWithAppointments, 2) : 0.0;

        $stmt = $db->prepare(
            'SELECT p.nome AS patient_name,
                    COUNT(a.id) AS appointments_count,
                    SUM(a.status IN ("confirmada", "realizada")) AS confirmed_count,
                    COALESCE(SUM(CASE WHEN a.pago = 1 AND a.valor_cobrado IS NOT NULL THEN a.valor_cobrado ELSE 0 END),0) AS paid_total
             FROM appointments a
             INNER JOIN patients p ON p.id = a.patient_id
             WHERE a.user_id = :uid
               AND a.data_hora BETWEEN :from AND :to
             GROUP BY p.id, p.nome
             ORDER BY appointments_count DESC, confirmed_count DESC
             LIMIT 5'
        );
        $stmt->execute(['uid' => $userId, 'from' => $from, 'to' => $to]);

        $topPatients = array_map(static fn(array $row): array => [
            'patient_name' => (string) $row['patient_name'],
            'appointments_count' => (int) $row['appointments_count'],
            'confirmed_count' => (int) $row['confirmed_count'],
            'paid_total' => (float) $row['paid_total'],
        ], $stmt->fetchAll());

        return [
            'patients_with_appointments' => $patientsWithAppointments,
            'returning_patients' => $returningPatients,
            'recurrence_rate' => $recurrenceRate,
            'avg_appointments_per_patient' => $avgAppointmentsPerPatient,
            'avg_revenue_per_patient' => $avgRevenuePerPatient,
            'top_patients' => $topPatients,
        ];
    }

    private function countAppointments(int $userId, string $from, string $to, \PDO $db): int
    {
        $stmt = $db->prepare(
            'SELECT COUNT(*) AS total
             FROM appointments
             WHERE user_id = :uid
               AND data_hora BETWEEN :from AND :to'
        );
        $stmt->execute(['uid' => $userId, 'from' => $from, 'to' => $to]);
        return (int) ($stmt->fetch()['total'] ?? 0);
    }

    /**
     * Meta mensal de conversão de leads para confirmação/realização.
     * @return array{month_label:string, leads:int, confirmed:int, conversion_rate:float, target_rate:float, achievement_rate:float}
     */
    private function monthlyConversionGoal(int $userId, \PDO $db, string $patientActiveClause): array
    {
        $monthStart = (new DateTime('first day of this month'))->format('Y-m-d 00:00:00');
        $monthEnd = (new DateTime('last day of this month'))->format('Y-m-d 23:59:59');
        $monthLabel = (new DateTime())->format('m/Y');

        $stmt = $db->prepare(
            'SELECT COUNT(*) AS total
             FROM patients
             WHERE user_id = :uid
             ' . $patientActiveClause . '
               AND status = "lead"
               AND created_at BETWEEN :from AND :to'
        );
        $stmt->execute(['uid' => $userId, 'from' => $monthStart, 'to' => $monthEnd]);
        $leads = (int) ($stmt->fetch()['total'] ?? 0);

        $stmt = $db->prepare(
            'SELECT COUNT(DISTINCT p.id) AS total
             FROM patients p
             INNER JOIN appointments a ON a.patient_id = p.id AND a.user_id = :uid
             WHERE p.user_id = :uid
                             ' . str_replace('deleted_at', 'p.deleted_at', $patientActiveClause) . '
               AND p.status = "lead"
               AND p.created_at BETWEEN :from AND :to
               AND a.status IN ("confirmada", "realizada")'
        );
        $stmt->execute(['uid' => $userId, 'from' => $monthStart, 'to' => $monthEnd]);
        $confirmed = (int) ($stmt->fetch()['total'] ?? 0);

        $conversionRate = $leads > 0 ? round(($confirmed / $leads) * 100, 1) : 0.0;
        
        $targetRate = $this->fetchMonthlyConversionTarget($userId, $db);
        
        $achievementRate = $targetRate > 0 ? round(min(200.0, ($conversionRate / $targetRate) * 100), 1) : 0.0;

        return [
            'month_label' => $monthLabel,
            'leads' => $leads,
            'confirmed' => $confirmed,
            'conversion_rate' => $conversionRate,
            'target_rate' => $targetRate,
            'achievement_rate' => $achievementRate,
        ];
    }

    private function fetchMonthlyConversionTarget(int $userId, \PDO $db): float
    {
        $defaultTarget = self::MONTHLY_CONVERSION_TARGET;

        try {
            $stmt = $db->prepare(
                'SELECT COUNT(*) AS total
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = "settings"
                   AND COLUMN_NAME = "meta_conversao_mensal"'
            );
            $stmt->execute();
            $row = $stmt->fetch() ?: [];
            if ((int) ($row['total'] ?? 0) <= 0) {
                return $defaultTarget;
            }

            $settingsStmt = $db->prepare('SELECT meta_conversao_mensal FROM settings WHERE user_id = :uid LIMIT 1');
            $settingsStmt->execute(['uid' => $userId]);
            $settings = $settingsStmt->fetch() ?: [];

            return (float) ($settings['meta_conversao_mensal'] ?? $defaultTarget);
        } catch (Throwable $e) {
            AppLogger::error('Dashboard conversion target fallback', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return $defaultTarget;
        }
    }

    private function columnExists(\PDO $db, string $table, string $column): bool
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
}


