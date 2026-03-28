<?php

declare(strict_types=1);

final class PatientController
{
    public function index(): void
    {
        $user = Auth::user();
        $userId = (int) $user['id'];

        $search = trim((string) ($_GET['search'] ?? ''));
        $patients = $this->fetchPatients($userId, $search);
        $message = $_GET['message'] ?? null;

        View::render('patients/index', [
            'patients' => $patients,
            'search' => $search,
            'message' => $message,
        ]);
    }

    public function create(): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            redirect(base_url('route=patients&message=Token inválido'));
        }

        $userId = (int) (Auth::user()['id'] ?? 0);
        $nome = trim((string) ($_POST['nome'] ?? ''));
        $whatsapp = preg_replace('/\D+/', '', (string) ($_POST['whatsapp'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $cpf = preg_replace('/\D+/', '', (string) ($_POST['cpf'] ?? ''));

        if ($nome === '' || $whatsapp === '' || $cpf === '') {
            redirect(base_url('route=patients&message=Nome, WhatsApp e CPF são obrigatórios'));
        }

        if (strlen($whatsapp) !== 11) {
            redirect(base_url('route=patients&message=WhatsApp deve ter 11 dígitos'));
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirect(base_url('route=patients&message=E-mail inválido'));
        }

        $db = Database::connection();
        $check = $db->prepare('SELECT id FROM patients WHERE user_id = :user_id AND cpf = :cpf LIMIT 1');
        $check->execute(['user_id' => $userId, 'cpf' => $cpf]);
        if ($check->fetch()) {
            redirect(base_url('route=patients&message=CPF já cadastrado'));
        }

        $stmt = $db->prepare('INSERT INTO patients (user_id, nome, whatsapp, email, cpf, created_at, updated_at) VALUES (:user_id, :nome, :whatsapp, :email, :cpf, NOW(), NOW())');
        $stmt->execute([
            'user_id' => $userId,
            'nome' => $nome,
            'whatsapp' => $whatsapp,
            'email' => $email !== '' ? $email : null,
            'cpf' => $cpf,
        ]);

        $patientId = (int) $db->lastInsertId();
        NotificationController::create($userId, 'paciente_adicionado', 'Paciente Adicionado', "Novo paciente '{$nome}' foi adicionado.", 'patient', $patientId);
        audit_log_event($userId, 'patient_created', 'Paciente #' . $patientId . ' criado: ' . $nome . '.');

        redirect(base_url('route=patients&message=Paciente cadastrado com sucesso'));
    }

    public function archive(): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            redirect(base_url('route=patients&message=Token inválido'));
        }

        $patientId = (int) ($_POST['patient_id'] ?? 0);
        $userId = (int) (Auth::user()['id'] ?? 0);

        $stmt = Database::connection()->prepare('UPDATE patients SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id AND user_id = :user_id');
        $stmt->execute([
            'id' => $patientId,
            'user_id' => $userId,
        ]);

        if ($stmt->rowCount() > 0) {
            audit_log_event($userId, 'patient_archived', 'Paciente #' . $patientId . ' arquivado.');
        }

        redirect(base_url('route=patients&message=Paciente arquivado'));
    }

    public function edit(): void
    {
        $userId    = (int) (Auth::user()['id'] ?? 0);
        $patientId = (int) ($_GET['patient_id'] ?? 0);

        if ($patientId <= 0) {
            redirect(base_url('route=patients&message=Paciente inválido'));
        }

        $stmt = Database::connection()->prepare(
            'SELECT id, nome, whatsapp, email, cpf, telefone, data_nascimento, endereco, status
             FROM patients
             WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $patientId, 'user_id' => $userId]);
        $patient = $stmt->fetch();

        if (!$patient) {
            redirect(base_url('route=patients&message=Paciente não encontrado'));
        }

        $message = $_GET['message'] ?? null;

        View::render('patients/edit', [
            'patient' => $patient,
            'message' => $message,
        ]);
    }

    public function update(): void
    {
        if (!verify_csrf($_POST['csrf_token'] ?? null)) {
            redirect(base_url('route=patients&message=Token inválido'));
        }

        $userId    = (int) (Auth::user()['id'] ?? 0);
        $patientId = (int) ($_POST['patient_id'] ?? 0);
        $nome      = trim((string) ($_POST['nome'] ?? ''));
        $whatsapp  = preg_replace('/\D+/', '', (string) ($_POST['whatsapp'] ?? ''));
        $email     = trim((string) ($_POST['email'] ?? ''));
        $telefone  = trim((string) ($_POST['telefone'] ?? ''));
        $dataNasc  = trim((string) ($_POST['data_nascimento'] ?? ''));
        $endereco  = trim((string) ($_POST['endereco'] ?? ''));
        $status    = (string) ($_POST['status'] ?? 'ativo');

        $allowedStatus = ['ativo', 'lead', 'arquivado'];
        if (!in_array($status, $allowedStatus, true)) {
            $status = 'ativo';
        }

        if ($nome === '' || $whatsapp === '') {
            redirect(base_url('route=patients&action=edit&patient_id=' . $patientId . '&message=Nome e WhatsApp são obrigatórios'));
        }

        if (strlen($whatsapp) !== 11) {
            redirect(base_url('route=patients&action=edit&patient_id=' . $patientId . '&message=WhatsApp deve ter 11 dígitos'));
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirect(base_url('route=patients&action=edit&patient_id=' . $patientId . '&message=E-mail inválido'));
        }

        $stmt = Database::connection()->prepare(
            'UPDATE patients
             SET nome = :nome, whatsapp = :whatsapp, email = :email, telefone = :telefone,
                 data_nascimento = :data_nascimento, endereco = :endereco,
                 status = :status, updated_at = NOW()
             WHERE id = :id AND user_id = :user_id AND deleted_at IS NULL'
        );
        $stmt->execute([
            'nome'            => $nome,
            'whatsapp'        => $whatsapp,
            'email'           => $email !== '' ? $email : null,
            'telefone'        => $telefone !== '' ? $telefone : null,
            'data_nascimento' => $dataNasc !== '' ? $dataNasc : null,
            'endereco'        => $endereco !== '' ? $endereco : null,
            'status'          => $status,
            'id'              => $patientId,
            'user_id'         => $userId,
        ]);

        if ($stmt->rowCount() > 0) {
            audit_log_event($userId, 'patient_updated', 'Paciente #' . $patientId . ' atualizado.');
        }

        redirect(base_url('route=patients&message=Paciente atualizado com sucesso'));
    }

    public function exportCsv(): void
    {
        $user = Auth::user();
        $userId = (int) ($user['id'] ?? 0);
        $search = trim((string) ($_GET['search'] ?? ''));

        $patients = $this->fetchPatients($userId, $search);
        $clinic = trim((string) ($user['nome_consultorio'] ?? 'atendy'));
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $clinic) ?: 'atendy';
        $fileName = 'pacientes-' . strtolower(trim($slug, '-')) . '-' . date('Ymd-His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');

        $output = fopen('php://output', 'wb');
        if ($output === false) {
            http_response_code(500);
            echo 'Falha ao gerar CSV';
            exit;
        }

        fwrite($output, "\xEF\xBB\xBF");

        fputcsv($output, ['Nome', 'WhatsApp', 'E-mail', 'CPF', 'Data de cadastro'], ';');

        foreach ($patients as $patient) {
            fputcsv($output, [
                (string) ($patient['nome'] ?? ''),
                (string) ($patient['whatsapp'] ?? ''),
                (string) ($patient['email'] ?? ''),
                (string) ($patient['cpf'] ?? ''),
                isset($patient['created_at']) ? date('d/m/Y H:i', strtotime((string) $patient['created_at'])) : '',
            ], ';');
        }

        fclose($output);
        exit;
    }

    public function exportPdf(): void
    {
        $user = Auth::user();
        $userId = (int) ($user['id'] ?? 0);
        $search = trim((string) ($_GET['search'] ?? ''));

        $patients = $this->fetchPatients($userId, $search);

        View::render('patients/export_pdf', [
            'patients' => $patients,
            'search' => $search,
            'user' => $user,
            'generatedAt' => date('d/m/Y H:i'),
        ]);
        exit;
    }

    private function fetchPatients(int $userId, string $search): array
    {
        $db = Database::connection();

        if ($search !== '') {
            $stmt = $db->prepare(
                'SELECT id, nome, whatsapp, email, cpf, created_at
                 FROM patients
                 WHERE user_id = :user_id
                   AND deleted_at IS NULL
                   AND (nome LIKE :search OR whatsapp LIKE :search)
                 ORDER BY created_at DESC'
            );
            $stmt->execute([
                'user_id' => $userId,
                'search' => '%' . $search . '%',
            ]);
            return $stmt->fetchAll();
        }

        $stmt = $db->prepare(
            'SELECT id, nome, whatsapp, email, cpf, created_at
             FROM patients
             WHERE user_id = :user_id
               AND deleted_at IS NULL
             ORDER BY created_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }
}


