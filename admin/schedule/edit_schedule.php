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
$id = sanitizeInt($_GET['id'] ?? 0);
$errors = [];

if (!$id) { setFlash('error','Invalid schedule.'); redirect($B.'/admin/schedule/schedule.php'); }

$row = $db->prepare("SELECT s.*, b.batch_name FROM schedule s JOIN batches b ON b.batch_id = s.batch_id WHERE s.id = ?");
$row->execute([$id]);
$sched = $row->fetch();
if (!$sched) { setFlash('error','Schedule not found.'); redirect($B.'/admin/schedule/schedule.php'); }

$batches = $db->query("SELECT batch_id, batch_name FROM batches WHERE status IN ('upcoming','ongoing') ORDER BY batch_name")->fetchAll();

if (isPost()) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token invalid.';
    } else {
        $batchId     = trim($_POST['batch_id']     ?? '');
        $schedDate   = trim($_POST['schedule_date'] ?? '');
        $startTime   = trim($_POST['start_time']   ?? '');
        $endTime     = trim($_POST['end_time']     ?? '');
        $topic       = trim($_POST['topic']        ?? '');
        $description = trim($_POST['description']  ?? '');

        if (empty($batchId))   $errors[] = 'Please select a batch.';
        if (empty($schedDate)) $errors[] = 'Date is required.';
        if (empty($startTime)) $errors[] = 'Start time is required.';
        if (empty($endTime))   $errors[] = 'End time is required.';
        if (empty($topic))     $errors[] = 'Topic is required.';
        if ($startTime && $endTime && $endTime <= $startTime) $errors[] = 'End time must be after start time.';

        if (empty($errors)) {
            $old = ['topic'=>$sched['topic'],'date'=>$sched['schedule_date'],'batch'=>$sched['batch_id']];
            $db->prepare("UPDATE schedule SET batch_id=?,schedule_date=?,start_time=?,end_time=?,topic=?,description=? WHERE id=?")
               ->execute([$batchId,$schedDate,$startTime,$endTime,$topic,$description?:null,$id]);

            logActivity('UPDATE_SCHEDULE','schedule',"Updated schedule '{$topic}' (ID:{$id})",$old,['topic'=>$topic,'date'=>$schedDate]);
            setFlash('success',"Schedule <strong>{$topic}</strong> updated.");
            redirect($B.'/admin/schedule/schedule.php');
        }
    }
}

$csrfToken = generateCSRFToken();
?>
<?php include __DIR__ . '/../sidebar.php'; ?>
<style>
    body{background:#FEFDDF}
    input:focus,select:focus,textarea:focus{outline:none;border-color:#E87F24!important;box-shadow:0 0 0 3px rgba(232,127,36,.12)}
    .card{background:white;border-radius:20px;box-shadow:0 2px 16px rgba(0,0,0,.06)}
    .btn-primary{background:linear-gradient(135deg,#E87F24,#FFC81E)}.btn-primary:hover{opacity:.9;transform:translateY(-1px)}
    .field-label{display:block;font-size:.875rem;font-weight:600;color:#374151;margin-bottom:.375rem}
    .field-input{width:100%;border:1px solid #e5e7eb;border-radius:.75rem;padding:.625rem 1rem;font-size:.875rem;transition:border-color .2s}
</style>

<div>
    <div class="flex items-center gap-4 mb-6">
        <a href="<?= $B ?>/admin/schedule/schedule.php"
           class="w-9 h-9 flex items-center justify-center rounded-xl bg-white shadow text-gray-500 hover:text-[#E87F24] transition">
            <i class="ri-arrow-left-line"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Edit Schedule</h1>
            <p class="text-sm text-gray-500"><?= sanitize($sched['topic']) ?> · <?= date('d M Y', strtotime($sched['schedule_date'])) ?></p>
        </div>
    </div>

    <?php renderFlashMessages(); ?>

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
                        <?php foreach ($batches as $b): ?>
                            <option value="<?= sanitize($b['batch_id']) ?>"
                                <?= ($b['batch_id'] === ($_POST['batch_id'] ?? $sched['batch_id'])) ? 'selected' : '' ?>>
                                <?= sanitize($b['batch_name']) ?> (<?= sanitize($b['batch_id']) ?>)
                            </option>
                        <?php endforeach; ?>
                        <!-- Include current batch even if status changed -->
                        <?php
                        $found = array_filter($batches, fn($b) => $b['batch_id'] === $sched['batch_id']);
                        if (empty($found)): ?>
                            <option value="<?= sanitize($sched['batch_id']) ?>" selected><?= sanitize($sched['batch_name']) ?></option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="mb-5">
                    <label class="field-label">Topic / Title <span class="text-red-500">*</span></label>
                    <input type="text" name="topic" maxlength="255"
                           value="<?= sanitize($_POST['topic'] ?? $sched['topic']) ?>"
                           class="field-input">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
                    <div>
                        <label class="field-label">Date <span class="text-red-500">*</span></label>
                        <input type="date" name="schedule_date"
                               value="<?= sanitize($_POST['schedule_date'] ?? $sched['schedule_date']) ?>"
                               class="field-input">
                    </div>
                    <div>
                        <label class="field-label">Start Time <span class="text-red-500">*</span></label>
                        <input type="time" name="start_time"
                               value="<?= sanitize($_POST['start_time'] ?? $sched['start_time']) ?>"
                               class="field-input">
                    </div>
                    <div>
                        <label class="field-label">End Time <span class="text-red-500">*</span></label>
                        <input type="time" name="end_time"
                               value="<?= sanitize($_POST['end_time'] ?? $sched['end_time']) ?>"
                               class="field-input">
                    </div>
                </div>

                <div class="mb-6">
                    <label class="field-label">Description / Notes</label>
                    <textarea name="description" rows="3" class="field-input resize-none"><?= sanitize($_POST['description'] ?? $sched['description']) ?></textarea>
                </div>

                <div class="flex gap-3">
                    <button type="submit"
                            class="btn-primary flex-1 py-3 rounded-xl text-white font-semibold shadow transition flex items-center justify-center gap-2">
                        <i class="ri-save-line"></i> Save Changes
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
