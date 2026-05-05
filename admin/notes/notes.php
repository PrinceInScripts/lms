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
$offset = ($page - 1) * $limit;

$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= ' AND (n.title LIKE ? OR n.description LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$totalStmt = $db->prepare("SELECT COUNT(*) FROM notes n WHERE $where");
$totalStmt->execute($params);
$total = (int) $totalStmt->fetchColumn();
$totalPages = max(1, ceil($total / $limit));

$stmt = $db->prepare("
    SELECT n.*,
           b.batch_name,
           c.course_name,
           u.username AS uploaded_by_name
    FROM notes n
    LEFT JOIN batches b ON b.batch_id = n.batch_id
    LEFT JOIN courses c ON c.id = n.course_id
    LEFT JOIN users u ON u.id = n.uploaded_by
    WHERE $where
    ORDER BY n.uploaded_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([...$params, $limit, $offset]);
$notes = $stmt->fetchAll();

$page_title = 'Notes — GyanSetu';
include __DIR__ . '/../sidebar.php';
?>
<div class="max-w-6xl mx-auto">
    <div class="flex flex-col sm:flex-row justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Notes &amp; Materials</h1>
            <p class="text-sm text-gray-500"><?= number_format($total) ?> note(s)</p>
        </div>
        <a href="<?= $B ?>/admin/notes/add_note.php" class="bg-gradient-to-r from-[#E87F24] to-[#FFC81E] text-white px-5 py-2.5 rounded-xl shadow flex items-center gap-2">
            <i class="ri-add-line"></i> Add Note
        </a>
    </div>

    <?php renderFlashMessages(); ?>

    <div class="bg-white rounded-2xl shadow-sm border p-4 mb-5">
        <form method="GET" class="flex gap-3">
            <div class="relative flex-1">
                <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Search by title or description…" class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm">
            </div>
            <button type="submit" class="bg-[#73A5CA] text-white px-5 py-2.5 rounded-xl">Filter</button>
            <?php if ($search): ?><a href="<?= $B ?>/admin/notes/notes.php" class="border px-4 py-2.5 rounded-xl text-gray-600">Clear</a><?php endif; ?>
        </form>
    </div>

    <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
        <?php if (empty($notes)): ?>
            <div class="p-16 text-center text-gray-400"><i class="ri-file-text-line text-5xl mb-3"></i><p>No notes found.</p></div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gradient-to-r from-[#E87F24] to-[#FFC81E] text-white">
                        <th class="px-5 py-3 text-left">Title</th>
                        <th class="px-5 py-3 text-left">Batch</th>
                        <th class="px-5 py-3 text-left">Course</th>
                        <th class="px-5 py-3 text-left">Uploaded</th>
                        <th class="px-5 py-3 text-center">Downloads</th>
                        <th class="px-5 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($notes as $n): ?>
                    <tr class="hover:bg-orange-50/50">
                        <td class="px-5 py-3.5 font-semibold text-gray-800"><?= sanitize($n['title']) ?></td>
                        <td class="px-5 py-3.5"><?= $n['batch_name'] ? sanitize($n['batch_name']) : '<span class="text-gray-400">—</span>' ?></td>
                        <td class="px-5 py-3.5"><?= $n['course_name'] ? sanitize($n['course_name']) : '<span class="text-gray-400">—</span>' ?></td>
                        <td class="px-5 py-3.5 text-xs text-gray-500">
                            <?= date('d M Y', strtotime($n['uploaded_at'])) ?><br>
                            by <?= sanitize($n['uploaded_by_name'] ?? 'system') ?>
                        </td>
                        <td class="px-5 py-3.5 text-center font-mono font-semibold"><?= (int)$n['download_count'] ?></td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center justify-center gap-3">
                                <a href="<?= $B ?>/admin/notes/view_note.php?id=<?= (int)$n['id'] ?>" title="View" class="text-[#73A5CA] hover:text-blue-700"><i class="ri-eye-line text-lg"></i></a>
                                <a href="<?= $B ?>/admin/notes/edit_note.php?id=<?= (int)$n['id'] ?>" title="Edit" class="text-[#E87F24] hover:text-orange-700"><i class="ri-edit-line text-lg"></i></a>
                                <button onclick="confirmDelete(<?= (int)$n['id'] ?>, '<?= sanitize($n['title']) ?>')" title="Delete" class="text-red-400 hover:text-red-600"><i class="ri-delete-bin-line text-lg"></i></button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="flex justify-between px-5 py-4 border-t">
            <p class="text-sm text-gray-500">Showing <?= $offset+1 ?>–<?= min($offset+$limit, $total) ?> of <?= $total ?></p>
            <div class="flex gap-1"><?php for($p=1;$p<=$totalPages;$p++): ?><a href="?page=<?=$p?>&search=<?=urlencode($search)?>" class="w-8 h-8 flex items-center justify-center rounded-lg <?= $p==$page ? 'bg-[#E87F24] text-white' : 'text-gray-500 hover:bg-orange-50' ?>"><?=$p?></a><?php endfor; ?></div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function confirmDelete(id, title) {
    if (confirm(`Delete note "${title}"? This action cannot be undone.`)) {
        window.location.href = '<?= $B ?>/admin/notes/delete_note.php?id=' + id;
    }
}
</script>