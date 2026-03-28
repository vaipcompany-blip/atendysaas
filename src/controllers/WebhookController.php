<?php

declare(strict_types=1);

final class WebhookController
{
    private WhatsAppService $service;

    public function __construct()
    {
        $this->service = new WhatsAppService();
    }

    public function verify(): void
    {
        $mode = (string) ($_GET['hub_mode'] ?? $_GET['hub.mode'] ?? '');
        $token = (string) ($_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '');
        $challenge = (string) ($_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '');

        if ($mode === 'subscribe' && $this->service->isWebhookVerifyTokenValid($token)) {
            http_response_code(200);
            header('Content-Type: text/plain; charset=utf-8');
            echo $challenge;
            return;
        }

        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Forbidden';
    }

    public function receive(): void
    {
        $raw = file_get_contents('php://input');
        $payload = json_decode((string) $raw, true);

        if (!is_array($payload)) {
            http_response_code(400);
            echo 'Invalid payload';
            return;
        }

        $changeValue = $payload['entry'][0]['changes'][0]['value'] ?? [];
        if (!is_array($changeValue)) {
            http_response_code(200);
            echo 'No changes';
            return;
        }

        $messages = $changeValue['messages'] ?? [];
        $statuses = $changeValue['statuses'] ?? [];
        $phoneNumberId = (string) ($changeValue['metadata']['phone_number_id'] ?? '');
        $userId = $this->service->resolveWebhookUserId($phoneNumberId);

        $processed = 0;
        if (is_array($messages)) {
            foreach ($messages as $message) {
                if (($message['type'] ?? '') !== 'text') {
                    continue;
                }

                $from       = (string) ($message['from'] ?? '');
                $text       = (string) ($message['text']['body'] ?? '');
                $externalId = (string) ($message['id'] ?? '');

                if ($from === '' || $text === '') {
                    continue;
                }

                // Idempotência: ignora mensagem já processada
                if ($externalId !== '') {
                    $exists = Database::connection()->prepare(
                        'SELECT id FROM whatsapp_messages WHERE external_message_id = :eid LIMIT 1'
                    );
                    $exists->execute(['eid' => $externalId]);
                    if ($exists->fetch()) {
                        continue;
                    }
                }

                $patientId = $this->service->findPatientIdByPhone($userId, $from);
                if ($patientId === null) {
                    $patientId = $this->service->ensurePatientByPhone($userId, $from);
                }

                $this->service->receiveCloudInbound($userId, $patientId, $text, $externalId);
                $processed++;
            }
        }

        if (is_array($statuses)) {
            foreach ($statuses as $statusItem) {
                $externalId = (string) ($statusItem['id'] ?? '');
                $status = (string) ($statusItem['status'] ?? '');

                if ($this->service->processCloudStatus($userId, $externalId, $status)) {
                    $processed++;
                }
            }
        }

        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['processed' => $processed], JSON_UNESCAPED_UNICODE);
    }
}

