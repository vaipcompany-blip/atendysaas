<?php

declare(strict_types=1);

final class HealthController
{
    public function show(): void
    {
        $report = (new HealthReport())->generate($this->shouldExposeDetailedReport());
        $status = (string) ($report['status'] ?? 'fail');

        http_response_code($status === 'fail' ? 503 : 200);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function shouldExposeDetailedReport(): bool
    {
        if ((string) env('APP_ENV', 'local') === 'local') {
            return true;
        }

        $configuredToken = trim((string) env('HEALTH_ENDPOINT_TOKEN', ''));
        $providedToken = trim((string) ($_GET['token'] ?? ''));

        return $configuredToken !== '' && $providedToken !== '' && hash_equals($configuredToken, $providedToken);
    }
}
