<?php
define('GYANSETU_APP', true);
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/db_conn.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/activity_logger.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db = getDB();
$B = rtrim(BASE_URL, '/');
$id = (int)($_GET['id'] ?? 0);
if(!$id) { setFlash('error','Invalid test.'); redirect($B.'/admin/tests/test.php'); }

$testStmt = $db->prepare("SELECT t.*, b.batch_name, c.course_name FROM tests t LEFT JOIN batches b ON b.batch_id = t.batch_id LEFT JOIN courses c ON c.id = t.course_id WHERE t.id = ?");
$testStmt->execute([$id]);
$test = $testStmt->fetch();
if(!$test) { setFlash('error','Test not found.'); redirect($B.'/admin/tests/test.php'); }

$qStmt = $db->prepare("SELECT * FROM test_questions WHERE test_id = ? ORDER BY id");
$qStmt->execute([$id]);
$questions = $qStmt->fetchAll();

$page_title = 'View Test — GyanSetu';
include __DIR__ . '/../sidebar.php';
?>
<div class="max-w-4xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="<?= $B ?>/admin/tests/test.php" class="w-9 h-9 rounded-xl bg-white shadow flex items-center justify-center"><i class="ri-arrow-left-line"></i></a>
        <div><h1 class="text-2xl font-bold"><?= sanitize($test['title']) ?></h1><p class="text-sm text-gray-500"><?= $test['batch_name'] ? sanitize($test['batch_name']) : 'All Batches' ?> | <?= $test['course_name'] ? sanitize($test['course_name']) : 'All Courses' ?> | Marks: <?= (float)$test['total_marks'] ?> | Duration: <?= (int)$test['duration_minutes'] ?> min</p></div>
        <div class="ml-auto flex gap-2"><a href="<?= $B ?>/admin/tests/add_question.php?test_id=<?= $id ?>" class="bg-[#73A5CA] text-white px-4 py-2 rounded-xl">+ Add Question</a><a href="<?= $B ?>/admin/tests/view_detailed_batch.php?test_id=<?= $id ?>" class="bg-[#E87F24] text-white px-4 py-2 rounded-xl">View Attempts</a></div>
    </div>
    <?php renderFlashMessages(); ?>
    <div class="bg-white rounded-2xl shadow-sm border p-6">
        <h3 class="font-bold text-lg mb-4">Questions (<?= count($questions) ?>)</h3>
        <?php if(empty($questions)): ?><p class="text-gray-400">No questions added yet.</p><?php else: ?>
        <div class="space-y-6">
            <?php foreach($questions as $i=>$q): ?>
            <div class="border-b pb-4">
                <div class="flex justify-between items-start">
                    <p class="font-semibold"><?= $i+1 ?>. <?= nl2br(sanitize($q['question_text'])) ?></p>
                    <a href="<?= $B ?>/admin/tests/delete_question.php?id=<?= $q['id'] ?>&test_id=<?= $id ?>" onclick="return confirm('Delete this question?')" class="text-red-400"><i class="ri-delete-bin-line"></i></a>
                </div>
                <div class="grid grid-cols-2 gap-2 mt-2 text-sm pl-4">
                    <div>A) <?= sanitize($q['option_a']) ?></div><div>B) <?= sanitize($q['option_b']) ?></div>
                    <div>C) <?= sanitize($q['option_c']) ?></div><div>D) <?= sanitize($q['option_d']) ?></div>
                </div>
                <div class="mt-2 text-sm text-green-600 font-semibold">✓ Correct answer: <?= strtoupper($q['correct_answer']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>