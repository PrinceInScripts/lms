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
$errors = [];
$old = [];

$batches = $db->query("SELECT batch_id, batch_name FROM batches WHERE status IN ('upcoming','ongoing') ORDER BY batch_name")->fetchAll();
$courses = $db->query("SELECT id, course_code, course_name FROM courses WHERE status = 'active' ORDER BY course_name")->fetchAll();

if (isPost()) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) $errors[] = 'Invalid CSRF token.';
    else {
        $old = $_POST;
        $title = trim($_POST['title'] ?? '');
        $batchId = trim($_POST['batch_id'] ?? '') ?: null;
        $courseId = sanitizeInt($_POST['course_id'] ?? 0) ?: null;
        $totalMarks = (float)($_POST['total_marks'] ?? 0);
        $duration = (int)($_POST['duration_minutes'] ?? 0);

        if (empty($title)) $errors[] = 'Test title is required.';
        if ($totalMarks <= 0) $errors[] = 'Total marks must be > 0.';
        if ($duration <= 0) $errors[] = 'Duration must be > 0 minutes.';

        if (empty($errors)) {
            $stmt = $db->prepare("INSERT INTO tests (title, batch_id, course_id, total_marks, duration_minutes) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$title, $batchId, $courseId, $totalMarks, $duration]);
            $testId = $db->lastInsertId();
            logActivity('CREATE_TEST', 'tests', "Created test '{$title}' (ID: {$testId})");
            setFlash('success', "Test created. Now add questions.");
            redirect($B . "/admin/tests/add_question.php?test_id={$testId}");
        }
    }
}

$csrf = generateCSRFToken();
$page_title = 'Create Test — GyanSetu';
include __DIR__ . '/../sidebar.php';
?>
<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="<?= $B ?>/admin/tests/test.php" class="w-9 h-9 rounded-xl bg-white shadow flex items-center justify-center"><i class="ri-arrow-left-line"></i></a>
        <div><h1 class="text-2xl font-bold">Create Test</h1><p class="text-sm text-gray-500">Set up a new test</p></div>
    </div>
    <?php renderFlashMessages(); if(!empty($errors)): ?><div class="bg-red-50 p-4 rounded-xl mb-5"><?php foreach($errors as $e) echo "<p class='text-red-600'>• $e</p>"; ?></div><?php endif; ?>
    <div class="bg-white rounded-2xl border p-7">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div class="mb-4"><label class="block font-semibold mb-1">Title *</label><input type="text" name="title" value="<?= sanitize($old['title'] ?? '') ?>" class="w-full border rounded-xl px-4 py-2.5" required></div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div><label>Batch (optional)</label><select name="batch_id" class="w-full border rounded-xl px-4 py-2.5 bg-white"><option value="">— Any Batch —</option><?php foreach($batches as $b): ?><option value="<?= $b['batch_id'] ?>" <?= ($old['batch_id']??'')==$b['batch_id']?'selected':''?>><?= $b['batch_name'] ?></option><?php endforeach; ?></select></div>
                <div><label>Course (optional)</label><select name="course_id" class="w-full border rounded-xl px-4 py-2.5 bg-white"><option value="">— Any Course —</option><?php foreach($courses as $c): ?><option value="<?= $c['id'] ?>" <?= (int)($old['course_id']??0)==$c['id']?'selected':''?>><?= $c['course_name'] ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="grid grid-cols-2 gap-4 mb-4"><div><label>Total Marks *</label><input type="number" step="0.5" name="total_marks" value="<?= (float)($old['total_marks']??0) ?>" class="w-full border rounded-xl px-4 py-2.5" required></div><div><label>Duration (minutes) *</label><input type="number" name="duration_minutes" value="<?= (int)($old['duration_minutes']??0) ?>" class="w-full border rounded-xl px-4 py-2.5" required></div></div>
            <div class="flex gap-3"><button type="submit" class="flex-1 bg-gradient-to-r from-[#E87F24] to-[#FFC81E] text-white py-3 rounded-xl">Create & Add Questions</button><a href="<?= $B ?>/admin/tests/test.php" class="px-6 py-3 border rounded-xl text-gray-600">Cancel</a></div>
        </form>
    </div>
</div>