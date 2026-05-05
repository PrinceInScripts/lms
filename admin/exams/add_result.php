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

$exam = $db->prepare("SELECT * FROM exams WHERE exam_id = ?")->execute([$examId])->fetch();
if(!$exam) { setFlash('error','Exam not found.'); redirect($B.'/admin/exams/exams.php'); }

// Fetch students from the batch (if batch specified, else all active students)
$students = [];
if($exam['batch_id']) {
    $stuStmt = $db->prepare("SELECT sd.student_id, sd.full_name FROM student_batches sb JOIN student_details sd ON sd.student_id = sb.student_id WHERE sb.batch_id = ? AND sb.status = 'active' AND sd.current_status = 'active' ORDER BY sd.full_name");
    $stuStmt->execute([$exam['batch_id']]);
    $students = $stuStmt->fetchAll();
} else {
    $stuStmt = $db->query("SELECT student_id, full_name FROM student_details WHERE current_status = 'active' ORDER BY full_name");
    $students = $stuStmt->fetchAll();
}

$errors = [];
if(isPost()) {
    if(!verifyCSRFToken($_POST['csrf_token'] ?? '')) $errors[] = 'CSRF invalid.';
    else {
        $studentId = trim($_POST['student_id'] ?? '');
        $obtained = (float)($_POST['obtained_marks'] ?? 0);
        if(!$studentId) $errors[] = 'Select a student.';
        if($obtained < 0 || $obtained > $exam['total_marks']) $errors[] = 'Obtained marks must be between 0 and total marks.';
        if(empty($errors)) {
            $grade = ($obtained >= $exam['passing_marks']) ? 'Pass' : 'Fail';
            // Check if result already exists
            $chk = $db->prepare("SELECT id FROM exam_results WHERE exam_id = ? AND student_id = ?");
            $chk->execute([$examId, $studentId]);
            if($chk->fetch()) {
                $db->prepare("UPDATE exam_results SET obtained_marks = ?, grade = ? WHERE exam_id = ? AND student_id = ?")->execute([$obtained, $grade, $examId, $studentId]);
            } else {
                $db->prepare("INSERT INTO exam_results (exam_id, student_id, obtained_marks, grade) VALUES (?,?,?,?)")->execute([$examId, $studentId, $obtained, $grade]);
            }
            logActivity('ADD_EXAM_RESULT', 'exams', "Added/updated result for exam {$exam['exam_name']} - student {$studentId}");
            setFlash('success', 'Result saved.');
            redirect($B."/admin/exams/view_exam.php?exam_id={$examId}");
        }
    }
}

$csrf = generateCSRFToken();
$page_title = 'Add Exam Result — GyanSetu';
include __DIR__ . '/../sidebar.php';
?>
<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-4 mb-6"><a href="<?= $B ?>/admin/exams/view_exam.php?exam_id=<?= $examId ?>" class="w-9 h-9 rounded-xl bg-white shadow flex items-center justify-center"><i class="ri-arrow-left-line"></i></a><div><h1 class="text-2xl font-bold">Add Result</h1><p class="text-sm text-gray-500">Exam: <?= sanitize($exam['exam_name']) ?></p></div></div>
    <?php renderFlashMessages(); if(!empty($errors)): ?><div class="bg-red-50 p-4 rounded-xl mb-5"><?php foreach($errors as $e) echo "<p class='text-red-600'>• $e</p>"; ?></div><?php endif; ?>
    <div class="bg-white rounded-2xl border p-7">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div class="mb-4"><label class="block font-semibold mb-1">Student *</label><select name="student_id" class="w-full border rounded-xl px-4 py-2.5 bg-white" required><option value="">— Select Student —</option><?php foreach($students as $s): ?><option value="<?= $s['student_id'] ?>"><?= sanitize($s['full_name']) ?> (<?= $s['student_id'] ?>)</option><?php endforeach; ?></select></div>
            <div class="mb-4"><label class="block font-semibold mb-1">Obtained Marks * (Max: <?= (float)$exam['total_marks'] ?>)</label><input type="number" step="0.5" name="obtained_marks" class="w-full border rounded-xl px-4 py-2.5" required></div>
            <div class="flex gap-3"><button type="submit" class="flex-1 bg-gradient-to-r from-[#E87F24] to-[#FFC81E] text-white py-3 rounded-xl">Save Result</button><a href="<?= $B ?>/admin/exams/view_exam.php?exam_id=<?= $examId ?>" class="px-6 py-3 border rounded-xl text-gray-600">Cancel</a></div>
        </form>
    </div>
</div>