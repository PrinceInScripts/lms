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
if(!$id) { setFlash('error','Invalid assignment.'); redirect($B.'/admin/assignments/assignments.php'); }

$stmt = $db->prepare("
    SELECT a.*, b.batch_name, c.course_name, u.username AS uploaded_by_name
    FROM assignments a
    LEFT JOIN batches b ON b.batch_id = a.batch_id
    LEFT JOIN courses c ON c.id = a.course_id
    LEFT JOIN users u ON u.id = a.uploaded_by
    WHERE a.id = ?
");
$stmt->execute([$id]);
$assign = $stmt->fetch();
if(!$assign) { setFlash('error','Assignment not found.'); redirect($B.'/admin/assignments/assignments.php'); }

// Download request
if(isset($_GET['download'])) {
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/Project/lms/' . $assign['file_path'];
    if(file_exists($fullPath)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($assign['file_path']) . '"');
        readfile($fullPath);
        exit;
    } else { setFlash('error','File not found.'); redirect($B.'/admin/assignments/view_assignment.php?id='.$id); }
}

// Fetch submissions
$submissions = [];
$subStmt = $db->prepare("
    SELECT s.*, sd.full_name
    FROM assignment_submissions s
    JOIN student_details sd ON sd.student_id = s.student_id
    WHERE s.assignment_id = ?
    ORDER BY s.submitted_at DESC
");
$subStmt->execute([$id]);
$submissions = $subStmt->fetchAll();

$page_title = 'View Assignment — GyanSetu';
include __DIR__ . '/../sidebar.php';
?>
<div class="max-w-4xl mx-auto">
    <div class="flex items-center gap-4 mb-6"><a href="<?= $B ?>/admin/assignments/assignments.php" class="w-9 h-9 rounded-xl bg-white shadow flex items-center justify-center"><i class="ri-arrow-left-line"></i></a><div><h1 class="text-2xl font-bold"><?= sanitize($assign['title']) ?></h1><p class="text-sm text-gray-500">Assignment details & submissions</p></div><div class="ml-auto flex gap-2"><a href="?id=<?= $id ?>&download=1" class="bg-[#73A5CA] text-white px-4 py-2 rounded-xl"><i class="ri-download-line"></i> Download File</a><a href="edit_assignment.php?id=<?= $id ?>" class="bg-[#E87F24] text-white px-4 py-2 rounded-xl"><i class="ri-edit-line"></i> Edit</a></div></div>
    <div class="bg-white rounded-2xl shadow-sm border p-6 mb-6"><div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm"><div><span class="font-semibold">Batch:</span> <?= $assign['batch_name'] ? sanitize($assign['batch_name']) : 'All Batches' ?></div><div><span class="font-semibold">Course:</span> <?= $assign['course_name'] ? sanitize($assign['course_name']) : 'All Courses' ?></div><div><span class="font-semibold">Due Date:</span> <?= date('d M Y', strtotime($assign['due_date'])) ?></div><div><span class="font-semibold">Max Marks:</span> <?= (float)$assign['max_marks'] ?></div><div><span class="font-semibold">Uploaded by:</span> <?= sanitize($assign['uploaded_by_name'] ?? 'system') ?></div><div><span class="font-semibold">File:</span> <?= basename($assign['file_path']) ?></div></div><div class="mt-4"><span class="font-semibold">Description:</span><p class="mt-1 text-gray-700"><?= nl2br(sanitize($assign['description'])) ?: '<span class="text-gray-400 italic">No description</span>' ?></p></div></div>
    <div class="bg-white rounded-2xl shadow-sm border p-6"><h3 class="font-bold text-lg mb-4">Submissions (<?= count($submissions) ?>)</h3><?php if(empty($submissions)): ?><p class="text-gray-400 text-sm">No submissions yet.</p><?php else: ?><div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="bg-gray-50"><th class="px-4 py-2 text-left">Student</th><th class="px-4 py-2 text-left">Submitted On</th><th class="px-4 py-2 text-center">Status</th><th class="px-4 py-2 text-center">View</th></tr></thead><tbody><?php foreach($submissions as $sub): $statusClass = $sub['status'] === 'submitted' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?><tr><td class="px-4 py-2 font-medium"><?= sanitize($sub['full_name']) ?></td><td class="px-4 py-2"><?= date('d M Y, h:i A', strtotime($sub['submitted_at'])) ?></td><td class="px-4 py-2 text-center"><span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= $statusClass ?>"><?= ucfirst($sub['status']) ?></span></td><td class="px-4 py-2 text-center"><a href="<?= $B ?>/uploads/assignments/<?= basename($sub['file_path']) ?>" target="_blank" class="text-[#73A5CA] hover:underline">View</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>
</div>