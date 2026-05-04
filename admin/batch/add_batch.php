<?php
define('GYANSETU_APP', true);
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/db_conn.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/activity_logger.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db     = getDB();
$errors = [];
$old    = [];

// ── Generate unique batch_id ───────────────────────────────────────────────
function generateBatchId(PDO $db): string {
    do {
        $num       = random_int(100, 999);
        $candidate = 'BAT' . $num;
        $chk       = $db->prepare("SELECT 1 FROM batches WHERE batch_id = ?");
        $chk->execute([$candidate]);
    } while ($chk->fetchColumn());
    return $candidate;
}

// ── POST handler ───────────────────────────────────────────────────────────
if (isPost()) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token invalid. Please refresh and try again.';
    } else {
        $old         = $_POST;
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

        // Validate
        if (empty($batchName))              $errors[] = 'Batch name is required.';
        if (strlen($batchName) > 100)       $errors[] = 'Batch name too long (max 100 chars).';
        if (empty($startDate))              $errors[] = 'Start date is required.';
        if (empty($endDate))                $errors[] = 'End date is required.';
        if ($startDate && $endDate && $endDate < $startDate) $errors[] = 'End date must be after start date.';
        if (!in_array($mode, ['online','offline','hybrid'])) $errors[] = 'Invalid mode.';
        if (!in_array($status, ['upcoming','ongoing','completed','cancelled'])) $errors[] = 'Invalid status.';
        if ($meetingLink && !filter_var($meetingLink, FILTER_VALIDATE_URL)) $errors[] = 'Meeting link must be a valid URL.';
        if ($maxStudents !== null && $maxStudents < 1) $errors[] = 'Max students must be at least 1.';

        if (empty($errors)) {
            try {
                $batchId = generateBatchId($db);

                $stmt = $db->prepare("
                    INSERT INTO batches
                        (batch_id, batch_name, academic_year, start_date, end_date,
                         time_slot, platform, meeting_link, max_students,
                         mode, status, created_by)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
                ");
                $stmt->execute([
                    $batchId, $batchName, $acYear ?: null,
                    $startDate, $endDate,
                    $timeSlot ?: null, $platform ?: null, $meetingLink ?: null,
                    $maxStudents, $mode, $status,
                    currentUserId(),
                ]);

                logActivity('ADD_BATCH', 'batches',
                    "Created batch '{$batchName}' (ID: {$batchId})");

                setFlash('success', "Batch {$batchName} created with ID {$batchId}.");
                redirect(BASE_URL . '/admin/batch/batches.php');

            } catch (Exception $e) {
                error_log('[GyanSetu] add_batch: ' . $e->getMessage());
                $errors[] = 'Database error. Please try again.';
            }
        }
    }
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Batch — GyanSetu</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family:'Inter',sans-serif; }
        body { background:#FEFDDF; }
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
            <a href="batches.php" class="w-9 h-9 flex items-center justify-center rounded-xl bg-white shadow text-gray-500 hover:text-[#E87F24] transition">
                <i class="ri-arrow-left-line"></i>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Add New Batch</h1>
                <p class="text-sm text-gray-500">Fill in batch details to create a new class</p>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="bg-red-50 border-l-4 border-red-400 rounded-xl p-4 mb-6">
            <p class="text-red-700 font-semibold text-sm flex items-center gap-2 mb-1"><i class="ri-error-warning-line"></i> Please fix the following:</p>
            <?php foreach ($errors as $e): ?><p class="text-red-600 text-sm pl-5">• <?= sanitize($e) ?></p><?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- LEFT: Main -->
                <div class="lg:col-span-2 space-y-6">

                    <div class="card p-6">
                        <p class="section-title"><i class="ri-information-line mr-1"></i> Basic Information</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Batch Name <span class="text-red-500">*</span></label>
                                <input type="text" name="batch_name" maxlength="100"
                                       value="<?= sanitize($old['batch_name'] ?? '') ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm transition"
                                       placeholder="e.g. JEE Mains Batch 2025 — Shift A">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Academic Year</label>
                                <input type="text" name="academic_year"
                                       value="<?= sanitize($old['academic_year'] ?? date('Y') . '-' . (date('Y')+1)) ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm transition"
                                       placeholder="2025-2026">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Max Students</label>
                                <input type="number" name="max_students" min="1" max="500"
                                       value="<?= sanitize($old['max_students'] ?? '') ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm transition"
                                       placeholder="e.g. 30">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Start Date <span class="text-red-500">*</span></label>
                                <input type="date" name="start_date"
                                       value="<?= sanitize($old['start_date'] ?? '') ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm transition">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">End Date <span class="text-red-500">*</span></label>
                                <input type="date" name="end_date"
                                       value="<?= sanitize($old['end_date'] ?? '') ?>"
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
                                       value="<?= sanitize($old['time_slot'] ?? '') ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm transition"
                                       placeholder="e.g. Mon–Fri 7:00 AM – 9:00 AM">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Platform</label>
                                <input type="text" name="platform"
                                       value="<?= sanitize($old['platform'] ?? '') ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm transition"
                                       placeholder="e.g. Zoom, Google Meet, Classroom">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Meeting Link</label>
                                <input type="url" name="meeting_link"
                                       value="<?= sanitize($old['meeting_link'] ?? '') ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm transition"
                                       placeholder="https://meet.google.com/...">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT: Settings -->
                <div class="space-y-6">
                    <div class="card p-6">
                        <p class="section-title"><i class="ri-settings-line mr-1"></i> Settings</p>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Mode <span class="text-red-500">*</span></label>
                                <select name="mode" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-white transition">
                                    <?php foreach (['online'=>'Online','offline'=>'Offline','hybrid'=>'Hybrid'] as $v=>$l): ?>
                                        <option value="<?=$v?>" <?= ($old['mode']??'online')===$v?'selected':'' ?>><?=$l?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Status <span class="text-red-500">*</span></label>
                                <select name="status" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-white transition">
                                    <?php foreach (['upcoming'=>'Upcoming','ongoing'=>'Ongoing','completed'=>'Completed','cancelled'=>'Cancelled'] as $v=>$l): ?>
                                        <option value="<?=$v?>" <?= ($old['status']??'upcoming')===$v?'selected':'' ?>><?=$l?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="bg-orange-50 border border-orange-100 rounded-2xl p-4 text-sm text-orange-700">
                        <p class="font-semibold mb-1 flex items-center gap-1.5"><i class="ri-lightbulb-line"></i> Auto-generated</p>
                        <p class="text-xs text-orange-500">A unique Batch ID (e.g. BAT247) will be generated automatically.</p>
                    </div>

                    <div class="flex flex-col gap-3">
                        <button type="submit" class="btn-primary w-full py-3 rounded-xl text-white font-semibold shadow transition flex items-center justify-center gap-2">
                            <i class="ri-add-circle-line"></i> Create Batch
                        </button>
                        <a href="batches.php" class="w-full py-3 rounded-xl border border-gray-200 text-gray-600 text-sm font-medium text-center hover:bg-gray-50 transition">Cancel</a>
                    </div>
                </div>
            </div>
        </form>
    <!-- </main> -->
</body>
</html>
