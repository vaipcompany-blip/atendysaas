<?php

declare(strict_types=1);

final class MailerService
{
    public function isEnabled(): bool
    {
        $host = trim((string) env('MAIL_HOST', ''));
        $from = trim((string) env('MAIL_FROM_ADDRESS', ''));
        return $host !== '' && $from !== '';
    }

    public function send(string $toEmail, string $subject, string $htmlBody, string $textBody = ''): array
    {
        $host = trim((string) env('MAIL_HOST', ''));
        $port = (int) env('MAIL_PORT', '587');
        $user = trim((string) env('MAIL_USERNAME', ''));
        $pass = trim((string) env('MAIL_PASSWORD', ''));
        $encryption = mb_strtolower(trim((string) env('MAIL_ENCRYPTION', 'tls')), 'UTF-8');
        $fromAddress = trim((string) env('MAIL_FROM_ADDRESS', ''));
        $fromName = trim((string) env('MAIL_FROM_NAME', env('APP_NAME', 'Atendy') ?? 'Atendy'));

        if ($host === '' || $fromAddress === '') {
            return ['success' => false, 'error' => 'SMTP não configurado'];
        }

        if ($textBody === '') {
            $textBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody)));
        }

        $message = $this->buildMimeMessage($fromAddress, $fromName, $toEmail, $subject, $htmlBody, $textBody);
        $transport = $encryption === 'ssl' ? 'ssl://' : '';

        $socket = @stream_socket_client($transport . $host . ':' . $port, $errno, $errstr, 20);
        if (!$socket) {
            return ['success' => false, 'error' => 'Falha na conexão SMTP: ' . $errstr . ' (' . $errno . ')'];
        }

        stream_set_timeout($socket, 20);

        try {
            $this->expect($socket, [220]);
            $this->command($socket, 'EHLO localhost', [250]);

            if ($encryption === 'tls') {
                $this->command($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('Falha ao iniciar TLS no SMTP.');
                }
                $this->command($socket, 'EHLO localhost', [250]);
            }

            if ($user !== '') {
                $this->command($socket, 'AUTH LOGIN', [334]);
                $this->command($socket, base64_encode($user), [334]);
                $this->command($socket, base64_encode($pass), [235]);
            }

            $this->command($socket, 'MAIL FROM:<' . $fromAddress . '>', [250]);
            $this->command($socket, 'RCPT TO:<' . $toEmail . '>', [250, 251]);
            $this->command($socket, 'DATA', [354]);

            fwrite($socket, $message . "\r\n.\r\n");
            $this->expect($socket, [250]);

            $this->command($socket, 'QUIT', [221]);
            fclose($socket);

            return ['success' => true];
        } catch (Throwable $e) {
            fclose($socket);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function buildMimeMessage(
        string $fromAddress,
        string $fromName,
        string $toEmail,
        string $subject,
        string $htmlBody,
        string $textBody
    ): string {
        $boundary = 'b_' . bin2hex(random_bytes(12));
        $safeSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $safeFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';

        $headers = [
            'From: ' . $safeFromName . ' <' . $fromAddress . '>',
            'To: <' . $toEmail . '>',
            'Subject: ' . $safeSubject,
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];

        $body = [];
        $body[] = '--' . $boundary;
        $body[] = 'Content-Type: text/plain; charset=UTF-8';
        $body[] = 'Content-Transfer-Encoding: 8bit';
        $body[] = '';
        $body[] = $textBody;
        $body[] = '';
        $body[] = '--' . $boundary;
        $body[] = 'Content-Type: text/html; charset=UTF-8';
        $body[] = 'Content-Transfer-Encoding: 8bit';
        $body[] = '';
        $body[] = $htmlBody;
        $body[] = '';
        $body[] = '--' . $boundary . '--';

        return implode("\r\n", array_merge($headers, [''], $body));
    }

    private function command($socket, string $command, array $okCodes): void
    {
        fwrite($socket, $command . "\r\n");
        $this->expect($socket, $okCodes);
    }

    private function expect($socket, array $okCodes): void
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }

        if ($response === '') {
            throw new RuntimeException('Servidor SMTP sem resposta.');
        }

        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $okCodes, true)) {
            throw new RuntimeException('SMTP erro [' . $code . ']: ' . trim($response));
        }
    }
}


