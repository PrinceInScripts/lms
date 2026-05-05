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
if(!$examId) { setFlash('error','Invalid exam.'); redirect($B.'/admin/exams/exams.php'); }

$exam = $db->prepare("SELECT * FROM exams WHERE exam_id = ?")->execute([$examId])->fetch();
if(!$exam) { setFlash('error','Exam not found.'); redirect($B.'/admin/exams/exams.php'); }

$batches = $db->query("SELECT batch_id, batch_name FROM batches WHERE status IN ('upcoming','ongoing') ORDER BY batch_name")->fetchAll();
$courses = $db->query("SELECT id, course_code, course_name FROM courses WHERE status = 'active' ORDER BY course_name")->fetchAll();
$errors = [];

if(isPost()) {
    if(!verifyCSRFToken($_POST['csrf_token'] ?? '')) $errors[] = 'CSRF invalid.';
    else {
        $examName = trim($_POST['exam_name'] ?? '');
        $batchId = trim($_POST['batch_id'] ?? '') ?: null;
        $courseId = sanitizeInt($_POST['course_id'] ?? 0) ?: null;
        $totalMarks = (float)($_POST['total_marks'] ?? 0);
        $passingMarks = (float)($_POST['passing_marks'] ?? 0);
        $examDate = trim($_POST['exam_date'] ?? '');
        if(empty($examName)) $errors[] = 'Exam name required.';
        if($totalMarks <= 0) $errors[] = 'Total marks > 0.';
        if($passingMarks <= 0 || $passingMarks > $totalMarks) $errors[] = 'Invalid passing marks.';
        if(empty($examDate)) $errors[] = 'Exam date required.';
        if(empty($errors)) {
            $upd = $db->prepare("UPDATE exams SET exam_name=?, batch_id=?, course_id=?, total_marks=?, passing_marks=?, exam_date=? WHERE exam_id=?");
            $upd->execute([$examName, $batchId, $courseId, $totalMarks, $passingMarks, $examDate, $examId]);
            logActivity('EDIT_EXAM', 'exams', "Updated exam {$examId}");
            setFlash('success','Exam updated.');
            redirect($B."/admin/exams/view_exam.php?exam_id={$examId}");
        }
    }
}

$csrf = generateCSRFToken();
$page_title = 'Edit Exam — GyanSetu';
include __DIR__ . '/../sidebar.php';
?>
<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-4 mb-6"><a href="<?= $B ?>/admin/exams/view_exam.php?exam_id=<?= $examId ?>" class="w-9 h-9 rounded-xl bg-white shadow flex items-center justify-center"><i class="ri-arrow-left-line"></i></a><div><h1 class="text-2xl font-bold">Edit Exam</h1><p class="text-sm text-gray-500"><?= sanitize($exam['exam_name']) ?></p></div></div>
    <?php renderFlashMessages(); if(!empty($errors)): ?><div class="bg-red-50 p-4 rounded-xl mb-5"><?php foreach($errors as $e) echo "<p class='text-red-600'>• $e</p>"; ?></div><?php endif; ?>
    <div class="bg-white rounded-2xl border p-7">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div class="mb-4"><label class="block font-semibold mb-1">Exam Name *</label><input type="text" name="exam_name" value="<?= sanitize($_POST['exam_name'] ?? $exam['exam_name']) ?>" class="w-full border rounded-xl px-4 py-2.5" required></div>
            <div class="grid grid-cols-2 gap-4 mb-4"><div><label>Batch (optional)</label><select name="batch_id" class="w-full border rounded-xl px-4 py-2.5 bg-white"><option value="">— Any Batch —</option><?php foreach($batches as $b): ?><option value="<?= $b['batch_id'] ?>" <?= (($_POST['batch_id']??$exam['batch_id'])==$b['batch_id'])?'selected':''?>><?= $b['batch_name'] ?></option><?php endforeach; ?></select></div><div><label>Course (optional)</label><select name="course_id" class="w-full border rounded-xl px-4 py-2.5 bg-white"><option value="">— Any Course —</option><?php foreach($courses as $c): ?><option value="<?= $c['id'] ?>" <?= (int)($_POST['course_id']??$exam['course_id'])==$c['id']?'selected':''?>><?= $c['course_name'] ?></option><?php endforeach; ?></select></div></div>
            <div class="grid grid-cols-2 gap-4 mb-4"><div><label>Total Marks *</label><input type="number" step="0.5" name="total_marks" value="<?= (float)($_POST['total_marks']??$exam['total_marks']) ?>" class="w-full border rounded-xl px-4 py-2.5" required></div><div><label>Passing Marks *</label><input type="number" step="0.5" name="passing_marks" value="<?= (float)($_POST['passing_marks']??$exam['passing_marks']) ?>" class="w-full border rounded-xl px-4 py-2.5" required></div></div>
            <div class="mb-6"><label class="block font-semibold mb-1">Exam Date *</label><input type="date" name="exam_date" value="<?= sanitize($_POST['exam_date']??$exam['exam_date']) ?>" class="w-full border rounded-xl px-4 py-2.5" required></div>
            <div class="flex gap-3"><button type="submit" class="flex-1 bg-gradient-to-r from-[#E87F24] to-[#FFC81E] text-white py-3 rounded-xl">Save Changes</button><a href="<?= $B ?>/admin/exams/view_exam.php?exam_id=<?= $examId ?>" class="px-6 py-3 border rounded-xl text-gray-600">Cancel</a></div>
        </form>
    </div>
</div>