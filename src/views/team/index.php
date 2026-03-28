<?php
$members = $members ?? [];
$message = $message ?? null;
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
    <h1 style="margin:0; font-size:24px; color:#1e293b;">Equipe</h1>
    <button type="button" onclick="document.getElementById('inviteForm').style.display=document.getElementById('inviteForm').style.display==='none'?'block':'none'" style="height:38px; padding:0 16px; background:#3b82f6; color:#fff; border:none; border-radius:8px; font-weight:600; cursor:pointer;">
        + Convidar Membro
    </button>
</div>

<?php if ($message): ?>
<div style="background:#f0fdf4; border:1px solid #86efac; border-radius:8px; padding:12px 16px; margin-bottom:20px; color:#15803d;">
    <?= e((string)$message) ?>
</div>
<?php endif; ?>

<!-- Formulário de Convite -->
<div id="inviteForm" style="display:none; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:20px; margin-bottom:20px;">
    <h3 style="margin:0 0 16px; font-size:16px;">Convidar novo membro</h3>
    <form method="POST" style="display:grid; gap:12px;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="invite">
        
        <div>
            <label style="display:block; font-weight:600; margin-bottom:4px;">E-mail</label>
            <input type="email" name="email" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; box-sizing:border-box;">
        </div>
        
        <div>
            <label style="display:block; font-weight:600; margin-bottom:4px;">Nome Completo</label>
            <input type="text" name="nome_completo" required style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; box-sizing:border-box;">
        </div>
        
        <div>
            <label style="display:block; font-weight:600; margin-bottom:4px;">Função</label>
            <select name="role" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; box-sizing:border-box;">
                <option value="staff">Assistente (acesso básico)</option>
                <option value="admin">Administrador (acesso completo)</option>
                <option value="owner">Proprietário (dono da clínica)</option>
            </select>
        </div>
        
        <div style="display:flex; gap:8px;">
            <button type="submit" style="height:36px; padding:0 16px; background:#3b82f6; color:#fff; border:none; border-radius:6px; font-weight:600; cursor:pointer;">Enviar Convite</button>
            <button type="button" onclick="document.getElementById('inviteForm').style.display='none'" style="height:36px; padding:0 16px; background:#e2e8f0; color:#1e293b; border:none; border-radius:6px; font-weight:600; cursor:pointer;">Cancelar</button>
        </div>
    </form>
</div>

<!-- Lista de Membros -->
<?php if (empty($members)): ?>
    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:40px; text-align:center;">
        <strong style="font-size:16px;">Nenhum membro convidado</strong>
        <p class="muted" style="margin-top:8px;">Comece convidando membros da sua equipe para colaborar.</p>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th style="width:120px;">Função</th>
                    <th style="width:100px;">Status</th>
                    <th style="width:130px;">Último Acesso</th>
                    <th style="width:100px;">Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members as $member): ?>
                <tr id="member-<?= (int)$member['id'] ?>">
                    <td><strong><?= e((string)$member['nome_completo']) ?></strong></td>
                    <td style="color:#64748b;"><?= e((string)$member['email']) ?></td>
                    <td>
                        <select onchange="updateRole(<?= (int)$member['id'] ?>, this.value)" style="padding:6px 8px; border:1px solid #cbd5e1; border-radius:4px; font-size:12px; background:#fff; cursor:pointer;">
                            <option value="staff" <?= $member['role'] === 'staff' ? 'selected' : '' ?>>Assistente</option>
                            <option value="admin" <?= $member['role'] === 'admin' ? 'selected' : '' ?>>Administrador</option>
                            <option value="owner" <?= $member['role'] === 'owner' ? 'selected' : '' ?>>Proprietário</option>
                        </select>
                    </td>
                    <td>
                        <?php if ($member['status'] === 'pending'): ?>
                            <span style="background:#fef08a; color:#854d0e; padding:4px 8px; border-radius:4px; font-size:12px; font-weight:600;">Pendente</span>
                        <?php elseif ($member['status'] === 'active'): ?>
                            <span style="background:#dcfce7; color:#166534; padding:4px 8px; border-radius:4px; font-size:12px; font-weight:600;">Ativo</span>
                        <?php else: ?>
                            <span style="background:#fee2e2; color:#991b1b; padding:4px 8px; border-radius:4px; font-size:12px; font-weight:600;">Inativo</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:12px; color:#64748b;">
                        <?= $member['last_login'] ? e(date('d/m/Y H:i', strtotime((string)$member['last_login']))) : 'Nunca' ?>
                    </td>
                    <td style="text-align:center;">
                        <button type="button" class="btn-danger" onclick="removeMember(<?= (int)$member['id'] ?>, '<?= e((string)$member['nome_completo']) ?>')" style="height:28px; padding:0 8px; font-size:11px; background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; border-radius:4px; cursor:pointer; font-weight:600;">Remover</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<script>
var teamUrl = '<?= e(base_url('route=team')) ?>';
var csrfToken = '<?= e((string)($csrf_token ?? '')) ?>';

function updateRole(memberId, newRole) {
    fetch(teamUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=update_role&member_id=' + memberId + '&role=' + newRole + '&csrf_token=' + encodeURIComponent(csrfToken)
    })
    .then(r => r.json())
    .then(d => {
        if (!d.success) {
            alert('Erro: ' + d.message);
            location.reload();
        }
    })
    .catch(e => {
        alert('Erro ao atualizar: ' + e.message);
        location.reload();
    });
}

function removeMember(memberId, memberName) {
    if (!confirm('Tem certeza que deseja remover ' + memberName + ' da equipe?')) return;
    
    fetch(teamUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=remove&member_id=' + memberId + '&csrf_token=' + encodeURIComponent(csrfToken)
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            var row = document.getElementById('member-' + memberId);
            if (row) row.style.opacity = '0.5';
            setTimeout(() => location.reload(), 300);
        } else {
            alert('Erro: ' + d.message);
        }
    })
    .catch(e => {
        alert('Erro ao remover: ' + e.message);
    });
}
</script>

