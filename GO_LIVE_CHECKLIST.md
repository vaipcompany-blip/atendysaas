# Checklist Executivo de Go-Live

Documento de aprovação final para publicação em produção.

## 1. Dados da janela
- Data planejada: 2026-03-25
- Horário de início: 22:21 (BRT)
- Horário de término: 22:22 (BRT)
- Responsável técnico: Pendente preenchimento
- Responsável negócio: Pendente preenchimento

## 2. Pré-go-live (obrigatório)
- [x] Backup completo gerado e validado.
- [x] Drill de restauração executado com sucesso na semana corrente.
- [x] Migrations pendentes = 0.
- [x] Healthcheck com status `ok` ou `warn` sem falha crítica.
- [x] `scripts/launch_gate.ps1` executado com `gate_status=go`.
- [x] Termos e Política publicados com versões corretas.
- [x] Conformidade LGPD ativa no cadastro (aceite obrigatório).

## 3. Comando oficial de aprovação

```powershell
powershell -ExecutionPolicy Bypass -File scripts/launch_gate.ps1 -PhpPath "C:\xampp\php\php.exe" -MysqlPath "C:\xampp\mysql\bin\mysql.exe" -BaseUrl "http://localhost/Aula-SQL/public/index.php" -DbHost "127.0.0.1" -DbPort 3306 -DbName "atendy" -DbUser "root"
```

Critério:
- Aprovar apenas se `gate_status=go` e `failed_checks=0`.

## 4. Go/No-Go
- Resultado do gate: `gate_status=go` e `failed_checks=0`
- Decisão final: GO
- Aprovado por: Pendente assinatura formal
- Horário da decisão: 22:21 (BRT)

### Evidências anexadas
- Gate completo: lint=ok, deploy_validate=ok, restore_drill=ok, e2e=`SUMMARY: total=85 fail=0`
- Changelog: `CHANGELOG.md`
- Release notes: `RELEASE_NOTES.md`
- Comunicação: `STAKEHOLDER_ANNOUNCEMENT.md`

## 5. Plano de rollback (se necessário)
- [ ] Colocar aplicação em modo manutenção.
- [ ] Restaurar backup validado mais recente.
- [ ] Executar verificação pós-restore.
- [ ] Validar login, dashboard, agenda, WhatsApp e billing.
- [ ] Registrar incidente e causa raiz.

## 6. Verificação pós-go-live (15 minutos)
- Comando recomendado:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/post_go_live_smoke.ps1 -BaseUrl "http://localhost/Aula-SQL/public/index.php" -PhpPath "C:\xampp\php\php.exe"
```

- [ ] Login do owner funcionando.
- [ ] Cadastro de paciente funcionando.
- [ ] Criação de consulta funcionando.
- [ ] Disparo manual de automações funcionando.
- [ ] Billing acessível para owner.
- [ ] Rotas proibidas bloqueadas para team member.
- [ ] Health endpoint sem falhas críticas.

## 7. Encerramento
- [ ] Comunicado de release enviado.
- [x] Evidências anexadas (JSON do gate + prints principais).
- [ ] Próxima janela de revisão operacional agendada.


