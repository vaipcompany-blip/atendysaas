# Changelog

Todas as mudanças relevantes deste ciclo de hardening até go-live.

## 2026-03-25

### Adicionado
- Separação clara de comportamento por ambiente (`local` vs `production`) com helpers de ambiente.
- Gestão de demo seed por serviço dedicado, com reconciliação automática após migrations.
- CLI de seed demo para criar, atualizar, proteger e purgar workspace de demonstração.
- Módulo de billing com planos, assinaturas, eventos e trilha de ativação/cancelamento.
- Inicialização automática de assinatura no cadastro da clínica.
- Gate de acesso por assinatura para proteger uso do workspace.
- Infra de jobs para automações com lock por workspace em MySQL e histórico de execução.
- Tabela `job_executions` para observabilidade operacional de jobs.
- Scripts portáveis de lint e E2E, com parametrização de binários e banco.
- CI em GitHub Actions com MySQL 8 + lint + E2E.
- Painel de observabilidade avançada em Configurações (status operacional, alertas, top erros, execuções recentes).
- Runbook operacional com validação pós-deploy e drill de restauração.
- Scripts `deploy_validate.ps1`, `restore_drill.ps1` e `launch_gate.ps1`.
- Compliance LGPD com:
  - aceite obrigatório de Termos e Política no cadastro,
  - trilha de consentimento em `legal_consents`,
  - páginas públicas de Termos e Privacidade,
  - painel de conformidade em Configurações.

### Alterado
- README ampliado com guias de operação, qualidade, recuperação e gate de lançamento.
- E2E adaptado para exigir aceite legal no registro.

### Validação
- Lint PHP completo: aprovado.
- Migrations até `16_create_legal_consents.sql`: aplicadas.
- E2E completo: `SUMMARY: total=85 fail=0`.
- Launch gate final: `gate_status=go` e `failed_checks=0`.

