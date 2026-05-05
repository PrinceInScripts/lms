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

$bFilter   = trim($_GET['batch_id'] ?? '');
$dFilter   = trim($_GET['date']     ?? '');
$stFilter  = trim($_GET['status']   ?? '');
$page      = max(1, (int)($_GET['page'] ?? 1));
$limit     = 20;
$offset    = ($page - 1) * $limit;

$batches = $db->query("SELECT batch_id, batch_name FROM batches ORDER BY batch_name")->fetchAll();

// Build query
$where  = '1=1';
$params = [];
if ($bFilter) { $where .= ' AND a.batch_id = ?';        $params[] = $bFilter; }
if ($dFilter) { $where .= ' AND a.attendance_date = ?';  $params[] = $dFilter; }
if ($stFilter && in_array($stFilter,['Present','Absent','Late'])) {
    $where .= ' AND a.status = ?'; $params[] = $stFilter;
}

// Summary stats for the current filter
$statStmt = $db->prepare("
    SELECT status, COUNT(*) AS cnt
    FROM attendance a
    WHERE $where
    GROUP BY status
");
$statStmt->execute($params);
$stats = ['Present'=>0,'Absent'=>0,'Late'=>0];
foreach ($statStmt->fetchAll() as $row) { $stats[$row['status']] = (int)$row['cnt']; }
$totalRecords = array_sum($stats);

$cntStmt = $db->prepare("SELECT COUNT(*) FROM attendance a WHERE $where");
$cntStmt->execute($params);
$total      = (int) $cntStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $limit));

$stmt = $db->prepare("
    SELECT a.*, sd.full_name, b.batch_name
    FROM attendance a
    JOIN student_details sd ON sd.student_id = a.student_id
    JOIN batches b ON b.batch_id = a.batch_id
    WHERE $where
    ORDER BY a.attendance_date DESC, b.batch_name, sd.full_name
    LIMIT ? OFFSET ?
");
$stmt->execute([...$params, $limit, $offset]);
$records = $stmt->fetchAll();

$statusColors = [
    'Present' => ['bg-green-100','text-green-700','bg-green-500'],
    'Absent'  => ['bg-red-100',  'text-red-700',  'bg-red-500'],
    'Late'    => ['bg-yellow-100','text-yellow-700','bg-yellow-500'],
];
?>
<?php include __DIR__ . '/../sidebar.php'; ?>
<style>
    body{background:#FEFDDF}
    .btn-primary{background:linear-gradient(135deg,#E87F24,#FFC81E);transition:all .2s}.btn-primary:hover{opacity:.9;transform:translateY(-1px)}
    .card{background:white;border-radius:20px;box-shadow:0 2px 16px rgba(0,0,0,.06)}
    input:focus,select:focus{outline:none;border-color:#E87F24!important;box-shadow:0 0 0 3px rgba(232,127,36,.12)}
    tr:hover td{background:#fff8f0}
    .action-btn{transition:all .2s}.action-btn:hover{transform:scale(1.15)}
    .field-input{border:1px solid #e5e7eb;border-radius:.75rem;padding:.6rem 1rem;font-size:.875rem;transition:border-color .2s}
</style>

<div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Attendance Records</h1>
            <p class="text-sm text-gray-500 mt-0.5"><?= number_format($total) ?> record<?= $total !== 1 ? 's' : '' ?></p>
        </div>
        <a href="<?= $B ?>/admin/attendance/attendance.php"
           class="btn-primary inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-white font-semibold text-sm shadow">
            <i class="ri-add-circle-line"></i> Mark Attendance
        </a>
    </div>

    <?php renderFlashMessages(); ?>

    <!-- Summary stats -->
    <?php if ($bFilter || $dFilter): ?>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
        <div class="card p-4 text-center">
            <p class="text-xs text-gray-400 font-medium">Total Records</p>
            <p class="text-2xl font-bold text-gray-700 mt-1"><?= $totalRecords ?></p>
        </div>
        <div class="card p-4 text-center border-l-4 border-green-400">
            <p class="text-xs text-gray-400 font-medium">Present</p>
            <p class="text-2xl font-bold text-green-600 mt-1"><?= $stats['Present'] ?></p>
            <?php if ($totalRecords > 0): ?>
            <p class="text-xs text-gray-400"><?= round($stats['Present']/$totalRecords*100) ?>%</p>
            <?php endif; ?>
        </div>
        <div class="card p-4 text-center border-l-4 border-red-400">
            <p class="text-xs text-gray-400 font-medium">Absent</p>
            <p class="text-2xl font-bold text-red-500 mt-1"><?= $stats['Absent'] ?></p>
            <?php if ($totalRecords > 0): ?>
            <p class="text-xs text-gray-400"><?= round($stats['Absent']/$totalRecords*100) ?>%</p>
            <?php endif; ?>
        </div>
        <div class="card p-4 text-center border-l-4 border-yellow-400">
            <p class="text-xs text-gray-400 font-medium">Late</p>
            <p class="text-2xl font-bold text-yellow-600 mt-1"><?= $stats['Late'] ?></p>
            <?php if ($totalRecords > 0): ?>
            <p class="text-xs text-gray-400"><?= round($stats['Late']/$totalRecords*100) ?>%</p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card p-4 mb-5">
        <form method="GET" class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Batch</label>
                <select name="batch_id" class="field-input bg-white">
                    <option value="">All Batches</option>
                    <?php foreach ($batches as $b): ?>
                        <option value="<?= sanitize($b['batch_id']) ?>" <?= $bFilter === $b['batch_id'] ? 'selected' : '' ?>>
                            <?= sanitize($b['batch_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Date</label>
                <input type="date" name="date" value="<?= sanitize($dFilter) ?>" class="field-input">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Status</label>
                <select name="status" class="field-input bg-white">
                    <option value="">All Status</option>
                    <option value="Present" <?= $stFilter==='Present'?'selected':'' ?>>Present</option>
                    <option value="Absent"  <?= $stFilter==='Absent' ?'selected':'' ?>>Absent</option>
                    <option value="Late"    <?= $stFilter==='Late'   ?'selected':'' ?>>Late</option>
                </select>
            </div>
            <button type="submit" class="btn-primary px-5 py-2.5 rounded-xl text-white text-sm font-semibold shadow">Filter</button>
            <?php if ($bFilter || $dFilter || $stFilter): ?>
                <a href="<?= $B ?>/admin/attendance/view_attendance.php"
                   class="px-4 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-600 hover:bg-gray-50 transition">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Table -->
    <div class="card overflow-hidden">
        <?php if (empty($records)): ?>
        <div class="flex flex-col items-center justify-center py-20 text-gray-400">
            <i class="ri-calendar-check-line text-5xl mb-3 text-[#E87F24] opacity-40"></i>
            <p class="font-medium">No attendance records found</p>
            <?php if ($bFilter || $dFilter || $stFilter): ?>
                <p class="text-sm mt-1">Try adjusting your filters</p>
            <?php else: ?>
                <a href="<?= $B ?>/admin/attendance/attendance.php"
                   class="mt-3 text-sm text-[#E87F24] hover:underline font-medium">Mark attendance first</a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gradient-to-r from-[#E87F24] to-[#FFC81E] text-white text-left">
                        <th class="px-5 py-3.5 font-semibold">Student</th>
                        <th class="px-5 py-3.5 font-semibold">Batch</th>
                        <th class="px-5 py-3.5 font-semibold">Date</th>
                        <th class="px-5 py-3.5 font-semibold text-center">Status</th>
                        <th class="px-5 py-3.5 font-semibold text-center">Edit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($records as $r): ?>
                    <?php [$sBg,$sTxt,$sDot] = $statusColors[$r['status']] ?? ['bg-gray-100','text-gray-600','bg-gray-400']; ?>
                    <tr class="transition-colors duration-150">
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-2.5">
                                <div class="w-7 h-7 rounded-full bg-gradient-to-br from-[#E87F24] to-[#FFC81E] flex items-center justify-center text-white font-bold text-xs flex-shrink-0">
                                    <?= strtoupper(mb_substr($r['full_name'],0,1)) ?>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-800"><?= sanitize($r['full_name']) ?></p>
                                    <p class="text-xs text-gray-400 font-mono"><?= sanitize($r['student_id']) ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="text-xs bg-orange-50 text-[#E87F24] font-semibold px-2 py-0.5 rounded-lg">
                                <?= sanitize($r['batch_name']) ?>
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-gray-600">
                            <?= date('d M Y', strtotime($r['attendance_date'])) ?>
                            <span class="text-xs text-gray-400 block"><?= date('D', strtotime($r['attendance_date'])) ?></span>
                        </td>
                        <td class="px-5 py-3.5 text-center">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold <?= $sBg ?> <?= $sTxt ?>">
                                <span class="w-1.5 h-1.5 rounded-full <?= $sDot ?>"></span>
                                <?= $r['status'] ?>
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-center">
                            <button onclick="openEdit(<?= $r['id'] ?>,'<?= sanitize($r['full_name']) ?>','<?= $r['status'] ?>')"
                                    class="action-btn text-[#E87F24] hover:text-orange-700" title="Edit">
                                <i class="ri-edit-line text-base"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="flex items-center justify-between px-5 py-4 border-t border-gray-100">
            <p class="text-sm text-gray-500">Showing <?= $offset+1 ?>–<?= min($offset+$limit,$total) ?> of <?= $total ?></p>
            <div class="flex gap-1">
                <?php for ($p=1;$p<=$totalPages;$p++): ?>
                <a href="?page=<?=$p?>&batch_id=<?=urlencode($bFilter)?>&date=<?=urlencode($dFilter)?>&status=<?=urlencode($stFilter)?>"
                   class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-medium transition <?= $p===$page?'bg-[#E87F24] text-white':'text-gray-500 hover:bg-orange-50' ?>">
                    <?= $p ?>
                </a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-1">Edit Attendance</h3>
        <p class="text-sm text-gray-500 mb-5">Update status for <strong id="editStudentName"></strong></p>
        <form method="POST" action="<?= $B ?>/admin/attendance/update_attendance.php">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <input type="hidden" name="id" id="editId">
            <input type="hidden" name="redirect" value="<?= sanitize($_SERVER['REQUEST_URI']) ?>">
            <div class="flex gap-3 mb-5">
                <?php foreach (['Present'=>'green','Absent'=>'red','Late'=>'yellow'] as $s => $c): ?>
                <label class="flex-1 text-center py-2.5 rounded-xl border-2 cursor-pointer font-semibold text-sm transition border-gray-200 hover:border-<?= $c ?>-400 status-label" data-status="<?= $s ?>">
                    <input type="radio" name="status" value="<?= $s ?>" class="hidden"> <?= $s ?>
                </label>
                <?php endforeach; ?>
            </div>
            <div class="flex gap-3">
                <button type="button" onclick="closeEdit()" class="flex-1 py-2.5 border border-gray-200 rounded-xl text-gray-600 text-sm font-medium hover:bg-gray-50 transition">Cancel</button>
                <button type="submit" class="flex-1 py-2.5 bg-[#E87F24] hover:opacity-90 rounded-xl text-white text-sm font-semibold transition">Update</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEdit(id, name, currentStatus) {
    document.getElementById('editId').value = id;
    document.getElementById('editStudentName').textContent = name;
    var modal = document.getElementById('editModal');
    modal.classList.remove('hidden');
    // Select current status radio
    var labels = modal.querySelectorAll('.status-label');
    labels.forEach(function(lbl) {
        var inp = lbl.querySelector('input');
        var isActive = inp.value === currentStatus;
        inp.checked = isActive;
        var colorMap = {Present:'border-green-500 bg-green-50 text-green-700', Absent:'border-red-500 bg-red-50 text-red-700', Late:'border-yellow-500 bg-yellow-50 text-yellow-700'};
        lbl.className = 'flex-1 text-center py-2.5 rounded-xl border-2 cursor-pointer font-semibold text-sm transition status-label ' +
            (isActive ? colorMap[inp.value] : 'border-gray-200 text-gray-600 hover:bg-gray-50');
        lbl.dataset.status = inp.value;
    });
    labels.forEach(function(lbl) {
        lbl.addEventListener('click', function() {
            var colorMap = {Present:'border-green-500 bg-green-50 text-green-700',Absent:'border-red-500 bg-red-50 text-red-700',Late:'border-yellow-500 bg-yellow-50 text-yellow-700'};
            labels.forEach(function(l) { l.querySelector('input').checked=false; l.className='flex-1 text-center py-2.5 rounded-xl border-2 cursor-pointer font-semibold text-sm transition status-label border-gray-200 text-gray-600 hover:bg-gray-50'; });
            this.querySelector('input').checked=true;
            this.className='flex-1 text-center py-2.5 rounded-xl border-2 cursor-pointer font-semibold text-sm transition status-label '+colorMap[this.dataset.status];
        });
    });
}
function closeEdit() { document.getElementById('editModal').classList.add('hidden'); }
document.getElementById('editModal').addEventListener('click', function(e){ if(e.target===this)closeEdit(); });
</script>
</div></div>
