<?php

declare(strict_types=1);

final class ReportsController
{
    private const PRESETS = ['7d', '30d', 'month'];

    public function index(): void
    {
        $userId = (int) (Auth::user()['id'] ?? 0);
        $db = Database::connection();

        $preset = (string) ($_GET['preset'] ?? '');
        if (!in_array($preset, self::PRESETS, true)) {
            $preset = '';
        }

        [$from, $to] = $this->resolveRange((string) ($_GET['from'] ?? ''), (string) ($_GET['to'] ?? ''), $preset);
        $compareEnabled = (int) ($_GET['compare'] ?? 0) === 1;

        $summary = $this->fetchSummary($userId, $from, $to, $db);
        $statusRows = $this->fetchStatusRows($userId, $from, $to, $db);
        $procedureRows = $this->fetchProcedureRows($userId, $from, $to, $db);
        $dailyRows = $this->fetchDailyRows($userId, $from, $to, $db);

        $summaryPrevious = null;
        $comparison = null;
        if ($compareEnabled) {
            [$previousFrom, $previousTo] = $this->previousRange($from, $to);
            $summaryPrevious = $this->fetchSummary($userId, $previousFrom, $previousTo, $db);
            $comparison = $this->buildComparison($summary, $summaryPrevious);
        }

        View::render('reports/index', [
            'from' => $from,
            'to' => $to,
            'preset' => $preset,
            'compareEnabled' => $compareEnabled,
            'summary' => $summary,
            'summaryPrevious' => $summaryPrevious,
            'comparison' => $comparison,
            'statusRows' => $statusRows,
            'procedureRows' => $procedureRows,
            'dailyRows' => $dailyRows,
        ]);
    }

    public function exportCsv(): void
    {
        $userId = (int) (Auth::user()['id'] ?? 0);
        $db = Database::connection();

        $preset = (string) ($_GET['preset'] ?? '');
        if (!in_array($preset, self::PRESETS, true)) {
            $preset = '';
        }
        [$from, $to] = $this->resolveRange((string) ($_GET['from'] ?? ''), (string) ($_GET['to'] ?? ''), $preset);
        $dailyRows = $this->fetchDailyRows($userId, $from, $to, $db);

        $fileName = 'relatorio-' . date('Ymd-His') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');

        $output = fopen('php://output', 'wb');
        if ($output === false) {
            http_response_code(500);
            echo 'Falha ao gerar CSV';
            exit;
        }

        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, ['Data', 'Consultas', 'Confirmadas/Realizadas', 'Receita recebida (R$)'], ';');

        foreach ($dailyRows as $row) {
            fputcsv($output, [
                (string) ($row['day'] ?? ''),
                (string) ((int) ($row['appointments_total'] ?? 0)),
                (string) ((int) ($row['confirmed_total'] ?? 0)),
                number_format((float) ($row['paid_revenue'] ?? 0), 2, '.', ''),
            ], ';');
        }

        fclose($output);
        exit;
    }

    public function exportPdf(): void
    {
        $userId = (int) (Auth::user()['id'] ?? 0);
        $db = Database::connection();

        $preset = (string) ($_GET['preset'] ?? '');
        if (!in_array($preset, self::PRESETS, true)) {
            $preset = '';
        }
        [$from, $to] = $this->resolveRange((string) ($_GET['from'] ?? ''), (string) ($_GET['to'] ?? ''), $preset);

        View::render('reports/export_pdf', [
            'from' => $from,
            'to' => $to,
            'summary' => $this->fetchSummary($userId, $from, $to, $db),
            'statusRows' => $this->fetchStatusRows($userId, $from, $to, $db),
            'procedureRows' => $this->fetchProcedureRows($userId, $from, $to, $db),
            'dailyRows' => $this->fetchDailyRows($userId, $from, $to, $db),
            'generatedAt' => date('d/m/Y H:i'),
            'user' => Auth::user(),
        ]);
        exit;
    }

    private function resolveRange(string $from, string $to, string $preset = ''): array
    {
        if ($preset !== '') {
            return $this->resolveRangeByPreset($preset);
        }

        $fromDate = DateTime::createFromFormat('Y-m-d', $from) ?: (new DateTime('first day of this month'));
        $toDate = DateTime::createFromFormat('Y-m-d', $to) ?: (new DateTime('last day of this month'));

        $fromDate->setTime(0, 0, 0);
        $toDate->setTime(23, 59, 59);

        if ($fromDate > $toDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        return [$fromDate, $toDate];
    }

    private function resolveRangeByPreset(string $preset): array
    {
        $today = new DateTime('today');

        if ($preset === '7d') {
            $from = (clone $today)->modify('-6 days')->setTime(0, 0, 0);
            $to = (clone $today)->setTime(23, 59, 59);
            return [$from, $to];
        }

        if ($preset === '30d') {
            $from = (clone $today)->modify('-29 days')->setTime(0, 0, 0);
            $to = (clone $today)->setTime(23, 59, 59);
            return [$from, $to];
        }

        $from = (clone $today)->modify('first day of this month')->setTime(0, 0, 0);
        $to = (clone $today)->modify('last day of this month')->setTime(23, 59, 59);
        return [$from, $to];
    }

    private function previousRange(DateTime $from, DateTime $to): array
    {
        $days = (int) $from->diff($to)->days + 1;
        $prevTo = (clone $from)->modify('-1 second');
        $prevFrom = (clone $prevTo)->modify('-' . ($days - 1) . ' days')->setTime(0, 0, 0);
        return [$prevFrom, $prevTo];
    }

    private function buildComparison(array $current, array $previous): array
    {
        return [
            'appointments_total' => $this->deltaBlock((float) ($current['appointments_total'] ?? 0), (float) ($previous['appointments_total'] ?? 0)),
            'appointments_confirmed' => $this->deltaBlock((float) ($current['appointments_confirmed'] ?? 0), (float) ($previous['appointments_confirmed'] ?? 0)),
            'confirmation_rate' => $this->deltaBlock((float) ($current['confirmation_rate'] ?? 0), (float) ($previous['confirmation_rate'] ?? 0)),
            'unique_patients' => $this->deltaBlock((float) ($current['unique_patients'] ?? 0), (float) ($previous['unique_patients'] ?? 0)),
            'paid_revenue' => $this->deltaBlock((float) ($current['paid_revenue'] ?? 0), (float) ($previous['paid_revenue'] ?? 0)),
        ];
    }

    private function deltaBlock(float $current, float $previous): array
    {
        $delta = round($current - $previous, 2);
        $deltaPct = $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : ($current > 0 ? 100.0 : 0.0);
        return [
            'current' => $current,
            'previous' => $previous,
            'delta' => $delta,
            'delta_pct' => $deltaPct,
        ];
    }

    private function fetchSummary(int $userId, DateTime $from, DateTime $to, PDO $db): array
    {
        $stmt = $db->prepare(
            'SELECT
                COUNT(*) AS appointments_total,
                SUM(status IN ("confirmada", "realizada")) AS appointments_confirmed,
                COUNT(DISTINCT patient_id) AS unique_patients,
                COALESCE(SUM(CASE WHEN pago = 1 AND valor_cobrado IS NOT NULL THEN valor_cobrado ELSE 0 END), 0) AS paid_revenue
             FROM appointments
             WHERE user_id = :uid
               AND data_hora BETWEEN :from AND :to'
        );
        $stmt->execute([
            'uid' => $userId,
            'from' => $from->format('Y-m-d H:i:s'),
            'to' => $to->format('Y-m-d H:i:s'),
        ]);

        $row = $stmt->fetch() ?: [];
        $appointmentsTotal = (int) ($row['appointments_total'] ?? 0);
        $appointmentsConfirmed = (int) ($row['appointments_confirmed'] ?? 0);

        return [
            'appointments_total' => $appointmentsTotal,
            'appointments_confirmed' => $appointmentsConfirmed,
            'confirmation_rate' => $appointmentsTotal > 0 ? round(($appointmentsConfirmed / $appointmentsTotal) * 100, 1) : 0.0,
            'unique_patients' => (int) ($row['unique_patients'] ?? 0),
            'paid_revenue' => (float) ($row['paid_revenue'] ?? 0),
        ];
    }

    private function fetchStatusRows(int $userId, DateTime $from, DateTime $to, PDO $db): array
    {
        $stmt = $db->prepare(
            'SELECT status, COUNT(*) AS total
             FROM appointments
             WHERE user_id = :uid
               AND data_hora BETWEEN :from AND :to
             GROUP BY status
             ORDER BY total DESC'
        );
        $stmt->execute([
            'uid' => $userId,
            'from' => $from->format('Y-m-d H:i:s'),
            'to' => $to->format('Y-m-d H:i:s'),
        ]);

        return array_map(static fn(array $row): array => [
            'status' => (string) ($row['status'] ?? ''),
            'total' => (int) ($row['total'] ?? 0),
        ], $stmt->fetchAll());
    }

    private function fetchProcedureRows(int $userId, DateTime $from, DateTime $to, PDO $db): array
    {
        $stmt = $db->prepare(
            'SELECT procedimento,
                    COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN pago = 1 AND valor_cobrado IS NOT NULL THEN valor_cobrado ELSE 0 END), 0) AS paid_revenue
             FROM appointments
             WHERE user_id = :uid
               AND data_hora BETWEEN :from AND :to
             GROUP BY procedimento
             ORDER BY total DESC, paid_revenue DESC
             LIMIT 10'
        );
        $stmt->execute([
            'uid' => $userId,
            'from' => $from->format('Y-m-d H:i:s'),
            'to' => $to->format('Y-m-d H:i:s'),
        ]);

        return array_map(static fn(array $row): array => [
            'procedimento' => (string) ($row['procedimento'] ?? 'Sem nome'),
            'total' => (int) ($row['total'] ?? 0),
            'paid_revenue' => (float) ($row['paid_revenue'] ?? 0),
        ], $stmt->fetchAll());
    }

    private function fetchDailyRows(int $userId, DateTime $from, DateTime $to, PDO $db): array
    {
        $stmt = $db->prepare(
            "SELECT DATE_FORMAT(data_hora, '%d/%m/%Y') AS day,
                    COUNT(*) AS appointments_total,
                    SUM(status IN ('confirmada','realizada')) AS confirmed_total,
                    COALESCE(SUM(CASE WHEN pago = 1 AND valor_cobrado IS NOT NULL THEN valor_cobrado ELSE 0 END), 0) AS paid_revenue
             FROM appointments
             WHERE user_id = :uid
               AND data_hora BETWEEN :from AND :to
             GROUP BY DATE(data_hora)
             ORDER BY DATE(data_hora) ASC"
        );
        $stmt->execute([
            'uid' => $userId,
            'from' => $from->format('Y-m-d H:i:s'),
            'to' => $to->format('Y-m-d H:i:s'),
        ]);

        return array_map(static fn(array $row): array => [
            'day' => (string) ($row['day'] ?? ''),
            'appointments_total' => (int) ($row['appointments_total'] ?? 0),
            'confirmed_total' => (int) ($row['confirmed_total'] ?? 0),
            'paid_revenue' => (float) ($row['paid_revenue'] ?? 0),
        ], $stmt->fetchAll());
    }
}
