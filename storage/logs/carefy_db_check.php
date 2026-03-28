<?php
require __DIR__ . '/../../src/bootstrap.php';
$pdo = Database::connection();
$stmt = $pdo->query('SELECT COUNT(*) AS total FROM users');
$row = $stmt->fetch();
echo 'USERS=' . ($row['total'] ?? 0) . PHP_EOL;
