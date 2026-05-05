<?php
define('GYANSETU_APP', true);
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/db_conn.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/activity_logger.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db      = getDB();
$B       = rtrim(BASE_URL, '/');
$search  = trim($_GET['search'] ?? '');
$bFilter = trim($_GET['batch_id'] ?? '');
$dFilter = trim($_GET['date'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$limit   = 15;
$offset  = ($page - 1) * $limit;

// Build WHERE
$where  = 's.is_cancelled = 0';
$params = [];
if ($search !== '') {
    $where   .= ' AND (s.topic LIKE ? OR b.batch_name LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($bFilter) { $where .= ' AND s.batch_id = ?'; $params[] = $bFilter; }
if ($dFilter) { $where .= ' AND s.schedule_date = ?'; $params[] = $dFilter; }

$cntStmt = $db->prepare("SELECT COUNT(*) FROM schedule s JOIN batches b ON b.batch_id = s.batch_id WHERE $where");
$cntStmt->execute($params);
$total      = (int) $cntStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $limit));

$stmt = $db->prepare("
    SELECT s.*, b.batch_name
    FROM schedule s
    JOIN batches b ON b.batch_id = s.batch_id
    WHERE $where
    ORDER BY s.schedule_date DESC, s.start_time DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([...$params, $limit, $offset]);
$schedules = $stmt->fetchAll();

// Batches for filter dropdown
$batches = $db->query("SELECT batch_id, batch_name FROM batches WHERE status IN ('upcoming','ongoing') ORDER BY batch_name")->fetchAll();

$today = date('Y-m-d');
?>
<?php include __DIR__ . '/../sidebar.php'; ?>
<style>
    body{background:#FEFDDF}
    .btn-primary{background:linear-gradient(135deg,#E87F24,#FFC81E);transition:all .2s}
    .btn-primary:hover{opacity:.9;transform:translateY(-1px)}
    tr:hover td{background:#fff8f0}
    .action-btn{transition:all .2s}.action-btn:hover{transform:scale(1.15)}
</style>

<div style="padding:0">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Schedule</h1>
            <p class="text-sm text-gray-500 mt-0.5"><?= number_format($total) ?> session<?= $total !== 1 ? 's' : '' ?></p>
        </div>
        <a href="<?= $B ?>/admin/schedule/add_schedule.php"
           class="btn-primary inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-white font-semibold text-sm shadow">
            <i class="ri-add-circle-line"></i> Add Schedule
        </a>
    </div>

    <?php renderFlashMessages(); ?>

    <!-- Filters -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-5">
        <form method="GET" class="flex flex-wrap gap-3 items-end">
            <div class="relative flex-1 min-w-[160px]">
                <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                <input type="text" name="search" value="<?= sanitize($search) ?>"
                       placeholder="Topic or batch…"
                       class="w-full pl-9 pr-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-[#E87F24] focus:ring-2 focus:ring-orange-100 transition">
            </div>
            <select name="batch_id" class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-white focus:outline-none focus:border-[#E87F24] transition">
                <option value="">All Batches</option>
                <?php foreach ($batches as $b): ?>
                    <option value="<?= sanitize($b['batch_id']) ?>" <?= $bFilter === $b['batch_id'] ? 'selected' : '' ?>>
                        <?= sanitize($b['batch_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="date" value="<?= sanitize($dFilter) ?>"
                   class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-[#E87F24] transition">
            <button type="submit" class="btn-primary px-5 py-2.5 rounded-xl text-white text-sm font-semibold shadow">Filter</button>
            <?php if ($search || $bFilter || $dFilter): ?>
                <a href="<?= $B ?>/admin/schedule/schedule.php"
                   class="px-4 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-600 hover:bg-gray-50 transition">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
        <?php if (empty($schedules)): ?>
            <div class="flex flex-col items-center justify-center py-20 text-gray-400">
                <i class="ri-calendar-line text-5xl mb-3 text-[#E87F24] opacity-40"></i>
                <p class="font-medium">No schedules found</p>
                <?php if ($search || $bFilter || $dFilter): ?>
                    <p class="text-sm mt-1">Try different filters</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gradient-to-r from-[#E87F24] to-[#FFC81E] text-white text-left">
                        <th class="px-5 py-3.5 font-semibold">Date</th>
                        <th class="px-5 py-3.5 font-semibold">Batch</th>
                        <th class="px-5 py-3.5 font-semibold">Topic</th>
                        <th class="px-5 py-3.5 font-semibold">Time</th>
                        <th class="px-5 py-3.5 font-semibold text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($schedules as $s): ?>
                    <?php
                    $isToday    = $s['schedule_date'] === $today;
                    $isUpcoming = $s['schedule_date'] > $today;
                    $isPast     = $s['schedule_date'] < $today;
                    ?>
                    <tr class="transition-colors duration-150">
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-2">
                                <?php if ($isToday): ?>
                                    <span class="w-2 h-2 rounded-full bg-green-500 flex-shrink-0"></span>
                                <?php elseif ($isUpcoming): ?>
                                    <span class="w-2 h-2 rounded-full bg-blue-400 flex-shrink-0"></span>
                                <?php else: ?>
                                    <span class="w-2 h-2 rounded-full bg-gray-300 flex-shrink-0"></span>
                                <?php endif; ?>
                                <div>
                                    <p class="font-semibold text-gray-800"><?= date('d M Y', strtotime($s['schedule_date'])) ?></p>
                                    <p class="text-xs text-gray-400"><?= date('D', strtotime($s['schedule_date'])) ?><?= $isToday ? ' · Today' : '' ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="inline-block bg-orange-50 text-[#E87F24] text-xs font-semibold px-2 py-0.5 rounded-lg">
                                <?= sanitize($s['batch_name']) ?>
                            </span>
                        </td>
                        <td class="px-5 py-3.5">
                            <p class="font-medium text-gray-800"><?= sanitize($s['topic']) ?></p>
                            <?php if ($s['description']): ?>
                                <p class="text-xs text-gray-400 mt-0.5 truncate max-w-xs"><?= sanitize(truncate($s['description'], 60)) ?></p>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3.5 text-gray-600">
                            <span class="flex items-center gap-1.5">
                                <i class="ri-time-line text-[#73A5CA]"></i>
                                <?= date('h:i A', strtotime($s['start_time'])) ?> – <?= date('h:i A', strtotime($s['end_time'])) ?>
                            </span>
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center justify-center gap-3">
                                <a href="<?= $B ?>/admin/attendance/attendance.php?schedule_id=<?= $s['id'] ?>&batch_id=<?= urlencode($s['batch_id']) ?>&date=<?= $s['schedule_date'] ?>"
                                   title="Mark Attendance" class="action-btn text-green-500 hover:text-green-700">
                                    <i class="ri-checkbox-circle-line text-lg"></i>
                                </a>
                                <a href="<?= $B ?>/admin/schedule/edit_schedule.php?id=<?= $s['id'] ?>"
                                   title="Edit" class="action-btn text-[#E87F24] hover:text-orange-700">
                                    <i class="ri-edit-line text-lg"></i>
                                </a>
                                <button onclick="confirmDelete(<?= $s['id'] ?>, '<?= sanitize($s['topic']) ?>')"
                                        title="Cancel" class="action-btn text-red-400 hover:text-red-600">
                                    <i class="ri-close-circle-line text-lg"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="flex items-center justify-between px-5 py-4 border-t border-gray-100">
            <p class="text-sm text-gray-500">Showing <?= $offset + 1 ?>–<?= min($offset + $limit, $total) ?> of <?= $total ?></p>
            <div class="flex gap-1">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&batch_id=<?= urlencode($bFilter) ?>&date=<?= urlencode($dFilter) ?>"
                   class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-medium transition
                          <?= $p === $page ? 'bg-[#E87F24] text-white' : 'text-gray-500 hover:bg-orange-50' ?>">
                    <?= $p ?>
                </a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Modal -->
<div id="deleteModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6">
        <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="ri-close-circle-line text-2xl text-red-500"></i>
        </div>
        <h3 class="text-lg font-bold text-gray-800 text-center mb-1">Cancel Session?</h3>
        <p class="text-sm text-gray-500 text-center mb-6">Mark <strong id="delTopic"></strong> as cancelled?</p>
        <div class="flex gap-3">
            <button onclick="closeModal()" class="flex-1 px-4 py-2.5 border border-gray-200 rounded-xl text-gray-600 text-sm font-medium hover:bg-gray-50 transition">Keep</button>
            <a id="deleteLink" href="#" class="flex-1 px-4 py-2.5 bg-red-500 hover:bg-red-600 rounded-xl text-white text-sm font-semibold text-center transition">Cancel Session</a>
        </div>
    </div>
</div>

<script>
function confirmDelete(id, topic) {
    document.getElementById('delTopic').textContent = topic;
    document.getElementById('deleteLink').href = '<?= $B ?>/admin/schedule/delete_schedule.php?id=' + id;
    document.getElementById('deleteModal').classList.remove('hidden');
}
function closeModal() { document.getElementById('deleteModal').classList.add('hidden'); }
document.getElementById('deleteModal').addEventListener('click', e => { if (e.target === document.getElementById('deleteModal')) closeModal(); });
</script>

</div></div>
<?php require_once __DIR__ . '/../../includes/functions.php'; // ensure footer close tags not needed — sidebar opens divs ?>
