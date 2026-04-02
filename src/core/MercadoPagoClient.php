<?php

declare(strict_types=1);

final class MercadoPagoClient
{
    private string $accessToken;
    private string $baseUrl;

    public function __construct()
    {
        $this->accessToken = trim((string) env('MERCADOPAGO_ACCESS_TOKEN', ''));
        $this->baseUrl = rtrim((string) env('MERCADOPAGO_API_URL', 'https://api.mercadopago.com'), '/');
    }

    public function isConfigured(): bool
    {
        return $this->accessToken !== '';
    }

    public function createPreference(array $payload): array
    {
        return $this->request('POST', '/checkout/preferences', $payload);
    }

    public function getPayment(string $paymentId): array
    {
        return $this->request('GET', '/v1/payments/' . rawurlencode($paymentId));
    }

    private function request(string $method, string $path, ?array $payload = null): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Mercado Pago nao configurado.');
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('Extensao cURL nao habilitada no PHP.');
        }

        $url = $this->baseUrl . $path;
        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
            'X-Idempotency-Key: ' . bin2hex(random_bytes(16)),
        ];

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Falha ao iniciar requisicao cURL.');
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($payload !== null) {
            $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                throw new RuntimeException('Falha ao codificar payload JSON.');
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded);
        }

        $rawResponse = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if (!is_string($rawResponse)) {
            throw new RuntimeException('Falha de comunicacao com Mercado Pago: ' . $curlError);
        }

        $decoded = json_decode($rawResponse, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Resposta invalida do Mercado Pago.');
        }

        if ($statusCode >= 400) {
            AppLogger::error('Mercado Pago API error', [
                'method' => $method,
                'path' => $path,
                'status_code' => $statusCode,
                'response' => $decoded,
            ]);
            throw new RuntimeException('Erro ao comunicar com Mercado Pago.');
        }

        return $decoded;
    }
}
