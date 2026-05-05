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
$testId = (int)($_GET['test_id'] ?? 0);
if(!$testId) { setFlash('error','Invalid test.'); redirect($B.'/admin/tests/test.php'); }

$test = $db->prepare("SELECT title FROM tests WHERE id = ?")->execute([$testId])->fetch();
if(!$test) { setFlash('error','Test not found.'); redirect($B.'/admin/tests/test.php'); }

$stmt = $db->prepare("
    SELECT ta.*, sd.full_name
    FROM test_attempts ta
    JOIN student_details sd ON sd.student_id = ta.student_id
    WHERE ta.test_id = ?
    ORDER BY ta.submitted_at DESC
");
$stmt->execute([$testId]);
$attempts = $stmt->fetchAll();

$page_title = 'Test Attempts — GyanSetu';
include __DIR__ . '/../sidebar.php';
?>
<div class="max-w-4xl mx-auto">
    <div class="flex items-center gap-4 mb-6"><a href="<?= $B ?>/admin/tests/view_test.php?id=<?= $testId ?>" class="w-9 h-9 rounded-xl bg-white shadow flex items-center justify-center"><i class="ri-arrow-left-line"></i></a><div><h1 class="text-2xl font-bold">Attempts: <?= sanitize($test['title']) ?></h1></div></div>
    <?php renderFlashMessages(); ?>
    <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
        <?php if(empty($attempts)): ?><div class="p-16 text-center text-gray-400"><i class="ri-history-line text-5xl mb-3"></i><p>No attempts yet.</p></div><?php else: ?>
        <div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="bg-gradient-to-r from-[#E87F24] to-[#FFC81E] text-white"><th class="px-5 py-3 text-left">Student</th><th class="px-5 py-3 text-center">Obtained Marks</th><th class="px-5 py-3 text-center">Status</th><th class="px-5 py-3 text-center">Submitted At</th></tr></thead><tbody class="divide-y"><?php foreach($attempts as $a): $statusClass = $a['status'] === 'submitted' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'; ?><tr><td class="px-5 py-3 font-semibold"><?= sanitize($a['full_name']) ?></td><td class="px-5 py-3 text-center"><?= (float)$a['obtained_marks'] ?> / <?= (float)$test['total_marks'] ?></td><td class="px-5 py-3 text-center"><span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $statusClass ?>"><?= ucfirst($a['status']) ?></span></td><td class="px-5 py-3 text-center text-gray-500"><?= date('d M Y, h:i A', strtotime($a['submitted_at'])) ?></td></td><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </div>
</div>