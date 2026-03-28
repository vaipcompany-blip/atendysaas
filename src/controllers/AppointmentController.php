<?php

declare(strict_types=1);

final class AppointmentController
{
    public function index(): void
    {
        $userId = (int) (Auth::user()['id'] ?? 0);
        $db = Database::connection();

        $period = (string) ($_GET['period'] ?? 'week');
        $allowedPeriods = ['day', 'week', 'month'];
        if (!in_array($period, $allowedPeriods, true)) {
            $period = 'week';
        }

        $dateParam = (string) ($_GET['date'] ?? date('Y-m-d'));
        $referenceDate = DateTime::createFromFormat('Y-m-d', $dateParam) ?: new DateTime('today');
        $referenceDate->setTime(0, 0, 0);

        [$rangeStart, $rangeEnd, $periodLabel] = $this->resolvePeriodRange($period, $referenceDate);

        $step = match ($period) {
            'day' => '1 day',
            'week' => '7 days',
            default => '1 month',
        };

        $prevDate = (clone $referenceDate)->modify('-' . $step)->format('Y-m-d');
        $nextDate = (clone $referenceDate)->modify('+' . $step)->format('Y-m-d');

        $stmtPatients = $db->prepare('SELECT id, nome FROM patients WHERE user_id = :user_id AND deleted_at IS NULL ORDER BY nome ASC');
        $stmtPatients->execute(['user_id' => $userId]);
        $patients = $stmtPatients->fetchAll();

                $appointments = $this->fetchAppointmentsByRange($userId, $rangeStart, $rangeEnd);

        $statusSummary = [
            'agendada' => 0,
            'confirmada' => 0,
            'realizada' => 0,
            'cancelada' => 0,
            'faltou' => 0,
            'reagendada' => 0,
        ];

        $appointmentsByDay = [];
        foreach ($appointments as $appointment) {
            $status = (string) ($appointment['status'] ?? 'agendada');
            if (isset($statusSummary[$status])) {
                $statusSummary[$status]++;
            }

            $dayKey = date('Y-m-d', strtotime((string) $appointment['data_hora']));
            if (!isset($appointmentsByDay[$dayKey])) {
                $appointmentsByDay[$dayKey] = [];
            }
            $appointmentsByDay[$dayKey][] = $appointment;
        }

        $message = $_GET['message'] ?? null;

        View::render('appointments/index', [
            'patients' => $patients,
            'appointments' => $appointments,
            'appointmentsByDay' => $appointmentsByDay,
            'statusSummary' => $statusSummary,
            'period' => $period,
            'periodLabel' => $periodLabel,
            'referenceDate' => $referenceDate->format('Y-m-d'),
            'rangeStart' => $rangeStart,
            'rangeEnd' => $rangeEnd,
            'prevDate' => $prevDate,
            'nextDate' => $nextDate,
            'message' => $message,
        ]);
    }

    public function exportCsv(): void
    {
        $user = Auth::user();
        $userId = (int) ($user['id'] ?? 0);

        $period = (string) ($_GET['period'] ?? 'week');
        $allowedPeriods = ['day', 'week', 'month'];
        if (!in_array($period, $allowedPeriods, true)) {
            $period = 'week';
        }

        $dateParam = (string) ($_GET['date'] ?? date('Y-m-d'));
        $referenceDate = DateTime::createFromFormat('Y-m-d', $dateParam) ?: new DateTime('today');
        $referenceDate->setTime(0, 0, 0);

        [$rangeStart, $rangeEnd] = $this->resolvePeriodRange($period, $referenceDate);
        $appointments = $this->fetchAppointmentsByRange($userId, $rangeStart, $rangeEnd);

        $clinic = trim((string) ($user['nome_consultorio'] ?? 'atendy'));
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $clinic) ?: 'atendy';
        $fileName = 'consultas-' . strtolower(trim($slug, '-')) . '-' . date('Ymd-His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');

        $output = fopen('php://output', 'wb');
        if ($output === false) {
            http_response_code(500);
            echo 'Falha ao gerar CSV';
            exit;
        }

        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, ['Paciente', 'Data/Hora', 'Status', 'Procedimento'], ';');

        foreach ($appointments as $appointment) {
            fputcsv($output, [
                (string) ($appointment['paciente_nome'] ?? ''),
                isset($appointment['data_hora']) ? date('d/m/Y H:i', strtotime((string) $appointment['data_hora'])) : '',
                (string) ($appointment['status'] ?? ''),
                (string) ($appointment['procedimento'] ?? ''),
            ], ';');
        }

        fclose($output);
        exit;
    }

    public function exportPdf(): void
    {
        $user = Auth::user();
        $userId = (int) ($user['id'] ?? 0);

        $period = (string) ($_GET['period'] ?? 'week');
        $allowedPeriods = ['day', 'week', 'month'];
        if (!in_array($period, $allowedPeriods, true)) {
            $period = 'week';
        }

        $dateParam = (string) ($_GET['date'] ?? date('Y-m-d'));
        $referenceDate = DateTime::createFromFormat('Y-m-d', $dateParam) ?: new DateTime('today');
        $referenceDate->setTime(0, 0, 0);

        [$rangeStart, $rangeEnd, $periodLabel] = $this->resolvePeriodRange($period, $referenceDate);
        $appointments = $this->fetchAppointmentsByRange($userId, $rangeStart, $rangeEnd);

        View::render('appointments/export_pdf', [
            'appointments' => $appointments,
            'user' => $user,
            'periodLabel' => $periodLabel,
            'rangeStart' => $rangeStart,
            'rangeEnd' => $rangeEnd,
            'generatedAt' => date('d/m/Y H:i'),
        ]);
        exit;
    }

    private function resolvePeriodRange(string $period, DateTime $referenceDate): array
    {
        if ($period === 'day') {
            $start = (clone $referenceDate)->setTime(0, 0, 0);
            $end = (clone $referenceDate)->setTime(23, 59, 59);
            $label = 'Dia';
            return [$start, $end, $label];
        }

        if ($period === 'month') {
            $start = (clone $referenceDate)->modify('first day of this month')->setTime(0, 0, 0);
            $end = (clone $referenceDate)->modify('last day of this month')->setTime(23, 59, 59);
            $label = 'Mês';
            return [$start, $end, $label];
        }

        $weekday = (int) $referenceDate->format('N');
        $start = (clone $referenceDate)->modify('-' . ($weekday - 1) . ' days')->setTime(0, 0, 0);
        $end = (clone $start)->modify('+6 days')->setTime(23, 59, 59);
        $label = 'Semana';

        return [$start, $end, $label];
    }

    private function fetchAppointmentsByRange(int $userId, DateTime $rangeStart, DateTime $rangeEnd): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT a.id, a.data_hora, a.status, a.procedimento, p.nome AS paciente_nome
             FROM appointments a
             INNER JOIN patients p ON p.id = a.patient_id
             WHERE a.user_id = :user_id
               AND a.data_hora BETWEEN :start_date AND :end_date
             ORDER BY a.data_hora ASC'
        );
        $stmt->execute([
            'user_id' => $userId,
            'start_date' => $rangeStart->format('Y-m-d H:i:s'),
            'end_date' => $rangeEnd->format('Y-m-d H:i:s'),
        ]);

        return $stmt->fetchAll();
    }

    public function create(): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            redirect(base_url('route=appointments&message=Token inválido'));
        }

        $userId = (int) (Auth::user()['id'] ?? 0);
        $patientId = (int) ($_POST['patient_id'] ?? 0);
        $dataHora = (string) ($_POST['data_hora'] ?? '');
        $procedimento = trim((string) ($_POST['procedimento'] ?? 'Consulta'));
        $recurrenceEnabled = (int) ($_POST['recurrence_enabled'] ?? 0) === 1;
        $recurrenceFrequency = (string) ($_POST['recurrence_frequency'] ?? 'weekly');
        $recurrenceCount = (int) ($_POST['recurrence_count'] ?? 1);

        if ($patientId <= 0 || $dataHora === '') {
            redirect(base_url('route=appointments&message=Paciente e data são obrigatórios'));
        }

        $date = DateTime::createFromFormat('Y-m-d\TH:i', $dataHora);
        if (!$date) {
            redirect(base_url('route=appointments&message=Data inválida'));
        }

        if ($date < new DateTime()) {
            redirect(base_url('route=appointments&message=Não pode agendar no passado'));
        }

        if (!in_array($recurrenceFrequency, ['weekly', 'monthly'], true)) {
            $recurrenceFrequency = 'weekly';
        }

        if ($recurrenceCount < 1) {
            $recurrenceCount = 1;
        }
        if ($recurrenceCount > 12) {
            $recurrenceCount = 12;
        }

        if (!$recurrenceEnabled) {
            $recurrenceCount = 1;
        }

        if ($this->isBlockedDate($userId, $date)) {
            redirect(base_url('route=appointments&message=Data indisponível (bloqueada no expediente da clínica)'));
        }

        $db = Database::connection();
        $check = $db->prepare('SELECT id FROM appointments WHERE user_id = :user_id AND data_hora = :data_hora AND status IN ("agendada", "confirmada") LIMIT 1');
        $check->execute([
            'user_id' => $userId,
            'data_hora' => $date->format('Y-m-d H:i:s'),
        ]);

        if ($check->fetch()) {
            redirect(base_url('route=appointments&message=Já existe consulta nesse horário'));
        }

        $createdIds = [];
        $ignoredCount = 0;

        for ($index = 0; $index < $recurrenceCount; $index++) {
            $slotDate = $this->resolveRecurringSlotDate($date, $index, $recurrenceFrequency);

            if ($slotDate < new DateTime()) {
                $ignoredCount++;
                continue;
            }

            if ($this->isBlockedDate($userId, $slotDate)) {
                $ignoredCount++;
                continue;
            }

            $check->execute([
                'user_id' => $userId,
                'data_hora' => $slotDate->format('Y-m-d H:i:s'),
            ]);
            if ($check->fetch()) {
                $ignoredCount++;
                continue;
            }

            $stmt = $db->prepare('INSERT INTO appointments (user_id, patient_id, data_hora, status, procedimento, created_at, updated_at) VALUES (:user_id, :patient_id, :data_hora, "agendada", :procedimento, NOW(), NOW())');
            $stmt->execute([
                'user_id' => $userId,
                'patient_id' => $patientId,
                'data_hora' => $slotDate->format('Y-m-d H:i:s'),
                'procedimento' => $procedimento,
            ]);
            $createdIds[] = (int) $db->lastInsertId();
        }

        if (empty($createdIds)) {
            redirect(base_url('route=appointments&message=Não foi possível criar as consultas recorrentes (conflito ou data bloqueada).'));
        }

        NotificationController::create(
            $userId,
            'consulta_agendada',
            'Consulta Agendada',
            $recurrenceCount > 1
                ? 'Série recorrente criada com ' . count($createdIds) . ' consulta(s).' 
                : 'Nova consulta agendada com sucesso.',
            'appointment',
            $createdIds[0]
        );
        audit_log_event($userId, 'appointment_created', 'Consulta(s) criada(s): ' . implode(',', $createdIds) . '.');

        $message = $recurrenceCount > 1
            ? 'Série criada: ' . count($createdIds) . ' consulta(s)' . ($ignoredCount > 0 ? ' · ignoradas: ' . $ignoredCount : '')
            : 'Consulta criada com sucesso';

        redirect(base_url('route=appointments&message=' . urlencode($message)));
    }

    private function resolveRecurringSlotDate(DateTime $baseDate, int $index, string $frequency): DateTime
    {
        $slotDate = clone $baseDate;
        if ($index === 0) {
            return $slotDate;
        }

        if ($frequency === 'monthly') {
            $slotDate->modify('+' . $index . ' month');
            return $slotDate;
        }

        $slotDate->modify('+' . $index . ' week');
        return $slotDate;
    }

    public function edit(): void
    {
        $userId        = (int) (Auth::user()['id'] ?? 0);
        $appointmentId = (int) ($_GET['appointment_id'] ?? 0);

        if ($appointmentId <= 0) {
            redirect(base_url('route=appointments&message=Consulta inválida'));
        }

        $db   = Database::connection();
        $stmt = $db->prepare(
            'SELECT id, patient_id, data_hora, status, procedimento, notas
             FROM appointments
             WHERE id = :id AND user_id = :user_id
             LIMIT 1'
        );
        $stmt->execute(['id' => $appointmentId, 'user_id' => $userId]);
        $appointment = $stmt->fetch();

        if (!$appointment) {
            redirect(base_url('route=appointments&message=Consulta não encontrada'));
        }

        $stmtPatients = $db->prepare('SELECT id, nome FROM patients WHERE user_id = :user_id AND deleted_at IS NULL ORDER BY nome ASC');
        $stmtPatients->execute(['user_id' => $userId]);
        $patients = $stmtPatients->fetchAll();

        $message = $_GET['message'] ?? null;

        View::render('appointments/edit', [
            'appointment' => $appointment,
            'patients'    => $patients,
            'message'     => $message,
        ]);
    }

    public function update(): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            redirect(base_url('route=appointments&message=Token inválido'));
        }

        $userId        = (int) (Auth::user()['id'] ?? 0);
        $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
        $patientId     = (int) ($_POST['patient_id'] ?? 0);
        $dataHora      = (string) ($_POST['data_hora'] ?? '');
        $procedimento  = trim((string) ($_POST['procedimento'] ?? 'Consulta'));
        $status        = (string) ($_POST['status'] ?? 'agendada');
        $notas         = trim((string) ($_POST['notas'] ?? ''));

        $allowedStatus = ['agendada','confirmada','realizada','cancelada','faltou','reagendada'];
        if (!in_array($status, $allowedStatus, true)) {
            redirect(base_url('route=appointments&message=Status inválido'));
        }

        if ($patientId <= 0 || $dataHora === '') {
            redirect(base_url('route=appointments&action=edit&appointment_id=' . $appointmentId . '&message=Paciente e data são obrigatórios'));
        }

        $date = DateTime::createFromFormat('Y-m-d\TH:i', $dataHora);
        if (!$date) {
            redirect(base_url('route=appointments&action=edit&appointment_id=' . $appointmentId . '&message=Data inválida'));
        }

        if ($this->isBlockedDate($userId, $date)) {
            redirect(base_url('route=appointments&action=edit&appointment_id=' . $appointmentId . '&message=Data indisponível (bloqueada no expediente da clínica)'));
        }

        $db    = Database::connection();

        // Verifica conflito apenas se mudar para status "ocupante" e não for a própria consulta
        $occupying = ['agendada', 'confirmada'];
        if (in_array($status, $occupying, true)) {
            $check = $db->prepare(
                'SELECT id FROM appointments
                 WHERE user_id = :user_id
                   AND data_hora = :data_hora
                   AND status IN ("agendada","confirmada")
                   AND id != :current_id
                 LIMIT 1'
            );
            $check->execute([
                'user_id'    => $userId,
                'data_hora'  => $date->format('Y-m-d H:i:s'),
                'current_id' => $appointmentId,
            ]);
            if ($check->fetch()) {
                redirect(base_url('route=appointments&action=edit&appointment_id=' . $appointmentId . '&message=Já existe consulta nesse horário'));
            }
        }

        $stmt = $db->prepare(
            'UPDATE appointments
             SET patient_id = :patient_id, data_hora = :data_hora,
                 procedimento = :procedimento, status = :status,
                 notas = :notas, updated_at = NOW()
             WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute([
            'patient_id'  => $patientId,
            'data_hora'   => $date->format('Y-m-d H:i:s'),
            'procedimento'=> $procedimento,
            'status'      => $status,
            'notas'       => $notas !== '' ? $notas : null,
            'id'          => $appointmentId,
            'user_id'     => $userId,
        ]);

        if ($stmt->rowCount() > 0) {
            audit_log_event($userId, 'appointment_updated', 'Consulta #' . $appointmentId . ' atualizada.');
        }

        redirect(base_url('route=appointments&message=Consulta atualizada com sucesso'));
    }

    public function updateStatus(): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            redirect(base_url('route=appointments&message=Token inválido'));
        }

        $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
        $status = (string) ($_POST['status'] ?? 'agendada');
        $userId = (int) (Auth::user()['id'] ?? 0);

        $allowed = ['agendada', 'confirmada', 'realizada', 'cancelada', 'faltou', 'reagendada'];
        if (!in_array($status, $allowed, true)) {
            redirect(base_url('route=appointments&message=Status inválido'));
        }

        $stmt = Database::connection()->prepare('UPDATE appointments SET status = :status, updated_at = NOW() WHERE id = :id AND user_id = :user_id');
        $stmt->execute([
            'status' => $status,
            'id' => $appointmentId,
            'user_id' => $userId,
        ]);

        // Gerar notificação baseada no novo status
        if ($status === 'confirmada') {
            NotificationController::create($userId, 'consulta_confirmada', 'Consulta Confirmada', 'Sua consulta foi confirmada.', 'appointment', $appointmentId);
        } elseif ($status === 'cancelada') {
            NotificationController::create($userId, 'consulta_cancelada', 'Consulta Cancelada', 'Sua consulta foi cancelada.', 'appointment', $appointmentId);
        } elseif ($status === 'realizada') {
            NotificationController::create($userId, 'consulta_realizada', 'Consulta Realizada', 'Sua consulta foi marcada como realizada.', 'appointment', $appointmentId);
        }

        if ($stmt->rowCount() > 0) {
            audit_log_event($userId, 'appointment_status_updated', 'Consulta #' . $appointmentId . ' alterada para ' . $status . '.');
        }

        redirect(base_url('route=appointments&message=Status atualizado'));
    }

    public function quickReschedule(): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            redirect(base_url('route=appointments&message=Token inválido'));
        }

        $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
        $newDateTime = trim((string) ($_POST['new_data_hora'] ?? ''));
        $period = (string) ($_POST['period'] ?? 'month');
        $date = (string) ($_POST['date'] ?? date('Y-m-d'));
        $userId = (int) (Auth::user()['id'] ?? 0);

        if ($appointmentId <= 0 || $newDateTime === '') {
            redirect(base_url('route=appointments&period=' . urlencode($period) . '&date=' . urlencode($date) . '&message=Dados inválidos para reagendamento'));
        }

        $dt = DateTime::createFromFormat('Y-m-d\TH:i', $newDateTime);
        if (!$dt) {
            $dt = DateTime::createFromFormat('Y-m-d H:i:s', $newDateTime);
        }

        if (!$dt) {
            redirect(base_url('route=appointments&period=' . urlencode($period) . '&date=' . urlencode($date) . '&message=Data inválida'));
        }

        if ($dt < new DateTime()) {
            redirect(base_url('route=appointments&period=' . urlencode($period) . '&date=' . urlencode($date) . '&message=Não pode reagendar para o passado'));
        }

        if ($this->isBlockedDate($userId, $dt)) {
            redirect(base_url('route=appointments&period=' . urlencode($period) . '&date=' . urlencode($date) . '&message=Data indisponível (bloqueada no expediente da clínica)'));
        }

        $db = Database::connection();

        $stmtCurrent = $db->prepare('SELECT id, status FROM appointments WHERE id = :id AND user_id = :user_id LIMIT 1');
        $stmtCurrent->execute(['id' => $appointmentId, 'user_id' => $userId]);
        $current = $stmtCurrent->fetch();
        if (!$current) {
            redirect(base_url('route=appointments&period=' . urlencode($period) . '&date=' . urlencode($date) . '&message=Consulta não encontrada'));
        }

        $check = $db->prepare(
            'SELECT id FROM appointments
             WHERE user_id = :user_id
               AND data_hora = :data_hora
               AND status IN ("agendada", "confirmada")
               AND id <> :id
             LIMIT 1'
        );
        $check->execute([
            'user_id' => $userId,
            'data_hora' => $dt->format('Y-m-d H:i:s'),
            'id' => $appointmentId,
        ]);

        if ($check->fetch()) {
            redirect(base_url('route=appointments&period=' . urlencode($period) . '&date=' . urlencode($date) . '&message=Conflito: já existe consulta nesse horário'));
        }

        $newStatus = (string) ($current['status'] ?? 'agendada');
        if (in_array($newStatus, ['agendada', 'confirmada'], true)) {
            $newStatus = 'reagendada';
        }

        $update = $db->prepare(
            'UPDATE appointments
             SET data_hora = :data_hora,
                 status = :status,
                 updated_at = NOW()
             WHERE id = :id AND user_id = :user_id'
        );
        $update->execute([
            'data_hora' => $dt->format('Y-m-d H:i:s'),
            'status' => $newStatus,
            'id' => $appointmentId,
            'user_id' => $userId,
        ]);

        if ($update->rowCount() > 0) {
            audit_log_event($userId, 'appointment_rescheduled', 'Consulta #' . $appointmentId . ' reagendada para ' . $dt->format('Y-m-d H:i:s') . '.');
        }

        redirect(base_url('route=appointments&period=' . urlencode($period) . '&date=' . urlencode($date) . '&message=Consulta reagendada com sucesso'));
    }

    private function isBlockedDate(int $userId, DateTime $date): bool
    {
        if ($this->hasBlockedTimeColumns()) {
            $stmt = Database::connection()->prepare(
                'SELECT id
                 FROM clinic_blocked_dates
                 WHERE user_id = :user_id
                   AND blocked_date = :blocked_date
                   AND is_active = 1
                   AND (
                       (start_time IS NULL AND end_time IS NULL)
                       OR (
                           start_time IS NOT NULL
                           AND end_time IS NOT NULL
                           AND TIME(:date_time) >= start_time
                           AND TIME(:date_time) < end_time
                       )
                   )
                 LIMIT 1'
            );
            $stmt->execute([
                'user_id' => $userId,
                'blocked_date' => $date->format('Y-m-d'),
                'date_time' => $date->format('Y-m-d H:i:s'),
            ]);

            return (bool) $stmt->fetch();
        }

        $stmt = Database::connection()->prepare(
            'SELECT id
             FROM clinic_blocked_dates
             WHERE user_id = :user_id
               AND blocked_date = :blocked_date
               AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute([
            'user_id' => $userId,
            'blocked_date' => $date->format('Y-m-d'),
        ]);

        return (bool) $stmt->fetch();
    }

    private function hasBlockedTimeColumns(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        try {
            $stmt = Database::connection()->query(
                "SELECT COUNT(*) AS total
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'clinic_blocked_dates'
                   AND COLUMN_NAME IN ('start_time', 'end_time')"
            );
            $row = $stmt ? $stmt->fetch() : null;
            $cached = ((int) ($row['total'] ?? 0)) >= 2;
            return $cached;
        } catch (Throwable $e) {
            $cached = false;
            return false;
        }
    }

}


