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

if (!$id) { setFlash('error', 'Invalid note.'); redirect($B . '/admin/notes/notes.php'); }

$stmt = $db->prepare("
    SELECT n.*, b.batch_name, c.course_name, u.username AS uploaded_by_name
    FROM notes n
    LEFT JOIN batches b ON b.batch_id = n.batch_id
    LEFT JOIN courses c ON c.id = n.course_id
    LEFT JOIN users u ON u.id = n.uploaded_by
    WHERE n.id = ?
");
$stmt->execute([$id]);
$note = $stmt->fetch();
if (!$note) { setFlash('error','Note not found.'); redirect($B.'/admin/notes/notes.php'); }

// Increment download count if download requested
if (isset($_GET['download'])) {
    $db->prepare("UPDATE notes SET download_count = download_count + 1 WHERE id = ?")->execute([$id]);
    logActivity('DOWNLOAD_NOTE', 'notes', "Downloaded note '{$note['title']}' (ID:{$id})");
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/Project/lms/' . $note['file_path'];
    if (file_exists($fullPath)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($note['file_path']) . '"');
        readfile($fullPath);
        exit;
    } else {
        setFlash('error', 'File not found on server.');
        redirect($B . '/admin/notes/view_note.php?id=' . $id);
    }
}

$page_title = 'View Note — GyanSetu';
include __DIR__ . '/../sidebar.php';
?>
<div class="max-w-3xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="<?= $B ?>/admin/notes/notes.php" class="w-9 h-9 rounded-xl bg-white shadow flex items-center justify-center"><i class="ri-arrow-left-line"></i></a>
        <div><h1 class="text-2xl font-bold text-gray-800"><?= sanitize($note['title']) ?></h1></div>
        <div class="ml-auto flex gap-2">
            <a href="?id=<?= $id ?>&download=1" class="bg-[#73A5CA] text-white px-4 py-2 rounded-xl flex items-center gap-2"><i class="ri-download-line"></i> Download</a>
            <a href="edit_note.php?id=<?= $id ?>" class="bg-[#E87F24] text-white px-4 py-2 rounded-xl flex items-center gap-2"><i class="ri-edit-line"></i> Edit</a>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border p-6 space-y-4">
        <div><span class="text-xs font-semibold text-gray-400 uppercase">Description</span><p class="mt-1 text-gray-700"><?= nl2br(sanitize($note['description'])) ?: '<span class="text-gray-400 italic">No description</span>' ?></p></div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div><span class="font-semibold">Batch:</span> <?= $note['batch_name'] ? sanitize($note['batch_name']) : '—' ?></div>
            <div><span class="font-semibold">Course:</span> <?= $note['course_name'] ? sanitize($note['course_name']) : '—' ?></div>
            <div><span class="font-semibold">File type:</span> <?= sanitize($note['file_type']) ?></div>
            <div><span class="font-semibold">File size:</span> <?= round($note['file_size'] / 1024, 2) ?> KB</div>
            <div><span class="font-semibold">Uploaded by:</span> <?= sanitize($note['uploaded_by_name'] ?? 'system') ?></div>
            <div><span class="font-semibold">Uploaded on:</span> <?= date('d M Y, h:i A', strtotime($note['uploaded_at'])) ?></div>
            <div><span class="font-semibold">Downloads:</span> <?= (int)$note['download_count'] ?></div>
        </div>
        <div class="bg-gray-50 rounded-xl p-4"><i class="ri-file-copy-line mr-2"></i> Filename: <?= basename($note['file_path']) ?></div>
    </div>
</div>