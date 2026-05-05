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

$stmt = $db->query("SELECT t.*, b.batch_name, c.course_name FROM tests t LEFT JOIN batches b ON b.batch_id = t.batch_id LEFT JOIN courses c ON c.id = t.course_id ORDER BY t.id DESC");
$tests = $stmt->fetchAll();

$page_title = 'Tests — GyanSetu';
include __DIR__ . '/../sidebar.php';
?>
<div class="max-w-6xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div><h1 class="text-2xl font-bold">Tests</h1><p class="text-sm text-gray-500"><?= count($tests) ?> test(s)</p></div>
        <a href="<?= $B ?>/admin/tests/create_test.php" class="bg-gradient-to-r from-[#E87F24] to-[#FFC81E] text-white px-5 py-2.5 rounded-xl">+ Create Test</a>
    </div>
    <?php renderFlashMessages(); ?>
    <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
        <?php if(empty($tests)): ?><div class="p-16 text-center text-gray-400"><i class="ri-questionnaire-line text-5xl mb-3"></i><p>No tests created yet.</p></div><?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="bg-gradient-to-r from-[#E87F24] to-[#FFC81E] text-white"><th class="px-5 py-3 text-left">Title</th><th class="px-5 py-3 text-left">Batch/Course</th><th class="px-5 py-3 text-center">Total Marks</th><th class="px-5 py-3 text-center">Duration (min)</th><th class="px-5 py-3 text-center">Actions</th></tr></thead>
                <tbody class="divide-y"><?php foreach($tests as $t): ?><tr class="hover:bg-orange-50/50"><td class="px-5 py-3.5 font-semibold"><?= sanitize($t['title']) ?></td><td class="px-5 py-3.5 text-xs text-gray-500"><?= $t['batch_name'] ? sanitize($t['batch_name']) : 'All' ?> / <?= $t['course_name'] ? sanitize($t['course_name']) : 'All' ?></td><td class="px-5 py-3.5 text-center"><?= (float)$t['total_marks'] ?></td><td class="px-5 py-3.5 text-center"><?= (int)$t['duration_minutes'] ?></td><td class="px-5 py-3.5"><div class="flex justify-center gap-3"><a href="<?= $B ?>/admin/tests/view_test.php?id=<?= $t['id'] ?>" class="text-[#73A5CA]"><i class="ri-eye-line"></i></a><a href="<?= $B ?>/admin/tests/edit_test.php?id=<?= $t['id'] ?>" class="text-[#E87F24]"><i class="ri-edit-line"></i></a><a href="<?= $B ?>/admin/tests/delete_test.php?id=<?= $t['id'] ?>" onclick="return confirm('Delete test and all questions?')" class="text-red-400"><i class="ri-delete-bin-line"></i></a></div></td></table><?php endforeach; ?></tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>