<?php
$doc = $doc ?? 'terms';
$versions = $versions ?? ['terms_version' => 'v1.0', 'privacy_version' => 'v1.0'];
$links = $links ?? ['terms_url' => base_url('route=legal&doc=terms'), 'privacy_url' => base_url('route=legal&doc=privacy')];
$isTerms = $doc === 'terms';
?>

<div class="card" style="max-width:900px; margin:30px auto;">
    <h1 class="page-title" style="font-size:30px;"><?= $isTerms ? 'Termos de Uso' : 'Política de Privacidade (LGPD)' ?></h1>
    <p class="muted">
        Versão <?= e($isTerms ? (string) ($versions['terms_version'] ?? 'v1.0') : (string) ($versions['privacy_version'] ?? 'v1.0')) ?>
        · Atualizado em <?= e(date('d/m/Y')) ?>
    </p>

    <div class="row" style="margin-bottom:14px;">
        <a href="<?= e((string) ($links['terms_url'] ?? base_url('route=legal&doc=terms'))) ?>" class="btn-secondary" style="text-decoration:none; display:inline-flex; align-items:center;">Termos</a>
        <a href="<?= e((string) ($links['privacy_url'] ?? base_url('route=legal&doc=privacy'))) ?>" class="btn-secondary" style="text-decoration:none; display:inline-flex; align-items:center;">Privacidade</a>
    </div>

    <?php if ($isTerms): ?>
        <h3 class="card-title">1) Objeto</h3>
        <p class="muted">O Atendy é uma plataforma SaaS para gestão clínica e automação de relacionamento com pacientes.</p>

        <h3 class="card-title">2) Responsabilidades</h3>
        <p class="muted">O cliente é responsável pela veracidade dos dados inseridos e pelo uso legítimo dos canais de comunicação com pacientes.</p>

        <h3 class="card-title">3) Segurança e acesso</h3>
        <p class="muted">A conta é pessoal e o cliente deve manter credenciais seguras, não compartilhando acesso com terceiros não autorizados.</p>

        <h3 class="card-title">4) Disponibilidade</h3>
        <p class="muted">O serviço busca alta disponibilidade, podendo haver indisponibilidades para manutenção planejada ou contingência.</p>

        <h3 class="card-title">5) Encerramento</h3>
        <p class="muted">A conta pode ser encerrada pelo cliente a qualquer momento, observando obrigações legais de retenção mínima de dados.</p>
    <?php else: ?>
        <h3 class="card-title">1) Controlador e finalidade</h3>
        <p class="muted">Os dados pessoais tratados no Atendy são utilizados para gestão clínica, agenda, comunicação e execução contratual.</p>

        <h3 class="card-title">2) Bases legais (LGPD)</h3>
        <p class="muted">Tratamento baseado em execução de contrato, legítimo interesse e, quando aplicável, consentimento do titular.</p>

        <h3 class="card-title">3) Direitos do titular</h3>
        <p class="muted">O titular pode solicitar confirmação de tratamento, acesso, correção, anonimização, portabilidade e eliminação conforme LGPD.</p>

        <h3 class="card-title">4) Segurança e retenção</h3>
        <p class="muted">Aplicamos medidas técnicas e administrativas para proteção de dados, com retenção mínima necessária para obrigações legais.</p>

        <h3 class="card-title">5) Contato</h3>
        <p class="muted">Solicitações de privacidade podem ser encaminhadas ao canal oficial da clínica responsável pelo tratamento.</p>
    <?php endif; ?>
</div>

