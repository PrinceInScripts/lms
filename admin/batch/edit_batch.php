<?php
define('GYANSETU_APP', true);
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/db_conn.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/activity_logger.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db      = getDB();
$batchId = trim($_GET['id'] ?? '');
$errors  = [];

if (empty($batchId)) { setFlash('error','Invalid batch.'); redirect(BASE_URL.'/admin/batch/batches.php'); }

$batch = $db->prepare("SELECT * FROM batches WHERE batch_id = ?");
$batch->execute([$batchId]);
$batch = $batch->fetch();
if (!$batch) { setFlash('error','Batch not found.'); redirect(BASE_URL.'/admin/batch/batches.php'); }

// ── POST ───────────────────────────────────────────────────────────────────
if (isPost()) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token invalid.';
    } else {
        $batchName   = trim($_POST['batch_name']  ?? '');
        $acYear      = trim($_POST['academic_year'] ?? '');
        $startDate   = trim($_POST['start_date']  ?? '');
        $endDate     = trim($_POST['end_date']    ?? '');
        $timeSlot    = trim($_POST['time_slot']   ?? '');
        $platform    = trim($_POST['platform']    ?? '');
        $meetingLink = trim($_POST['meeting_link'] ?? '');
        $maxStudents = sanitizeInt($_POST['max_students'] ?? '');
        $mode        = $_POST['mode']   ?? 'online';
        $status      = $_POST['status'] ?? 'upcoming';

        if (empty($batchName))           $errors[] = 'Batch name is required.';
        if (empty($startDate))           $errors[] = 'Start date is required.';
        if (empty($endDate))             $errors[] = 'End date is required.';
        if ($endDate < $startDate)       $errors[] = 'End date must be after start date.';
        if ($meetingLink && !filter_var($meetingLink, FILTER_VALIDATE_URL)) $errors[] = 'Meeting link must be a valid URL.';
        if (!in_array($mode, ['online','offline','hybrid'])) $errors[] = 'Invalid mode.';
        if (!in_array($status, ['upcoming','ongoing','completed','cancelled'])) $errors[] = 'Invalid status.';

        if (empty($errors)) {
            $old = ['name'=>$batch['batch_name'],'status'=>$batch['status'],'start'=>$batch['start_date']];

            $stmt = $db->prepare("
                UPDATE batches SET
                    batch_name=?, academic_year=?, start_date=?, end_date=?,
                    time_slot=?, platform=?, meeting_link=?, max_students=?,
                    mode=?, status=?
                WHERE batch_id=?
            ");
            $stmt->execute([
                $batchName, $acYear ?: null, $startDate, $endDate,
                $timeSlot ?: null, $platform ?: null, $meetingLink ?: null,
                $maxStudents,
                $mode, $status, $batchId
            ]);

            logActivity('UPDATE_BATCH','batches',
                "Updated batch '{$batchName}' (ID: {$batchId})",
                $old, ['name'=>$batchName,'status'=>$status,'start'=>$startDate]);

            // Re-fetch
            $q = $db->prepare("SELECT * FROM batches WHERE batch_id = ?");
            $q->execute([$batchId]);
            $batch = $q->fetch();

            setFlash('success',"Batch {$batchName} updated.");
            redirect(BASE_URL.'/admin/batch/edit_batch.php?id='.urlencode($batchId));
        }
    }
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Batch — GyanSetu</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family:'Inter',sans-serif; } body { background:#FEFDDF; }
        input:focus,select:focus,textarea:focus { outline:none; border-color:#E87F24!important; box-shadow:0 0 0 3px rgba(232,127,36,.12); }
        .card { background:white; border-radius:20px; box-shadow:0 2px 16px rgba(0,0,0,.06); }
        .section-title { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:#E87F24; border-bottom:2px solid #FFF3E0; padding-bottom:.6rem; margin-bottom:1.1rem; }
        .btn-primary { background:linear-gradient(135deg,#E87F24,#FFC81E); }
        .btn-primary:hover { opacity:.9; transform:translateY(-1px); }
    </style>
</head>
<body class="flex">
    <?php include __DIR__ . '/../sidebar.php'; ?>
    <!-- <main class="flex-1 p-6 md:p-8 ml-0 md:ml-64 min-h-screen"> -->

        <div class="flex items-center gap-4 mb-8">
            <a href="batches.php" class="w-9 h-9 flex items-center justify-center rounded-xl bg-white shadow text-gray-500 hover:text-[#E87F24] transition"><i class="ri-arrow-left-line"></i></a>
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Edit Batch</h1>
                <p class="text-sm text-gray-500">
                    <span class="font-mono text-[#E87F24] font-semibold"><?= sanitize($batch['batch_id']) ?></span>
                    · <?= sanitize($batch['batch_name']) ?>
                </p>
            </div>
            <a href="view_batch.php?id=<?= urlencode($batchId) ?>"
               class="ml-auto inline-flex items-center gap-1.5 px-4 py-2 rounded-xl border border-[#73A5CA] text-[#73A5CA] text-sm font-medium hover:bg-blue-50 transition">
                <i class="ri-eye-line"></i> View
            </a>
        </div>

        <?php renderFlashMessages(); ?>

        <?php if (!empty($errors)): ?>
        <div class="bg-red-50 border-l-4 border-red-400 rounded-xl p-4 mb-6">
            <p class="text-red-700 font-semibold text-sm flex items-center gap-2 mb-1"><i class="ri-error-warning-line"></i> Please fix:</p>
            <?php foreach ($errors as $e): ?><p class="text-red-600 text-sm pl-5">• <?= sanitize($e) ?></p><?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-6">
                    <div class="card p-6">
                        <p class="section-title"><i class="ri-information-line mr-1"></i> Basic Information</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Batch Name <span class="text-red-500">*</span></label>
                                <input type="text" name="batch_name" maxlength="100"
                                       value="<?= sanitize($_POST['batch_name'] ?? $batch['batch_name']) ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm transition">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Academic Year</label>
                                <input type="text" name="academic_year"
                                       value="<?= sanitize($_POST['academic_year'] ?? $batch['academic_year']) ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm transition">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Max Students</label>
                                <input type="number" name="max_students" min="1"
                                       value="<?= sanitize($_POST['max_students'] ?? $batch['max_students']) ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm transition">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Start Date <span class="text-red-500">*</span></label>
                                <input type="date" name="start_date"
                                       value="<?= sanitize($_POST['start_date'] ?? $batch['start_date']) ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm transition">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">End Date <span class="text-red-500">*</span></label>
                                <input type="date" name="end_date"
                                       value="<?= sanitize($_POST['end_date'] ?? $batch['end_date']) ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm transition">
                            </div>
                        </div>
                    </div>
                    <div class="card p-6">
                        <p class="section-title"><i class="ri-links-line mr-1"></i> Platform & Schedule</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Time Slot</label>
                                <input type="text" name="time_slot"
                                       value="<?= sanitize($_POST['time_slot'] ?? $batch['time_slot']) ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm transition">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Platform</label>
                                <input type="text" name="platform"
                                       value="<?= sanitize($_POST['platform'] ?? $batch['platform']) ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm transition">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Meeting Link</label>
                                <input type="url" name="meeting_link"
                                       value="<?= sanitize($_POST['meeting_link'] ?? $batch['meeting_link']) ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm transition">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="card p-6">
                        <p class="section-title"><i class="ri-settings-line mr-1"></i> Settings</p>
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Batch ID</label>
                            <input type="text" value="<?= sanitize($batch['batch_id']) ?>" disabled
                                   class="w-full border border-gray-100 rounded-xl px-4 py-2.5 text-sm bg-gray-50 text-gray-500 font-mono">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Mode</label>
                            <select name="mode" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-white transition">
                                <?php foreach (['online'=>'Online','offline'=>'Offline','hybrid'=>'Hybrid'] as $v=>$l): ?>
                                    <option value="<?=$v?>" <?= ($v===($_POST['mode']??$batch['mode']))?'selected':'' ?>><?=$l?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Status</label>
                            <select name="status" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-white transition">
                                <?php foreach (['upcoming'=>'Upcoming','ongoing'=>'Ongoing','completed'=>'Completed','cancelled'=>'Cancelled'] as $v=>$l): ?>
                                    <option value="<?=$v?>" <?= ($v===($_POST['status']??$batch['status']))?'selected':'' ?>><?=$l?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="flex flex-col gap-3">
                        <button type="submit" class="btn-primary w-full py-3 rounded-xl text-white font-semibold shadow transition flex items-center justify-center gap-2">
                            <i class="ri-save-line"></i> Save Changes
                        </button>
                        <a href="batches.php" class="w-full py-3 rounded-xl border border-gray-200 text-gray-600 text-sm font-medium text-center hover:bg-gray-50 transition">Cancel</a>
                    </div>
                </div>
            </div>
        </form>
    <!-- </main> -->
</body>
</html>
