<?php

declare(strict_types=1);

final class WhatsAppCloudClient
{
    public function isEnabled(array $config): bool
    {
        return ($config['mode'] ?? 'mock') === 'cloud'
            && (string) ($config['phone_number_id'] ?? '') !== ''
            && (string) ($config['access_token'] ?? '') !== '';
    }

    public function sendTextMessage(array $config, string $to, string $text): array
    {
        if (!$this->isEnabled($config)) {
            return [
                'success' => false,
                'status' => 'mock',
                'external_id' => null,
                'error' => 'Cloud API não configurada',
            ];
        }

        if (!function_exists('curl_init')) {
            return [
                'success' => false,
                'status' => 'failed',
                'external_id' => null,
                'error' => 'Extensão cURL não habilitada no PHP.',
            ];
        }

        $apiUrl = rtrim((string) ($config['api_url'] ?? 'https://graph.facebook.com/v20.0'), '/');
        $phoneNumberId = (string) ($config['phone_number_id'] ?? '');
        $accessToken = (string) ($config['access_token'] ?? '');

        $endpoint = sprintf('%s/%s/messages', $apiUrl, $phoneNumberId);

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $text,
            ],
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);

        $responseBody = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false || $curlError !== '') {
            return [
                'success' => false,
                'status' => 'failed',
                'external_id' => null,
                'error' => 'Falha de conexão CURL: ' . $curlError,
            ];
        }

        $decoded = json_decode($responseBody, true);
        if ($httpCode >= 200 && $httpCode < 300 && isset($decoded['messages'][0]['id'])) {
            return [
                'success' => true,
                'status' => 'sent',
                'external_id' => (string) $decoded['messages'][0]['id'],
                'error' => null,
            ];
        }

        $errorMessage = $decoded['error']['message'] ?? ('HTTP ' . $httpCode);
        return [
            'success' => false,
            'status' => 'failed',
            'external_id' => null,
            'error' => (string) $errorMessage,
        ];
    }
}

