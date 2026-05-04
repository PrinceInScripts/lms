<?php
define('GYANSETU_APP', true);
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/db_conn.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/activity_logger.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db     = getDB();
$search = trim($_GET['search'] ?? '');
$filter = $_GET['status'] ?? '';
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 12;
$offset = ($page - 1) * $limit;

$where  = '1=1';
$params = [];

if ($search !== '') {
    $where   .= ' AND (b.batch_name LIKE ? OR b.batch_id LIKE ?)';
    $like     = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
}
if (in_array($filter, ['upcoming','ongoing','completed','cancelled'])) {
    $where   .= ' AND b.status = ?';
    $params[] = $filter;
}

$total = (int) $db->prepare("SELECT COUNT(*) FROM batches b WHERE $where")
    ->execute($params) ? $db->prepare("SELECT COUNT(*) FROM batches b WHERE $where")
    ->execute($params) : 0;

// Re-run properly
$cntStmt = $db->prepare("SELECT COUNT(*) FROM batches b WHERE $where");
$cntStmt->execute($params);
$total      = (int) $cntStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $limit));

$stmt = $db->prepare("
    SELECT b.*,
           (SELECT COUNT(*) FROM student_batches sb WHERE sb.batch_id = b.batch_id AND sb.status = 'active') AS active_students
    FROM batches b
    WHERE $where
    ORDER BY b.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([...$params, $limit, $offset]);
$batches = $stmt->fetchAll();

// ── Status helpers ─────────────────────────────────────────────────────────
function batchStatusBadge(string $s): string {
    $map = [
        'upcoming'  => ['bg-yellow-100','text-yellow-700','bg-yellow-400','Upcoming'],
        'ongoing'   => ['bg-green-100', 'text-green-700', 'bg-green-500', 'Ongoing'],
        'completed' => ['bg-blue-100',  'text-blue-700',  'bg-blue-500',  'Completed'],
        'cancelled' => ['bg-red-100',   'text-red-700',   'bg-red-500',   'Cancelled'],
    ];
    [$bg,$txt,$dot,$lbl] = $map[$s] ?? ['bg-gray-100','text-gray-600','bg-gray-400',ucfirst($s)];
    return "<span class=\"inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold $bg $txt\">
                <span class=\"w-1.5 h-1.5 rounded-full $dot\"></span>$lbl</span>";
}

$allStatuses = ['upcoming','ongoing','completed','cancelled'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batches — GyanSetu</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #FEFDDF; }
        .btn-primary { background: linear-gradient(135deg,#E87F24,#FFC81E); transition: all .2s; }
        .btn-primary:hover { opacity:.9; transform:translateY(-1px); box-shadow:0 6px 20px rgba(232,127,36,.3); }
        .batch-card { background:white; border-radius:20px; box-shadow:0 2px 16px rgba(0,0,0,.06); transition:all .25s; }
        .batch-card:hover { transform:translateY(-3px); box-shadow:0 8px 30px rgba(0,0,0,.1); }
        .action-btn { transition:all .2s; }
        .action-btn:hover { transform:scale(1.15); }
        .filter-chip { transition:all .2s; }
    </style>
</head>
<body class="flex">
    <?php include __DIR__ . '/../sidebar.php'; ?>

    <!-- <main class="flex-1 p-6 md:p-8 ml-0 md:ml-64 min-h-screen"> -->

        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Batches</h1>
                <p class="text-sm text-gray-500 mt-0.5"><?= number_format($total) ?> batch<?= $total !== 1 ? 'es' : '' ?> total</p>
            </div>
            <a href="add_batch.php" class="btn-primary inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-white font-semibold text-sm shadow">
                <i class="ri-add-circle-line"></i> Add Batch
            </a>
        </div>

        <?php renderFlashMessages(); ?>

        <!-- Search + Filter -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-6">
            <form method="GET" class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="text" name="search" value="<?= sanitize($search) ?>"
                           placeholder="Search by batch name or ID…"
                           class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-[#E87F24] focus:ring-2 focus:ring-orange-100 transition">
                </div>
                <select name="status" class="border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-white focus:outline-none focus:border-[#E87F24] transition">
                    <option value="">All Status</option>
                    <?php foreach ($allStatuses as $sv): ?>
                        <option value="<?= $sv ?>" <?= $filter === $sv ? 'selected' : '' ?>><?= ucfirst($sv) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-primary px-5 py-2.5 rounded-xl text-white text-sm font-semibold shadow">Search</button>
                <?php if ($search || $filter): ?>
                    <a href="batches.php" class="px-4 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-600 hover:bg-gray-50 transition">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Batch Cards Grid -->
        <?php if (empty($batches)): ?>
            <div class="flex flex-col items-center justify-center py-24 text-gray-400">
                <i class="ri-inbox-line text-5xl mb-3 text-[#E87F24] opacity-40"></i>
                <p class="font-medium">No batches found</p>
                <?php if ($search || $filter): ?><p class="text-sm mt-1">Try different search or filters</p><?php endif; ?>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5 mb-6">
                <?php foreach ($batches as $b): ?>
                <div class="batch-card p-5">
                    <!-- Top row -->
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <span class="text-xs font-mono font-bold text-[#E87F24] bg-orange-50 px-2 py-0.5 rounded-lg">
                                <?= sanitize($b['batch_id']) ?>
                            </span>
                            <h3 class="text-base font-bold text-gray-800 mt-1.5 leading-tight">
                                <?= sanitize($b['batch_name']) ?>
                            </h3>
                        </div>
                        <?= batchStatusBadge($b['status']) ?>
                    </div>

                    <!-- Meta -->
                    <div class="space-y-1.5 mb-4 text-sm text-gray-500">
                        <div class="flex items-center gap-2">
                            <i class="ri-calendar-line text-[#73A5CA]"></i>
                            <span><?= $b['start_date'] ? date('d M Y', strtotime($b['start_date'])) : '—' ?>
                            <?= $b['end_date'] ? ' → ' . date('d M Y', strtotime($b['end_date'])) : '' ?></span>
                        </div>
                        <?php if ($b['time_slot']): ?>
                        <div class="flex items-center gap-2">
                            <i class="ri-time-line text-[#73A5CA]"></i>
                            <span><?= sanitize($b['time_slot']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($b['platform']): ?>
                        <div class="flex items-center gap-2">
                            <i class="ri-computer-line text-[#73A5CA]"></i>
                            <span><?= sanitize($b['platform']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Enrollment bar -->
                    <?php
                    $max     = (int)$b['max_students'];
                    $current = (int)$b['active_students'];
                    $pct     = $max > 0 ? min(100, round($current / $max * 100)) : 0;
                    $barCol  = $pct >= 90 ? 'bg-red-400' : ($pct >= 70 ? 'bg-yellow-400' : 'bg-green-400');
                    ?>
                    <div class="mb-4">
                        <div class="flex justify-between text-xs text-gray-500 mb-1">
                            <span>Enrolled</span>
                            <span class="font-semibold text-gray-700">
                                <?= $current ?><?= $max > 0 ? " / $max" : '' ?>
                            </span>
                        </div>
                        <?php if ($max > 0): ?>
                        <div class="w-full bg-gray-100 rounded-full h-1.5">
                            <div class="<?= $barCol ?> h-1.5 rounded-full transition-all" style="width:<?= $pct ?>%"></div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-3 pt-3 border-t border-gray-50">
                        <a href="view_batch.php?id=<?= urlencode($b['batch_id']) ?>"
                           class="action-btn flex items-center gap-1 text-[#73A5CA] hover:text-blue-700 text-sm font-medium">
                            <i class="ri-eye-line"></i> View
                        </a>
                        <a href="edit_batch.php?id=<?= urlencode($b['batch_id']) ?>"
                           class="action-btn flex items-center gap-1 text-[#E87F24] hover:text-orange-700 text-sm font-medium">
                            <i class="ri-edit-line"></i> Edit
                        </a>
                        <a href="assign_student.php?batch_id=<?= urlencode($b['batch_id']) ?>"
                           class="action-btn flex items-center gap-1 text-purple-500 hover:text-purple-700 text-sm font-medium">
                            <i class="ri-user-add-line"></i> Enroll
                        </a>
                        <button onclick="confirmDelete('<?= sanitize($b['batch_id']) ?>','<?= sanitize($b['batch_name']) ?>')"
                                class="action-btn ml-auto text-red-400 hover:text-red-600">
                            <i class="ri-delete-bin-line text-lg"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="flex items-center justify-between mt-4">
                <p class="text-sm text-gray-500">Showing <?= $offset+1 ?>–<?= min($offset+$limit,$total) ?> of <?= $total ?></p>
                <div class="flex gap-1">
                    <?php for ($p=1;$p<=$totalPages;$p++): ?>
                    <a href="?page=<?=$p?>&search=<?=urlencode($search)?>&status=<?=urlencode($filter)?>"
                       class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-medium transition
                              <?= $p===$page ? 'bg-[#E87F24] text-white shadow' : 'text-gray-500 hover:bg-orange-50' ?>">
                        <?= $p ?>
                    </a>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    <!-- </main> -->

    <!-- Delete Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6">
            <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="ri-delete-bin-line text-2xl text-red-500"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-800 text-center mb-1">Delete Batch?</h3>
            <p class="text-sm text-gray-500 text-center mb-6">
                Delete <strong id="delBatchName"></strong>? All student enrollments in this batch will also be removed.
            </p>
            <div class="flex gap-3">
                <button onclick="closeModal()" class="flex-1 px-4 py-2.5 border border-gray-200 rounded-xl text-gray-600 text-sm font-medium hover:bg-gray-50 transition">Cancel</button>
                <a id="deleteLink" href="#" class="flex-1 px-4 py-2.5 bg-red-500 hover:bg-red-600 rounded-xl text-white text-sm font-semibold text-center transition">Delete</a>
            </div>
        </div>
    </div>

    <script>
        function confirmDelete(id, name) {
            document.getElementById('delBatchName').textContent = name;
            document.getElementById('deleteLink').href = 'delete_batch.php?id=' + encodeURIComponent(id);
            document.getElementById('deleteModal').classList.remove('hidden');
        }
        function closeModal() { document.getElementById('deleteModal').classList.add('hidden'); }
        document.getElementById('deleteModal').addEventListener('click', e => { if (e.target===document.getElementById('deleteModal')) closeModal(); });
    </script>
</body>
</html>
