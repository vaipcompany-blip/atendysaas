<?php

declare(strict_types=1);

final class CalendarController
{
    // Feed público iCal sem autenticação, protegido por token secreto.
    public function feed(): void
    {
        $token = trim((string) ($_GET['token'] ?? ''));
        if ($token === '') {
            http_response_code(400);
            exit('Token obrigatório.');
        }

        $db   = Database::connection();
        $stmt = $db->prepare(
            'SELECT s.user_id, u.nome_consultorio
             FROM settings s
             INNER JOIN users u ON u.id = s.user_id
             WHERE s.calendar_token = :token
               AND u.ativo = 1
             LIMIT 1'
        );
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch();

        if (!$row) {
            http_response_code(404);
            exit('Token inválido ou expirado.');
        }

        $userId       = (int)    $row['user_id'];
        $clinicName   = (string) $row['nome_consultorio'];
        $appointments = $this->fetchUpcoming($db, $userId);

        $this->outputIcal($clinicName, $appointments);
    }

    // �"?�"? Export imediato (.ics) �?" requer login �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
    public function export(): void
    {
        $userId     = (int) (Auth::user()['id'] ?? 0);
        $clinicName = (string) (Auth::user()['nome_consultorio'] ?? 'Atendy');
        $db         = Database::connection();
        $appointments = $this->fetchUpcoming($db, $userId, 180); // próximos 180 dias
        $this->outputIcal($clinicName, $appointments, true);
    }

    // �"?�"? Gerar / revogar token �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?
    public function generateToken(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'CSRF inválido.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $userId = (int) (Auth::user()['id'] ?? 0);
        $action = trim((string) ($_POST['action'] ?? 'generate'));

        $db = Database::connection();

        if ($action === 'revoke') {
            $db->prepare('UPDATE settings SET calendar_token = NULL WHERE user_id = :uid')
               ->execute(['uid' => $userId]);
            echo json_encode(['success' => true, 'token' => null, 'message' => 'Token revogado.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Gerar novo token seguro
        $newToken = bin2hex(random_bytes(32)); // 64 chars hex

        // Verificar unicidade (colisão improvável mas garantida)
        for ($i = 0; $i < 5; $i++) {
            $check = $db->prepare('SELECT user_id FROM settings WHERE calendar_token = :t LIMIT 1');
            $check->execute(['t' => $newToken]);
            if (!$check->fetch()) {
                break;
            }
            $newToken = bin2hex(random_bytes(32));
        }

        $db->prepare('UPDATE settings SET calendar_token = :token WHERE user_id = :uid')
           ->execute(['token' => $newToken, 'uid' => $userId]);

        $feedUrl = $this->buildFeedUrl($newToken);
        echo json_encode([
            'success'   => true,
            'token'     => $newToken,
            'feed_url'  => $feedUrl,
            'webcal'    => str_replace(['http://', 'https://'], 'webcal://', $feedUrl),
            'google'    => 'https://calendar.google.com/calendar/r?cid=' . urlencode($feedUrl),
            'message'   => 'Token gerado com sucesso.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // �"?�"? Helpers privados �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?

    private function fetchUpcoming(\PDO $db, int $uid, int $days = 90): array
    {
        $stmt = $db->prepare(
            "SELECT a.id, a.data_hora, a.procedimento, a.notas, a.status,
                    a.updated_at, p.nome AS paciente_nome, p.whatsapp
             FROM appointments a
             INNER JOIN patients p ON p.id = a.patient_id
             WHERE a.user_id = :uid
               AND a.status NOT IN ('cancelada','faltou','reagendada')
               AND a.data_hora BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL :days DAY)
               AND p.deleted_at IS NULL
             ORDER BY a.data_hora ASC"
        );
        $stmt->execute(['uid' => $uid, 'days' => $days]);
        return $stmt->fetchAll() ?: [];
    }

    private function outputIcal(string $clinicName, array $appointments, bool $download = false): void
    {
        $now = gmdate('Ymd\THis\Z');
        $slug = preg_replace('/[^a-z0-9]/', '', strtolower($clinicName));
        $prodId = "-//Atendy//{$slug}//PT";

        if ($download) {
            header('Content-Type: text/calendar; charset=utf-8');
            header('Content-Disposition: attachment; filename="atendy-consultas.ics"');
        } else {
            header('Content-Type: text/calendar; charset=utf-8');
            header('Cache-Control: no-store, max-age=0');
        }

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:' . $prodId,
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:Atendy �?" ' . $this->escIcal($clinicName),
            'X-WR-TIMEZONE:America/Sao_Paulo',
            'X-WR-CALDESC:Agenda de consultas exportada pelo Atendy',
            'REFRESH-INTERVAL;VALUE=DURATION:PT1H',
        ];

        foreach ($appointments as $appt) {
            $uid    = 'atendy-appt-' . (int)$appt['id'] . '@atendy.local';
            $dtStart = $this->toIcalDate((string) $appt['data_hora']);
            // Duração padrão de 1h (poderíamos usar settings.duracao_consulta futuramente)
            $dtEnd   = $this->toIcalDate((string) $appt['data_hora'], 60);
            $updated = !empty($appt['updated_at'])
                       ? $this->toIcalDate((string)$appt['updated_at'], 0, true)
                       : $now;
            $summary = $this->escIcal((string)$appt['procedimento'])
                       . ' �?" ' . $this->escIcal((string)$appt['paciente_nome']);
            $desc    = 'Paciente: ' . $this->escIcal((string)$appt['paciente_nome'])
                       . '\\nProcedimento: ' . $this->escIcal((string)$appt['procedimento'])
                       . '\\nStatus: ' . $this->escIcal((string)$appt['status'])
                       . ($appt['notas'] ? '\\nNotas: ' . $this->escIcal((string)$appt['notas']) : '');

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $uid;
            $lines[] = 'DTSTAMP:' . $now;
            $lines[] = 'LAST-MODIFIED:' . $updated;
            $lines[] = 'DTSTART;TZID=America/Sao_Paulo:' . $dtStart;
            $lines[] = 'DTEND;TZID=America/Sao_Paulo:' . $dtEnd;
            $lines[] = 'SUMMARY:' . $summary;
            $lines[] = 'DESCRIPTION:' . $desc;
            $lines[] = 'STATUS:' . $this->mapStatus((string)$appt['status']);
            $lines[] = 'CATEGORIES:Atendy,Consulta';
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        // RFC 5545: quebrar linhas longas em 75 chars
        $output = '';
        foreach ($lines as $line) {
            $output .= $this->foldLine($line) . "\r\n";
        }

        echo $output;
        exit;
    }

    private function toIcalDate(string $datetime, int $addMinutes = 0, bool $utc = false): string
    {
        $dt = new DateTime($datetime, new DateTimeZone('America/Sao_Paulo'));
        if ($addMinutes > 0) {
            $dt->modify("+{$addMinutes} minutes");
        }
        if ($utc) {
            $dt->setTimezone(new DateTimeZone('UTC'));
            return $dt->format('Ymd\THis\Z');
        }
        return $dt->format('Ymd\THis');
    }

    private function mapStatus(string $status): string
    {
        return match ($status) {
            'confirmada' => 'CONFIRMED',
            'realizada'  => 'COMPLETED',
            default      => 'TENTATIVE',
        };
    }

    private function escIcal(string $text): string
    {
        $text = str_replace(['\\', ';', ','], ['\\\\', '\\;', '\\,'], $text);
        return preg_replace('/\r?\n/', '\\n', $text);
    }

    private function foldLine(string $line): string
    {
        // RFC 5545 §3.1 �?" max 75 octets per line, continuation with CRLF + SPACE
        if (mb_strlen($line, 'UTF-8') <= 75) {
            return $line;
        }
        $folded = '';
        $bytes  = 0;
        $chars  = preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($chars as $char) {
            $charLen = strlen($char); // byte length
            if ($bytes + $charLen > 75) {
                $folded .= "\r\n ";
                $bytes   = 1; // the leading space counts
            }
            $folded .= $char;
            $bytes  += $charLen;
        }
        return $folded;
    }

    private function buildFeedUrl(string $token): string
    {
        $base = rtrim((string) ($_SERVER['REQUEST_SCHEME'] ?? 'http'), '/') . '://'
              . (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
        return $base . $script . '?route=calendar_feed&token=' . urlencode($token);
    }
}


