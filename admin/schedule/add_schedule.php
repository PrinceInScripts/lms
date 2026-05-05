<?php
define('GYANSETU_APP', true);
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/db_conn.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/activity_logger.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db     = getDB();
$B      = rtrim(BASE_URL, '/');
$errors = [];
$old    = [];

// Batches for dropdown
$batches = $db->query("SELECT batch_id, batch_name FROM batches WHERE status IN ('upcoming','ongoing') ORDER BY batch_name")->fetchAll();

if (isPost()) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token invalid. Please refresh.';
    } else {
        $old         = $_POST;
        $batchId     = trim($_POST['batch_id']    ?? '');
        $schedDate   = trim($_POST['schedule_date'] ?? '');
        $startTime   = trim($_POST['start_time']  ?? '');
        $endTime     = trim($_POST['end_time']    ?? '');
        $topic       = trim($_POST['topic']       ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($batchId))   $errors[] = 'Please select a batch.';
        if (empty($schedDate)) $errors[] = 'Date is required.';
        if (empty($startTime)) $errors[] = 'Start time is required.';
        if (empty($endTime))   $errors[] = 'End time is required.';
        if (empty($topic))     $errors[] = 'Topic is required.';
        if ($startTime && $endTime && $endTime <= $startTime) $errors[] = 'End time must be after start time.';

        // Validate batch exists
        if ($batchId) {
            $bChk = $db->prepare("SELECT 1 FROM batches WHERE batch_id = ?");
            $bChk->execute([$batchId]);
            if (!$bChk->fetchColumn()) $errors[] = 'Invalid batch selected.';
        }

        if (empty($errors)) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO schedule (batch_id, schedule_date, start_time, end_time, topic, description, created_by)
                    VALUES (?,?,?,?,?,?,?)
                ");
                $stmt->execute([$batchId, $schedDate, $startTime, $endTime, $topic, $description ?: null, currentUserId()]);

                logActivity('ADD_SCHEDULE', 'schedule', "Added schedule '{$topic}' for batch {$batchId} on {$schedDate}");
                setFlash('success', "Schedule <strong>{$topic}</strong> added for " . date('d M Y', strtotime($schedDate)) . ".");
                redirect($B . '/admin/schedule/schedule.php');
            } catch (Exception $e) {
                error_log('[GyanSetu] add_schedule: ' . $e->getMessage());
                $errors[] = 'Database error. Please try again.';
            }
        }
    }
}

$csrfToken   = generateCSRFToken();
$prefillBatch = $_GET['batch_id'] ?? '';
$prefillDate  = $_GET['date'] ?? date('Y-m-d');
?>
<?php include __DIR__ . '/../sidebar.php'; ?>
<style>
    body{background:#FEFDDF}
    input:focus,select:focus,textarea:focus{outline:none;border-color:#E87F24!important;box-shadow:0 0 0 3px rgba(232,127,36,.12)}
    .card{background:white;border-radius:20px;box-shadow:0 2px 16px rgba(0,0,0,.06)}
    .section-title{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#E87F24;border-bottom:2px solid #FFF3E0;padding-bottom:.6rem;margin-bottom:1.1rem}
    .btn-primary{background:linear-gradient(135deg,#E87F24,#FFC81E)}.btn-primary:hover{opacity:.9;transform:translateY(-1px)}
    .field-label{display:block;font-size:.875rem;font-weight:600;color:#374151;margin-bottom:.375rem}
    .field-input{width:100%;border:1px solid #e5e7eb;border-radius:.75rem;padding:.625rem 1rem;font-size:.875rem;transition:border-color .2s,box-shadow .2s}
</style>

<div>
    <div class="flex items-center gap-4 mb-6">
        <a href="<?= $B ?>/admin/schedule/schedule.php"
           class="w-9 h-9 flex items-center justify-center rounded-xl bg-white shadow text-gray-500 hover:text-[#E87F24] transition">
            <i class="ri-arrow-left-line"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Add Schedule</h1>
            <p class="text-sm text-gray-500">Schedule a new class or session</p>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="bg-red-50 border-l-4 border-red-400 rounded-xl p-4 mb-5">
        <p class="text-red-700 font-semibold text-sm flex items-center gap-2 mb-1"><i class="ri-error-warning-line"></i> Please fix:</p>
        <?php foreach ($errors as $e): ?><p class="text-red-600 text-sm pl-5">• <?= sanitize($e) ?></p><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="max-w-2xl">
        <div class="card p-7">
            <form method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                <div class="mb-5">
                    <label class="field-label">Batch <span class="text-red-500">*</span></label>
                    <select name="batch_id" class="field-input bg-white">
                        <option value="">— Select a batch —</option>
                        <?php foreach ($batches as $b): ?>
                            <option value="<?= sanitize($b['batch_id']) ?>"
                                <?= ($old['batch_id'] ?? $prefillBatch) === $b['batch_id'] ? 'selected' : '' ?>>
                                <?= sanitize($b['batch_name']) ?> (<?= sanitize($b['batch_id']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-5">
                    <label class="field-label">Topic / Title <span class="text-red-500">*</span></label>
                    <input type="text" name="topic" maxlength="255"
                           value="<?= sanitize($old['topic'] ?? '') ?>"
                           class="field-input" placeholder="e.g. Chapter 5 — Limits & Continuity">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
                    <div>
                        <label class="field-label">Date <span class="text-red-500">*</span></label>
                        <input type="date" name="schedule_date"
                               value="<?= sanitize($old['schedule_date'] ?? $prefillDate) ?>"
                               class="field-input">
                    </div>
                    <div>
                        <label class="field-label">Start Time <span class="text-red-500">*</span></label>
                        <input type="time" name="start_time"
                               value="<?= sanitize($old['start_time'] ?? '') ?>"
                               class="field-input">
                    </div>
                    <div>
                        <label class="field-label">End Time <span class="text-red-500">*</span></label>
                        <input type="time" name="end_time"
                               value="<?= sanitize($old['end_time'] ?? '') ?>"
                               class="field-input">
                    </div>
                </div>

                <div class="mb-6">
                    <label class="field-label">Description / Notes</label>
                    <textarea name="description" rows="3"
                              class="field-input resize-none"
                              placeholder="Optional notes about this session…"><?= sanitize($old['description'] ?? '') ?></textarea>
                </div>

                <div class="flex gap-3">
                    <button type="submit"
                            class="btn-primary flex-1 py-3 rounded-xl text-white font-semibold shadow transition flex items-center justify-center gap-2">
                        <i class="ri-calendar-check-line"></i> Add Schedule
                    </button>
                    <a href="<?= $B ?>/admin/schedule/schedule.php"
                       class="px-6 py-3 rounded-xl border border-gray-200 text-gray-600 text-sm font-medium hover:bg-gray-50 transition text-center">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
</div></div>
