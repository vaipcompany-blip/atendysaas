<?php

declare(strict_types=1);

final class FinanceiroController
{
    public function index(): void
    {
        $userId = (int) (Auth::user()['id'] ?? 0);
        $db = Database::connection();

        $periodos = ['mes_atual', 'mes_anterior', 'trimestre', 'semestre', 'ano', 'personalizado'];
        $periodoRaw = trim((string) ($_GET['periodo'] ?? 'mes_atual'));
        if (!in_array($periodoRaw, $periodos, true)) {
            $periodoRaw = 'mes_atual';
        }

        [$dataInicio, $dataFim, $periodoLabel] = $this->resolvePeriodo($periodoRaw);

        if ($periodoRaw === 'personalizado') {
            $dataInicio = trim((string) ($_GET['data_inicio'] ?? $dataInicio));
            $dataFim = trim((string) ($_GET['data_fim'] ?? $dataFim));
            $periodoLabel = 'Personalizado';
        }

        $formaFiltro = trim((string) ($_GET['forma_pagamento'] ?? ''));
        $statusFiltro = trim((string) ($_GET['status_pgto'] ?? ''));

        $kpis = $this->buildKpis($db, $userId, $dataInicio, $dataFim, $formaFiltro, $statusFiltro);
        $receitaPorForma = $this->buildReceitaPorForma($db, $userId, $dataInicio, $dataFim);
        $evolucaoMensal = $this->buildEvolucaoMensal($db, $userId);
        $receitaPorProcedimento = $this->buildReceitaPorProcedimento($db, $userId, $dataInicio, $dataFim);
        $pendentes = $this->buildPendentes($db, $userId);

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 30;
        $lancamentos = $this->buildLancamentos($db, $userId, $dataInicio, $dataFim, $formaFiltro, $statusFiltro, $page, $perPage);
        $totalRows = $this->countLancamentos($db, $userId, $dataInicio, $dataFim, $formaFiltro, $statusFiltro);
        $totalPages = max(1, (int) ceil($totalRows / $perPage));

        $formasDisponiveis = $this->buildFormasDisponiveis($db, $userId);

        View::render('financeiro/index', [
            'kpis' => $kpis,
            'receitaPorForma' => $receitaPorForma,
            'evolucaoMensal' => $evolucaoMensal,
            'receitaPorProcedimento' => $receitaPorProcedimento,
            'pendentes' => $pendentes,
            'lancamentos' => $lancamentos,
            'totalRows' => $totalRows,
            'totalPages' => $totalPages,
            'page' => $page,
            'perPage' => $perPage,
            'periodo' => $periodoRaw,
            'periodoLabel' => $periodoLabel,
            'dataInicio' => $dataInicio,
            'dataFim' => $dataFim,
            'formaFiltro' => $formaFiltro,
            'statusFiltro' => $statusFiltro,
            'formasDisponiveis' => $formasDisponiveis,
        ]);
    }

    public function updatePagamento(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token CSRF inválido.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $userId = (int) (Auth::user()['id'] ?? 0);
        $apptId = (int) ($_POST['appointment_id'] ?? 0);

        if ($apptId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID inválido.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $valor = isset($_POST['valor_cobrado']) && $_POST['valor_cobrado'] !== ''
            ? round((float) str_replace(',', '.', (string) $_POST['valor_cobrado']), 2)
            : null;
        $forma = trim((string) ($_POST['forma_pagamento'] ?? ''));
        $pago = (int) ($_POST['pago'] ?? 0) === 1 ? 1 : 0;
        $dataPagamento = $pago && trim((string) ($_POST['data_pagamento'] ?? '')) !== ''
            ? trim((string) $_POST['data_pagamento'])
            : null;

        $formasValidas = ['dinheiro', 'pix', 'cartao_credito', 'cartao_debito', 'convenio', 'outro'];
        if ($forma !== '' && !in_array($forma, $formasValidas, true)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Forma de pagamento inválida.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $db = Database::connection();
        $stmt = $db->prepare(
            'UPDATE appointments
             SET valor_cobrado = :valor,
                 forma_pagamento = :forma,
                 pago = :pago,
                 data_pagamento = :data_pgto,
                 updated_at = NOW()
             WHERE id = :id AND user_id = :uid'
        );
        $stmt->execute([
            'valor' => $valor,
            'forma' => $forma !== '' ? $forma : null,
            'pago' => $pago,
            'data_pgto' => $dataPagamento,
            'id' => $apptId,
            'uid' => $userId,
        ]);

        if ($pago === 1) {
            NotificationController::create($userId, 'pagamento_recebido', 'Pagamento Recebido', 'Pagamento recebido com sucesso!', 'appointment', $apptId);
        } elseif ($pago === 0 && $valor !== null) {
            NotificationController::create($userId, 'pagamento_pendente', 'Pagamento Pendente', 'Pagamento pendente de confirmação.', 'appointment', $apptId);
        }

        if ($stmt->rowCount() > 0) {
            audit_log_event($userId, 'payment_updated', 'Pagamento da consulta #' . $apptId . ' atualizado.');
        }

        echo json_encode(['success' => true, 'message' => 'Pagamento atualizado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function resolvePeriodo(string $periodo): array
    {
        $hoje = new DateTimeImmutable();
        switch ($periodo) {
            case 'mes_anterior':
                $ini = $hoje->modify('first day of last month')->format('Y-m-d');
                $fim = $hoje->modify('last day of last month')->format('Y-m-d');
                $label = $hoje->modify('first day of last month')->format('F/Y');
                break;
            case 'trimestre':
                $ini = $hoje->modify('-3 months')->format('Y-m-d');
                $fim = $hoje->format('Y-m-d');
                $label = 'Últimos 3 meses';
                break;
            case 'semestre':
                $ini = $hoje->modify('-6 months')->format('Y-m-d');
                $fim = $hoje->format('Y-m-d');
                $label = 'Últimos 6 meses';
                break;
            case 'ano':
                $ini = (new DateTimeImmutable('first day of january this year'))->format('Y-m-d');
                $fim = $hoje->format('Y-m-d');
                $label = 'Ano ' . $hoje->format('Y');
                break;
            case 'personalizado':
                $ini = $hoje->modify('first day of this month')->format('Y-m-d');
                $fim = $hoje->format('Y-m-d');
                $label = 'Personalizado';
                break;
            default:
                $ini = $hoje->modify('first day of this month')->format('Y-m-d');
                $fim = $hoje->modify('last day of this month')->format('Y-m-d');
                $label = $hoje->format('F/Y');
                break;
        }

        return [$ini, $fim, $label];
    }

    private function buildWhereExtra(PDO $db, string $formaFiltro, string $statusFiltro): array
    {
        $clauses = [];
        $params = [];
        if ($formaFiltro !== '') {
            $clauses[] = 'a.forma_pagamento = :forma_filtro';
            $params['forma_filtro'] = $formaFiltro;
        }
        if ($statusFiltro === 'pago') {
            $clauses[] = 'a.pago = 1';
        } elseif ($statusFiltro === 'pendente') {
            $clauses[] = 'a.pago = 0 AND a.valor_cobrado IS NOT NULL';
        }

        return [$clauses, $params];
    }

    private function buildKpis(PDO $db, int $uid, string $ini, string $fim, string $forma, string $status): array
    {
        [$extraClauses, $extraParams] = $this->buildWhereExtra($db, $forma, $status);
        $extraSql = $extraClauses ? ('AND ' . implode(' AND ', $extraClauses)) : '';

        $stmt = $db->prepare(
            "SELECT
                COUNT(*) AS total_consultas,
                COUNT(CASE WHEN a.pago = 1 THEN 1 END) AS total_pagas,
                COUNT(CASE WHEN a.pago = 0 AND a.valor_cobrado IS NOT NULL THEN 1 END) AS total_pendentes,
                COALESCE(SUM(CASE WHEN a.pago = 1 THEN a.valor_cobrado END), 0) AS receita_paga,
                COALESCE(SUM(CASE WHEN a.pago = 0 AND a.valor_cobrado IS NOT NULL THEN a.valor_cobrado END), 0) AS receita_pendente,
                COALESCE(AVG(CASE WHEN a.pago = 1 AND a.valor_cobrado IS NOT NULL THEN a.valor_cobrado END), 0) AS ticket_medio
             FROM appointments a
             WHERE a.user_id = :uid
               AND a.status IN ('realizada','confirmada')
               AND DATE(a.data_hora) BETWEEN :ini AND :fim
               $extraSql"
        );
        $stmt->execute(array_merge(['uid' => $uid, 'ini' => $ini, 'fim' => $fim], $extraParams));
        $row = $stmt->fetch();

        $totalConsultas = (int) ($row['total_consultas'] ?? 0);
        $totalPagas = (int) ($row['total_pagas'] ?? 0);
        $totalPendentes = (int) ($row['total_pendentes'] ?? 0);
        $receitaPaga = (float) ($row['receita_paga'] ?? 0);
        $receitaPendente = (float) ($row['receita_pendente'] ?? 0);
        $ticketMedio = (float) ($row['ticket_medio'] ?? 0);
        $taxaRecebimento = $totalConsultas > 0 ? round(($totalPagas / $totalConsultas) * 100, 1) : 0.0;

        return compact(
            'totalConsultas',
            'totalPagas',
            'totalPendentes',
            'receitaPaga',
            'receitaPendente',
            'ticketMedio',
            'taxaRecebimento'
        );
    }

    private function buildReceitaPorForma(PDO $db, int $uid, string $ini, string $fim): array
    {
        $labels = [
            'dinheiro' => 'Dinheiro',
            'pix' => 'PIX',
            'cartao_credito' => 'Cartão Crédito',
            'cartao_debito' => 'Cartão Débito',
            'convenio' => 'Convênio',
            'outro' => 'Outro',
        ];

        $stmt = $db->prepare(
            "SELECT forma_pagamento, COALESCE(SUM(valor_cobrado), 0) AS total
             FROM appointments
             WHERE user_id = :uid
               AND pago = 1
               AND forma_pagamento IS NOT NULL
               AND status IN ('realizada','confirmada')
               AND DATE(data_hora) BETWEEN :ini AND :fim
             GROUP BY forma_pagamento
             ORDER BY total DESC"
        );
        $stmt->execute(['uid' => $uid, 'ini' => $ini, 'fim' => $fim]);
        $rows = $stmt->fetchAll();

        $result = [];
        foreach ($rows as $r) {
            $key = (string) $r['forma_pagamento'];
            $result[] = [
                'forma' => $key,
                'label' => $labels[$key] ?? ucfirst($key),
                'total' => (float) $r['total'],
            ];
        }

        return $result;
    }

    private function buildEvolucaoMensal(PDO $db, int $uid): array
    {
        $stmt = $db->prepare(
            "SELECT DATE_FORMAT(data_hora, '%Y-%m') AS mes,
                    COALESCE(SUM(CASE WHEN pago = 1 THEN valor_cobrado END), 0) AS receita,
                    COALESCE(SUM(CASE WHEN pago = 0 AND valor_cobrado IS NOT NULL THEN valor_cobrado END), 0) AS pendente,
                    COUNT(*) AS consultas
             FROM appointments
             WHERE user_id = :uid
               AND status IN ('realizada','confirmada')
               AND valor_cobrado IS NOT NULL
               AND data_hora >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 11 MONTH), '%Y-%m-01')
             GROUP BY mes
             ORDER BY mes ASC"
        );
        $stmt->execute(['uid' => $uid]);

        return $stmt->fetchAll() ?: [];
    }

    private function buildReceitaPorProcedimento(PDO $db, int $uid, string $ini, string $fim): array
    {
        $stmt = $db->prepare(
            "SELECT procedimento,
                    COUNT(*) AS qtd,
                    COALESCE(SUM(CASE WHEN pago=1 THEN valor_cobrado END), 0) AS receita,
                    COALESCE(AVG(CASE WHEN pago=1 AND valor_cobrado IS NOT NULL THEN valor_cobrado END), 0) AS ticket
             FROM appointments
             WHERE user_id = :uid
               AND status IN ('realizada','confirmada')
               AND DATE(data_hora) BETWEEN :ini AND :fim
             GROUP BY procedimento
             ORDER BY receita DESC
             LIMIT 10"
        );
        $stmt->execute(['uid' => $uid, 'ini' => $ini, 'fim' => $fim]);

        return $stmt->fetchAll() ?: [];
    }

    private function buildPendentes(PDO $db, int $uid): array
    {
        $stmt = $db->prepare(
            "SELECT a.id, a.data_hora, a.procedimento, a.valor_cobrado, a.forma_pagamento,
                    p.nome AS paciente_nome
             FROM appointments a
             INNER JOIN patients p ON p.id = a.patient_id
             WHERE a.user_id = :uid
               AND a.pago = 0
               AND a.valor_cobrado IS NOT NULL
               AND a.status IN ('realizada','confirmada')
               AND p.deleted_at IS NULL
             ORDER BY a.data_hora DESC
             LIMIT 20"
        );
        $stmt->execute(['uid' => $uid]);

        return $stmt->fetchAll() ?: [];
    }

    private function buildLancamentos(PDO $db, int $uid, string $ini, string $fim, string $forma, string $status, int $page, int $perPage): array
    {
        [$extraClauses, $extraParams] = $this->buildWhereExtra($db, $forma, $status);
        $extraSql = $extraClauses ? ('AND ' . implode(' AND ', $extraClauses)) : '';
        $offset = ($page - 1) * $perPage;

        $stmt = $db->prepare(
            "SELECT a.id, a.data_hora, a.procedimento, a.valor_cobrado, a.forma_pagamento,
                    a.pago, a.data_pagamento, a.status,
                    p.nome AS paciente_nome
             FROM appointments a
             INNER JOIN patients p ON p.id = a.patient_id
             WHERE a.user_id = :uid
               AND a.status IN ('realizada','confirmada')
               AND DATE(a.data_hora) BETWEEN :ini AND :fim
               $extraSql
               AND p.deleted_at IS NULL
             ORDER BY a.data_hora DESC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
        $stmt->bindValue(':ini', $ini, PDO::PARAM_STR);
        $stmt->bindValue(':fim', $fim, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        foreach ($extraParams as $k => $v) {
            $stmt->bindValue(':' . $k, $v, PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll() ?: [];
    }

    private function countLancamentos(PDO $db, int $uid, string $ini, string $fim, string $forma, string $status): int
    {
        [$extraClauses, $extraParams] = $this->buildWhereExtra($db, $forma, $status);
        $extraSql = $extraClauses ? ('AND ' . implode(' AND ', $extraClauses)) : '';

        $stmt = $db->prepare(
            "SELECT COUNT(*) AS total
             FROM appointments a
             INNER JOIN patients p ON p.id = a.patient_id
             WHERE a.user_id = :uid
               AND a.status IN ('realizada','confirmada')
               AND DATE(a.data_hora) BETWEEN :ini AND :fim
               $extraSql
               AND p.deleted_at IS NULL"
        );
        $stmt->execute(array_merge(['uid' => $uid, 'ini' => $ini, 'fim' => $fim], $extraParams));

        return (int) ($stmt->fetch()['total'] ?? 0);
    }

    private function buildFormasDisponiveis(PDO $db, int $uid): array
    {
        $stmt = $db->prepare(
            "SELECT DISTINCT forma_pagamento
             FROM appointments
             WHERE user_id = :uid AND forma_pagamento IS NOT NULL
             ORDER BY forma_pagamento"
        );
        $stmt->execute(['uid' => $uid]);

        return array_column($stmt->fetchAll(), 'forma_pagamento');
    }
}