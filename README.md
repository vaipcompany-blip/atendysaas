# Atendy MVP (PHP + MySQL)

MVP funcional com:
- Login do dentista
- Dashboard com KPIs básicos
- Cadastro/listagem/arquivamento de pacientes
- Criação/listagem de consultas e atualização de status
- WhatsApp simulado (envio/recebimento + histórico)
- Confirmação automática 24h (manual via tela ou CLI)
- Lembretes automáticos 12h/2h
- Follow-up automático (falta, cancelamento, inatividade 60 dias)

## 1) Pré-requisitos
- XAMPP instalado
- Apache e MySQL iniciados
- PHP via `C:\xampp\php\php.exe`

## 2) Banco de dados
Execute no PowerShell:

```powershell
C:\xampp\php\php.exe "C:\xampp\htdocs\Aula-SQL\scripts\migrate.php"
```

O runner faz:
- criação do banco `atendy` se ainda não existir
- aplicação ordenada de todos os arquivos em `tables`
- registro das migrations já aplicadas em `schema_migrations`
- reconciliação do workspace demo conforme o ambiente (`local` mantém demo opcional; produção remove ou desativa)

Se uma migration já foi aplicada com o mesmo conteúdo, ela é ignorada.
Se um arquivo já aplicado for alterado depois, o runner falha para evitar drift silencioso.

Opcional (recomendado): criar usuário exclusivo da aplicação.

```powershell
Get-Content "C:\xampp\htdocs\Aula-SQL\db_scripts\02_create_app_user.sql" | C:\xampp\mysql\bin\mysql.exe -u root atendy
```

## 3) Configuração
Arquivo `.env` já criado com padrão local:
- `APP_ENV=local`
- `APP_ENABLE_DEMO_SEED=1`
- `DB_HOST=127.0.0.1`
- `DB_PORT=3306`
- `DB_DATABASE=atendy`
- `DB_USERNAME=root`
- `DB_PASSWORD=`
- `SESSION_IDLE_TIMEOUT_MINUTES=120`
- `TEAM_INVITE_EXPIRATION_HOURS=168`
- `BILLING_TRIAL_DAYS=14`
- `BILLING_DEFAULT_PLAN=starter`
- `LEGAL_TERMS_VERSION=v1.0`
- `LEGAL_PRIVACY_VERSION=v1.0`

Se seu MySQL tiver senha, atualize `c:\xampp\htdocs\Aula-SQL\.env`.
Se criar o usuário exclusivo, use:
- `DB_USERNAME=atendy_user`
- `DB_PASSWORD=atendy123`

Opcional para backups por CLI/painel:
- `MYSQLDUMP_PATH=C:\xampp\mysql\bin\mysqldump.exe`

Opcional para health detalhado fora do ambiente local:
- `HEALTH_ENDPOINT_TOKEN=gere_um_token_longo_e_aleatorio`

Fluxo recomendado por ambiente:
- `local`: mantenha `APP_ENABLE_DEMO_SEED=1` se quiser um workspace demo pronto.
- `staging/production`: use `APP_ENV=production` e `APP_ENABLE_DEMO_SEED=0`.

Billing/assinatura:
- No cadastro da clínica, o sistema cria automaticamente uma assinatura de trial.
- A rota `Billing` permite ativar plano manualmente (MVP) e cancelar assinatura.
- Workspaces com assinatura inativa são redirecionados para `Billing` até regularizar.

Compliance LGPD:
- O cadastro exige aceite de Termos de Uso e Política de Privacidade.
- O sistema registra trilha de consentimento versionada por workspace em `legal_consents`.
- Em `Configurações`, a seção de conformidade mostra versões exigidas, último aceite e permite registrar novo aceite.

Fila/jobs operacionais:
- As automações usam lock por workspace (`GET_LOCK` no MySQL) para evitar execução duplicada em paralelo.
- Toda execução é registrada em `job_executions` com status (`success`, `failed`, `skipped`) e payload resumido.
- O `scripts/cron.php` continua com lock global por arquivo e agora também aplica lock por workspace durante o processamento.

Seed demo manual:

```powershell
C:\xampp\php\php.exe "C:\xampp\htdocs\Aula-SQL\scripts\seed_demo.php"
```

Para purgar/proteger o demo seed explicitamente:

```powershell
C:\xampp\php\php.exe "C:\xampp\htdocs\Aula-SQL\scripts\seed_demo.php" --purge
```

## 4) Como abrir no navegador
Com Apache do XAMPP ativo, abra:
- `http://localhost/Aula-SQL/public/index.php?route=login`

Se aparecer erro de conexão com banco, valide as credenciais no `.env` e reinicie o Apache.

## 4.1) Health check operacional
Health check HTTP público:

```powershell
Invoke-WebRequest -Uri "http://localhost/Aula-SQL/public/index.php?route=health" -UseBasicParsing
```

Health check via CLI:

```powershell
C:\xampp\php\php.exe "C:\xampp\htdocs\Aula-SQL\scripts\healthcheck.php"
```

O relatório verifica:
- conexão com banco
- gravabilidade de `storage/logs`
- migrations pendentes
- versão do PHP/SAPI
- configuração básica de e-mail

## 4.2) Backup operacional
Backup via CLI:

```powershell
C:\xampp\php\php.exe "C:\xampp\htdocs\Aula-SQL\scripts\backup.php"
```

Backup via painel:
- `Configurações > Backup operacional > Gerar e baixar backup`

Os arquivos ficam salvos em:
- `storage/backups`

## 4.3) Qualidade automatizada (lint + E2E)
Validação local rápida (aceita PHP em PATH ou caminho explícito):

```powershell
powershell -ExecutionPolicy Bypass -File scripts/lint.ps1
```

Validação E2E com parâmetros explícitos:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/e2e_validate.ps1 -BaseUrl "http://localhost/Aula-SQL/public/index.php" -PhpPath "C:\xampp\php\php.exe" -MysqlPath "C:\xampp\mysql\bin\mysql.exe" -DbHost "127.0.0.1" -DbPort 3306 -DbName "atendy" -DbUser "root" -DbPassword ""
```

CI automático:
- O workflow `.github/workflows/ci.yml` roda em push/PR e executa lint + E2E com MySQL 8.
- O pipeline sobe o servidor PHP embutido em `127.0.0.1:8080` e valida o fluxo completo.

## 4.4) Validação pós-deploy (runbook)
Após qualquer deploy, rode o checklist automatizado:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/deploy_validate.ps1 -PhpPath "C:\xampp\php\php.exe"
```

Esse script valida:
- migrações em modo silencioso
- healthcheck CLI
- ausência de migrations pendentes
- geração de backup operacional
- execução `cron.php --dry-run`

Se algum check falhar, o script retorna código de saída `2`.

## 4.5) Drill de restauração (disaster recovery)
Teste periódico de restauração sem impactar o banco principal:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/restore_drill.ps1 -MysqlPath "C:\xampp\mysql\bin\mysql.exe" -DbHost "127.0.0.1" -DbPort 3306 -DbUser "root"
```

Comportamento:
- seleciona o backup `.sql` mais recente de `storage/backups`
- restaura em banco temporário (`atendy_restore_drill_...`)
- executa checks de sanidade (tabelas, `users`, `schema_migrations`)
- remove o banco temporário ao final (padrão)

Para manter o banco temporário para inspeção manual:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/restore_drill.ps1 -KeepTempDatabase
```

## 4.6) Gate final de lançamento (GO/NO-GO)
Antes de publicar em produção, rode o gate completo:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/launch_gate.ps1 -PhpPath "C:\xampp\php\php.exe" -MysqlPath "C:\xampp\mysql\bin\mysql.exe" -BaseUrl "http://localhost/Aula-SQL/public/index.php" -DbHost "127.0.0.1" -DbPort 3306 -DbName "atendy" -DbUser "root"
```

O gate consolida:
- lint de PHP
- checklist pós-deploy (`deploy_validate.ps1`)
- drill de restore (`restore_drill.ps1`)
- E2E completo (`e2e_validate.ps1`)

Saída esperada:
- `gate_status = go` para liberar lançamento
- `gate_status = no-go` para bloquear e corrigir pendências

Execuções parciais (quando necessário):

```powershell
powershell -ExecutionPolicy Bypass -File scripts/launch_gate.ps1 -SkipE2E
powershell -ExecutionPolicy Bypass -File scripts/launch_gate.ps1 -SkipRestoreDrill
```

## 4.7) Artefatos executivos de release
- Changelog consolidado: `CHANGELOG.md`
- Checklist de go-live e rollback: `GO_LIVE_CHECKLIST.md`
- Release notes para negócio/técnico: `RELEASE_NOTES.md`
- Template de comunicação para stakeholders: `STAKEHOLDER_ANNOUNCEMENT.md`
- Pendências finais de encerramento: `FINAL_PENDING.md`

## 4.8) Smoke pós-go-live (15 minutos)
Após publicar em produção, rode o smoke operacional rápido:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/post_go_live_smoke.ps1 -BaseUrl "http://localhost/Aula-SQL/public/index.php" -PhpPath "C:\xampp\php\php.exe"
```

Critério:
- `status=ok` e `fail_count=0`.
- Usar esse resultado para marcar a seção 6 do `GO_LIVE_CHECKLIST.md`.

## 4.9) Fechamento de sign-off (checklist)
Para preencher automaticamente os dados finais do checklist:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/finalize_signoff.ps1 -TechnicalOwner "Nome Técnico" -BusinessOwner "Nome Negócio" -ApprovedBy "Diretoria" -AnnouncementSent -ReviewWindow "Revisão em 7 dias" -Apply
```

Sem `-Apply`, o script roda em modo preview.

## 5) Primeiro acesso
- O fluxo padrão é criar a clínica pela tela de cadastro.
- Em ambiente local, você pode manter um workspace demo opcional usando `APP_ENABLE_DEMO_SEED=1`.
- Em produção, o `scripts/migrate.php` remove ou desativa automaticamente o demo seed para não deixar credencial padrão ativa.

### Segurança de login
- O sistema aplica limite de tentativas de login por `login + IP`.
- Regra atual: até `5` falhas em `15` minutos.
- Ao exceder, novas tentativas ficam bloqueadas temporariamente.

### Encerrar sessões ativas
- Em `Configurações > Segurança da conta`, existe o botão `Encerrar sessões`.
- A ação invalida sessões antigas/dispositivos do mesmo usuário.
- A sessão atual permanece ativa para evitar logout acidental durante configuração.

## 5.1) Recuperação de senha por e-mail (SMTP)
O sistema já possui fluxo completo de recuperação de senha:
- Tela `Esqueci minha senha`
- Token com expiração de 30 minutos
- Token de uso único

Para envio real por e-mail, configure no `.env`:

```dotenv
MAIL_HOST=smtp.seuprovedor.com
MAIL_PORT=587
MAIL_USERNAME=usuario@seuprovedor.com
MAIL_PASSWORD=sua_senha_ou_app_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@seudominio.com
MAIL_FROM_NAME=Atendy
```

Observações:
- Em `APP_ENV=local`, se SMTP não estiver configurado, o sistema mostra o link de reset na tela (modo desenvolvimento).
- Em ambiente de produção, mantenha SMTP configurado para envio real e não exibir links localmente.

## 5.2) Endurecimento recomendado para produção
- Defina `APP_ENV=production`.
- Defina `APP_URL=https://SEU_DOMINIO_RAILWAY/index.php`.
- Defina `APP_ENABLE_DEMO_SEED=0`.
- Use credenciais próprias de banco, sem `root`.
- Configure `HEALTH_ENDPOINT_TOKEN` se precisar de health detalhado por HTTP.
- Rode `scripts/migrate.php` no deploy para reconciliar e remover/desativar o demo seed.
- Sirva a aplicação apenas sob HTTPS para ativar HSTS e cookies `secure`.

## 5.3) Deploy na Railway (passo a passo)

### 1. Preparar repositório
1. Suba este projeto para um repositório no GitHub.
2. Garanta que os arquivos `Dockerfile` e `.dockerignore` estejam versionados.

### 2. Criar projeto na Railway
1. Em `railway.app`, clique em `New Project`.
2. Escolha `Deploy from GitHub repo`.
3. Selecione o repositório do Atendy.

### 3. Adicionar banco MySQL
1. Dentro do projeto, clique em `New` > `Database` > `MySQL`.
2. A Railway criará as credenciais automaticamente.

### 4. Configurar variáveis do serviço web
No serviço do app, configure:

```dotenv
APP_ENV=production
APP_URL=https://SEU_DOMINIO_RAILWAY/index.php
APP_ENABLE_DEMO_SEED=0

DB_HOST=...
DB_PORT=...
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

HEALTH_ENDPOINT_TOKEN=token_longo_e_aleatorio
WHATSAPP_MODE=mock
```

Opcional (se usar e-mail real de recuperação):
- `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`.

### 5. Deploy inicial
1. A Railway fará o build pelo `Dockerfile` automaticamente.
2. Quando o deploy ficar `Healthy`, abra a URL pública.

### 6. Rodar migrations em produção
No terminal da Railway (serviço web), execute:

```bash
php scripts/migrate.php
```

### 7. Smoke test pós-deploy
1. Abra `https://SEU_DOMINIO_RAILWAY/index.php?route=login`.
2. Faça login e valide: painel, pacientes, consultas e financeiro.
3. Health check:
	- `https://SEU_DOMINIO_RAILWAY/index.php?route=health`

### 8. Mobile e PC (responsividade)
Para uso em celular e desktop:
1. Use sempre a URL HTTPS pública da Railway.
2. Não use links `localhost` no conteúdo enviado para usuários.
3. Teste no navegador do celular e no desktop após cada release.
4. Em caso de cache antigo no celular/PC, atualizar com recarga forçada.

Observação:
- O layout já possui `meta viewport` e regras responsivas para telas menores.

## 6) Etapas seguintes (roadmap histórico)
As etapas abaixo fazem parte do roadmap original e já foram implementadas neste ciclo de hardening/go-live:
1. Lembretes automáticos de 12h e 2h
2. Follow-up automático de faltas/cancelamentos
3. Exportações CSV/PDF e dashboard avançado
4. Dashboard financeiro avançado

## 6.1) Integração real WhatsApp Cloud API (Meta)
O projeto já suporta modo real com webhook e configuração por cliente (dentista).

### Como o cliente configura no painel
1. Entrar no `Atendy`.
2. Abrir `Configurações`.
3. Em `Onboarding (3 passos)`, preencher:
	- `Modo = cloud`
	- `Phone Number ID`
	- `Access Token`
	- `Verify Token`
4. Salvar.
5. Clicar em `Salvar e testar envio` com número de teste.

### Tutorial simples para enviar ao cliente final
1. Faça login no `Atendy`.
2. Clique em `Configurações` no menu superior.
3. Copie da Meta apenas 2 dados: `Phone Number ID` e `Access Token`.
4. Cole esses 2 dados nos campos da tela e clique em `Salvar configurações`.
5. Ainda na mesma tela, copie `Callback URL` e `Verify Token` e cole no webhook da Meta.
6. Na Meta, assine os eventos `messages` e `message_status`.
7. Volte ao `Atendy`, informe seu número no bloco `Teste final` e clique em `Salvar e testar envio`.
8. Se a mensagem chegar no WhatsApp, a integração está pronta.

O cliente não precisa editar `.env` nem mexer em arquivos técnicos.

### Variáveis no `.env`
Estas variáveis funcionam como fallback global/admin. O ideal para SaaS é usar `Configurações` por usuário.
- `WHATSAPP_MODE`
- `WHATSAPP_API_URL`
- `WHATSAPP_PHONE_NUMBER_ID`
- `WHATSAPP_ACCESS_TOKEN`
- `WHATSAPP_VERIFY_TOKEN`
- `WHATSAPP_DEFAULT_COUNTRY`
- `WHATSAPP_USER_ID`

### URL de webhook no Meta
- Verificação e recebimento:
	- `http://localhost/Aula-SQL/public/index.php?route=whatsapp_webhook`

O endpoint processa:
- `messages` (entrada de texto do paciente)
- `statuses` (`sent`, `delivered`, `read`, `failed`) para atualizar o status da mensagem enviada

Observação: para webhook real da Meta funcionar, a URL precisa ser pública com HTTPS.
Para ambiente local, use túnel (ex.: ngrok) e configure a URL pública equivalente.

### Teste rápido da verificação do webhook
```powershell
Invoke-WebRequest -Uri "http://localhost/Aula-SQL/public/index.php?route=whatsapp_webhook&hub.mode=subscribe&hub.verify_token=atendy_verify_token&hub.challenge=12345" -UseBasicParsing
```

Se estiver correto, a resposta deve conter `12345`.

## 7) Como testar o módulo WhatsApp (simulado)
1. Entre em `http://localhost/Aula-SQL/public/index.php?route=login`.
2. Cadastre um paciente em `Pacientes`.
3. Crie uma consulta em `Consultas` para daqui ~24h.
4. Acesse `WhatsApp` e clique em `Rodar tudo`.
5. Veja o histórico de mensagens na própria tela.
6. Em `Simular mensagem recebida`, envie `Sim` para confirmar automaticamente.

### Envio real (Cloud API)
1. Configure via tela `Configurações` (recomendado) ou `.env` (fallback).
2. Use telefone de paciente em formato nacional (11 dígitos); o sistema adiciona país (`55`) automaticamente.
3. Envie mensagem pelo módulo `WhatsApp`.

## 7.1) Exportação de pacientes (CSV e PDF)
Na tela `Pacientes`, ao lado da busca, existem os botões:
- `Exportar CSV`: baixa arquivo `.csv` com os registros filtrados.
- `Exportar PDF`: abre relatório imprimível em nova aba para `Imprimir / Salvar PDF`.

Ambos respeitam o filtro de busca atual (`nome` ou `WhatsApp`).

## 9) KPIs no Dashboard (mensagens)
O dashboard agora exibe também, no mês atual:
- Mensagens enviadas
- Mensagens recebidas
- Taxa de entrega/leitura (`delivered + read` / `outbound`)

## 10) Resposta automática inicial para novos leads
Quando um número novo envia mensagem no webhook:
- O sistema cria automaticamente um paciente com status `lead`.
- Envia menu inicial automático:
	- `1` Agendar consulta
	- `2` Tirar dúvidas
	- `3` Falar com atendente
- Respostas `1`, `2` e `3` disparam mensagens de continuação apropriadas.

### Janela dos gatilhos automáticos
- `confirmacao_24h`: consultas entre +23h e +25h.
- `lembrete_12h`: consultas entre +11h e +13h.
- `lembrete_2h`: consultas entre +1h e +3h.
- `followup_falta_d1`: consulta marcada como `faltou` há 1 dia.
- `followup_cancelamento_d1`: consulta `cancelada` nas últimas 24h.
- `followup_inatividade_60d`: paciente sem consulta há 60 dias.

## 8) Rodar automações por linha de comando
```powershell
C:\xampp\php\php.exe "C:\xampp\htdocs\Aula-SQL\scripts\run_automations.php"
```


