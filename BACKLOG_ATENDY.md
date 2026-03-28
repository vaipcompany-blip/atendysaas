# Backlog Técnico - Atendy

**Produto:** Atendy  
**Stack:** PHP + MySQL  
**Data:** 21/03/2026  
**Perfil:** Dentista (acesso único)

## 1) Estrutura de Entrega (Épicos)

- **EP-01 - Segurança e Acesso** (RF-1)
- **EP-02 - CRM de Pacientes** (RF-3)
- **EP-03 - Agenda de Consultas** (RF-4)
- **EP-04 - Integração WhatsApp Base** (RF-2)
- **EP-05 - Confirmação Automática (Core)** (RF-5)
- **EP-06 - Lembretes Automáticos** (RF-6)
- **EP-07 - Reagendamento** (RF-7)
- **EP-08 - Resposta Inicial para Leads** (RF-8)
- **EP-09 - Follow-up Automático** (RF-9)
- **EP-10 - Dashboard e Indicadores** (RF-10)
- **EP-11 - Configurações do Sistema** (RF-11)

## 2) Backlog por Épico (Histórias + Critérios + Tarefas)

## EP-01 - Segurança e Acesso

### US-001 - Login com e-mail/CPF e senha
**Como** dentista, **quero** autenticar com e-mail/CPF e senha, **para** acessar meu painel com segurança.

**Critérios de aceitação**
- Dado credenciais válidas, quando eu fizer login, então devo acessar o dashboard.
- Dado credenciais inválidas, quando eu tentar login, então devo ver mensagem de erro genérica.
- Sessão deve expirar após inatividade configurável.

**Tarefas técnicas**
- Criar tabela `users` com índice em `email` e `cpf`.
- Implementar hash de senha (`password_hash`).
- Implementar login, logout e middleware de autenticação.
- Implementar rate limit de tentativas de login.

### US-002 - Recuperação de senha por e-mail
**Critérios de aceitação**
- Link de recuperação expira em até 30 minutos.
- Token de reset deve ser de uso único.

**Tarefas técnicas**
- Criar tabela `password_resets`.
- Implementar fluxo de emissão, validação e redefinição.

---

## EP-02 - CRM de Pacientes

### US-003 - Cadastrar paciente
**Como** dentista, **quero** cadastrar pacientes, **para** organizar contatos e histórico.

**Critérios de aceitação**
- Campos obrigatórios: nome, whatsapp, cpf.
- CPF deve ser único por dentista (`user_id + cpf`).
- WhatsApp deve validar formato nacional (11 dígitos).

**Tarefas técnicas**
- Criar tabela `patients` com `deleted_at` (soft delete).
- Implementar endpoint/form de criação e validações.
- Implementar auditoria de criação.

### US-004 - Editar e arquivar paciente
**Critérios de aceitação**
- Edição deve atualizar `updated_at`.
- Arquivamento não remove dados fisicamente.

**Tarefas técnicas**
- Implementar update e soft delete.
- Implementar filtros ativos/inativos.

### US-005 - Listar e buscar pacientes
**Critérios de aceitação**
- Filtro por nome e telefone com paginação.
- Tempo de resposta alvo < 2s para operação principal.

**Tarefas técnicas**
- Implementar paginação e índices.
- Implementar exportação CSV.

---

## EP-03 - Agenda de Consultas

### US-006 - Criar consulta
**Como** dentista, **quero** agendar consultas, **para** organizar agenda clínica.

**Critérios de aceitação**
- Não permitir data/hora no passado.
- Não permitir conflito de horário para o mesmo dentista.
- Consulta deve estar ligada a um paciente.

**Tarefas técnicas**
- Criar tabela `appointments` com `user_id`, `patient_id`, `status`.
- Validar regras de negócio RN-002 e RN-003.
- Criar índice composto por `user_id` + `data_hora`.

### US-007 - Alterar status e visualizar agenda
**Critérios de aceitação**
- Status válidos: `agendada`, `confirmada`, `realizada`, `cancelada`, `faltou`, `reagendada`.
- Visualização por dia/semana/mês.

**Tarefas técnicas**
- Implementar calendário/lista.
- Implementar transições de status com validação.

---

## EP-04 - Integração WhatsApp Base

### US-008 - Enviar mensagem manual pelo painel
**Critérios de aceitação**
- Mensagem enviada deve gerar log em `whatsapp_messages`.
- Falha de envio deve registrar motivo.

**Tarefas técnicas**
- Criar serviço de integração (adapter) com API WhatsApp.
- Implementar persistência de mensagens com `direction=outbound`.

### US-009 - Receber mensagens via webhook
**Critérios de aceitação**
- Webhook autenticado por token/chave.
- Mensagem recebida associada a paciente quando possível.

**Tarefas técnicas**
- Criar endpoint de webhook.
- Persistir em `whatsapp_messages` com `direction=inbound`.
- Implementar idempotência por `message_id` externo.

---

## EP-05 - Confirmação Automática (Core)

### US-010 - Disparo automático 24h antes
**Como** sistema, **quero** enviar pedido de confirmação antes da consulta, **para** reduzir faltas.

**Critérios de aceitação**
- Consulta em T-24h dispara template de confirmação.
- Respostas "sim", "ok" e "confirmo", além de emojis positivos, marcam como `confirmada`.
- Sem resposta: manter `em_aguardo` e seguir para lembrete.

**Tarefas técnicas**
- Criar job agendado (`cron`) para varredura de consultas.
- Criar parser simples de intenção (palavras-chave + emojis).
- Registrar em `automation_logs`.

---

## EP-06 - Lembretes Automáticos

### US-011 - Cadência de lembretes 24h/12h/2h
**Critérios de aceitação**
- Dentista pode ativar/desativar janelas de envio.
- Sistema não envia lembrete duplicado para o mesmo gatilho.

**Tarefas técnicas**
- Tabela de controle de disparos (`automation_logs`).
- Regras anti-duplicidade por consulta + tipo + janela.

---

## EP-07 - Reagendamento

### US-012 - Reagendar com 3 opções de horário
**Critérios de aceitação**
- Mostrar apenas horários livres dentro do expediente.
- Limite de 2 reagendamentos por consulta.

**Tarefas técnicas**
- Serviço de sugestão de slots.
- Atualização de status para `reagendada` + novo agendamento.

---

## EP-08 - Resposta Inicial para Leads

### US-013 - Autoatendimento do primeiro contato
**Critérios de aceitação**
- Novo número recebe menu inicial automaticamente.
- Ao avançar para agendamento, sistema cria paciente básico.

**Tarefas técnicas**
- Detector de "novo contato" por `whatsapp`.
- Máquina de estado simples para fluxo conversacional.

---

## EP-09 - Follow-up Automático

### US-014 - Reengajar faltas/cancelamentos/inativos
**Critérios de aceitação**
- Disparar follow-up em cenários configurados.
- Resposta positiva oferece 3 horários disponíveis.

**Tarefas técnicas**
- Motor de regras por evento (`faltou`, `cancelada`, inatividade).
- Templates parametrizáveis por dentista.

---

## EP-10 - Dashboard e Indicadores

### US-015 - Exibir KPIs operacionais
**Critérios de aceitação**
- Mostrar: total pacientes, consultas no mês, taxa de confirmação, taxa de falta, mensagens enviadas/recebidas.
- Filtro por período (7d, 30d, 3m, 12m).

**Tarefas técnicas**
- Criar queries agregadas e endpoint de métricas.
- Implementar cache de 5 minutos.

### US-016 - Exportar relatório em PDF
**Critérios de aceitação**
- Exportação respeita período filtrado.

**Tarefas técnicas**
- Serviço de exportação PDF.

---

## EP-11 - Configurações do Sistema

### US-017 - Configurar clínica, expediente e automações
**Critérios de aceitação**
- Permitir editar perfil da clínica, horários, feriados e templates.
- Token WhatsApp não pode ser vazio quando integração ativa.

**Tarefas técnicas**
- Criar/atualizar tabela `settings`.
- Validar campos críticos e confirmar alterações sensíveis.

### US-018 - Segurança da conta
**Critérios de aceitação**
- Alteração de senha exige senha atual.
- Encerrar sessões ativas quando solicitado.

**Tarefas técnicas**
- Rotina de invalidação de sessões/tokens.

## 3) Definições de Pronto

### DoR (Definition of Ready)
- História com ator, objetivo e valor de negócio.
- Critérios de aceitação testáveis.
- Dependências técnicas mapeadas.

### DoD (Definition of Done)
- Critérios de aceitação validados.
- Testes mínimos de unidade/integração do módulo.
- Logs de erro e auditoria implementados.
- Sem vulnerabilidades óbvias de SQL Injection/XSS/CSRF.

## 4) Priorização por Fase (Roadmap)

### Fase 1 (Semanas 1-4) - MVP
- US-001, US-002, US-003, US-004, US-005, US-006, US-007, US-008, US-009

### Fase 2 (Semanas 5-8) - Core de Retenção
- US-010, US-011, US-015

### Fase 3 (Semanas 9-12) - Escala Comercial
- US-012, US-013, US-014, US-016, US-017, US-018

## 5) Dependências Técnicas

- API WhatsApp Business (oficial ou provedor homologado).
- Processo em background (`cron`) para automações.
- SMTP/API de e-mail para recuperação de senha.
- HTTPS obrigatório em produção.

## 6) Métricas de Sucesso (Produto)

- Taxa de confirmação >= 80%.
- Redução de faltas >= 40%.
- Tempo de resposta das operações principais < 2s.
- Disponibilidade mensal >= 99,5%.
- Satisfação do dentista >= 4,5/5.

## 7) Anexo - Mapeamento RF -> Épico/US

- RF-1 -> EP-01 (US-001, US-002)
- RF-2 -> EP-04 (US-008, US-009)
- RF-3 -> EP-02 (US-003, US-004, US-005)
- RF-4 -> EP-03 (US-006, US-007)
- RF-5 -> EP-05 (US-010)
- RF-6 -> EP-06 (US-011)
- RF-7 -> EP-07 (US-012)
- RF-8 -> EP-08 (US-013)
- RF-9 -> EP-09 (US-014)
- RF-10 -> EP-10 (US-015, US-016)
- RF-11 -> EP-11 (US-017, US-018)


