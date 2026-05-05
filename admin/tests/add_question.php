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
$testId = (int)($_GET['test_id'] ?? 0);
if (!$testId) { setFlash('error', 'No test specified.'); redirect($B . '/admin/tests/test.php'); }

// Verify test exists
$testStmt = $db->prepare("SELECT title FROM tests WHERE id = ?");
$testStmt->execute([$testId]);
$test = $testStmt->fetch();
if (!$test) { setFlash('error', 'Test not found.'); redirect($B . '/admin/tests/test.php'); }

$errors = [];
if (isPost()) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) $errors[] = 'Invalid CSRF.';
    else {
        $question = trim($_POST['question_text'] ?? '');
        $optA = trim($_POST['option_a'] ?? '');
        $optB = trim($_POST['option_b'] ?? '');
        $optC = trim($_POST['option_c'] ?? '');
        $optD = trim($_POST['option_d'] ?? '');
        $correct = $_POST['correct_answer'] ?? '';

        if (empty($question)) $errors[] = 'Question text is required.';
        if (empty($optA) || empty($optB) || empty($optC) || empty($optD)) $errors[] = 'All four options are required.';
        if (!in_array($correct, ['a','b','c','d'])) $errors[] = 'Select correct answer.';

        if (empty($errors)) {
            $stmt = $db->prepare("INSERT INTO test_questions (test_id, question_text, option_a, option_b, option_c, option_d, correct_answer) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$testId, $question, $optA, $optB, $optC, $optD, $correct]);
            logActivity('ADD_QUESTION', 'tests', "Added question to test '{$test['title']}' (ID:{$testId})");
            setFlash('success', 'Question added.');
            if (isset($_POST['add_another'])) {
                redirect($B . "/admin/tests/add_question.php?test_id={$testId}");
            } else {
                redirect($B . "/admin/tests/view_test.php?id={$testId}");
            }
        }
    }
}

$csrf = generateCSRFToken();
$page_title = 'Add Question — GyanSetu';
include __DIR__ . '/../sidebar.php';
?>
<div class="max-w-3xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="<?= $B ?>/admin/tests/view_test.php?id=<?= $testId ?>" class="w-9 h-9 rounded-xl bg-white shadow flex items-center justify-center"><i class="ri-arrow-left-line"></i></a>
        <div><h1 class="text-2xl font-bold">Add Question</h1><p class="text-sm text-gray-500">Test: <?= sanitize($test['title']) ?></p></div>
    </div>
    <?php renderFlashMessages(); if(!empty($errors)): ?><div class="bg-red-50 p-4 rounded-xl mb-5"><?php foreach($errors as $e) echo "<p class='text-red-600'>• $e</p>"; ?></div><?php endif; ?>
    <div class="bg-white rounded-2xl border p-7">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div class="mb-4"><label class="block font-semibold mb-1">Question Text *</label><textarea name="question_text" rows="3" class="w-full border rounded-xl px-4 py-2.5" required></textarea></div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div><label>Option A *</label><input type="text" name="option_a" class="w-full border rounded-xl px-4 py-2.5" required></div>
                <div><label>Option B *</label><input type="text" name="option_b" class="w-full border rounded-xl px-4 py-2.5" required></div>
                <div><label>Option C *</label><input type="text" name="option_c" class="w-full border rounded-xl px-4 py-2.5" required></div>
                <div><label>Option D *</label><input type="text" name="option_d" class="w-full border rounded-xl px-4 py-2.5" required></div>
            </div>
            <div class="mb-6"><label class="block font-semibold mb-1">Correct Answer *</label><select name="correct_answer" class="w-full border rounded-xl px-4 py-2.5 bg-white"><option value="">Select</option><option value="a">A</option><option value="b">B</option><option value="c">C</option><option value="d">D</option></select></div>
            <div class="flex gap-3">
                <button type="submit" name="add_another" value="1" class="flex-1 bg-[#73A5CA] text-white py-3 rounded-xl">Save & Add Another</button>
                <button type="submit" class="flex-1 bg-gradient-to-r from-[#E87F24] to-[#FFC81E] text-white py-3 rounded-xl">Save & Finish</button>
            </div>
        </form>
    </div>
</div>