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
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) $errors[] = 'Invalid CSRF.';
    else {
        $old = $_POST;
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $batchId     = trim($_POST['batch_id'] ?? '') ?: null;
        $courseId    = sanitizeInt($_POST['course_id'] ?? 0) ?: null;
        $dueDate     = trim($_POST['due_date'] ?? '');
        $maxMarks    = (float)($_POST['max_marks'] ?? 0);
        $file        = $_FILES['assignment_file'] ?? null;

        if (empty($title)) $errors[] = 'Title required.';
        if (empty($dueDate)) $errors[] = 'Due date required.';
        if ($maxMarks <= 0) $errors[] = 'Max marks must be > 0.';
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) $errors[] = 'Please upload a file.';

        $allowedTypes = ['application/pdf','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/zip','application/x-zip-compressed'];
        if ($file) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if (!in_array($mime, $allowedTypes)) $errors[] = 'Invalid file type. Allowed: PDF, DOC, DOCX, ZIP.';
            if ($file['size'] > 10*1024*1024) $errors[] = 'File exceeds 10 MB.';
        }

        if (empty($errors)) {
            $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/Project/lms/uploads/assignments/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $newName = 'assign_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $dest = $uploadDir . $newName;
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $stmt = $db->prepare("
                    INSERT INTO assignments (title, description, file_path, batch_id, course_id, due_date, max_marks, uploaded_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$title, $description ?: null, 'uploads/assignments/' . $newName, $batchId, $courseId, $dueDate, $maxMarks, currentUserId()]);
                logActivity('CREATE_ASSIGNMENT', 'assignments', "Created assignment '{$title}'");
                setFlash('success','Assignment created.');
                redirect($B . '/admin/assignments/assignments.php');
            } else $errors[] = 'Upload failed.';
        }
    }
}

$csrf = generateCSRFToken();
$page_title = 'Add Assignment — GyanSetu';
include __DIR__ . '/../sidebar.php';
?>
<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-4 mb-6"><a href="<?= $B ?>/admin/assignments/assignments.php" class="w-9 h-9 rounded-xl bg-white shadow flex items-center justify-center"><i class="ri-arrow-left-line"></i></a><div><h1 class="text-2xl font-bold">Add Assignment</h1><p class="text-sm text-gray-500">Create a new assignment for students</p></div></div>
    <?php renderFlashMessages(); if(!empty($errors)): ?><div class="bg-red-50 p-4 rounded-xl mb-5"><?php foreach($errors as $e) echo "<p class='text-red-600'>• $e</p>"; ?></div><?php endif; ?>
    <div class="bg-white rounded-2xl border p-7"><form method="POST" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <div class="mb-4"><label class="block font-semibold mb-1">Title *</label><input type="text" name="title" value="<?= sanitize($old['title'] ?? '') ?>" class="w-full border rounded-xl px-4 py-2.5" required></div>
        <div class="mb-4"><label class="block font-semibold mb-1">Description</label><textarea name="description" rows="3" class="w-full border rounded-xl px-4 py-2.5"><?= sanitize($old['description'] ?? '') ?></textarea></div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4"><div><label>Batch (opt)</label><select name="batch_id" class="w-full border rounded-xl px-4 py-2.5"><option value="">— Any Batch —</option><?php foreach($batches as $b): ?><option value="<?= $b['batch_id'] ?>" <?= ($old['batch_id']??'')===$b['batch_id']?'selected':''?>><?= $b['batch_name'] ?></option><?php endforeach; ?></select></div><div><label>Course (opt)</label><select name="course_id" class="w-full border rounded-xl px-4 py-2.5"><option value="">— Any Course —</option><?php foreach($courses as $c): ?><option value="<?= $c['id'] ?>" <?= (int)($old['course_id']??0)===$c['id']?'selected':''?>><?= $c['course_name'] ?></option><?php endforeach; ?></select></div></div>
        <div class="grid grid-cols-2 gap-4 mb-4"><div><label>Due Date *</label><input type="date" name="due_date" value="<?= sanitize($old['due_date']??'') ?>" class="w-full border rounded-xl px-4 py-2.5" required></div><div><label>Max Marks *</label><input type="number" step="0.5" name="max_marks" value="<?= (float)($old['max_marks']??0) ?>" class="w-full border rounded-xl px-4 py-2.5" required></div></div>
        <div class="mb-6"><label class="block font-semibold mb-1">Assignment File *</label><input type="file" name="assignment_file" accept=".pdf,.doc,.docx,.zip" class="w-full text-sm" required><p class="text-xs text-gray-400">Max 10 MB. Allowed: PDF, DOC, DOCX, ZIP.</p></div>
        <div class="flex gap-3"><button type="submit" class="flex-1 bg-gradient-to-r from-[#E87F24] to-[#FFC81E] text-white py-3 rounded-xl">Create Assignment</button><a href="<?= $B ?>/admin/assignments/assignments.php" class="px-6 py-3 border rounded-xl text-gray-600">Cancel</a></div>
    </form></div>
</div>