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
$examId = trim($_GET['exam_id'] ?? '');
if(!$examId) { setFlash('error','Exam ID required.'); redirect($B.'/admin/exams/exams.php'); }

$exam = $db->prepare("SELECT e.*, b.batch_name, c.course_name FROM exams e LEFT JOIN batches b ON b.batch_id = e.batch_id LEFT JOIN courses c ON c.id = e.course_id WHERE e.exam_id = ?")->execute([$examId])->fetch();
if(!$exam) { setFlash('error','Exam not found.'); redirect($B.'/admin/exams/exams.php'); }

$results = $db->prepare("SELECT er.*, sd.full_name FROM exam_results er JOIN student_details sd ON sd.student_id = er.student_id WHERE er.exam_id = ? ORDER BY sd.full_name")->execute([$examId])->fetchAll();

$page_title = 'Exam Results — GyanSetu';
include __DIR__ . '/../sidebar.php';
?>
<div class="max-w-5xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-4"><a href="<?= $B ?>/admin/exams/exams.php" class="w-9 h-9 rounded-xl bg-white shadow flex items-center justify-center"><i class="ri-arrow-left-line"></i></a><div><h1 class="text-2xl font-bold"><?= sanitize($exam['exam_name']) ?></h1><p class="text-sm text-gray-500"><?= $exam['batch_name'] ? sanitize($exam['batch_name']) : 'All Batches' ?> | <?= $exam['course_name'] ? sanitize($exam['course_name']) : 'All Courses' ?> | Date: <?= date('d M Y', strtotime($exam['exam_date'])) ?> | Passing: <?= (float)$exam['passing_marks'] ?> / <?= (float)$exam['total_marks'] ?></p></div></div>
        <a href="<?= $B ?>/admin/exams/add_result.php?exam_id=<?= $examId ?>" class="bg-[#73A5CA] text-white px-4 py-2 rounded-xl">+ Add Result</a>
    </div>
    <?php renderFlashMessages(); ?>
    <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
        <?php if(empty($results)): ?><div class="p-16 text-center text-gray-400"><i class="ri-file-copy-line text-5xl mb-3"></i><p>No results added yet.</p></div><?php else: ?>
        <div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="bg-gradient-to-r from-[#E87F24] to-[#FFC81E] text-white"><th class="px-5 py-3 text-left">Student</th><th class="px-5 py-3 text-center">Obtained Marks</th><th class="px-5 py-3 text-center">Result</th><th class="px-5 py-3 text-center">Action</th></tr></thead><tbody class="divide-y"><?php foreach($results as $r): $resultClass = $r['grade'] === 'Pass' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?><tr><td class="px-5 py-3 font-semibold"><?= sanitize($r['full_name']) ?> (<span class="text-xs text-gray-400"><?= $r['student_id'] ?></span>)</td><td class="px-5 py-3 text-center"><?= (float)$r['obtained_marks'] ?> / <?= (float)$exam['total_marks'] ?></td><td class="px-5 py-3 text-center"><span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $resultClass ?>"><?= $r['grade'] ?></span></td><td class="px-5 py-3 text-center"><a href="<?= $B ?>/admin/exams/add_result.php?exam_id=<?= $examId ?>&edit=<?= $r['student_id'] ?>" class="text-[#E87F24]"><i class="ri-edit-line"></i></a> (edit via same form)</td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </div>
</div>