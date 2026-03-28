<?php
// src/views/errors/404.php
// Renderizado pelo roteador quando a rota não existe.
// Não usa View::render() para evitar dependência de variáveis injetadas.
http_response_code(404);
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Página não encontrada · Atendy</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap');
        *{box-sizing:border-box;margin:0;padding:0}
        body{min-height:100vh;display:flex;align-items:center;justify-content:center;
             background:#f4f7fb;font-family:'Plus Jakarta Sans',Inter,Segoe UI,sans-serif;
             color:#0f172a;padding:20px}
        .wrap{max-width:480px;width:100%;text-align:center}
        .badge{width:96px;height:96px;border-radius:28px;
               background:linear-gradient(135deg,#2563eb,#60a5fa);
               display:inline-flex;align-items:center;justify-content:center;
               font-size:44px;margin-bottom:22px;
               box-shadow:0 16px 40px rgba(37,99,235,.28)}
        h1{font-size:80px;font-weight:800;color:#2563eb;line-height:1;letter-spacing:-.04em;margin-bottom:6px}
        h2{font-size:22px;font-weight:700;margin-bottom:10px}
        p{color:#64748b;font-size:15px;line-height:1.6;margin-bottom:28px}
        .actions{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
        .btn{display:inline-flex;align-items:center;gap:7px;height:44px;padding:0 20px;
             border-radius:11px;font-weight:600;font-size:14px;text-decoration:none;
             transition:.18s ease;font-family:inherit}
        .btn-primary{background:#2563eb;color:#fff;border:none}
        .btn-primary:hover{background:#1d4ed8}
        .btn-ghost{background:#fff;color:#334155;border:1px solid #cbd5e1}
        .btn-ghost:hover{background:#f1f5f9}
    </style>
</head>
<body>
<div class="wrap">
    <div class="badge">AT</div>
    <h1>404</h1>
    <h2>Página não encontrada</h2>
    <p>A rota que você tentou acessar não existe ou foi movida.<br>
       Verifique o endereço ou use o menu para navegar.</p>
    <div class="actions">
        <a href="<?= e(base_url('route=dashboard')) ?>" class="btn btn-primary">
            Ir para o Painel
        </a>
        <a href="javascript:history.back()" class="btn btn-ghost">
            Voltar
        </a>
    </div>
</div>
</body>
</html>


