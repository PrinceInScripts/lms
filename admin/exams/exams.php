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
$stmt = $db->query("SELECT e.*, b.batch_name, c.course_name FROM exams e LEFT JOIN batches b ON b.batch_id = e.batch_id LEFT JOIN courses c ON c.id = e.course_id ORDER BY e.exam_date DESC");
$exams = $stmt->fetchAll();

$page_title = 'Exams — GyanSetu';
include __DIR__ . '/../sidebar.php';
?>
<div class="max-w-6xl mx-auto">
    <div class="flex justify-between items-center mb-6"><div><h1 class="text-2xl font-bold">Exams</h1><p class="text-sm text-gray-500"><?= count($exams) ?> exam(s)</p></div><a href="<?= $B ?>/admin/exams/create_exam.php" class="bg-gradient-to-r from-[#E87F24] to-[#FFC81E] text-white px-5 py-2.5 rounded-xl">+ Create Exam</a></div>
    <?php renderFlashMessages(); ?>
    <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
        <?php if(empty($exams)): ?><div class="p-16 text-center text-gray-400"><i class="ri-file-copy-line text-5xl mb-3"></i><p>No exams created.</p></div><?php else: ?>
        <div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="bg-gradient-to-r from-[#E87F24] to-[#FFC81E] text-white"><th class="px-5 py-3 text-left">Exam Name</th><th class="px-5 py-3 text-left">Batch/Course</th><th class="px-5 py-3 text-center">Total Marks</th><th class="px-5 py-3 text-center">Passing</th><th class="px-5 py-3 text-center">Exam Date</th><th class="px-5 py-3 text-center">Actions</th></tr></thead><tbody class="divide-y"><?php foreach($exams as $e): ?><tr class="hover:bg-orange-50/50"><td class="px-5 py-3.5 font-semibold"><?= sanitize($e['exam_name']) ?></td><td class="px-5 py-3.5 text-xs text-gray-500"><?= $e['batch_name'] ? sanitize($e['batch_name']) : 'All' ?> / <?= $e['course_name'] ? sanitize($e['course_name']) : 'All' ?></td><td class="px-5 py-3.5 text-center"><?= (float)$e['total_marks'] ?></td><td class="px-5 py-3.5 text-center"><?= (float)$e['passing_marks'] ?></td><td class="px-5 py-3.5 text-center"><?= date('d M Y', strtotime($e['exam_date'])) ?></td><td class="px-5 py-3.5"><div class="flex justify-center gap-3"><a href="<?= $B ?>/admin/exams/view_exam.php?exam_id=<?= $e['exam_id'] ?>" class="text-[#73A5CA]"><i class="ri-eye-line"></i></a><a href="<?= $B ?>/admin/exams/edit_exam.php?exam_id=<?= $e['exam_id'] ?>" class="text-[#E87F24]"><i class="ri-edit-line"></i></a><a href="<?= $B ?>/admin/exams/delete_exam.php?exam_id=<?= $e['exam_id'] ?>" onclick="return confirm('Delete exam and all results?')" class="text-red-400"><i class="ri-delete-bin-line"></i></a></div></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </div>
</div>