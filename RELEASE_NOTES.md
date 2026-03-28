# Release Notes - Atendy SaaS (Go-Live)

## Release
- Data: 2026-03-25
- Status: Aprovado para produção (GO)
- Escopo: Hardening completo de produto, operações, segurança e compliance.

## Resumo Executivo
Esta release conclui a preparação de produção do Atendy com foco em confiabilidade operacional, segurança de acesso, governança de dados e capacidade de recuperação.

## Principais Entregas

### 1) Ambiente e segurança operacional
- Separação de comportamento por ambiente (`local` x `production`).
- Controle de demo seed com reconciliação automática por migration.
- Endurecimento de sessão e headers de segurança HTTP.

### 2) Billing e assinatura
- Planos, assinaturas e eventos de cobrança versionados em banco.
- Trial automático no cadastro da clínica.
- Gate de acesso por status da assinatura.
- Tela de billing com ativação/cancelamento (MVP).

### 3) Jobs e automações
- Lock por workspace para evitar execução duplicada.
- Histórico de execução em `job_executions`.
- Integração no cron, painel e script manual.

### 4) Qualidade e CI
- Lint e E2E parametrizados para execução local e CI.
- Pipeline GitHub Actions com MySQL 8 e validação completa.

### 5) Observabilidade
- Painel de observabilidade operacional em Configurações.
- KPIs de jobs/erros/automação e alertas de status.
- Healthcheck ampliado com visão de automações.

### 6) Deploy, backup e recuperação
- Checklist pós-deploy automatizado.
- Drill de restauração em banco temporário.
- Gate final GO/NO-GO para lançamento.

### 7) Compliance LGPD
- Aceite obrigatório de Termos e Política no cadastro.
- Trilhas de consentimento versionadas em `legal_consents`.
- Páginas públicas de Termos e Privacidade.
- Status de conformidade no painel de Configurações.

## Evidências de Validação
- Migrations aplicadas até `16_create_legal_consents.sql`.
- Regressão E2E: `SUMMARY: total=85 fail=0`.
- Launch gate: `gate_status=go`.

## Risco Residual
- Baixo. Risco residual principal ligado a operação externa (infra, SMTP e provedor WhatsApp), mitigado por healthcheck, backup e runbook de rollback.

## Plano de Rollout
1. Executar `scripts/launch_gate.ps1` sem flags de skip.
2. Publicar release em janela aprovada.
3. Validar smoke pós-go-live (login, agenda, WhatsApp, billing, relatórios).
4. Monitorar painel de observabilidade por 60 minutos.

## Plano de Rollback
1. Ativar manutenção.
2. Restaurar último backup validado.
3. Confirmar saúde via healthcheck e smoke essencial.
4. Registrar incidente e causa raiz.


