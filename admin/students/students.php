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
$page   = max(1, (int) ($_GET['page'] ?? 1));
$limit  = 15;
$offset = ($page - 1) * $limit;

// ── Query ──────────────────────────────────────────────────────────────────
$where  = '';
$params = [];

if ($search !== '') {
    $where    = "AND (sd.full_name LIKE ? OR sd.phone LIKE ? OR sd.student_id LIKE ? OR u.email LIKE ?)";
    $like     = '%' . $search . '%';
    $params   = [$like, $like, $like, $like];
}

$countSql = "SELECT COUNT(*) FROM student_details sd
             JOIN users u ON u.id = sd.user_id
             WHERE u.role = 'student' $where";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $limit));

$sql = "SELECT sd.id, sd.student_id, sd.full_name, sd.phone, sd.stream,
               sd.current_status, sd.enrollment_date, u.email, u.id AS user_id
        FROM student_details sd
        JOIN users u ON u.id = sd.user_id
        WHERE u.role = 'student' $where
        ORDER BY sd.id DESC
        LIMIT ? OFFSET ?";

$stmt = $db->prepare($sql);
$stmt->execute([...$params, $limit, $offset]);
$students = $stmt->fetchAll();

// ── Status badge helper ────────────────────────────────────────────────────
function statusBadge(string $status): string {
    $map = [
        'active'      => ['bg' => 'bg-green-100',  'text' => 'text-green-700',  'dot' => 'bg-green-500',  'label' => 'Active'],
        'dropped'     => ['bg' => 'bg-red-100',    'text' => 'text-red-700',    'dot' => 'bg-red-500',    'label' => 'Dropped'],
        'on_hold'     => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-700', 'dot' => 'bg-yellow-500', 'label' => 'On Hold'],
        'transferred' => ['bg' => 'bg-blue-100',   'text' => 'text-blue-700',   'dot' => 'bg-blue-500',   'label' => 'Transferred'],
        'completed'   => ['bg' => 'bg-purple-100', 'text' => 'text-purple-700', 'dot' => 'bg-purple-500', 'label' => 'Completed'],
    ];
    $s = $map[$status] ?? ['bg' => 'bg-gray-100', 'text' => 'text-gray-600', 'dot' => 'bg-gray-400', 'label' => ucfirst($status)];
    return "<span class=\"inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold {$s['bg']} {$s['text']}\">
                <span class=\"w-1.5 h-1.5 rounded-full {$s['dot']}\"></span>{$s['label']}
            </span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students — GyanSetu</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #FEFDDF; }
        .btn-primary { background: linear-gradient(135deg,#E87F24,#FFC81E); }
        .btn-primary:hover { opacity: .9; transform: translateY(-1px); }
        tr:hover td { background: #fff8f0; }
        .action-btn { transition: all .2s; }
        .action-btn:hover { transform: scale(1.15); }
        .table-wrap { border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,.07); }
    </style>
</head>
<body class="flex">
    <?php include __DIR__ . '/../sidebar.php'; ?>

    <main class="flex-1 p-6 md:p-8 ml-0 md:ml-64 min-h-screen">

        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Students</h1>
                <p class="text-sm text-gray-500 mt-0.5">
                    <?= number_format($total) ?> student<?= $total !== 1 ? 's' : '' ?> registered
                </p>
            </div>
            <a href="../student/add_student.php"
               class="btn-primary inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-white font-semibold text-sm shadow transition">
                <i class="ri-user-add-line"></i> Add Student
            </a>
        </div>

        <!-- Flash -->
        <?php renderFlashMessages(); ?>

        <!-- Search bar -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-6">
            <form method="GET" class="flex gap-3">
                <div class="relative flex-1">
                    <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="text" name="search" value="<?= sanitize($search) ?>"
                           placeholder="Search by name, phone, student ID or email…"
                           class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-[#E87F24] focus:ring-2 focus:ring-orange-100 transition">
                </div>
                <button type="submit"
                        class="btn-primary px-5 py-2.5 rounded-xl text-white text-sm font-semibold shadow transition">
                    Search
                </button>
                <?php if ($search): ?>
                    <a href="students.php"
                       class="px-4 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-600 hover:bg-gray-50 transition">
                        Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Table -->
        <div class="table-wrap bg-white">
            <?php if (empty($students)): ?>
                <div class="flex flex-col items-center justify-center py-20 text-gray-400">
                    <i class="ri-user-search-line text-5xl mb-3 text-[#E87F24] opacity-40"></i>
                    <p class="font-medium">No students found</p>
                    <?php if ($search): ?>
                        <p class="text-sm mt-1">Try a different search term</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gradient-to-r from-[#E87F24] to-[#FFC81E] text-white">
                                <th class="px-5 py-4 text-left font-semibold">#</th>
                                <th class="px-5 py-4 text-left font-semibold">Student ID</th>
                                <th class="px-5 py-4 text-left font-semibold">Name</th>
                                <th class="px-5 py-4 text-left font-semibold">Phone</th>
                                <th class="px-5 py-4 text-left font-semibold">Stream</th>
                                <th class="px-5 py-4 text-left font-semibold">Enrolled</th>
                                <th class="px-5 py-4 text-left font-semibold">Status</th>
                                <th class="px-5 py-4 text-center font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php foreach ($students as $i => $s): ?>
                            <tr class="transition-colors duration-150">
                                <td class="px-5 py-3.5 text-gray-400 font-mono text-xs">
                                    <?= $offset + $i + 1 ?>
                                </td>
                                <td class="px-5 py-3.5">
                                    <span class="font-mono text-xs bg-orange-50 text-[#E87F24] px-2 py-0.5 rounded-lg font-semibold">
                                        <?= sanitize($s['student_id']) ?>
                                    </span>
                                </td>
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-[#E87F24] to-[#FFC81E] flex items-center justify-center text-white font-bold text-xs flex-shrink-0">
                                            <?= strtoupper(mb_substr($s['full_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-800"><?= sanitize($s['full_name']) ?></p>
                                            <p class="text-xs text-gray-400"><?= sanitize($s['email']) ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-3.5 text-gray-600"><?= sanitize($s['phone'] ?? '—') ?></td>
                                <td class="px-5 py-3.5 text-gray-600"><?= sanitize($s['stream']) ?></td>
                                <td class="px-5 py-3.5 text-gray-500 text-xs">
                                    <?= $s['enrollment_date'] ? date('d M Y', strtotime($s['enrollment_date'])) : '—' ?>
                                </td>
                                <td class="px-5 py-3.5">
                                    <?= statusBadge($s['current_status'] ?? 'active') ?>
                                </td>
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center justify-center gap-3">
                                        <a href="../student/view_student.php?id=<?= (int)$s['user_id'] ?>"
                                           title="View" class="action-btn text-[#73A5CA] hover:text-blue-700">
                                            <i class="ri-eye-line text-lg"></i>
                                        </a>
                                        <a href="../student/edit_student.php?id=<?= (int)$s['user_id'] ?>"
                                           title="Edit" class="action-btn text-[#E87F24] hover:text-orange-700">
                                            <i class="ri-edit-line text-lg"></i>
                                        </a>
                                        <button onclick="confirmDelete(<?= (int)$s['user_id'] ?>, '<?= sanitize($s['full_name']) ?>')"
                                                title="Delete" class="action-btn text-red-400 hover:text-red-600">
                                            <i class="ri-delete-bin-line text-lg"></i>
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
                    <p class="text-sm text-gray-500">
                        Showing <?= $offset + 1 ?>–<?= min($offset + $limit, $total) ?> of <?= $total ?>
                    </p>
                    <div class="flex gap-1">
                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <a href="?page=<?= $p ?>&search=<?= urlencode($search) ?>"
                               class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-medium transition
                                      <?= $p === $page ? 'bg-[#E87F24] text-white shadow' : 'text-gray-500 hover:bg-orange-50' ?>">
                                <?= $p ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    </main>

    <!-- Delete Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6 transform transition-all">
            <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="ri-delete-bin-line text-2xl text-red-500"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-800 text-center mb-1">Delete Student?</h3>
            <p class="text-sm text-gray-500 text-center mb-6">
                This will permanently remove <strong id="deleteName"></strong> and all their data.
            </p>
            <div class="flex gap-3">
                <button onclick="closeModal()"
                        class="flex-1 px-4 py-2.5 border border-gray-200 rounded-xl text-gray-600 text-sm font-medium hover:bg-gray-50 transition">
                    Cancel
                </button>
                <a id="deleteLink" href="#"
                   class="flex-1 px-4 py-2.5 bg-red-500 hover:bg-red-600 rounded-xl text-white text-sm font-semibold text-center transition">
                    Delete
                </a>
            </div>
        </div>
    </div>

    <script>
        function confirmDelete(id, name) {
            document.getElementById('deleteName').textContent = name;
            document.getElementById('deleteLink').href = '../student/delete_student.php?id=' + id;
            document.getElementById('deleteModal').classList.remove('hidden');
        }
        function closeModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
    </script>
</body>
</html>
