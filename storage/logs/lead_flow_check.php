<?php
require __DIR__ . '/../../src/bootstrap.php';
$service = new WhatsAppService();
$userId = 1;
$patientId = $service->ensurePatientByPhone($userId, '5511988887777');
$service->receiveCloudInbound($userId, $patientId, 'Olá', 'wamid.LEAD001');
$pdo = Database::connection();
$p = $pdo->prepare('SELECT id,nome,whatsapp,status FROM patients WHERE id=:id');
$p->execute(['id' => $patientId]);
print_r($p->fetch());
$m = $pdo->prepare('SELECT direction,texto,status FROM whatsapp_messages WHERE patient_id=:id ORDER BY id DESC LIMIT 3');
$m->execute(['id' => $patientId]);
print_r($m->fetchAll());
