<?php
define('GYANSETU_APP', true);
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/db_conn.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/activity_logger.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db = getDB();
$B  = rtrim(BASE_URL, '/');

$selectedBatch    = trim($_GET['batch_id']    ?? '');
$selectedDate     = trim($_GET['date']        ?? date('Y-m-d'));
$selectedSchedule = sanitizeInt($_GET['schedule_id'] ?? 0);

$batches   = $db->query("SELECT batch_id, batch_name FROM batches WHERE status IN ('upcoming','ongoing') ORDER BY batch_name")->fetchAll();
$students  = [];
$existing  = [];
$schedules = [];
$saved     = false;

// Load schedules for selected batch
if ($selectedBatch) {
    $schedStmt = $db->prepare("
        SELECT id, topic, schedule_date, start_time
        FROM schedule
        WHERE batch_id = ? AND is_cancelled = 0 AND schedule_date <= CURDATE()
        ORDER BY schedule_date DESC, start_time DESC
        LIMIT 30
    ");
    $schedStmt->execute([$selectedBatch]);
    $schedules = $schedStmt->fetchAll();
}

// Load students if batch selected
if ($selectedBatch && $selectedDate) {
    $stStmt = $db->prepare("
        SELECT sb.student_id, sd.full_name, sd.phone
        FROM student_batches sb
        JOIN student_details sd ON sd.student_id = sb.student_id
        WHERE sb.batch_id = ? AND sb.status = 'active'
        ORDER BY sd.full_name
    ");
    $stStmt->execute([$selectedBatch]);
    $students = $stStmt->fetchAll();

    // Load existing attendance for this date
    $exStmt = $db->prepare("
        SELECT student_id, status
        FROM attendance
        WHERE batch_id = ? AND attendance_date = ?
    ");
    $exStmt->execute([$selectedBatch, $selectedDate]);
    foreach ($exStmt->fetchAll() as $row) {
        $existing[$row['student_id']] = $row['status'];
    }
}

// ── POST: Save attendance ──────────────────────────────────────────────────
$saveErrors = [];
if (isPost() && isset($_POST['save_attendance'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $saveErrors[] = 'Security token invalid. Please refresh.';
    } else {
        $batchId    = trim($_POST['batch_id']    ?? '');
        $markDate   = trim($_POST['mark_date']   ?? '');
        $scheduleId = sanitizeInt($_POST['schedule_id'] ?? 0) ?: null;
        $attendance = $_POST['attendance'] ?? [];

        if (empty($batchId))  $saveErrors[] = 'Batch is required.';
        if (empty($markDate)) $saveErrors[] = 'Date is required.';
        if (empty($attendance)) $saveErrors[] = 'No attendance data submitted.';

        if (empty($saveErrors)) {
            try {
                $db->beginTransaction();
                $inserted = 0; $updated = 0;

                foreach ($attendance as $stuId => $status) {
                    $stuId = trim($stuId);
                    if (!in_array($status, ['Present','Absent','Late'])) continue;

                    // UPSERT: DB has UNIQUE(student_id, batch_id, attendance_date)
                    $chk = $db->prepare("SELECT id FROM attendance WHERE student_id=? AND batch_id=? AND attendance_date=?");
                    $chk->execute([$stuId, $batchId, $markDate]);
                    $existingId = $chk->fetchColumn();

                    if ($existingId) {
                        $db->prepare("UPDATE attendance SET status=?,schedule_id=?,marked_by=?,marked_at=NOW() WHERE id=?")
                           ->execute([$status, $scheduleId, currentUserId(), $existingId]);
                        $updated++;
                    } else {
                        $db->prepare("
                            INSERT INTO attendance (student_id, batch_id, schedule_id, attendance_date, status, marked_by)
                            VALUES (?,?,?,?,?,?)
                        ")->execute([$stuId, $batchId, $scheduleId, $markDate, $status, currentUserId()]);
                        $inserted++;
                    }
                }

                $db->commit();
                logActivity('MARK_ATTENDANCE','attendance',
                    "Marked attendance for batch {$batchId} on {$markDate}: {$inserted} new, {$updated} updated");

                setFlash('success', "Attendance saved — <strong>{$inserted} marked</strong>, <strong>{$updated} updated</strong>.");

                // Reload page with same filters to show updated state
                redirect($B . "/admin/attendance/attendance.php?batch_id=" . urlencode($batchId) . "&date=" . urlencode($markDate) . ($scheduleId ? "&schedule_id=$scheduleId" : ''));

            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                error_log('[GyanSetu] attendance: ' . $e->getMessage());
                $saveErrors[] = 'Database error. Please try again.';
            }
        }
    }
}

$csrfToken = generateCSRFToken();

// Stats from existing
$statPresent  = count(array_filter($existing, fn($v) => $v === 'Present'));
$statAbsent   = count(array_filter($existing, fn($v) => $v === 'Absent'));
$statLate     = count(array_filter($existing, fn($v) => $v === 'Late'));
$totalStudents = count($students);
?>
<?php include __DIR__ . '/../sidebar.php'; ?>
<style>
    body{background:#FEFDDF}
    .btn-primary{background:linear-gradient(135deg,#E87F24,#FFC81E);transition:all .2s}.btn-primary:hover{opacity:.9;transform:translateY(-1px)}
    .card{background:white;border-radius:20px;box-shadow:0 2px 16px rgba(0,0,0,.06)}
    input:focus,select:focus{outline:none;border-color:#E87F24!important;box-shadow:0 0 0 3px rgba(232,127,36,.12)}
    .att-row{transition:background .15s}
    .att-row:hover{background:#fff8f0}
    .radio-label{display:flex;align-items:center;gap:6px;padding:6px 12px;border-radius:10px;cursor:pointer;font-size:.8rem;font-weight:600;border:2px solid transparent;transition:all .2s;user-select:none}
    input[type=radio]:checked + .radio-label.present{background:#dcfce7;border-color:#22c55e;color:#166534}
    input[type=radio]:checked + .radio-label.absent{background:#fee2e2;border-color:#ef4444;color:#991b1b}
    input[type=radio]:checked + .radio-label.late{background:#fef9c3;border-color:#eab308;color:#713f12}
    .radio-label:hover{background:#f3f4f6}
    input[type=radio]{display:none}
    .field-label{display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.3rem}
    .field-input{width:100%;border:1px solid #e5e7eb;border-radius:.75rem;padding:.6rem 1rem;font-size:.875rem;transition:border-color .2s}
</style>

<div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Mark Attendance</h1>
            <p class="text-sm text-gray-500">Select a batch and date, then mark each student's status</p>
        </div>
        <a href="<?= $B ?>/admin/attendance/view_attendance.php"
           class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-[#73A5CA] text-[#73A5CA] text-sm font-semibold hover:bg-blue-50 transition">
            <i class="ri-bar-chart-line"></i> View Records
        </a>
    </div>

    <?php renderFlashMessages(); ?>

    <?php if (!empty($saveErrors)): ?>
    <div class="bg-red-50 border-l-4 border-red-400 rounded-xl p-4 mb-5">
        <?php foreach ($saveErrors as $e): ?><p class="text-red-600 text-sm">• <?= sanitize($e) ?></p><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Filter bar -->
    <div class="card p-5 mb-5">
        <form method="GET" class="flex flex-wrap gap-4 items-end">
            <div class="flex-1 min-w-[180px]">
                <label class="field-label">Batch</label>
                <select name="batch_id" class="field-input bg-white" onchange="this.form.submit()">
                    <option value="">— Select Batch —</option>
                    <?php foreach ($batches as $b): ?>
                        <option value="<?= sanitize($b['batch_id']) ?>" <?= $selectedBatch === $b['batch_id'] ? 'selected' : '' ?>>
                            <?= sanitize($b['batch_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="field-label">Date</label>
                <input type="date" name="date" value="<?= sanitize($selectedDate) ?>"
                       max="<?= date('Y-m-d') ?>"
                       class="field-input" onchange="this.form.submit()">
            </div>
            <?php if ($schedules): ?>
            <div class="flex-1 min-w-[200px]">
                <label class="field-label">Link to Schedule (optional)</label>
                <select name="schedule_id" class="field-input bg-white">
                    <option value="">— None —</option>
                    <?php foreach ($schedules as $sc): ?>
                        <option value="<?= $sc['id'] ?>" <?= $selectedSchedule === (int)$sc['id'] ? 'selected' : '' ?>>
                            <?= date('d M', strtotime($sc['schedule_date'])) ?> · <?= sanitize($sc['topic']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <?php if ($selectedBatch && $selectedDate): ?>
            <button type="submit" class="btn-primary px-5 py-2.5 rounded-xl text-white text-sm font-semibold shadow">Apply</button>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($selectedBatch && $selectedDate): ?>

    <!-- Stats -->
    <?php if (!empty($existing)): ?>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
        <div class="card p-4 text-center">
            <p class="text-xs text-gray-400">Total Students</p>
            <p class="text-2xl font-bold text-gray-700 mt-1"><?= $totalStudents ?></p>
        </div>
        <div class="card p-4 text-center">
            <p class="text-xs text-gray-400">Present</p>
            <p class="text-2xl font-bold text-green-600 mt-1"><?= $statPresent ?></p>
        </div>
        <div class="card p-4 text-center">
            <p class="text-xs text-gray-400">Absent</p>
            <p class="text-2xl font-bold text-red-500 mt-1"><?= $statAbsent ?></p>
        </div>
        <div class="card p-4 text-center">
            <p class="text-xs text-gray-400">Late</p>
            <p class="text-2xl font-bold text-yellow-600 mt-1"><?= $statLate ?></p>
        </div>
    </div>
    <?php endif; ?>

    <?php if (empty($students)): ?>
        <div class="card p-12 text-center text-gray-400">
            <i class="ri-user-search-line text-5xl mb-3 text-[#E87F24] opacity-40"></i>
            <p class="font-medium">No active students in this batch</p>
            <a href="<?= $B ?>/admin/batch/assign_student.php?batch_id=<?= urlencode($selectedBatch) ?>"
               class="mt-2 inline-block text-sm text-[#E87F24] hover:underline">Enroll students first</a>
        </div>
    <?php else: ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="save_attendance" value="1">
        <input type="hidden" name="batch_id"   value="<?= sanitize($selectedBatch) ?>">
        <input type="hidden" name="mark_date"  value="<?= sanitize($selectedDate) ?>">
        <input type="hidden" name="schedule_id" value="<?= $selectedSchedule ?>">

        <div class="card overflow-hidden mb-5">
            <!-- Bulk actions -->
            <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50 flex flex-wrap items-center gap-3">
                <span class="text-sm font-semibold text-gray-600">Mark all as:</span>
                <button type="button" onclick="markAll('Present')"
                        class="px-3 py-1.5 bg-green-100 hover:bg-green-200 text-green-700 text-xs font-semibold rounded-lg transition">
                    ✓ All Present
                </button>
                <button type="button" onclick="markAll('Absent')"
                        class="px-3 py-1.5 bg-red-100 hover:bg-red-200 text-red-700 text-xs font-semibold rounded-lg transition">
                    ✗ All Absent
                </button>
                <span class="ml-auto text-xs text-gray-400"><?= $totalStudents ?> students · <?= date('d M Y', strtotime($selectedDate)) ?></span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gradient-to-r from-[#E87F24] to-[#FFC81E] text-white">
                            <th class="px-5 py-3 text-left font-semibold">#</th>
                            <th class="px-5 py-3 text-left font-semibold">Student</th>
                            <th class="px-5 py-3 text-left font-semibold">ID</th>
                            <th class="px-5 py-3 text-center font-semibold">Attendance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php foreach ($students as $i => $st): ?>
                        <?php $currentStatus = $existing[$st['student_id']] ?? 'Present'; ?>
                        <tr class="att-row" id="row-<?= $i ?>">
                            <td class="px-5 py-3 text-gray-400 text-xs"><?= $i + 1 ?></td>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-[#E87F24] to-[#FFC81E] flex items-center justify-center text-white font-bold text-xs flex-shrink-0">
                                        <?= strtoupper(mb_substr($st['full_name'], 0, 1)) ?>
                                    </div>
                                    <p class="font-semibold text-gray-800"><?= sanitize($st['full_name']) ?></p>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <span class="font-mono text-xs bg-orange-50 text-[#E87F24] px-2 py-0.5 rounded-lg font-semibold">
                                    <?= sanitize($st['student_id']) ?>
                                </span>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-center gap-2">
                                    <?php foreach (['Present','Absent','Late'] as $status): ?>
                                    <?php $statusLc = strtolower($status); ?>
                                    <input type="radio"
                                           name="attendance[<?= htmlspecialchars($st['student_id']) ?>]"
                                           value="<?= $status ?>"
                                           id="att_<?= $i ?>_<?= $statusLc ?>"
                                           <?= $currentStatus === $status ? 'checked' : '' ?>
                                           onchange="updateRowBg(<?= $i ?>, '<?= $status ?>')">
                                    <label for="att_<?= $i ?>_<?= $statusLc ?>"
                                           class="radio-label <?= $statusLc ?>"
                                           style="<?= $currentStatus === $status ? getInlineStyle($status) : '' ?>">
                                        <?php if ($status === 'Present'): ?>
                                            <i class="ri-check-line text-sm"></i> Present
                                        <?php elseif ($status === 'Absent'): ?>
                                            <i class="ri-close-line text-sm"></i> Absent
                                        <?php else: ?>
                                            <i class="ri-time-line text-sm"></i> Late
                                        <?php endif; ?>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="btn-primary px-8 py-3 rounded-xl text-white font-semibold shadow transition flex items-center gap-2">
                <i class="ri-save-line"></i> Save Attendance
            </button>
            <a href="<?= $B ?>/admin/attendance/view_attendance.php?batch_id=<?= urlencode($selectedBatch) ?>&date=<?= urlencode($selectedDate) ?>"
               class="px-6 py-3 rounded-xl border border-[#73A5CA] text-[#73A5CA] text-sm font-semibold hover:bg-blue-50 transition">
                View Report
            </a>
        </div>
    </form>
    <?php endif; ?>
    <?php endif; ?>
</div>
</div></div>

<script>
function getStatusBg(status){
    if(status==='Present')return '#f0fdf4';
    if(status==='Absent') return '#fef2f2';
    if(status==='Late')   return '#fefce8';
    return '';
}
function updateRowBg(idx, status) {
    document.getElementById('row-'+idx).style.background = getStatusBg(status);
    // Update label styles
    var labels = document.getElementById('row-'+idx).querySelectorAll('.radio-label');
    var styles = {Present:'background:#dcfce7;border-color:#22c55e;color:#166534',Absent:'background:#fee2e2;border-color:#ef4444;color:#991b1b',Late:'background:#fef9c3;border-color:#eab308;color:#713f12'};
    labels.forEach(function(lbl){
        var s = lbl.classList.contains('present')?'Present':(lbl.classList.contains('absent')?'Absent':'Late');
        lbl.style.cssText = (s===status) ? styles[status] : '';
    });
    // Update live stats
    updateStats();
}
function markAll(status){
    document.querySelectorAll('input[type=radio][value="'+status+'"]').forEach(function(r, i){
        r.checked = true;
        var rowIdx = Math.floor(i);
        updateRowBg(rowIdx, status);
    });
    // Re-scan by row index properly
    var rows = document.querySelectorAll('.att-row');
    rows.forEach(function(row, idx){
        row.style.background = getStatusBg(status);
        row.querySelectorAll('.radio-label').forEach(function(lbl){
            var s = lbl.classList.contains('present')?'Present':(lbl.classList.contains('absent')?'Absent':'Late');
            var styles = {Present:'background:#dcfce7;border-color:#22c55e;color:#166534',Absent:'background:#fee2e2;border-color:#ef4444;color:#991b1b',Late:'background:#fef9c3;border-color:#eab308;color:#713f12'};
            lbl.style.cssText = (s===status) ? styles[status] : '';
        });
    });
    updateStats();
}
function updateStats(){
    var present=0,absent=0,late=0;
    document.querySelectorAll('input[type=radio]:checked').forEach(function(r){
        if(r.value==='Present')present++;
        else if(r.value==='Absent')absent++;
        else if(r.value==='Late')late++;
    });
}
// Apply initial row backgrounds from existing data
document.querySelectorAll('.att-row').forEach(function(row, idx){
    var checked = row.querySelector('input[type=radio]:checked');
    if(checked){ row.style.background = getStatusBg(checked.value); }
});

<?php
function getInlineStyle(string $status): string {
    return match($status) {
        'Present' => 'background:#dcfce7;border-color:#22c55e;color:#166534',
        'Absent'  => 'background:#fee2e2;border-color:#ef4444;color:#991b1b',
        'Late'    => 'background:#fef9c3;border-color:#eab308;color:#713f12',
        default   => ''
    };
}
?>
</script>
