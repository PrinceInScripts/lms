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
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token invalid. Please refresh.';
    } else {
        $old = $_POST;
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $batchId     = trim($_POST['batch_id'] ?? '') ?: null;
        $courseId    = sanitizeInt($_POST['course_id'] ?? 0) ?: null;
        $file        = $_FILES['note_file'] ?? null;

        if (empty($title)) $errors[] = 'Title is required.';
        if (strlen($title) > 255) $errors[] = 'Title must be ≤ 255 characters.';
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) $errors[] = 'Please select a valid file.';

        // File validation
        $allowedTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'image/jpeg', 'image/png'
        ];
        $maxSize = 10 * 1024 * 1024; // 10 MB

        if ($file) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime, $allowedTypes)) {
                $errors[] = 'Invalid file type. Allowed: PDF, DOC, DOCX, PPT, PPTX, JPG, PNG.';
            }
            if ($file['size'] > $maxSize) {
                $errors[] = 'File size exceeds 10 MB limit.';
            }
        }

        // Optional batch/course existence check
        if ($batchId) {
            $chk = $db->prepare("SELECT 1 FROM batches WHERE batch_id = ?");
            $chk->execute([$batchId]);
            if (!$chk->fetchColumn()) $errors[] = 'Selected batch does not exist.';
        }
        if ($courseId) {
            $chk = $db->prepare("SELECT 1 FROM courses WHERE id = ?");
            $chk->execute([$courseId]);
            if (!$chk->fetchColumn()) $errors[] = 'Selected course does not exist.';
        }

        if (empty($errors)) {
            // Create uploads directory if missing
            $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/Project/lms/uploads/notes/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $newName = 'note_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $dest = $uploadDir . $newName;

            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $stmt = $db->prepare("
                    INSERT INTO notes (title, description, file_path, file_type, file_size,
                                       batch_id, course_id, uploaded_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $title,
                    $description ?: null,
                    'uploads/notes/' . $newName,
                    $mime,
                    $file['size'],
                    $batchId,
                    $courseId,
                    currentUserId()
                ]);

                logActivity('ADD_NOTE', 'notes', "Added note '{$title}' (file: {$newName})");
                setFlash('success', "Note '{$title}' added successfully.");
                redirect($B . '/admin/notes/notes.php');
            } else {
                $errors[] = 'Failed to save uploaded file.';
            }
        }
    }
}

$csrfToken = generateCSRFToken();
$page_title = 'Add Note — GyanSetu';
include __DIR__ . '/../sidebar.php';
?>
<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-4 mb-8">
        <a href="<?= $B ?>/admin/notes/notes.php" class="w-9 h-9 rounded-xl bg-white shadow flex items-center justify-center text-gray-500 hover:text-[#E87F24]">
            <i class="ri-arrow-left-line"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Add Note</h1>
            <p class="text-sm text-gray-500">Upload a note / study material</p>
        </div>
    </div>

    <?php renderFlashMessages(); ?>
    <?php if (!empty($errors)): ?>
        <div class="bg-red-50 border-l-4 border-red-400 rounded-xl p-4 mb-5">
            <?php foreach ($errors as $e): ?><p class="text-red-600 text-sm">• <?= sanitize($e) ?></p><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl shadow-sm border p-7">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Title <span class="text-red-500">*</span></label>
                <input type="text" name="title" maxlength="255" value="<?= sanitize($old['title'] ?? '') ?>"
                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm" required>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Description</label>
                <textarea name="description" rows="4" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm resize-none"><?= sanitize($old['description'] ?? '') ?></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Batch (optional)</label>
                    <select name="batch_id" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-white">
                        <option value="">— Any Batch —</option>
                        <?php foreach ($batches as $b): ?>
                            <option value="<?= sanitize($b['batch_id']) ?>" <?= ($old['batch_id'] ?? '') === $b['batch_id'] ? 'selected' : '' ?>>
                                <?= sanitize($b['batch_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Course (optional)</label>
                    <select name="course_id" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-white">
                        <option value="">— Any Course —</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= (int)($old['course_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= sanitize($c['course_name']) ?> (<?= sanitize($c['course_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">File <span class="text-red-500">*</span></label>
                <input type="file" name="note_file" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png" class="w-full text-sm" required>
                <p class="text-xs text-gray-400 mt-1">Max 10 MB. Allowed: PDF, DOC, DOCX, PPT, PPTX, JPG, PNG.</p>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="flex-1 bg-gradient-to-r from-[#E87F24] to-[#FFC81E] text-white font-semibold py-3 rounded-xl shadow">Upload Note</button>
                <a href="<?= $B ?>/admin/notes/notes.php" class="px-6 py-3 border border-gray-200 rounded-xl text-gray-600 hover:bg-gray-50">Cancel</a>
            </div>
        </form>
    </div>
</div>