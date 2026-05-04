<?php
define('GYANSETU_APP', true);
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/db_conn.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/activity_logger.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db         = getDB();
$errors     = [];
$prefillBatch = trim($_GET['batch_id'] ?? '');

// Load active batches for dropdown
$batchList = $db->query("
    SELECT batch_id, batch_name, current_enrollment, max_students, status
    FROM batches
    WHERE status IN ('upcoming','ongoing')
    ORDER BY batch_name
")->fetchAll();

// Load active students for dropdown (not already in this batch if pre-filled)
$studentList = $db->query("
    SELECT sd.student_id, sd.full_name, sd.stream
    FROM student_details sd
    JOIN users u ON u.id = sd.user_id
    WHERE u.status = 'active' AND sd.current_status = 'active'
    ORDER BY sd.full_name
")->fetchAll();

// ── POST ───────────────────────────────────────────────────────────────────
if (isPost()) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token invalid.';
    } else {
        $studentId = trim($_POST['student_id'] ?? '');
        $batchId   = trim($_POST['batch_id']   ?? '');

        if (empty($studentId)) $errors[] = 'Please select a student.';
        if (empty($batchId))   $errors[] = 'Please select a batch.';

        if (empty($errors)) {
            // Validate student exists
            $chkS = $db->prepare("SELECT student_id, full_name FROM student_details WHERE student_id = ?");
            $chkS->execute([$studentId]);
            $studentRow = $chkS->fetch();
            if (!$studentRow) $errors[] = 'Student not found.';

            // Validate batch exists + is enrollable
            $chkB = $db->prepare("SELECT batch_id, batch_name, max_students, current_enrollment, status FROM batches WHERE batch_id = ?");
            $chkB->execute([$batchId]);
            $batchRow = $chkB->fetch();
            if (!$batchRow) {
                $errors[] = 'Batch not found.';
            } elseif (!in_array($batchRow['status'], ['upcoming','ongoing'])) {
                $errors[] = 'You can only enroll students in upcoming or ongoing batches.';
            }

            // Capacity check
            if (empty($errors) && $batchRow['max_students'] > 0
                && $batchRow['current_enrollment'] >= $batchRow['max_students']) {
                $errors[] = "Batch <strong>{$batchRow['batch_name']}</strong> is full ({$batchRow['max_students']} students).";
            }

            // Duplicate check (active enrollment)
            if (empty($errors)) {
                $dup = $db->prepare("
                    SELECT id FROM student_batches
                    WHERE student_id = ? AND batch_id = ? AND status = 'active'
                ");
                $dup->execute([$studentId, $batchId]);
                if ($dup->fetch()) {
                    $errors[] = "<strong>{$studentRow['full_name']}</strong> is already actively enrolled in this batch.";
                }
            }
        }

        if (empty($errors)) {
            try {
                $db->beginTransaction();

                // Check if a previous (dropped/completed) record exists — upsert
                $existing = $db->prepare("SELECT id FROM student_batches WHERE student_id = ? AND batch_id = ?");
                $existing->execute([$studentId, $batchId]);
                $existRow = $existing->fetch();

                if ($existRow) {
                    // Re-activate previous record
                    $db->prepare("UPDATE student_batches SET status = 'active', enrolled_at = NOW(), enrolled_by = ? WHERE id = ?")
                       ->execute([currentUserId(), $existRow['id']]);
                } else {
                    // Fresh insert — DB has UNIQUE(student_id, batch_id) which prevents hard dupes
                    $db->prepare("
                        INSERT INTO student_batches (student_id, batch_id, enrolled_by, status)
                        VALUES (?, ?, ?, 'active')
                    ")->execute([$studentId, $batchId, currentUserId()]);
                }

                // Increment current_enrollment
                $db->prepare("
                    UPDATE batches
                    SET current_enrollment = (
                        SELECT COUNT(*) FROM student_batches
                        WHERE batch_id = ? AND status = 'active'
                    )
                    WHERE batch_id = ?
                ")->execute([$batchId, $batchId]);

                $db->commit();

                logActivity('ASSIGN_STUDENT','batches',
                    "Enrolled student '{$studentRow['full_name']}' (ID: {$studentId}) into batch '{$batchRow['batch_name']}' (ID: {$batchId})");

                setFlash('success',
                    "Student {$studentRow['full_name']} enrolled in {$batchRow['batch_name']}.");
                redirect(BASE_URL.'/admin/batch/view_batch.php?id='.urlencode($batchId));

            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                error_log('[GyanSetu] assign_student: ' . $e->getMessage());
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
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Student to Batch — GyanSetu</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family:'Inter',sans-serif; } body { background:#FEFDDF; }
        select:focus,input:focus { outline:none; border-color:#E87F24!important; box-shadow:0 0 0 3px rgba(232,127,36,.12); }
        .card { background:white; border-radius:20px; box-shadow:0 2px 16px rgba(0,0,0,.06); }
        .btn-primary { background:linear-gradient(135deg,#E87F24,#FFC81E); }
        .btn-primary:hover { opacity:.9; transform:translateY(-1px); }
        select { transition:border-color .2s,box-shadow .2s; }
    </style>
</head>
<body class="flex">
    <?php include __DIR__ . '/../sidebar.php'; ?>

    <main class="flex-1 p-6 md:p-8 ml-0 md:ml-64 min-h-screen">

        <div class="flex items-center gap-4 mb-8">
            <a href="batches.php" class="w-9 h-9 flex items-center justify-center rounded-xl bg-white shadow text-gray-500 hover:text-[#E87F24] transition"><i class="ri-arrow-left-line"></i></a>
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Enroll Student in Batch</h1>
                <p class="text-sm text-gray-500">Assign an active student to a batch</p>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="bg-red-50 border-l-4 border-red-400 rounded-xl p-4 mb-6">
            <p class="text-red-700 font-semibold text-sm flex items-center gap-2 mb-1"><i class="ri-error-warning-line"></i> Please fix:</p>
            <?php foreach ($errors as $e): ?><p class="text-red-600 text-sm pl-5">• <?= $e ?></p><?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="max-w-2xl">
            <div class="card p-8">
                <form method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                    <!-- Student select -->
                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Select Student <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <i class="ri-user-star-line absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <select name="student_id" id="studentSelect"
                                    class="w-full border border-gray-200 rounded-xl pl-10 pr-4 py-3 text-sm bg-white">
                                <option value="">— Choose a student —</option>
                                <?php foreach ($studentList as $s):
                                    $sel = ($s['student_id'] === ($_POST['student_id'] ?? '')) ? 'selected' : '';
                                ?>
                                <option value="<?= sanitize($s['student_id']) ?>" <?= $sel ?>>
                                    <?= sanitize($s['full_name']) ?> (<?= sanitize($s['student_id']) ?>) · <?= sanitize($s['stream']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if (empty($studentList)): ?>
                            <p class="text-xs text-gray-400 mt-1.5">No active students found. <a href="../student/add_student.php" class="text-[#E87F24] hover:underline">Add one?</a></p>
                        <?php endif; ?>
                    </div>

                    <!-- Batch select -->
                    <div class="mb-8">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            Select Batch <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <i class="ri-group-line absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <select name="batch_id" id="batchSelect"
                                    class="w-full border border-gray-200 rounded-xl pl-10 pr-4 py-3 text-sm bg-white">
                                <option value="">— Choose a batch —</option>
                                <?php foreach ($batchList as $b):
                                    $sel = ($b['batch_id'] === ($_POST['batch_id'] ?? $prefillBatch)) ? 'selected' : '';
                                    $seats = $b['max_students'] > 0
                                        ? " · {$b['current_enrollment']}/{$b['max_students']} seats"
                                        : " · {$b['current_enrollment']} enrolled";
                                    $full = $b['max_students'] > 0 && $b['current_enrollment'] >= $b['max_students'];
                                ?>
                                <option value="<?= sanitize($b['batch_id']) ?>" <?= $sel ?> <?= $full ? 'disabled' : '' ?>>
                                    <?= sanitize($b['batch_name']) ?> (<?= sanitize($b['batch_id']) ?>)<?= $seats ?><?= $full ? ' — FULL' : '' ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- Batch info preview -->
                        <div id="batchInfo" class="hidden mt-3 bg-blue-50 border border-blue-100 rounded-xl p-3 text-sm text-blue-700"></div>
                        <?php if (empty($batchList)): ?>
                            <p class="text-xs text-gray-400 mt-1.5">No enrollable batches found. <a href="add_batch.php" class="text-[#E87F24] hover:underline">Create one?</a></p>
                        <?php endif; ?>
                    </div>

                    <div class="flex gap-3">
                        <button type="submit" class="btn-primary flex-1 py-3 rounded-xl text-white font-semibold shadow transition flex items-center justify-center gap-2">
                            <i class="ri-user-add-line"></i> Enroll Student
                        </button>
                        <a href="batches.php" class="px-6 py-3 rounded-xl border border-gray-200 text-gray-600 text-sm font-medium hover:bg-gray-50 transition text-center">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        // Batch info preview on select
        const batchData = <?= json_encode(array_column($batchList, null, 'batch_id'), JSON_HEX_TAG) ?>;
        document.getElementById('batchSelect').addEventListener('change', function () {
            const info  = document.getElementById('batchInfo');
            const batch = batchData[this.value];
            if (!batch) { info.classList.add('hidden'); return; }
            const seats = batch.max_students > 0
                ? `${batch.current_enrollment} / ${batch.max_students} seats used`
                : `${batch.current_enrollment} enrolled (no cap)`;
            info.innerHTML = `<i class="ri-information-line mr-1"></i> <strong>${batch.batch_name}</strong> · ${seats} · Status: ${batch.status}`;
            info.classList.remove('hidden');
        });

        // Trigger on page load if pre-filled
        if (document.getElementById('batchSelect').value) {
            document.getElementById('batchSelect').dispatchEvent(new Event('change'));
        }

        // Live student search filter
        const studentSel = document.getElementById('studentSelect');
        const origOptions = [...studentSel.options].slice(1); // skip placeholder

        // Simple text filter for long lists
        if (origOptions.length > 20) {
            const wrapper  = studentSel.closest('div');
            const searchIn = document.createElement('input');
            searchIn.placeholder = 'Type to search students…';
            searchIn.className   = 'w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm mb-2 transition';
            searchIn.style.outline = 'none';
            wrapper.parentNode.insertBefore(searchIn, wrapper);

            searchIn.addEventListener('input', function () {
                const q = this.value.toLowerCase();
                [...studentSel.options].forEach((opt, i) => {
                    if (i === 0) return;
                    opt.hidden = q && !opt.text.toLowerCase().includes(q);
                });
            });
        }
    </script>
</body>
</html>
