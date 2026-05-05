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
$id = (int)($_GET['id'] ?? 0);
if (!$id) { setFlash('error','Invalid note.'); redirect($B.'/admin/notes/notes.php'); }

$stmt = $db->prepare("SELECT * FROM notes WHERE id = ?");
$stmt->execute([$id]);
$note = $stmt->fetch();
if (!$note) { setFlash('error','Note not found.'); redirect($B.'/admin/notes/notes.php'); }

$batches = $db->query("SELECT batch_id, batch_name FROM batches WHERE status IN ('upcoming','ongoing') ORDER BY batch_name")->fetchAll();
$courses = $db->query("SELECT id, course_code, course_name FROM courses WHERE status = 'active' ORDER BY course_name")->fetchAll();
$errors = [];

if (isPost()) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) $errors[] = 'Invalid CSRF token.';
    else {
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $batchId     = trim($_POST['batch_id'] ?? '') ?: null;
        $courseId    = sanitizeInt($_POST['course_id'] ?? 0) ?: null;
        $file        = $_FILES['note_file'] ?? null;

        if (empty($title)) $errors[] = 'Title is required.';
        $updateFields = ['title' => $title, 'description' => $description ?: null, 'batch_id' => $batchId, 'course_id' => $courseId];

        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['application/pdf','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/vnd.ms-powerpoint','application/vnd.openxmlformats-officedocument.presentationml.presentation','image/jpeg','image/png'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if (!in_array($mime, $allowedTypes)) $errors[] = 'Invalid file type.';
            if ($file['size'] > 10*1024*1024) $errors[] = 'File too large (max 10 MB).';
            if (empty($errors)) {
                $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/Project/lms/uploads/notes/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $newName = 'note_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                $dest = $uploadDir . $newName;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    // Delete old file
                    $oldFile = $_SERVER['DOCUMENT_ROOT'] . '/Project/lms/' . $note['file_path'];
                    if (file_exists($oldFile)) unlink($oldFile);
                    $updateFields['file_path'] = 'uploads/notes/' . $newName;
                    $updateFields['file_type'] = $mime;
                    $updateFields['file_size'] = $file['size'];
                } else $errors[] = 'Failed to upload new file.';
            }
        }

        if (empty($errors)) {
            $sql = "UPDATE notes SET title=:title, description=:desc, batch_id=:batch, course_id=:course";
            $params = [':title'=>$title, ':desc'=>$description, ':batch'=>$batchId, ':course'=>$courseId, ':id'=>$id];
            if (isset($updateFields['file_path'])) {
                $sql .= ", file_path=:path, file_type=:ftype, file_size=:fsize";
                $params[':path'] = $updateFields['file_path'];
                $params[':ftype'] = $updateFields['file_type'];
                $params[':fsize'] = $updateFields['file_size'];
            }
            $sql .= " WHERE id=:id";
            $db->prepare($sql)->execute($params);
            logActivity('EDIT_NOTE', 'notes', "Updated note '{$title}' (ID:{$id})");
            setFlash('success','Note updated.');
            redirect($B.'/admin/notes/view_note.php?id='.$id);
        }
    }
}

$csrf = generateCSRFToken();
$page_title = 'Edit Note — GyanSetu';
include __DIR__ . '/../sidebar.php';
?>
<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-4 mb-6"><a href="<?= $B ?>/admin/notes/notes.php" class="w-9 h-9 rounded-xl bg-white shadow flex items-center justify-center"><i class="ri-arrow-left-line"></i></a><div><h1 class="text-2xl font-bold">Edit Note</h1><p class="text-sm text-gray-500"><?= sanitize($note['title']) ?></p></div></div>
    <?php renderFlashMessages(); if(!empty($errors)): ?><div class="bg-red-50 p-4 rounded-xl mb-5"><?php foreach($errors as $e) echo "<p class='text-red-600'>• $e</p>"; ?></div><?php endif; ?>
    <div class="bg-white rounded-2xl border p-7"><form method="POST" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <div class="mb-4"><label class="block font-semibold mb-1">Title *</label><input type="text" name="title" value="<?= sanitize($_POST['title'] ?? $note['title']) ?>" class="w-full border rounded-xl px-4 py-2.5" required></div>
        <div class="mb-4"><label class="block font-semibold mb-1">Description</label><textarea name="description" rows="4" class="w-full border rounded-xl px-4 py-2.5"><?= sanitize($_POST['description'] ?? $note['description']) ?></textarea></div>
        <div class="grid grid-cols-2 gap-4 mb-4"><div><label>Batch (opt)</label><select name="batch_id" class="w-full border rounded-xl px-4 py-2.5"><option value="">— None —</option><?php foreach($batches as $b): ?><option value="<?= $b['batch_id'] ?>" <?= (($_POST['batch_id'] ?? $note['batch_id']) == $b['batch_id']) ? 'selected' : '' ?>><?= $b['batch_name'] ?></option><?php endforeach; ?></select></div><div><label>Course (opt)</label><select name="course_id" class="w-full border rounded-xl px-4 py-2.5"><option value="">— None —</option><?php foreach($courses as $c): ?><option value="<?= $c['id'] ?>" <?= (int)($_POST['course_id'] ?? $note['course_id']) === (int)$c['id'] ? 'selected' : '' ?>><?= $c['course_name'] ?></option><?php endforeach; ?></select></div></div>
        <div class="mb-6"><label class="block font-semibold mb-1">Replace file (optional)</label><input type="file" name="note_file" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png" class="w-full text-sm"><p class="text-xs text-gray-400">Current: <?= basename($note['file_path']) ?></p></div>
        <div class="flex gap-3"><button type="submit" class="flex-1 bg-gradient-to-r from-[#E87F24] to-[#FFC81E] text-white py-3 rounded-xl">Save Changes</button><a href="<?= $B ?>/admin/notes/view_note.php?id=<?= $id ?>" class="px-6 py-3 border rounded-xl text-gray-600">Cancel</a></div>
    </form></div>
</div>