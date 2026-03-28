# Comunicado de Lançamento - Atendy SaaS

## Template (Slack/Email)
Assunto: Atendy em produção - release aprovada

Mensagem:
Olá, time.

Concluímos a preparação de produção do Atendy e a release foi aprovada no gate final.

Status atual:
- Gate de lançamento: GO
- Regressão E2E: 85/85 testes passando
- Compliance LGPD: ativo (aceite versionado de termos/política)
- Operação: backup, restore drill e checklist pós-deploy validados

Principais ganhos desta release:
- Billing e assinatura integrados ao fluxo do produto
- Observabilidade operacional com alertas e métricas de execução
- CI automatizado com lint + E2E
- Runbook completo de deploy, rollback e recuperação

Próximos passos imediatos:
1. Executar janela de publicação conforme checklist executivo.
2. Monitorar operação por 60 minutos após o go-live.
3. Fechar ata de release com evidências do gate.

Documentos de referência:
- CHANGELOG.md
- RELEASE_NOTES.md
- GO_LIVE_CHECKLIST.md

Obrigado a todos os envolvidos.


