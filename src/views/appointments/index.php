<style>
.appt-agendada{background:#eaf1ff;color:#1d4ed8;border:1px solid #93c5fd}
.appt-confirmada{background:#dcfce7;color:#166534;border:1px solid #86efac}
.appt-realizada{background:#f3e8ff;color:#6b21a8;border:1px solid #c084fc}
.appt-cancelada{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5}
.appt-faltou{background:#fff7ed;color:#9a3412;border:1px solid #fdba74}
.appt-reagendada{background:#fef9c3;color:#854d0e;border:1px solid #fde047}
.status-badge{display:inline-flex;padding:3px 9px;border-radius:999px;font-size:11px;font-weight:700}
.period-row{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center}
.period-tabs{display:flex;gap:8px;flex-wrap:wrap}
.period-tab{height:34px;padding:0 12px;border-radius:10px;border:1px solid #cbd5e1;background:#fff;color:#334155;text-decoration:none;display:inline-flex;align-items:center;font-size:13px;font-weight:600}
.period-tab.active{background:#2563eb;border-color:#2563eb;color:#fff}
.period-nav{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.period-nav a{height:34px;width:34px;border-radius:10px;background:#fff;border:1px solid #cbd5e1;color:#334155;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;font-weight:700}
.summary-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px}
.summary-item{padding:12px;border:1px solid #e2e8f0;border-radius:12px;background:#fff}
.summary-item strong{display:block;font-size:24px;line-height:1;color:#0f172a}
.summary-item span{font-size:12px;color:#64748b}
.day-block{border:1px solid #e2e8f0;border-radius:12px;padding:12px;margin-bottom:10px;background:#fff}
.day-title{font-size:14px;font-weight:700;color:#0f172a;margin-bottom:8px}
.mini-list{display:flex;flex-direction:column;gap:7px}
.mini-item{display:flex;justify-content:space-between;gap:8px;align-items:center;padding:8px 10px;border-radius:10px;background:#f8fafc;border:1px solid #edf2f7}
.mini-time{font-size:12px;font-weight:700;color:#2563eb;min-width:58px}
.mini-main{flex:1;min-width:0}
.mini-main strong{display:block;font-size:13px;color:#0f172a}
.mini-main span{display:block;font-size:12px;color:#64748b}
.month-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:8px}
.month-head{padding:8px;border-radius:10px;background:#f1f5f9;border:1px solid #e2e8f0;text-align:center;font-size:12px;font-weight:700;color:#475569}
.month-cell{border:1px solid #e2e8f0;border-radius:12px;background:#fff;min-height:108px;padding:8px;display:flex;flex-direction:column;gap:6px;transition:.14s ease}
.month-cell:hover{box-shadow:0 10px 20px rgba(15,23,42,.06);transform:translateY(-1px)}
.month-cell.out{background:#f8fafc;color:#94a3b8}
.month-cell.today{border-color:#93c5fd;box-shadow:0 0 0 2px rgba(59,130,246,.18)}
.month-cell-header{display:flex;justify-content:space-between;align-items:center;font-size:12px;font-weight:700}
.month-count{font-size:11px;color:#2563eb;background:#eaf1ff;border:1px solid #bfdbfe;border-radius:999px;padding:2px 7px}
.month-events{display:flex;flex-direction:column;gap:5px}
.month-event{display:block;text-decoration:none;border:1px solid #e2e8f0;border-radius:8px;padding:5px 6px;background:#f8fafc}
.month-event-time{font-size:11px;font-weight:700;color:#1d4ed8}
.month-event-name{font-size:11px;color:#334155;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.month-more{font-size:11px;color:#64748b}
.month-cell.drop-hover{border-color:#60a5fa;background:#eff6ff}
.month-event[draggable="true"]{cursor:grab}
.month-event[draggable="true"]:active{cursor:grabbing}
.re-modal-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.5);display:none;align-items:center;justify-content:center;z-index:1200;padding:16px}
.re-modal-backdrop.open{display:flex}
.re-modal{width:min(420px,100%);background:#fff;border:1px solid #dbe3ef;border-radius:14px;box-shadow:0 20px 45px rgba(15,23,42,.28);padding:14px}
.re-modal h4{margin:0 0 6px;font-size:17px;color:#0f172a}
.re-modal p{margin:0 0 12px;font-size:13px;color:#64748b}
.re-modal .field{margin-bottom:10px}
.re-modal-actions{display:flex;justify-content:flex-end;gap:8px}
@media(max-width:980px){.month-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>

<h1 class="page-title">Consultas</h1>
<p class="muted" style="margin:0 0 14px;">Período: <strong><?= e((string) $periodLabel) ?></strong> · <?= e($rangeStart->format('d/m/Y')) ?> até <?= e($rangeEnd->format('d/m/Y')) ?></p>

<?php if (!empty($message)): ?>
    <div class="alert"><?= e((string) $message) ?></div>
<?php endif; ?>

<div class="card">
    <div class="period-row">
        <div class="period-tabs">
            <a class="period-tab <?= $period === 'day' ? 'active' : '' ?>" href="<?= e(base_url('route=appointments&period=day&date=' . urlencode((string) $referenceDate))) ?>">Dia</a>
            <a class="period-tab <?= $period === 'week' ? 'active' : '' ?>" href="<?= e(base_url('route=appointments&period=week&date=' . urlencode((string) $referenceDate))) ?>">Semana</a>
            <a class="period-tab <?= $period === 'month' ? 'active' : '' ?>" href="<?= e(base_url('route=appointments&period=month&date=' . urlencode((string) $referenceDate))) ?>">Mês</a>
        </div>

        <div class="period-nav">
            <a href="<?= e(base_url('route=appointments&period=' . $period . '&date=' . urlencode((string) $prevDate))) ?>">&larr;</a>
            <form method="get" action="<?= e(base_url()) ?>" class="inline" style="margin:0;">
                <input type="hidden" name="route" value="appointments">
                <input type="hidden" name="period" value="<?= e((string) $period) ?>">
                <input type="date" name="date" value="<?= e((string) $referenceDate) ?>" style="height:34px;">
                <button type="submit" class="btn-secondary" style="height:34px;padding:0 12px;">Ir</button>
            </form>
            <a href="<?= e(base_url('route=appointments&period=' . $period . '&date=' . urlencode((string) $nextDate))) ?>">&rarr;</a>
        </div>

        <div class="row" style="gap:8px;">
            <a href="<?= e(base_url('route=appointments&action=export_csv&period=' . urlencode((string) $period) . '&date=' . urlencode((string) $referenceDate))) ?>"
               class="btn-secondary"
               style="display:inline-flex;align-items:center;justify-content:center;height:34px;padding:0 12px;border-radius:10px;color:#fff;text-decoration:none;white-space:nowrap;">
                Exportar CSV
            </a>
            <a href="<?= e(base_url('route=appointments&action=export_pdf&period=' . urlencode((string) $period) . '&date=' . urlencode((string) $referenceDate))) ?>"
               target="_blank"
               class="btn-secondary"
               style="display:inline-flex;align-items:center;justify-content:center;height:34px;padding:0 12px;border-radius:10px;color:#fff;text-decoration:none;white-space:nowrap;">
                Exportar PDF
            </a>
        </div>
    </div>
</div>

<div class="card">
    <div class="summary-grid">
        <div class="summary-item"><strong><?= (int) count($appointments) ?></strong><span>Total no período</span></div>
        <div class="summary-item"><strong><?= (int) ($statusSummary['agendada'] ?? 0) ?></strong><span>Agendadas</span></div>
        <div class="summary-item"><strong><?= (int) ($statusSummary['confirmada'] ?? 0) ?></strong><span>Confirmadas</span></div>
        <div class="summary-item"><strong><?= (int) ($statusSummary['realizada'] ?? 0) ?></strong><span>Realizadas</span></div>
        <div class="summary-item"><strong><?= (int) ($statusSummary['cancelada'] ?? 0) ?></strong><span>Canceladas</span></div>
        <div class="summary-item"><strong><?= (int) ($statusSummary['faltou'] ?? 0) ?></strong><span>Faltas</span></div>
    </div>
</div>

<?php if ($period === 'month'): ?>
<div class="card">
    <h3 class="card-title">Calendário do mês</h3>
    <p class="muted" style="margin-top:-6px; margin-bottom:10px;">Arraste um compromisso para outro dia para reagendar rapidamente.</p>
    <?php
    $monthRef = DateTime::createFromFormat('Y-m-d', (string) $referenceDate) ?: new DateTime('today');
    $firstOfMonth = (clone $monthRef)->modify('first day of this month')->setTime(0, 0, 0);
    $startWeekday = (int) $firstOfMonth->format('N');
    $calendarStart = (clone $firstOfMonth)->modify('-' . ($startWeekday - 1) . ' days');
    $lastOfMonth = (clone $monthRef)->modify('last day of this month')->setTime(0, 0, 0);
    $endWeekday = (int) $lastOfMonth->format('N');
    $calendarEnd = (clone $lastOfMonth)->modify('+' . (7 - $endWeekday) . ' days');
    $todayKey = date('Y-m-d');
    $weekDays = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
    ?>

    <div class="month-grid" style="margin-bottom:8px;">
        <?php foreach ($weekDays as $wd): ?>
            <div class="month-head"><?= e($wd) ?></div>
        <?php endforeach; ?>
    </div>

    <div class="month-grid">
        <?php
        for ($d = clone $calendarStart; $d <= $calendarEnd; $d->modify('+1 day')):
            $dayKey = $d->format('Y-m-d');
            $inMonth = $d->format('Y-m') === $monthRef->format('Y-m');
            $items = $appointmentsByDay[$dayKey] ?? [];
            $cellClass = 'month-cell' . ($inMonth ? '' : ' out') . ($dayKey === $todayKey ? ' today' : '');
        ?>
            <div class="<?= e($cellClass) ?>" data-day="<?= e($dayKey) ?>">
                <div class="month-cell-header">
                    <span><?= e($d->format('d')) ?></span>
                    <?php if (!empty($items)): ?><span class="month-count"><?= e((string) count($items)) ?></span><?php endif; ?>
                </div>
                <div class="month-events">
                    <?php foreach (array_slice($items, 0, 2) as $it): ?>
                        <a class="month-event"
                           href="<?= e(base_url('route=appointments&action=edit&appointment_id=' . $it['id'])) ?>"
                           title="Editar consulta"
                           draggable="true"
                           data-appointment-id="<?= e((string) $it['id']) ?>"
                           data-current-datetime="<?= e(date('Y-m-d H:i:s', strtotime((string) $it['data_hora']))) ?>">
                            <div class="month-event-time"><?= e(date('H:i', strtotime((string) $it['data_hora']))) ?></div>
                            <div class="month-event-name"><?= e((string) $it['paciente_nome']) ?></div>
                        </a>
                    <?php endforeach; ?>
                    <?php if (count($items) > 2): ?>
                        <div class="month-more">+<?= e((string) (count($items) - 2)) ?> consulta(s)</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endfor; ?>
    </div>

    <form id="quickRescheduleForm" method="post" action="<?= e(base_url('route=appointments')) ?>" style="display:none;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="quick_reschedule">
        <input type="hidden" name="period" value="<?= e((string) $period) ?>">
        <input type="hidden" name="date" value="<?= e((string) $referenceDate) ?>">
        <input type="hidden" name="appointment_id" id="quickRescheduleAppointmentId" value="">
        <input type="hidden" name="new_data_hora" id="quickRescheduleDateTime" value="">
    </form>

    <div id="rescheduleModalBackdrop" class="re-modal-backdrop" aria-hidden="true">
        <div class="re-modal" role="dialog" aria-modal="true" aria-labelledby="rescheduleModalTitle">
            <h4 id="rescheduleModalTitle">Reagendar consulta</h4>
            <p>Defina o novo dia e horário para concluir o reagendamento.</p>

            <div class="field">
                <label for="rescheduleDateTimeInput">Novo horário</label>
                <input type="datetime-local" id="rescheduleDateTimeInput" required>
            </div>

            <div class="re-modal-actions">
                <button type="button" id="rescheduleCancelBtn" class="btn-secondary" style="height:34px;padding:0 12px;">Cancelar</button>
                <button type="button" id="rescheduleConfirmBtn" style="height:34px;padding:0 12px;">Confirmar</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($period === 'month'): ?>
<script>
(function () {
    var dragged = null;
    var cells = document.querySelectorAll('.month-cell[data-day]');
    var events = document.querySelectorAll('.month-event[draggable="true"]');
    var form = document.getElementById('quickRescheduleForm');
    var idInput = document.getElementById('quickRescheduleAppointmentId');
    var dateInput = document.getElementById('quickRescheduleDateTime');
    var modalBackdrop = document.getElementById('rescheduleModalBackdrop');
    var modalInput = document.getElementById('rescheduleDateTimeInput');
    var modalConfirm = document.getElementById('rescheduleConfirmBtn');
    var modalCancel = document.getElementById('rescheduleCancelBtn');

    if (!form || !idInput || !dateInput || !events.length || !cells.length || !modalBackdrop || !modalInput || !modalConfirm || !modalCancel) {
        return;
    }

    function openModal(defaultDateTime) {
        modalInput.value = defaultDateTime;
        modalBackdrop.classList.add('open');
        modalBackdrop.setAttribute('aria-hidden', 'false');
        window.setTimeout(function () {
            modalInput.focus();
        }, 0);
    }

    function closeModal() {
        modalBackdrop.classList.remove('open');
        modalBackdrop.setAttribute('aria-hidden', 'true');
    }

    modalCancel.addEventListener('click', function () {
        closeModal();
    });

    modalBackdrop.addEventListener('click', function (event) {
        if (event.target === modalBackdrop) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modalBackdrop.classList.contains('open')) {
            closeModal();
        }
    });

    modalConfirm.addEventListener('click', function () {
        var normalized = (modalInput.value || '').trim();
        if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(normalized)) {
            window.alert('Formato inválido. Use AAAA-MM-DDTHH:MM');
            return;
        }

        dateInput.value = normalized;
        closeModal();
        form.submit();
    });

    events.forEach(function (item) {
        item.addEventListener('dragstart', function (event) {
            dragged = {
                id: item.getAttribute('data-appointment-id') || '',
                currentDateTime: item.getAttribute('data-current-datetime') || ''
            };

            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', dragged.id);
            }
        });
    });

    cells.forEach(function (cell) {
        cell.addEventListener('dragover', function (event) {
            event.preventDefault();
            cell.classList.add('drop-hover');
            if (event.dataTransfer) {
                event.dataTransfer.dropEffect = 'move';
            }
        });

        cell.addEventListener('dragleave', function () {
            cell.classList.remove('drop-hover');
        });

        cell.addEventListener('drop', function (event) {
            event.preventDefault();
            cell.classList.remove('drop-hover');

            if (!dragged || !dragged.id || !dragged.currentDateTime) {
                return;
            }

            var targetDay = cell.getAttribute('data-day');
            if (!targetDay) {
                return;
            }

            var currentParts = dragged.currentDateTime.split(' ');
            var currentTime = (currentParts[1] || '09:00:00').substring(0, 5);
            var suggested = targetDay + 'T' + currentTime;

            idInput.value = dragged.id;
            openModal(suggested);
        });
    });
}());
</script>
<?php endif; ?>

<div class="card">
    <h3 class="card-title">Visão rápida por dia</h3>
    <?php if (empty($appointmentsByDay)): ?>
        <div class="muted">Nenhuma consulta encontrada neste período.</div>
    <?php else: ?>
        <?php foreach ($appointmentsByDay as $day => $list): ?>
            <div class="day-block">
                <div class="day-title"><?= e(date('d/m/Y (D)', strtotime((string) $day))) ?> · <?= e((string) count($list)) ?> consulta(s)</div>
                <div class="mini-list">
                    <?php foreach ($list as $appointment): ?>
                        <?php $aStatus = (string) $appointment['status']; ?>
                        <div class="mini-item">
                            <div class="mini-time"><?= e(date('H:i', strtotime((string) $appointment['data_hora']))) ?></div>
                            <div class="mini-main">
                                <strong><?= e((string) $appointment['paciente_nome']) ?></strong>
                                <span><?= e((string) $appointment['procedimento']) ?></span>
                            </div>
                            <span class="status-badge appt-<?= e($aStatus) ?>"><?= e(ucfirst($aStatus)) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="card">
    <h3 class="card-title">Novo agendamento</h3>
    <form method="post" action="<?= e(base_url('route=appointments')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <div class="field">
            <label>Paciente</label>
            <select name="patient_id" required>
                <option value="">Selecione o paciente</option>
                <?php foreach ($patients as $patient): ?>
                    <option value="<?= e((string) $patient['id']) ?>"><?= e((string) $patient['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label>Data e hora</label>
            <input type="datetime-local" name="data_hora" required>
        </div>
        <div class="field">
            <label>Procedimento</label>
            <input type="text" name="procedimento" placeholder="Procedimento" required>
        </div>
        <div class="field">
            <label>Recorrência</label>
            <select name="recurrence_enabled" id="recurrenceEnabledSelect">
                <option value="0" selected>Não</option>
                <option value="1">Sim</option>
            </select>
        </div>
        <div class="field" id="recurrenceFrequencyField" style="display:none;">
            <label>Frequência</label>
            <select name="recurrence_frequency">
                <option value="weekly" selected>Semanal</option>
                <option value="monthly">Mensal</option>
            </select>
        </div>
        <div class="field" id="recurrenceCountField" style="display:none;">
            <label>Quantidade</label>
            <input type="number" name="recurrence_count" min="1" max="12" value="4">
        </div>
        <div class="field" style="justify-content:flex-end;">
            <label>&nbsp;</label>
            <button type="submit">Agendar</button>
        </div>
    </form>
</div>

<script>
(function () {
    var enabledSelect = document.getElementById('recurrenceEnabledSelect');
    var frequencyField = document.getElementById('recurrenceFrequencyField');
    var countField = document.getElementById('recurrenceCountField');

    if (!enabledSelect || !frequencyField || !countField) {
        return;
    }

    function toggleRecurrenceFields() {
        var enabled = enabledSelect.value === '1';
        frequencyField.style.display = enabled ? '' : 'none';
        countField.style.display = enabled ? '' : 'none';
    }

    enabledSelect.addEventListener('change', toggleRecurrenceFields);
    toggleRecurrenceFields();
}());
</script>

<div class="card">
    <h3 class="card-title">Detalhamento completo</h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Paciente</th>
                    <th>Data/Hora</th>
                    <th>Status</th>
                    <th>Procedimento</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($appointments)): ?>
                    <tr><td colspan="5" class="muted">Nenhuma consulta no período selecionado.</td></tr>
                <?php else: ?>
                    <?php foreach ($appointments as $appointment): ?>
                        <tr>
                            <td><?= e((string) $appointment['paciente_nome']) ?></td>
                            <td><?= e((string) $appointment['data_hora']) ?></td>
                            <td>
                                <?php $aStatus = (string) $appointment['status']; ?>
                                <span class="status-badge appt-<?= e($aStatus) ?>"><?= e(ucfirst($aStatus)) ?></span>
                            </td>
                            <td><?= e((string) $appointment['procedimento']) ?></td>
                            <td>
                                <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                                    <a href="<?= e(base_url('route=appointments&action=edit&appointment_id=' . $appointment['id'])) ?>"
                                       class="btn-secondary"
                                       style="display:inline-flex;align-items:center;height:32px;padding:0 10px;border-radius:8px;color:#fff;text-decoration:none;font-size:12px;font-weight:600;">Editar</a>
                                    <form method="post" action="<?= e(base_url('route=appointments')) ?>" class="inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="status">
                                        <input type="hidden" name="appointment_id" value="<?= e((string) $appointment['id']) ?>">
                                        <select name="status" style="height:32px;font-size:12px;padding:4px 8px;">
                                            <option value="agendada" <?= $appointment['status'] === 'agendada' ? 'selected' : '' ?>>Agendada</option>
                                            <option value="confirmada" <?= $appointment['status'] === 'confirmada' ? 'selected' : '' ?>>Confirmada</option>
                                            <option value="realizada" <?= $appointment['status'] === 'realizada' ? 'selected' : '' ?>>Realizada</option>
                                            <option value="cancelada" <?= $appointment['status'] === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                                            <option value="faltou" <?= $appointment['status'] === 'faltou' ? 'selected' : '' ?>>Faltou</option>
                                            <option value="reagendada" <?= $appointment['status'] === 'reagendada' ? 'selected' : '' ?>>Reagendada</option>
                                        </select>
                                        <button type="submit" class="btn-secondary" style="height:32px;padding:0 10px;font-size:12px;">OK</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

