<?php
require 'C:/xampp/htdocs/Aula-SQL/src/bootstrap.php';

$to = $argv[1] ?? '';
if ($to === '') {
    echo "Uso: php scripts/test_mailer.php email@destino.com" . PHP_EOL;
    exit(1);
}

$mailer = new MailerService();
if (!$mailer->isEnabled()) {
    echo "SMTP não configurado. Verifique MAIL_HOST e MAIL_FROM_ADDRESS no .env" . PHP_EOL;
    exit(1);
}

$subject = 'Teste SMTP Atendy';
$html = '<h2>Teste SMTP OK �o.</h2><p>Este e-mail confirma que o SMTP do Atendy está funcionando.</p>';
$text = "Teste SMTP OK.\nEste e-mail confirma que o SMTP do Atendy está funcionando.";

$result = $mailer->send($to, $subject, $html, $text);

if (($result['success'] ?? false) === true) {
    echo "E-mail enviado com sucesso para: {$to}" . PHP_EOL;
    exit(0);
}

$error = (string) ($result['error'] ?? 'Erro desconhecido');
echo "Falha no envio: {$error}" . PHP_EOL;
exit(1);


