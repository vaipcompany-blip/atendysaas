<?php
$currentPage = (int) ($currentPage ?? 1);
$totalPages = (int) ($totalPages ?? 1);
$notifications = $notifications ?? [];
$unreadCount = (int) ($unreadCount ?? 0);
$totalRows = (int) ($totalRows ?? 0);
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
    <div>
        <h1 style="margin:0; font-size:24px; color:#1e293b;">NI Notificações</h1>
        <p class="muted" style="margin:4px 0 0;">Total: <strong><?= $totalRows ?></strong> | Não-lidas: <strong><?= $unreadCount ?></strong></p>
    </div>
    <?php if ($unreadCount > 0): ?>
    <button type="button" onclick="markAllRead()" style="height:38px; padding:0 16px; background:#3b82f6; color:#fff; border:none; border-radius:8px; font-weight:600; cursor:pointer;">
        OK Marcar tudo como lido
    </button>
    <?php endif; ?>
</div>

<?php if (empty($notifications)): ?>
    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:40px; text-align:center;">
        <div style="font-size:48px; margin-bottom:12px;">NI</div>
        <strong style="font-size:16px;">Sem notificações</strong>
        <p class="muted" style="margin-top:8px;">Você está ao dia! Voltaremos aqui com novidades.</p>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:50px;">Status</th>
                    <th>Titulo</th>
                    <th>Mensagem</th>
                    <th style="width:140px;">Data/Hora</th>
                    <th style="width:100px;">Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($notifications as $notif): ?>
                <tr id="notif-<?= (int)$notif['id'] ?>" style="<?= $notif['is_read'] ? '' : 'background:#f0fdf4;' ?>">
                    <td style="text-align:center;">
                        <?php if (!$notif['is_read']): ?>
                            <span style="display:inline-block; width:10px; height:10px; background:#22c55e; border-radius:50%;"></span>
                        <?php else: ?>
                            <span style="display:inline-block; width:10px; height:10px; background:#cbd5e1; border-radius:50%;"></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong style="font-size:13px;">
                            <?php
                            $icons = [
                                'consulta_confirmada' => '[OK] ',
                                'consulta_reagendada' => '[RE] ',
                                'consulta_cancelada' => '[CA] ',
                                'pagamento_pendente' => '[PP] ',
                                'pagamento_recebido' => '[PR] ',
                                'lead_novo' => '[LE] ',
                                'automacao_executada' => '[AU] ',
                                'system' => '[SI] ',
                            ];
                            $type = (string)($notif['type'] ?? 'system');
                            $icon = $icons[$type] ?? '';
                            ?>
                            <?= $icon ?><?= e((string)$notif['title']) ?>
                        </strong>
                    </td>
                    <td style="color:#475569; font-size:12px;">
                        <?= e((string)$notif['message']) ?>
                    </td>
                    <td style="font-size:12px; color:#64748b;">
                        <?= e(date('d/m/Y H:i', strtotime((string)$notif['created_at']))) ?>
                    </td>
                    <td style="text-align:center;">
                        <?php if (!$notif['is_read']): ?>
                        <button type="button" class="btn-secondary" onclick="markAsRead(<?= (int)$notif['id'] ?>)" style="height:28px; padding:0 8px; font-size:11px;">
                            Marcar lido
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginação -->
    <?php if ($totalPages > 1): ?>
    <div style="display:flex; justify-content:center; gap:8px; margin-top:20px;">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <?php if ($p === $currentPage): ?>
                <button type="button" disabled style="width:36px; height:36px; border:1px solid #cbd5e1; background:#3b82f6; color:#fff; border-radius:8px; font-weight:600; cursor:not-allowed;">
                    <?= $p ?>
                </button>
            <?php else: ?>
                <a href="<?= e(base_url('route=notifications&page=' . $p)) ?>" style="width:36px; height:36px; display:inline-flex; align-items:center; justify-content:center; border:1px solid #cbd5e1; background:#fff; color:#1e293b; border-radius:8px; text-decoration:none; font-weight:600; cursor:pointer;">
                    <?= $p ?>
                </a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>

<script>
var notifUrl = '<?= e(base_url('route=notifications')) ?>';

function markAsRead(id) {
    var row = document.getElementById('notif-' + id);
    fetch(notifUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=mark_read&notification_id=' + id
    })
    .then(r => r.json())
    .then(d => {
        if (d.success && row) {
            row.style.background = '';
            var btn = row.querySelector('button');
            if (btn) btn.style.display = 'none';
            // Recarregar badge do header
            if (window.updateBadgeInHeader) window.updateBadgeInHeader();
        }
    });
}

function markAllRead() {
    if (!confirm('Marcar todas as notificações como lidas?')) return;
    fetch(notifUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=mark_all_read'
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            window.location.reload();
        }
    });
}
</script>

