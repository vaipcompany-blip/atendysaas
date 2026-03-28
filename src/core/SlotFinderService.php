<?php

declare(strict_types=1);

/**
 * SlotFinderService
 *
 * Calcula os próximos N slots de horário livres para um determinado dentista,
 * respeitando horário de expediente, duração da consulta, intervalo entre
 * consultas e consultas já agendadas/confirmadas.
 */
final class SlotFinderService
{
    /** Dias da semana ignorados (0 = domingo, 6 = sábado). */
    private const WEEKEND_DAYS = [0, 6];

    /** Máximo de dias à frente que o algoritmo varre antes de desistir. */
    private const MAX_DAYS_AHEAD = 30;

    /**
     * Retorna até $limit slots livres a partir de amanhã.
     *
     * Cada item do array retornado:
     *   ['label' => 'Seg 23/03 às 09:10', 'datetime' => '2026-03-23 09:10:00']
     *
     * @return array<int, array{label: string, datetime: string}>
     */
    public function findNext(int $userId, int $limit = 3): array
    {
        $settings = $this->loadSettings($userId);
        $blockedDates = $this->loadBlockedDates($userId);

        $abertura   = (string) ($settings['horario_abertura']   ?? '08:00:00');
        $fechamento = (string) ($settings['horario_fechamento'] ?? '18:00:00');
        $duracao    = max(10, (int) ($settings['duracao_consulta'] ?? 60));
        $intervalo  = max(0,  (int) ($settings['intervalo']        ?? 10));
        $passo      = $duracao + $intervalo; // minutos entre início de um slot e o próximo

        $occupied  = $this->loadOccupiedSlots($userId);
        $slots     = [];
        $today     = new DateTime('today');
        $dayOffset = 1; // começa amanhã

        while (count($slots) < $limit && $dayOffset <= self::MAX_DAYS_AHEAD) {
            $day = (clone $today)->modify("+{$dayOffset} days");

            // Pula fins de semana
            if (in_array((int) $day->format('w'), self::WEEKEND_DAYS, true)) {
                $dayOffset++;
                continue;
            }

            $dateStr = $day->format('Y-m-d');

            // Pula datas bloqueadas (feriados/indisponibilidades cadastradas)
            if (isset($blockedDates[$dateStr])) {
                $dayOffset++;
                continue;
            }

            // Gera todos os slots do dia
            $cursor = new DateTime("{$dateStr} {$abertura}");
            $end    = new DateTime("{$dateStr} {$fechamento}");

            while ($cursor < $end && count($slots) < $limit) {
                // Slot termina após a duração definida e não pode ultrapassar o fechamento.
                $slotEnd = (clone $cursor)->modify("+{$duracao} minutes");
                if ($slotEnd > $end) {
                    break;
                }

                $slotKey = $cursor->format('Y-m-d H:i:s');

                if (!isset($occupied[$slotKey])) {
                    $slots[] = [
                        'label'    => $this->formatLabel($cursor),
                        'datetime' => $slotKey,
                    ];
                }

                $cursor->modify("+{$passo} minutes");
            }

            $dayOffset++;
        }

        return $slots;
    }

    // �"?�"? Private helpers �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?

    private function loadSettings(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT horario_abertura, horario_fechamento, duracao_consulta, intervalo
             FROM settings
             WHERE user_id = :user_id
             LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetch() ?: [];
    }

    /**
     * Retorna um mapa [datetime_string => true] de todos os slots já ocupados
     * nos próximos MAX_DAYS_AHEAD dias (status agendada ou confirmada).
     *
     * @return array<string, true>
     */
    private function loadOccupiedSlots(int $userId): array
    {
        $from = (new DateTime('tomorrow'))->format('Y-m-d 00:00:00');
        $to   = (new DateTime('today'))
                    ->modify('+' . self::MAX_DAYS_AHEAD . ' days')
                    ->format('Y-m-d 23:59:59');

        $stmt = Database::connection()->prepare(
            "SELECT DATE_FORMAT(data_hora, '%Y-%m-%d %H:%i:%s') AS slot
             FROM appointments
             WHERE user_id   = :user_id
               AND status    IN ('agendada', 'confirmada')
               AND data_hora BETWEEN :from AND :to"
        );
        $stmt->execute(['user_id' => $userId, 'from' => $from, 'to' => $to]);

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(string) $row['slot']] = true;
        }

        return $map;
    }

    /**
     * Retorna mapa [YYYY-mm-dd => true] de datas bloqueadas ativas do usuário.
     *
     * @return array<string, true>
     */
    private function loadBlockedDates(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT DATE_FORMAT(blocked_date, "%Y-%m-%d") AS blocked_day
             FROM clinic_blocked_dates
             WHERE user_id = :user_id
               AND is_active = 1'
        );
        $stmt->execute(['user_id' => $userId]);

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $day = (string) ($row['blocked_day'] ?? '');
            if ($day !== '') {
                $map[$day] = true;
            }
        }

        return $map;
    }

    /**
     * Formata o slot para exibição amigável em português.
     * Ex: "Seg 23/03 às 09:10"
     */
    private function formatLabel(DateTime $dt): string
    {
        $diasPt = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
        $diaSemana = $diasPt[(int) $dt->format('w')];
        return sprintf('%s %s às %s', $diaSemana, $dt->format('d/m'), $dt->format('H:i'));
    }
}

