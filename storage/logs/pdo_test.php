<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', '');
    echo "OK\n";
} catch (Throwable $e) {
    echo 'ERRO: ' . $e->getMessage() . "\n";
}
