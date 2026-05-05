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
$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 15;
$offset = ($page-1)*$limit;

$where = '1=1';
$params = [];
if ($search) { $where .= ' AND (a.title LIKE ?)'; $params[] = "%$search%"; }

$totalStmt = $db->prepare("SELECT COUNT(*) FROM assignments a WHERE $where");
$totalStmt->execute($params);
$total = (int)$totalStmt->fetchColumn();
$totalPages = max(1, ceil($total/$limit));

$stmt = $db->prepare("
    SELECT a.*, b.batch_name, c.course_name
    FROM assignments a
    LEFT JOIN batches b ON b.batch_id = a.batch_id
    LEFT JOIN courses c ON c.id = a.course_id
    WHERE $where
    ORDER BY a.due_date ASC, a.id DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([...$params, $limit, $offset]);
$assignments = $stmt->fetchAll();

$page_title = 'Assignments — GyanSetu';
include __DIR__ . '/../sidebar.php';
?>
<div class="max-w-6xl mx-auto">
    <div class="flex flex-col sm:flex-row justify-between gap-4 mb-6"><div><h1 class="text-2xl font-bold">Assignments</h1><p class="text-sm text-gray-500"><?= number_format($total) ?> assignment(s)</p></div><a href="<?= $B ?>/admin/assignments/add_assignment.php" class="bg-gradient-to-r from-[#E87F24] to-[#FFC81E] text-white px-5 py-2.5 rounded-xl shadow flex items-center gap-2"><i class="ri-add-line"></i> Add Assignment</a></div>
    <?php renderFlashMessages(); ?>
    <div class="bg-white rounded-2xl shadow-sm border p-4 mb-5"><form method="GET" class="flex gap-3"><div class="relative flex-1"><i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i><input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Search by title…" class="w-full pl-9 pr-4 py-2.5 border rounded-xl"></div><button type="submit" class="bg-[#73A5CA] text-white px-5 py-2.5 rounded-xl">Filter</button><?php if($search): ?><a href="<?= $B ?>/admin/assignments/assignments.php" class="border px-4 py-2.5 rounded-xl text-gray-600">Clear</a><?php endif; ?></form></div>
    <div class="bg-white rounded-2xl shadow-sm overflow-hidden"><?php if(empty($assignments)): ?><div class="p-16 text-center text-gray-400"><i class="ri-task-line text-5xl mb-3"></i><p>No assignments found.</p></div><?php else: ?><div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="bg-gradient-to-r from-[#E87F24] to-[#FFC81E] text-white"><th class="px-5 py-3 text-left">Title</th><th class="px-5 py-3 text-left">Batch/Course</th><th class="px-5 py-3 text-left">Due Date</th><th class="px-5 py-3 text-center">Max Marks</th><th class="px-5 py-3 text-center">Actions</th></tr></thead><tbody class="divide-y"><?php foreach($assignments as $a): ?><tr class="hover:bg-orange-50/50"><td class="px-5 py-3.5 font-semibold"><?= sanitize($a['title']) ?></td><td class="px-5 py-3.5 text-gray-600 text-xs"><?= sanitize($a['batch_name'] ?? 'All Batches') ?> / <?= sanitize($a['course_name'] ?? 'All Courses') ?></td><td class="px-5 py-3.5"><?= date('d M Y', strtotime($a['due_date'])) ?></td><td class="px-5 py-3.5 text-center font-mono"><?= (float)$a['max_marks'] ?></td><td class="px-5 py-3.5"><div class="flex justify-center gap-3"><a href="<?= $B ?>/admin/assignments/view_assignment.php?id=<?= (int)$a['id'] ?>" title="View" class="text-[#73A5CA]"><i class="ri-eye-line text-lg"></i></a><a href="<?= $B ?>/admin/assignments/edit_assignment.php?id=<?= (int)$a['id'] ?>" title="Edit" class="text-[#E87F24]"><i class="ri-edit-line text-lg"></i></a><button onclick="confirmDelete(<?= (int)$a['id'] ?>, '<?= sanitize($a['title']) ?>')" title="Delete" class="text-red-400"><i class="ri-delete-bin-line text-lg"></i></button></div></td></tr><?php endforeach; ?></tbody></table></div><?php if($totalPages>1): ?><div class="flex justify-between px-5 py-4 border-t"><p class="text-sm text-gray-500">Showing <?= $offset+1 ?>–<?= min($offset+$limit,$total) ?> of <?= $total ?></p><div class="flex gap-1"><?php for($p=1;$p<=$totalPages;$p++): ?><a href="?page=<?=$p?>&search=<?=urlencode($search)?>" class="w-8 h-8 flex items-center justify-center rounded-lg <?= $p==$page ? 'bg-[#E87F24] text-white' : 'text-gray-500 hover:bg-orange-50' ?>"><?=$p?></a><?php endfor; ?></div></div><?php endif; ?><?php endif; ?></div>
</div>
<script>function confirmDelete(id,title){if(confirm(`Delete assignment "${title}"?`)) window.location.href='<?= $B ?>/admin/assignments/delete_assignment.php?id='+id;}</script>